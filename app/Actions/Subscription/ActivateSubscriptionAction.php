<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Mail\SubscriptionConfirmed;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ActivateSubscriptionAction
{
    public function execute(
        Entreprise $entreprise,
        string $plan,
        int $durationMonths,
        int $trialDays = 0,
        ?float $amount = null,
        ?string $paymentMethod = null,
        ?string $paymentReference = null,
        ?int $confirmedBy = null,
    ): Subscription {
        $isTrial = $trialDays > 0;
        $startsAt = now();
        $expiresAt = $isTrial
            ? $startsAt->copy()->addDays($trialDays)
            : $startsAt->copy()->addMonths($durationMonths);

        // Valeurs par défaut métier si non fournies
        $amount ??= $isTrial ? 0.0 : (float) (config("plans.prices.{$plan}") ?? 0.0);
        $paymentMethod ??= $isTrial ? 'trial' : 'manual';
        $paymentReference ??= $isTrial ? "trial-{$trialDays}j" : 'admin';

        $subscription = DB::transaction(function () use (
            $entreprise, $plan, $expiresAt, $startsAt,
            $amount, $paymentMethod, $paymentReference, $confirmedBy
        ): Subscription {
            $entreprise->update([
                'plan' => $plan,
                'plan_expires_at' => $expiresAt,
            ]);

            return Subscription::create([
                'entreprise_id' => $entreprise->id,
                'plan' => $plan,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'status' => 'confirmed',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'confirmed_by' => $confirmedBy,
            ]);
        });

        Cache::forget("inertia.entreprise.{$entreprise->id}");
        Cache::forget("inertia.has_succursales.{$entreprise->id}");

        $adminUser = $entreprise->users()
            ->where('role', 'super_admin')
            ->orderBy('id')
            ->first()
            ?? $entreprise->users()->orderBy('id')->first();

        if ($adminUser?->email) {
            try {
                Mail::to($adminUser->email, $adminUser->name)
                    ->send(new SubscriptionConfirmed(
                        userName: $adminUser->name,
                        entrepriseName: $entreprise->name,
                        plan: $plan,
                        expireDate: $expiresAt->format('d/m/Y'),
                        amount: $amount,
                        isTrial: $isTrial,
                        trialDays: $trialDays,
                        appUrl: config('app.url'),
                    ));
            } catch (\Exception $e) {
                Log::warning('Email abonnement échoué: '.$e->getMessage());
            }
        }

        return $subscription;
    }
}
