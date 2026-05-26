<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Subscription\ConfirmerPaiementWebhookAction;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\NetikashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, ConfirmerPaiementWebhookAction $action, NetikashService $netikash): JsonResponse
    {
        $rawBody   = $request->getContent();
        $secret    = config('services.netikash.webhook_secret');
        $signature = (string) $request->header('X-Signature', '');
        $timestamp = (string) $request->header('X-Timestamp', '');

        if (empty($secret)) {
            Log::error('Netikash webhook: NETIKASH_WEBHOOK_SECRET non configuré');

            return response()->json(['error' => 'Service misconfigured'], 500);
        }

        if (! $netikash->verifyWebhookSignature($rawBody, $signature, $timestamp, $secret)) {
            Log::warning('Netikash webhook: signature invalide', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event', '');

        if ($event !== 'payment.success') {
            return response()->json(['status' => 'ignored']);
        }

        // Netikash envoie "ref" = notre payment_reference
        $ref          = (string) $request->input('ref', '');
        $subscription = Subscription::where('payment_reference', $ref)->first();

        if (! $subscription) {
            Log::warning('Netikash webhook: référence introuvable', ['ref' => $ref]);

            return response()->json(['error' => 'Référence introuvable'], 404);
        }

        if ($subscription->status === 'confirmed') {
            return response()->json(['status' => 'already_confirmed']);
        }

        $action->execute($subscription);

        return response()->json(['status' => 'activated']);
    }
}
