<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\SubscriptionConfirmed;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        $entreprises = Entreprise::orderBy('created_at', 'desc')
            ->get()
            ->map(fn($e) => [
                'id'              => $e->id,
                'name'            => $e->name,
                'plan'            => $e->plan,
                'plan_expires_at' => $e->plan_expires_at
                    ? \Carbon\Carbon::parse($e->plan_expires_at)->format('d/m/Y')
                    : null,
                'created_at'      => $e->created_at->format('d/m/Y'),
            ]);

        $subscriptions = Subscription::with('entreprise')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn($s) => [
                'id'                => $s->id,
                'entreprise'        => $s->entreprise->name ?? '—',
                'plan'              => $s->plan,
                'amount'            => $s->amount,
                'payment_method'    => $s->payment_method,
                'payment_reference' => $s->payment_reference,
                'starts_at'         => $s->starts_at?->format('d/m/Y'),
                'expires_at'        => $s->expires_at?->format('d/m/Y'),
                'created_at'        => $s->created_at->format('d/m/Y H:i'),
            ]);

        $historique = Subscription::with('entreprise')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'entreprise'     => $s->entreprise->name ?? '—',
                'plan'           => $s->plan,
                'amount'         => $s->amount,
                'status'         => $s->status,
                'payment_method' => $s->payment_method,
                'starts_at'      => $s->starts_at?->format('d/m/Y'),
                'expires_at'     => $s->expires_at?->format('d/m/Y'),
                'created_at'     => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Plans', [
            'entreprises'   => $entreprises,
            'subscriptions' => $subscriptions,
            'historique'    => $historique,
        ]);
    }

    public function activate(Request $request, Entreprise $entreprise)
    {
        $request->validate([
            'plan'       => 'required|in:free,premium,pro',
            'duration'   => 'required|integer|min:1|max:12',
            'trial_days' => 'nullable|integer|min:0|max:30',
        ]);

        $trialDays = intval($request->trial_days ?? 0);
        $isTrial   = $trialDays > 0;

        $expiresAt = $isTrial
            ? now()->addDays($trialDays)
            : now()->addMonths((int) $request->duration);

        $amount = $isTrial ? 0 : ($request->plan === 'premium' ? 7 : 10);
        $method = $isTrial ? 'trial' : ($request->payment_method ?? 'manual');
        $ref    = $isTrial ? "trial-{$trialDays}j" : ($request->payment_reference ?? 'admin');

        $entreprise->update([
            'plan'            => $request->plan,
            'plan_expires_at' => $expiresAt,
        ]);

        Subscription::create([
            'entreprise_id'     => $entreprise->id,
            'plan'              => $request->plan,
            'amount'            => $amount,
            'payment_method'    => $method,
            'payment_reference' => $ref,
            'status'            => 'confirmed',
            'starts_at'         => now(),
            'expires_at'        => $expiresAt,
            'confirmed_by'      => auth('owner')->id(),
        ]);

        // ── Email au propriétaire de l'entreprise ──────────────
        // On récupère le super_admin de l'entreprise (le vrai propriétaire)
	$adminUser = $entreprise->users()
    ->where('role', 'super_admin')
    ->orderBy('id')
    ->first()
    ?? $entreprise->users()->orderBy('id')->first();

	Log::info('Tentative mail abo', [
    'entreprise' => $entreprise->name,
    'adminUser'  => $adminUser?->email ?? 'NULL',
    'plan'       => $request->plan,
]);
	if ($adminUser?->email) {
    try {
        Mail::to($adminUser->email, $adminUser->name)
            ->send(new SubscriptionConfirmed(
                userName:      $adminUser->name,
                entrepriseName: $entreprise->name,
                plan:          $request->plan,
                expireDate:    $expiresAt->format('d/m/Y'),
                amount:        $amount,
                isTrial:       $isTrial,
                trialDays:     $trialDays,
		appUrl:         config('app.url'),
            ));
	Log::info('Mail abo envoyé à ' . $adminUser->email);
    } catch (\Exception $e) {
        Log::warning('Email abonnement échoué: ' . $e->getMessage());
	}
	}

        $msg = $isTrial
            ? "Votre essai {$trialDays} jours activé pour {$entreprise->name}"
            : " Plan {$request->plan} activé pour {$entreprise->name}";

        return back()->with('success', $msg);
    }

    public function downgrade(Entreprise $entreprise)
    {
        $entreprise->update([
            'plan'            => 'free',
            'plan_expires_at' => null,
        ]);

        return back()->with('success', "Retour au plan Free pour {$entreprise->name}");
    }
}
