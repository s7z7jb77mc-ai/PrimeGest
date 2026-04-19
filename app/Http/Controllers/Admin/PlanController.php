<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
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
            ->where(function($q) {
                $q->where('is_super_admin', true)
                  ->orWhere('role', 'super_admin');
            })
            ->orderBy('id')
            ->first();

        // Fallback : premier utilisateur de l'entreprise
        if (!$adminUser) {
            $adminUser = $entreprise->users()->orderBy('id')->first();
        }

        if ($adminUser?->email) {
            try {
                $planLabel = match($request->plan) {
                    'premium' => 'Premium',
                    'pro'     => 'Pro',
                    default   => 'Free',
                };
                $expireStr = $expiresAt->format('d/m/Y');

                $subject = $isTrial
                    ? "Votre essai gratuit PrimeGest {$planLabel} est activé !"
                    : "Votre abonnement PrimeGest {$planLabel} est confirmé ✓";

                $body = $isTrial
                    ? "Bonjour {$adminUser->name},\n\nBonne nouvelle ! Votre essai gratuit du plan {$planLabel} pour « {$entreprise->name} » a été activé.\n\nDurée : {$trialDays} jours\nExpiration : {$expireStr}\n\nProfitez de toutes les fonctionnalités premium pendant cette période.\n\nBonne découverte !\n\n— L'équipe PrimeGest\nhttps://primegest.app"
                    : "Bonjour {$adminUser->name},\n\nVotre abonnement au plan {$planLabel} pour « {$entreprise->name} » a été confirmé avec succès.\n\nDébut : " . now()->format('d/m/Y') . "\nExpiration : {$expireStr}\nMontant : {$amount} \$/mois\n\nMerci pour votre confiance.\n\n— L'équipe PrimeGest\nhttps://primegest.app";

                Mail::raw($body, function ($message) use ($adminUser, $subject) {
                    $message
                        ->to($adminUser->email, $adminUser->name)
                        ->subject($subject)
                        ->replyTo(config('mail.from.address'));
                });
            } catch (\Exception $e) {
                Log::warning('Email confirmation abonnement échoué: ' . $e->getMessage());
            }
        }

        $msg = $isTrial
            ? "✅ Essai {$trialDays} jours activé pour {$entreprise->name}"
            : "✅ Plan {$request->plan} activé pour {$entreprise->name}";

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
