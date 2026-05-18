<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionConfirmed;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $secret = (string) config('services.netikash.webhook_secret', '');
        $received = (string) $request->header('X-Netikash-Signature', '');
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $received)) {
            Log::warning('Netikash webhook: signature invalide', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event', '');
        $reference = $request->input('reference', '');

        if ($event !== 'payment.success') {
            return response()->json(['status' => 'ignored']);
        }

        $subscription = Subscription::where('payment_reference', $reference)->first();

        if (! $subscription) {
            return response()->json(['error' => 'Référence introuvable'], 404);
        }

        if ($subscription->status === 'confirmed') {
            return response()->json(['status' => 'already_confirmed']);
        }

        DB::transaction(function () use ($subscription): void {
            $subscription->update(['status' => 'confirmed']);
            Entreprise::where('id', $subscription->entreprise_id)->update([
                'plan' => $subscription->plan,
                'plan_expires_at' => $subscription->expires_at,
            ]);
        });

        $this->sendConfirmationEmail($subscription);

        return response()->json(['status' => 'activated']);
    }

    private function sendConfirmationEmail(Subscription $subscription): void
    {
        $entreprise = Entreprise::find($subscription->entreprise_id);
        if (! $entreprise) {
            return;
        }

        $adminUser = $entreprise->users()
            ->where('role', 'super_admin')
            ->orderBy('id')
            ->first()
            ?? $entreprise->users()->orderBy('id')->first();

        if (! $adminUser?->email) {
            return;
        }

        try {
            Mail::to($adminUser->email)->send(new SubscriptionConfirmed(
                userName: $adminUser->name,
                entrepriseName: $entreprise->name,
                plan: $subscription->plan,
                expireDate: $subscription->expires_at->format('d/m/Y'),
                amount: (float) $subscription->amount,
                isTrial: false,
                trialDays: 0,
                appUrl: (string) config('app.url'),
            ));
        } catch (\Exception $e) {
            Log::warning('Email abonnement confirmé échoué: '.$e->getMessage());
        }
    }
}
