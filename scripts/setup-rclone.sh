#!/usr/bin/env bash
# =============================================================================
# setup-rclone.sh — Configure rclone avec OVH Object Storage (S3)
# À lancer UNE SEULE FOIS sur le serveur de production
#
# Prérequis : les variables RCLONE_S3_* doivent être définies dans
#             .env.prod.local avant de lancer ce script
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Charger les variables depuis .env puis .env.prod.local
load_env() {
    local file="$1"
    [[ -f "$file" ]] || return 0
    while IFS= read -r line; do
        [[ "$line" =~ ^[[:space:]]*# ]] && continue
        [[ -z "${line// }" ]] && continue
        if [[ "$line" =~ ^RCLONE_S3_ ]]; then
            local key="${line%%=*}"
            local val="${line#*=}"
            val="${val%\"}"; val="${val#\"}"; val="${val%\'}"; val="${val#\'}"
            export "$key=$val"
        fi
    done < "$file"
}

load_env "$PROJECT_DIR/.env"
load_env "$PROJECT_DIR/.env.prod.local"

# Vérifier que les clés sont renseignées
if [[ -z "${RCLONE_S3_ACCESS_KEY:-}" ]] || [[ -z "${RCLONE_S3_SECRET_KEY:-}" ]]; then
    echo "✗ ERREUR : RCLONE_S3_ACCESS_KEY et RCLONE_S3_SECRET_KEY doivent être définis dans .env.prod.local"
    exit 1
fi

# Installer rclone si absent
if ! command -v rclone &>/dev/null; then
    echo "Installation de rclone..."
    curl https://rclone.org/install.sh | sudo bash
fi

# Créer la config rclone pour OVH Object Storage
mkdir -p ~/.config/rclone

cat > ~/.config/rclone/rclone.conf << EOF
[backup]
type = s3
provider = Other
access_key_id = ${RCLONE_S3_ACCESS_KEY}
secret_access_key = ${RCLONE_S3_SECRET_KEY}
endpoint = ${RCLONE_S3_ENDPOINT:-s3.eu-west-par.io.cloud.ovh.net}
region = ${RCLONE_S3_REGION:-eu-west-par}
acl = private
EOF

echo "✓ rclone configuré."
echo ""
echo "Test de connexion..."
rclone ls "backup:${RCLONE_S3_BUCKET:-nidemiel-backups}" && echo "✓ Connexion OK — bucket accessible." || echo "✗ Erreur de connexion, vérifier les credentials."
