<?php
/**
 * Plugin Hub License Manager
 *
 * Classe a integrer dans vos plugins WordPress pour gerer les licences
 * via la plateforme Plugin Hub.
 *
 * @package PluginHub
 * @version 1.0.0
 */

if (!class_exists('PluginHubLicense')) {

    class PluginHubLicense
    {
        private string $api_url;
        private string $product_slug;
        private string $plugin_file;
        private string $option_prefix;
        private ?string $license_key = null;
        private array $license_data = [];

        /**
         * Constructeur
         *
         * @param string $api_url URL de l'API Plugin Hub (ex: https://hub.wabeo.work/api)
         * @param string $product_slug Slug du produit sur Plugin Hub
         * @param string $plugin_file Chemin du fichier principal du plugin (__FILE__)
         * @param string $option_prefix Prefixe pour les options WordPress (defaut: product_slug)
         */
        public function __construct(
            string $api_url,
            string $product_slug,
            string $plugin_file,
            string $option_prefix = ''
        ) {
            $this->api_url = rtrim($api_url, '/');
            $this->product_slug = $product_slug;
            $this->plugin_file = $plugin_file;
            $this->option_prefix = $option_prefix ?: $product_slug;

            $this->license_key = get_option($this->option_prefix . '_license_key');
            $this->license_data = get_option($this->option_prefix . '_license_data', []);
        }

        /**
         * Obtenir la cle de licence
         */
        public function getLicenseKey(): ?string
        {
            return $this->license_key;
        }

        /**
         * Verifier si la licence est valide
         */
        public function isValid(): bool
        {
            if (empty($this->license_key)) {
                return false;
            }

            // Verifier le cache local
            $cached = $this->license_data;
            if (!empty($cached['valid']) && !empty($cached['expires_cache'])) {
                if (time() < $cached['expires_cache']) {
                    return $cached['valid'] === true;
                }
            }

            // Verifier aupres du serveur
            return $this->verify();
        }

        /**
         * Verifier la licence aupres du serveur
         */
        public function verify(): bool
        {
            if (empty($this->license_key)) {
                return false;
            }

            $response = $this->apiRequest('license/verify', [
                'license_key' => $this->license_key,
                'product_slug' => $this->product_slug,
                'domain' => $this->getDomain(),
            ]);

            if (!$response || !isset($response['success'])) {
                return false;
            }

            $valid = $response['valid'] ?? false;

            // Mettre en cache pour 24h
            $this->license_data = [
                'valid' => $valid,
                'expires_cache' => time() + DAY_IN_SECONDS,
                'license' => $response['license'] ?? [],
                'reason' => $response['reason'] ?? null,
                'message' => $response['message'] ?? null,
            ];
            update_option($this->option_prefix . '_license_data', $this->license_data);

            return $valid;
        }

        /**
         * Activer la licence
         *
         * @param string $license_key Cle de licence
         * @return array Resultat de l'activation
         */
        public function activate(string $license_key): array
        {
            $response = $this->apiRequest('license/activate', [
                'license_key' => $license_key,
                'product_slug' => $this->product_slug,
                'domain' => $this->getDomain(),
                'local_ip' => $this->getLocalIp(),
            ]);

            if (!$response) {
                return [
                    'success' => false,
                    'message' => 'Erreur de connexion au serveur de licence.',
                ];
            }

            if ($response['success'] ?? false) {
                // Sauvegarder la licence
                $this->license_key = $license_key;
                update_option($this->option_prefix . '_license_key', $license_key);

                // Mettre a jour le cache
                $this->license_data = [
                    'valid' => true,
                    'expires_cache' => time() + DAY_IN_SECONDS,
                    'activation' => $response['activation'] ?? [],
                ];
                update_option($this->option_prefix . '_license_data', $this->license_data);
            }

            return $response;
        }

        /**
         * Desactiver la licence
         *
         * @return array Resultat de la desactivation
         */
        public function deactivate(): array
        {
            if (empty($this->license_key)) {
                return [
                    'success' => false,
                    'message' => 'Aucune licence a desactiver.',
                ];
            }

            $response = $this->apiRequest('license/deactivate', [
                'license_key' => $this->license_key,
                'domain' => $this->getDomain(),
            ]);

            if (!$response) {
                return [
                    'success' => false,
                    'message' => 'Erreur de connexion au serveur de licence.',
                ];
            }

            if ($response['success'] ?? false) {
                // Supprimer la licence locale
                $this->license_key = null;
                $this->license_data = [];
                delete_option($this->option_prefix . '_license_key');
                delete_option($this->option_prefix . '_license_data');
            }

            return $response;
        }

        /**
         * Obtenir les donnees de licence en cache
         */
        public function getLicenseData(): array
        {
            return $this->license_data;
        }

        /**
         * Effectuer une requete vers l'API
         */
        private function apiRequest(string $endpoint, array $data): ?array
        {
            $url = $this->api_url . '/' . ltrim($endpoint, '/');

            $response = wp_remote_post($url, [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($data),
            ]);

            if (is_wp_error($response)) {
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : null;
        }

        /**
         * Obtenir le domaine du site
         */
        private function getDomain(): string
        {
            $url = home_url();
            $parsed = wp_parse_url($url);
            return $parsed['host'] ?? $url;
        }

        /**
         * Obtenir l'IP locale du serveur
         */
        private function getLocalIp(): ?string
        {
            return $_SERVER['SERVER_ADDR'] ?? null;
        }
    }
}
