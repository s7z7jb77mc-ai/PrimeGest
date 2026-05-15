<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Subscription\ActivateSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), (string) config('services.webhook_secret'));

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->validate([
            'event' => ['required', 'string'],
            'entreprise_id' => ['required', 'integer'],
            'plan' => ['required', 'in:premium,pro'],
            'duration_months' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric'],
            'payment_reference' => ['required', 'string'],
        ]);

        if ($payload['event'] !== 'payment.success') {
            return response()->json(['status' => 'ignored']);
        }

        $entreprise = Entreprise::findOrFail($payload['entreprise_id']);

        (new ActivateSubscriptionAction)->execute(
            entreprise: $entreprise,
            plan: $payload['plan'],
            durationMonths: (int) $payload['duration_months'],
            amount: (float) $payload['amount'],
            paymentMethod: 'webhook',
            paymentReference: $payload['payment_reference'],
        );

        return response()->json(['status' => 'activated']);
    }
}
