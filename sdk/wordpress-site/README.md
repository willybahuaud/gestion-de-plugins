# SDK Plugin Hub - Sites de vente WordPress

SDK PHP pour intégrer un site de vente WordPress avec la plateforme Plugin Hub.

## Installation

Copiez les fichiers dans votre thème ou plugin WordPress :

```
/wp-content/themes/votre-theme/includes/plugin-hub/
├── class-plugin-hub-client.php
└── class-plugin-hub-webhook.php
```

## Configuration

```php
// Dans functions.php ou votre plugin
require_once get_template_directory() . '/includes/plugin-hub/class-plugin-hub-client.php';
require_once get_template_directory() . '/includes/plugin-hub/class-plugin-hub-webhook.php';

// Initialisation du client
$pluginHub = new PluginHubClient(
    'https://hub.wabeo.work',  // URL de la plateforme
    'votre-api-token'          // Token API (créé dans le back-office)
);
```

## Authentification SSO

### Inscription

```php
$result = $pluginHub->register(
    'client@example.com',
    'motdepasse123',
    'Jean Dupont'
);

if (isset($result['error'])) {
    echo 'Erreur: ' . $result['message'];
} else {
    // L'utilisateur est automatiquement connecté
    echo 'Bienvenue ' . $result['user']['name'];
}
```

### Connexion

```php
$result = $pluginHub->login('client@example.com', 'motdepasse123');

if (isset($result['error'])) {
    echo 'Identifiants invalides';
} else {
    // Rediriger vers le compte
    wp_redirect('/mon-compte');
    exit;
}
```

### Déconnexion

```php
$pluginHub->logout();
wp_redirect('/');
exit;
```

### Vérifier si connecté

```php
if ($pluginHub->isLoggedIn()) {
    $user = $pluginHub->getUser();
    echo 'Connecté en tant que ' . $user['name'];
}
```

### Récupérer / Mettre à jour le profil

```php
// Récupérer
$user = $pluginHub->getUser();

// Mettre à jour
$pluginHub->updateUser([
    'name' => 'Nouveau Nom',
    'phone' => '0612345678',
]);
```

### Reset mot de passe

```php
// Étape 1 : Demander le reset
$pluginHub->forgotPassword('client@example.com');

// Étape 2 : Reset avec le token reçu par email
$pluginHub->resetPassword(
    $token,
    'client@example.com',
    'nouveaumotdepasse',
    'nouveaumotdepasse'
);
```

## Checkout Stripe

### Redirection vers Stripe

```php
// Redirige automatiquement vers Stripe Checkout
$pluginHub->redirectToCheckout(
    1,                                    // ID du prix
    home_url('/merci'),                   // URL de succès
    home_url('/panier')                   // URL d'annulation
);
```

### Créer une session sans redirection

```php
$session = $pluginHub->createCheckoutSession(
    1,
    home_url('/merci'),
    home_url('/panier')
);

// Rediriger manuellement
if (!empty($session['checkout_url'])) {
    wp_redirect($session['checkout_url']);
    exit;
}
```

## Licences

### Lister les licences de l'utilisateur

```php
$licenses = $pluginHub->getUserLicenses();

foreach ($licenses['data'] as $license) {
    echo $license['uuid'] . ' - ' . $license['status'];
    echo ' (expire le ' . $license['expires_at'] . ')';
}
```

### Détail d'une licence

```php
$license = $pluginHub->getLicense('550e8400-e29b-41d4-a716-446655440000');
```

## Abonnements

```php
// Lister
$subscriptions = $pluginHub->getSubscriptions();

// Annuler
$pluginHub->cancelSubscription('sub_xxx');

// Réactiver
$pluginHub->reactivateSubscription('sub_xxx');
```

## Webhooks entrants

La plateforme envoie des webhooks pour notifier des événements (achat, renouvellement, etc.).

### Configuration

```php
// Enregistrer l'endpoint REST API
PluginHubWebhook::registerRestEndpoint(
    'mon-site/v1',           // Namespace
    '/webhook',              // Route
    'votre-webhook-secret'   // Secret (configuré dans le back-office)
);
// L'URL sera : https://votre-site.com/wp-json/mon-site/v1/webhook
```

### Gérer les événements

```php
add_action('plugin_hub_webhook_license_created', function ($data, $fullPayload) {
    $license = $data['license'];
    $user = $data['user'];

    // Envoyer un email de bienvenue
    wp_mail(
        $user['email'],
        'Votre licence est prête !',
        'Clé de licence : ' . $license['key']
    );
}, 10, 2);

add_action('plugin_hub_webhook_license_expired', function ($data) {
    // Envoyer un rappel de renouvellement
});

add_action('plugin_hub_webhook_payment_failed', function ($data) {
    // Notifier l'utilisateur du problème de paiement
});
```

### Événements disponibles

| Événement | Action WordPress | Description |
|-----------|------------------|-------------|
| `license.created` | `plugin_hub_webhook_license_created` | Nouvelle licence créée |
| `license.renewed` | `plugin_hub_webhook_license_renewed` | Licence renouvelée |
| `license.expired` | `plugin_hub_webhook_license_expired` | Licence expirée |
| `license.suspended` | `plugin_hub_webhook_license_suspended` | Paiement échoué |
| `license.refunded` | `plugin_hub_webhook_license_refunded` | Remboursement |
| `release.published` | `plugin_hub_webhook_release_published` | Nouvelle version publiée |
| `user.created` | `plugin_hub_webhook_user_created` | Nouveau compte |
| `payment.completed` | `plugin_hub_webhook_payment_completed` | Paiement réussi |
| `payment.failed` | `plugin_hub_webhook_payment_failed` | Paiement échoué |

### Handler générique

```php
add_action('plugin_hub_webhook', function ($event, $data, $fullPayload) {
    // Log tous les webhooks
    error_log('Webhook reçu: ' . $event);
}, 10, 3);
```

## Exemple complet : Page Mon Compte

```php
<?php
// Template: page-mon-compte.php

$pluginHub = new PluginHubClient(
    'https://hub.wabeo.work',
    get_option('plugin_hub_api_token')
);

// Vérifier connexion
if (!$pluginHub->isLoggedIn()) {
    wp_redirect('/connexion');
    exit;
}

$user = $pluginHub->getUser();
$licenses = $pluginHub->getUserLicenses();
?>

<h1>Bonjour <?php echo esc_html($user['name']); ?></h1>

<h2>Vos licences</h2>
<table>
    <thead>
        <tr>
            <th>Produit</th>
            <th>Clé</th>
            <th>Statut</th>
            <th>Expire le</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($licenses['data'] as $license): ?>
        <tr>
            <td><?php echo esc_html($license['product']['name']); ?></td>
            <td><code><?php echo esc_html($license['uuid']); ?></code></td>
            <td><?php echo esc_html($license['status']); ?></td>
            <td><?php echo $license['expires_at'] ? date('d/m/Y', strtotime($license['expires_at'])) : 'Lifetime'; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
```

## Sécurité

- **Ne jamais exposer l'API Token** côté client (JavaScript)
- **Toujours vérifier la signature** des webhooks
- Utiliser HTTPS partout
- Stocker les secrets dans `wp-config.php` ou les options WordPress chiffrées

```php
// Dans wp-config.php
define('PLUGIN_HUB_API_TOKEN', 'votre-token');
define('PLUGIN_HUB_WEBHOOK_SECRET', 'votre-secret');

// Utilisation
$pluginHub = new PluginHubClient(
    'https://hub.wabeo.work',
    PLUGIN_HUB_API_TOKEN
);
```
