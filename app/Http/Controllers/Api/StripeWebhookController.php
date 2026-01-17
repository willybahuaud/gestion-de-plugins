<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Price;
use App\Models\User;
use App\Services\StripeService;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Event;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private WebhookService $webhookService
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid signature', 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        return match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'invoice.paid' => $this->handleInvoicePaid($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            default => response('Webhook received', 200),
        };
    }

    private function handleCheckoutCompleted(Event $event): Response
    {
        $session = $event->data->object;
        $metadata = $session->metadata;

        $userId = $metadata->user_id ?? null;
        $priceId = $metadata->price_id ?? null;
        $productId = $metadata->product_id ?? null;

        if (!$userId || !$priceId || !$productId) {
            Log::warning('Checkout completed with missing metadata', [
                'session_id' => $session->id,
                'metadata' => (array)$metadata,
            ]);
            return response('Missing metadata', 200);
        }

        $user = User::find($userId);
        $price = Price::find($priceId);

        if (!$user || !$price) {
            Log::warning('Checkout completed with invalid user or price', [
                'user_id' => $userId,
                'price_id' => $priceId,
            ]);
            return response('Invalid user or price', 200);
        }

        // Pour les achats lifetime (non-recurring), créer la licence directement
        if (!$price->isRecurring()) {
            $this->createLicense($user, $price, null, $session->id);
        }

        // Pour les abonnements, la licence sera créée via l'événement subscription.created

        return response('Checkout processed', 200);
    }

    private function handleSubscriptionCreated(Event $event): Response
    {
        $subscription = $event->data->object;
        $customerId = $subscription->customer;
        $priceId = $subscription->items->data[0]->price->id ?? null;

        $user = User::where('stripe_customer_id', $customerId)->first();
        $price = Price::where('stripe_price_id', $priceId)->first();

        if (!$user || !$price) {
            Log::warning('Subscription created with unknown customer or price', [
                'customer_id' => $customerId,
                'stripe_price_id' => $priceId,
            ]);
            return response('Unknown customer or price', 200);
        }

        // Vérifier si une licence existe déjà pour cet abonnement
        $existingLicense = License::where('stripe_subscription_id', $subscription->id)->first();
        if ($existingLicense) {
            return response('License already exists', 200);
        }

        $this->createLicense($user, $price, $subscription->id);

        return response('Subscription processed', 200);
    }

    private function handleSubscriptionUpdated(Event $event): Response
    {
        $subscription = $event->data->object;
        $license = License::where('stripe_subscription_id', $subscription->id)->first();

        if (!$license) {
            return response('License not found', 200);
        }

        // Mettre à jour le statut de la licence selon le statut de l'abonnement
        $status = match ($subscription->status) {
            'active' => 'active',
            'past_due' => 'suspended',
            'canceled', 'unpaid' => 'revoked',
            default => $license->status,
        };

        $license->update([
            'status' => $status,
            'expires_at' => $subscription->current_period_end
                ? \Carbon\Carbon::createFromTimestamp($subscription->current_period_end)
                : null,
        ]);

        return response('Subscription updated', 200);
    }

    private function handleSubscriptionDeleted(Event $event): Response
    {
        $subscription = $event->data->object;
        $license = License::where('stripe_subscription_id', $subscription->id)->first();

        if (!$license) {
            return response('License not found', 200);
        }

        $license->update([
            'status' => 'expired',
        ]);

        // Déclencher le webhook d'expiration
        $this->webhookService->licenseExpired($license);

        return response('Subscription deleted', 200);
    }

    private function handleInvoicePaid(Event $event): Response
    {
        $invoice = $event->data->object;
        $customerId = $invoice->customer;
        $subscriptionId = $invoice->subscription;

        $user = User::where('stripe_customer_id', $customerId)->first();

        if (!$user) {
            return response('Unknown customer', 200);
        }

        $license = $subscriptionId
            ? License::where('stripe_subscription_id', $subscriptionId)->first()
            : null;

        // Créer l'enregistrement de facture
        Invoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'license_id' => $license?->id,
                'number' => $invoice->number,
                'amount_total' => $invoice->total,
                'amount_tax' => $invoice->tax ?? 0,
                'currency' => $invoice->currency,
                'status' => 'paid',
                'stripe_pdf_url' => $invoice->invoice_pdf,
                'issued_at' => \Carbon\Carbon::createFromTimestamp($invoice->created),
            ]
        );

        // Si c'est un renouvellement d'abonnement, mettre à jour la date d'expiration
        if ($license && $subscriptionId) {
            $subscription = $this->stripeService->getSubscription($subscriptionId);
            $license->update([
                'status' => 'active',
                'expires_at' => \Carbon\Carbon::createFromTimestamp($subscription->current_period_end),
            ]);

            // Déclencher le webhook de renouvellement
            $this->webhookService->licenseRenewed($license);
        }

        // Déclencher le webhook de paiement réussi
        if ($license) {
            $this->webhookService->paymentCompleted($license, [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total,
                'currency' => $invoice->currency,
            ]);
        }

        return response('Invoice processed', 200);
    }

    private function handleInvoicePaymentFailed(Event $event): Response
    {
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription;

        if (!$subscriptionId) {
            return response('No subscription', 200);
        }

        $license = License::where('stripe_subscription_id', $subscriptionId)->first();

        if ($license) {
            $license->update(['status' => 'suspended']);

            // Déclencher le webhook d'échec de paiement
            $this->webhookService->paymentFailed($license, [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount_due ?? 0,
                'currency' => $invoice->currency,
            ]);
        }

        return response('Payment failure processed', 200);
    }

    private function createLicense(User $user, Price $price, ?string $subscriptionId, ?string $checkoutSessionId = null): License
    {
        $expiresAt = null;

        if ($price->isRecurring() && $subscriptionId) {
            $subscription = $this->stripeService->getSubscription($subscriptionId);
            $expiresAt = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);
        }

        $license = License::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'product_id' => $price->product_id,
            'price_id' => $price->id,
            'stripe_subscription_id' => $subscriptionId,
            'status' => 'active',
            'type' => $price->isRecurring() ? 'subscription' : 'lifetime',
            'activations_limit' => $price->activations_limit ?? 1,
            'expires_at' => $expiresAt,
        ]);

        // Déclencher le webhook sortant
        $this->webhookService->licenseCreated($license);

        return $license;
    }
}
