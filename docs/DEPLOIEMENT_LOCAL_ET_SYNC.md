# Déploiement local Ubuntu Server + synchronisation bidirectionnelle

Guide complet pour installer l'application Fouta en réseau local (sans Internet obligatoire) et synchroniser les données avec le VPS Contabo (Webuzo).

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Prérequis matériels](#2-prérequis-matériels)
3. [Installation Ubuntu Server LTS](#3-installation-ubuntu-server-lts)
4. [Installation LAMP](#4-installation-lamp)
5. [Déploiement de l'application](#5-déploiement-de-lapplication)
6. [Configuration Apache](#6-configuration-apache)
7. [Réseau et sécurité](#7-réseau-et-sécurité)
8. [Sauvegarde locale](#8-sauvegarde-locale)
9. [Système de synchronisation](#9-système-de-synchronisation)
10. [Tests depuis WAMP (développement)](#10-tests-depuis-wamp-développement)
11. [Exploitation quotidienne](#11-exploitation-quotidienne)
12. [Dépannage](#12-dépannage)

---

## 1. Vue d'ensemble

```
┌─────────────────────────────────────────────────────────────┐
│  RÉSEAU LOCAL ENTREPRISE (LAN)                               │
│  Ubuntu Server 192.168.1.100                                 │
│  Apache + PHP 8 + MySQL → /var/www/fouta                     │
│       ▲                                                      │
│       │ http://192.168.1.100                                 │
│  ┌────┴────┬──────────┬──────────┐                          │
│  │ Caisse  │ Admin    │ Autres   │                          │
│  └─────────┴──────────┴──────────┘                          │
└───────────────────────────┬─────────────────────────────────┘
                            │ HTTPS (quand Internet disponible)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  VPS Contabo — Webuzo                                        │
│  https://www.foutapoidslourds.com                            │
│  MySQL jomas_fouta3                                          │
└─────────────────────────────────────────────────────────────┘
```

**Principe :** tous les postes du bureau accèdent au serveur Ubuntu via le réseau local. La synchronisation vers le VPS s'effectue quand Internet est disponible, lancée **depuis le serveur local** (le VPS ne peut pas initier la connexion vers un réseau privé).

---

## 2. Prérequis matériels

| Élément | Recommandation |
|---------|----------------|
| Machine serveur | Mini-PC ou PC fixe, 4 Go RAM min, 50 Go SSD |
| Réseau | Câble Ethernet pour le serveur (Wi-Fi possible) |
| Onduleur (UPS) | Fortement recommandé (coupures de courant) |
| Clé USB | Sauvegarde quotidienne MySQL |
| OS | Ubuntu Server 22.04 LTS ou 24.04 LTS |

---

## 3. Installation Ubuntu Server LTS

### 3.1 Installation de base

1. Flasher Ubuntu Server 22.04/24.04 LTS sur clé USB (Rufus / balenaEtcher).
2. Installer en cochant **OpenSSH server**.
3. Créer un utilisateur administrateur (ex. `fouta-admin`).
4. Mettre à jour :

```bash
sudo apt update && sudo apt upgrade -y
sudo reboot
```

### 3.2 IP fixe (Netplan)

Éditer `/etc/netplan/00-installer-config.yaml` (adapter l'interface et la passerelle) :

```yaml
network:
  version: 2
  ethernets:
    enp0s3:
      dhcp4: no
      addresses:
        - 192.168.1.100/24
      routes:
        - to: default
          via: 192.168.1.1
      nameservers:
        addresses: [8.8.8.8, 1.1.1.1]
```

Appliquer :

```bash
sudo netplan apply
ip a
```

**Alternative :** réserver l'adresse MAC du serveur dans la box/routeur (DHCP statique).

### 3.3 Hostname

```bash
sudo hostnamectl set-hostname fouta-serveur
```

---

## 4. Installation LAMP

### 4.1 Vérifier les versions sur le VPS

Sur le VPS Webuzo (SSH ou terminal cPanel) :

```bash
php -v
mysql --version
```

Installer **la même version majeure de PHP** sur Ubuntu.

### 4.2 Packages

Ubuntu 22.04 (PHP 8.1) :

```bash
sudo apt install -y apache2 mysql-server \
  php libapache2-mod-php php-mysql php-curl php-json php-mbstring \
  php-xml php-gd php-zip php-intl unzip git
```

Ubuntu 24.04 (PHP 8.3 par défaut) — si le VPS est en 8.2, ajouter le PPA ondrej/php.

### 4.3 Composer

```bash
cd /tmp
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 4.4 Sécuriser MySQL

```bash
sudo mysql_secure_installation
```

Créer base et utilisateur :

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE fouta_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fouta_user'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_SOLIDE';
GRANT ALL PRIVILEGES ON fouta_local.* TO 'fouta_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

MySQL doit rester en écoute **localhost uniquement** (défaut sécurisé).

---

## 5. Déploiement de l'application

### 5.1 Copier les fichiers

```bash
sudo mkdir -p /var/www/fouta
sudo chown -R $USER:www-data /var/www/fouta
cd /var/www/fouta
git clone VOTRE_DEPOT . 
# ou : scp/rsync depuis votre machine de dev
composer install --no-dev
```

### 5.2 Configuration

```bash
cp conn/conn.example.php conn/conn.php
cp config/site.example.php config/site.php
cp config/sync.example.php config/sync.php
```

Éditer `conn/conn.php` :

```php
$db_host = "localhost";
$db_name = "fouta_local";
$db_user = "fouta_user";
$db_pass = "MOT_DE_PASSE_SOLIDE";
```

Éditer `config/site.php` :

```php
return ['site_url' => 'http://192.168.1.100'];
```

Éditer `config/sync.php` :

```php
return [
    'node_id' => 'local_entreprise',
    'remote_url' => 'https://www.foutapoidslourds.com',
    'remote_api_token' => 'TOKEN_IDENTIQUE_SUR_LES_DEUX_NOEUDS',
    'node_priority_on_tie' => false,
];
```

### 5.3 Base de données initiale

**Option A — Recommandée : dump depuis le VPS**

```bash
# Depuis une machine avec accès au VPS
mysqldump -h IP_VPS -u USER -p jomas_fouta3 > fouta_init.sql
mysql -u fouta_user -p fouta_local < fouta_init.sql
```

**Option B — Schéma vierge**

```bash
mysql -u fouta_user -p fouta_local < migrations/schema_production_install_complet.sql
php migrations/run_migration_production_ajouts.php
```

### 5.4 Dossier upload

Copier depuis le VPS :

```bash
rsync -avz user@vps:/chemin/vers/Fouta/upload/ /var/www/fouta/upload/
```

### 5.5 Permissions

```bash
sudo chown -R www-data:www-data /var/www/fouta
sudo chmod -R 755 /var/www/fouta/upload
```

### 5.6 Adapter .htaccess pour le local

Commenter les redirections HTTPS et domaine production en tête de `.htaccess` :

```apache
# RewriteCond %{HTTP_HOST} ^www\. [NC]
# RewriteRule ^ https://e.foutapoidslourds.com%{REQUEST_URI} [L,R=301]
# RewriteCond %{HTTPS} !=on
# RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 6. Configuration Apache

Créer `/etc/apache2/sites-available/fouta.conf` :

```apache
<VirtualHost *:80>
    ServerName 192.168.1.100
    DocumentRoot /var/www/fouta

    <Directory /var/www/fouta>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/fouta-error.log
    CustomLog ${APACHE_LOG_DIR}/fouta-access.log combined
</VirtualHost>
```

Activer :

```bash
sudo a2enmod rewrite
sudo a2ensite fouta.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

Limites PHP (fichier `/var/www/fouta/.user.ini`) :

```ini
upload_max_filesize = 1000M
post_max_size = 1000M
max_execution_time = 300
memory_limit = 512M
```

---

## 7. Réseau et sécurité

### 7.1 Pare-feu UFW

```bash
sudo ufw allow from 192.168.1.0/24 to any port 80
sudo ufw allow OpenSSH
sudo ufw enable
sudo ufw status
```

Adapter `192.168.1.0/24` au sous-réseau du client.

### 7.2 Test accès LAN

Depuis un autre poste du bureau :

```
http://192.168.1.100/
http://192.168.1.100/admin/
```

### 7.3 Auto-démarrage

```bash
sudo systemctl enable apache2 mysql
```

---

## 8. Sauvegarde locale

### 8.1 Script `/usr/local/bin/backup-fouta.sh`

```bash
#!/bin/bash
set -e
BACKUP_DIR="/media/usb/backups"
mkdir -p "$BACKUP_DIR"
FILE="$BACKUP_DIR/fouta_$(date +%Y%m%d_%H%M).sql.gz"
mysqldump -u fouta_user -p'MOT_DE_PASSE' fouta_local | gzip > "$FILE"
find "$BACKUP_DIR" -name "fouta_*.sql.gz" -mtime +7 -delete
echo "Backup OK : $FILE"
```

```bash
sudo chmod +x /usr/local/bin/backup-fouta.sh
```

### 8.2 Cron

```bash
sudo crontab -e
```

```
0 2 * * * /usr/local/bin/backup-fouta.sh >> /var/log/fouta-backup.log 2>&1
```

### 8.3 Test de restauration

```bash
gunzip -c backup.sql.gz | mysql -u fouta_user -p fouta_local
```

---

## 9. Système de synchronisation

### 9.1 Architecture

| Composant | Fichier |
|-----------|---------|
| Config | `config/sync.php` |
| API distante | `sync/api.php` |
| Moteur | `includes/sync_functions.php` |
| Registre tables | `includes/sync_registry.php` |
| CLI bidirectionnel | `scripts/sync_run.php` |
| Interface admin | `admin/sync/index.php` |

Chaque enregistrement possède :

- `sync_uuid` — identifiant global unique
- `sync_updated_at` — date de dernière modification
- `sync_deleted_at` — suppression logique (soft delete)
- `sync_origin_node` — nœud ayant créé/modifié l'enregistrement

**Conflits :** règle Last-Write-Wins (`sync_updated_at` le plus récent gagne).

### 9.2 Installation sync (sur CHAQUE nœud : local + VPS)

```bash
cd /var/www/fouta   # ou chemin WAMP

# 1. Infrastructure + colonnes + triggers
php migrations/run_add_sync_columns.php

# 2. UUID pour données existantes
php migrations/run_assign_sync_uuids.php
```

**Important :** exécuter d'abord sur le VPS, puis faire un **pull initial** sur le local pour aligner les UUID.

### 9.3 Configuration identique du token

Le `remote_api_token` doit être **identique** sur les deux nœuds. Générer un token fort :

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Sur le **serveur local** :

```php
'node_id' => 'local_entreprise',
'remote_url' => 'https://www.foutapoidslourds.com',
'remote_api_token' => 'VOTRE_TOKEN',
```

Sur le **VPS** :

```php
'node_id' => 'vps_prod',
'remote_url' => 'http://192.168.1.100',  // inutilisé si sync initiée depuis local uniquement
'remote_api_token' => 'VOTRE_TOKEN',
```

### 9.4 Première synchronisation

Ordre recommandé :

```bash
# 1. Pull initial (aligner local sur VPS)
php scripts/sync_pull.php

# 2. Sync fichiers images
php scripts/sync_files.php

# 3. Vérification
php scripts/sync_verify.php
```

### 9.5 Commandes CLI

| Commande | Action |
|----------|--------|
| `php scripts/sync_pull.php` | Récupère les changements du VPS |
| `php scripts/sync_push.php` | Envoie les changements locaux au VPS |
| `php scripts/sync_run.php` | Pull puis push (bidirectionnel) |
| `php scripts/sync_files.php` | Sync dossier `upload/` |
| `php scripts/sync_verify.php` | Compare comptages par table |
| `php scripts/sync_run.php --dry-run` | Simulation sans écriture |

### 9.6 Cron (serveur local)

```bash
sudo nano /etc/cron.d/fouta-sync
```

```
*/30 * * * * www-data /usr/bin/php /var/www/fouta/scripts/sync_run.php >> /var/log/fouta-sync.log 2>&1
0 3 * * * www-data /usr/bin/php /var/www/fouta/scripts/sync_files.php >> /var/log/fouta-sync-files.log 2>&1
```

### 9.7 Interface admin

URL : `/admin/sync/index.php`

Boutons : Tester connexion, Pull, Push, Sync complète, Sync fichiers.

Accès : rôles **informaticien**, **développeur** ou **admin**.

### 9.8 Tables exclues par défaut

- `panier` (session)
- `fcm_tokens` (appareils)
- `user_password_reset`, `admin_password_reset` (tokens temporaires)
- Tables `sync_*` (métadonnées)

Modifiable via `excluded_tables` dans `config/sync.php`.

---

## 10. Tests depuis WAMP (développement)

### 10.1 Prérequis WAMP

- PHP >= 8.0 avec extensions : `curl`, `pdo_mysql`, `json`, `mbstring`
- Base locale `fouta` (WAMP) — **ne pas** pointer toute l'app sur le VPS
- Extension cURL activée dans `php.ini`

### 10.2 Configuration locale

```bash
copy config\sync.example.php config\sync.php
```

Éditer `config/sync.php` :

```php
'node_id' => 'dev_wamp',
'remote_url' => 'https://www.foutapoidslourds.com',
'remote_api_token' => 'TOKEN_IDENTIQUE_AU_VPS',
'remote_db_verify' => [
    'host' => 'IP_VPS',
    'name' => 'jomas_fouta3',
    'user' => 'utilisateur',
    'pass' => 'mot_de_passe',
],
```

Commenter les redirects HTTPS dans `.htaccess` pour le dev local.

### 10.3 Déploiement sync sur le VPS (obligatoire)

1. **Sauvegarde BDD VPS** avant toute manipulation :

```bash
mysqldump -h IP_VPS -u USER -p jomas_fouta3 > backup_avant_sync.sql
```

2. Uploader via FTP/SFTP les dossiers/fichiers :
   - `sync/`
   - `includes/sync_*.php`
   - `scripts/sync_*.php`
   - `migrations/create_sync_infrastructure.sql`
   - `migrations/run_add_sync_columns.php`
   - `migrations/run_assign_sync_uuids.php`
   - `config/sync.example.php` → copier en `config/sync.php` sur VPS

3. Exécuter sur le VPS :

```bash
php migrations/run_add_sync_columns.php
php migrations/run_assign_sync_uuids.php
```

4. Configurer `config/sync.php` sur VPS avec le même token.

### 10.4 Checklist de test

| # | Étape | Commande / action | Résultat attendu |
|---|-------|-------------------|------------------|
| 1 | Migrations locales | `php migrations/run_add_sync_columns.php` | Colonnes sync créées |
| 2 | UUID locaux | `php migrations/run_assign_sync_uuids.php` | UUID assignés |
| 3 | Test connexion | `php -r` ou admin sync « Tester connexion » | JSON `{ success: true }` |
| 4 | Pull initial | `php scripts/sync_pull.php` | Données VPS → local |
| 5 | Push test | Créer vente caisse locale, `php scripts/sync_push.php` | Visible sur VPS |
| 6 | Pull test | Modifier produit sur VPS, `php scripts/sync_pull.php` | Mis à jour en local |
| 7 | Conflit | Modifier même enregistrement des 2 côtés, `sync_run.php` | Log dans `sync_log` |
| 8 | Fichiers | `php scripts/sync_files.php` | Images sur VPS |
| 9 | Vérification | `php scripts/sync_verify.php` | Comptages comparés |

### 10.5 Test connexion API (curl)

```bash
curl -X POST "https://www.foutapoidslourds.com/sync/api.php?action=ping" \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json"
```

Réponse attendue :

```json
{"success":true,"node_id":"vps_prod","time":"...","tables":42}
```

### 10.6 Limitation réseau WAMP

Le VPS **ne peut pas** appeler votre PC WAMP (NAT). Tous les tests sont **initiés depuis WAMP** :

```bash
cd C:\wamp64\www\Fouta
php scripts/sync_pull.php
php scripts/sync_push.php
```

---

## 11. Exploitation quotidienne

### Scénario type (magasin sans Internet permanent)

1. Matin : le serveur local fonctionne en autonomie (LAN).
2. Ventes, caisse, stock : enregistrés en local.
3. Quand Internet revient : sync automatique (cron 30 min) ou manuelle via admin.
4. Soir : vérifier `/var/log/fouta-sync.log`.

### Sync manuelle

```bash
php scripts/sync_run.php
php scripts/sync_files.php
```

Ou via **Admin → Synchronisation**.

---

## 12. Dépannage

| Problème | Solution |
|----------|----------|
| `Token non autorisé` | Vérifier token identique dans les deux `config/sync.php` |
| `Extension curl requise` | Activer `extension=curl` dans php.ini WAMP |
| Timeout sync | Augmenter `http_timeout` dans config ; `max_execution_time=600` en CLI |
| Conflits stock | Consulter table `sync_log` ; résoudre manuellement si nécessaire |
| UUID différents | Refaire pull initial après assign UUID sur VPS |
| Erreur FK à l'import | Vérifier ordre des tables ; relancer pull table par table |
| SSL certificat | `verify_ssl => false` en dev uniquement |
| Postes ne voient pas le serveur | Vérifier IP, UFW, ping 192.168.1.100 |

### Logs

```bash
tail -f /var/log/fouta-sync.log
mysql -u fouta_user -p -e "SELECT * FROM sync_log ORDER BY id DESC LIMIT 20;" fouta_local
```

### Mode simulation

```bash
php scripts/sync_run.php --dry-run
```

---

## Fichiers créés par le module sync

```
config/sync.example.php
includes/sync_registry.php
includes/sync_functions.php
includes/sync_hooks.php
sync/api.php
sync/.htaccess
migrations/create_sync_infrastructure.sql
migrations/run_add_sync_columns.php
migrations/run_assign_sync_uuids.php
scripts/sync_run.php
scripts/sync_push.php
scripts/sync_pull.php
scripts/sync_files.php
scripts/sync_verify.php
admin/sync/index.php
```

---

*Documentation générée pour le projet Fouta — déploiement local et synchronisation bidirectionnelle.*
