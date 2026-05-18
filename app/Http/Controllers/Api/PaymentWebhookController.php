<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Subscription\ConfirmerPaiementWebhookAction;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, ConfirmerPaiementWebhookAction $action): JsonResponse
    {
        $rawBody = $request->getContent();
        $secret = config('services.netikash.webhook_secret');

        if (empty($secret)) {
            Log::error('Netikash webhook: NETIKASH_WEBHOOK_SECRET non configuré');

            return response()->json(['error' => 'Service misconfigured'], 500);
        }

        $expected = hash_hmac('sha256', $rawBody, (string) $secret);
        $received = (string) $request->header('X-Netikash-Signature', '');

        if (! hash_equals($expected, $received)) {
            Log::warning('Netikash webhook: signature invalide', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if ($request->input('event') !== 'payment.success') {
            return response()->json(['status' => 'ignored']);
        }

        $subscription = Subscription::where('payment_reference', $request->input('reference', ''))->first();

        if (! $subscription) {
            return response()->json(['error' => 'Référence introuvable'], 404);
        }

        if ($subscription->status === 'confirmed') {
            return response()->json(['status' => 'already_confirmed']);
        }

        $action->execute($subscription);

        return response()->json(['status' => 'activated']);
    }
}
