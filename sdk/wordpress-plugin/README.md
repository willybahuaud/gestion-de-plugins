# SDK Plugin Hub - Plugins WordPress

SDK PHP à intégrer dans vos plugins WordPress commerciaux pour gérer les licences et les mises à jour automatiques.

## Installation

Copiez le fichier dans votre plugin :

```
/wp-content/plugins/votre-plugin/
├── votre-plugin.php
└── includes/
    └── class-plugin-hub-license.php
```

## Intégration

Dans le fichier principal de votre plugin :

```php
<?php
/**
 * Plugin Name: Mon Plugin Premium
 * Version: 1.0.0
 */

// Inclure le SDK
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-hub-license.php';

// Initialiser la gestion de licence
function mon_plugin_init_license() {
    $license = new PluginHubLicense([
        'api_url' => 'https://hub.wabeo.work',
        'product_slug' => 'mon-plugin',
        'plugin_file' => __FILE__,
        'plugin_version' => '1.0.0',
    ]);
    $license->init();

    // Stocker l'instance pour usage ultérieur
    $GLOBALS['mon_plugin_license'] = $license;
}
add_action('plugins_loaded', 'mon_plugin_init_license');
```

## Fonctionnalités automatiques

Une fois initialisé, le SDK gère automatiquement :

### 1. Page de licence dans les réglages

Une page "Licence mon-plugin" est ajoutée dans Réglages, permettant à l'utilisateur d'activer/désactiver sa licence.

### 2. Vérification quotidienne

La licence est vérifiée une fois par jour via WP-Cron. Le résultat est mis en cache 24h.

### 3. Mises à jour automatiques

Si la licence est valide et active, le plugin vérifie les mises à jour et les affiche dans l'écran WordPress standard.

### 4. Notices admin

Des notifications sont affichées si :
- Aucune licence n'est configurée
- La licence a expiré
- Il y a un problème de paiement

## API du SDK

### Vérifier si la licence est valide

```php
$license = $GLOBALS['mon_plugin_license'];

if ($license->isValid()) {
    // Fonctionnalités premium
} else {
    // Fonctionnalités limitées
}
```

### Récupérer le statut détaillé

```php
$status = $license->getStatus();

// $status = [
//     'valid' => true,
//     'license' => [
//         'status' => 'active',
//         'expires_at' => '2027-01-15T00:00:00Z',
//         'activations_used' => 1,
//         'activations_max' => 3,
//     ],
//     'update_available' => true,
//     'latest_version' => '1.3.0',
// ]
```

### Vérifier si les updates sont disponibles

```php
if ($license->canUpdate()) {
    // Licence active, mises à jour autorisées
}
```

### Activation/Désactivation programmatique

```php
// Activer
$result = $license->activateLicense('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
if ($result['valid']) {
    echo 'Activé !';
}

// Désactiver
$license->deactivateLicense();
```

### Récupérer la clé de licence

```php
$key = $license->getLicenseKey();
```

## Restreindre des fonctionnalités

### Exemple : Désactiver une fonctionnalité si pas de licence

```php
function mon_plugin_feature_premium() {
    $license = $GLOBALS['mon_plugin_license'] ?? null;

    if (!$license || !$license->isValid()) {
        wp_die('Cette fonctionnalité nécessite une licence active.');
    }

    // ... code de la fonctionnalité
}
```

### Exemple : Afficher un message dans un shortcode

```php
add_shortcode('mon_shortcode_premium', function ($atts) {
    $license = $GLOBALS['mon_plugin_license'] ?? null;

    if (!$license || !$license->isValid()) {
        return '<p class="notice">Fonctionnalité premium. <a href="https://votre-site.com/acheter">Acheter une licence</a></p>';
    }

    // ... rendu du shortcode
});
```

## Comportement selon le statut

| Statut licence | Plugin fonctionne | Updates | Notice admin |
|----------------|-------------------|---------|--------------|
| `active` | ✅ Oui | ✅ Oui | ❌ Non |
| `expired` | ✅ Oui | ❌ Non | ⚠️ "Licence expirée" |
| `suspended` | ✅ Oui | ❌ Non | 🔴 "Problème de paiement" |
| Non configuré | ✅ Oui | ❌ Non | ⚠️ "Activez votre licence" |

## Personnalisation

### Changer la durée du cache

Par défaut, le statut de licence est mis en cache 24h. Vous pouvez le modifier :

```php
// Dans la classe, avant l'appel à init()
$license = new PluginHubLicense([...]);
// Modifier la propriété si vous avez étendu la classe
```

### Hooks disponibles

Le SDK utilise les hooks WordPress standard :

```php
// Avant vérification de licence
add_action('plugin_hub_check_license_mon-plugin', function () {
    // Exécuté avant la vérification quotidienne
}, 5);

// Modifier les infos de mise à jour
add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    // Modifier $transient si nécessaire
    return $transient;
}, 15);
```

## Sécurité

- La clé de licence est stockée dans `wp_options` (table `options`)
- Les requêtes API utilisent HTTPS
- Le domaine est normalisé (sans www, en minuscules)
- La vérification se fait côté serveur, pas côté client

## Dépannage

### La licence ne s'active pas

1. Vérifiez que la clé est correcte
2. Vérifiez que le domaine correspond
3. Vérifiez que vous n'avez pas atteint le quota d'activations

### Les mises à jour n'apparaissent pas

1. Vérifiez que la licence est `active` (pas `expired`)
2. Videz les transients : `delete_transient('plugin_hub_license_mon-plugin_update')`
3. Vérifiez la connexion à l'API

### Forcer une vérification

```php
$license = $GLOBALS['mon_plugin_license'];
delete_transient('plugin_hub_license_mon-plugin_status');
$status = $license->checkLicense();
```
