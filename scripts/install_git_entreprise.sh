#!/bin/bash
# =============================================================================
# Installation Git + liaison dépôt sur serveur entreprise (foutasvr)
#
# Usage :
#   cd /var/www/fouta
#   bash scripts/install_git_entreprise.sh
#
# Variables optionnelles :
#   GIT_USER_NAME='Fouta Local' GIT_USER_EMAIL='fouta@entreprise.local' \
#   GIT_REPO='https://github.com/nick-dev12/fouta_.git' \
#   GIT_BRANCH='main' bash scripts/install_git_entreprise.sh
# =============================================================================

set -euo pipefail

WEB_ROOT="${FOUTA_WEB_ROOT:-/var/www/fouta}"
GIT_REPO="${GIT_REPO:-https://github.com/nick-dev12/fouta_.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
GIT_USER_NAME="${GIT_USER_NAME:-Fouta Serveur Local}"
GIT_USER_EMAIL="${GIT_USER_EMAIL:-fouta@foutapoidslourds.com}"
BACKUP_DIR="${BACKUP_DIR:-/home/fouta/fouta-config-backup}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[git-install]${NC} $*"; }
warn() { echo -e "${YELLOW}[git-install]${NC} $*"; }
err()  { echo -e "${RED}[git-install]${NC} $*" >&2; }

if [[ ! -d "$WEB_ROOT" ]]; then
    err "Dossier introuvable : $WEB_ROOT"
    exit 1
fi

log "=== 1/6 — Installation Git ==="
sudo apt-get update -qq
sudo apt-get install -y -qq git

log "=== 2/6 — Identité Git ==="
git config --global user.name "$GIT_USER_NAME"
git config --global user.email "$GIT_USER_EMAIL"
git config --global pull.rebase false
log "user.name  = $(git config --global user.name)"
log "user.email = $(git config --global user.email)"

log "=== 3/6 — Sauvegarde configs locales ==="
mkdir -p "$BACKUP_DIR"
PROTECTED=(
    conn/conn.php
    config/site.php
    config/sync.php
    config/pull_prod_entreprise.php
    config/update_entreprise.php
)
for f in "${PROTECTED[@]}"; do
    if [[ -f "$WEB_ROOT/$f" ]]; then
        cp -a "$WEB_ROOT/$f" "$BACKUP_DIR/$(echo "$f" | tr '/' '_')"
        log "Sauvegardé : $f"
    fi
done

log "=== 4/6 — Liaison dépôt Git ==="
cd "$WEB_ROOT"

if [[ -d .git ]]; then
    warn "Dépôt Git déjà présent — git remote -v :"
    git remote -v || true
    git fetch origin 2>/dev/null || warn "git fetch échoué — vérifiez l'accès réseau"
else
    log "Initialisation Git dans $WEB_ROOT"
    git init
    git remote add origin "$GIT_REPO" 2>/dev/null || git remote set-url origin "$GIT_REPO"
    git fetch origin "$GIT_BRANCH"
    git checkout -B "$GIT_BRANCH" "origin/$GIT_BRANCH" 2>/dev/null || {
        warn "checkout direct impossible — tentative merge"
        git checkout -B "$GIT_BRANCH"
        git pull origin "$GIT_BRANCH" --allow-unrelated-histories || true
    }
fi

log "=== 5/6 — Restauration configs + skip-worktree ==="
for f in "${PROTECTED[@]}"; do
    backup_file="$BACKUP_DIR/$(echo "$f" | tr '/' '_')"
    if [[ -f "$backup_file" ]]; then
        cp -a "$backup_file" "$WEB_ROOT/$f"
        log "Restauré : $f"
    fi
    if [[ -f "$WEB_ROOT/$f" ]]; then
        git update-index --skip-worktree "$f" 2>/dev/null || true
        log "Protégé (skip-worktree) : $f"
    fi
done

log "=== 6/6 — Config mise à jour ==="
if [[ ! -f config/update_entreprise.php ]]; then
    cp config/update_entreprise.example.php config/update_entreprise.php
    log "Créé config/update_entreprise.php — éditez si besoin"
    git update-index --skip-worktree config/update_entreprise.php 2>/dev/null || true
fi

log "=== Installation Git terminée ==="
echo ""
echo "  Prochaine mise à jour :"
echo "    cd $WEB_ROOT"
echo "    php scripts/update_entreprise_server.php"
echo ""
echo "  Branche : $GIT_BRANCH"
echo "  Remote  : $GIT_REPO"
git status -sb 2>/dev/null || true
