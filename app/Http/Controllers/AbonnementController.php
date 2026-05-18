<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Subscription\InitierPaiementAction;
use App\Http\Requests\InitierPaiementRequest;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AbonnementController extends Controller
{
    public function index(Request $request): Response
    {
        $entreprise = $request->user()->entreprise;
        abort_if($entreprise === null, 403, 'Entreprise introuvable.');

        $joursRestants = $entreprise->plan_expires_at
            ? (int) max(0, now()->diffInDays($entreprise->plan_expires_at, false))
            : null;

        $historique = Subscription::where('entreprise_id', $entreprise->id)
            ->latest()->take(10)->get()
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

    public function initierPaiement(InitierPaiementRequest $request, InitierPaiementAction $action): JsonResponse
    {
        $entreprise = $request->user()->entreprise;
        abort_if($entreprise === null, 403, 'Entreprise introuvable.');

        try {
            $subscription = $action->execute(
                entreprise: $entreprise,
                plan: $request->validated('plan'),
                duree: (int) $request->validated('duree'),
                phone: $request->validated('phone'),
                devise: $request->validated('devise'),
            );
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Le paiement n\'a pas pu être initié. Vérifiez votre numéro et réessayez.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'reference' => $subscription->payment_reference,
            'message' => 'Confirmez le paiement sur votre téléphone.',
        ]);
    }

    public function statut(Request $request, string $reference): JsonResponse
    {
        $entreprise = $request->user()->entreprise;
        abort_if($entreprise === null, 403, 'Entreprise introuvable.');

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
}
