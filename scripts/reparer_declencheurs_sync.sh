#!/usr/bin/env bash
# RECRÉER LES DÉCLENCHEURS DE SYNCHRO SUR FOUTASVR (08/09/2026) — une commande, en sudo :
#
#     cd /var/www/fouta && sudo bash scripts/reparer_declencheurs_sync.sh
#
# POURQUOI : l'import du 1er septembre (--skip-triggers) a effacé les 156
# déclencheurs qui avancent sync_updated_at à chaque modification. Depuis,
# le déploiement échoue à les recréer (erreur MySQL 1419 : le journal binaire
# est actif et l'utilisateur de l'application n'a pas le droit SUPER).
# Sans eux, la synchro nocturne ne voit presque aucune modification faite à
# l'atelier (photos, stocks, ventes…) : le VPS n'est plus une vraie copie.
#
# CE QUE FAIT LE SCRIPT :
#   1. autorise MySQL à créer des déclencheurs sans SUPER (SET PERSIST : la
#      valeur survit aux redémarrages, aucun fichier de configuration à éditer) ;
#   2. rejoue migrations/run_add_sync_columns.php, qui recrée les déclencheurs ;
#   3. compte les déclencheurs et s'arrête en erreur s'il n'y en a toujours pas.
set -euo pipefail
cd "$(dirname "$0")/.."
echo "1. droit de créer des déclencheurs (log_bin_trust_function_creators)"
mysql -e "SET PERSIST log_bin_trust_function_creators = 1; SELECT @@global.log_bin_trust_function_creators AS trust_function_creators;"
echo "2. recréation des déclencheurs"
sudo -u fouta php migrations/run_add_sync_columns.php 2>&1 | tail -5 || php migrations/run_add_sync_columns.php 2>&1 | tail -5
echo "3. contrôle"
N=$(php -r 'require "conn/conn.php"; echo (int) $db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE \"tr_%_sync_%\"")->fetchColumn();')
echo "   déclencheurs de synchro : $N"
if [ "$N" -lt 2 ]; then echo "   ÉCHEC : aucun déclencheur recréé — lire la sortie ci-dessus"; exit 1; fi
echo "TERMINÉ : la synchro voit de nouveau chaque modification."
