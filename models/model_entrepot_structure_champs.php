<?php
/**
 * Champs structurels dynamiques de l’entrepôt (colonnes nb_* sur entrepot_emplacement_etage).
 */
require_once __DIR__ . '/../conn/conn.php';

define('ENTREPOT_STRUCTURE_CHAMPS_MAX', 30);
define('ENTREPOT_STRUCTURE_CHAMP_LABEL_MAX', 100);

/**
 * Champs système avec référentiel nommé (sync dédiée).
 *
 * @return array<string, string> slug => clé sync
 */
function entrepot_structure_champs_systeme_referentiel_map() {
    return [
        'rayons' => 'rayons',
        'allees' => 'allees',
        'zones' => 'zones',
        'etageres' => 'etageres',
        'barres' => 'barres',
        'positions' => 'positions',
    ];
}

/**
 * Slug système → niveau hiérarchique CRUD.
 *
 * @return array<string, string>
 */
function entrepot_structure_champ_niveau_hierarchie_map() {
    return [
        'zones' => 'zone',
        'rayons' => 'rayon',
        'etageres' => 'etagere',
        'barres' => 'barre',
        'positions' => 'position',
    ];
}

/**
 * Normalisation canonique (minuscules, sans accents) pour reconnexion champs.
 * Règle : « teste 1 » ≈ « Teste 1 » ≈ « testé 1 » via translittération.
 *
 * @param string $label
 * @return string
 */
function entrepot_structure_champ_slug_canonique($label) {
    $label = trim((string) $label);
    if ($label === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if ($ascii !== false) {
            $label = $ascii;
        }
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $label));
    $slug = trim($slug, '_');
    if (strlen($slug) > 80) {
        $slug = substr($slug, 0, 80);
    }

    return $slug;
}

/**
 * @return bool
 */
function entrepot_structure_champ_archive_tables_ok() {
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT 1 FROM entrepot_structure_champ_archive LIMIT 1');

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Colonnes niveau_hierarchie / slug_canonique + table archive.
 */
function entrepot_structure_champ_ensure_hierarchie_schema() {
    global $db;
    if (!$db || !entrepot_structure_champs_tables_ok()) {
        return false;
    }
    if (entrepot_structure_champ_archive_tables_ok()) {
        try {
            $db->query('SELECT niveau_hierarchie FROM entrepot_structure_champ LIMIT 1');

            return true;
        } catch (PDOException $e) {
            // continue migration
        }
    }
    $runner = __DIR__ . '/../migrations/run_migrate_entrepot_hierarchie_crud.php';
    if (!is_file($runner)) {
        return false;
    }
    ob_start();
    include $runner;
    ob_end_clean();

    return entrepot_structure_champ_archive_tables_ok();
}

/**
 * @return bool
 */
function entrepot_structure_champs_tables_ok() {
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM entrepot_structure_champ LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * Crée la table registre si absente (auto-migration légère).
 *
 * @return bool
 */
function entrepot_structure_champs_ensure_table() {
    global $db;
    if (!$db) {
        return false;
    }
    if (entrepot_structure_champs_tables_ok()) {
        return true;
    }
    $sqlFile = __DIR__ . '/../migrations/create_entrepot_structure_champs.sql';
    if (!is_file($sqlFile)) {
        return false;
    }
    try {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            return false;
        }
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }
            $db->exec($statement);
        }
        entrepot_structure_champs_sync_colonnes_etage();

        return entrepot_structure_champs_tables_ok();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return bool
 */
function entrepot_emplacement_etage_table_ok() {
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT 1 FROM entrepot_emplacement_etage LIMIT 1');

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param string $colonne
 * @return bool
 */
function entrepot_structure_champ_colonne_existe($colonne) {
    global $db;
    if (!$db || !entrepot_emplacement_etage_table_ok()) {
        return false;
    }
    $colonne = entrepot_structure_champ_normaliser_colonne($colonne);
    if ($colonne === '') {
        return false;
    }
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tbl
               AND COLUMN_NAME = :col'
        );
        $stmt->execute([':tbl' => 'entrepot_emplacement_etage', ':col' => $colonne]);

        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param string $label
 * @return string
 */
function entrepot_structure_champ_slug_depuis_label($label) {
    $label = trim((string) $label);
    if ($label === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if ($ascii !== false) {
            $label = $ascii;
        }
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $label));
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'champ';
    }
    if (strlen($slug) > 40) {
        $slug = substr($slug, 0, 40);
    }

    return $slug;
}

/**
 * @param string $colonne
 * @return string
 */
function entrepot_structure_champ_normaliser_colonne($colonne) {
    $colonne = strtolower(trim((string) $colonne));
    if ($colonne === '') {
        return '';
    }
    if (strpos($colonne, 'nb_') !== 0) {
        $colonne = 'nb_' . $colonne;
    }
    if (!preg_match('/^nb_[a-z0-9_]{1,48}$/', $colonne)) {
        return '';
    }

    return $colonne;
}

/**
 * @return array<int, array<string, mixed>>
 */
function entrepot_structure_champs_fallback_defaut() {
    return [
        ['id' => 0, 'slug' => 'rayons', 'label' => 'Rayons', 'icon' => 'fa-th-large', 'colonne_db' => 'nb_rayons', 'ordre' => 10, 'est_systeme' => 1, 'max_valeur' => 500],
        ['id' => 0, 'slug' => 'allees', 'label' => 'Allées', 'icon' => 'fa-road', 'colonne_db' => 'nb_allees', 'ordre' => 20, 'est_systeme' => 1, 'max_valeur' => 50],
        ['id' => 0, 'slug' => 'zones', 'label' => 'Zones', 'icon' => 'fa-map-marker-alt', 'colonne_db' => 'nb_zones', 'ordre' => 30, 'est_systeme' => 1, 'max_valeur' => 50],
        ['id' => 0, 'slug' => 'etageres', 'label' => 'Étagères', 'icon' => 'fa-bars-staggered', 'colonne_db' => 'nb_etageres', 'ordre' => 35, 'est_systeme' => 1, 'max_valeur' => 50],
        ['id' => 0, 'slug' => 'positions', 'label' => 'Positions', 'icon' => 'fa-crosshairs', 'colonne_db' => 'nb_positions', 'ordre' => 40, 'est_systeme' => 1, 'max_valeur' => 50],
        ['id' => 0, 'slug' => 'barres', 'label' => 'Barres / rayon', 'icon' => 'fa-grip-lines', 'colonne_db' => 'nb_barres', 'ordre' => 50, 'est_systeme' => 1, 'max_valeur' => 50],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function entrepot_structure_champs_list() {
    global $db;
    entrepot_structure_champs_ensure_table();
    if (!entrepot_structure_champs_tables_ok()) {
        return entrepot_structure_champs_fallback_defaut();
    }
    try {
        $stmt = $db->query(
            'SELECT id, slug, slug_canonique, label, icon, colonne_db, ordre, est_systeme, lie_barre, niveau_hierarchie, max_valeur, date_creation
             FROM entrepot_structure_champ
             ORDER BY ordre ASC, id ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows !== [] ? $rows : entrepot_structure_champs_fallback_defaut();
    } catch (PDOException $e) {
        return entrepot_structure_champs_fallback_defaut();
    }
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function entrepot_structure_champ_get($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !entrepot_structure_champs_tables_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM entrepot_structure_champ WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param string $slug
 * @return array<string, mixed>|null
 */
function entrepot_structure_champ_get_by_slug($slug) {
    global $db;
    $slug = trim((string) $slug);
    if ($slug === '' || !entrepot_structure_champs_tables_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM entrepot_structure_champ WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @return bool
 */
function entrepot_structure_champ_slug_actif($slug) {
    return entrepot_structure_champ_get_by_slug($slug) !== null;
}

/**
 * Synchronise les colonnes nb_* sur entrepot_emplacement_etage avec le registre.
 */
function entrepot_structure_champs_sync_colonnes_etage() {
    global $db;
    if (!$db || !entrepot_emplacement_etage_table_ok() || !entrepot_structure_champs_tables_ok()) {
        return;
    }
    foreach (entrepot_structure_champs_list() as $champ) {
        $col = (string) ($champ['colonne_db'] ?? '');
        if ($col === '' || entrepot_structure_champ_colonne_existe($col)) {
            continue;
        }
        $max = max(1, (int) ($champ['max_valeur'] ?? 50));
        $type = $max > 255 ? 'SMALLINT UNSIGNED' : 'TINYINT UNSIGNED';
        try {
            $db->exec(
                'ALTER TABLE `entrepot_emplacement_etage`
                 ADD COLUMN `' . str_replace('`', '', $col) . '` ' . $type . ' NOT NULL DEFAULT 10'
            );
        } catch (PDOException $e) {
            // colonne peut exister sans être détectée selon le schéma
        }
    }
}

/**
 * @param string $colonne
 * @param int $default
 * @return array{success: bool, message: string}
 */
function entrepot_structure_champ_ajouter_colonne_etage($colonne, $default = 10) {
    global $db;
    $colonne = entrepot_structure_champ_normaliser_colonne($colonne);
    if ($colonne === '' || !$db || !entrepot_emplacement_etage_table_ok()) {
        return ['success' => false, 'message' => 'Colonne invalide ou table étage absente.'];
    }
    if (entrepot_structure_champ_colonne_existe($colonne)) {
        return ['success' => true, 'message' => 'Colonne déjà présente.'];
    }
    $default = max(1, (int) $default);
    $type = $default > 255 ? 'SMALLINT UNSIGNED' : 'TINYINT UNSIGNED';
    try {
        $db->exec(
            'ALTER TABLE `entrepot_emplacement_etage`
             ADD COLUMN `' . str_replace('`', '', $colonne) . '` ' . $type . ' NOT NULL DEFAULT ' . $default
        );

        return ['success' => true, 'message' => 'Colonne ' . $colonne . ' créée.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur ALTER TABLE ADD : ' . $e->getMessage()];
    }
}

/**
 * @param string $colonne
 * @return array{success: bool, message: string}
 */
function entrepot_structure_champ_supprimer_colonne_etage($colonne) {
    global $db;
    $colonne = entrepot_structure_champ_normaliser_colonne($colonne);
    if ($colonne === '' || !$db || !entrepot_emplacement_etage_table_ok()) {
        return ['success' => false, 'message' => 'Colonne invalide.'];
    }
    if (!entrepot_structure_champ_colonne_existe($colonne)) {
        return ['success' => true, 'message' => 'Colonne déjà absente.'];
    }
    try {
        $db->exec(
            'ALTER TABLE `entrepot_emplacement_etage` DROP COLUMN `' . str_replace('`', '', $colonne) . '`'
        );

        return ['success' => true, 'message' => 'Colonne ' . $colonne . ' supprimée.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur ALTER TABLE DROP : ' . $e->getMessage()];
    }
}

/**
 * @param string $label
 * @param string $icon
 * @param int $max_valeur
 * @param int $default_valeur
 * @return array{success: bool, message: string, champ?: array<string, mixed>}
 */
function entrepot_structure_champ_ajouter($label, $icon = 'fa-cube', $max_valeur = 50, $default_valeur = 10, $lie_barre = false, $niveau_hierarchie = null) {
    global $db;
    if (!$db) {
        return ['success' => false, 'message' => 'Connexion BDD indisponible.'];
    }
    if (!entrepot_structure_champs_ensure_table()) {
        return ['success' => false, 'message' => 'Impossible d’initialiser le registre des champs.'];
    }
    entrepot_structure_champ_ensure_hierarchie_schema();
    if (!entrepot_emplacement_etage_table_ok()) {
        return ['success' => false, 'message' => 'Table entrepot_emplacement_etage absente — exécutez la migration entrepôt.'];
    }

    $label = trim((string) $label);
    if ($label === '') {
        return ['success' => false, 'message' => 'Le libellé du champ est obligatoire.'];
    }
    if (function_exists('mb_strlen') && mb_strlen($label, 'UTF-8') > ENTREPOT_STRUCTURE_CHAMP_LABEL_MAX) {
        return ['success' => false, 'message' => 'Libellé trop long (max ' . ENTREPOT_STRUCTURE_CHAMP_LABEL_MAX . ' caractères).'];
    }

    $count = (int) $db->query('SELECT COUNT(*) FROM entrepot_structure_champ')->fetchColumn();
    if ($count >= ENTREPOT_STRUCTURE_CHAMPS_MAX) {
        return ['success' => false, 'message' => 'Limite de ' . ENTREPOT_STRUCTURE_CHAMPS_MAX . ' champs structurels atteinte.'];
    }

    $slug_base = entrepot_structure_champ_slug_depuis_label($label);
    $slug = $slug_base;
    $suffix = 1;
    while (entrepot_structure_champ_get_by_slug($slug) !== null) {
        $suffix++;
        $slug = $slug_base . '_' . $suffix;
    }

    $colonne = entrepot_structure_champ_normaliser_colonne($slug);
    if ($colonne === '') {
        return ['success' => false, 'message' => 'Impossible de générer un identifiant de colonne valide.'];
    }

    $max_valeur = max(1, min(500, (int) $max_valeur));
    $default_valeur = max(1, min($max_valeur, (int) $default_valeur));

    $icon = trim((string) $icon);
    if ($icon === '' || !preg_match('/^fa-[a-z0-9-]+$/', $icon)) {
        $icon = 'fa-cube';
    }

    $ordre = (int) $db->query('SELECT COALESCE(MAX(ordre), 0) FROM entrepot_structure_champ')->fetchColumn();
    $ordre += 10;

    entrepot_structure_champ_ensure_lie_barre_schema();
    $lie_barre = $lie_barre ? 1 : 0;
    $slug_canonique = entrepot_structure_champ_slug_canonique($label);
    $niveaux_valides = ['zone', 'rayon', 'etagere', 'barre', 'position'];
    $niv = $niveau_hierarchie !== null ? trim((string) $niveau_hierarchie) : '';
    if ($niv !== '' && !in_array($niv, $niveaux_valides, true)) {
        $niv = 'rayon';
    }
    if ($niv === '' && $lie_barre === 1) {
        $niv = 'etagere';
    }

    $archive = entrepot_structure_champ_reconnecter($slug_canonique, $label, $icon, $colonne, $ordre, $lie_barre, $max_valeur, $niv);
    if ($archive !== null) {
        return $archive;
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO entrepot_structure_champ (slug, slug_canonique, label, icon, colonne_db, ordre, est_systeme, lie_barre, niveau_hierarchie, max_valeur, date_creation)
             VALUES (:slug, :canon, :label, :icon, :colonne, :ordre, 0, :lie_barre, :niv, :max_v, NOW())'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':canon' => $slug_canonique,
            ':label' => $label,
            ':icon' => $icon,
            ':colonne' => $colonne,
            ':ordre' => $ordre,
            ':lie_barre' => $lie_barre,
            ':niv' => $niv !== '' ? $niv : null,
            ':max_v' => $max_valeur,
        ]);
        $id = (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }

    if ($lie_barre === 1) {
        entrepot_structure_champ_definir_lie_barre($id);
    }

    // ALTER TABLE provoque un commit implicite MySQL — ne pas utiliser de transaction ici.
    $col_res = entrepot_structure_champ_ajouter_colonne_etage($colonne, $default_valeur);
    if (!$col_res['success']) {
        try {
            $db->prepare('DELETE FROM entrepot_structure_champ WHERE id = :id')->execute([':id' => $id]);
        } catch (PDOException $e) {
            // ignore cleanup error
        }

        return $col_res;
    }

    $champ = entrepot_structure_champ_get($id);

    return [
        'success' => true,
        'message' => 'Champ « ' . $label . ' » ajouté (colonne ' . $colonne . ').',
        'champ' => $champ,
    ];
}

/**
 * @param array<string, mixed> $champ
 * @return string
 */
function entrepot_structure_champ_resoudre_niveau_hierarchie($champ) {
    if (!is_array($champ)) {
        return '';
    }
    $niv = trim((string) ($champ['niveau_hierarchie'] ?? ''));
    if ($niv !== '') {
        return $niv;
    }
    $slug = (string) ($champ['slug'] ?? '');
    $map = entrepot_structure_champ_niveau_hierarchie_map();

    return (string) ($map[$slug] ?? '');
}

/**
 * Niveaux hiérarchiques CRUD encore actifs (registre des champs).
 *
 * @return array<string, array<string, mixed>>
 */
function entrepot_hierarchie_niveaux_actifs() {
    $ordre = ['zone', 'rayon', 'etagere', 'barre', 'position'];
    $out = [];
    foreach (entrepot_structure_champs_list() as $champ) {
        $niv = entrepot_structure_champ_resoudre_niveau_hierarchie($champ);
        if ($niv === '' || isset($out[$niv])) {
            continue;
        }
        $out[$niv] = [
            'niveau' => $niv,
            'label' => (string) ($champ['label'] ?? ucfirst($niv)),
            'icon' => (string) ($champ['icon'] ?? 'fa-cube'),
            'champ_id' => (int) ($champ['id'] ?? 0),
            'slug' => (string) ($champ['slug'] ?? ''),
        ];
    }
    $sorted = [];
    foreach ($ordre as $key) {
        if (isset($out[$key])) {
            $sorted[$key] = $out[$key];
        }
    }

    return $sorted;
}

/**
 * @param string $niveau
 * @return bool
 */
function entrepot_hierarchie_niveau_est_actif($niveau) {
    $niveau = trim((string) $niveau);
    $actifs = entrepot_hierarchie_niveaux_actifs();

    return isset($actifs[$niveau]);
}

/**
 * @param string $niveau
 * @return string
 */
function entrepot_hierarchie_table_pour_niveau($niveau) {
    $map = [
        'zone' => 'entrepot_zone',
        'rayon' => 'entrepot_rayon',
        'etagere' => 'entrepot_etagere',
        'barre' => 'entrepot_barre',
        'position' => 'entrepot_position',
    ];

    return (string) ($map[$niveau] ?? '');
}

/**
 * @param string $niveau
 * @return array<int, string>
 */
function entrepot_hierarchie_niveaux_a_purger_depuis($niveau) {
    $ordre = ['zone', 'rayon', 'etagere', 'barre', 'position'];
    $idx = array_search($niveau, $ordre, true);
    if ($idx === false) {
        return [];
    }

    return array_slice($ordre, (int) $idx);
}

/**
 * @param array<int, string> $niveaux
 * @return int
 */
function entrepot_hierarchie_compter_produits_lies_niveaux(array $niveaux) {
    global $db;
    if (!$db || $niveaux === [] || !function_exists('produits_has_column') || !produits_has_column('entrepot_position_id')) {
        return 0;
    }
    if (!in_array('position', $niveaux, true)) {
        return 0;
    }
    $min = (string) ($niveaux[0] ?? '');
    try {
        if ($min === 'position' && count($niveaux) === 1) {
            return (int) $db->query(
                'SELECT COUNT(*) FROM produits WHERE entrepot_position_id IS NOT NULL AND entrepot_position_id > 0'
            )->fetchColumn();
        }
        $db->query('SELECT 1 FROM entrepot_position LIMIT 1');
        $db->query('SELECT 1 FROM entrepot_barre LIMIT 1');
        $filtres = [
            'zone' => 'b.zone_id IS NOT NULL AND b.zone_id > 0',
            'rayon' => 'b.rayon_id IS NOT NULL AND b.rayon_id > 0',
            'etagere' => 'b.etagere_id IS NOT NULL AND b.etagere_id > 0',
            'barre' => '1=1',
        ];
        $filtre = $filtres[$min] ?? '1=1';
        $sql = 'SELECT COUNT(DISTINCT pr.id)
                FROM produits pr
                INNER JOIN entrepot_position p ON p.id = pr.entrepot_position_id
                INNER JOIN entrepot_barre b ON b.id = p.barre_id
                WHERE pr.entrepot_position_id IS NOT NULL AND pr.entrepot_position_id > 0';
        if ($filtre !== '1=1') {
            $sql .= ' AND ' . $filtre;
        }

        return (int) $db->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param array<int, string> $niveaux
 * @return int
 */
function entrepot_hierarchie_detacher_produits_niveaux(array $niveaux) {
    global $db;
    if (!$db || $niveaux === [] || !function_exists('produits_has_column') || !produits_has_column('entrepot_position_id')) {
        return 0;
    }
    if (!in_array('position', $niveaux, true)) {
        return 0;
    }
    $count = entrepot_hierarchie_compter_produits_lies_niveaux($niveaux);
    if ($count <= 0) {
        return 0;
    }
    $min = (string) ($niveaux[0] ?? '');
    try {
        if ($min === 'position' && count($niveaux) === 1) {
            $db->exec('UPDATE produits SET entrepot_position_id = NULL WHERE entrepot_position_id IS NOT NULL AND entrepot_position_id > 0');
        } else {
            $filtres = [
                'zone' => 'b.zone_id IS NOT NULL AND b.zone_id > 0',
                'rayon' => 'b.rayon_id IS NOT NULL AND b.rayon_id > 0',
                'etagere' => 'b.etagere_id IS NOT NULL AND b.etagere_id > 0',
                'barre' => '1=1',
            ];
            $filtre = $filtres[$min] ?? '1=1';
            $sql = 'UPDATE produits pr
                    INNER JOIN entrepot_position p ON p.id = pr.entrepot_position_id
                    INNER JOIN entrepot_barre b ON b.id = p.barre_id
                    SET pr.entrepot_position_id = NULL
                    WHERE pr.entrepot_position_id IS NOT NULL AND pr.entrepot_position_id > 0';
            if ($filtre !== '1=1') {
                $sql .= ' AND ' . $filtre;
            }
            $db->exec($sql);
        }

        return $count;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param array<int, string> $niveaux
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_purger_niveaux(array $niveaux) {
    global $db;
    if (!$db || $niveaux === []) {
        return ['success' => true, 'message' => ''];
    }
    entrepot_hierarchie_detacher_produits_niveaux($niveaux);
    $delete_order = array_reverse($niveaux);
    foreach ($delete_order as $niv) {
        $table = entrepot_hierarchie_table_pour_niveau($niv);
        if ($table === '') {
            continue;
        }
        try {
            $db->exec('DELETE FROM `' . $table . '`');
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur purge ' . $niv . ' : ' . $e->getMessage()];
        }
    }

    return ['success' => true, 'message' => ''];
}

/**
 * @param int $champ_id
 * @return int
 */
function entrepot_structure_champ_compter_elements($champ_id) {
    global $db;
    $champ_id = (int) $champ_id;
    if ($champ_id <= 0 || !entrepot_champ_element_tables_ok()) {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM entrepot_champ_element WHERE champ_id = :c');
        $st->execute([':c' => $champ_id]);

        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Analyse d’impact avant suppression d’un champ structurel.
 *
 * @param int $id
 * @return array<string, mixed>|null
 */
function entrepot_structure_champ_impact_suppression($id) {
    global $db;
    $id = (int) $id;
    $champ = entrepot_structure_champ_get($id);
    if ($champ === null) {
        return null;
    }

    $label = (string) ($champ['label'] ?? '');
    $colonne = (string) ($champ['colonne_db'] ?? '');
    $niveau = entrepot_structure_champ_resoudre_niveau_hierarchie($champ);
    $niveaux_purge = $niveau !== '' ? entrepot_hierarchie_niveaux_a_purger_depuis($niveau) : [];

    $labels_niveau = [
        'zone' => 'Zones',
        'rayon' => 'Rayons',
        'etagere' => 'Étagères',
        'barre' => 'Barres',
        'position' => 'Positions',
    ];

    $entites = [];
    foreach ($niveaux_purge as $niv) {
        $table = entrepot_hierarchie_table_pour_niveau($niv);
        $count = 0;
        if ($table !== '' && $db) {
            try {
                $count = (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            } catch (PDOException $e) {
                $count = 0;
            }
        }
        if ($count > 0) {
            $entites[] = [
                'niveau' => $niv,
                'label' => $labels_niveau[$niv] ?? ucfirst($niv),
                'count' => $count,
            ];
        }
    }

    $elements_champ = 0;
    if (entrepot_champ_element_tables_ok()) {
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM entrepot_champ_element WHERE champ_id = :c');
            $st->execute([':c' => $id]);
            $elements_champ = (int) $st->fetchColumn();
        } catch (PDOException $e) {
            $elements_champ = 0;
        }
    }

    $barres_liees = 0;
    if ((int) ($champ['lie_barre'] ?? 0) === 1 && entrepot_champ_element_tables_ok()) {
        try {
            $db->query('SELECT champ_element_id FROM entrepot_barre LIMIT 1');
            $st = $db->prepare(
                'SELECT COUNT(*) FROM entrepot_barre b
                 INNER JOIN entrepot_champ_element e ON e.id = b.champ_element_id
                 WHERE e.champ_id = :c'
            );
            $st->execute([':c' => $id]);
            $barres_liees = (int) $st->fetchColumn();
        } catch (PDOException $e) {
            $barres_liees = 0;
        }
    }

    $produits_lies = entrepot_hierarchie_compter_produits_lies_niveaux($niveaux_purge);

    $avertissements = [];
    if ($niveau !== '') {
        $avertissements[] = 'Le niveau hiérarchique « ' . ($labels_niveau[$niveau] ?? $niveau) . ' » sera retiré de la barre d’outils et de la carte du niveau.';
        if ($entites !== []) {
            $avertissements[] = 'Toutes les entités liées à ce champ seront supprimées définitivement.';
        }
    } else {
        $avertissements[] = 'La colonne de configuration « ' . $colonne . ' » sera retirée des niveaux entrepôt.';
    }
    if ($elements_champ > 0) {
        $avertissements[] = $elements_champ . ' élément(s) nommé(s) sur les niveaux seront supprimés.';
    }
    if ($produits_lies > 0) {
        $avertissements[] = $produits_lies . ' produit(s) perdront leur emplacement assigné (référence position effacée).';
    }

    return [
        'champ_id' => $id,
        'label' => $label,
        'colonne_db' => $colonne,
        'niveau_hierarchie' => $niveau,
        'niveau_label' => $niveau !== '' ? ($labels_niveau[$niveau] ?? $niveau) : '',
        'entites' => $entites,
        'elements_champ' => $elements_champ,
        'barres_liees' => $barres_liees,
        'produits_lies' => $produits_lies,
        'avertissements' => $avertissements,
        'est_systeme' => (int) ($champ['est_systeme'] ?? 0) === 1,
    ];
}

/**
 * @param array<string, mixed> $champ
 * @return array{success: bool, message: string}
 */
function entrepot_structure_champ_purger_donnees_liees($champ) {
    global $db;
    if (!is_array($champ) || !$db) {
        return ['success' => false, 'message' => 'Champ invalide.'];
    }
    $champ_id = (int) ($champ['id'] ?? 0);
    $niveau = entrepot_structure_champ_resoudre_niveau_hierarchie($champ);
    if ($niveau !== '') {
        $niveaux = entrepot_hierarchie_niveaux_a_purger_depuis($niveau);
        $purge = entrepot_hierarchie_purger_niveaux($niveaux);
        if (!$purge['success']) {
            return $purge;
        }
    }
    if ($champ_id > 0 && entrepot_champ_element_tables_ok()) {
        try {
            if ((int) ($champ['lie_barre'] ?? 0) === 1) {
                try {
                    $db->query('SELECT champ_element_id FROM entrepot_barre LIMIT 1');
                    $db->prepare(
                        'UPDATE entrepot_barre b
                         INNER JOIN entrepot_champ_element e ON e.id = b.champ_element_id
                         SET b.champ_element_id = NULL
                         WHERE e.champ_id = :c'
                    )->execute([':c' => $champ_id]);
                } catch (PDOException $e) {
                    // ignore
                }
            }
            $db->prepare('DELETE FROM entrepot_champ_element WHERE champ_id = :c')->execute([':c' => $champ_id]);
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur purge éléments : ' . $e->getMessage()];
        }
    }

    return ['success' => true, 'message' => ''];
}

/**
 * @param int $id
 * @return array{success: bool, message: string}
 */
function entrepot_structure_champ_supprimer($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !entrepot_structure_champs_tables_ok()) {
        return ['success' => false, 'message' => 'Champ introuvable.'];
    }

    $champ = entrepot_structure_champ_get($id);
    if ($champ === null) {
        return ['success' => false, 'message' => 'Champ introuvable.'];
    }

    $total = (int) $db->query('SELECT COUNT(*) FROM entrepot_structure_champ')->fetchColumn();
    if ($total <= 1) {
        return ['success' => false, 'message' => 'Impossible de supprimer le dernier champ structurel.'];
    }

    $colonne = (string) ($champ['colonne_db'] ?? '');
    $label = (string) ($champ['label'] ?? $colonne);
    $niveau = entrepot_structure_champ_resoudre_niveau_hierarchie($champ);

    $purge = entrepot_structure_champ_purger_donnees_liees($champ);
    if (!$purge['success']) {
        return $purge;
    }

    entrepot_structure_champ_ensure_hierarchie_schema();
    if (entrepot_structure_champ_archive_tables_ok()) {
        $canon = (string) ($champ['slug_canonique'] ?? entrepot_structure_champ_slug_canonique($label));
        try {
            $db->prepare(
                'INSERT INTO entrepot_structure_champ_archive
                 (champ_id_origine, slug, slug_canonique, label, icon, colonne_db, niveau_hierarchie, lie_barre, max_valeur, config_json, date_archivage)
                 VALUES (:cid, :slug, :canon, :label, :icon, :col, :niv, :lie, :max, :json, NOW())'
            )->execute([
                ':cid' => $id,
                ':slug' => (string) ($champ['slug'] ?? ''),
                ':canon' => $canon,
                ':label' => $label,
                ':icon' => (string) ($champ['icon'] ?? 'fa-cube'),
                ':col' => $colonne,
                ':niv' => ($champ['niveau_hierarchie'] ?? null) ?: null,
                ':lie' => (int) ($champ['lie_barre'] ?? 0),
                ':max' => (int) ($champ['max_valeur'] ?? 50),
                ':json' => json_encode($champ, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $e) {
            // archive optionnelle
        }
    }

    // ALTER TABLE provoque un commit implicite — supprimer la colonne avant le registre.
    if ($colonne !== '') {
        $drop = entrepot_structure_champ_supprimer_colonne_etage($colonne);
        if (!$drop['success']) {
            return $drop;
        }
    }

    try {
        $db->prepare('DELETE FROM entrepot_structure_champ WHERE id = :id')->execute([':id' => $id]);
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }

    return [
        'success' => true,
        'message' => 'Champ « ' . $label . ' » supprimé. Les boutons et sections liés ont été retirés de l’interface.'
            . ($niveau !== '' ? ' Données hiérarchiques associées purgées.' : '')
            . ($colonne !== '' ? ' Colonne ' . $colonne . ' retirée.' : ''),
    ];
}

/**
 * Colonnes SELECT pour entrepot_emplacement_etage (hors id, numero_etage, date_modification).
 *
 * @return array<int, string>
 */
function entrepot_structure_champs_colonnes_db() {
    $cols = [];
    foreach (entrepot_structure_champs_list() as $champ) {
        $col = (string) ($champ['colonne_db'] ?? '');
        if ($col !== '' && entrepot_structure_champ_colonne_existe($col)) {
            $cols[] = $col;
        }
    }

    return $cols;
}

/**
 * Libellé du champ lié aux barres (lie_barre), ou null.
 *
 * @return string|null
 */
function entrepot_structure_champ_get_lie_barre_label() {
    $lie = entrepot_structure_champ_get_lie_barre();
    if ($lie === null) {
        return null;
    }
    $label = trim((string) ($lie['label'] ?? ''));
    if ($label === '') {
        return null;
    }

    return $label;
}

/**
 * Libellé affiché pour nb_barres (dynamique si un champ lie_barre est défini).
 *
 * @return string
 */
function entrepot_structure_champ_label_nb_barres() {
    $lie_label = entrepot_structure_champ_get_lie_barre_label();
    if ($lie_label !== null) {
        return 'Barres par ' . $lie_label;
    }
    foreach (entrepot_structure_champs_list() as $ch) {
        if ((string) ($ch['slug'] ?? '') === 'barres') {
            return (string) ($ch['label'] ?? 'Barres / rayon');
        }
    }

    return 'Barres / rayon';
}

/**
 * Pour formulaires admin (table, modals, page étage).
 *
 * @return array<int, array<string, mixed>>
 */
function entrepot_structure_champs_pour_formulaire() {
    entrepot_structure_champ_ensure_lie_barre_schema();
    $lie_label = entrepot_structure_champ_get_lie_barre_label();
    $out = [];
    foreach (entrepot_structure_champs_list() as $champ) {
        $col = (string) ($champ['colonne_db'] ?? '');
        if ($col === '' || !entrepot_structure_champ_colonne_existe($col)) {
            continue;
        }
        $slug = (string) ($champ['slug'] ?? '');
        $label = (string) ($champ['label'] ?? $col);
        $label_modal = null;
        if ($slug === 'barres') {
            $label = entrepot_structure_champ_label_nb_barres();
            $label_modal = 'Barres';
        }
        $entry = [
            'id' => (int) ($champ['id'] ?? 0),
            'slug' => $slug,
            'name' => $col,
            'label' => $label,
            'icon' => (string) ($champ['icon'] ?? 'fa-cube'),
            'max' => max(1, (int) ($champ['max_valeur'] ?? 50)),
            'est_systeme' => (int) ($champ['est_systeme'] ?? 0),
            'lie_barre' => (int) ($champ['lie_barre'] ?? 0),
        ];
        if ($label_modal !== null) {
            $entry['label_modal'] = $label_modal;
        }
        if ($slug === 'barres' && $lie_label !== null) {
            $entry['hint'] = 'Nombre de barres pour chaque « ' . $lie_label . ' ».';
        } elseif ((int) ($champ['lie_barre'] ?? 0) === 1) {
            $entry['hint'] = 'Nombre d’éléments « ' . $label . ' » sur cet étage (groupes de barres).';
        } elseif ((int) ($champ['est_systeme'] ?? 0) === 0) {
            $entry['hint'] = 'Nombre d’éléments « ' . $label . ' » nommables sur cet étage.';
        }
        $out[] = $entry;
    }

    return $out;
}

/**
 * @param array<string, mixed> $post
 * @return array<string, int>
 */
function entrepot_structure_champs_valeurs_depuis_post(array $post) {
    $out = [];
    foreach (entrepot_structure_champs_pour_formulaire() as $champ) {
        $name = (string) $champ['name'];
        if (!isset($post[$name])) {
            continue;
        }
        $val = (int) $post[$name];
        $max = (int) $champ['max'];
        if ($val < 1) {
            $val = 1;
        }
        if ($val > $max) {
            $val = $max;
        }
        $out[$name] = $val;
    }

    return $out;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, int>
 */
function entrepot_structure_champs_valeurs_depuis_ligne(array $row) {
    $out = [];
    foreach (entrepot_structure_champs_pour_formulaire() as $champ) {
        $name = (string) $champ['name'];
        $out[$name] = isset($row[$name]) ? max(1, (int) $row[$name]) : 10;
    }

    return $out;
}

/**
 * Valeurs par défaut pour tous les champs structurels actifs (ajout niveau, compatibilité nb_*).
 *
 * @return array<string, int>
 */
function entrepot_structure_champs_valeurs_defaut() {
    $legacy = [
        'nb_rayons' => 10,
        'nb_allees' => 10,
        'nb_zones' => 10,
        'nb_etageres' => 10,
        'nb_positions' => 10,
        'nb_barres' => 10,
    ];
    $out = [];
    foreach (entrepot_structure_champs_pour_formulaire() as $champ) {
        $name = (string) $champ['name'];
        $def = isset($legacy[$name]) ? (int) $legacy[$name] : 10;
        $max = max(1, (int) $champ['max']);
        $out[$name] = max(1, min($max, $def));
    }

    return $out;
}

/**
 * @param array<string, int> $valeurs colonne_db => int
 * @return array{success: bool, message: string}
 */
function entrepot_structure_champs_valider_valeurs(array $valeurs) {
    foreach (entrepot_structure_champs_pour_formulaire() as $champ) {
        $name = (string) $champ['name'];
        if (!isset($valeurs[$name])) {
            return ['success' => false, 'message' => 'Valeur manquante pour « ' . $champ['label'] . ' ».'];
        }
        $val = (int) $valeurs[$name];
        $max = (int) $champ['max'];
        if ($val < 1 || $val > $max) {
            return ['success' => false, 'message' => '« ' . $champ['label'] . ' » invalide (1 à ' . $max . ').'];
        }
    }

    return ['success' => true, 'message' => 'OK'];
}

/**
 * Encode les champs pour JSON côté client (aperçu DOM).
 *
 * @return array<int, array<string, mixed>>
 */
function entrepot_structure_champs_json_client() {
    return entrepot_structure_champs_pour_formulaire();
}

/**
 * Champs ajoutés manuellement (hors système rayons / allées / zones…).
 *
 * @return array<int, array<string, mixed>>
 */
function entrepot_structure_champs_custom_list() {
    $out = [];
    foreach (entrepot_structure_champs_list() as $champ) {
        if ((int) ($champ['est_systeme'] ?? 0) === 1) {
            continue;
        }
        $out[] = $champ;
    }

    return $out;
}

/**
 * @return bool
 */
function entrepot_champ_element_tables_ok() {
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM entrepot_champ_element LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * @return bool
 */
function entrepot_champ_element_ensure_table() {
    global $db;
    if (!$db) {
        return false;
    }
    if (entrepot_champ_element_tables_ok()) {
        return true;
    }
    $sqlFile = __DIR__ . '/../migrations/create_entrepot_champ_element.sql';
    if (!is_file($sqlFile)) {
        return false;
    }
    try {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            return false;
        }
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }
            $db->exec($statement);
        }

        return entrepot_champ_element_tables_ok();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Synchronise les éléments nommés des champs personnalisés pour un étage.
 *
 * @param PDO $db
 * @param int $etage_id
 * @param int $numero_etage
 */
function entrepot_sync_champs_custom_etage($db, $etage_id, $numero_etage) {
    entrepot_champ_element_ensure_table();
    if (!entrepot_champ_element_tables_ok()) {
        return;
    }

    $cfg = entrepot_emplacement_get_etage($numero_etage);
    if ($cfg === null) {
        return;
    }

    $etage_id = (int) $etage_id;
    $numero_etage = (int) $numero_etage;
    if ($etage_id <= 0 || $numero_etage <= 0) {
        return;
    }

    foreach (entrepot_structure_champs_custom_list() as $champ) {
        $champ_id = (int) ($champ['id'] ?? 0);
        $col = (string) ($champ['colonne_db'] ?? '');
        if ($champ_id <= 0 || $col === '' || !isset($cfg[$col])) {
            continue;
        }
        $count = max(0, (int) $cfg[$col]);
        $prefix = trim((string) ($champ['label'] ?? 'Élément'));
        if ($prefix === '') {
            $prefix = 'Élément';
        }

        $existing = [];
        $st = $db->prepare(
            'SELECT id, numero, nom FROM entrepot_champ_element WHERE etage_id = :e AND champ_id = :c ORDER BY numero ASC'
        );
        $st->execute([':e' => $etage_id, ':c' => $champ_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[(int) $row['numero']] = $row;
        }

        for ($i = 1; $i <= $count; $i++) {
            if (isset($existing[$i])) {
                continue;
            }
            $ins = $db->prepare(
                'INSERT INTO entrepot_champ_element (etage_id, champ_id, numero, nom, date_modification)
                 VALUES (:e, :c, :n, :nom, NOW())'
            );
            $ins->execute([
                ':e' => $etage_id,
                ':c' => $champ_id,
                ':n' => $i,
                ':nom' => $prefix . ' ' . $i,
            ]);
        }

        $db->prepare(
            'DELETE FROM entrepot_champ_element WHERE etage_id = :e AND champ_id = :c AND numero > :max'
        )->execute([':e' => $etage_id, ':c' => $champ_id, ':max' => $count]);
    }
}

/**
 * @param int $etage_id
 * @param int $champ_id
 * @return array<int, array<string, mixed>>
 */
function entrepot_get_champ_elements_etage($etage_id, $champ_id) {
    global $db;
    $etage_id = (int) $etage_id;
    $champ_id = (int) $champ_id;
    if ($etage_id <= 0 || $champ_id <= 0 || !entrepot_champ_element_tables_ok()) {
        return [];
    }
    try {
        $stmt = $db->prepare(
            'SELECT id, numero, nom FROM entrepot_champ_element
             WHERE etage_id = :e AND champ_id = :c ORDER BY numero ASC'
        );
        $stmt->execute([':e' => $etage_id, ':c' => $champ_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Panneaux UI (champs custom + éléments nommables) pour la page étage.
 *
 * @param int $numero_etage
 * @return array<int, array<string, mixed>>
 */
function entrepot_get_panels_champs_custom_etage($numero_etage) {
    entrepot_champ_element_ensure_table();
    if (!function_exists('entrepot_get_etage_ref_by_numero') || !entrepot_referentiel_tables_ok()) {
        return [];
    }

    $etage = entrepot_get_etage_ref_by_numero($numero_etage);
    if ($etage === null) {
        return [];
    }

    $etage_id = (int) $etage['id'];
    $cfg = entrepot_emplacement_get_etage($numero_etage);
    if ($cfg === null) {
        return [];
    }

    $panels = [];
    $lie_barre_id = 0;
    $lie = entrepot_structure_champ_get_lie_barre();
    if ($lie !== null) {
        $lie_barre_id = (int) ($lie['id'] ?? 0);
    }
    foreach (entrepot_structure_champs_custom_list() as $champ) {
        $champ_id = (int) ($champ['id'] ?? 0);
        if ($lie_barre_id > 0 && $champ_id === $lie_barre_id) {
            continue;
        }
        if ((int) ($champ['lie_barre'] ?? 0) === 1) {
            continue;
        }
        $col = (string) ($champ['colonne_db'] ?? '');
        if ($col === '' || !entrepot_structure_champ_colonne_existe($col)) {
            continue;
        }
        if ($champ_id <= 0) {
            continue;
        }
        $slug = (string) ($champ['slug'] ?? ('champ_' . $champ_id));
        $panels[] = [
            'champ_id' => $champ_id,
            'slug' => $slug,
            'label' => (string) ($champ['label'] ?? $slug),
            'icon' => (string) ($champ['icon'] ?? 'fa-cube'),
            'count' => max(0, (int) ($cfg[$col] ?? 0)),
            'elements' => entrepot_get_champ_elements_etage($etage_id, $champ_id),
        ];
    }

    return $panels;
}

/**
 * @param PDO $db
 * @param int $etage_id
 * @param array<int|string, mixed> $rows
 */
function entrepot_update_noms_champs_custom($db, $etage_id, array $rows) {
    if (!entrepot_champ_element_tables_ok()) {
        return;
    }
    $etage_id = (int) $etage_id;
    foreach ($rows as $champ_id => $elements) {
        if (!is_array($elements)) {
            continue;
        }
        $champ_id = (int) $champ_id;
        foreach ($elements as $el_id => $data) {
            if (!is_array($data)) {
                continue;
            }
            $el_id = (int) $el_id;
            $nom = trim((string) ($data['nom'] ?? ''));
            if ($el_id <= 0 || $champ_id <= 0 || $nom === '') {
                continue;
            }
            $db->prepare(
                'UPDATE entrepot_champ_element SET nom = :nom, date_modification = NOW()
                 WHERE id = :id AND etage_id = :e AND champ_id = :c'
            )->execute([':nom' => $nom, ':id' => $el_id, ':e' => $etage_id, ':c' => $champ_id]);
        }
    }
}

/**
 * Ajoute lie_barre sur entrepot_structure_champ si absent.
 */
function entrepot_structure_champ_ensure_lie_barre_schema() {
    global $db;
    if (!$db || !entrepot_structure_champs_tables_ok()) {
        return;
    }
    if (entrepot_structure_champ_colonne_registre_existe('lie_barre')) {
        return;
    }
    try {
        $db->exec(
            'ALTER TABLE `entrepot_structure_champ` ADD COLUMN `lie_barre` TINYINT(1) NOT NULL DEFAULT 0 AFTER `est_systeme`'
        );
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * @param string $colonne
 * @return bool
 */
function entrepot_structure_champ_colonne_registre_existe($colonne) {
    global $db;
    if (!$db || !entrepot_structure_champs_tables_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tbl
               AND COLUMN_NAME = :col'
        );
        $stmt->execute([':tbl' => 'entrepot_structure_champ', ':col' => $colonne]);

        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Ajoute champ_element_id sur entrepot_barre si absent.
 */
function entrepot_barre_ensure_champ_element_schema() {
    global $db;
    if (!$db) {
        return;
    }
    try {
        $db->query('SELECT champ_element_id FROM entrepot_barre LIMIT 1');
    } catch (PDOException $e) {
        try {
            $db->exec(
                'ALTER TABLE `entrepot_barre` ADD COLUMN `champ_element_id` INT UNSIGNED NULL DEFAULT NULL AFTER `zone_id`'
            );
        } catch (PDOException $e2) {
            // ignore
        }
    }
    entrepot_champ_element_ensure_table();
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl
               AND CONSTRAINT_NAME = :cn'
        );
        $stmt->execute([':tbl' => 'entrepot_barre', ':cn' => 'fk_entrepot_barre_champ_element']);
        if ((int) $stmt->fetchColumn() === 0 && entrepot_champ_element_tables_ok()) {
            $db->exec(
                'ALTER TABLE `entrepot_barre`
                 ADD CONSTRAINT `fk_entrepot_barre_champ_element`
                 FOREIGN KEY (`champ_element_id`) REFERENCES `entrepot_champ_element` (`id`)
                 ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    } catch (PDOException $e) {
        // ignore FK errors
    }
}

/**
 * Un seul champ custom peut être lié aux barres.
 *
 * @param int $champ_id
 * @return bool
 */
function entrepot_structure_champ_definir_lie_barre($champ_id) {
    global $db;
    $champ_id = (int) $champ_id;
    if ($champ_id <= 0 || !entrepot_structure_champs_tables_ok()) {
        return false;
    }
    entrepot_structure_champ_ensure_lie_barre_schema();
    $champ = entrepot_structure_champ_get($champ_id);
    if ($champ === null || (int) ($champ['est_systeme'] ?? 0) === 1) {
        return false;
    }
    try {
        $db->exec('UPDATE entrepot_structure_champ SET lie_barre = 0 WHERE est_systeme = 0');
        $db->prepare('UPDATE entrepot_structure_champ SET lie_barre = 1 WHERE id = :id')
            ->execute([':id' => $champ_id]);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array<string, mixed>|null
 */
function entrepot_structure_champ_get_lie_barre() {
    entrepot_structure_champ_ensure_lie_barre_schema();
    if (!entrepot_structure_champs_tables_ok()) {
        return null;
    }
    global $db;
    try {
        $stmt = $db->query(
            'SELECT * FROM entrepot_structure_champ WHERE lie_barre = 1 AND est_systeme = 0 ORDER BY id ASC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Éléments du champ lié aux barres pour un étage.
 *
 * @param int $etage_id
 * @return array<int, array<string, mixed>>
 */
function entrepot_get_elements_champ_lie_barre_etage($etage_id) {
    $lie = entrepot_structure_champ_get_lie_barre();
    if ($lie === null) {
        return [];
    }

    return entrepot_get_champ_elements_etage((int) $etage_id, (int) $lie['id']);
}

/**
 * Configuration cascade formulaire produit (champs actifs, ordre).
 *
 * @return array<int, array<string, mixed>>
 */
function produit_emplacement_cascade_fields_config() {
    entrepot_structure_champ_ensure_lie_barre_schema();
    entrepot_structure_champ_ensure_hierarchie_schema();
    $hierarchie = function_exists('entrepot_hierarchie_schema_ok') && entrepot_hierarchie_schema_ok();

    $system_map = [
        'zones' => [
            'key' => 'ref_zone',
            'type' => 'zones',
            'niveau' => 'zone',
            'ordre' => 10,
            'hint' => 'Zone du niveau choisi.',
        ],
        'rayons' => [
            'key' => 'ref_rayon',
            'type' => 'rayons',
            'niveau' => 'rayon',
            'ordre' => 20,
            'hint' => 'Rayon de la zone choisie.',
        ],
        'etageres' => [
            'key' => 'ref_etagere',
            'type' => 'etageres',
            'niveau' => 'etagere',
            'ordre' => 30,
            'hint' => 'Étagère du rayon choisi.',
        ],
        'barres' => [
            'key' => 'ref_barre',
            'type' => 'barres',
            'niveau' => 'barre',
            'ordre' => 40,
            'hint' => 'Barre de l’étagère choisie.',
        ],
        'positions' => [
            'key' => 'entrepot_position_id',
            'type' => 'positions',
            'niveau' => 'position',
            'ordre' => 50,
            'hint' => 'Position sur la barre choisie.',
        ],
    ];

    if (!$hierarchie) {
        $system_map['allees'] = [
            'key' => 'ref_allee',
            'type' => 'allees',
            'niveau' => 'allee',
            'ordre' => 15,
            'hint' => 'Choisissez l’allée voulue (nom en base).',
        ];
    }

    $fields = [];
    $custom_by_niveau = [];
    foreach (entrepot_structure_champs_list() as $champ) {
        if ((int) ($champ['lie_barre'] ?? 0) === 1) {
            continue;
        }
        $slug = (string) ($champ['slug'] ?? '');
        if ($slug === 'allees' && $hierarchie) {
            continue;
        }
        $label = (string) ($champ['label'] ?? $slug);
        $icon = (string) ($champ['icon'] ?? 'fa-cube');

        if ((int) ($champ['est_systeme'] ?? 0) === 1) {
            if (!isset($system_map[$slug])) {
                continue;
            }
            $def = $system_map[$slug];
            $fields[] = [
                'key' => $def['key'],
                'type' => $def['type'],
                'niveau' => $def['niveau'],
                'ordre' => $def['ordre'],
                'label' => $label,
                'icon' => $icon,
                'hint' => $def['hint'],
                'champ_id' => (int) ($champ['id'] ?? 0),
            ];
            continue;
        }

        $cid = (int) ($champ['id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $niv = (string) ($champ['niveau_hierarchie'] ?? 'rayon');
        $ordre_custom = 25;
        if ($niv === 'zone') {
            $ordre_custom = 12;
        } elseif ($niv === 'etagere') {
            $ordre_custom = 32;
        } elseif ($niv === 'barre') {
            $ordre_custom = 42;
        } elseif ($niv === 'position') {
            $ordre_custom = 52;
        }
        $custom_by_niveau[] = [
            'key' => 'ref_champ_' . $cid,
            'type' => 'custom',
            'niveau' => $niv,
            'ordre' => $ordre_custom,
            'label' => $label,
            'icon' => $icon,
            'hint' => 'Choisissez un élément « ' . $label . ' » (nom en base).',
            'champ_id' => $cid,
        ];
    }

    $fields = array_merge($fields, $custom_by_niveau);
    usort($fields, function ($a, $b) {
        return ((int) ($a['ordre'] ?? 0)) <=> ((int) ($b['ordre'] ?? 0));
    });

    return $fields;
}

/**
 * Reconnexion d’un champ supprimé puis recréé (slug_canonique).
 *
 * @return array{success: bool, message: string, champ?: array<string, mixed>}|null
 */
function entrepot_structure_champ_reconnecter($slug_canonique, $label, $icon, $colonne, $ordre, $lie_barre, $max_valeur, $niveau_hierarchie) {
    global $db;
    if (!entrepot_structure_champ_archive_tables_ok() || $slug_canonique === '') {
        return null;
    }
    try {
        $stmt = $db->prepare(
            'SELECT * FROM entrepot_structure_champ_archive
             WHERE slug_canonique = :c ORDER BY date_archivage DESC LIMIT 1'
        );
        $stmt->execute([':c' => $slug_canonique]);
        $arch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$arch) {
            return null;
        }
        $slug_base = entrepot_structure_champ_slug_depuis_label($label);
        $slug = $slug_base;
        $suffix = 1;
        while (entrepot_structure_champ_get_by_slug($slug) !== null) {
            $suffix++;
            $slug = $slug_base . '_' . $suffix;
        }
        $niv = $niveau_hierarchie !== '' ? $niveau_hierarchie : ($arch['niveau_hierarchie'] ?? null);
        $lie = $lie_barre ? 1 : (int) ($arch['lie_barre'] ?? 0);
        $icon_restored = trim((string) ($arch['icon'] ?? '')) !== '' ? (string) $arch['icon'] : $icon;
        $db->prepare(
            'INSERT INTO entrepot_structure_champ (slug, slug_canonique, label, icon, colonne_db, ordre, est_systeme, lie_barre, niveau_hierarchie, max_valeur, date_creation)
             VALUES (:slug, :canon, :label, :icon, :col, :ordre, 0, :lie, :niv, :max, NOW())'
        )->execute([
            ':slug' => $slug,
            ':canon' => $slug_canonique,
            ':label' => $label,
            ':icon' => $icon_restored,
            ':col' => $colonne,
            ':ordre' => $ordre,
            ':lie' => $lie,
            ':niv' => $niv ?: null,
            ':max' => $max_valeur,
        ]);
        $id = (int) $db->lastInsertId();
        if ($lie === 1) {
            entrepot_structure_champ_definir_lie_barre($id);
        }
        $col_res = entrepot_structure_champ_ajouter_colonne_etage($colonne, 10);
        if (!$col_res['success']) {
            $db->prepare('DELETE FROM entrepot_structure_champ WHERE id = :id')->execute([':id' => $id]);

            return $col_res;
        }
        $db->prepare('DELETE FROM entrepot_structure_champ_archive WHERE id = :id')->execute([':id' => (int) $arch['id']]);

        return [
            'success' => true,
            'message' => 'Champ « ' . $label . ' » recréé et reconnecté à l’archive.',
            'champ' => entrepot_structure_champ_get($id),
        ];
    } catch (PDOException $e) {
        return null;
    }
}
