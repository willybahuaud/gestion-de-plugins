<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Price;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StripeController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Créer une session de checkout Stripe
     * Appelé par les sites de vente WordPress
     */
    public function createCheckout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'price_id' => 'required|exists:prices,id',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $price = Price::findOrFail($request->price_id);

        try {
            $session = $this->stripeService->createCheckoutSession(
                $user,
                $price,
                $request->success_url,
                $request->cancel_url,
                $request->metadata ?? []
            );

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la session de paiement',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Récupérer les informations d'abonnement d'un utilisateur
     */
    public function getSubscription(Request $request): JsonResponse
    {
        $user = auth()->user();

        $activeLicenses = $user->activeLicenses()
            ->where('type', 'subscription')
            ->whereNotNull('stripe_subscription_id')
            ->with('product', 'price')
            ->get();

        $subscriptions = $activeLicenses->map(function ($license) {
            $subscriptionData = null;

            if ($license->stripe_subscription_id) {
                try {
                    $subscription = $this->stripeService->getSubscription($license->stripe_subscription_id);
                    $subscriptionData = [
                        'status' => $subscription->status,
                        'current_period_end' => $subscription->current_period_end,
                        'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    ];
                } catch (\Exception $e) {
                    // Ignorer les erreurs Stripe
                }
            }

            return [
                'license_id' => $license->id,
                'license_uuid' => $license->uuid,
                'product' => [
                    'id' => $license->product->id,
                    'name' => $license->product->name,
                    'slug' => $license->product->slug,
                ],
                'price' => [
                    'id' => $license->price->id,
                    'name' => $license->price->name,
                    'amount' => $license->price->amount,
                    'interval' => $license->price->interval,
                ],
                'status' => $license->status,
                'expires_at' => $license->expires_at?->toIso8601String(),
                'stripe_subscription' => $subscriptionData,
            ];
        });

        return response()->json([
            'success' => true,
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Annuler un abonnement
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_uuid' => 'required|exists:licenses,uuid',
            'immediately' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $license = $user->licenses()
            ->where('uuid', $request->license_uuid)
            ->whereNotNull('stripe_subscription_id')
            ->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'Licence non trouvée ou pas un abonnement',
            ], 404);
        }

        try {
            $subscription = $this->stripeService->cancelSubscription(
                $license->stripe_subscription_id,
                $request->boolean('immediately', false)
            );

            if ($request->boolean('immediately', false)) {
                $license->update(['status' => 'revoked']);
            }

            return response()->json([
                'success' => true,
                'message' => $request->boolean('immediately')
                    ? 'Abonnement annulé immédiatement'
                    : 'Abonnement annulé à la fin de la période',
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Réactiver un abonnement annulé
     */
    public function reactivateSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_uuid' => 'required|exists:licenses,uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $license = $user->licenses()
            ->where('uuid', $request->license_uuid)
            ->whereNotNull('stripe_subscription_id')
            ->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'Licence non trouvée ou pas un abonnement',
            ], 404);
        }

        try {
            $this->stripeService->reactivateSubscription($license->stripe_subscription_id);

            $license->update(['status' => 'active']);

            return response()->json([
                'success' => true,
                'message' => 'Abonnement réactivé',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réactivation',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
