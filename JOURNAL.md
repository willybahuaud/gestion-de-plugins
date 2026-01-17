# Journal de développement — Plugin Hub

*Fichier de suivi pour garder le fil du projet entre les sessions*

---

## Dernière mise à jour : 18 janvier 2026

---

## État actuel du projet

### Infrastructure
| Élément | Statut | Notes |
|---------|--------|-------|
| Laravel 12 | Installé | PHP 8.2+ |
| Migrations | 13 créées | Toutes les tables du brief |
| Modèles | 11 créés | Avec relations |
| JWT Auth | Configuré | tymon/jwt-auth v2.0 |
| Stripe | Configuré | Clés test en place |
| Redis | Configuré | Via socket o2switch |
| Backblaze B2 | Configuré | Pour les releases |

### Authentification (3 niveaux)
| Type | Statut | Mécanisme |
|------|--------|-----------|
| Admin | Fonctionnel | Session Laravel |
| Users API | Fonctionnel | JWT |
| Sites WordPress | Fonctionnel | API Tokens |

### API Endpoints
| Groupe | Statut | Endpoints |
|--------|--------|-----------|
| Auth | Fonctionnel | register, login, logout, refresh, user, update |
| Licences | Fonctionnel | verify, activate, deactivate, getActivations |
| Updates | Fonctionnel | check, download (URL signée) |
| Stripe | Fonctionnel | checkout, subscriptions, cancel, reactivate |
| Webhooks Stripe | Fonctionnel | checkout.completed, subscription.*, invoice.* |

### Back-office Admin
| Page | Statut | Notes |
|------|--------|-------|
| Login | Complet | |
| Dashboard | Complet | Stats + dernières activités |
| Products - Liste | Complet | |
| Products - Création | Complet | |
| Products - Édition | Complet | |
| Products - Détail | Complet | Avec liens prix et releases |
| Prices - Création | Complet | Sync Stripe |
| Prices - Édition | Complet | |
| Releases - Liste | Complet | Par produit |
| Releases - Création | Complet | Upload ZIP, changelog, publication programmée |
| Releases - Détail | Complet | Hash SHA256, téléchargement |
| Releases - Édition | Complet | |
| Users - Liste | Complet | Avec recherche |
| Users - Détail | Complet | Licences + factures |
| Licenses - Liste | Complet | Filtres statut/produit |
| Licenses - Détail | Complet | Avec activations |
| Licenses - Création | Complet | Sélection produit/prix dynamique |
| Licenses - Édition | Complet | Statut, activations, expiration |
| API Tokens - Liste | Complet | |
| API Tokens - Création | Complet | |
| Profile Admin | Complet | Infos + mot de passe |

### Services
| Service | Statut | Fonctionnalités |
|---------|--------|-----------------|
| StripeService | Fonctionnel | Sync produits/prix, checkout, subscriptions |
| WebhookService | Fonctionnel | Dispatch événements vers sites externes |

### Jobs
| Job | Statut | Notes |
|-----|--------|-------|
| SendWebhook | Fonctionnel | HMAC signature, retry 3x |

### Déploiement
| Élément | Statut | Notes |
|---------|--------|-------|
| o2switch | Déployé | Via hook Git + SSH |
| URL | https://hub.wabeo.work | |
| Dossier serveur | ~/hub-app | |
| Cloudflare | ? | À vérifier |
| HTTPS | Oui | |

---

## Ce qui reste à faire

### Priorité haute (fonctionnel)
- [x] ~~**Vues licences** : créer `create.blade.php` et `edit.blade.php`~~ (fait 17/01)
- [x] ~~**CRUD Prix** : contrôleur + routes + vues~~ (fait 17/01)
- [x] ~~**CRUD Releases** : contrôleur + routes + vues + upload zip~~ (fait 17/01)

### Priorité haute (sécurité)
- [x] ~~**Passkeys** pour les admins (1Password compatible)~~ (fait 17/01)
- [x] ~~**Rate limiting** sur login (5 tentatives/15min)~~ (fait 17/01)
- [x] ~~**Rate limiting** par licence sur verify (10 req/heure)~~ (fait 17/01)
- [x] ~~**Signature HMAC** pour les requêtes plugins~~ (fait 17/01)
- [x] ~~**Validation/blacklist domaines** (localhost, IPs privées)~~ (fait 17/01)
- [x] ~~**Headers de sécurité** (X-Frame-Options, CSP, etc.)~~ (fait 17/01)

### Priorité moyenne
- [x] ~~**Chiffrement** des secrets webhooks en BDD~~ (fait 18/01)
- [x] ~~**Logs d'audit** des actions admin~~ (fait 18/01)
- [x] ~~**Idempotence webhooks Stripe** (éviter double-traitement)~~ (fait 18/01)
- [x] ~~**Session timeout** admin (30min)~~ (fait 17/01)
- [x] ~~**Configurer webhook Stripe**~~ (fait 17/01)

### Priorité basse
- [x] ~~**Tests unitaires**~~ (fait 18/01)
- [x] ~~**Tests d'intégration API**~~ (fait 18/01)
- [x] ~~**SDK WordPress** pour les plugins~~ (fait 17/01)

---

## Historique des sessions

### 18 janvier 2026
- **Sécurité renforcée**
  - Chiffrement des secrets webhooks en BDD (cast `encrypted` sur WebhookEndpoint)
  - Migration pour convertir les secrets existants
  - Logs d'audit complets : table, modèle AuditLog, trait Auditable
  - Logging auto sur: Product, Price, Release, License, User, ApiToken, WebhookEndpoint
  - Logging login/logout admin (password et passkey)
  - Vue admin pour consulter les logs avec filtres
  - Idempotence webhooks Stripe (table stripe_processed_events)
  - Commande de nettoyage hebdomadaire (stripe:cleanup-events)
- **Tests** (40 tests, 63 assertions)
  - Factories pour Product, Price, License, Activation
  - Tests unitaires pour License, Activation, AuditLog
  - Tests d'intégration API pour verify, activate, deactivate
  - Migration pour ajouter colonnes manquantes sur activations
  - Configuration `.env.testing` pour SQLite en mémoire
  - Trait Auditable désactivé pendant les tests (`app()->runningUnitTests()`)
- **Corrections**
  - Accesseurs uuid et activations_limit sur License (compatibilité API)
  - Controller utilise license_key au lieu de uuid
  - Migration audit_logs : FK corrigée vers `admin_users` (était `admins`)
  - `Activation::normalizeDomain()` accepte null

### 17 janvier 2026 (nuit)
- **Configuration webhook Stripe**
  - Installation Stripe CLI en local
  - Création endpoint dans Stripe Dashboard (test)
  - STRIPE_WEBHOOK_SECRET configuré sur o2switch
  - Test avec `stripe trigger` → 200 OK
- **SDK WordPress - Sites de vente** (`sdk/wordpress-site/`)
  - `PluginHubClient` : auth SSO, checkout, licences, abonnements
  - `PluginHubWebhook` : réception webhooks avec vérification HMAC
  - Intégration REST API WordPress
  - Documentation complète avec exemples
- **SDK WordPress - Plugins installés** (`sdk/wordpress-plugin/`)
  - `PluginHubLicense` : activation/désactivation licence
  - Mises à jour automatiques via hooks WordPress
  - Page admin dans Réglages
  - Notices pour licences expirées/suspendues
  - Documentation complète
- **Documentation d'implémentation** (`sdk/IMPLEMENTATION.md`)
  - Architecture SSO avec schéma
  - Templates WordPress complets (connexion, inscription, mon compte, checkout)
  - Exemples de restriction de fonctionnalités
  - Bonnes pratiques

### 17 janvier 2026 (soir)
- **Correction Passkeys WebAuthn** : nombreux ajustements pour compatibilité laragear/webauthn v3
  - Remplacement de Webpass (lib JS) par fetch natif (problèmes CSRF)
  - Ajout middleware `guard:admin` pour que `$request->user()` fonctionne
  - Migration webauthn du package (structure table différente)
  - Config auth : driver `eloquent-webauthn` pour le provider admins
  - Adaptation contrôleur à la nouvelle API du package (save(), toCreate(), toVerify())
- Passkeys fonctionnelles : enregistrement + login + suppression
- **Refonte page login** : UX améliorée passkey-first
  - Flow en étapes : email → vérification → passkey ou password
  - Route `/admin/check-email` avec rate limit 5/5min (anti-énumération)
  - Switch possible entre passkey et mot de passe
- **Session timeout 30 min** avec modal de reconnexion
  - SESSION_LIFETIME=30 sur le serveur
  - Modal "session expirée" avec reconnexion passkey
  - Wrapper fetch qui intercepte les 401 pour afficher la modal
  - Icône passkey dans la navbar admin

### 17 janvier 2026 (après-midi)
- **CRUD Prix** : PriceController + routes + vues create/edit
  - Sync automatique avec Stripe
  - Gestion recurring/one_time
  - Mise à jour products/show avec liens
- **CRUD Releases** : ReleaseController + routes + vues complètes
  - Upload fichiers ZIP (max 50Mo)
  - Hash SHA256 calculé automatiquement
  - Publication immédiate ou programmée
  - Téléchargement admin
  - Stockage local (releases) ou B2 en prod
- **Vues Licences** : create.blade.php et edit.blade.php
  - Création manuelle de licences (offrir, migration)
  - Sélection dynamique produit → prix avec JS
  - Auto-remplissage type/activations depuis le prix sélectionné
  - Édition statut, limite activations, expiration
  - Ajout bouton Modifier dans la vue show
- **Sécurité complète** :
  - Rate limiting : login admin (5/15min), verify (10/h), activate (5/h), auth API (10/min)
  - Headers sécurité : X-Frame-Options, X-Content-Type-Options, CSP, HSTS, Referrer-Policy
  - Passkeys WebAuthn : enregistrement, login, gestion (compatible 1Password)
  - Signature HMAC : middleware optionnel pour requêtes plugins (anti-replay)
  - Validation domaines : blacklist localhost/IPs privées/domaines dev
- Vérification config Stripe sur o2switch (clés test OK)
- Mise à jour CLAUDE.md avec infos SSH

### 17 janvier 2026 (matin)
- Création du `brief-complet.md` après phase de questions
- Création de `security-analysis.md` avec recommandations
- Exploration du projet existant (déjà très avancé)
- Création de ce fichier `JOURNAL.md`

### Sessions précédentes
- Initialisation Laravel 12
- Création des migrations et modèles
- Intégration Stripe
- Création de l'API complète
- Création du back-office admin
- Déploiement initial o2switch

---

## Notes techniques

### Accès o2switch
```bash
# SSH
ssh sc4wabeodev@pesto.o2switch.net
cd ~/hub-app

# Commandes utiles
php artisan migrate
php artisan config:cache
php artisan route:cache
php artisan view:clear
```

### Déploiement
```bash
# Push déclenche le déploiement automatique
git push origin main
# Webhook : https://hub.wabeo.work/deploy.php
```

### Variables d'environnement (o2switch)
| Variable | Statut |
|----------|--------|
| APP_KEY | Configuré |
| JWT_SECRET | Configuré |
| STRIPE_KEY | Configuré (test) |
| STRIPE_SECRET | Configuré (test) |
| STRIPE_WEBHOOK_SECRET | À configurer |
| B2_* | À vérifier |

### Dossier releases
```bash
# Créer si nécessaire
mkdir -p ~/hub-app/storage/app/releases
```

---

*Ce fichier doit être mis à jour à chaque session de travail.*
