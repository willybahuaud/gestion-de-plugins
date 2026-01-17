# Guide d'implémentation des SDK Plugin Hub

Ce document explique comment intégrer les SDK dans vos sites WordPress et plugins.

---

## Table des matières

1. [Architecture SSO](#architecture-sso)
2. [SDK Site de vente](#sdk-site-de-vente)
   - [Installation](#installation-site-de-vente)
   - [Connexion utilisateur](#connexion-utilisateur)
   - [Inscription](#inscription)
   - [Déconnexion](#déconnexion)
   - [Gestion de session](#gestion-de-session)
   - [Page Mon Compte](#page-mon-compte)
   - [Checkout Stripe](#checkout-stripe)
   - [Réception des webhooks](#réception-des-webhooks)
3. [SDK Plugin installé](#sdk-plugin-installé)
   - [Installation](#installation-plugin)
   - [Intégration dans votre plugin](#intégration-dans-votre-plugin)
   - [Restreindre des fonctionnalités](#restreindre-des-fonctionnalités)

---

## Architecture SSO

Le système SSO (Single Sign-On) fonctionne ainsi :

```
┌─────────────────┐         ┌─────────────────┐         ┌─────────────────┐
│  Utilisateur    │         │  Site WordPress │         │   Plugin Hub    │
│  (navigateur)   │         │  (site vente)   │         │   (API)         │
└────────┬────────┘         └────────┬────────┘         └────────┬────────┘
         │                           │                           │
         │  1. Formulaire login      │                           │
         │  ───────────────────────► │                           │
         │                           │                           │
         │                           │  2. POST /auth/login      │
         │                           │  ───────────────────────► │
         │                           │                           │
         │                           │  3. Token JWT + user      │
         │                           │  ◄─────────────────────── │
         │                           │                           │
         │                           │  4. Stocke en session PHP │
         │                           │  ─────────┐               │
         │                           │           │               │
         │                           │  ◄────────┘               │
         │                           │                           │
         │  5. Cookie session        │                           │
         │  ◄─────────────────────── │                           │
         │                           │                           │
```

**Points clés :**
- WordPress ne stocke jamais le mot de passe
- Le token JWT est stocké en session PHP (pas en cookie)
- Chaque requête API utilise ce token
- Le token expire après 60 minutes (refresh automatique)

---

## SDK Site de vente

### Installation site de vente

1. Créez un dossier dans votre thème :

```
wp-content/themes/votre-theme/
└── includes/
    └── plugin-hub/
        ├── class-plugin-hub-client.php
        └── class-plugin-hub-webhook.php
```

2. Dans `functions.php` :

```php
<?php
// Charger le SDK
require_once get_template_directory() . '/includes/plugin-hub/class-plugin-hub-client.php';
require_once get_template_directory() . '/includes/plugin-hub/class-plugin-hub-webhook.php';

// Configuration
define('PLUGIN_HUB_API_URL', 'https://hub.wabeo.work');
define('PLUGIN_HUB_API_TOKEN', 'votre-api-token'); // Depuis le back-office

// Instance globale
function plugin_hub(): PluginHubClient {
    static $instance = null;
    if ($instance === null) {
        $instance = new PluginHubClient(
            PLUGIN_HUB_API_URL,
            PLUGIN_HUB_API_TOKEN
        );
    }
    return $instance;
}
```

---

### Connexion utilisateur

#### Template de page de connexion

Créez `page-connexion.php` :

```php
<?php
/**
 * Template Name: Connexion
 */

// Déjà connecté ? Rediriger vers le compte
if (plugin_hub()->isLoggedIn()) {
    wp_redirect(home_url('/mon-compte'));
    exit;
}

// Traitement du formulaire
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_nonce'])) {
    if (wp_verify_nonce($_POST['login_nonce'], 'plugin_hub_login')) {
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password']; // Ne pas sanitize le mdp

        $result = plugin_hub()->login($email, $password);

        if (isset($result['error'])) {
            $error = $result['message'] ?? 'Identifiants incorrects';
        } else {
            // Connexion réussie, rediriger
            $redirect = $_GET['redirect_to'] ?? home_url('/mon-compte');
            wp_redirect($redirect);
            exit;
        }
    }
}

get_header();
?>

<div class="login-form">
    <h1>Connexion</h1>

    <?php if ($error): ?>
        <div class="error-message"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php wp_nonce_field('plugin_hub_login', 'login_nonce'); ?>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required
                   value="<?php echo esc_attr($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>

    <p>
        <a href="<?php echo home_url('/mot-de-passe-oublie'); ?>">Mot de passe oublié ?</a>
    </p>
    <p>
        Pas encore de compte ? <a href="<?php echo home_url('/inscription'); ?>">S'inscrire</a>
    </p>
</div>

<?php get_footer(); ?>
```

---

### Inscription

#### Template de page d'inscription

Créez `page-inscription.php` :

```php
<?php
/**
 * Template Name: Inscription
 */

if (plugin_hub()->isLoggedIn()) {
    wp_redirect(home_url('/mon-compte'));
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_nonce'])) {
    if (wp_verify_nonce($_POST['register_nonce'], 'plugin_hub_register')) {
        $email = sanitize_email($_POST['email']);
        $name = sanitize_text_field($_POST['name']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        // Validation
        if ($password !== $password_confirm) {
            $error = 'Les mots de passe ne correspondent pas';
        } elseif (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères';
        } else {
            $result = plugin_hub()->register($email, $password, $name);

            if (isset($result['error'])) {
                $error = $result['message'] ?? 'Erreur lors de l\'inscription';
            } else {
                // Inscription réussie, l'utilisateur est automatiquement connecté
                wp_redirect(home_url('/mon-compte?welcome=1'));
                exit;
            }
        }
    }
}

get_header();
?>

<div class="register-form">
    <h1>Créer un compte</h1>

    <?php if ($error): ?>
        <div class="error-message"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php wp_nonce_field('plugin_hub_register', 'register_nonce'); ?>

        <div class="form-group">
            <label for="name">Nom complet</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo esc_attr($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required
                   value="<?php echo esc_attr($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required
                   minlength="8">
        </div>

        <div class="form-group">
            <label for="password_confirm">Confirmer le mot de passe</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>

        <button type="submit" class="btn btn-primary">S'inscrire</button>
    </form>

    <p>
        Déjà un compte ? <a href="<?php echo home_url('/connexion'); ?>">Se connecter</a>
    </p>
</div>

<?php get_footer(); ?>
```

---

### Déconnexion

Créez une page ou un endpoint de déconnexion :

```php
<?php
/**
 * Template Name: Déconnexion
 */

// Vérifier le nonce si passé en paramètre (sécurité)
if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'logout')) {
    wp_die('Lien de déconnexion invalide');
}

// Déconnecter de Plugin Hub
plugin_hub()->logout();

// Rediriger vers l'accueil
wp_redirect(home_url('/?logged_out=1'));
exit;
```

#### Lien de déconnexion sécurisé

```php
<a href="<?php echo wp_nonce_url(home_url('/deconnexion'), 'logout'); ?>">
    Se déconnecter
</a>
```

---

### Gestion de session

#### Vérifier si l'utilisateur est connecté

```php
// Dans un template ou une fonction
if (plugin_hub()->isLoggedIn()) {
    $user = plugin_hub()->getUserSession();
    echo 'Bonjour ' . esc_html($user['user']['name']);
} else {
    echo '<a href="/connexion">Se connecter</a>';
}
```

#### Protéger une page

```php
<?php
// Au début du template
if (!plugin_hub()->isLoggedIn()) {
    wp_redirect(home_url('/connexion?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
    exit;
}

// Le reste du template...
```

#### Middleware dans functions.php

```php
// Protéger automatiquement certaines pages
add_action('template_redirect', function () {
    // Pages qui nécessitent une connexion
    $protected_pages = ['mon-compte', 'mes-licences', 'mes-factures'];

    if (is_page($protected_pages) && !plugin_hub()->isLoggedIn()) {
        wp_redirect(home_url('/connexion?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
        exit;
    }
});
```

---

### Page Mon Compte

Créez `page-mon-compte.php` :

```php
<?php
/**
 * Template Name: Mon Compte
 */

if (!plugin_hub()->isLoggedIn()) {
    wp_redirect(home_url('/connexion'));
    exit;
}

$user = plugin_hub()->getUser();
$licenses = plugin_hub()->getUserLicenses();

// Traitement mise à jour profil
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_nonce'])) {
    if (wp_verify_nonce($_POST['update_profile_nonce'], 'update_profile')) {
        $result = plugin_hub()->updateUser([
            'name' => sanitize_text_field($_POST['name']),
            'phone' => sanitize_text_field($_POST['phone']),
        ]);

        if (!isset($result['error'])) {
            $message = 'Profil mis à jour !';
            $user = plugin_hub()->getUser(); // Recharger
        }
    }
}

get_header();
?>

<div class="account-page">
    <h1>Mon compte</h1>

    <?php if (isset($_GET['welcome'])): ?>
        <div class="success-message">Bienvenue ! Votre compte a été créé.</div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="success-message"><?php echo esc_html($message); ?></div>
    <?php endif; ?>

    <!-- Profil -->
    <section class="account-section">
        <h2>Mon profil</h2>
        <form method="POST">
            <?php wp_nonce_field('update_profile', 'update_profile_nonce'); ?>

            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?php echo esc_attr($user['email']); ?>" disabled>
                <small>L'email ne peut pas être modifié</small>
            </div>

            <div class="form-group">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name"
                       value="<?php echo esc_attr($user['name']); ?>">
            </div>

            <div class="form-group">
                <label for="phone">Téléphone</label>
                <input type="tel" id="phone" name="phone"
                       value="<?php echo esc_attr($user['phone'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn">Mettre à jour</button>
        </form>
    </section>

    <!-- Licences -->
    <section class="account-section">
        <h2>Mes licences</h2>

        <?php if (empty($licenses['data'])): ?>
            <p>Vous n'avez pas encore de licence.</p>
            <a href="<?php echo home_url('/acheter'); ?>" class="btn btn-primary">
                Acheter maintenant
            </a>
        <?php else: ?>
            <table class="licenses-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Clé de licence</th>
                        <th>Statut</th>
                        <th>Expire le</th>
                        <th>Activations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($licenses['data'] as $license): ?>
                    <tr>
                        <td><?php echo esc_html($license['product']['name']); ?></td>
                        <td>
                            <code class="license-key" data-full="<?php echo esc_attr($license['uuid']); ?>">
                                <?php echo esc_html(substr($license['uuid'], 0, 8) . '...'); ?>
                            </code>
                            <button type="button" class="btn-copy" onclick="copyLicense(this)">
                                Copier
                            </button>
                        </td>
                        <td>
                            <span class="status status-<?php echo esc_attr($license['status']); ?>">
                                <?php echo esc_html(ucfirst($license['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($license['expires_at']): ?>
                                <?php echo date('d/m/Y', strtotime($license['expires_at'])); ?>
                            <?php else: ?>
                                <span class="lifetime">Lifetime</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo (int)$license['activations_count']; ?>
                            /
                            <?php echo $license['activations_limit'] ?: '∞'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Déconnexion -->
    <section class="account-section">
        <a href="<?php echo wp_nonce_url(home_url('/deconnexion'), 'logout'); ?>"
           class="btn btn-secondary">
            Se déconnecter
        </a>
    </section>
</div>

<script>
function copyLicense(btn) {
    const code = btn.previousElementSibling;
    const fullKey = code.dataset.full;
    navigator.clipboard.writeText(fullKey).then(() => {
        btn.textContent = 'Copié !';
        setTimeout(() => btn.textContent = 'Copier', 2000);
    });
}
</script>

<?php get_footer(); ?>
```

---

### Checkout Stripe

#### Page produit avec bouton d'achat

```php
<?php
// single-product.php ou template personnalisé

$product_id = get_the_ID();
$price_id = get_post_meta($product_id, 'plugin_hub_price_id', true);

// Vérifier si l'utilisateur est connecté pour acheter
$can_buy = plugin_hub()->isLoggedIn();
?>

<div class="product-page">
    <h1><?php the_title(); ?></h1>
    <div class="product-price">49€ / an</div>

    <?php if ($can_buy): ?>
        <form method="POST" action="<?php echo home_url('/checkout'); ?>">
            <?php wp_nonce_field('checkout', 'checkout_nonce'); ?>
            <input type="hidden" name="price_id" value="<?php echo esc_attr($price_id); ?>">
            <button type="submit" class="btn btn-primary btn-large">
                Acheter maintenant
            </button>
        </form>
    <?php else: ?>
        <a href="<?php echo home_url('/connexion?redirect_to=' . urlencode(get_permalink())); ?>"
           class="btn btn-primary btn-large">
            Se connecter pour acheter
        </a>
    <?php endif; ?>
</div>
```

#### Page de checkout (redirection Stripe)

Créez `page-checkout.php` :

```php
<?php
/**
 * Template Name: Checkout
 */

if (!plugin_hub()->isLoggedIn()) {
    wp_redirect(home_url('/connexion'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wp_redirect(home_url());
    exit;
}

if (!wp_verify_nonce($_POST['checkout_nonce'] ?? '', 'checkout')) {
    wp_die('Session expirée, veuillez réessayer.');
}

$price_id = (int) ($_POST['price_id'] ?? 0);
if (!$price_id) {
    wp_die('Produit invalide.');
}

// Rediriger vers Stripe Checkout
plugin_hub()->redirectToCheckout(
    $price_id,
    home_url('/merci'),          // URL de succès
    home_url('/panier?cancelled=1')  // URL d'annulation
);
```

#### Page de succès

```php
<?php
/**
 * Template Name: Merci
 */

get_header();
?>

<div class="success-page">
    <h1>Merci pour votre achat !</h1>
    <p>Votre licence a été créée et envoyée par email.</p>
    <p>
        <a href="<?php echo home_url('/mon-compte'); ?>" class="btn btn-primary">
            Voir mes licences
        </a>
    </p>
</div>

<?php get_footer(); ?>
```

---

### Réception des webhooks

Les webhooks permettent de recevoir les événements en temps réel (nouvel achat, renouvellement, etc.).

#### Configuration

Dans `functions.php` :

```php
// Enregistrer l'endpoint webhook
PluginHubWebhook::registerRestEndpoint(
    'mon-site/v1',
    '/plugin-hub-webhook',
    'votre-webhook-secret'  // Configuré dans le back-office Plugin Hub
);

// L'URL sera : https://votre-site.com/wp-json/mon-site/v1/plugin-hub-webhook
```

#### Gérer les événements

```php
// Nouvelle licence créée
add_action('plugin_hub_webhook_license_created', function ($data) {
    $license = $data['license'];
    $user = $data['user'];

    // Envoyer un email personnalisé
    wp_mail(
        $user['email'],
        'Votre licence ' . $license['product']['name'],
        "Bonjour {$user['name']},\n\n" .
        "Votre clé de licence : {$license['key']}\n\n" .
        "Installez le plugin et activez votre licence dans Réglages > Licence."
    );

    // Logger dans la BDD
    // ...
}, 10, 1);

// Licence expirée
add_action('plugin_hub_webhook_license_expired', function ($data) {
    $user = $data['user'];

    wp_mail(
        $user['email'],
        'Votre licence a expiré',
        "Renouvelez maintenant pour continuer à recevoir les mises à jour :\n" .
        home_url('/renouveler')
    );
});

// Paiement échoué
add_action('plugin_hub_webhook_payment_failed', function ($data) {
    // Notifier l'admin
    wp_mail(
        get_option('admin_email'),
        'Paiement échoué',
        "Client : {$data['user']['email']}\nMontant : {$data['amount']}€"
    );
});
```

---

## SDK Plugin installé

### Installation plugin

Copiez le fichier dans votre plugin :

```
wp-content/plugins/mon-plugin-premium/
├── mon-plugin-premium.php
└── includes/
    └── class-plugin-hub-license.php
```

### Intégration dans votre plugin

Dans le fichier principal du plugin :

```php
<?php
/**
 * Plugin Name: Mon Plugin Premium
 * Description: Un super plugin avec des fonctionnalités premium
 * Version: 1.2.0
 * Author: Votre Nom
 */

defined('ABSPATH') || exit;

// Constantes
define('MON_PLUGIN_VERSION', '1.2.0');
define('MON_PLUGIN_FILE', __FILE__);
define('MON_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Charger le SDK de licence
require_once MON_PLUGIN_PATH . 'includes/class-plugin-hub-license.php';

/**
 * Initialise le gestionnaire de licence
 */
function mon_plugin_init_license() {
    $GLOBALS['mon_plugin_license'] = new PluginHubLicense([
        'api_url' => 'https://hub.wabeo.work',
        'product_slug' => 'mon-plugin-premium',
        'plugin_file' => MON_PLUGIN_FILE,
        'plugin_version' => MON_PLUGIN_VERSION,
    ]);
    $GLOBALS['mon_plugin_license']->init();
}
add_action('plugins_loaded', 'mon_plugin_init_license');

/**
 * Helper pour accéder à la licence
 */
function mon_plugin_license(): PluginHubLicense {
    return $GLOBALS['mon_plugin_license'];
}

/**
 * Vérifie si la licence est valide
 */
function mon_plugin_is_licensed(): bool {
    return mon_plugin_license()->isValid();
}

// Charger le reste du plugin
require_once MON_PLUGIN_PATH . 'includes/class-mon-plugin.php';
```

### Restreindre des fonctionnalités

#### Exemple 1 : Désactiver une fonctionnalité sans licence

```php
// Dans votre code
function mon_plugin_feature_export() {
    if (!mon_plugin_is_licensed()) {
        wp_die(
            '<h1>Fonctionnalité Premium</h1>' .
            '<p>Cette fonctionnalité nécessite une licence active.</p>' .
            '<p><a href="https://votre-site.com/acheter">Acheter une licence</a></p>',
            'Licence requise',
            ['back_link' => true]
        );
    }

    // Code de la fonctionnalité...
}
```

#### Exemple 2 : Afficher un message dans un shortcode

```php
add_shortcode('mon_shortcode_premium', function ($atts) {
    if (!mon_plugin_is_licensed()) {
        return '<div class="mon-plugin-notice">' .
               '<p>Cette fonctionnalité nécessite une licence.</p>' .
               '<a href="https://votre-site.com/acheter" class="button">Acheter</a>' .
               '</div>';
    }

    // Rendu normal du shortcode
    return mon_plugin_render_shortcode($atts);
});
```

#### Exemple 3 : Limiter le nombre d'éléments

```php
function mon_plugin_get_items() {
    $items = get_posts(['post_type' => 'mon_type', 'posts_per_page' => -1]);

    // Version gratuite : limité à 5 éléments
    if (!mon_plugin_is_licensed() && count($items) > 5) {
        $items = array_slice($items, 0, 5);
        // Ajouter un flag pour afficher un message
        $GLOBALS['mon_plugin_limited'] = true;
    }

    return $items;
}
```

#### Exemple 4 : Menu admin conditionnel

```php
add_action('admin_menu', function () {
    // Menu principal toujours visible
    add_menu_page(
        'Mon Plugin',
        'Mon Plugin',
        'manage_options',
        'mon-plugin',
        'mon_plugin_admin_page',
        'dashicons-admin-plugins'
    );

    // Sous-menu premium uniquement si licence valide
    if (mon_plugin_is_licensed()) {
        add_submenu_page(
            'mon-plugin',
            'Export Avancé',
            'Export Avancé',
            'manage_options',
            'mon-plugin-export',
            'mon_plugin_export_page'
        );
    }
});
```

#### Exemple 5 : Hooks conditionnels

```php
// N'activer certains hooks que si licence valide
if (mon_plugin_is_licensed()) {
    add_filter('the_content', 'mon_plugin_filter_content');
    add_action('wp_footer', 'mon_plugin_footer_scripts');
}
```

---

## Structure de fichiers recommandée

### Site de vente

```
wp-content/themes/theme-vente/
├── functions.php
├── includes/
│   └── plugin-hub/
│       ├── class-plugin-hub-client.php
│       └── class-plugin-hub-webhook.php
├── page-connexion.php
├── page-inscription.php
├── page-mon-compte.php
├── page-checkout.php
├── page-merci.php
└── page-deconnexion.php
```

### Plugin avec licence

```
wp-content/plugins/mon-plugin-premium/
├── mon-plugin-premium.php
├── includes/
│   ├── class-plugin-hub-license.php
│   ├── class-mon-plugin.php
│   └── ...
├── assets/
└── templates/
```

---

## Bonnes pratiques

### Sécurité

1. **Ne jamais exposer les tokens** côté client (JavaScript)
2. **Toujours vérifier les nonces** sur les formulaires
3. **Stocker les secrets** dans `wp-config.php`

```php
// wp-config.php
define('PLUGIN_HUB_API_TOKEN', 'votre-token');
define('PLUGIN_HUB_WEBHOOK_SECRET', 'votre-secret');
```

### Performance

1. **Utiliser le cache** du SDK (les vérifications sont cachées 24h)
2. **Ne pas appeler l'API** à chaque page load
3. **Vérifier isLoggedIn()** avant getUser() (évite une requête inutile)

### UX

1. **Rediriger après login** vers la page d'origine
2. **Messages clairs** pour les erreurs
3. **Afficher le statut** de la licence dans l'admin
