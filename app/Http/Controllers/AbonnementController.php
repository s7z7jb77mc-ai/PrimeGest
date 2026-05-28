<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Subscription\ConfirmerPaiementWebhookAction;
use App\Actions\Subscription\InitierPaiementAction;
use App\Http\Requests\InitierPaiementRequest;
use App\Models\Subscription;
use App\Services\NetikashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AbonnementController extends Controller
{
    public function index(Request $request): Response
    {
        $entreprise = $request->user()->entreprise;
        abort_if($entreprise === null, 403, 'Entreprise introuvable.');

        $joursRestants = $entreprise->plan_expires_at
            ? (int) max(0, now()->diffInDays($entreprise->plan_expires_at, false))
            : null;

        $historique = Subscription::where('entreprise_id', $entreprise->id)
            ->latest()->take(10)->get()
            ->map(fn (Subscription $s) => [
                'id'                => $s->id,
                'plan'              => $s->plan,
                'amount'            => $s->amount,
                'status'            => $s->status,
                'payment_method'    => $s->payment_method,
                'payment_reference' => $s->payment_reference,
                'starts_at'         => $s->starts_at?->format('d/m/Y'),
                'expires_at'        => $s->expires_at?->format('d/m/Y'),
                'created_at'        => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Abonnement/Index', [
            'plan'             => $entreprise->plan,
            'plan_expires_at'  => $entreprise->plan_expires_at?->toIso8601String(),
            'jours_restants'   => $joursRestants,
            'historique'       => $historique,
            'prices'           => config('plans.prices', ['premium' => 7, 'pro' => 10]),
            'promo_prices'     => config('plans.promotional_prices', []),
            'usd_to_cdf_rate'  => config('services.netikash.usd_to_cdf_rate', 2800),
        ]);
    }

    public function initierPaiement(InitierPaiementRequest $request, InitierPaiementAction $action): JsonResponse
    {
        $entreprise = $request->user()->entreprise;
        abort_if($entreprise === null, 403, 'Entreprise introuvable.');

        try {
            $result = $action->execute(
                entreprise: $entreprise,
                plan:       $request->validated('plan'),
                duree:      (int) $request->validated('duree'),
                devise:     $request->validated('devise'),
            );
        } catch (\Throwable $e) {
            Log::error('Initiation paiement échouée', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Le paiement n\'a pas pu être initié. Réessayez.',
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'reference'    => $result['subscription']->payment_reference,
            'checkout_url' => $result['checkout_url'],
            'message'      => 'Redirection vers la page de paiement.',
        ]);
    }

    public function statut(
        Request $request,
        string $reference,
        NetikashService $netikash,
        ConfirmerPaiementWebhookAction $confirmer,
    ): JsonResponse {
        $entreprise = $request->user()->entreprise;
        abort_if($entreprise === null, 403, 'Entreprise introuvable.');

        $subscription = Subscription::where('payment_reference', $reference)
            ->where('entreprise_id', $entreprise->id)
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'Référence introuvable.'], 404);
        }

        // Déjà confirmé — rien à faire
        if ($subscription->status === 'confirmed') {
            return response()->json([
                'status'     => 'confirmed',
                'plan'       => $subscription->plan,
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ]);
        }

        // Vérifier directement chez Netikash si on a l'ID de la requête
        $netikashRequestId = $subscription->netikash_order_id;

        if ($netikashRequestId) {
            try {
                $netikashStatus = $netikash->getPaymentStatus($netikashRequestId);
                $status         = $netikashStatus['status'] ?? null;

                // Netikash statuts de succès : approved, completed, success
                if (in_array($status, ['approved', 'completed', 'success', 'cashed'], true)) {
                    $confirmer->execute($subscription);
                    $subscription->refresh();
                }
            } catch (\Throwable $e) {
                Log::warning('Vérification statut Netikash échouée', [
                    'reference' => $reference,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status'     => $subscription->status,
            'plan'       => $subscription->plan,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
        ]);
    }
}
