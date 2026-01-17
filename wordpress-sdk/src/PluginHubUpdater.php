<?php
/**
 * Plugin Hub Updater
 *
 * Classe a integrer dans vos plugins WordPress pour gerer les mises a jour
 * automatiques via la plateforme Plugin Hub.
 *
 * @package PluginHub
 * @version 1.0.0
 */

if (!class_exists('PluginHubUpdater')) {

    class PluginHubUpdater
    {
        private string $api_url;
        private string $product_slug;
        private string $plugin_file;
        private string $plugin_basename;
        private string $version;
        private ?PluginHubLicense $license = null;
        private ?array $update_data = null;

        /**
         * Constructeur
         *
         * @param string $api_url URL de l'API Plugin Hub
         * @param string $product_slug Slug du produit
         * @param string $plugin_file Chemin du fichier principal du plugin (__FILE__)
         * @param string $version Version actuelle du plugin
         * @param PluginHubLicense|null $license Instance de PluginHubLicense (optionnel)
         */
        public function __construct(
            string $api_url,
            string $product_slug,
            string $plugin_file,
            string $version,
            ?PluginHubLicense $license = null
        ) {
            $this->api_url = rtrim($api_url, '/');
            $this->product_slug = $product_slug;
            $this->plugin_file = $plugin_file;
            $this->plugin_basename = plugin_basename($plugin_file);
            $this->version = $version;
            $this->license = $license;
        }

        /**
         * Initialiser les hooks WordPress pour les mises a jour
         */
        public function init(): void
        {
            add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
            add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
            add_action('upgrader_process_complete', [$this, 'afterUpdate'], 10, 2);
        }

        /**
         * Verifier si une mise a jour est disponible
         */
        public function checkForUpdate($transient)
        {
            if (empty($transient->checked)) {
                return $transient;
            }

            $update = $this->getUpdateData();

            if ($update && !empty($update['update_available'])) {
                $transient->response[$this->plugin_basename] = (object) [
                    'slug' => $this->product_slug,
                    'plugin' => $this->plugin_basename,
                    'new_version' => $update['latest_version'],
                    'url' => '',
                    'package' => $update['download_url'] ?? '',
                    'tested' => $update['requirements']['min_wp_version'] ?? '',
                    'requires_php' => $update['requirements']['min_php_version'] ?? '',
                ];
            }

            return $transient;
        }

        /**
         * Fournir les informations du plugin pour la popup de details
         */
        public function pluginInfo($result, $action, $args)
        {
            if ($action !== 'plugin_information') {
                return $result;
            }

            if (!isset($args->slug) || $args->slug !== $this->product_slug) {
                return $result;
            }

            $update = $this->getUpdateData();

            if (!$update) {
                return $result;
            }

            $plugin_data = get_plugin_data($this->plugin_file);

            return (object) [
                'name' => $plugin_data['Name'] ?? $this->product_slug,
                'slug' => $this->product_slug,
                'version' => $update['latest_version'] ?? $this->version,
                'author' => $plugin_data['Author'] ?? '',
                'author_profile' => $plugin_data['AuthorURI'] ?? '',
                'requires' => $update['requirements']['min_wp_version'] ?? '',
                'requires_php' => $update['requirements']['min_php_version'] ?? '',
                'tested' => $update['requirements']['min_wp_version'] ?? '',
                'last_updated' => $update['published_at'] ?? '',
                'sections' => [
                    'description' => $plugin_data['Description'] ?? '',
                    'changelog' => $this->formatChangelog($update['changelog'] ?? ''),
                ],
                'download_link' => $update['download_url'] ?? '',
            ];
        }

        /**
         * Nettoyer le cache apres une mise a jour
         */
        public function afterUpdate($upgrader, $options): void
        {
            if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
                return;
            }

            if (!isset($options['plugins']) || !in_array($this->plugin_basename, $options['plugins'])) {
                return;
            }

            // Vider le cache
            $this->update_data = null;
            delete_transient($this->getCacheKey());
        }

        /**
         * Verifier manuellement les mises a jour
         *
         * @return array|null Donnees de mise a jour ou null
         */
        public function checkUpdate(): ?array
        {
            // Vider le cache
            delete_transient($this->getCacheKey());
            $this->update_data = null;

            return $this->getUpdateData();
        }

        /**
         * Obtenir les donnees de mise a jour (avec cache)
         */
        private function getUpdateData(): ?array
        {
            if ($this->update_data !== null) {
                return $this->update_data;
            }

            // Verifier le cache
            $cached = get_transient($this->getCacheKey());
            if ($cached !== false) {
                $this->update_data = $cached;
                return $this->update_data;
            }

            // Requete API
            $license_key = $this->license ? $this->license->getLicenseKey() : null;

            if (empty($license_key)) {
                return null;
            }

            $response = $this->apiRequest('update/check', [
                'license_key' => $license_key,
                'product_slug' => $this->product_slug,
                'domain' => $this->getDomain(),
                'current_version' => $this->version,
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo('version'),
            ]);

            if (!$response || !isset($response['success'])) {
                return null;
            }

            $this->update_data = $response;

            // Mettre en cache pour 12h
            set_transient($this->getCacheKey(), $this->update_data, 12 * HOUR_IN_SECONDS);

            return $this->update_data;
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
         * Formater le changelog pour l'affichage
         */
        private function formatChangelog(?string $changelog): string
        {
            if (empty($changelog)) {
                return '';
            }

            // Convertir le markdown basique en HTML
            $html = nl2br(esc_html($changelog));
            $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
            $html = preg_replace('/(<li>.*<\/li>)+/s', '<ul>$0</ul>', $html);

            return $html;
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
         * Obtenir la cle de cache
         */
        private function getCacheKey(): string
        {
            return 'pluginhub_update_' . $this->product_slug;
        }
    }
}
