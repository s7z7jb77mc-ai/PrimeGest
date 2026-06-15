<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Subscription\ActivateSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\ActivateSubscriptionRequest;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        $entreprises = Entreprise::orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->map(fn (Entreprise $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'plan' => $e->plan,
                'plan_expires_at' => $e->plan_expires_at?->format('d/m/Y'),
                'created_at' => $e->created_at->format('d/m/Y'),
            ]);

        $subscriptions = Subscription::with('entreprise')
            ->where('status', 'pending')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'entreprise' => $s->entreprise?->name ?? '—',
                'plan' => $s->plan,
                'amount' => $s->amount,
                'payment_method' => $s->payment_method,
                'payment_reference' => $s->payment_reference,
                'starts_at' => $s->starts_at?->format('d/m/Y'),
                'expires_at' => $s->expires_at?->format('d/m/Y'),
                'created_at' => $s->created_at->format('d/m/Y H:i'),
            ]);

        $historique = Subscription::with('entreprise')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'entreprise' => $s->entreprise?->name ?? '—',
                'plan' => $s->plan,
                'amount' => $s->amount,
                'status' => $s->status,
                'payment_method' => $s->payment_method,
                'starts_at' => $s->starts_at?->format('d/m/Y'),
                'expires_at' => $s->expires_at?->format('d/m/Y'),
                'created_at' => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Plans', [
            'entreprises' => $entreprises,
            'subscriptions' => $subscriptions,
            'historique' => $historique,
        ]);
    }

    public function activate(ActivateSubscriptionRequest $request, Entreprise $entreprise): RedirectResponse
    {
        $data = $request->validated();
        $trialDays = (int) ($data['trial_days'] ?? 0);

        (new ActivateSubscriptionAction)->execute(
            entreprise: $entreprise,
            plan: $data['plan'],
            durationMonths: (int) $data['duration'],
            trialDays: $trialDays,
            paymentMethod: $data['payment_method'] ?? null,
            paymentReference: $data['payment_reference'] ?? null,
            confirmedBy: auth()->id(),
        );

        $msg = $trialDays > 0
            ? "Essai {$trialDays} jours activé pour {$entreprise->name}"
            : "Plan {$data['plan']} activé pour {$entreprise->name}";

        return back()->with('success', $msg);
    }

    public function downgrade(Entreprise $entreprise): RedirectResponse
    {
        $entreprise->update([
            'plan' => 'free',
            'plan_expires_at' => null,
        ]);

        return back()->with('success', "Retour au plan Free pour {$entreprise->name}");
    }

    public function destroyEntreprise(Entreprise $entreprise): RedirectResponse
    {
        $name = $entreprise->name;

        DB::transaction(function () use ($entreprise): void {
            $entreprise->delete();
        });

        return back()->with('success', "Entreprise « {$name} » supprimée.");
    }
}
