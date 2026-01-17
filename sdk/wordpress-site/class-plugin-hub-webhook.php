<?php
/**
 * Plugin Hub Webhook Handler - Réception des webhooks de la plateforme
 *
 * Gère la réception et la validation des webhooks envoyés par Plugin Hub
 * vers les sites de vente WordPress.
 *
 * @package PluginHub
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PluginHubWebhook
{
    private string $secret;
    private array $handlers = [];

    /**
     * Événements disponibles
     */
    public const EVENT_LICENSE_CREATED = 'license.created';
    public const EVENT_LICENSE_RENEWED = 'license.renewed';
    public const EVENT_LICENSE_EXPIRED = 'license.expired';
    public const EVENT_LICENSE_SUSPENDED = 'license.suspended';
    public const EVENT_LICENSE_REFUNDED = 'license.refunded';
    public const EVENT_LICENSE_ACTIVATED = 'license.activated';
    public const EVENT_LICENSE_DEACTIVATED = 'license.deactivated';
    public const EVENT_RELEASE_PUBLISHED = 'release.published';
    public const EVENT_USER_CREATED = 'user.created';
    public const EVENT_USER_UPDATED = 'user.updated';
    public const EVENT_PAYMENT_COMPLETED = 'payment.completed';
    public const EVENT_PAYMENT_FAILED = 'payment.failed';

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Enregistre un handler pour un événement
     *
     * @param string $event Nom de l'événement
     * @param callable $callback Fonction à appeler
     */
    public function on(string $event, callable $callback): self
    {
        if (!isset($this->handlers[$event])) {
            $this->handlers[$event] = [];
        }
        $this->handlers[$event][] = $callback;
        return $this;
    }

    /**
     * Traite un webhook entrant
     * À appeler depuis un endpoint WordPress (REST API ou autre)
     */
    public function handle(): array
    {
        // Récupérer le payload
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

        // Vérifier la signature
        if (!$this->verifySignature($payload, $signature)) {
            http_response_code(401);
            return ['error' => 'Invalid signature'];
        }

        // Décoder le payload
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            return ['error' => 'Invalid JSON'];
        }

        $event = $data['event'] ?? null;
        if (!$event) {
            http_response_code(400);
            return ['error' => 'Missing event type'];
        }

        // Exécuter les handlers
        $this->dispatch($event, $data);

        return ['success' => true, 'event' => $event];
    }

    /**
     * Vérifie la signature HMAC du webhook
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Dispatch l'événement aux handlers enregistrés
     */
    private function dispatch(string $event, array $data): void
    {
        // Handlers spécifiques à l'événement
        if (isset($this->handlers[$event])) {
            foreach ($this->handlers[$event] as $handler) {
                call_user_func($handler, $data['data'] ?? [], $data);
            }
        }

        // Handler générique '*' pour tous les événements
        if (isset($this->handlers['*'])) {
            foreach ($this->handlers['*'] as $handler) {
                call_user_func($handler, $event, $data['data'] ?? [], $data);
            }
        }

        // Action WordPress pour extensibilité
        do_action('plugin_hub_webhook', $event, $data['data'] ?? [], $data);
        do_action('plugin_hub_webhook_' . str_replace('.', '_', $event), $data['data'] ?? [], $data);
    }

    /**
     * Enregistre un endpoint REST API WordPress pour recevoir les webhooks
     */
    public static function registerRestEndpoint(string $namespace, string $route, string $secret): void
    {
        add_action('rest_api_init', function () use ($namespace, $route, $secret) {
            register_rest_route($namespace, $route, [
                'methods' => 'POST',
                'callback' => function ($request) use ($secret) {
                    $webhook = new self($secret);
                    $result = $webhook->handle();

                    if (isset($result['error'])) {
                        return new WP_Error(
                            'webhook_error',
                            $result['error'],
                            ['status' => 400]
                        );
                    }

                    return new WP_REST_Response($result, 200);
                },
                'permission_callback' => '__return_true',
            ]);
        });
    }
}
