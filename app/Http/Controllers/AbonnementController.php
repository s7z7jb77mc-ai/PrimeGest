<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\NetikashService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AbonnementController extends Controller
{
    public function index(Request $request): Response
    {
        $entreprise = $request->user()->entreprise;

        $joursRestants = $entreprise->plan_expires_at
            ? (int) max(0, now()->diffInDays($entreprise->plan_expires_at, false))
            : null;

        $historique = Subscription::where('entreprise_id', $entreprise->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'plan' => $s->plan,
                'amount' => $s->amount,
                'status' => $s->status,
                'payment_method' => $s->payment_method,
                'payment_reference' => $s->payment_reference,
                'starts_at' => $s->starts_at?->format('d/m/Y'),
                'expires_at' => $s->expires_at?->format('d/m/Y'),
                'created_at' => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Abonnement/Index', [
            'plan' => $entreprise->plan,
            'plan_expires_at' => $entreprise->plan_expires_at?->toIso8601String(),
            'jours_restants' => $joursRestants,
            'historique' => $historique,
            'prices' => config('plans.prices', ['premium' => 7, 'pro' => 10]),
            'promo_prices' => config('plans.promotional_prices', []),
            'usd_to_cdf_rate' => config('services.netikash.usd_to_cdf_rate', 2800),
        ]);
    }

    public function initierPaiement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:premium,pro'],
            'duree' => ['required', 'integer', 'min:1', 'max:12'],
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'devise' => ['required', 'in:USD,CDF'],
        ]);

        $entreprise = $request->user()->entreprise;

        $pricing = app(PricingService::class);
        $montant = $pricing->calculate($data['plan'], (int) $data['duree'], $data['devise']);
        $reference = 'PG-'.$entreprise->id.'-'.strtoupper(Str::random(8));
        $expiresAt = now()->addMonths((int) $data['duree']);

        $subscription = Subscription::create([
            'entreprise_id' => $entreprise->id,
            'plan' => $data['plan'],
            'amount' => $montant,
            'payment_method' => 'netikash',
            'payment_reference' => $reference,
            'status' => 'pending',
            'starts_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        try {
            $netikash = app(NetikashService::class);
            $netikash->initiatePayment(
                phone: $data['phone'],
                amount: $montant,
                currency: $data['devise'],
                reference: $reference,
                description: 'Abonnement PrimeGest '.ucfirst($data['plan']).' '.$data['duree'].' mois',
            );
        } catch (\Throwable $e) {
            $subscription->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Le paiement n\'a pas pu être initié. Vérifiez votre numéro et réessayez.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'reference' => $reference,
            'message' => 'Confirmez le paiement sur votre téléphone.',
        ]);
    }

    public function statut(Request $request, string $reference): JsonResponse
    {
        $entreprise = $request->user()->entreprise;
        $subscription = Subscription::where('payment_reference', $reference)
            ->where('entreprise_id', $entreprise->id)
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'Référence introuvable.'], 404);
        }

        return response()->json([
            'status' => $subscription->status,
            'plan' => $subscription->plan,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
        ]);
    }

    public function storeDemande(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:premium,pro'],
            'duree' => ['required', 'integer', 'min:1', 'max:12'],
            'payment_method' => ['required', 'string', 'max:100'],
            'payment_reference' => ['required', 'string', 'max:255'],
        ]);

        $entreprise = $request->user()->entreprise;
        $expiresAt = now()->addMonths((int) $data['duree']);

        Subscription::create([
            'entreprise_id' => $entreprise->id,
            'plan' => $data['plan'],
            'amount' => config("plans.prices.{$data['plan']}", 0) * (int) $data['duree'],
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'],
            'status' => 'pending',
            'starts_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return back()->with('success', 'Votre demande a été soumise. L\'équipe PrimeGest la confirmera sous 24h.');
    }
}
