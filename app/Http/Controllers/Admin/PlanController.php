<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        $entreprises = Entreprise::orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'plan' => $e->plan,
                'plan_expires_at' => $e->plan_expires_at
                    ? \Carbon\Carbon::parse($e->plan_expires_at)->format('d/m/Y')
                    : null,
                'created_at' => $e->created_at->format('d/m/Y'),
            ]);

        $subscriptions = Subscription::with('entreprise')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'entreprise' => $s->entreprise->name ?? '—',
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
            ->map(fn ($s) => [
                'id' => $s->id,
                'entreprise' => $s->entreprise->name ?? '—',
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

    public function activate(Request $request, Entreprise $entreprise): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'plan' => 'required|in:free,premium,pro',
            'duration' => 'required|integer|min:1|max:12',
            'trial_days' => 'nullable|integer|min:0|max:30',
        ]);

        $trialDays = intval($request->trial_days ?? 0);
        $isTrial = $trialDays > 0;
        $amount = $isTrial ? 0 : ($request->plan === 'premium' ? 7 : 10);
        $method = $isTrial ? 'trial' : ($request->payment_method ?? 'manual');
        $ref = $isTrial ? "trial-{$trialDays}j" : ($request->payment_reference ?? 'admin');

        (new \App\Actions\Subscription\ActivateSubscriptionAction)->execute(
            entreprise: $entreprise,
            plan: $request->plan,
            durationMonths: (int) $request->duration,
            trialDays: $trialDays,
            amount: $amount,
            paymentMethod: $method,
            paymentReference: $ref,
            confirmedBy: auth('owner')->id() ?? auth()->id(),
        );

        $msg = $isTrial
            ? "Essai {$trialDays} jours activé pour {$entreprise->name}"
            : "Plan {$request->plan} activé pour {$entreprise->name}";

        return back()->with('success', $msg);
    }

    public function downgrade(Entreprise $entreprise)
    {
        $entreprise->update([
            'plan' => 'free',
            'plan_expires_at' => null,
        ]);

        return back()->with('success', "Retour au plan Free pour {$entreprise->name}");
    }
}
