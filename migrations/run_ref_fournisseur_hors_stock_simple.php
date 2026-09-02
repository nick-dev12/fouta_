<?php
/**
 * LA RÉFÉRENCE FOURNISSEUR HORS DE LA VUE DU STOCK SIMPLE (02/09/2026).
 *
 * Décision de la direction : le gestionnaire de stock SIMPLE (rôle
 * `gestion_stock`, le rayonniste) ne doit voir NI le fournisseur, NI la
 * référence fournisseur. Le fournisseur (champ `fournisseur_id`) lui était
 * déjà caché ; la référence fournisseur, elle, lui restait visible — elle
 * fuyait notamment dans la colonne « Référence » du catalogue (repli sur la
 * réf. fournisseur quand l'OEM manque).
 *
 * Le geste : retirer la ligne de droit du rôle `gestion_stock` sur le champ
 * `reference_fournisseur`. Un champ n'est visible d'un rôle que s'il porte
 * une ligne de droit pour lui (c'est ainsi que `fournisseur_id` est masqué) ;
 * en la retirant, la référence fournisseur disparaît PARTOUT pour ce rôle —
 * catalogue, fiche, formulaires, exports.
 *
 * Idempotent : rejouée, elle ne trouve plus la ligne et le dit.
 *   php migrations/run_ref_fournisseur_hors_stock_simple.php
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

/* état avant */
$avant = $db->prepare('SELECT niveau FROM produit_formulaire_champ_role WHERE champ_id = ? AND role = ?');
$avant->execute([$cid, 'gestion_stock']);
$niv = $avant->fetchColumn();
if ($niv === false) {
    echo "le stock simple (gestion_stock) n'a déjà AUCUN droit dessus — rien à faire\n";
} else {
    printf("le stock simple avait le niveau « %s» dessus — on le retire\n", $niv);
    $del = $db->prepare('DELETE FROM produit_formulaire_champ_role WHERE champ_id = ? AND role = ?');
    $del->execute([$cid, 'gestion_stock']);
    printf("retiré : %d ligne(s)\n", $del->rowCount());
}

/* relecture : qui voit encore la référence fournisseur ? */
$roles = $db->prepare('SELECT role, niveau FROM produit_formulaire_champ_role WHERE champ_id = ? ORDER BY role');
$roles->execute([$cid]);
$txt = [];
foreach ($roles->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $txt[] = $r['role'] . '=' . $r['niveau'];
}
echo 'rôles avec droit sur la référence fournisseur : ', implode(', ', $txt), "\n";
echo (in_array('gestion_stock', array_map(function ($r) { return explode('=', $r)[0]; }, $txt), true)
    ? "ATTENTION : gestion_stock y est encore\n"
    : "le stock simple ne voit plus la référence fournisseur.\n");
