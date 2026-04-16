# Stratégie de backup — my-commerce

## Ce qui est sauvegardé

| Données | Criticité | Description |
|---------|-----------|-------------|
| Base PostgreSQL | 🔴 Critique | Commandes, clients, produits, tout |
| `public/uploads/` | 🟠 Important | Images produits uploadées |
| `.env.prod.local` | 🔴 Critique | Clés Stripe, SMTP, Colissimo, S3 — à sauvegarder manuellement dans un gestionnaire de mots de passe (Bitwarden, 1Password…) |

## Fréquence et rétention

- **Quotidien** — 3h du matin
- **Rétention locale** — 30 jours sur le serveur
- **Rétention distante** — illimitée sur OVH Object Storage (à purger manuellement si besoin)

## Stockage distant — OVH Object Storage

| Paramètre | Valeur |
|-----------|--------|
| Bucket | `nidemiel-backups` |
| Région | Paris (`eu-west-par`) |
| Endpoint | `s3.eu-west-par.io.cloud.ovh.net` |
| Accès | Privé (jamais public) |
| Credentials | Dans `.env.prod.local` uniquement — ne jamais commiter |

Les credentials S3 ne sont **jamais** dans le code. Ils sont dans `.env.prod.local` (non commité).

---

## Mise en place sur le serveur (à faire une seule fois)

### 1. Ajouter les credentials dans `.env.prod.local`

```env
RCLONE_S3_ACCESS_KEY=ta_cle_acces_ovh
RCLONE_S3_SECRET_KEY=ta_cle_secrete_ovh
RCLONE_S3_ENDPOINT=s3.eu-west-par.io.cloud.ovh.net
RCLONE_S3_REGION=eu-west-par
RCLONE_S3_BUCKET=nidemiel-backups
```

### 2. Configurer rclone

```bash
./scripts/setup-rclone.sh
```

Ce script :
- Installe rclone si absent
- Crée `~/.config/rclone/rclone.conf` avec les credentials du `.env.prod.local`
- Teste la connexion au bucket

### 3. Tester le backup manuellement

```bash
./scripts/backup.sh
```

Vérifie que :
- Un fichier `backups/db_*.sql.gz` est créé
- Un fichier `backups/uploads_*.tar.gz` est créé
- Les fichiers apparaissent dans le bucket OVH

```bash
rclone ls backup:nidemiel-backups
```

### 4. Planifier le cron quotidien

```bash
crontab -e
```

```cron
# Backup quotidien à 3h du matin
0 3 * * * cd /var/www/my-commerce && ./scripts/backup.sh >> /var/log/backup.log 2>&1
```

---

## Restaurer depuis un backup

### Restaurer la base de données

```bash
# Lister les backups disponibles
ls -lh backups/db_*.sql.gz

# Ou depuis OVH directement
rclone ls backup:nidemiel-backups

# Télécharger un backup depuis OVH si nécessaire
rclone copy backup:nidemiel-backups/db_my-commerce_20260415_030000.sql.gz ./restore/

# Restaurer
gunzip -c backups/db_my-commerce_YYYYMMDD_HHMMSS.sql.gz | \
  docker compose exec -T database psql -U "$POSTGRES_USER" "$POSTGRES_DB"
```

### Restaurer les uploads

```bash
tar -xzf backups/uploads_YYYYMMDD_HHMMSS.tar.gz -C public/
```

---

## En cas de piratage — Procédure d'urgence

### 1. Isoler immédiatement

```bash
docker compose down
```

### 2. Préserver les preuves (avant de nettoyer)

```bash
docker compose logs > ~/incident-$(date +%Y%m%d).log
```

### 3. Identifier la brèche

- Logs Caddy : quelle URL a été exploitée ?
- `git diff` : des fichiers ont-ils été modifiés ?
- BDD : des comptes admin inconnus ont-ils été créés ?

### 4. Changer TOUTES les clés

- **Stripe** → Tableau de bord Stripe → Révoquer les clés live → Générer de nouvelles
- **OVH S3** → Espace client → Supprimer l'utilisateur Object Storage → Recréer
- **SMTP OVH** → Changer le mot de passe email
- **`APP_SECRET`** Symfony → Régénérer (`php -r "echo bin2hex(random_bytes(16));"`)
- **Mot de passe PostgreSQL** → Changer dans Docker + `.env.prod.local`

### 5. Restaurer depuis le dernier backup sain

Utiliser la procédure de restauration ci-dessus avec le backup **d'avant l'incident**.

### 6. Remettre en ligne

```bash
docker compose up -d
```

---

## Vérifier les logs de backup

```bash
# Dernières exécutions
tail -50 /var/log/backup.log

# Vérifier le contenu du bucket OVH
rclone ls backup:nidemiel-backups --human-readable
```
