# Installation serveur local — fplserver (192.168.1.217)

Guide rapide pour le serveur déjà configuré (Ubuntu 22.04).

---

## Informations serveur

| Élément | Valeur |
|---------|--------|
| Hostname | `fplserver` |
| IP LAN | `192.168.1.217` |
| IP Tailscale | `100.120.171.2` |
| Chemin web | `/var/www/fouta` |
| URL site | `http://192.168.1.217/` |

---

## Étape 1 — Connexion SSH

Depuis Windows (PowerShell) :

```powershell
ssh jomas@100.120.171.2
# ou depuis le réseau local :
ssh jomas@192.168.1.217
```

---

## Étape 2 — Installation automatique (recommandé)

Sur le serveur :

```bash
cd ~
git clone https://github.com/nick-dev12/fouta_.git fouta-install
cd fouta-install
chmod +x scripts/install_local_server.sh

sudo FOUTA_DB_PASS='VotreMotDePasseMySQL!' \
     FOUTA_SYNC_TOKEN='FoutaSync2026DevTokenChangeInProduction!' \
     FOUTA_LAN_IP='192.168.1.217' \
     ./scripts/install_local_server.sh
```

Le script installe : Apache, PHP, MySQL, clone le code, Composer, Apache vhost, config, cron sync.

---

## Étape 3 — Importer depuis la PRODUCTION (recommandé)

Site production : [https://e.foutapoidslourds.com/](https://e.foutapoidslourds.com/)

```bash
cd /var/www/fouta
git pull origin main

# 1. Créer la config (une seule fois)
cp config/import_production.example.php config/import_production.php
nano config/import_production.php
# → Renseigner production_db, production_ssh.upload_path, local.db_pass

# 2. Trouver le chemin upload/ sur le VPS si besoin
php scripts/import_from_production.php --find-upload-path

# 3. Import complet (BDD + images)
sudo php scripts/import_from_production.php
```

Options :

| Commande | Action |
|----------|--------|
| `php scripts/import_from_production.php` | BDD + upload/ |
| `php scripts/import_from_production.php --db-only` | BDD seulement |
| `php scripts/import_from_production.php --files-only` | Images seulement |
| `php scripts/import_from_production.php --find-upload-path` | Lister chemins upload sur VPS |

---

## Étape 3 bis — Importer depuis WAMP (alternative)

Sur **Windows** (WAMP), exporter la base :

```powershell
cd C:\wamp64\bin\mysql\mysql8.x.x\bin
.\mysqldump.exe -u root fouta3 > C:\Users\jomas\Desktop\fouta_local.sql
```

Copier vers le serveur :

```powershell
scp C:\Users\jomas\Desktop\fouta_local.sql jomas@100.120.171.2:~/
```

Sur le **serveur** :

```bash
mysql -u fouta_user -p fouta_local < ~/fouta_local.sql
cd /var/www/fouta
sudo -u www-data php migrations/run_add_sync_columns.php
sudo -u www-data php migrations/run_assign_sync_uuids.php
```

### Option B — Dump depuis le VPS

```bash
mysqldump -h 62.171.190.193 -u jomas_effe -p jomas_fouta3 > ~/fouta_vps.sql
mysql -u fouta_user -p fouta_local < ~/fouta_vps.sql
```

---

## Étape 4 — Copier les images (upload/)

Depuis **Windows** vers le serveur :

```powershell
scp -r C:\wamp64\www\Fouta\upload jomas@100.120.171.2:/tmp/upload
```

Sur le **serveur** :

```bash
sudo rsync -av /tmp/upload/ /var/www/fouta/upload/
sudo chown -R www-data:www-data /var/www/fouta/upload
sudo chmod -R 775 /var/www/fouta/upload
```

---

## Corriger erreur sync triggers (1419)

Si `run_add_sync_columns.php` affiche l'erreur `SUP privilege / log_bin_trust_function_creators` :

```bash
sudo mysql -u root -e "SET GLOBAL log_bin_trust_function_creators = 1;"
echo 'log_bin_trust_function_creators = 1' | sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf
sudo systemctl restart mysql
cd /var/www/fouta
sudo -u www-data php migrations/run_add_sync_columns.php
sudo -u www-data php migrations/run_assign_sync_uuids.php
```


```bash
cd /var/www/fouta
sudo -u www-data php scripts/sync_test_ping.php
sudo -u www-data php scripts/sync_local_to_vps.php --files-priority
```

Depuis un poste du bureau :

- `http://192.168.1.217/`
- `http://192.168.1.217/admin/`

---

## Étape 6 — Pare-feu (optionnel)

Autoriser uniquement le réseau local :

```bash
sudo ufw allow from 192.168.1.0/24 to any port 80
sudo ufw allow OpenSSH
sudo ufw enable
```

---

## Mise à jour ultérieure

```bash
cd /var/www/fouta
git pull origin main
composer install --no-dev
sudo systemctl reload apache2
```

---

## Déploiement depuis WAMP (Windows → serveur local)

Script tout-en-un : **production → WAMP → fplserver** (BDD + images).

1. Copier la config :
   ```powershell
   cd C:\wamp64\www\Fouta
   copy config\deploy_wamp.example.php config\deploy_wamp.php
   notepad config\deploy_wamp.php
   ```
   Renseigner : `production_db`, `local_server`, et FTP si besoin (`production_files.method` = `ftp` ou `skip`).

2. Lancer (double-clic ou PowerShell) :
   ```powershell
   scripts\sync_prod_to_wamp_and_server.bat
   ```
   Ou :
   ```powershell
   php scripts/sync_prod_to_wamp_and_server.php
   ```

| Option | Action |
|--------|--------|
| `--prod-only` | Production → WAMP seulement |
| `--server-only` | WAMP → serveur local seulement |
| `--db-only` | Base de données uniquement |
| `--files-only` | Images upload/ uniquement |
| `--dry-run` | Simulation |

### Sync incrémentale (WAMP ↔ production + serveur → production)

Configurer sans refaire le deploy complet :

```powershell
scripts\setup_sync_nodes.bat
# ou :
php scripts/setup_sync_nodes.php
php scripts/setup_sync_nodes.php --wamp-only
php scripts/setup_sync_nodes.php --server-only
```

| Nœud | node_id | Direction |
|------|---------|-----------|
| WAMP dev | `dev_wamp` | `bidirectional` |
| Serveur fplserver | `local_entreprise` | `push_only` |
| Production | `production` | (distant — e.foutapoidslourds.com) |

Token partagé : identique dans `deploy_wamp.php` → section `sync` et sur **e.foutapoidslourds.com** (`config/sync.php`).

**Exploitation :**
- Refresh complet prod → WAMP → fplserver : `sync_prod_to_wamp_and_server.bat`
- Sync quotidienne magasin → production : cron auto sur fplserver (toutes les 30 min)
- Dev WAMP : `php scripts/sync_run.php`

---

## Dépannage

| Problème | Solution |
|----------|----------|
| Page blanche | `tail -f /var/log/apache2/fouta-error.log` |
| Redirection HTTPS prod | Vérifier `.htaccess` (lignes e.foutapoidslourds.com commentées) |
| BDD inaccessible | Vérifier `conn/conn.php` |
| Sync échoue | `php scripts/sync_test_ping.php` + token identique sur e.foutapoidslourds.com |

Voir aussi : [DEPLOIEMENT_LOCAL_ET_SYNC.md](DEPLOIEMENT_LOCAL_ET_SYNC.md)
