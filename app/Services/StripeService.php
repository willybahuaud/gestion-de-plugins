<?php

namespace App\Services;

use App\Models\License;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Stripe;
use Stripe\Subscription;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Créer ou récupérer un customer Stripe pour un utilisateur
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        if ($user->stripe_customer_id) {
            return Customer::retrieve($user->stripe_customer_id);
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
            'address' => [
                'line1' => $user->address_line1,
                'line2' => $user->address_line2,
                'city' => $user->city,
                'postal_code' => $user->postal_code,
                'country' => $user->country,
            ],
            'tax_id_data' => $user->vat_number ? [
                ['type' => 'eu_vat', 'value' => $user->vat_number],
            ] : [],
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Synchroniser un produit local vers Stripe
     */
    public function syncProduct(Product $product): StripeProduct
    {
        $data = [
            'name' => $product->name,
            'description' => $product->description,
            'metadata' => [
                'product_id' => $product->id,
                'slug' => $product->slug,
            ],
        ];

        if ($product->stripe_product_id) {
            return StripeProduct::update($product->stripe_product_id, $data);
        }

        $stripeProduct = StripeProduct::create($data);
        $product->update(['stripe_product_id' => $stripeProduct->id]);

        return $stripeProduct;
    }

    /**
     * Synchroniser un prix local vers Stripe
     */
    public function syncPrice(Price $price): StripePrice
    {
        if ($price->stripe_price_id) {
            // Les prix Stripe ne peuvent pas être modifiés, on retourne l'existant
            return StripePrice::retrieve($price->stripe_price_id);
        }

        $product = $price->product;
        if (!$product->stripe_product_id) {
            $this->syncProduct($product);
            $product->refresh();
        }

        $data = [
            'product' => $product->stripe_product_id,
            'currency' => $price->currency,
            'unit_amount' => $price->amount,
            'metadata' => [
                'price_id' => $price->id,
            ],
        ];

        if ($price->isRecurring()) {
            $data['recurring'] = [
                'interval' => $price->interval,
                'interval_count' => $price->interval_count,
            ];
        }

        $stripePrice = StripePrice::create($data);
        $price->update(['stripe_price_id' => $stripePrice->id]);

        return $stripePrice;
    }

    /**
     * Créer une session de checkout Stripe
     */
    public function createCheckoutSession(
        User $user,
        Price $price,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Session {
        $customer = $this->getOrCreateCustomer($user);

        if (!$price->stripe_price_id) {
            $this->syncPrice($price);
            $price->refresh();
        }

        $sessionData = [
            'customer' => $customer->id,
            'line_items' => [
                [
                    'price' => $price->stripe_price_id,
                    'quantity' => 1,
                ],
            ],
            'mode' => $price->isRecurring() ? 'subscription' : 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => array_merge([
                'user_id' => $user->id,
                'price_id' => $price->id,
                'product_id' => $price->product_id,
            ], $metadata),
            'automatic_tax' => [
                'enabled' => true,
            ],
            'customer_update' => [
                'address' => 'auto',
                'name' => 'auto',
            ],
        ];

        // Pour les paiements uniques (lifetime), activer la création de facture
        if (!$price->isRecurring()) {
            $sessionData['invoice_creation'] = [
                'enabled' => true,
            ];
        }

        return Session::create($sessionData);
    }

    /**
     * Récupérer un abonnement Stripe
     */
    public function getSubscription(string $subscriptionId): Subscription
    {
        return Subscription::retrieve($subscriptionId);
    }

    /**
     * Annuler un abonnement Stripe
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false): Subscription
    {
        $subscription = Subscription::retrieve($subscriptionId);

        if ($immediately) {
            return $subscription->cancel();
        }

        return Subscription::update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Réactiver un abonnement annulé (si pas encore terminé)
     */
    public function reactivateSubscription(string $subscriptionId): Subscription
    {
        return Subscription::update($subscriptionId, [
            'cancel_at_period_end' => false,
        ]);
    }
}
