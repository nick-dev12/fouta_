<?php
/**
 * LA RÉFÉRENCE FPL SELON LA RÈGLE DE LA DIRECTION (07/09/2026).
 *
 * Ce que fait cette migration, dans l'ordre, sous transaction (tout ou rien) :
 *  1. le schéma : categories.code, sous_categories.code, marques.abreviation,
 *     produits.reference_fpl ;
 *  2. les codes des catégories selon le barème (Accessoires 100, Carrosserie
 *     150, … Châssis 900) ; une catégorie hors barème prend le bloc libre suivant ;
 *  3. les numéros des sous-catégories, à la suite dans le bloc de leur famille,
 *     par ordre alphabétique (seulement celles qui n'en ont pas encore) ;
 *  4. les abréviations des marques (liste marques.pdf, sinon 3 lettres) ;
 *     « CUMINS » (faute de frappe) est fusionnée dans « CUMMINS » ; les marques
 *     HORS liste de la direction et sans aucune donnée (ni pièce, ni modèle de
 *     véhicule) sont retirées (suppression douce, réversible) — celles de la
 *     liste restent, même vides ;
 *  5. les références OEM écrites dans les descriptions sont recopiées dans la
 *     colonne reference_oem quand elle est vide ;
 *  6. la référence FPL de chaque pièce est calculée et enregistrée ;
 *  7. le bilan : combien de pièces sans OEM (repli sur l'identifiant), sans
 *     marque (XXX), et les références en double.
 * Idempotente : rejouée, elle ne change rien de ce qui est déjà posé (les
 * codes et abréviations existants sont CONSERVÉS, même modifiés à la main).
 *
 * Usage : php migrations/run_reference_fpl.php
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_reference_fpl.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

/* 1. le schéma (les ALTER valident implicitement : hors transaction) */
$faits = reference_fpl_schema_poser();
echo 'Schéma : ', $faits === [] ? 'déjà en place' : 'ajouté ' . implode(', ', $faits), "\n";

try {
    $db->beginTransaction();

    /* 2. les codes des catégories */
    $bareme = reference_fpl_bareme_categories();
    $maj = $db->prepare('UPDATE categories SET code = :c WHERE id = :id');
    $sans = [];
    foreach ($db->query("SELECT id, nom, code FROM categories WHERE sync_deleted_at IS NULL ORDER BY id") as $c) {
        if ($c['code']) {
            continue;
        }
        $n = rf_normaliser_nom($c['nom']);
        if (isset($bareme[$n])) {
            $maj->execute([':c' => $bareme[$n], ':id' => (int) $c['id']]);
            printf("  catégorie %-30s → %d\n", $c['nom'], $bareme[$n]);
        } else {
            $sans[] = $c;
        }
    }
    foreach ($sans as $c) {
        $code = categorie_code_prochain();
        if ($code === null) {
            throw new RuntimeException('Plus de bloc libre sous 1000 pour la catégorie « ' . $c['nom'] . ' »');
        }
        $maj->execute([':c' => $code, ':id' => (int) $c['id']]);
        printf("  catégorie %-30s → %d (hors barème : bloc libre suivant)\n", $c['nom'], $code);
    }
    /* le barème doit rester unique */
    $dbl = $db->query("SELECT code, COUNT(*) n FROM categories WHERE code IS NOT NULL AND sync_deleted_at IS NULL GROUP BY code HAVING n > 1")->fetchAll(PDO::FETCH_ASSOC);
    if ($dbl !== []) {
        throw new RuntimeException('Deux catégories portent le même code : ' . json_encode($dbl));
    }

    /* 3. les sous-catégories */
    $total_sc = 0;
    foreach ($db->query("SELECT id, nom, code FROM categories WHERE code IS NOT NULL AND sync_deleted_at IS NULL ORDER BY code") as $c) {
        $n = sous_categories_semer_codes((int) $c['id']);
        if ($n < 0) {
            throw new RuntimeException('Le bloc de « ' . $c['nom'] . ' » (' . $c['code'] . ') est plein');
        }
        $total_sc += $n;
    }
    echo "Sous-catégories numérotées : $total_sc\n";

    /* 4. les marques */
    $cumins = $db->query("SELECT id FROM marques WHERE UPPER(nom) = 'CUMINS' AND sync_deleted_at IS NULL")->fetchColumn();
    $cummins = $db->query("SELECT id FROM marques WHERE UPPER(nom) = 'CUMMINS' AND sync_deleted_at IS NULL")->fetchColumn();
    if ($cumins && $cummins && (int) $cumins !== (int) $cummins) {
        $n = $db->prepare("UPDATE produits SET marque_id = :b WHERE marque_id = :a");
        $n->execute([':b' => (int) $cummins, ':a' => (int) $cumins]);
        $db->prepare("UPDATE marques SET sync_deleted_at = NOW(), sync_updated_at = NOW() WHERE id = :id")->execute([':id' => (int) $cumins]);
        echo "Marque CUMINS fusionnée dans CUMMINS (", $n->rowCount(), " pièce(s) déplacée(s))\n";
    }
    /* une marque de la liste de la direction retirée par erreur (passage précédent) revient */
    $liste_dir = reference_fpl_marques_direction();
    $revenues = [];
    foreach ($db->query("SELECT id, nom FROM marques WHERE sync_deleted_at IS NOT NULL") as $m) {
        if (in_array(rf_normaliser_nom($m['nom']), $liste_dir, true)) {
            $db->prepare("UPDATE marques SET sync_deleted_at = NULL, sync_updated_at = NOW() WHERE id = :id")->execute([':id' => (int) $m['id']]);
            $revenues[] = $m['nom'];
        }
    }
    if ($revenues !== []) {
        echo 'Marques de la liste de la direction restaurées : ', implode(', ', $revenues), "\n";
    }
    $retirees = [];
    foreach ($db->query("SELECT m.id, m.nom,
                                (SELECT COUNT(*) FROM produits p WHERE p.marque_id = m.id) np,
                                (SELECT COUNT(*) FROM vehicule_modeles v WHERE v.marque_id = m.id) nv
                           FROM marques m WHERE m.sync_deleted_at IS NULL") as $m) {
        if ((int) $m['np'] === 0 && (int) $m['nv'] === 0 && !in_array(rf_normaliser_nom($m['nom']), $liste_dir, true)) {
            $db->prepare("UPDATE marques SET sync_deleted_at = NOW(), sync_updated_at = NOW() WHERE id = :id")->execute([':id' => (int) $m['id']]);
            $retirees[] = $m['nom'];
        }
    }
    echo 'Marques sans aucune donnée retirées (', count($retirees), ') : ', $retirees === [] ? '—' : implode(', ', $retirees), "\n";
    $maj = $db->prepare("UPDATE marques SET abreviation = :a WHERE id = :id");
    $na = 0;
    foreach ($db->query("SELECT id, nom, abreviation FROM marques WHERE sync_deleted_at IS NULL ORDER BY nom") as $m) {
        if (trim((string) $m['abreviation']) !== '') {
            continue;
        }
        $a = marque_abreviation_proposer($m['nom'], (int) $m['id']);
        $maj->execute([':a' => $a, ':id' => (int) $m['id']]);
        $na++;
    }
    echo "Abréviations posées : $na\n";

    /* 5. les OEM enfouis dans les descriptions */
    $noem = produits_oem_recuperer_des_descriptions();
    echo "Références OEM récupérées depuis les descriptions : $noem\n";

    /* 6. les références */
    $s = produits_reference_fpl_recalculer_tout();
    printf("Références FPL : %d pièces, %d posées, %d changées, %d sans code de catégorie, %d sans OEM (repli identifiant), %d sans marque (XXX)\n",
        $s['total'], $s['posees'], $s['changees'], $s['sans_code'], $s['oem_manquant'], $s['marque_manquante']);
    if ($s['sans_code'] > 0) {
        throw new RuntimeException($s['sans_code'] . ' pièce(s) dans une catégorie sans code');
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'ÉCHEC (rien n\'est modifié hors schéma) : ' . $e->getMessage() . "\n");
    exit(1);
}

/* 7. le bilan */
echo "\nCatégories et leurs blocs :\n";
foreach ($db->query("SELECT c.code, c.nom, (SELECT COUNT(*) FROM sous_categories s WHERE s.categorie_id = c.id AND s.sync_deleted_at IS NULL) n,
                            (SELECT MAX(code) FROM sous_categories s WHERE s.categorie_id = c.id AND s.sync_deleted_at IS NULL) mx
                       FROM categories c WHERE c.sync_deleted_at IS NULL ORDER BY c.code") as $c) {
    printf("  %3d  %-30s %2d sous-catégorie(s), jusqu'à %s\n", $c['code'], $c['nom'], $c['n'], $c['mx'] ?: '—');
}
$doublons = $db->query("SELECT reference_fpl, COUNT(*) n FROM produits WHERE sync_deleted_at IS NULL AND reference_fpl IS NOT NULL GROUP BY reference_fpl HAVING n > 1")->fetchAll(PDO::FETCH_ASSOC);
echo 'Références en double : ', count($doublons), ($doublons !== [] ? ' (ex. ' . $doublons[0]['reference_fpl'] . ' × ' . $doublons[0]['n'] . ')' : ''), "\n";
echo 'Exemples : ';
foreach ($db->query("SELECT reference_fpl FROM produits WHERE reference_fpl IS NOT NULL AND reference_oem <> '' AND marque_id IS NOT NULL ORDER BY id DESC LIMIT 3") as $r) {
    echo $r['reference_fpl'], ' ';
}
echo "\nMigration référence FPL : OK\n";
