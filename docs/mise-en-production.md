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

> ⚠️ En production on utilise **toujours** deux fichiers compose ensemble :
> `compose.yaml` (base) + `compose.prod.yaml` (surcharges prod)

```bash
cd /var/www/my-commerce

# Charger les variables dans le shell (obligatoire avant chaque commande docker compose)
export $(grep -v '^#' .env.prod.local | xargs)

# Construire l'image et démarrer tous les services
docker compose -f compose.yaml -f compose.prod.yaml up -d --build

# Vérifier que tout tourne
docker compose -f compose.yaml -f compose.prod.yaml ps
```

**Explication des options :**

| Option | Signification |
|---|---|
| `-f compose.yaml -f compose.prod.yaml` | Charge les deux fichiers, le second écrase le premier |
| `up` | Crée et démarre les conteneurs |
| `-d` | En arrière-plan (detached), le terminal reste libre |
| `--build` | Reconstruit l'image Docker avant de démarrer |
| `--force-recreate` | Recrée les conteneurs même s'ils n'ont pas changé (utile pour recharger les variables d'env) |

---

## Étape 5 — Initialiser l'application

> Les migrations sont exécutées **automatiquement** au démarrage du conteneur via `docker-entrypoint.sh`.
> Si tu as besoin de les relancer manuellement :

```bash
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console cache:clear
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console assets:install
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

---

## Commandes du quotidien

### Workflow de déploiement standard
```bash
# Sur ta machine locale
git add .
git commit -m "feat: ..."
git push

# Sur le serveur
ssh devzair@<ip> -p 1991
cd /var/www/my-commerce
git pull

# Si tu as modifié du PHP, JS, CSS ou le Dockerfile → rebuild obligatoire
export $(grep -v '^#' .env.prod.local | xargs)
docker compose -f compose.yaml -f compose.prod.yaml up -d --build

# Si tu as modifié uniquement des templates Twig ou des fichiers de config → pas de rebuild
docker compose -f compose.yaml -f compose.prod.yaml up -d
```

### Voir les logs
```bash
# Logs en temps réel
docker compose -f compose.yaml -f compose.prod.yaml logs -f php

# 50 dernières lignes
docker compose -f compose.yaml -f compose.prod.yaml logs php --tail=50

# Filtrer uniquement les erreurs
docker compose -f compose.yaml -f compose.prod.yaml logs php --tail=100 | grep -i "error\|exception\|critical"
```

### Exécuter une commande Symfony
```bash
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console <commande>

# Exemples
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console cache:clear
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console app:create-admin
```

### Modifier une variable d'environnement
```bash
nano .env.prod.local
# ... modifie la valeur ...

# Recharger sans rebuild
export $(grep -v '^#' .env.prod.local | xargs)
docker compose -f compose.yaml -f compose.prod.yaml up -d --force-recreate php messenger-worker
```

### Vérifier ce qu'un conteneur voit réellement
```bash
docker compose -f compose.yaml -f compose.prod.yaml exec php printenv | grep MAILER
docker compose -f compose.yaml -f compose.prod.yaml exec php printenv | grep DATABASE
```

---

## Images et fichiers uploadés

Les images et vidéos uploadées via l'admin sont dans des **volumes Docker persistants** :
- `my-commerce_uploads_data` → `/app/public/assets/images`
- `my-commerce_videos_data` → `/app/public/assets/videos`

Ces volumes **survivent aux rebuilds** — les images ne sont pas perdues lors d'un `--build`.

```bash
# Espace utilisé par Docker
docker system df

# Voir le contenu du volume images
docker run --rm -v my-commerce_uploads_data:/data alpine ls -la /data/
```

---

## Erreurs courantes

### Conteneur en `Restarting` en boucle
```bash
docker compose -f compose.yaml -f compose.prod.yaml logs php --tail=30
```
Cherche la ligne `[critical]` — c'est là qu'est l'erreur réelle.

### `The mailer DSN must contain a scheme`
Le `MAILER_DSN` est vide ou mal formé dans `.env.prod.local`.
Le `@` de l'email doit être encodé en `%40` dans l'URL :
```
MAILER_DSN=smtp://contact%40nidemiel.com:MOTDEPASSE@ssl0.ovh.net:465?encryption=ssl
```

### `could not find driver` / base de données inaccessible
- Vérifie que `DATABASE_URL` utilise `database` comme hôte (pas `localhost`)
- Vérifie que `POSTGRES_USER` / `POSTGRES_PASSWORD` sont bien renseignés

### Variables d'env vides dans le conteneur (`MAILER_DSN=`)
Le `export` n'a pas été fait avant `docker compose up`.
```bash
export $(grep -v '^#' .env.prod.local | xargs)
docker compose -f compose.yaml -f compose.prod.yaml up -d --force-recreate php
```

### `Invalid upload directory does not exist`
Les dossiers d'upload n'existent pas → rebuild obligatoire :
```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

### Assets EasyAdmin manquants (404 sur `/bundles/easyadmin/...`)
```bash
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console assets:install
```

### `MAILER_DSN=MAILER_DSN=smtp://...` (valeur dupliquée)
Erreur de saisie dans `.env.prod.local` — la ligne contient le nom de la variable deux fois.
Ouvrir le fichier et corriger la ligne.

---

## Sécurité serveur

| Protection | Outil | Config |
|---|---|---|
| Pare-feu | UFW | Ports 1991 (SSH), 80, 443 ouverts uniquement |
| Anti brute-force | Fail2ban | 5 tentatives max, ban 1h |
| HTTPS | Let's Encrypt via Caddy | Automatique, renouvellement auto |
| Auth SSH | Clé publique uniquement | Mot de passe désactivé |
| Root login | Désactivé | `PermitRootLogin no` |

```bash
# État du pare-feu
sudo ufw status

# IPs bannies par Fail2ban
sudo fail2ban-client status sshd
```

---

## Résumé des rôles utilisateur

| Rôle | Accès |
|------|-------|
| `ROLE_USER` | Espace client (commandes, adresses, compte) |
| `ROLE_ADMIN` | Tout l'espace admin sauf utilisateurs et paramètres |
| `ROLE_SUPER_ADMIN` | Accès complet (utilisateurs, paramètres, tout) |

Créer les comptes admin avec : `docker compose exec php bin/console app:create-user`
