# Plugin Hub WordPress SDK

Classes PHP a integrer dans vos plugins WordPress pour gerer les licences et les mises a jour automatiques via la plateforme Plugin Hub.

## Installation

Copiez les fichiers du dossier `src/` dans votre plugin WordPress.

## Utilisation

### 1. Gestion des licences

```php
// Dans votre fichier principal de plugin
require_once plugin_dir_path(__FILE__) . 'includes/PluginHubLicense.php';

$license = new PluginHubLicense(
    'https://hub.wabeo.work/api',  // URL de l'API
    'mon-plugin',                   // Slug du produit
    __FILE__,                       // Fichier principal du plugin
    'mon_plugin'                    // Prefixe pour les options (optionnel)
);

// Verifier si la licence est valide
if ($license->isValid()) {
    // Licence valide, activer les fonctionnalites premium
}

// Activer une licence
$result = $license->activate('MA-CLE-DE-LICENCE');
if ($result['success']) {
    echo 'Licence activee !';
} else {
    echo 'Erreur: ' . $result['message'];
}

// Desactiver la licence
$result = $license->deactivate();
```

### 2. Mises a jour automatiques

```php
require_once plugin_dir_path(__FILE__) . 'includes/PluginHubLicense.php';
require_once plugin_dir_path(__FILE__) . 'includes/PluginHubUpdater.php';

// Creer l'instance de licence
$license = new PluginHubLicense(
    'https://hub.wabeo.work/api',
    'mon-plugin',
    __FILE__
);

// Creer l'updater
$updater = new PluginHubUpdater(
    'https://hub.wabeo.work/api',
    'mon-plugin',
    __FILE__,
    '1.0.0',  // Version actuelle du plugin
    $license  // Instance de licence
);

// Initialiser les hooks de mise a jour
$updater->init();
```

### 3. Page de reglages pour la licence

```php
add_action('admin_menu', function() {
    add_options_page(
        'Licence Mon Plugin',
        'Mon Plugin',
        'manage_options',
        'mon-plugin-license',
        'mon_plugin_license_page'
    );
});

function mon_plugin_license_page() {
    global $license;

    // Traitement du formulaire
    if (isset($_POST['activate_license'])) {
        check_admin_referer('mon_plugin_license');
        $result = $license->activate(sanitize_text_field($_POST['license_key']));
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>Licence activee !</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }

    if (isset($_POST['deactivate_license'])) {
        check_admin_referer('mon_plugin_license');
        $license->deactivate();
        echo '<div class="notice notice-success"><p>Licence desactivee.</p></div>';
    }

    $is_valid = $license->isValid();
    $key = $license->getLicenseKey();
    ?>
    <div class="wrap">
        <h1>Licence Mon Plugin</h1>

        <form method="post">
            <?php wp_nonce_field('mon_plugin_license'); ?>

            <table class="form-table">
                <tr>
                    <th>Cle de licence</th>
                    <td>
                        <input type="text" name="license_key"
                               value="<?php echo esc_attr($key); ?>"
                               class="regular-text"
                               <?php echo $key ? 'readonly' : ''; ?>>
                    </td>
                </tr>
                <tr>
                    <th>Statut</th>
                    <td>
                        <?php if ($is_valid): ?>
                            <span style="color: green;">Licence valide</span>
                        <?php elseif ($key): ?>
                            <span style="color: red;">Licence invalide</span>
                        <?php else: ?>
                            <span style="color: gray;">Aucune licence</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if ($key): ?>
                <input type="submit" name="deactivate_license"
                       class="button" value="Desactiver">
            <?php else: ?>
                <input type="submit" name="activate_license"
                       class="button button-primary" value="Activer">
            <?php endif; ?>
        </form>
    </div>
    <?php
}
```

## API Reference

### PluginHubLicense

| Methode | Description |
|---------|-------------|
| `isValid()` | Verifie si la licence est valide (avec cache) |
| `verify()` | Force la verification aupres du serveur |
| `activate($key)` | Active une licence |
| `deactivate()` | Desactive la licence |
| `getLicenseKey()` | Retourne la cle de licence |
| `getLicenseData()` | Retourne les donnees en cache |

### PluginHubUpdater

| Methode | Description |
|---------|-------------|
| `init()` | Initialise les hooks WordPress |
| `checkUpdate()` | Force la verification des mises a jour |

## Notes

- Les verifications de licence sont mises en cache pendant 24h
- Les verifications de mises a jour sont mises en cache pendant 12h
- Le domaine du site est automatiquement detecte
- L'IP locale du serveur est envoyee pour le debug
