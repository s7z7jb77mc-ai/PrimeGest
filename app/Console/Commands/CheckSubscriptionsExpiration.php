<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SubscriptionExpired;
use App\Mail\SubscriptionExpiringSoon;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptionsExpiration extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Downgrade les abonnements expirés et envoie les emails d\'avertissement';

    public function handle(): int
    {
        $this->downgradeExpired();
        $this->sendExpiringWarnings();

        return Command::SUCCESS;
    }

    private function downgradeExpired(): void
    {
        $expiredEntreprises = Entreprise::query()
            ->where('plan', '!=', 'free')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->get();

        foreach ($expiredEntreprises as $entreprise) {
            $planAncien = $entreprise->plan;

            $entreprise->update([
                'plan' => 'free',
                'plan_expires_at' => null,
            ]);

            Subscription::where('entreprise_id', $entreprise->id)
                ->where('status', 'confirmed')
                ->update(['status' => 'expired']);

            $adminUser = $entreprise->users()
                ->where('role', 'super_admin')
                ->orderBy('id')
                ->first()
                ?? $entreprise->users()->orderBy('id')->first();

            if ($adminUser?->email) {
                try {
                    Mail::to($adminUser->email, $adminUser->name)
                        ->send(new SubscriptionExpired(
                            userName: $adminUser->name,
                            entrepriseName: $entreprise->name,
                            planAncien: $planAncien,
                            appUrl: config('app.url'),
                        ));
                } catch (\Exception $e) {
                    $this->warn("Email expiration échoué pour {$entreprise->name} : {$e->getMessage()}");
                }
            }

            $this->info("Downgrade : {$entreprise->name} ({$planAncien} → free)");
        }
    }

    private function sendExpiringWarnings(): void
    {
        $soonEntreprises = Entreprise::query()
            ->where('plan', '!=', 'free')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '>', now())
            ->where('plan_expires_at', '<=', now()->addDays(7))
            ->get();

        foreach ($soonEntreprises as $entreprise) {
            // Éviter le double envoi dans la même journée
            $alreadySent = Subscription::where('entreprise_id', $entreprise->id)
                ->where('status', 'confirmed')
                ->whereNotNull('warning_sent_at')
                ->whereDate('warning_sent_at', today())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $joursRestants = (int) max(0, now()->diffInDays($entreprise->plan_expires_at, false));

            $adminUser = $entreprise->users()
                ->where('role', 'super_admin')
                ->orderBy('id')
                ->first()
                ?? $entreprise->users()->orderBy('id')->first();

            if ($adminUser?->email) {
                try {
                    Mail::to($adminUser->email, $adminUser->name)
                        ->send(new SubscriptionExpiringSoon(
                            userName: $adminUser->name,
                            entrepriseName: $entreprise->name,
                            plan: $entreprise->plan,
                            expireDate: $entreprise->plan_expires_at->format('d/m/Y'),
                            joursRestants: $joursRestants,
                            appUrl: config('app.url'),
                        ));

                    Subscription::where('entreprise_id', $entreprise->id)
                        ->where('status', 'confirmed')
                        ->update(['warning_sent_at' => now()]);

                    $this->info("Avertissement envoyé : {$entreprise->name} (expire dans {$joursRestants}j)");
                } catch (\Exception $e) {
                    $this->warn("Email avertissement échoué pour {$entreprise->name} : {$e->getMessage()}");
                }
            }
        }
    }
}
