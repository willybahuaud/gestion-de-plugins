# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Plateforme centrale de gestion de plugins WordPress. Sert de couche d'abstraction entre Stripe et les sites de vente WordPress pour gérer :
- Licences et leur cycle de vie
- Authentification SSO des utilisateurs
- Distribution des versions de plugins
- Webhooks sortants vers les sites de vente

## Technical Stack (Target)

- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: MySQL
- **File Storage**: Backblaze B2 (signed URLs)
- **Payments**: Stripe + Stripe Tax
- **Cache**: File-based initially (Redis later)
- **Hosting**: o2switch mutualisé (shared hosting constraints)

## Architecture

```
[Stripe] ←webhooks→ [Plateforme Laravel] ←API→ [Sites vente WP]
                           ↓                        ↓
                    [Backblaze B2]           [Plugins installés]
```

La plateforme est invisible pour le client final. Les sites WordPress consomment l'API pour :
- Authentification (SSO custom avec JWT)
- Création de sessions Stripe Checkout
- Récupération des licences utilisateur

Les plugins installés appellent l'API pour :
- Vérification de licence (1x/jour)
- Check et téléchargement des mises à jour

## Key Documentation

- `briefs/brief-complet.md` - Spécifications complètes du projet
- `briefs/brief-initial-plateforme-plugins.md` - Brief initial

## Development Constraints

- Compatible hébergement mutualisé : pas de workers persistants, queues en mode sync
- EUR uniquement comme devise
- TVA gérée par Stripe Tax
