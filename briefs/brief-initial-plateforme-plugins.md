# Brief Projet — Plateforme de gestion de plugins WordPress

## Contexte et objectifs

### Problématique

Développement de plusieurs plugins WordPress à commercialiser, chacun nécessitant le même socle technique : gestion des licences, paiements, facturation, versioning, accès utilisateurs. Plutôt que de dupliquer cette logique sur chaque site de vente, l'idée est de centraliser.

### Objectif

Construire une **plateforme centrale** qui :

- Sert de source de vérité pour les licences, clients, et versions
- Expose une API consommée par les sites WordPress de vente
- Expose une API consommée par les plugins installés chez les clients (vérification de licence, updates)
- Délègue les paiements à Stripe tout en gardant une copie locale des données critiques
- Fournit un back-office pour le suivi clients, support, facturation
- Offre un espace client (front privé) pour consulter ses licences et télécharger les plugins

---

## Architecture générale

```
[Stripe] ←→ webhooks ←→ [Plateforme centrale + BDD]
                                    ↑
            ┌───────────────┬───────┴───────┬────────────────┐
       [Site vente 1]  [Site vente 2]  [Espace client]  [Plugin installé]
```

### Principes

- **Stripe** gère les paiements, abonnements, et génère les factures
- **La plateforme** reçoit les webhooks Stripe et synchronise les données localement
- **Les sites de vente WordPress** ne font que consommer l'API (pas de logique de licence chez eux)
- **Les plugins installés** chez les clients pingent l'API pour vérifier leur licence et checker les updates
- **Le SSO** est centralisé : l'authentification se fait sur la plateforme, les sites de vente délèguent

---

## Stack technique

| Composant | Choix | Justification |
|-----------|-------|---------------|
| Framework | Laravel 12 | PHP natif, écosystème mature, sécurité intégrée, compatible mutu |
| PHP | 8.2 minimum | Requis par Laravel 12 |
| BDD | MySQL / PostgreSQL | Au choix selon hébergement |
| Cache | Redis (idéal) ou fichier | Pour le cache des vérifications de licence |
| Paiement | Stripe | API complète, gestion des abos, facturation |
| Auth API | Laravel Passport | OAuth2 standard pour le SSO |
| Stockage fichiers | S3 / Backblaze B2 | Signed URLs, décharge le serveur |
| Protection DDoS | Cloudflare | Gratuit, absorbe les attaques volumétriques |

### Note hébergement

Peut tourner sur mutualisé avec quelques adaptations :
- Queues en mode sync ou via cron (`schedule:run`)
- Pas de worker persistant nécessaire au démarrage

Recommandé à terme : petit VPS (Hetzner, OVH) à 5-10€/mois pour plus de souplesse.

---

## Modèle de données

### Users

Les clients qui achètent des licences.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| email | string | Unique, login |
| password | string | Hash bcrypt |
| name | string | Nom complet |
| stripe_customer_id | string | Nullable, lien vers Stripe |
| billing_name | string | Nullable, nom facturation |
| billing_email | string | Nullable, email facturation |
| billing_address | string | Nullable |
| billing_city | string | Nullable |
| billing_postal_code | string | Nullable |
| billing_country | string | Nullable, code ISO |
| vat_number | string | Nullable, pour clients pro EU |
| created_at | timestamp | |
| updated_at | timestamp | |

### Products

Les plugins commercialisés.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| slug | string | Unique, identifiant technique (ex: `deposit-manager`) |
| name | string | Nom affiché |
| description | text | Nullable |
| stripe_product_id | string | Lien vers le produit Stripe |
| repo_url | string | Nullable, URL du repo Git pour automatisation |
| created_at | timestamp | |
| updated_at | timestamp | |

### Licenses

Table centrale — une licence par achat.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| license_key | string | Unique, format UUID ou custom |
| user_id | bigint | FK → users |
| product_id | bigint | FK → products |
| status | enum | `active`, `expired`, `suspended`, `refunded` |
| expires_at | timestamp | Nullable si lifetime |
| max_activations | int | Nombre de sites autorisés (1, 3, unlimited = 0) |
| stripe_subscription_id | string | Nullable, si abo récurrent |
| created_at | timestamp | |
| updated_at | timestamp | |

**Index** : `license_key` (unique), `user_id`, `product_id`, `status`

### Activations

Trace des sites où une licence est activée.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| license_id | bigint | FK → licenses |
| domain | string | Normalisé (sans www, sans protocole) |
| activated_at | timestamp | |
| last_checked_at | timestamp | Nullable, dernier ping |

**Contrainte** : unique sur `license_id` + `domain`

### Releases

Versions publiées des plugins.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| product_id | bigint | FK → products |
| version | string | Semver (ex: `1.2.3`) |
| changelog | text | Nullable, markdown |
| file_path | string | Chemin vers le zip (local ou S3) |
| file_size | int | Nullable, en bytes |
| min_php_version | string | Nullable |
| min_wp_version | string | Nullable |
| released_at | timestamp | |
| created_at | timestamp | |

### Invoices

Copie locale des factures Stripe.

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| stripe_invoice_id | string | Unique |
| user_id | bigint | FK → users |
| license_id | bigint | Nullable, FK → licenses |
| number | string | Numéro de facture Stripe |
| amount | int | En centimes |
| currency | string | Code ISO (EUR, USD) |
| tax | int | Nullable, montant TVA en centimes |
| status | enum | `paid`, `void`, `uncollectible` |
| pdf_url | string | URL Stripe du PDF |
| pdf_path | string | Nullable, copie locale |
| issued_at | timestamp | |
| created_at | timestamp | |

### Transactions

Historique des paiements (optionnel si Invoices suffit).

| Champ | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| stripe_payment_intent_id | string | Unique |
| user_id | bigint | FK → users |
| license_id | bigint | Nullable |
| amount | int | En centimes |
| currency | string | |
| status | enum | `succeeded`, `failed`, `refunded` |
| paid_at | timestamp | Nullable |
| created_at | timestamp | |

---

## API Endpoints

### Authentification (SSO)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/oauth/authorize` | Début du flow OAuth2 |
| POST | `/oauth/token` | Échange code → token |
| GET | `/api/v1/user` | Infos utilisateur connecté |

Implémenté via **Laravel Passport**.

### Vérification de licence

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/v1/licenses/verify` | Vérifie validité d'une licence |

**Payload entrant :**
```json
{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "domain": "example.com",
  "product_slug": "deposit-manager"
}
```

**Réponse succès :**
```json
{
  "valid": true,
  "license": {
    "status": "active",
    "expires_at": "2026-01-15T00:00:00Z",
    "activations_used": 2,
    "activations_max": 3
  },
  "update_available": true,
  "latest_version": "1.3.0"
}
```

**Réponse échec :**
```json
{
  "valid": false,
  "error": "license_expired"
}
```

### Gestion des activations

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/v1/licenses/{key}/activate` | Active sur un domaine |
| DELETE | `/api/v1/licenses/{key}/deactivate` | Désactive un domaine |

### Updates

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/v1/products/{slug}/check-update` | Check si update dispo |
| GET | `/api/v1/products/{slug}/download` | Télécharge (signed URL) |

**Paramètres check-update :**
- `current_version` : version installée
- `license_key` : clé de licence

**Réponse :**
```json
{
  "update_available": true,
  "version": "1.3.0",
  "changelog": "- Fix bug XYZ\n- New feature ABC",
  "download_url": "https://...",
  "download_url_expires": "2025-01-16T12:30:00Z"
}
```

Le `download_url` est une **URL signée temporaire** (10 min) incluant licence + domaine + timestamp.

---

## Webhooks Stripe à écouter

| Événement | Action |
|-----------|--------|
| `customer.created` | Créer/lier User |
| `customer.updated` | Sync infos billing |
| `checkout.session.completed` | Créer licence |
| `invoice.paid` | Créer/renouveler licence + stocker Invoice |
| `invoice.finalized` | Stocker Invoice |
| `invoice.payment_failed` | Notifier, marquer licence |
| `customer.subscription.updated` | Sync status licence |
| `customer.subscription.deleted` | Expirer licence |
| `charge.refunded` | Marquer licence `refunded` |

**Sécurité** : toujours vérifier la signature du webhook (`Stripe-Signature` header).

---

## Performance et sécurité

### Tenir la charge sur les vérifications de licence

**Côté API :**
- Endpoint ultra léger : une requête indexée, pas de jointures
- Cache Redis sur le résultat (5-15 min par clé+domaine)
- Rate limiting : 60 req/min par licence (natif Laravel)

**Côté plugin WordPress :**
- Vérification **1x par jour max** via WP Cron
- Stockage du résultat en transient
- Pas de check à chaque page load

### Protection DDoS

| Couche | Solution |
|--------|----------|
| Réseau | Cloudflare en proxy (gratuit) |
| Applicatif | Rate limiting Laravel |
| Filtrage | Header custom `X-Plugin-Signature` |

### Sécurité API

- Pas d'énumération possible (même réponse timing pour clé valide/invalide)
- Signed URLs pour les téléchargements (expiration courte)
- HTTPS obligatoire
- Tokens OAuth2 avec expiration

---

## Système SSO

### Flow

1. Client clique "Se connecter" sur un site de vente
2. Redirection vers `account.tondomaine.com/oauth/authorize`
3. Authentification sur la plateforme
4. Redirection retour avec code d'autorisation
5. Le site de vente échange le code contre un token
6. Le site de vente récupère les infos user via `/api/v1/user`

### Implémentation

**Laravel Passport** avec :
- Authorization Code Grant (pour les sites web)
- Refresh tokens pour maintenir la session

Les sites WordPress de vente utilisent un plugin OAuth2 client ou une intégration custom.

---

## Dispatch des nouvelles versions

### Workflow de publication

1. Développement terminé, tag Git créé
2. Build du zip (manuel ou CI/CD)
3. Upload sur S3/Backblaze
4. Création de l'entrée `Release` via back-office avec :
   - Version
   - Changelog
   - Chemin du fichier
   - Prérequis (PHP, WP)

### Côté plugin installé

Le plugin hook sur le système d'updates WordPress :
- `pre_set_site_transient_update_plugins`
- Ou filtre `site_transient_update_plugins`

Il appelle `/api/v1/products/{slug}/check-update` en passant sa version et sa licence.

Si update dispo, WordPress affiche la notification et permet l'update en un clic.

### Sécurité du téléchargement

- URL signée avec expiration (10 min)
- Signature inclut : licence, domaine, timestamp
- Stockage sur S3/Backblaze avec signed URLs natives
- Pas de téléchargement sans licence valide

---

## Bonus envisageables (V2+)

| Fonctionnalité | Description |
|----------------|-------------|
| Support tickets | Intégré à la plateforme, lié aux licences |
| Download logs | Traçabilité des téléchargements |
| Coupons custom | Logique promo au-delà de Stripe |
| Multi-pricing | Géré via Stripe Prices (mensuel, annuel, lifetime) |
| Affiliation | Tracking des ventes par affilié |
| API publique | Pour intégrations tierces |

---

## Prochaines étapes

1. **Initialiser le projet Laravel 12**
2. **Configurer Passport** pour le SSO
3. **Créer les migrations** selon le schéma
4. **Implémenter les webhooks Stripe** (customer, invoice, subscription)
5. **Construire l'endpoint de vérification de licence** avec cache
6. **Construire l'endpoint de check-update** avec signed URLs
7. **Développer le back-office** (gestion produits, licences, clients)
8. **Développer l'espace client** (mes licences, téléchargements, factures)
9. **Créer le SDK/boilerplate** pour les plugins WordPress (vérification + updates)
10. **Déployer** (VPS ou mutu avec Cloudflare)

---

*Document généré le 16 janvier 2026*
