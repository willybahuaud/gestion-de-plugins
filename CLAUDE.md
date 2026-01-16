# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Plugin Hub - Plateforme centrale de gestion de plugins WordPress commerciaux. Sert de couche d'abstraction entre Stripe et les sites de vente WordPress pour gérer :
- Licences et leur cycle de vie
- Authentification SSO des utilisateurs
- Distribution des versions de plugins
- Webhooks sortants vers les sites de vente

## Technical Stack

- **Framework**: Laravel 12.47 (PHP 8.2+)
- **Database**: MySQL
- **Cache/Session**: Redis (via socket sur o2switch)
- **File Storage**: Backblaze B2 (signed URLs)
- **Payments**: Stripe + Stripe Tax
- **Hosting**: o2switch mutualisé

## Common Commands

```bash
# Run on o2switch via SSH
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Reset DB with seeds
php artisan make:model Name -mfc       # Create model with migration, factory, controller
php artisan route:list                 # List all routes
php artisan config:cache               # Cache config (production)
php artisan route:cache                # Cache routes (production)
```

## Architecture

```
[Stripe] ←webhooks→ [Plugin Hub API] ←→ [Sites vente WP]
                           ↓
                    [Backblaze B2]     [Plugins installés]
```

La plateforme est invisible pour le client final. Les sites WordPress consomment l'API pour :
- Authentification (SSO custom avec JWT)
- Création de sessions Stripe Checkout
- Récupération des licences utilisateur

Les plugins installés appellent l'API pour :
- Vérification de licence (1x/jour)
- Check et téléchargement des mises à jour

## Key Files

- `briefs/brief-complet.md` - Spécifications complètes du projet
- `public/deploy.php` - Webhook de déploiement automatique
- `routes/api.php` - Routes API
- `routes/web.php` - Routes web (back-office)

## Deployment

Déploiement automatique via webhook GitHub :
1. Push sur `main`
2. GitHub envoie webhook à `https://hub.wabeo.work/deploy.php`
3. Le script exécute `git pull` + `composer install` + `artisan` commands

## Development Constraints

- Compatible hébergement mutualisé o2switch
- Queues en mode `sync` (pas de workers persistants)
- EUR uniquement comme devise
- TVA gérée par Stripe Tax
