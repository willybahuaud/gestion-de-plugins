<?php

namespace App\Services;

use App\Jobs\SendWebhook;
use App\Models\License;
use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookService
{
    /**
     * Liste des événements disponibles
     */
    public const EVENTS = [
        'license.created',
        'license.activated',
        'license.deactivated',
        'license.expired',
        'license.suspended',
        'license.revoked',
        'license.renewed',
        'payment.completed',
        'payment.failed',
        'subscription.cancelled',
        'user.created',
        'user.updated',
    ];

    /**
     * Dispatcher un événement à tous les endpoints concernés
     */
    public function dispatch(string $event, array $payload, ?int $productId = null): void
    {
        $endpoints = WebhookEndpoint::where('is_active', true)->get();

        foreach ($endpoints as $endpoint) {
            if ($endpoint->shouldReceiveEvent($event, $productId)) {
                SendWebhook::dispatch($endpoint, $event, $payload);
            }
        }
    }

    /**
     * Déclencher un webhook pour la création d'une licence
     */
    public function licenseCreated(License $license): void
    {
        $license->load('user', 'product', 'price');

        $this->dispatch('license.created', [
            'license' => $this->formatLicense($license),
            'user' => $this->formatUser($license->user),
            'product' => $this->formatProduct($license->product),
        ], $license->product_id);
    }

    /**
     * Déclencher un webhook pour l'activation d'une licence
     */
    public function licenseActivated(License $license, string $domain): void
    {
        $license->load('user', 'product');

        $this->dispatch('license.activated', [
            'license' => $this->formatLicense($license),
            'domain' => $domain,
            'user' => $this->formatUser($license->user),
            'product' => $this->formatProduct($license->product),
        ], $license->product_id);
    }

    /**
     * Déclencher un webhook pour la désactivation d'une licence
     */
    public function licenseDeactivated(License $license, string $domain): void
    {
        $license->load('user', 'product');

        $this->dispatch('license.deactivated', [
            'license' => $this->formatLicense($license),
            'domain' => $domain,
            'user' => $this->formatUser($license->user),
            'product' => $this->formatProduct($license->product),
        ], $license->product_id);
    }

    /**
     * Déclencher un webhook pour l'expiration d'une licence
     */
    public function licenseExpired(License $license): void
    {
        $license->load('user', 'product');

        $this->dispatch('license.expired', [
            'license' => $this->formatLicense($license),
            'user' => $this->formatUser($license->user),
            'product' => $this->formatProduct($license->product),
        ], $license->product_id);
    }

    /**
     * Déclencher un webhook pour le renouvellement d'une licence
     */
    public function licenseRenewed(License $license): void
    {
        $license->load('user', 'product');

        $this->dispatch('license.renewed', [
            'license' => $this->formatLicense($license),
            'user' => $this->formatUser($license->user),
            'product' => $this->formatProduct($license->product),
        ], $license->product_id);
    }

    /**
     * Déclencher un webhook pour un paiement réussi
     */
    public function paymentCompleted(License $license, array $paymentData): void
    {
        $license->load('user', 'product');

        $this->dispatch('payment.completed', [
            'license' => $this->formatLicense($license),
            'user' => $this->formatUser($license->user),
            'product' => $this->formatProduct($license->product),
            'payment' => $paymentData,
        ], $license->product_id);
    }

    /**
     * Déclencher un webhook pour un échec de paiement
     */
    public function paymentFailed(License $license, array $paymentData): void
    {
        $license->load('user', 'product');

        $this->dispatch('payment.failed', [
            'license' => $this->formatLicense($license),
            'user' => $this->formatUser($license->user),
            'product' => $this->formatProduct($license->product),
            'payment' => $paymentData,
        ], $license->product_id);
    }

    /**
     * Déclencher un webhook pour la création d'un utilisateur
     */
    public function userCreated(User $user): void
    {
        $this->dispatch('user.created', [
            'user' => $this->formatUser($user),
        ]);
    }

    private function formatLicense(License $license): array
    {
        return [
            'uuid' => $license->uuid,
            'status' => $license->status,
            'type' => $license->type,
            'activations_limit' => $license->activations_limit,
            'activations_count' => $license->activations()->where('is_active', true)->count(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'created_at' => $license->created_at->toIso8601String(),
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }

    private function formatProduct($product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ];
    }
}
