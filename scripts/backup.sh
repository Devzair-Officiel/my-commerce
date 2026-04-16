#!/usr/bin/env bash
# =============================================================================
# backup.sh — Sauvegarde PostgreSQL + uploads
# =============================================================================
# Usage :
#   ./scripts/backup.sh
#
# Variables d'environnement requises (depuis .env ou l'environnement système) :
#   POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD
#   BACKUP_DIR   — répertoire local où stocker les dumps  (défaut : ./backups)
#   BACKUP_RETAIN_DAYS — nombre de jours de rétention     (défaut : 30)
#   NOTIFY_EMAIL — adresse email pour les alertes d'échec (optionnel)
# =============================================================================

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Charger uniquement les variables POSTGRES_* et BACKUP_* depuis .env
# (évite les variables en lecture seule comme UID)
load_env() {
    local file="$1"
    [[ -f "$file" ]] || return 0
    while IFS= read -r line; do
        # Ignorer commentaires et lignes vides
        [[ "$line" =~ ^[[:space:]]*# ]] && continue
        [[ -z "${line// }" ]] && continue
        # N'importer que les variables qui nous intéressent
        if [[ "$line" =~ ^(POSTGRES_|BACKUP_|NOTIFY_EMAIL) ]]; then
            local key="${line%%=*}"
            local val="${line#*=}"
            # Retirer les guillemets éventuels
            val="${val%\"}"
            val="${val#\"}"
            val="${val%\'}"
            val="${val#\'}"
            export "$key=$val"
        fi
    done < "$file"
}

load_env "$PROJECT_DIR/.env"
load_env "$PROJECT_DIR/.env.prod.local"

POSTGRES_DB="${POSTGRES_DB:-my-commerce}"
POSTGRES_USER="${POSTGRES_USER:-app}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-}"
POSTGRES_HOST="${POSTGRES_HOST:-localhost}"   # sur le serveur hôte après exposition du port
POSTGRES_PORT="${POSTGRES_PORT:-5432}"

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/backups}"
BACKUP_RETAIN_DAYS="${BACKUP_RETAIN_DAYS:-30}"
NOTIFY_EMAIL="${NOTIFY_EMAIL:-}"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_${POSTGRES_DB}_${TIMESTAMP}.sql.gz"
UPLOADS_FILE="$BACKUP_DIR/uploads_${TIMESTAMP}.tar.gz"

# ── Fonctions ─────────────────────────────────────────────────────────────────
log()  { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
fail() {
    log "ERREUR : $*"
    if [[ -n "$NOTIFY_EMAIL" ]] && command -v mail &>/dev/null; then
        echo "Backup échoué sur $(hostname) : $*" | mail -s "[BACKUP FAILED] my-commerce" "$NOTIFY_EMAIL"
    fi
    exit 1
}

# ── Vérifications ──────────────────────────────────────────────────────────────
mkdir -p "$BACKUP_DIR"
command -v pg_dump &>/dev/null  || fail "pg_dump introuvable — installer postgresql-client"
command -v gzip    &>/dev/null  || fail "gzip introuvable"

# ── 1. Dump PostgreSQL ─────────────────────────────────────────────────────────
log "Démarrage du dump PostgreSQL → $BACKUP_FILE"

# Via Docker Compose (recommandé quand la BDD tourne dans Docker)
if command -v docker &>/dev/null && docker compose -f "$PROJECT_DIR/compose.yaml" ps database 2>/dev/null | grep -q "running\|Up"; then
    PGPASSWORD="$POSTGRES_PASSWORD" \
    docker compose -f "$PROJECT_DIR/compose.yaml" exec -T database \
        pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" \
    | gzip > "$BACKUP_FILE" \
    || fail "pg_dump via Docker a échoué"
else
    # BDD exposée directement (prod sans Docker ou port mappé)
    PGPASSWORD="$POSTGRES_PASSWORD" \
    pg_dump -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" -U "$POSTGRES_USER" "$POSTGRES_DB" \
    | gzip > "$BACKUP_FILE" \
    || fail "pg_dump direct a échoué"
fi

log "Dump terminé : $(du -sh "$BACKUP_FILE" | cut -f1)"

# ── 2. Archives des uploads ────────────────────────────────────────────────────
UPLOADS_DIR="$PROJECT_DIR/public/uploads"
if [[ -d "$UPLOADS_DIR" ]]; then
    log "Archivage des uploads → $UPLOADS_FILE"
    tar -czf "$UPLOADS_FILE" -C "$PROJECT_DIR/public" uploads \
        || fail "Archivage des uploads a échoué"
    log "Uploads archivés : $(du -sh "$UPLOADS_FILE" | cut -f1)"
else
    log "Pas de dossier uploads trouvé, on passe."
fi

# ── 3. Sync vers stockage distant (rclone) ─────────────────────────────────────
# Nécessite rclone configuré avec un remote nommé "backup"
# Voir : https://rclone.org/docs/ — rclone config
if command -v rclone &>/dev/null && rclone listremotes 2>/dev/null | grep -q "^backup:"; then
    log "Sync vers stockage distant (rclone backup:nidemiel-backups/)…"
    rclone copy "$BACKUP_DIR" backup:nidemiel-backups/ \
        --include "*.gz" \
        --transfers 2 \
        --log-level INFO \
        || log "AVERTISSEMENT : rclone sync a échoué — backup local conservé"
    log "Sync distant terminé."
else
    log "rclone non configuré — backup local uniquement."
fi

# ── 4. Rotation — suppression des vieux backups ────────────────────────────────
log "Suppression des backups locaux de plus de ${BACKUP_RETAIN_DAYS} jours…"
find "$BACKUP_DIR" -name "*.gz" -mtime +"$BACKUP_RETAIN_DAYS" -delete
log "Rotation terminée."

# ── 5. Résumé ──────────────────────────────────────────────────────────────────
log "✓ Backup terminé avec succès."
log "  BDD     : $BACKUP_FILE"
[[ -f "$UPLOADS_FILE" ]] && log "  Uploads : $UPLOADS_FILE"
log "  Espace backups : $(du -sh "$BACKUP_DIR" | cut -f1)"
