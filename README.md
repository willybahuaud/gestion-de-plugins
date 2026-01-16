# Plugin Hub

Plateforme centrale de gestion de plugins WordPress commerciaux.

## Le concept

Une seule plateforme pour gérer tous vos plugins :

- **Licences** : génération, activation, renouvellement, révocation
- **Utilisateurs** : authentification SSO centralisée pour tous vos sites de vente
- **Versions** : upload, changelog, publication programmée, distribution sécurisée
- **Paiements** : intégration Stripe avec synchronisation automatique
- **Webhooks** : notification des sites de vente en temps réel

## Pourquoi ?

Plutôt que de dupliquer la logique de gestion des licences sur chaque site de vente, tout est centralisé ici. Les sites WordPress ne font que consommer l'API.

```
[Stripe] ←→ [Plugin Hub] ←→ [Sites de vente WP]
                 ↓
          [Plugins installés]
```

## Stack

Laravel 12 • MySQL • Backblaze B2 • Stripe

---

*Projet en cours de développement*
