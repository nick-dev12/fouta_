<?php
/**
 * Migre produits legacy (colonnes numériques) vers entrepot_position_id.
 * php migrations/migrate_produits_vers_entrepot_position.php
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_entrepot_referentiel.php';

if (!$db || !produits_has_column('entrepot_position_id') || !entrepot_referentiel_tables_ok()) {
    fwrite(STDERR, "Prérequis manquants (migration référentiel + colonne entrepot_position_id).\n");
    exit(1);
}

$stmt = $db->query('SELECT * FROM produits WHERE entrepot_position_id IS NULL');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$updated = 0;

foreach ($rows as $p) {
    $has_legacy = !empty($p['etage']) || !empty($p['barre_rayon']);
    if (!$has_legacy) {
        continue;
    }
    $pid = entrepot_resolve_position_id_from_legacy($p);
    if ($pid === null) {
        continue;
    }
    if (entrepot_assign_produit_position((int) $p['id'], $pid)) {
        $updated++;
    }
}

echo "Produits migrés vers entrepot_position_id : $updated\n";
