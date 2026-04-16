# Mise en production — Guide complet

## Prérequis serveur

- VPS Linux (Ubuntu 22.04+ recommandé)
- Docker + Docker Compose installés
- Nom de domaine pointant vers le serveur
- Accès SSH

---

## Étape 1 — Préparer le serveur

```bash
# Installer Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker

# Vérifier
docker --version
docker compose version
```

---

## Étape 2 — Déployer le code

```bash
# Cloner le projet
git clone https://github.com/ton-user/my-commerce.git /var/www/my-commerce
cd /var/www/my-commerce
```

---

## Étape 3 — Configurer les variables d'environnement 🔴

Créer le fichier `.env.prod.local` (jamais commité) :

```bash
nano /var/www/my-commerce/.env.prod.local
```

```env
APP_ENV=prod
APP_SECRET=                        # php -r "echo bin2hex(random_bytes(16));"

# Base de données
DATABASE_URL=postgresql://USER:PASSWORD@database:5432/DB_NAME
POSTGRES_DB=my-commerce
POSTGRES_USER=my-commerce
POSTGRES_PASSWORD=                 # Mot de passe fort généré

# Stripe (clés LIVE — pas test)
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...   # Recréer le webhook sur dashboard.stripe.com

# Email OVH
MAILER_DSN=smtp://contact@nidemiel.com:MOT_DE_PASSE@ssl0.ovh.net:465?encryption=ssl
MAILER_SENDER_EMAIL=contact@nidemiel.com
MAILER_SENDER_NAME=Nidemiel

# Colissimo
COLISSIMO_LOGIN=
COLISSIMO_PASSWORD=

# Sentry
SENTRY_DSN=https://...@....ingest.de.sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.1

# Backup OVH Object Storage
RCLONE_S3_ACCESS_KEY=
RCLONE_S3_SECRET_KEY=
RCLONE_S3_ENDPOINT=s3.eu-west-par.io.cloud.ovh.net
RCLONE_S3_REGION=eu-west-par
RCLONE_S3_BUCKET=nidemiel-backups
```

> ⚠️ Sauvegarder ce fichier dans un gestionnaire de mots de passe (Bitwarden, 1Password).

---

## Étape 4 — Construire et démarrer les containers

```bash
cd /var/www/my-commerce

# Construire l'image de prod
docker compose build

# Démarrer tous les services
docker compose up -d

# Vérifier que tout tourne
docker compose ps
```

---

## Étape 5 — Initialiser l'application

```bash
# Exécuter les migrations
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Vider le cache prod
docker compose exec php bin/console cache:clear --env=prod

# Vérifier les assets
docker compose exec php bin/console assets:install --env=prod
```

---

## Étape 6 — Créer le premier compte Super Admin 🔴

```bash
docker compose exec php bin/console app:create-user
```

Renseigner :
- Email
- Mot de passe (8 caractères minimum)
- Rôle → choisir **Super administrateur**

> C'est le seul compte qui peut gérer les utilisateurs et les paramètres du site.

---

## Étape 7 — Configurer le backup

```bash
# Configurer rclone avec les credentials OVH
./scripts/setup-rclone.sh

# Tester le backup
./scripts/backup.sh

# Ajouter le cron quotidien
crontab -e
```

```cron
# Backup quotidien à 3h du matin
0 3 * * * cd /var/www/my-commerce && ./scripts/backup.sh >> /var/log/backup.log 2>&1

# Suivi Colissimo toutes les 15 minutes
*/15 * * * * cd /var/www/my-commerce && docker compose exec -T php bin/console app:sync-shipment-tracking -q >> /var/log/colissimo-sync.log 2>&1
```

---

## Étape 8 — Configurer le webhook Stripe

1. Aller sur [dashboard.stripe.com](https://dashboard.stripe.com) → Développeurs → Webhooks
2. Ajouter un endpoint : `https://nidemiel.com/webhooks/stripe`
3. Événements à écouter :
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
4. Copier le **Signing secret** → mettre dans `STRIPE_WEBHOOK_SECRET` du `.env.prod.local`

---

## Étape 9 — Configurer les DNS (OVH)

| Type | Sous-domaine | Valeur |
|------|-------------|--------|
| A | `@` | IP de ton serveur |
| A | `www` | IP de ton serveur |
| TXT | `@` | `v=spf1 include:mx.ovh.com ~all` |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:contact@nidemiel.com` |

Voir `docs/email-spf-dkim-dmarc.md` pour le détail complet.

---

## Étape 10 — UptimeRobot

1. Créer un compte sur [uptimerobot.com](https://uptimerobot.com)
2. Ajouter 3 moniteurs HTTPS (intervalle 5 min) :
   - `https://nidemiel.com`
   - `https://nidemiel.com/boutique`
   - `https://nidemiel.com/sitemap.xml`

Voir `docs/monitoring.md` pour le détail.

---

## Étape 11 — Pages légales 🔴

Depuis l'admin (`/admin`), créer les 3 pages statiques :

| Slug | Titre |
|------|-------|
| `mentions-legales` | Mentions légales |
| `politique-de-confidentialite` | Politique de confidentialité |
| `cgv` | Conditions générales de vente |

> Ces pages sont obligatoires légalement pour un site e-commerce français.

---

## Étape 12 — Tests avant ouverture

- [ ] Créer un compte client et passer une vraie commande (carte Stripe live)
- [ ] Vérifier la réception de l'email de confirmation
- [ ] Tester la livraison à domicile + point relais
- [ ] Vérifier que les factures PDF se génèrent
- [ ] Tester `php bin/console sentry:test` → vérifier la réception de l'alerte
- [ ] Vérifier `https://nidemiel.com/sitemap.xml`
- [ ] Vérifier `https://nidemiel.com/robots.txt`
- [ ] Tester la délivrabilité email sur [mail-tester.com](https://mail-tester.com)

---

## Résumé des rôles utilisateur

| Rôle | Accès |
|------|-------|
| `ROLE_USER` | Espace client (commandes, adresses, compte) |
| `ROLE_ADMIN` | Tout l'espace admin sauf utilisateurs et paramètres |
| `ROLE_SUPER_ADMIN` | Accès complet (utilisateurs, paramètres, tout) |

Créer les comptes admin avec : `docker compose exec php bin/console app:create-user`
