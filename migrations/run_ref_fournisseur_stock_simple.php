<?php
/**
 * LA RÉFÉRENCE FOURNISSEUR OUVERTE AU STOCK SIMPLE (07/09/2026).
 *
 * Nouvelle décision de la direction : le gestionnaire de stock SIMPLE (rôle
 * `gestion_stock`) DOIT voir et saisir la référence fournisseur. Elle
 * REMPLACE la migration du 02/09 (run_ref_fournisseur_hors_stock_simple.php,
 * supprimée) qui la lui retirait — et qui, rejouée à chaque déploiement par
 * le script de mise à jour, effaçait le droit dès qu'un informaticien le
 * redonnait à l'écran « Champs du formulaire pièce ».
 *
 * Le geste : AJOUTER la ligne de droit (niveau « modifier ») si elle manque.
 * Rien d'autre n'est touché : les autres rôles gardent ce qu'ils ont, et un
 * réglage fait à l'écran n'est jamais écrasé. Idempotente.
 *   php migrations/run_ref_fournisseur_stock_simple.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$champ = $db->query("SELECT id, label FROM produit_formulaire_champ WHERE slug = 'reference_fournisseur' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$champ) {
    echo "champ 'reference_fournisseur' absent — rien à faire\n";
    exit(0);
}
$cid = (int) $champ['id'];
printf("champ : #%d « %s »\n", $cid, $champ['label']);

$nb = (int) $db->query("SELECT COUNT(*) FROM produit_formulaire_champ_role WHERE champ_id = $cid")->fetchColumn();
if ($nb === 0) {
    echo "aucune liste de rôles sur ce champ : il est déjà visible de tous (stock simple compris) — rien à faire\n";
    exit(0);
}

$st = $db->prepare('SELECT niveau FROM produit_formulaire_champ_role WHERE champ_id = ? AND role = ?');
$st->execute([$cid, 'gestion_stock']);
$niv = $st->fetchColumn();
$a_niveau = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produit_formulaire_champ_role' AND COLUMN_NAME = 'niveau'")->fetchColumn() > 0;
if ($niv !== false) {
    printf("le stock simple a déjà le niveau « %s » dessus — rien à faire\n", $niv);
} else {
    if ($a_niveau) {
        $db->prepare('INSERT INTO produit_formulaire_champ_role (champ_id, role, niveau, date_modification) VALUES (?, ?, ?, NOW())')
           ->execute([$cid, 'gestion_stock', 'modifier']);
    } else {
        $db->prepare('INSERT INTO produit_formulaire_champ_role (champ_id, role, date_modification) VALUES (?, ?, NOW())')
           ->execute([$cid, 'gestion_stock']);
    }
    echo "droit AJOUTÉ : gestion_stock = modifier\n";
}

$roles = $db->prepare('SELECT role, ' . ($a_niveau ? 'niveau' : "'modifier' AS niveau") . ' FROM produit_formulaire_champ_role WHERE champ_id = ? ORDER BY role');
$roles->execute([$cid]);
$txt = [];
$ok = false;
foreach ($roles->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $txt[] = $r['role'] . '=' . $r['niveau'];
    if ($r['role'] === 'gestion_stock') {
        $ok = true;
    }
}
echo 'rôles avec droit sur la référence fournisseur : ', implode(', ', $txt), "\n";
if (!$ok) {
    fwrite(STDERR, "ÉCHEC : gestion_stock n'a toujours pas le droit\n");
    exit(1);
}
echo "le stock simple voit et saisit la référence fournisseur.\n";
