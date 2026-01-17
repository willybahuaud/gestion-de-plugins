# Analyse de Sécurité — Plateforme de Gestion de Plugins

*Document généré le 17 janvier 2026*

---

## 1. Points forts (déjà prévus)

| Domaine | Mesure | Évaluation |
|---------|--------|------------|
| Transport | HTTPS obligatoire | Bon |
| Rate limiting | 60 req/min par IP | Bon, mais à affiner |
| Webhooks Stripe | Vérification signature HMAC-SHA256 | Bon |
| Webhooks sortants | Signature HMAC-SHA256 | Bon |
| Téléchargements | Signed URLs avec expiration 10min | Bon |
| Timing attacks | Réponse identique valide/invalide | Bon |
| DDoS | Cloudflare en proxy | Bon |
| Mots de passe | Bcrypt | Bon (standard Laravel) |

---

## 2. Vulnérabilités identifiées et recommandations

### 2.1 Authentification

#### Risque : Absence de 2FA pour le back-office

**Gravité : Haute**

Le brief mentionne "2FA recommandé" mais pas obligatoire. Le back-office donne accès à toutes les licences, données clients, et permet de modifier les configurations critiques.

**Recommandation** : Implémenter 2FA dès V1 pour les admins via TOTP (Google Authenticator, Authy). Packages recommandés :
- `pragmarx/google2fa-laravel`
- `laravel/fortify` avec 2FA intégré

#### Risque : JWT sans rotation ni révocation

**Gravité : Moyenne**

Les JWT utilisateurs ne mentionnent pas de mécanisme de révocation. Un token volé reste valide jusqu'à expiration.

**Recommandation** :
- Tokens à courte durée : 15-30 minutes
- Refresh tokens à durée plus longue : 7-30 jours
- Blacklist de tokens révoqués en Redis
- Invalider tous les tokens d'un user en cas de changement de mot de passe

#### Risque : Pas de limite de tentatives de login

**Gravité : Haute**

Aucune mention de protection brute-force sur `/api/v1/auth/login`.

**Recommandation** : Throttling spécifique :
```php
// routes/api.php
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,15'); // 5 tentatives par 15 minutes
```

Ajouter aussi un throttle par email (pas seulement par IP) pour bloquer les attaques distribuées.

---

### 2.2 API de vérification de licence

#### Risque : Endpoints publics sans authentification

**Gravité : Haute**

Les endpoints `/api/v1/licenses/verify`, `/activate`, `/deactivate` sont accessibles sans authentification. Un attaquant pourrait :
- Tester des clés de licence par énumération
- Activer/désactiver des domaines s'il connaît une clé

**Recommandations** :

1. **Ajouter un HMAC de requête** signé avec un secret partagé par plugin :

```php
// Côté plugin WordPress
$timestamp = time();
$payload = json_encode($data);
$signature = hash_hmac('sha256', $timestamp . $payload, PLUGIN_SECRET);

// Headers envoyés
X-Timestamp: 1705412345
X-Plugin-Signature: abc123...
```

```php
// Côté plateforme - Middleware de vérification
public function handle($request, Closure $next)
{
    $timestamp = $request->header('X-Timestamp');
    $signature = $request->header('X-Plugin-Signature');

    // Vérifier que le timestamp n'est pas trop vieux (5 min)
    if (abs(time() - $timestamp) > 300) {
        return response()->json(['error' => 'Request expired'], 401);
    }

    // Récupérer le secret du produit
    $product = Product::where('slug', $request->product_slug)->first();
    $expectedSignature = hash_hmac('sha256', $timestamp . $request->getContent(), $product->api_secret);

    if (!hash_equals($expectedSignature, $signature)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    return $next($request);
}
```

2. **Rate limiting par licence** (pas seulement par IP) :
```php
// 10 requêtes par heure par clé de licence
RateLimiter::for('license-verify', function (Request $request) {
    return Limit::perHour(10)->by($request->license_key);
});
```

3. **Logger les tentatives suspectes** :
- Plusieurs domaines différents pour une même clé
- Clés invalides répétées depuis une même IP
- Patterns d'énumération (clés séquentielles)

---

### 2.3 Sécurité des clés de licence

#### Risque : Format UUID potentiellement faible

**Gravité : Basse**

UUID v4 est aléatoire (122 bits d'entropie) mais le format est prévisible.

**Recommandation** : Utiliser un format renforcé :

```php
// Option 1 : Préfixe + hash long
$key = 'LIC-' . strtoupper(bin2hex(random_bytes(16)));
// Résultat : LIC-A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6

// Option 2 : Format segmenté lisible
$key = sprintf('%s-%s-%s-%s-%s',
    strtoupper(bin2hex(random_bytes(4))),
    strtoupper(bin2hex(random_bytes(4))),
    strtoupper(bin2hex(random_bytes(4))),
    strtoupper(bin2hex(random_bytes(4))),
    strtoupper(bin2hex(random_bytes(4)))
);
// Résultat : A1B2C3D4-E5F6G7H8-I9J0K1L2-M3N4O5P6-Q7R8S9T0
```

---

### 2.4 Stockage des secrets

#### Risque : Secrets webhooks en clair dans la BDD

**Gravité : Moyenne**

Les `secret` des WebhookEndpoints sont stockés en clair. En cas de fuite de la BDD, tous les secrets sont compromis.

**Recommandation** : Chiffrer avec les helpers Laravel :

```php
// Model WebhookEndpoint
protected $casts = [
    'secret' => 'encrypted',
];
```

Ou manuellement :
```php
// Stockage
$endpoint->secret = encrypt($plainSecret);

// Lecture
$plainSecret = decrypt($endpoint->secret);
```

---

### 2.5 Validation des domaines

#### Risque : Manipulation du domaine lors de l'activation

**Gravité : Moyenne**

Un utilisateur malveillant pourrait :
- Activer sur `localhost`, `127.0.0.1`, ou des IPs privées
- Exploiter des sous-domaines multiples pour contourner les limites

**Recommandations** :

1. **Blacklister les domaines/IPs invalides** :

```php
class DomainValidator
{
    private array $blacklist = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '*.local',
        '*.test',
        '*.example',
        '*.invalid',
        '*.localhost',
    ];

    private array $privateRanges = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    public function isValid(string $domain): bool
    {
        $domain = $this->normalize($domain);

        // Vérifier blacklist
        foreach ($this->blacklist as $pattern) {
            if (fnmatch($pattern, $domain)) {
                return false;
            }
        }

        // Vérifier si c'est une IP privée
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return !$this->isPrivateIp($domain);
        }

        return true;
    }

    public function normalize(string $domain): string
    {
        // Enlever protocole
        $domain = preg_replace('#^https?://#', '', $domain);
        // Enlever www.
        $domain = preg_replace('#^www\.#', '', $domain);
        // Enlever port
        $domain = preg_replace('#:\d+$#', '', $domain);
        // Enlever path
        $domain = explode('/', $domain)[0];
        // Minuscules
        return strtolower(trim($domain));
    }
}
```

2. **Politique de sous-domaines** à définir :
- Option A : `site.com` et `www.site.com` = même activation
- Option B : `*.site.com` = 1 activation wildcard
- Option C : Chaque sous-domaine = 1 activation distincte

---

### 2.6 Webhooks entrants Stripe

#### Risque : Replay attacks

**Gravité : Moyenne**

Un webhook intercepté pourrait être rejoué pour déclencher des actions multiples.

**Recommandation** : Vérifier le timestamp Stripe (Laravel Cashier le fait automatiquement) :

```php
// Si pas Cashier, vérification manuelle
$payload = $request->getContent();
$sigHeader = $request->header('Stripe-Signature');

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        config('services.stripe.webhook_secret')
    );
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    return response('Invalid signature', 400);
}

// Stripe vérifie que le webhook a moins de 5 minutes
```

**Bonus** : Stocker les event IDs traités pour éviter le double-traitement :

```php
// Table: processed_stripe_events (event_id unique)
if (ProcessedStripeEvent::where('event_id', $event->id)->exists()) {
    return response('Already processed', 200);
}

ProcessedStripeEvent::create(['event_id' => $event->id]);
```

---

### 2.7 Téléchargements

#### Risque : Partage d'URLs signées

**Gravité : Basse**

Une URL signée peut être partagée pendant sa durée de validité (10 minutes).

**Recommandations** :
- Réduire l'expiration à 5 minutes
- Logger tous les téléchargements avec IP, licence, domaine, user-agent
- Alerter si une même URL est utilisée depuis plusieurs IPs différentes

```php
// Génération URL signée Backblaze B2
$signedUrl = $b2->createSignedUrl(
    $release->file_path,
    now()->addMinutes(5),
    [
        'license' => $license->license_key,
        'domain' => $domain,
    ]
);

// Log du téléchargement
DownloadLog::create([
    'license_id' => $license->id,
    'release_id' => $release->id,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'domain' => $domain,
]);
```

---

### 2.8 Injection SQL / XSS

#### Risque : Faible si Laravel est correctement utilisé

**Gravité : Variable**

**Recommandations** :

1. **SQL Injection** : Toujours utiliser Eloquent ou Query Builder
```php
// BON
User::where('email', $request->email)->first();

// MAUVAIS
DB::select("SELECT * FROM users WHERE email = '{$request->email}'");
```

2. **XSS** : Échapper toutes les sorties Blade
```blade
{{-- BON - échappé automatiquement --}}
{{ $user->name }}

{{-- MAUVAIS - pas d'échappement --}}
{!! $user->bio !!}
```

3. **Validation stricte** : Utiliser FormRequest pour tous les inputs
```php
class VerifyLicenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'uuid'],
            'domain' => ['required', 'string', 'max:255'],
            'product_slug' => ['required', 'string', 'alpha_dash', 'max:50'],
        ];
    }
}
```

---

### 2.9 Protection CSRF

#### Risque : Applicable au back-office uniquement

**Gravité : Moyenne**

Les APIs REST stateless ne sont pas concernées (pas de cookies de session). Le back-office l'est.

**Recommandation** : S'assurer que tous les formulaires du back-office incluent `@csrf` :

```blade
<form method="POST" action="/admin/licenses/{{ $license->id }}">
    @csrf
    @method('PUT')
    ...
</form>
```

---

### 2.10 Sécurité du back-office

#### Risques additionnels

**Gravité : Moyenne**

- Pas de logs d'audit des actions admin
- Pas de session timeout mentionné
- Pas de re-authentification pour actions sensibles

**Recommandations** :

1. **Table d'audit** :
```php
// Migration
Schema::create('admin_activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_user_id')->constrained();
    $table->string('action'); // 'license.revoked', 'user.deleted', etc.
    $table->string('target_type')->nullable(); // 'License', 'User', etc.
    $table->unsignedBigInteger('target_id')->nullable();
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->ipAddress('ip_address');
    $table->string('user_agent')->nullable();
    $table->timestamp('created_at');
});
```

2. **Session timeout** :
```php
// config/session.php
'lifetime' => 30, // 30 minutes
'expire_on_close' => true,
```

3. **Re-authentification pour actions sensibles** :
```php
// Middleware pour actions critiques
public function handle($request, Closure $next)
{
    if (!session('password_confirmed_at') ||
        now()->diffInMinutes(session('password_confirmed_at')) > 5) {
        return redirect()->route('admin.password.confirm');
    }

    return $next($request);
}
```

---

### 2.11 Headers de sécurité

#### Risque : Headers manquants

**Gravité : Moyenne**

**Recommandation** : Ajouter via middleware Laravel :

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle($request, Closure $next)
{
    $response = $next($request);

    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

    // CSP pour le back-office (adapter selon les besoins)
    if ($request->is('admin/*')) {
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    }

    return $response;
}
```

Ou via Cloudflare (Transform Rules > Modify Response Headers).

---

### 2.12 Données sensibles et erreurs

#### Risque : Exposition d'informations techniques

**Gravité : Moyenne**

**Recommandations** :

1. **Production** :
```env
APP_DEBUG=false
APP_ENV=production
```

2. **Handler d'exceptions personnalisé** :
```php
// app/Exceptions/Handler.php
public function render($request, Throwable $e)
{
    if ($request->expectsJson()) {
        if ($e instanceof ModelNotFoundException) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        if ($e instanceof ValidationException) {
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        }

        // Erreur générique en production
        if (app()->isProduction()) {
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    return parent::render($request, $e);
}
```

---

## 3. Tableau récapitulatif des priorités

| Risque | Gravité | Priorité V1 | Effort |
|--------|---------|-------------|--------|
| 2FA back-office | Haute | **Obligatoire** | Moyen |
| Rate limiting login | Haute | **Obligatoire** | Faible |
| Signature requêtes plugin (HMAC) | Haute | **Obligatoire** | Moyen |
| Validation/blacklist domaines | Moyenne | **Obligatoire** | Faible |
| Rate limiting par licence | Moyenne | Recommandé | Faible |
| Chiffrement secrets BDD | Moyenne | Recommandé | Faible |
| Headers sécurité | Moyenne | Recommandé | Faible |
| Logs audit admin | Moyenne | Recommandé | Moyen |
| Idempotence webhooks Stripe | Moyenne | Recommandé | Faible |
| JWT rotation/révocation | Moyenne | Recommandé | Moyen |
| Format clé licence renforcé | Basse | Optionnel | Faible |
| Logs téléchargements | Basse | Optionnel | Faible |

---

## 4. Schéma de flux sécurisé pour vérification licence

```
Plugin WP                              Plateforme
    │                                       │
    │  1. Préparer la requête               │
    │     - timestamp = now()               │
    │     - payload = {license, domain}     │
    │     - signature = HMAC(timestamp +    │
    │                        payload,       │
    │                        PLUGIN_SECRET) │
    │                                       │
    ├──── POST /api/v1/licenses/verify ────►│
    │     Headers:                          │
    │       X-Plugin-Signature: {sig}       │
    │       X-Timestamp: {ts}               │
    │     Body:                             │
    │       license_key, domain, slug       │
    │                                       │
    │                                  ┌────┴─────┐
    │                                  │ Vérifier │
    │                                  ├──────────┤
    │                                  │ 1. Timestamp < 5min ?
    │                                  │ 2. Signature HMAC valide ?
    │                                  │ 3. Rate limit licence OK ?
    │                                  │ 4. Rate limit IP OK ?
    │                                  │ 5. Licence existe ?
    │                                  │ 6. Licence active ?
    │                                  │ 7. Produit correspond ?
    │                                  │ 8. Domaine autorisé ?
    │                                  │ 9. Quota activations OK ?
    │                                  └────┬─────┘
    │                                       │
    │◄───── 200 OK ─────────────────────────┤
    │       {                               │
    │         "valid": true,                │
    │         "license": {...},             │
    │         "update_available": true      │
    │       }                               │
    │                                       │
    │  2. Stocker en transient (24h)        │
    │                                       │
```

---

## 5. Checklist de sécurité pré-déploiement

### Configuration serveur
- [ ] HTTPS forcé (redirect HTTP → HTTPS)
- [ ] Certificat SSL valide (Let's Encrypt ou autre)
- [ ] Cloudflare activé en mode proxy
- [ ] Headers de sécurité configurés

### Configuration Laravel
- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` généré et sécurisé
- [ ] Logs en mode `daily` avec rotation

### Base de données
- [ ] Utilisateur MySQL dédié avec permissions minimales
- [ ] Mots de passe forts
- [ ] Pas d'accès distant (localhost only)

### Secrets
- [ ] `.env` non accessible publiquement
- [ ] Secrets webhooks chiffrés en BDD
- [ ] API keys Stripe en mode live (pas test)
- [ ] Webhook secret Stripe configuré

### Authentification
- [ ] 2FA activé pour tous les admins
- [ ] Rate limiting sur login configuré
- [ ] Session timeout configuré

### API
- [ ] Rate limiting global activé
- [ ] Signature HMAC implémentée
- [ ] Validation domaines implémentée
- [ ] Logs d'accès activés

### Monitoring
- [ ] Alertes sur erreurs 500
- [ ] Alertes sur tentatives de login échouées
- [ ] Monitoring uptime configuré

---

## 6. Ressources et références

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Stripe Webhook Security](https://stripe.com/docs/webhooks/signatures)
- [Cloudflare Security Headers](https://developers.cloudflare.com/rules/transform/response-header-modification/)

---

*Document validé le : ____________________*

*Signature : ____________________*
