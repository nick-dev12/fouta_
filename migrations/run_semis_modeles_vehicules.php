<?php
/**
 * SEMIS DES MODÈLES DE VÉHICULES (02/09/2026) — le fichier Excel de la
 * direction « marques_classees_par_nationalite_sans_sinotruk.xlsx » donne,
 * marque par marque, les gammes/séries qui deviennent les MODÈLES de
 * l'étape 1 du wizard (table vehicule_modeles, jusqu'ici vide en prod).
 *
 * Les intitulés sont ceux du fichier, à la lettre. Six marques du fichier
 * manquaient à la table marques (FAW, ROR, TRAILOR, YORK, SMB, KASSBOHRER) :
 * sur ordre de la direction du 02/09 (« remplis les marques et les modèles »),
 * la migration les CRÉE d'abord, puis sème leurs modèles. SMB et KASSBOHRER
 * n'ont pas de feuille de modèles dans le fichier : marque seule.
 *
 * Idempotent : une marque ou un modèle déjà présents (même nom, non
 * supprimés) ne sont pas reposés. Rejouable telle quelle en production.
 *   php migrations/run_semis_modeles_vehicules.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

/* --- 1. les marques du fichier absentes de la table --- */
$marques_a_creer = ['FAW', 'ROR', 'TRAILOR', 'YORK', 'SMB', 'KASSBOHRER'];
$marque_existe = $db->prepare('SELECT id FROM marques WHERE UPPER(TRIM(nom)) = UPPER(?) LIMIT 1');
$marque_creer = $db->prepare('INSERT INTO marques (nom, sync_uuid, sync_updated_at) VALUES (?, UUID(), NOW())');
foreach ($marques_a_creer as $nom_marque) {
    $marque_existe->execute([$nom_marque]);
    if ((int) $marque_existe->fetchColumn() > 0) {
        echo "  marque $nom_marque : déjà là\n";
        continue;
    }
    $marque_creer->execute([$nom_marque]);
    echo "  marque $nom_marque : CRÉÉE (#", (int) $db->lastInsertId(), ")\n";
}

$semis = [
    'BEIBEN' => ['V3', 'Beiben heavy truck', 'Spécialités'],
    'CAMC' => ['CAMC', 'H3', 'H5', 'Camions spécialisés'],
    'DAF' => ['Anciennes séries', '95', '95XF', 'XF'],
    'DEUTZ' => ['DEUTZ', 'TCD'],
    'DONGFENG' => ['EQ', 'D-series', 'KL', 'KX', 'KX / GX / D'],
    'FAW' => ['Jiefang anciennes séries', 'CA / J5', 'J6', 'JH6', 'JK6', 'J6P / JH6 / J7'],
    'FOTON' => ['Aumark', 'Auman', 'Auman EST', 'Auman Galaxy', 'Aumark / Tunland commercial'],
    'HINO' => ['Super Dolphin / Profia', 'Profia'],
    'HOWO' => ['HOWO', 'HOWO T7H', 'HOWO T5G', 'HOWO NX / MAX'],
    'ISUZU' => ['F-Series', 'Giga'],
    'IVECO' => ['Anciennes séries IVECO', 'TurboStar', 'TurboTech', 'EuroTech', 'EuroStar', 'Stralis', 'Stralis Hi-Way', 'Stralis Hi-Road', 'S-Way', 'X-Way'],
    'KAMAZ' => ['KAMAZ anciennes séries', 'KAMAZ', 'KAMAZ-Master'],
    'MAN' => ['G / M / F', 'F90', 'F2000', 'TGA', 'TGL', 'TGM', 'TGS', 'TGX', 'TGE'],
    'MERCEDES BENZ' => ['NG / SK', 'LN / LK', 'Actros', 'Axor', 'Atego', 'Econic', 'Arocs', 'Antos', 'Zetros', 'Actros F'],
    'PERKINS' => ['400 Series', '904 Series', '1100 Series', '1200 Series', '1500 Series', '1700 Series', '2200 Series', '2400 Series', '2500 Series', '2800 Series', '4000 / 5000 Series'],
    'ROR' => ['ROR', 'ROR / Meritor'],
    'RVI (RENAULT)' => ['Renault Véhicules Industriels', 'Manager', 'Major', 'Premium', 'Midlum', 'Magnum', 'Premium Route', 'T'],
    'SAF' => ['SAF', 'SAF INTRA', 'SAF-Holland'],
    'SHACMAN' => ['F2000', 'F3000', 'H3000', 'X3000', 'X5000', 'X6000', 'L3000'],
    'TATA' => ['Prima', 'Signa', 'Ultra', 'Ultra Sleek'],
    'TRAILOR' => ['Trailor'],
    'VOLVO' => ['F', 'FL / NL / N', 'FH', 'FM', 'FMX', 'FE', 'FL'],
    'WEICHAI' => ['WP', 'WP Engine for Truck', 'WP10 / WP12 / WP13', 'Nouvelle énergie'],
    'YORK' => ['York'],
];

$marque_id = $db->prepare('SELECT id FROM marques WHERE UPPER(TRIM(nom)) = UPPER(?) LIMIT 1');
$deja = $db->prepare('SELECT COUNT(*) FROM vehicule_modeles WHERE marque_id = ? AND UPPER(TRIM(nom)) = UPPER(?) AND sync_deleted_at IS NULL');
$poser = $db->prepare('INSERT INTO vehicule_modeles (marque_id, nom, sync_uuid, sync_updated_at) VALUES (?, ?, UUID(), NOW())');

$total_poses = 0;
$total_deja = 0;
$marques_absentes = [];
foreach ($semis as $marque => $modeles) {
    $marque_id->execute([$marque]);
    $mid = (int) $marque_id->fetchColumn();
    if ($mid <= 0) {
        $marques_absentes[] = $marque;
        continue;
    }
    $poses = 0;
    $en_place = 0;
    foreach ($modeles as $nom) {
        $deja->execute([$mid, $nom]);
        if ((int) $deja->fetchColumn() > 0) {
            $en_place++;
            continue;
        }
        $poser->execute([$mid, $nom]);
        $poses++;
    }
    $total_poses += $poses;
    $total_deja += $en_place;
    printf("  %-18s (#%d) : %d posé(s), %d déjà là\n", $marque, $mid, $poses, $en_place);
}

printf("TOTAL : %d modèle(s) posé(s), %d déjà en place\n", $total_poses, $total_deja);
if ($marques_absentes !== []) {
    echo 'ANOMALIE : marques toujours absentes malgré la création (modèles non semés) : ',
        implode(', ', $marques_absentes), "\n";
}

$relu = (int) $db->query('SELECT COUNT(*) FROM vehicule_modeles WHERE sync_deleted_at IS NULL')->fetchColumn();
echo 'relecture : ', $relu, " modèle(s) vivants dans vehicule_modeles\n";
