<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Mail\SubscriptionConfirmed;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConfirmerPaiementWebhookAction
{
    public function execute(Subscription $subscription): void
    {
        $entreprise = Entreprise::with(['users' => fn ($q) => $q->orderBy('id')])
            ->findOrFail($subscription->entreprise_id);

        DB::transaction(function () use ($subscription, $entreprise): void {
            $subscription->update(['status' => 'confirmed']);
            $entreprise->update([
                'plan' => $subscription->plan,
                'plan_expires_at' => $subscription->expires_at,
            ]);
        });

        $this->sendConfirmationEmail($subscription, $entreprise);
    }

    private function sendConfirmationEmail(Subscription $subscription, Entreprise $entreprise): void
    {
        $adminUser = $entreprise->users
            ->firstWhere('role', 'super_admin')
            ?? $entreprise->users->first();

        if (! $adminUser?->email) {
            return;
        }

        try {
            Mail::to($adminUser->email)->later(
                now()->addSeconds(5),
                new SubscriptionConfirmed(
                    userName: $adminUser->name,
                    entrepriseName: $entreprise->name,
                    plan: $subscription->plan,
                    expireDate: $subscription->expires_at->format('d/m/Y'),
                    amount: (float) $subscription->amount,
                    isTrial: false,
                    trialDays: 0,
                    appUrl: (string) config('app.url'),
                )
            );
        } catch (\Exception $e) {
            Log::warning('Email abonnement confirmé échoué: '.$e->getMessage());
        }
    }
}
