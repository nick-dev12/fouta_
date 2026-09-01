#!/bin/bash
# =============================================================================
# Vérification des prérequis — serveur Ubuntu local (Fouta)
# Usage :
#   chmod +x scripts/check_local_prerequisites.sh
#   ./scripts/check_local_prerequisites.sh
#
# Ne modifie rien : diagnostic uniquement.
# =============================================================================

set -uo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

ok()   { echo -e "  ${GREEN}[OK]${NC}    $*"; }
miss() { echo -e "  ${RED}[MANQUE]${NC} $*"; MISSING=$((MISSING + 1)); }
warn() { echo -e "  ${YELLOW}[WARN]${NC}  $*"; }

MISSING=0
PHP_EXT_MISSING=0

echo ""
echo -e "${CYAN}=== Fouta — diagnostic prérequis serveur local Ubuntu ===${NC}"
echo ""

# --- OS ---
echo "Système"
if [[ -f /etc/os-release ]]; then
    # shellcheck source=/dev/null
    . /etc/os-release
    ok "OS : ${PRETTY_NAME:-$NAME}"
    case "${ID:-}" in
        ubuntu)
            MAJOR="${VERSION_ID%%.*}"
            if [[ "$MAJOR" -ge 22 ]]; then
                ok "Version Ubuntu compatible (22.04+ recommandé)"
            else
                warn "Ubuntu < 22.04 — préférez 22.04 LTS ou 24.04 LTS"
            fi
            ;;
        *)
            warn "OS non Ubuntu — le guide officiel cible Ubuntu Server LTS"
            ;;
    esac
else
    warn "Impossible de détecter la distribution"
fi

# --- Commandes de base ---
echo ""
echo "Outils système"
for cmd in curl git unzip rsync ssh scp mysqldump mysql; do
    if command -v "$cmd" >/dev/null 2>&1; then
        ok "$cmd ($(command -v "$cmd"))"
    else
        miss "$cmd — requis pour import/sync depuis le VPS"
    fi
done

# --- Apache ---
echo ""
echo "Apache"
if command -v apache2 >/dev/null 2>&1 || systemctl is-active apache2 >/dev/null 2>&1; then
    ok "Apache installé"
    if systemctl is-active apache2 >/dev/null 2>&1; then
        ok "Service apache2 actif"
    else
        warn "Apache installé mais service inactif — sudo systemctl start apache2"
    fi
    if apache2ctl -M 2>/dev/null | grep -q rewrite_module || a2query -m rewrite >/dev/null 2>&1; then
        ok "mod_rewrite activé"
    else
        miss "mod_rewrite — sudo a2enmod rewrite && sudo systemctl reload apache2"
    fi
else
    miss "Apache (apache2) — sudo apt install apache2"
fi

# --- MySQL / MariaDB ---
echo ""
echo "MySQL / MariaDB"
if command -v mysql >/dev/null 2>&1; then
    ok "Client mysql : $(mysql --version 2>/dev/null | head -1)"
    if systemctl is-active mysql >/dev/null 2>&1 || systemctl is-active mariadb >/dev/null 2>&1; then
        ok "Service MySQL/MariaDB actif"
    else
        warn "MySQL installé mais service inactif — sudo systemctl start mysql"
    fi
else
    miss "MySQL — sudo apt install mysql-server"
fi

# --- PHP ---
echo ""
echo "PHP (>= 8.0 requis par composer.json)"
if command -v php >/dev/null 2>&1; then
    PHP_VER=$(php -r 'echo PHP_VERSION;')
    PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
    PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
    ok "PHP CLI $PHP_VER"
    if [[ "$PHP_MAJOR" -lt 8 ]]; then
        miss "PHP $PHP_VER trop ancien — minimum 8.0 (8.1+ recommandé)"
    fi
else
    miss "PHP — sudo apt install php php-cli libapache2-mod-php"
fi

REQUIRED_EXTS=(pdo_mysql curl json mbstring xml gd zip intl)
echo ""
echo "Extensions PHP"
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
        ok "extension $ext"
    else
        miss "extension $ext — ex. sudo apt install php-${ext} ou php-mysql pour pdo_mysql"
        PHP_EXT_MISSING=$((PHP_EXT_MISSING + 1))
    fi
done

# --- Composer ---
echo ""
echo "Composer"
if command -v composer >/dev/null 2>&1; then
    ok "Composer $(composer --version 2>/dev/null | head -1)"
else
    miss "Composer — curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer"
fi

# --- Espace disque ---
echo ""
echo "Espace disque"
DISK_AVAIL=$(df -BG / 2>/dev/null | awk 'NR==2 {print $4}' | tr -d 'G')
if [[ -n "$DISK_AVAIL" && "$DISK_AVAIL" -ge 20 ]]; then
    ok "Espace libre sur / : ${DISK_AVAIL} Go"
elif [[ -n "$DISK_AVAIL" ]]; then
    warn "Espace libre faible : ${DISK_AVAIL} Go — prévoir 50 Go+ (BDD + upload/)"
else
    warn "Impossible de lire l'espace disque"
fi

# --- Réseau (optionnel) ---
echo ""
echo "Réseau (optionnel)"
if ping -c1 -W2 8.8.8.8 >/dev/null 2>&1; then
    ok "Accès Internet (ping 8.8.8.8)"
else
    warn "Pas d'Internet — OK pour usage LAN, mais import initial depuis VPS nécessite une connexion temporaire"
fi

LAN_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
if [[ -n "$LAN_IP" ]]; then
    ok "IP locale : $LAN_IP"
else
    warn "IP locale non détectée"
fi

# --- Dossier application (si déjà déployé) ---
FOUTA_ROOT="${FOUTA_WEB_ROOT:-/var/www/fouta}"
echo ""
echo "Application (si déjà déployée dans $FOUTA_ROOT)"
if [[ -d "$FOUTA_ROOT" ]]; then
    ok "Dossier $FOUTA_ROOT existe"
    [[ -f "$FOUTA_ROOT/composer.json" ]] && ok "composer.json présent" || warn "composer.json absent"
    [[ -f "$FOUTA_ROOT/conn/conn.php" ]] && ok "conn/conn.php présent" || warn "conn/conn.php absent — copier conn.example.php"
    [[ -f "$FOUTA_ROOT/config/sync.php" ]] && ok "config/sync.php présent" || warn "config/sync.php absent — copier config/sync.example.php"
    [[ -d "$FOUTA_ROOT/vendor" ]] && ok "vendor/ (Composer) présent" || warn "vendor/ absent — lancer composer install"
    [[ -d "$FOUTA_ROOT/upload" ]] && ok "upload/ présent" || warn "upload/ absent — à copier depuis le VPS"
else
    warn "Application pas encore déployée dans $FOUTA_ROOT (normal en première installation)"
fi

# --- Résumé ---
echo ""
echo -e "${CYAN}=== Résumé ===${NC}"
if [[ "$MISSING" -eq 0 ]]; then
    echo -e "${GREEN}Tous les prérequis essentiels semblent installés.${NC}"
    echo ""
    echo "Prochaines étapes :"
    echo "  1. Copier config/pull_prod_entreprise.example.php → config/pull_prod_entreprise.php"
    echo "  2. Importer BDD + fichiers : ./scripts/pull_prod_to_entreprise.sh"
    echo "  3. Configurer sync push local→VPS : config/sync.php"
else
    echo -e "${RED}$MISSING élément(s) manquant(s).${NC}"
    echo ""
    echo "Installation automatique (Ubuntu 22.04/24.04) :"
    echo "  sudo FOUTA_DB_PASS='MotDePasse!' FOUTA_SYNC_TOKEN='VotreToken' \\"
    echo "       FOUTA_LAN_IP='${LAN_IP:-192.168.1.217}' \\"
    echo "       ./scripts/install_local_server.sh"
    echo ""
    echo "Ou installation manuelle des paquets :"
    echo "  sudo apt update && sudo apt install -y apache2 mysql-server \\"
    echo "    php php-cli libapache2-mod-php php-mysql php-curl php-json \\"
    echo "    php-mbstring php-xml php-gd php-zip php-intl unzip git curl rsync"
fi
echo ""

exit $([[ "$MISSING" -eq 0 ]] && echo 0 || echo 1)
