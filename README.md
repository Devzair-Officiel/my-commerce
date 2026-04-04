# 📦 Projet Symfony – E-commerce

Ce projet est une application **e-commerce B2C** développée avec **Symfony** et une architecture moderne, orientée performance, maintenabilité et sécurité.

---

## 🧱 Stack technique

- **Backend** : Symfony (version stable)
- **ORM** : Doctrine
- **Base de données** : PostgreSQL
- **Templates** : Twig
- **Admin** : EasyAdmin
- **Infrastructure** : Docker (environnement de développement)

---

## 🗂️ Organisation générale

- `src/Controller` : Controllers HTTP (navigation + rendu).
- `src/Entity` : Entités Doctrine (Product, Category, Page, Setting…).
- `src/Repository` : Accès aux données et requêtes spécialisées.
- `src/Storefront` : Logique storefront (layout globals, cache, perf).
- `src/Twig` : Extensions Twig (variables globales, helpers).
- `templates/` : Templates Twig (layout, pages, partials).

---

Lancer des commande :
  composer test      # lance les 24 tests
  composer analyse   # PHPStan niveau 5


Taille idéale à uploader : 1200 * 1200 px minimum, format carré ou proche du carré
---

## 🧭 Données globales du layout (header / footer)

Les données communes au site public (header, footer, menus, paramètres généraux) sont fournies via des **variables globales Twig**, sans dépendre des controllers.

### Principe
- Centralisation dans `StorefrontGlobalsProvider`
- Exposition automatique à Twig via `StorefrontGlobalsExtension`
- Cache taggable (invalidation auto via Doctrine listeners)
- **Aucune donnée publique en session**
- **Aucune entité Doctrine en cache** (tableaux scalaires uniquement)

### Variables Twig disponibles (partout)
- `globalSetting`
- `globalHeaderPages`
- `globalFooterPages`
- `globalMegaCategories`

Exemple :
```twig
{% if globalSetting %}
  {{ globalSetting.website_name }}
{% endif %}
```

En local, Stripe ne peut pas appeler directement notre serveur (localhost, Docker, réseau privé).
Donc stripe listen doit tourner en permanence pendant les tests de paiement.
```bash
stripe listen --forward-to https://localhost/webhooks/stripe --skip-verify
```

---

## 🐳 Commandes utiles (Docker)

> Les commandes ci-dessous supposent une stack `docker compose` et un service PHP nommé `php`.
> Si ton service s’appelle autrement (ex: `app`, `web`, `fpm`), remplace `php` par le bon nom :
> ```bash
> docker compose ps
> ```

### Démarrer / arrêter

```bash
docker compose up -d
docker compose down
```

### Rebuild (après changement Dockerfile / extensions / config)

```bash
docker compose build --no-cache
docker compose up -d
```

### Entrer dans le conteneur PHP

```bash
docker compose exec php sh
# ou bash si dispo
docker compose exec php bash
```

### Lancer des commandes Symfony (dans le conteneur)

```bash
docker compose exec php php bin/console about
docker compose exec php php bin/console cache:clear
docker compose exec php php bin/console debug:container
docker compose exec php php bin/console debug:twig
```

### Composer

```bash
docker compose exec php composer install
docker compose exec php composer update
docker compose exec php composer dump-autoload
```

### Doctrine / Base de données

```bash
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:migrations:diff
docker compose exec php php bin/console doctrine:schema:validate
```

> ⚠️ Commandes destructrices (dev uniquement) :
```bash
docker compose exec php php bin/console doctrine:database:drop --force
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:schema:update --force
```

### Fixtures (si utilisées)

```bash
docker compose exec php php bin/console doctrine:fixtures:load
```

### Tests (si PHPUnit)

```bash
docker compose exec php php bin/phpunit
# ou
docker compose exec php ./vendor/bin/phpunit
```

### Logs

```bash
docker compose logs -f
docker compose logs -f php
```

---

## ⚡ Performance & cache

- Cache applicatif Symfony avec **tags**
- Invalidation automatique lors des modifications admin
- Clés de cache **versionnées** (ex: `storefront.globals.v3.scalars`)
- Pas de requêtes globales répétées sur chaque page

---

## 🔐 Principes d’architecture

- Séparation claire des responsabilités
- Logique métier hors des templates
- Controllers légers
- Twig dédié à l’affichage

---
