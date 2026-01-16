# Brief Complet — Plateforme de Gestion de Plugins WordPress

*Document généré le 16 janvier 2026 suite à la phase de clarification*

---

## 1. Vision du projet

### 1.1 Résumé

Construire une **plateforme centrale invisible** qui sert de couche d'abstraction entre Stripe et les sites de vente WordPress. Cette plateforme est le coeur technique qui gère :
- Les licences et leur cycle de vie
- Les utilisateurs et leur authentification (SSO)
- Les versions de plugins et leur distribution
- La synchronisation avec Stripe (paiements, abonnements, factures)
- Les webhooks sortants vers les sites de vente

### 1.2 Philosophie

- **Invisible pour le client final** : le client n'accède jamais directement à la plateforme
- **Source de vérité unique** : toute la logique métier est centralisée ici
- **Sites de vente légers** : WordPress ne fait que consommer l'API et afficher
- **Coûts maîtrisés** : architecture simple, hébergement mutualisé au départ
- **Évolutif** : pensé pour 500 clients la 1ère année, 2500 la 3ème, scalable au-delà

### 1.3 Périmètre

| Élément | V1 | V2+ |
|---------|-----|-----|
| Nombre de plugins | 1-2 | jusqu'à 5 |
| Sites de vente | 1-2 | plusieurs par plugin/cible |
| Pricing | Annuel + Lifetime | Multisite, upgrades |
| Espace client | Non | Potentiellement |
| Support intégré | Non (Freescout externe) | À évaluer |

---

## 2. Architecture

### 2.1 Vue d'ensemble

```
                              ┌─────────────────────┐
                              │       STRIPE        │
                              │  (paiements, TVA,   │
                              │   factures)         │
                              └──────────┬──────────┘
                                         │ webhooks
                                         ▼
┌─────────────┐              ┌─────────────────────┐              ┌─────────────┐
│ Site vente  │◄── API ────► │   PLATEFORME        │ ◄── API ───► │ Plugin WP   │
│ WordPress   │              │   CENTRALE          │              │ installé    │
│             │◄── webhook ──│   (Laravel 12)      │              │ chez client │
└─────────────┘              │                     │              └─────────────┘
                             │  - Licences         │
                             │  - Users (SSO)      │
                             │  - Releases         │
                             │  - Webhooks out     │
                             │  - Back-office      │
                             └─────────────────────┘
                                         │
                              ┌──────────┴──────────┐
                              │    Backblaze B2     │
                              │  (stockage zips)    │
                              └─────────────────────┘
```

### 2.2 Flux principaux

#### Inscription utilisateur
1. Client remplit le formulaire sur le site WordPress
2. WordPress envoie les données à l'API plateforme (`POST /api/v1/auth/register`)
3. La plateforme crée le compte et retourne un token
4. WordPress stocke le token en session (pas de mot de passe côté WP)

#### Connexion (SSO)
1. Client clique "Se connecter" sur un site WordPress
2. WordPress affiche le formulaire de login
3. Les credentials sont envoyés à la plateforme (`POST /api/v1/auth/login`)
4. La plateforme valide et retourne un token + infos user
5. WordPress crée la session locale avec le token

#### Achat
1. Client clique "Acheter" sur le site WordPress
2. WordPress appelle l'API plateforme (`POST /api/v1/checkout/create-session`)
3. La plateforme crée une session Stripe Checkout et retourne l'URL
4. Client est redirigé vers Stripe, paie
5. Stripe envoie webhook à la plateforme
6. La plateforme crée la licence et notifie le site WordPress via webhook sortant

#### Vérification de licence (plugin installé)
1. Plugin WP appelle `POST /api/v1/licenses/verify` (1x/jour max)
2. La plateforme vérifie la licence et retourne le statut + info update
3. Plugin stocke le résultat en transient WordPress

#### Mise à jour plugin
1. Plugin WP appelle `GET /api/v1/products/{slug}/check-update`
2. Si update dispo, la plateforme retourne une URL signée temporaire
3. WordPress télécharge et installe la mise à jour

---

## 3. Stack technique

| Composant | Choix | Justification |
|-----------|-------|---------------|
| Framework | Laravel 12 | Écosystème mature, compatible mutualisé |
| PHP | 8.2+ | Requis par Laravel 12 |
| Base de données | MySQL | Disponible chez o2switch |
| Cache | Redis | Disponible chez o2switch |
| Paiement | Stripe + Stripe Tax | Gestion complète paiements/TVA |
| Stockage fichiers | Backblaze B2 | Économique, signed URLs |
| Protection DDoS | Cloudflare | Gratuit, proxy |
| Hébergement initial | o2switch mutualisé | Coûts maîtrisés |
| Hébergement futur | Petit VPS si nécessaire | Scalabilité |

### 3.1 Contraintes mutualisé

- Queues en mode `sync` ou via `schedule:run` (cron toutes les minutes)
- Pas de worker persistant
- Redis disponible (mode Socket recommandé)
- Logs rotatifs pour ne pas saturer l'espace

---

## 4. Modèle de données

### 4.1 Users

Utilisateurs finaux (clients qui achètent).

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| email | string | Unique, login |
| password | string | Hash bcrypt |
| name | string | Nom complet |
| stripe_customer_id | string | Nullable, lien Stripe |
| phone | string | Nullable |
| address_line1 | string | Nullable |
| address_line2 | string | Nullable |
| city | string | Nullable |
| postal_code | string | Nullable |
| country | string | Nullable, code ISO |
| vat_number | string | Nullable, clients pro EU |
| email_verified_at | timestamp | Nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### 4.2 AdminUsers

Utilisateurs back-office (vous, et potentiellement d'autres).

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| email | string | Unique |
| password | string | Hash bcrypt |
| name | string | |
| role | enum | `super_admin`, `admin`, `viewer` (extensible) |
| created_at | timestamp | |
| updated_at | timestamp | |

### 4.3 Products

Les plugins commercialisés.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| slug | string | Unique, identifiant technique |
| name | string | Nom affiché |
| description | text | Nullable |
| stripe_product_id | string | Lien produit Stripe |
| is_active | boolean | Défaut true |
| created_at | timestamp | |
| updated_at | timestamp | |

### 4.4 Prices

Tarifs des produits (pour gérer annuel, lifetime, futur multisite).

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| product_id | bigint | FK → products |
| stripe_price_id | string | Lien price Stripe |
| name | string | Ex: "Licence annuelle", "Lifetime" |
| type | enum | `recurring`, `one_time` |
| amount | int | En centimes |
| currency | string | `EUR` |
| interval | enum | Nullable, `year`, `month` |
| max_activations | int | Nombre de sites (0 = illimité) |
| is_active | boolean | Défaut true |
| created_at | timestamp | |
| updated_at | timestamp | |

### 4.5 Licenses

Table centrale — une licence par achat.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| license_key | string | Unique, UUID |
| user_id | bigint | FK → users |
| product_id | bigint | FK → products |
| price_id | bigint | FK → prices |
| status | enum | `active`, `expired`, `suspended`, `refunded` |
| expires_at | timestamp | Nullable si lifetime |
| max_activations | int | Copié depuis price au moment de l'achat |
| stripe_subscription_id | string | Nullable, si récurrent |
| grace_period_days | int | Défaut 0, extensible |
| created_at | timestamp | |
| updated_at | timestamp | |

**Statuts** :
- `active` : licence valide, updates disponibles
- `expired` : licence expirée, plugin fonctionne mais pas d'updates
- `suspended` : paiement échoué, en attente de régularisation
- `refunded` : remboursé, traité comme expiré

### 4.6 Activations

Sites où une licence est activée.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| license_id | bigint | FK → licenses |
| domain | string | Normalisé (sans www, sans protocole) |
| activated_at | timestamp | |
| last_check_at | timestamp | Nullable, dernier ping |
| plugin_version | string | Nullable, version installée |

**Contrainte** : unique sur `license_id` + `domain`

### 4.7 Releases

Versions publiées des plugins.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| product_id | bigint | FK → products |
| version | string | Semver (ex: `1.2.3`) |
| changelog | text | Nullable, markdown |
| file_path | string | Chemin Backblaze B2 |
| file_size | int | Nullable, en bytes |
| file_hash | string | Nullable, SHA256 pour intégrité |
| min_php_version | string | Nullable |
| min_wp_version | string | Nullable |
| is_published | boolean | Défaut false |
| published_at | timestamp | Nullable, date de publication (peut être future) |
| created_at | timestamp | |
| updated_at | timestamp | |

### 4.8 Invoices

Copie locale des factures Stripe.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| stripe_invoice_id | string | Unique |
| user_id | bigint | FK → users |
| license_id | bigint | Nullable, FK → licenses |
| number | string | Numéro Stripe |
| amount_total | int | En centimes |
| amount_tax | int | En centimes |
| currency | string | `EUR` |
| status | enum | `paid`, `void`, `uncollectible` |
| stripe_pdf_url | string | URL Stripe du PDF |
| local_pdf_path | string | Nullable, copie locale Backblaze |
| issued_at | timestamp | |
| created_at | timestamp | |

### 4.9 WebhookEndpoints

Configuration des webhooks sortants vers les sites de vente.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| name | string | Ex: "Site vente Plugin A" |
| url | string | URL du endpoint |
| secret | string | Clé secrète pour signature |
| events | json | Liste des événements écoutés |
| product_ids | json | Nullable, filtrer par produits |
| is_active | boolean | Défaut true |
| created_at | timestamp | |
| updated_at | timestamp | |

### 4.10 WebhookLogs

Historique des webhooks envoyés (debug/monitoring).

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| webhook_endpoint_id | bigint | FK |
| event | string | Type d'événement |
| payload | json | Données envoyées |
| response_status | int | Nullable, code HTTP retour |
| response_body | text | Nullable |
| attempts | int | Nombre de tentatives |
| sent_at | timestamp | |
| created_at | timestamp | |

### 4.11 ApiTokens

Tokens pour les sites de vente WordPress.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| name | string | Ex: "Site vente Plugin A" |
| token_hash | string | Hash du token |
| abilities | json | Permissions |
| last_used_at | timestamp | Nullable |
| expires_at | timestamp | Nullable |
| created_at | timestamp | |

---

## 5. API Endpoints

### 5.1 Authentification (SSO)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/api/v1/auth/register` | Créer un compte utilisateur | API Token |
| POST | `/api/v1/auth/login` | Connecter un utilisateur | API Token |
| POST | `/api/v1/auth/logout` | Déconnecter | User Token |
| POST | `/api/v1/auth/password/forgot` | Demander reset password | API Token |
| POST | `/api/v1/auth/password/reset` | Reset password | API Token |
| GET | `/api/v1/auth/user` | Infos utilisateur connecté | User Token |
| PUT | `/api/v1/auth/user` | Modifier profil | User Token |

**Register - Payload :**
```json
{
  "email": "client@example.com",
  "password": "securepassword",
  "name": "Jean Dupont"
}
```

**Register - Réponse :**
```json
{
  "user": {
    "id": 123,
    "email": "client@example.com",
    "name": "Jean Dupont"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

### 5.2 Checkout

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/api/v1/checkout/create-session` | Créer session Stripe Checkout | User Token |
| GET | `/api/v1/checkout/success` | Page succès (redirect Stripe) | - |
| GET | `/api/v1/checkout/cancel` | Page annulation | - |

**Create Session - Payload :**
```json
{
  "price_id": 1,
  "success_url": "https://site-vente.com/merci",
  "cancel_url": "https://site-vente.com/panier"
}
```

**Create Session - Réponse :**
```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_xxx",
  "session_id": "cs_xxx"
}
```

### 5.3 Licences (pour sites de vente)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/users/{id}/licenses` | Licences d'un utilisateur | User Token |
| GET | `/api/v1/licenses/{key}` | Détail d'une licence | User Token |

### 5.4 Vérification de licence (pour plugins installés)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/api/v1/licenses/verify` | Vérifier validité licence | - |
| POST | `/api/v1/licenses/activate` | Activer sur un domaine | - |
| POST | `/api/v1/licenses/deactivate` | Désactiver un domaine | - |

**Verify - Payload :**
```json
{
  "license_key": "550e8400-e29b-41d4-a716-446655440000",
  "domain": "client-site.com",
  "product_slug": "mon-plugin"
}
```

**Verify - Réponse succès :**
```json
{
  "valid": true,
  "license": {
    "status": "active",
    "expires_at": "2027-01-15T00:00:00Z",
    "activations_used": 1,
    "activations_max": 3
  },
  "update_available": true,
  "latest_version": "1.3.0"
}
```

**Verify - Réponse échec :**
```json
{
  "valid": false,
  "error_code": "license_expired",
  "message": "Votre licence a expiré le 15/01/2026"
}
```

**Codes d'erreur possibles :**
- `invalid_license` : clé inexistante
- `license_expired` : expirée
- `license_suspended` : suspendue (paiement échoué)
- `license_refunded` : remboursée
- `max_activations_reached` : quota atteint
- `product_mismatch` : licence pour un autre produit

### 5.5 Updates (pour plugins installés)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/products/{slug}/check-update` | Vérifier si update dispo | - |
| GET | `/api/v1/products/{slug}/download` | Télécharger (signed URL) | - |

**Check Update - Paramètres :**
- `license_key` : clé de licence
- `domain` : domaine du site
- `current_version` : version installée

**Check Update - Réponse :**
```json
{
  "update_available": true,
  "version": "1.3.0",
  "changelog": "## 1.3.0\n- Nouvelle fonctionnalité X\n- Correction bug Y",
  "download_url": "https://f002.backblazeb2.com/file/...",
  "download_url_expires_at": "2026-01-16T12:30:00Z",
  "requires_php": "8.0",
  "requires_wp": "6.0"
}
```

**Download - Paramètres :**
- `license_key` : clé de licence
- `domain` : domaine du site
- `signature` : signature de la requête
- `expires` : timestamp expiration

---

## 6. Webhooks

### 6.1 Webhooks entrants (depuis Stripe)

| Événement Stripe | Action plateforme |
|------------------|-------------------|
| `customer.created` | Lier ou créer User |
| `customer.updated` | Sync infos facturation |
| `checkout.session.completed` | Créer licence + webhook sortant |
| `invoice.paid` | Renouveler licence si récurrent + stocker facture + webhook sortant |
| `invoice.finalized` | Stocker facture + télécharger PDF |
| `invoice.payment_failed` | Passer licence en `suspended` + webhook sortant |
| `customer.subscription.updated` | Sync statut licence |
| `customer.subscription.deleted` | Passer licence en `expired` + webhook sortant |
| `charge.refunded` | Passer licence en `refunded` + webhook sortant |

### 6.2 Webhooks sortants (vers sites de vente)

**Événements émis :**

| Événement | Déclencheur |
|-----------|-------------|
| `license.created` | Nouvelle licence créée |
| `license.renewed` | Licence renouvelée |
| `license.expired` | Licence expirée |
| `license.suspended` | Paiement échoué |
| `license.refunded` | Remboursement |
| `license.activated` | Activation sur un domaine |
| `license.deactivated` | Désactivation d'un domaine |
| `release.published` | Nouvelle version publiée |
| `user.created` | Nouveau compte utilisateur |
| `user.updated` | Profil modifié |

**Format du webhook :**
```json
{
  "event": "license.created",
  "timestamp": "2026-01-16T10:30:00Z",
  "data": {
    "license": {
      "key": "550e8400-e29b-41d4-a716-446655440000",
      "status": "active",
      "expires_at": "2027-01-16T00:00:00Z",
      "product_slug": "mon-plugin"
    },
    "user": {
      "id": 123,
      "email": "client@example.com",
      "name": "Jean Dupont"
    }
  }
}
```

**Signature :**
Header `X-Webhook-Signature` contenant un HMAC-SHA256 du body avec la clé secrète.

**Retry policy :**
- 3 tentatives avec délai exponentiel (1min, 5min, 30min)
- Log de chaque tentative pour debug

---

## 7. Back-office

### 7.1 Dashboard

- Statistiques clés : licences actives, revenus mois, nouveaux clients
- Graphique évolution licences/revenus
- Dernières activités (achats, activations, expirations)
- Alertes (paiements échoués, licences expirant bientôt)

### 7.2 Gestion des produits

- Liste des plugins
- Créer/éditer un produit (sync Stripe)
- Gérer les tarifs (prices) pour chaque produit
- Activer/désactiver un produit

### 7.3 Gestion des releases

- Liste des versions par produit
- Upload d'une nouvelle version (zip)
- Saisie : version, changelog, prérequis PHP/WP
- Programmation de la publication (date/heure future)
- Publication immédiate ou différée
- Historique des versions

### 7.4 Gestion des clients

- Liste des utilisateurs avec recherche/filtres
- Fiche client détaillée :
  - Infos personnelles et facturation
  - Historique des licences
  - Historique des factures
  - Activations en cours
- Actions : modifier infos, réinitialiser mot de passe

### 7.5 Gestion des licences

- Liste avec filtres (statut, produit, expiration)
- Détail licence :
  - Infos générales
  - Client associé
  - Activations (domaines)
  - Historique (création, renouvellements, changements statut)
- Actions :
  - Prolonger/réduire durée
  - Changer statut manuellement
  - Ajouter/supprimer activations
  - Révoquer licence

### 7.6 Factures

- Liste des factures avec filtres
- Lien vers PDF Stripe
- Export (CSV, période)

### 7.7 Webhooks

- Configuration des endpoints sortants
- Choix des événements par endpoint
- Filtrage par produit
- Logs des webhooks envoyés (succès/échecs)
- Possibilité de renvoyer un webhook

### 7.8 Paramètres

- Gestion des API tokens pour les sites de vente
- Gestion des admins (quand multi-utilisateur)
- Configuration générale

---

## 8. Comportement des plugins installés

### 8.1 Vérification de licence

- Appel API 1x par jour maximum (via WP Cron)
- Stockage résultat en transient WordPress (24h)
- Pas de vérification à chaque page load

### 8.2 Comportement selon statut

| Statut licence | Plugin fonctionne | Updates | Message affiché |
|----------------|-------------------|---------|-----------------|
| `active` | Oui | Oui | - |
| `expired` | Oui | Non | "Renouvelez pour recevoir les mises à jour" |
| `suspended` | Oui | Non | "Problème de paiement, veuillez régulariser" |
| `refunded` | Oui | Non | "Licence révoquée" |
| Non activé | Oui | Non | "Activez votre licence" |

### 8.3 Gestion des activations

- Le client peut désactiver une licence d'un domaine :
  - Via une action dans le plugin lui-même
  - Via le site de vente (appel API)
- Après désactivation, il peut activer sur un autre domaine

### 8.4 Hook WordPress pour updates

Le plugin utilise le filtre `site_transient_update_plugins` pour injecter les infos de mise à jour. WordPress affiche alors la notification standard et permet l'update en un clic.

---

## 9. Sécurité

### 9.1 Protection API

| Mesure | Détail |
|--------|--------|
| HTTPS | Obligatoire partout |
| Rate limiting | 60 req/min par IP pour endpoints publics |
| Signature webhooks | HMAC-SHA256 pour Stripe et webhooks sortants |
| Timing attack prevention | Réponse identique pour licence valide/invalide |
| Signed URLs | Expiration 10min pour téléchargements |

### 9.2 Authentification

| Type | Mécanisme |
|------|-----------|
| Admins back-office | Session Laravel classique + 2FA recommandé |
| Sites de vente | API Token (Bearer) |
| Utilisateurs finaux | JWT avec refresh token |
| Plugins installés | Licence key + domaine (pas d'auth user) |

### 9.3 Protection DDoS

- Cloudflare en proxy (gratuit)
- Rate limiting applicatif Laravel
- Cache agressif sur endpoint de vérification

---

## 10. Notifications

### 10.1 Emails sortants (V1)

| Destinataire | Événement | Action |
|--------------|-----------|--------|
| Admin (vous) | Nouvel achat | Email récapitulatif |

Les autres notifications (client) sont gérées par les sites de vente via les webhooks sortants.

### 10.2 Emails via Stripe (automatiques)

- Factures envoyées par Stripe directement aux clients
- Rappels de paiement gérés par Stripe

---

## 11. Hébergement et déploiement

### 11.1 Phase initiale (o2switch mutualisé)

**Configuration :**
- PHP 8.2+
- MySQL 8.0
- Redis (mode Socket)
- Queues en mode sync
- Cron `* * * * * php artisan schedule:run`

**Limites à surveiller :**
- Espace disque (logs, factures PDF)
- Temps d'exécution scripts (30s max généralement)
- Connexions MySQL simultanées

### 11.2 Évolution (VPS)

Quand passer sur VPS :
- Charge trop importante pour mutualisé
- Besoin de queues asynchrones (workers persistants)

VPS recommandé : Hetzner CAX11 (~4€/mois) ou OVH Starter

### 11.3 Domaine

Sous-domaine de `wabeo.work` (ex: `api.wabeo.work`, `hub.wabeo.work`, etc.)

---

## 12. Évolutions futures (V2+)

| Fonctionnalité | Description | Priorité |
|----------------|-------------|----------|
| Licences multisite | Tarifs avec plus d'activations | Haute |
| Upgrades de licence | Passer d'une formule à une autre | Haute |
| Espace client | Interface pour voir ses licences | Moyenne |
| Support intégré | Tickets liés aux licences | Basse |
| Affiliation | Tracking des ventes par affilié | Basse |
| Multi-devises | USD, GBP en plus de EUR | Basse |
| API publique documentée | Pour intégrations tierces | Basse |

---

## 13. Livrables attendus

### 13.1 Application Laravel

- Modèles et migrations
- API REST complète
- Webhooks Stripe
- Webhooks sortants
- Back-office (Blade ou Livewire)

### 13.2 Documentation

- Documentation API (pour intégration WordPress)
- Guide d'intégration plugin WordPress

### 13.3 Code WordPress réutilisable

- Classe PHP pour vérification de licence
- Classe PHP pour gestion des updates
- Exemple d'intégration

---

## 14. Résumé des décisions techniques

| Sujet | Décision |
|-------|----------|
| Framework | Laravel 12 |
| Base de données | MySQL |
| Cache | Redis |
| Stockage fichiers | Backblaze B2 |
| Paiement | Stripe |
| TVA | Stripe Tax |
| Devise | EUR uniquement |
| Hébergement initial | o2switch mutualisé |
| SSO | Custom JWT (pas OAuth2 complet) |
| Back-office | Laravel natif (Blade/Livewire) |

---

*Document validé le : ____________________*

*Signature : ____________________*
