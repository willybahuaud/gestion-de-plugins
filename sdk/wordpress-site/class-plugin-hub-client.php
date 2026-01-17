<?php
/**
 * Plugin Hub Client - SDK pour sites de vente WordPress
 *
 * Permet aux sites WordPress de communiquer avec la plateforme Plugin Hub
 * pour gérer l'authentification SSO, le checkout Stripe et les licences.
 *
 * @package PluginHub
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PluginHubClient
{
    private string $apiUrl;
    private string $apiToken;
    private int $timeout = 30;

    private const SESSION_KEY = 'plugin_hub_user';

    public function __construct(string $apiUrl, string $apiToken)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiToken = $apiToken;
    }

    // =========================================================================
    // AUTH - SSO
    // =========================================================================

    /**
     * Inscrit un nouvel utilisateur
     */
    public function register(string $email, string $password, string $name): array
    {
        $response = $this->request('POST', '/auth/register', [
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], 'api');

        if (!empty($response['token'])) {
            $this->setUserSession($response);
        }

        return $response;
    }

    /**
     * Connecte un utilisateur
     */
    public function login(string $email, string $password): array
    {
        $response = $this->request('POST', '/auth/login', [
            'email' => $email,
            'password' => $password,
        ], 'api');

        if (!empty($response['token'])) {
            $this->setUserSession($response);
        }

        return $response;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): array
    {
        $response = $this->request('POST', '/auth/logout', [], 'user');
        $this->clearUserSession();
        return $response;
    }

    /**
     * Demande un reset de mot de passe
     */
    public function forgotPassword(string $email): array
    {
        return $this->request('POST', '/auth/password/forgot', [
            'email' => $email,
        ], 'api');
    }

    /**
     * Reset le mot de passe
     */
    public function resetPassword(string $token, string $email, string $password, string $passwordConfirmation): array
    {
        return $this->request('POST', '/auth/password/reset', [
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], 'api');
    }

    /**
     * Récupère les infos de l'utilisateur connecté
     */
    public function getUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return $this->request('GET', '/auth/user', [], 'user');
    }

    /**
     * Met à jour le profil utilisateur
     */
    public function updateUser(array $data): array
    {
        return $this->request('PUT', '/auth/user', $data, 'user');
    }

    /**
     * Rafraîchit le token JWT
     */
    public function refreshToken(): ?array
    {
        $response = $this->request('POST', '/auth/refresh', [], 'user');

        if (!empty($response['token'])) {
            $session = $this->getUserSession();
            $session['token'] = $response['token'];
            $this->setUserSession($session);
        }

        return $response;
    }

    // =========================================================================
    // CHECKOUT
    // =========================================================================

    /**
     * Crée une session Stripe Checkout
     */
    public function createCheckoutSession(int $priceId, string $successUrl, string $cancelUrl): array
    {
        return $this->request('POST', '/checkout/create-session', [
            'price_id' => $priceId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], 'user');
    }

    /**
     * Redirige vers Stripe Checkout
     */
    public function redirectToCheckout(int $priceId, string $successUrl, string $cancelUrl): void
    {
        $response = $this->createCheckoutSession($priceId, $successUrl, $cancelUrl);

        if (!empty($response['checkout_url'])) {
            wp_redirect($response['checkout_url']);
            exit;
        }

        wp_die('Erreur lors de la création de la session de paiement.');
    }

    // =========================================================================
    // LICENCES
    // =========================================================================

    /**
     * Récupère les licences de l'utilisateur connecté
     */
    public function getUserLicenses(): array
    {
        $user = $this->getUserSession();
        if (empty($user['user']['id'])) {
            return ['error' => 'Non connecté'];
        }

        return $this->request('GET', '/users/' . $user['user']['id'] . '/licenses', [], 'user');
    }

    /**
     * Récupère le détail d'une licence
     */
    public function getLicense(string $licenseKey): array
    {
        return $this->request('GET', '/licenses/' . $licenseKey, [], 'user');
    }

    // =========================================================================
    // SUBSCRIPTIONS
    // =========================================================================

    /**
     * Récupère les abonnements de l'utilisateur
     */
    public function getSubscriptions(): array
    {
        return $this->request('GET', '/subscriptions', [], 'user');
    }

    /**
     * Annule un abonnement
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->request('POST', '/subscriptions/' . $subscriptionId . '/cancel', [], 'user');
    }

    /**
     * Réactive un abonnement annulé
     */
    public function reactivateSubscription(string $subscriptionId): array
    {
        return $this->request('POST', '/subscriptions/' . $subscriptionId . '/reactivate', [], 'user');
    }

    // =========================================================================
    // SESSION MANAGEMENT
    // =========================================================================

    /**
     * Vérifie si un utilisateur est connecté
     */
    public function isLoggedIn(): bool
    {
        $session = $this->getUserSession();
        return !empty($session['token']);
    }

    /**
     * Récupère la session utilisateur
     */
    public function getUserSession(): ?array
    {
        if (!session_id()) {
            session_start();
        }
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Définit la session utilisateur
     */
    private function setUserSession(array $data): void
    {
        if (!session_id()) {
            session_start();
        }
        $_SESSION[self::SESSION_KEY] = $data;
    }

    /**
     * Supprime la session utilisateur
     */
    private function clearUserSession(): void
    {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Récupère le token JWT de l'utilisateur
     */
    public function getUserToken(): ?string
    {
        $session = $this->getUserSession();
        return $session['token'] ?? null;
    }

    // =========================================================================
    // HTTP CLIENT
    // =========================================================================

    /**
     * Effectue une requête HTTP vers l'API
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $endpoint Ex: /auth/login
     * @param array $data Données à envoyer
     * @param string $authType 'api' (API Token) ou 'user' (JWT Token)
     */
    private function request(string $method, string $endpoint, array $data = [], string $authType = 'api'): array
    {
        $url = $this->apiUrl . '/api/v1' . $endpoint;

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Auth header
        if ($authType === 'api') {
            $headers['Authorization'] = 'Bearer ' . $this->apiToken;
        } else {
            $token = $this->getUserToken();
            if ($token) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
        }

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->timeout,
        ];

        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = json_encode($data);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'error' => true,
                'message' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true) ?? [];

        // Token expiré → tenter un refresh
        if ($statusCode === 401 && $authType === 'user') {
            $refreshed = $this->refreshToken();
            if (!empty($refreshed['token'])) {
                // Réessayer la requête avec le nouveau token
                return $this->request($method, $endpoint, $data, $authType);
            }
            $this->clearUserSession();
        }

        if ($statusCode >= 400) {
            $body['error'] = true;
            $body['status_code'] = $statusCode;
        }

        return $body;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Configure le timeout des requêtes
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
}
