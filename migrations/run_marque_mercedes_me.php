<?php
/**
 * MERCEDES S'ABRÈGE « ME », PAS « MER » (08/09/2026) — décision de la direction.
 *
 * L'abréviation de marque entre dans la référence FPL de chaque pièce
 * (FPL + code famille + abréviation + 6 chiffres OEM). Le 07/09, la marque
 * MERCEDES BENZ avait reçu « MER » ; la direction veut « ME » — c'est aussi ce
 * que porte l'atelier d'étiquettes depuis le début (FPL 100 ME107516).
 *
 * Ce que fait la migration :
 *   1. elle vérifie que « ME » n'est pris par aucune autre marque ;
 *   2. elle pose « ME » sur la marque Mercedes (celle qui porte « MER ») ;
 *   3. elle RECALCULE les références FPL de toutes les pièces : seules celles
 *      de Mercedes changent (le calcul est déterministe) — le compte rendu le
 *      prouve, pièce par pièce si demandé.
 * Les codes-barres et QR ne bougent pas : ils reposent sur l'identifiant, pas
 * sur la référence. Les étiquettes déjà imprimées avec « MER » restent lisibles
 * (même code-barres) ; elles porteront « ME » à la prochaine impression.
 *
 * Idempotente :  php migrations/run_marque_mercedes_me.php [--detail]
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_reference_fpl.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

if (!reference_fpl_schema_ok()) {
    echo "Le schéma de la référence FPL n'est pas en place (jouez run_reference_fpl.php d'abord).\n";
    exit(1);
}

$detail = in_array('--detail', $argv ?? [], true);

$mercedes = $db->query("SELECT id, nom, abreviation FROM marques
                        WHERE sync_deleted_at IS NULL
                          AND (LOWER(nom) LIKE '%mercedes%' OR LOWER(nom) LIKE '%benz%')
                     ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if ($mercedes === []) {
    echo "Aucune marque Mercedes : rien à faire.\n";
    exit(0);
}

$autre = $db->query("SELECT id, nom FROM marques
                     WHERE abreviation = 'ME' AND sync_deleted_at IS NULL
                       AND NOT (LOWER(nom) LIKE '%mercedes%' OR LOWER(nom) LIKE '%benz%')")->fetch(PDO::FETCH_ASSOC);
if ($autre) {
    echo 'REFUS : « ME » est déjà l\'abréviation de #', $autre['id'], ' « ', $autre['nom'], " » — rien n'a bougé.\n";
    exit(1);
}

$poser = $db->prepare('UPDATE marques SET abreviation = :a, sync_updated_at = NOW() WHERE id = :id');
$changees = 0;
foreach ($mercedes as $m) {
    if ((string) $m['abreviation'] === 'ME') {
        echo 'marque #', $m['id'], ' « ', $m['nom'], " » : déjà ME.\n";
        continue;
    }
    $poser->execute([':a' => 'ME', ':id' => (int) $m['id']]);
    $changees++;
    echo 'marque #', $m['id'], ' « ', $m['nom'], ' » : ', ($m['abreviation'] !== null && $m['abreviation'] !== '' ? $m['abreviation'] : '(vide)'), " → ME\n";
}

/* le recalcul : déterministe, donc seules les pièces Mercedes bougent */
$avant = [];
if ($detail) {
    foreach ($db->query("SELECT id, reference_fpl FROM produits WHERE sync_deleted_at IS NULL") as $r) {
        $avant[(int) $r['id']] = (string) $r['reference_fpl'];
    }
}
produit_reference_fpl_cache_vider();
$stats = produits_reference_fpl_recalculer_tout();
printf("références recalculées : %d pièces vues, %d changées (%d sans code de famille, %d sans OEM, %d sans marque)\n",
    $stats['total'], $stats['changees'], $stats['sans_code'], $stats['oem_manquant'], $stats['marque_manquante']);

$ids = implode(',', array_map(function ($m) { return (int) $m['id']; }, $mercedes));
echo 'pièces Mercedes : ', $db->query("SELECT COUNT(*) FROM produits WHERE marque_id IN ($ids) AND sync_deleted_at IS NULL")->fetchColumn(),
     ' — dont avec « ME » dans la référence : ',
     $db->query("SELECT COUNT(*) FROM produits WHERE marque_id IN ($ids) AND sync_deleted_at IS NULL AND reference_fpl LIKE 'FPL___ME%'")->fetchColumn(),
     ' — encore avec « MER » : ',
     $db->query("SELECT COUNT(*) FROM produits WHERE sync_deleted_at IS NULL AND reference_fpl LIKE 'FPL___MER%'")->fetchColumn(), "\n";

if ($detail) {
    foreach ($db->query("SELECT id, nom, reference_fpl FROM produits WHERE sync_deleted_at IS NULL ORDER BY id") as $r) {
        $id = (int) $r['id'];
        if (isset($avant[$id]) && $avant[$id] !== (string) $r['reference_fpl']) {
            printf("  #%-5d %-34s %s → %s\n", $id, mb_substr((string) $r['nom'], 0, 34), $avant[$id], $r['reference_fpl']);
        }
    }
}
