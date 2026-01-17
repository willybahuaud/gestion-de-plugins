<?php
/**
 * Plugin Hub License Manager - SDK pour plugins WordPress
 *
 * Gère la vérification de licence, l'activation/désactivation
 * et les mises à jour automatiques pour les plugins commerciaux.
 *
 * @package PluginHub
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PluginHubLicense
{
    private string $apiUrl;
    private string $productSlug;
    private string $pluginFile;
    private string $pluginVersion;
    private string $optionKey;
    private int $cacheHours = 24;

    public function __construct(array $config)
    {
        $this->apiUrl = rtrim($config['api_url'] ?? 'https://hub.wabeo.work', '/');
        $this->productSlug = $config['product_slug'];
        $this->pluginFile = $config['plugin_file'];
        $this->pluginVersion = $config['plugin_version'];
        $this->optionKey = 'plugin_hub_license_' . sanitize_key($this->productSlug);
    }

    /**
     * Initialise les hooks WordPress
     */
    public function init(): void
    {
        // Vérification périodique de la licence
        add_action('admin_init', [$this, 'scheduleCheck']);
        add_action('plugin_hub_check_license_' . $this->productSlug, [$this, 'checkLicense']);

        // Hook pour les mises à jour
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdates']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);

        // Admin notices
        add_action('admin_notices', [$this, 'adminNotices']);

        // Page de licence dans les settings du plugin
        add_action('admin_menu', [$this, 'addLicensePage']);
        add_action('admin_post_plugin_hub_activate_license', [$this, 'handleActivation']);
        add_action('admin_post_plugin_hub_deactivate_license', [$this, 'handleDeactivation']);
    }

    // =========================================================================
    // LICENSE VERIFICATION
    // =========================================================================

    /**
     * Programme la vérification quotidienne
     */
    public function scheduleCheck(): void
    {
        $hook = 'plugin_hub_check_license_' . $this->productSlug;
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), 'daily', $hook);
        }
    }

    /**
     * Vérifie la licence auprès de l'API
     */
    public function checkLicense(): array
    {
        $licenseKey = $this->getLicenseKey();
        if (empty($licenseKey)) {
            return $this->cacheResult([
                'valid' => false,
                'error_code' => 'no_license',
                'message' => 'Aucune licence configurée',
            ]);
        }

        $response = $this->request('POST', '/licenses/verify', [
            'license_key' => $licenseKey,
            'domain' => $this->getDomain(),
            'product_slug' => $this->productSlug,
        ]);

        return $this->cacheResult($response);
    }

    /**
     * Active la licence sur ce domaine
     */
    public function activateLicense(string $licenseKey): array
    {
        $response = $this->request('POST', '/licenses/activate', [
            'license_key' => $licenseKey,
            'domain' => $this->getDomain(),
            'product_slug' => $this->productSlug,
        ]);

        if (!empty($response['valid']) && $response['valid'] === true) {
            $this->saveLicenseKey($licenseKey);
            $this->cacheResult($response);
        }

        return $response;
    }

    /**
     * Désactive la licence de ce domaine
     */
    public function deactivateLicense(): array
    {
        $licenseKey = $this->getLicenseKey();
        if (empty($licenseKey)) {
            return ['error' => true, 'message' => 'Aucune licence à désactiver'];
        }

        $response = $this->request('POST', '/licenses/deactivate', [
            'license_key' => $licenseKey,
            'domain' => $this->getDomain(),
            'product_slug' => $this->productSlug,
        ]);

        if (!isset($response['error']) || $response['error'] === false) {
            $this->deleteLicenseKey();
            $this->clearCache();
        }

        return $response;
    }

    /**
     * Récupère le statut de licence (depuis le cache ou l'API)
     */
    public function getStatus(): array
    {
        $cached = get_transient($this->optionKey . '_status');
        if ($cached !== false) {
            return $cached;
        }

        return $this->checkLicense();
    }

    /**
     * Vérifie si la licence est valide
     */
    public function isValid(): bool
    {
        $status = $this->getStatus();
        return !empty($status['valid']) && $status['valid'] === true;
    }

    /**
     * Vérifie si la licence permet les mises à jour
     */
    public function canUpdate(): bool
    {
        $status = $this->getStatus();
        if (empty($status['valid'])) {
            return false;
        }
        // Seules les licences actives peuvent mettre à jour
        return ($status['license']['status'] ?? '') === 'active';
    }

    // =========================================================================
    // UPDATES
    // =========================================================================

    /**
     * Vérifie les mises à jour disponibles
     */
    public function checkForUpdates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Ne pas vérifier si pas de licence valide
        if (!$this->canUpdate()) {
            return $transient;
        }

        $updateInfo = $this->getUpdateInfo();
        if (empty($updateInfo) || empty($updateInfo['update_available'])) {
            return $transient;
        }

        $pluginBasename = plugin_basename($this->pluginFile);

        $transient->response[$pluginBasename] = (object) [
            'slug' => $this->productSlug,
            'plugin' => $pluginBasename,
            'new_version' => $updateInfo['version'],
            'url' => $updateInfo['info_url'] ?? '',
            'package' => $updateInfo['download_url'] ?? '',
            'tested' => $updateInfo['tested_wp'] ?? '',
            'requires_php' => $updateInfo['requires_php'] ?? '',
            'requires' => $updateInfo['requires_wp'] ?? '',
        ];

        return $transient;
    }

    /**
     * Récupère les infos de mise à jour depuis l'API
     */
    private function getUpdateInfo(): ?array
    {
        $cacheKey = $this->optionKey . '_update';
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $licenseKey = $this->getLicenseKey();
        if (empty($licenseKey)) {
            return null;
        }

        $response = $this->request('GET', '/products/' . $this->productSlug . '/check-update', [
            'license_key' => $licenseKey,
            'domain' => $this->getDomain(),
            'current_version' => $this->pluginVersion,
        ]);

        if (isset($response['error'])) {
            return null;
        }

        set_transient($cacheKey, $response, 12 * HOUR_IN_SECONDS);
        return $response;
    }

    /**
     * Fournit les infos du plugin pour l'écran de détails
     */
    public function pluginInfo($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->productSlug) {
            return $result;
        }

        $updateInfo = $this->getUpdateInfo();
        if (empty($updateInfo)) {
            return $result;
        }

        return (object) [
            'name' => $updateInfo['name'] ?? $this->productSlug,
            'slug' => $this->productSlug,
            'version' => $updateInfo['version'] ?? $this->pluginVersion,
            'author' => $updateInfo['author'] ?? '',
            'homepage' => $updateInfo['homepage'] ?? '',
            'requires' => $updateInfo['requires_wp'] ?? '',
            'tested' => $updateInfo['tested_wp'] ?? '',
            'requires_php' => $updateInfo['requires_php'] ?? '',
            'downloaded' => $updateInfo['downloads'] ?? 0,
            'last_updated' => $updateInfo['last_updated'] ?? '',
            'sections' => [
                'description' => $updateInfo['description'] ?? '',
                'changelog' => $updateInfo['changelog'] ?? '',
            ],
            'download_link' => $updateInfo['download_url'] ?? '',
            'banners' => $updateInfo['banners'] ?? [],
        ];
    }

    // =========================================================================
    // ADMIN UI
    // =========================================================================

    /**
     * Ajoute la page de licence dans le menu admin
     */
    public function addLicensePage(): void
    {
        add_options_page(
            'Licence ' . $this->productSlug,
            'Licence ' . $this->productSlug,
            'manage_options',
            'plugin-hub-license-' . $this->productSlug,
            [$this, 'renderLicensePage']
        );
    }

    /**
     * Affiche la page de licence
     */
    public function renderLicensePage(): void
    {
        $status = $this->getStatus();
        $licenseKey = $this->getLicenseKey();
        $message = $_GET['message'] ?? '';
        ?>
        <div class="wrap">
            <h1>Licence <?php echo esc_html($this->productSlug); ?></h1>

            <?php if ($message === 'activated'): ?>
                <div class="notice notice-success"><p>Licence activée avec succès !</p></div>
            <?php elseif ($message === 'deactivated'): ?>
                <div class="notice notice-info"><p>Licence désactivée.</p></div>
            <?php elseif ($message === 'error'): ?>
                <div class="notice notice-error"><p><?php echo esc_html($_GET['error_message'] ?? 'Erreur'); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; padding: 20px;">
                <?php if ($this->isValid()): ?>
                    <p style="color: green; font-weight: bold;">✓ Licence active</p>
                    <table class="form-table">
                        <tr>
                            <th>Clé de licence</th>
                            <td><code><?php echo esc_html($this->maskLicenseKey($licenseKey)); ?></code></td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td><?php echo esc_html($status['license']['status'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Expire le</th>
                            <td><?php echo $status['license']['expires_at'] ? date('d/m/Y', strtotime($status['license']['expires_at'])) : 'Jamais (Lifetime)'; ?></td>
                        </tr>
                        <tr>
                            <th>Activations</th>
                            <td><?php echo esc_html($status['license']['activations_used'] ?? 0); ?> / <?php echo $status['license']['activations_max'] ? esc_html($status['license']['activations_max']) : '∞'; ?></td>
                        </tr>
                    </table>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="plugin_hub_deactivate_license">
                        <input type="hidden" name="product_slug" value="<?php echo esc_attr($this->productSlug); ?>">
                        <?php wp_nonce_field('plugin_hub_deactivate_' . $this->productSlug); ?>
                        <p><button type="submit" class="button">Désactiver la licence</button></p>
                    </form>
                <?php else: ?>
                    <p style="color: #d63638; font-weight: bold;">✗ Licence inactive</p>
                    <?php if (!empty($status['message'])): ?>
                        <p><?php echo esc_html($status['message']); ?></p>
                    <?php endif; ?>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="plugin_hub_activate_license">
                        <input type="hidden" name="product_slug" value="<?php echo esc_attr($this->productSlug); ?>">
                        <?php wp_nonce_field('plugin_hub_activate_' . $this->productSlug); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="license_key">Clé de licence</label></th>
                                <td><input type="text" id="license_key" name="license_key" class="regular-text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></td>
                            </tr>
                        </table>
                        <p><button type="submit" class="button button-primary">Activer la licence</button></p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche les notices admin
     */
    public function adminNotices(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status = $this->getStatus();

        if (empty($status['valid']) || $status['valid'] === false) {
            $settingsUrl = admin_url('options-general.php?page=plugin-hub-license-' . $this->productSlug);
            echo '<div class="notice notice-warning"><p>';
            echo sprintf(
                '<strong>%s</strong> : Licence non activée. <a href="%s">Activer maintenant</a>',
                esc_html($this->productSlug),
                esc_url($settingsUrl)
            );
            echo '</p></div>';
        } elseif (($status['license']['status'] ?? '') === 'expired') {
            echo '<div class="notice notice-warning"><p>';
            echo sprintf(
                '<strong>%s</strong> : Votre licence a expiré. Renouvelez-la pour continuer à recevoir les mises à jour.',
                esc_html($this->productSlug)
            );
            echo '</p></div>';
        } elseif (($status['license']['status'] ?? '') === 'suspended') {
            echo '<div class="notice notice-error"><p>';
            echo sprintf(
                '<strong>%s</strong> : Problème de paiement détecté. Veuillez régulariser votre situation.',
                esc_html($this->productSlug)
            );
            echo '</p></div>';
        }
    }

    /**
     * Gère l'activation de licence depuis le formulaire
     */
    public function handleActivation(): void
    {
        check_admin_referer('plugin_hub_activate_' . $this->productSlug);

        if ($_POST['product_slug'] !== $this->productSlug) {
            return;
        }

        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');
        $result = $this->activateLicense($licenseKey);

        $redirectUrl = admin_url('options-general.php?page=plugin-hub-license-' . $this->productSlug);

        if (!empty($result['valid']) && $result['valid'] === true) {
            wp_redirect($redirectUrl . '&message=activated');
        } else {
            wp_redirect($redirectUrl . '&message=error&error_message=' . urlencode($result['message'] ?? 'Erreur inconnue'));
        }
        exit;
    }

    /**
     * Gère la désactivation de licence depuis le formulaire
     */
    public function handleDeactivation(): void
    {
        check_admin_referer('plugin_hub_deactivate_' . $this->productSlug);

        if ($_POST['product_slug'] !== $this->productSlug) {
            return;
        }

        $this->deactivateLicense();

        $redirectUrl = admin_url('options-general.php?page=plugin-hub-license-' . $this->productSlug);
        wp_redirect($redirectUrl . '&message=deactivated');
        exit;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Effectue une requête HTTP vers l'API
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->apiUrl . '/api/v1' . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
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
                'valid' => false,
                'error' => true,
                'message' => $response->get_error_message(),
            ];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?? [];
    }

    /**
     * Cache le résultat de vérification
     */
    private function cacheResult(array $result): array
    {
        set_transient($this->optionKey . '_status', $result, $this->cacheHours * HOUR_IN_SECONDS);
        return $result;
    }

    /**
     * Vide le cache
     */
    private function clearCache(): void
    {
        delete_transient($this->optionKey . '_status');
        delete_transient($this->optionKey . '_update');
    }

    /**
     * Récupère le domaine actuel (normalisé)
     */
    private function getDomain(): string
    {
        $domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);
        return strtolower($domain);
    }

    /**
     * Récupère la clé de licence stockée
     */
    public function getLicenseKey(): ?string
    {
        return get_option($this->optionKey . '_key') ?: null;
    }

    /**
     * Sauvegarde la clé de licence
     */
    private function saveLicenseKey(string $key): void
    {
        update_option($this->optionKey . '_key', $key);
    }

    /**
     * Supprime la clé de licence
     */
    private function deleteLicenseKey(): void
    {
        delete_option($this->optionKey . '_key');
    }

    /**
     * Masque une clé de licence pour l'affichage
     */
    private function maskLicenseKey(string $key): string
    {
        if (strlen($key) < 8) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
}
