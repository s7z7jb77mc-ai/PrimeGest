<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
