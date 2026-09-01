<?php
/**
 * Champs dynamiques — formulaire ajout / modification produit.
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin.php';
require_once __DIR__ . '/model_produits.php';

define('PRODUIT_FORMULAIRE_CHAMPS_MAX', 40);
define('PRODUIT_FORMULAIRE_CHAMP_LABEL_MAX', 100);

/**
 * @return array<int, array<string, mixed>>
 */
function produit_formulaire_champs_systeme_defaut() {
    return [
        ['slug' => 'nom', 'label' => 'Nom du produit', 'icon' => 'fa-tag', 'section' => 'info', 'colonne_db' => 'nom', 'ordre' => 10, 'verrouille' => 1, 'obligatoire' => 1],
        ['slug' => 'description', 'label' => 'Description', 'icon' => 'fa-align-left', 'section' => 'info', 'colonne_db' => 'description', 'ordre' => 20, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'fournisseur_id', 'label' => 'Fournisseur', 'icon' => 'fa-truck', 'section' => 'info', 'colonne_db' => 'fournisseur_id', 'ordre' => 30, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'marque_id', 'label' => 'Marque', 'icon' => 'fa-certificate', 'section' => 'info', 'colonne_db' => 'marque_id', 'ordre' => 40, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'reference_fournisseur', 'label' => 'Référence fournisseur', 'icon' => 'fa-barcode', 'section' => 'info', 'colonne_db' => 'reference_fournisseur', 'ordre' => 50, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'prix', 'label' => 'Prix de vente', 'icon' => 'fa-coins', 'section' => 'prix', 'colonne_db' => 'prix', 'ordre' => 110, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'prix_promotion', 'label' => 'Prix promotionnel', 'icon' => 'fa-percent', 'section' => 'prix', 'colonne_db' => 'prix_promotion', 'ordre' => 120, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'prix_achat', 'label' => 'Prix grossiste', 'icon' => 'fa-receipt', 'section' => 'prix', 'colonne_db' => 'prix_achat', 'ordre' => 130, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'stock', 'label' => 'Stock', 'icon' => 'fa-boxes-stacked', 'section' => 'prix', 'colonne_db' => 'stock', 'ordre' => 140, 'verrouille' => 1, 'obligatoire' => 1],
        /* LE SEUIL DE LA PIÈCE (31/08) : chaque pièce a le sien, et l'alerte
         * parle dès que le stock lui est inférieur OU égal. Case vide = aucun
         * seuil, le logiciel ne dit rien ; zéro = préviens-moi à l'épuisement. */
        ['slug' => 'seuil_alerte', 'label' => 'Seuil d\'alerte', 'icon' => 'fa-bell', 'section' => 'prix', 'colonne_db' => 'seuil_alerte', 'ordre' => 145],
        ['slug' => 'categorie_id', 'label' => 'Catégorie', 'icon' => 'fa-folder', 'section' => 'prix', 'colonne_db' => 'categorie_id', 'ordre' => 150, 'verrouille' => 1, 'obligatoire' => 1],
        ['slug' => 'sous_categorie_id', 'label' => 'Sous-catégorie', 'icon' => 'fa-folder-open', 'section' => 'prix', 'colonne_db' => 'sous_categorie_id', 'ordre' => 160, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'statut', 'label' => 'Statut', 'icon' => 'fa-toggle-on', 'section' => 'prix', 'colonne_db' => 'statut', 'ordre' => 170, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'identifiant_interne', 'label' => 'Référence FPL', 'icon' => 'fa-qrcode', 'section' => 'ref', 'colonne_db' => 'identifiant_interne', 'ordre' => 210, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'emplacement', 'label' => 'Emplacement entrepôt', 'icon' => 'fa-warehouse', 'section' => 'ref', 'colonne_db' => null, 'ordre' => 220, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'variantes', 'label' => 'Variantes', 'icon' => 'fa-layer-group', 'section' => 'variantes', 'colonne_db' => null, 'ordre' => 310, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'poids', 'label' => 'Poids disponibles', 'icon' => 'fa-weight-hanging', 'section' => 'options', 'colonne_db' => 'poids', 'ordre' => 410, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'couleurs', 'label' => 'Couleurs', 'icon' => 'fa-palette', 'section' => 'options', 'colonne_db' => 'couleurs', 'ordre' => 420, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'taille', 'label' => 'Tailles', 'icon' => 'fa-ruler', 'section' => 'options', 'colonne_db' => 'taille', 'ordre' => 430, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'images_produit', 'label' => 'Galerie photos', 'icon' => 'fa-images', 'section' => 'media', 'colonne_db' => null, 'ordre' => 510, 'verrouille' => 0, 'obligatoire' => 0],
        ['slug' => 'image_etiquette_fpl', 'label' => 'Photo étiquette FPL', 'icon' => 'fa-tag', 'section' => 'media', 'colonne_db' => 'image_etiquette_fpl', 'ordre' => 520, 'verrouille' => 0, 'obligatoire' => 0],
    ];
}

/**
 * @return bool
 */
function produit_formulaire_champs_tables_ok() {
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok === true) {
        return true;
    }
    try {
        $db->query('SELECT 1 FROM produit_formulaire_champ LIMIT 1');
        $db->query('SELECT 1 FROM produit_formulaire_champ_droit LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        return false;
    }

    return true;
}

/**
 * Crée les tables si absentes (utilisable en CLI et via HTTP).
 *
 * @return bool
 */
function produit_formulaire_champs_run_migration() {
    global $db;
    if (!$db) {
        return false;
    }
    if (produit_formulaire_champs_tables_ok()) {
        return true;
    }
    $sql_file = __DIR__ . '/../migrations/create_produit_formulaire_champs.sql';
    if (!is_file($sql_file)) {
        return false;
    }
    $sql = file_get_contents($sql_file);
    if ($sql === false || trim($sql) === '') {
        return false;
    }
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        return false;
    }
    if (!produit_formulaire_champs_tables_ok()) {
        return false;
    }
    produit_formulaire_champs_seed_systeme();
    produit_formulaire_champ_roles_ensure();

    return true;
}

/**
 * @return bool
 */
function produit_formulaire_champ_roles_table_ok() {
    global $db;
    if (!$db || !produit_formulaire_champs_tables_ok()) {
        return false;
    }
    try {
        $db->query('SELECT 1 FROM produit_formulaire_champ_role LIMIT 1');

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return bool
 */
function produit_formulaire_champ_roles_ensure() {
    global $db;
    if (!$db || !produit_formulaire_champs_tables_ok()) {
        return false;
    }
    if (produit_formulaire_champ_roles_table_ok()) {
        return true;
    }
    $sql_file = __DIR__ . '/../migrations/migrate_produit_formulaire_champ_roles.sql';
    if (!is_file($sql_file)) {
        return false;
    }
    $sql = file_get_contents($sql_file);
    if ($sql === false || trim($sql) === '') {
        return false;
    }
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        return false;
    }

    return produit_formulaire_champ_roles_table_ok();
}

/**
 * @return bool
 */
function produit_formulaire_champs_ensure_schema() {
    if (!produit_formulaire_champs_tables_ok()) {
        if (!produit_formulaire_champs_run_migration()) {
            return false;
        }
    }

    return produit_formulaire_champ_roles_ensure();
}

/**
 * @return array{success: bool, message: string}
 */
function produit_formulaire_champs_seed_systeme() {
    global $db;
    if (!$db || !produit_formulaire_champs_tables_ok()) {
        return ['success' => false, 'message' => 'Tables absentes.'];
    }
    $inserted = 0;
    foreach (produit_formulaire_champs_systeme_defaut() as $def) {
        $slug = (string) ($def['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $st = $db->prepare('SELECT id FROM produit_formulaire_champ WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $slug]);
        if ($st->fetchColumn()) {
            continue;
        }
        $db->prepare(
            'INSERT INTO produit_formulaire_champ
             (slug, label, icon, section, type_champ, colonne_db, ordre, est_systeme, verrouille, actif, obligatoire, date_creation)
             VALUES (:slug, :label, :icon, :section, \'systeme\', :col, :ordre, 1, :ver, 1, :obl, NOW())'
        )->execute([
            ':slug' => $slug,
            ':label' => (string) ($def['label'] ?? $slug),
            ':icon' => (string) ($def['icon'] ?? 'fa-cube'),
            ':section' => (string) ($def['section'] ?? 'info'),
            ':col' => ($def['colonne_db'] ?? null) ?: null,
            ':ordre' => (int) ($def['ordre'] ?? 0),
            ':ver' => (int) ($def['verrouille'] ?? 0),
            ':obl' => (int) ($def['obligatoire'] ?? 0),
        ]);
        $inserted++;
    }

    return ['success' => true, 'message' => 'Champs système synchronisés (' . $inserted . ' ajouté(s)).'];
}

/**
 * @return array<int, array<string, mixed>>
 */
function produit_formulaire_champs_list($actifs_seulement = false) {
    global $db;
    produit_formulaire_champs_ensure_schema();
    produit_formulaire_champs_seed_systeme();
    if (!produit_formulaire_champs_tables_ok()) {
        return produit_formulaire_champs_systeme_defaut();
    }
    try {
        $sql = 'SELECT * FROM produit_formulaire_champ';
        if ($actifs_seulement) {
            $sql .= ' WHERE actif = 1';
        }
        $sql .= ' ORDER BY ordre ASC, id ASC';

        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param string $slug
 * @return array<string, mixed>|null
 */
function produit_formulaire_champ_get_by_slug($slug) {
    global $db;
    $slug = trim((string) $slug);
    if ($slug === '' || !produit_formulaire_champs_tables_ok()) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM produit_formulaire_champ WHERE slug = :s LIMIT 1');
    $st->execute([':s' => $slug]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function produit_formulaire_champ_get($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !produit_formulaire_champs_tables_ok()) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM produit_formulaire_champ WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * @param array<string, mixed> $champ
 * @return bool
 */
function produit_formulaire_champ_colonne_disponible($champ) {
    $col = trim((string) ($champ['colonne_db'] ?? ''));
    if ($col === '') {
        return true;
    }

    return produits_has_column($col);
}

/**
 * @return array<string, string> role => label
 */
function produit_formulaire_roles_disponibles() {
    require_once __DIR__ . '/model_admin.php';
    $out = [];
    foreach (admin_roles_valides() as $role) {
        $out[$role] = admin_role_label($role);
    }

    return $out;
}

/**
 * @param array<int, string> $roles
 * @return array<int, string>
 */
function produit_formulaire_champ_roles_normaliser(array $roles) {
    require_once __DIR__ . '/model_admin.php';
    $valides = admin_roles_valides();
    $out = [];
    foreach ($roles as $r) {
        $r = normalize_admin_role((string) $r);
        if (in_array($r, $valides, true)) {
            $out[$r] = $r;
        }
    }

    return array_values($out);
}

/**
 * @return array<int, array<int, string>> champ_id => [roles]
 */
function produit_formulaire_champ_roles_map_all() {
    global $db, $produit_formulaire_champ_roles_map_cache;
    if (isset($produit_formulaire_champ_roles_map_cache) && is_array($produit_formulaire_champ_roles_map_cache)) {
        return $produit_formulaire_champ_roles_map_cache;
    }
    $map = [];
    if (!$db || !produit_formulaire_champ_roles_table_ok()) {
        $produit_formulaire_champ_roles_map_cache = $map;

        return $map;
    }
    try {
        $rows = $db->query('SELECT champ_id, role FROM produit_formulaire_champ_role ORDER BY champ_id, role')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $cid = (int) ($row['champ_id'] ?? 0);
            $role = normalize_admin_role((string) ($row['role'] ?? ''));
            if ($cid <= 0 || $role === '') {
                continue;
            }
            if (!isset($map[$cid])) {
                $map[$cid] = [];
            }
            $map[$cid][] = $role;
        }
    } catch (PDOException $e) {
        $map = [];
    }
    $produit_formulaire_champ_roles_map_cache = $map;

    return $map;
}

/**
 * VOIR N'EST PAS MODIFIER (31/08). La colonne `niveau` de
 * produit_formulaire_champ_role dit, pour chaque rôle, s'il ne fait que voir
 * le champ ('voir') ou s'il peut aussi l'écrire ('modifier'). Une base qui
 * n'a pas encore la colonne se comporte comme avant : tout est 'modifier'.
 *
 * @return bool
 */
function produit_formulaire_champ_role_niveau_colonne_ok() {
    global $db, $produit_formulaire_champ_role_niveau_ok_cache;
    if (isset($produit_formulaire_champ_role_niveau_ok_cache)) {
        return $produit_formulaire_champ_role_niveau_ok_cache;
    }
    $produit_formulaire_champ_role_niveau_ok_cache = false;
    if ($db && produit_formulaire_champ_roles_table_ok()) {
        try {
            $db->query('SELECT niveau FROM produit_formulaire_champ_role LIMIT 1');
            $produit_formulaire_champ_role_niveau_ok_cache = true;
        } catch (PDOException $e) {
            $produit_formulaire_champ_role_niveau_ok_cache = false;
        }
    }

    return $produit_formulaire_champ_role_niveau_ok_cache;
}

/**
 * @return array<int, array<string, string>> champ_id => [role => 'voir'|'modifier']
 */
function produit_formulaire_champ_niveaux_map_all() {
    global $db, $produit_formulaire_champ_niveaux_map_cache;
    if (isset($produit_formulaire_champ_niveaux_map_cache) && is_array($produit_formulaire_champ_niveaux_map_cache)) {
        return $produit_formulaire_champ_niveaux_map_cache;
    }
    $map = [];
    if (!$db || !produit_formulaire_champ_role_niveau_colonne_ok()) {
        $produit_formulaire_champ_niveaux_map_cache = $map;

        return $map;
    }
    try {
        $rows = $db->query('SELECT champ_id, role, niveau FROM produit_formulaire_champ_role')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $cid = (int) ($row['champ_id'] ?? 0);
            $role = normalize_admin_role((string) ($row['role'] ?? ''));
            if ($cid <= 0 || $role === '') {
                continue;
            }
            $map[$cid][$role] = ((string) ($row['niveau'] ?? 'modifier')) === 'voir' ? 'voir' : 'modifier';
        }
    } catch (PDOException $e) {
        $map = [];
    }
    $produit_formulaire_champ_niveaux_map_cache = $map;

    return $map;
}

/**
 * Le rôle peut-il ÉCRIRE ce champ ? Un champ qu'on ne voit pas ne s'écrit
 * jamais ; un champ sans restriction de rôle reste ouvert, comme avant.
 *
 * @param string $slug
 * @param string|null $role
 * @return bool
 */
function produit_formulaire_champ_modifiable($slug, $role = null) {
    if (!produit_formulaire_champ_visible($slug)) {
        return false;
    }
    require_once __DIR__ . '/model_admin.php';
    if ($role === null) {
        if (session_status() === PHP_SESSION_NONE) {
            return true;
        }
        $role = isset($_SESSION['admin_role']) ? (string) $_SESSION['admin_role'] : 'admin';
    }
    $role = normalize_admin_role((string) $role);
    if (produit_formulaire_acces_bypass_role($role)) {
        return true;
    }
    $champ = produit_formulaire_champ_get_by_slug($slug);
    if ($champ === null) {
        return true;
    }
    $niveaux = produit_formulaire_champ_niveaux_map_all();
    $pour_ce_champ = $niveaux[(int) ($champ['id'] ?? 0)] ?? [];
    if ($pour_ce_champ === []) {
        return true;
    }

    return (($pour_ce_champ[$role] ?? 'modifier') === 'modifier');
}

/**
 * @param int $champ_id
 * @return array<int, string>
 */
function produit_formulaire_champ_roles_get($champ_id) {
    $champ_id = (int) $champ_id;
    if ($champ_id <= 0) {
        return [];
    }
    $map = produit_formulaire_champ_roles_map_all();

    return $map[$champ_id] ?? [];
}

/**
 * Liste vide = tous les rôles autorisés (rétrocompatibilité).
 *
 * @param int $champ_id
 * @param array<int, string> $roles
 * @return array{success: bool, message: string}
 */
/**
 * @param int $champ_id
 * @return array<string, string> role => 'voir'|'modifier'
 */
function produit_formulaire_champ_niveaux_get($champ_id) {
    $map = produit_formulaire_champ_niveaux_map_all();

    return $map[(int) $champ_id] ?? [];
}

function produit_formulaire_champ_roles_enregistrer($champ_id, array $roles, array $niveaux = []) {
    global $db;
    $champ_id = (int) $champ_id;
    if ($champ_id <= 0 || !$db || !produit_formulaire_champ_roles_table_ok()) {
        return ['success' => false, 'message' => 'Configuration des accès indisponible.'];
    }
    if (produit_formulaire_champ_get($champ_id) === null) {
        return ['success' => false, 'message' => 'Champ introuvable.'];
    }
    $roles = produit_formulaire_champ_roles_normaliser($roles);
    if ($roles === []) {
        return ['success' => false, 'message' => 'Sélectionnez au moins un type de compte autorisé.'];
    }
    require_once __DIR__ . '/model_admin.php';
    $tous_roles = admin_roles_valides();
    try {
        /* LE NIVEAU DE CHAQUE DROIT (31/08) : 'voir' ou 'modifier'. Tout ce
         * qui n'est pas dit vaut 'modifier' — c'est le comportement d'avant. */
        $avec_niveau = produit_formulaire_champ_role_niveau_colonne_ok();
        $niveau_de = [];
        foreach ($roles as $role) {
            $n = isset($niveaux[$role]) ? (string) $niveaux[$role] : 'modifier';
            $niveau_de[$role] = $n === 'voir' ? 'voir' : 'modifier';
        }
        $que_des_modifier = !in_array('voir', $niveau_de, true);

        $db->prepare('DELETE FROM produit_formulaire_champ_role WHERE champ_id = :c')->execute([':c' => $champ_id]);
        /* Le raccourci « tout le monde » ne vaut QUE si personne n'est en
         * lecture seule : sinon la nuance se perdrait à l'enregistrement. */
        if (count($roles) >= count($tous_roles) && $que_des_modifier) {
            produit_formulaire_champ_roles_map_reset();

            return ['success' => true, 'message' => 'Accès ouvert à tous les types de compte.'];
        }
        $st = $avec_niveau
            ? $db->prepare('INSERT INTO produit_formulaire_champ_role (champ_id, role, niveau, date_modification) VALUES (:c, :r, :n, NOW())')
            : $db->prepare('INSERT INTO produit_formulaire_champ_role (champ_id, role, date_modification) VALUES (:c, :r, NOW())');
        $compte_voir = 0;
        foreach ($roles as $role) {
            if ($avec_niveau) {
                $st->execute([':c' => $champ_id, ':r' => $role, ':n' => $niveau_de[$role]]);
            } else {
                $st->execute([':c' => $champ_id, ':r' => $role]);
            }
            if ($niveau_de[$role] === 'voir') {
                $compte_voir++;
            }
        }
        produit_formulaire_champ_roles_map_reset();

        $message = 'Accès enregistrés pour ' . count($roles) . ' type(s) de compte';
        $message .= $compte_voir > 0 ? ', dont ' . $compte_voir . ' en lecture seule.' : '.';

        return ['success' => true, 'message' => $message];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @return void
 */
function produit_formulaire_champ_roles_map_reset() {
    global $produit_formulaire_champ_roles_map_cache, $produit_formulaire_champ_niveaux_map_cache;
    unset($produit_formulaire_champ_roles_map_cache, $produit_formulaire_champ_niveaux_map_cache);
}

/**
 * @param string|null $role
 * @return bool
 */
function produit_formulaire_acces_bypass_role($role = null) {
    require_once __DIR__ . '/model_admin.php';
    require_once __DIR__ . '/../includes/admin_permissions.php';
    if ($role === null) {
        $role = admin_current_role();
    }
    $role = normalize_admin_role((string) $role);

    return in_array($role, ['informaticien', 'developpeur'], true);
}

/**
 * @param string $slug
 * @param string|null $role
 * @return bool
 */
function produit_formulaire_champ_acces_pour_role($slug, $role = null) {
    require_once __DIR__ . '/model_admin.php';
    if ($role === null) {
        if (session_status() === PHP_SESSION_NONE) {
            return true;
        }
        $role = isset($_SESSION['admin_role']) ? (string) $_SESSION['admin_role'] : 'admin';
    }
    $role = normalize_admin_role((string) $role);
    if (produit_formulaire_acces_bypass_role($role)) {
        return true;
    }
    $champ = produit_formulaire_champ_get_by_slug($slug);
    if ($champ === null) {
        return true;
    }
    $roles = produit_formulaire_champ_roles_get((int) ($champ['id'] ?? 0));
    if ($roles === []) {
        return true;
    }

    return in_array($role, $roles, true);
}

/**
 * Colonnes produits liées à un champ (y compris slugs composés).
 *
 * @param array<string, mixed> $champ
 * @return array<int, string>
 */
function produit_formulaire_champ_colonnes_donnees($champ) {
    $slug = (string) ($champ['slug'] ?? '');
    $col = trim((string) ($champ['colonne_db'] ?? ''));
    if ($col !== '') {
        return [$col];
    }
    if ($slug === 'emplacement') {
        $cols = ['entrepot_noeud_id', 'entrepot_position_id', 'etage', 'numero_rayon', 'allee', 'zone_emplacement', 'position_emplacement', 'barre_rayon'];
        $out = [];
        foreach ($cols as $c) {
            if (function_exists('produits_has_column') && produits_has_column($c)) {
                $out[] = $c;
            }
        }

        return $out;
    }
    if ($slug === 'images_produit') {
        return ['image_principale', 'images'];
    }
    if ($slug === 'fournisseur_id') {
        $out = [];
        if (function_exists('produits_has_column') && produits_has_column('fournisseur_id')) {
            $out[] = 'fournisseur_id';
        }
        if (function_exists('produits_has_column') && produits_has_column('nom_fournisseur')) {
            $out[] = 'nom_fournisseur';
        }
        if (function_exists('produits_has_column') && produits_has_column('fournisseur_nom')) {
            $out[] = 'fournisseur_nom';
        }

        return $out;
    }
    if ($slug === 'marque_id' && function_exists('produits_has_column') && produits_has_column('marque_id')) {
        return ['marque_id', 'marque_nom'];
    }
    if ($slug === 'categorie_id') {
        return ['categorie_id', 'categorie_nom', 'sous_categorie_id', 'sous_categorie_nom'];
    }

    return [];
}

/**
 * @param string $slug
 * @return bool
 */
function produit_formulaire_champ_obligatoire($slug) {
    $ch = produit_formulaire_champ_get_by_slug($slug);
    if ($ch === null || !produit_formulaire_champ_visible($slug)) {
        return false;
    }

    return (int) ($ch['obligatoire'] ?? 0) === 1;
}

/**
 * @param string $slug
 * @return bool
 */
function produit_formulaire_champ_visible($slug) {
    static $cache = [];
    $slug = trim((string) $slug);
    if ($slug === '') {
        return false;
    }
    require_once __DIR__ . '/model_admin.php';
    $role = isset($_SESSION['admin_role']) ? normalize_admin_role((string) $_SESSION['admin_role']) : 'admin';
    $key = $slug . '|' . $role;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $champ = produit_formulaire_champ_get_by_slug($slug);
    if ($champ === null) {
        $cache[$key] = true;

        return true;
    }
    if ((int) ($champ['actif'] ?? 0) !== 1) {
        $cache[$key] = false;

        return false;
    }
    if ((string) ($champ['type_champ'] ?? '') === 'systeme' && !produit_formulaire_champ_colonne_disponible($champ)) {
        $cache[$key] = false;

        return false;
    }
    if (!produit_formulaire_champ_acces_pour_role($slug, $role)) {
        $cache[$key] = false;

        return false;
    }
    $cache[$key] = true;

    return true;
}

/**
 * @param int $champ_id
 * @return string
 */
function produit_formulaire_champ_roles_resume($champ_id) {
    $roles = produit_formulaire_champ_roles_get((int) $champ_id);
    if ($roles === []) {
        return 'Tous les types de compte';
    }
    $labels = produit_formulaire_roles_disponibles();
    $niveaux = produit_formulaire_champ_niveaux_get((int) $champ_id);
    $parts = [];
    foreach ($roles as $role) {
        $nom = $labels[$role] ?? $role;
        /* Dire lequel ne fait que regarder : c'est toute la nuance. */
        $parts[] = (($niveaux[$role] ?? 'modifier') === 'voir') ? $nom . ' (lecture seule)' : $nom;
    }

    return implode(', ', $parts);
}

/**
 * @return bool
 */
function produit_formulaire_acces_filtrage_admin_actif() {
    if (session_status() === PHP_SESSION_NONE || empty($_SESSION['admin_id'])) {
        return false;
    }
    $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    $in_admin = defined('ADMIN_ROUTE_ENFORCED') || (strpos($script, '/admin/') !== false);
    if (!$in_admin) {
        return false;
    }
    if (produit_formulaire_acces_bypass_role()) {
        return false;
    }

    return produit_formulaire_champ_roles_table_ok();
}

/**
 * @param array<string, mixed> $produit
 * @param string|null $role
 * @return array<string, mixed>
 */
function produit_formulaire_filtrer_produit_acces(array $produit, $role = null) {
    if (!produit_formulaire_acces_filtrage_admin_actif()) {
        return $produit;
    }
    require_once __DIR__ . '/model_admin.php';
    if ($role === null) {
        $role = isset($_SESSION['admin_role']) ? normalize_admin_role((string) $_SESSION['admin_role']) : 'admin';
    }
    foreach (produit_formulaire_champs_list(false) as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        if ($slug === '' || produit_formulaire_champ_visible($slug)) {
            continue;
        }
        foreach (produit_formulaire_champ_colonnes_donnees($ch) as $col) {
            if (array_key_exists($col, $produit)) {
                unset($produit[$col]);
            }
        }
    }
    if (isset($produit['pf_custom']) && is_array($produit['pf_custom'])) {
        foreach (array_keys($produit['pf_custom']) as $cslug) {
            if (!produit_formulaire_champ_visible((string) $cslug)) {
                unset($produit['pf_custom'][$cslug]);
            }
        }
    }

    return $produit;
}

/**
 * @param array<int, array<string, mixed>> $produits
 * @return array<int, array<string, mixed>>
 */
function produit_formulaire_filtrer_produits_liste_acces(array $produits) {
    if (!produit_formulaire_acces_filtrage_admin_actif() || $produits === []) {
        return $produits;
    }
    foreach ($produits as $i => $p) {
        if (is_array($p)) {
            $produits[$i] = produit_formulaire_filtrer_produit_acces($p);
        }
    }

    return $produits;
}

/**
 * @param string $section
 * @return bool
 */
function produit_formulaire_section_visible($section) {
    foreach (produit_formulaire_champs_list(true) as $ch) {
        if (($ch['section'] ?? '') === $section && produit_formulaire_champ_visible((string) ($ch['slug'] ?? ''))) {
            return true;
        }
    }

    return false;
}

/**
 * @return array<int, array<string, mixed>>
 */
function produit_formulaire_champs_custom_actifs() {
    $out = [];
    foreach (produit_formulaire_champs_list(true) as $ch) {
        if ((string) ($ch['type_champ'] ?? '') === 'systeme') {
            continue;
        }
        $slug = (string) ($ch['slug'] ?? '');
        if ($slug !== '' && !produit_formulaire_champ_visible($slug)) {
            continue;
        }
        $out[] = $ch;
    }

    return $out;
}

/**
 * @param string $label
 * @return string
 */
function produit_formulaire_champ_slug_depuis_label($label) {
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

    return substr($slug, 0, 50);
}

/**
 * @param string $label
 * @param string $type_champ
 * @param string $section
 * @param string $options_raw
 * @param bool $obligatoire
 * @param array<int, string> $roles_acces
 * @return array{success: bool, message: string, id?: int}
 */
function produit_formulaire_champ_ajouter($label, $type_champ, $section, $options_raw = '', $obligatoire = false, array $roles_acces = []) {
    global $db;
    if (!$db || !produit_formulaire_champs_tables_ok()) {
        return ['success' => false, 'message' => 'Tables absentes — exécutez la migration champs produit.'];
    }
    $label = trim((string) $label);
    if ($label === '') {
        return ['success' => false, 'message' => 'Le libellé est obligatoire.'];
    }
    $types = ['texte', 'textarea', 'nombre', 'select'];
    $type_champ = in_array($type_champ, $types, true) ? $type_champ : 'texte';
    $sections = ['info', 'prix', 'ref', 'variantes', 'options', 'media'];
    $section = in_array($section, $sections, true) ? $section : 'info';
    $count = (int) $db->query('SELECT COUNT(*) FROM produit_formulaire_champ')->fetchColumn();
    if ($count >= PRODUIT_FORMULAIRE_CHAMPS_MAX) {
        return ['success' => false, 'message' => 'Limite de ' . PRODUIT_FORMULAIRE_CHAMPS_MAX . ' champs atteinte.'];
    }
    $slug_base = produit_formulaire_champ_slug_depuis_label($label);
    if ($slug_base === '') {
        $slug_base = 'champ';
    }
    $slug = $slug_base;
    $n = 1;
    while (produit_formulaire_champ_get_by_slug($slug) !== null) {
        $n++;
        $slug = $slug_base . '_' . $n;
    }
    $options_json = null;
    if ($type_champ === 'select') {
        $opts = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) $options_raw))));
        if ($opts === []) {
            return ['success' => false, 'message' => 'Indiquez au moins une option pour une liste déroulante.'];
        }
        $options_json = json_encode($opts, JSON_UNESCAPED_UNICODE);
    }
    $roles_acces = produit_formulaire_champ_roles_normaliser($roles_acces);
    if ($roles_acces === []) {
        return ['success' => false, 'message' => 'Sélectionnez au moins un type de compte autorisé à voir ce champ.'];
    }
    $ordre = (int) $db->query('SELECT COALESCE(MAX(ordre), 0) FROM produit_formulaire_champ')->fetchColumn() + 10;
    try {
        $db->prepare(
            'INSERT INTO produit_formulaire_champ
             (slug, label, icon, section, type_champ, colonne_db, ordre, est_systeme, verrouille, actif, obligatoire, options_json, date_creation)
             VALUES (:slug, :label, \'fa-puzzle-piece\', :section, :type, NULL, :ordre, 0, 0, 1, :obl, :opts, NOW())'
        )->execute([
            ':slug' => $slug,
            ':label' => $label,
            ':section' => $section,
            ':type' => $type_champ,
            ':ordre' => $ordre,
            ':obl' => $obligatoire ? 1 : 0,
            ':opts' => $options_json,
        ]);

        $new_id = (int) $db->lastInsertId();
        produit_formulaire_champ_roles_enregistrer($new_id, $roles_acces);

        return ['success' => true, 'message' => 'Champ « ' . $label . ' » ajouté.', 'id' => $new_id];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @param int $actif
 * @return array{success: bool, message: string}
 */
function produit_formulaire_champ_set_actif($id, $actif) {
    global $db;
    $id = (int) $id;
    $champ = produit_formulaire_champ_get($id);
    if ($champ === null) {
        return ['success' => false, 'message' => 'Champ introuvable.'];
    }
    if ((int) ($champ['verrouille'] ?? 0) === 1 && (int) $actif === 0) {
        return ['success' => false, 'message' => 'Ce champ système est verrouillé et ne peut pas être désactivé.'];
    }
    try {
        $db->prepare('UPDATE produit_formulaire_champ SET actif = :a WHERE id = :id')
            ->execute([':a' => (int) $actif === 1 ? 1 : 0, ':id' => $id]);

        return ['success' => true, 'message' => 'Champ mis à jour.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @return array{success: bool, message: string, produits_lies?: int}
 */
function produit_formulaire_champ_supprimer($id) {
    global $db;
    $id = (int) $id;
    $champ = produit_formulaire_champ_get($id);
    if ($champ === null) {
        return ['success' => false, 'message' => 'Champ introuvable.'];
    }
    if ((int) ($champ['est_systeme'] ?? 0) === 1) {
        return ['success' => false, 'message' => 'Les champs système ne peuvent pas être supprimés — désactivez-les si besoin.'];
    }
    if ((int) ($champ['verrouille'] ?? 0) === 1) {
        return ['success' => false, 'message' => 'Ce champ est verrouillé.'];
    }
    $produits_lies = 0;
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM produit_champ_valeur WHERE champ_id = :c');
        $st->execute([':c' => $id]);
        $produits_lies = (int) $st->fetchColumn();
        $db->prepare('DELETE FROM produit_formulaire_champ WHERE id = :id')->execute([':id' => $id]);

        return [
            'success' => true,
            'message' => 'Champ « ' . ($champ['label'] ?? '') . ' » supprimé.',
            'produits_lies' => $produits_lies,
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Retire un champ : suppression définitive (personnalisé) ou désactivation (système).
 *
 * @param int $id
 * @return array{success: bool, message: string, produits_lies?: int}
 */
function produit_formulaire_champ_retirer($id) {
    $id = (int) $id;
    $champ = produit_formulaire_champ_get($id);
    if ($champ === null) {
        return ['success' => false, 'message' => 'Champ introuvable.'];
    }
    if ((int) ($champ['verrouille'] ?? 0) === 1) {
        return ['success' => false, 'message' => 'Ce champ est verrouillé et ne peut pas être retiré.'];
    }
    if ((int) ($champ['est_systeme'] ?? 0) === 1) {
        $res = produit_formulaire_champ_set_actif($id, 0);
        if ($res['success']) {
            $res['message'] = 'Champ « ' . ($champ['label'] ?? '') . ' » retiré des formulaires (désactivé).';
        }

        return $res;
    }

    return produit_formulaire_champ_supprimer($id);
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function produit_formulaire_champ_impact_suppression($id) {
    $champ = produit_formulaire_champ_get($id);
    if ($champ === null) {
        return null;
    }
    global $db;
    $produits_lies = 0;
    $verrou = (int) ($champ['verrouille'] ?? 0) === 1;
    $est_sys = (int) ($champ['est_systeme'] ?? 0) === 1;
    if ($db && !$est_sys) {
        try {
            $st = $db->prepare('SELECT COUNT(DISTINCT produit_id) FROM produit_champ_valeur WHERE champ_id = :c');
            $st->execute([':c' => $id]);
            $produits_lies = (int) $st->fetchColumn();
        } catch (PDOException $e) {
            $produits_lies = 0;
        }
    }
    $avertissements = [];
    $action = 'supprimer';
    if ($verrou) {
        $action = 'bloque';
        $avertissements[] = 'Ce champ est verrouillé (nom, stock ou catégorie) et ne peut pas être retiré.';
    } elseif ($est_sys) {
        $action = 'desactiver';
        $avertissements[] = 'Le champ sera masqué des formulaires ajout et modification produit.';
        $avertissements[] = 'Les champs système ne sont pas effacés de la base — vous pourrez les réactiver avec l’interrupteur.';
    } else {
        $avertissements[] = 'Le champ disparaîtra définitivement des formulaires produit.';
        if ($produits_lies > 0) {
            $avertissements[] = $produits_lies . ' produit(s) possèdent une valeur pour ce champ — les données seront effacées.';
        }
    }

    return [
        'label' => (string) ($champ['label'] ?? ''),
        'est_systeme' => $est_sys,
        'verrouille' => $verrou,
        'peut_retirer' => !$verrou,
        'action' => $action,
        'produits_lies' => $produits_lies,
        'avertissements' => $avertissements,
    ];
}

/**
 * @return array<int, int>
 */
function produit_formulaire_droits_map() {
    global $db;
    if (!$db || !produit_formulaire_champs_tables_ok()) {
        return [];
    }
    try {
        $rows = $db->query('SELECT admin_id, peut_gerer FROM produit_formulaire_champ_droit WHERE peut_gerer = 1')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[(int) ($r['admin_id'] ?? 0)] = 1;
        }

        return $map;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param array<int, int> $admin_ids
 * @return array{success: bool, message: string}
 */
function produit_formulaire_droits_enregistrer(array $admin_ids) {
    global $db;
    if (!$db || !produit_formulaire_champs_tables_ok()) {
        return ['success' => false, 'message' => 'Tables absentes.'];
    }
    $admin_ids = array_values(array_unique(array_filter(array_map('intval', $admin_ids))));
    try {
        $db->exec('DELETE FROM produit_formulaire_champ_droit');
        if ($admin_ids !== []) {
            $st = $db->prepare(
                'INSERT INTO produit_formulaire_champ_droit (admin_id, peut_gerer, date_modification) VALUES (:a, 1, NOW())'
            );
            foreach ($admin_ids as $aid) {
                if ($aid > 0) {
                    $st->execute([':a' => $aid]);
                }
            }
        }

        return ['success' => true, 'message' => 'Droits enregistrés (' . count($admin_ids) . ' administrateur(s)).'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int|null $admin_id
 * @return bool
 */
function produit_formulaire_peut_gerer_champs($admin_id = null) {
    require_once __DIR__ . '/../includes/admin_permissions.php';
    if (admin_is_full_admin()) {
        return true;
    }
    if ($admin_id === null) {
        $admin_id = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    }
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return false;
    }
    $map = produit_formulaire_droits_map();

    return isset($map[$admin_id]);
}

/**
 * @param int $produit_id
 * @param array<string, mixed> $post
 * @return void
 */
function produit_formulaire_enregistrer_valeurs_custom($produit_id, array $post) {
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !$db || !produit_formulaire_champs_tables_ok()) {
        return;
    }
    foreach (produit_formulaire_champs_custom_actifs() as $ch) {
        $cid = (int) ($ch['id'] ?? 0);
        $slug = (string) ($ch['slug'] ?? '');
        if ($cid <= 0 || $slug === '') {
            continue;
        }
        $key = 'pf_custom_' . $slug;
        if (!array_key_exists($key, $post)) {
            continue;
        }
        $val = trim((string) $post[$key]);
        if ($val === '') {
            $db->prepare('DELETE FROM produit_champ_valeur WHERE produit_id = :p AND champ_id = :c')
                ->execute([':p' => $produit_id, ':c' => $cid]);
            continue;
        }
        $db->prepare(
            'INSERT INTO produit_champ_valeur (produit_id, champ_id, valeur, date_modification)
             VALUES (:p, :c, :v, NOW())
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), date_modification = NOW()'
        )->execute([':p' => $produit_id, ':c' => $cid, ':v' => $val]);
    }
}

/**
 * @param int $produit_id
 * @return array<string, string>
 */
function produit_formulaire_valeurs_custom($produit_id) {
    global $db;
    $produit_id = (int) $produit_id;
    $out = [];
    if ($produit_id <= 0 || !$db || !produit_formulaire_champs_tables_ok()) {
        return $out;
    }
    try {
        $st = $db->prepare(
            'SELECT c.slug, v.valeur
             FROM produit_champ_valeur v
             INNER JOIN produit_formulaire_champ c ON c.id = v.champ_id
             WHERE v.produit_id = :p'
        );
        $st->execute([':p' => $produit_id]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            if (produit_formulaire_acces_filtrage_admin_actif() && !produit_formulaire_champ_acces_pour_role($slug)) {
                continue;
            }
            $out[$slug] = (string) ($row['valeur'] ?? '');
        }
    } catch (PDOException $e) {
        // ignore
    }

    return $out;
}

/**
 * @return array<string, string>
 */
function produit_formulaire_sections_labels() {
    return [
        'info' => 'Informations générales',
        'prix' => 'Prix, stock & catégorie',
        'ref' => 'Référence & emplacement',
        'variantes' => 'Variantes',
        'options' => 'Options d\'achat',
        'media' => 'Galerie photos',
    ];
}

/**
 * Clés API / tableaux produits liées à chaque slug de champ.
 *
 * @return array<string, array<int, string>>
 */
function produit_formulaire_champ_clefs_donnees() {
    return [
        'description' => ['description', 'desc_excerpt', 'descPreview', 'descShort'],
        'marque_id' => ['marque_nom', 'marque_libelle_catalogue', 'marque_id', 'pcn_marque_join_nom'],
        'fournisseur_id' => ['fournisseur_nom', 'fournisseur_id', 'nom_fournisseur', 'fournisseur_table_nom'],
        'identifiant_interne' => ['identifiant_interne', 'ref_produit', 'ref'],
        'reference_fournisseur' => ['reference_fournisseur', 'ref_fournisseur'],
        'categorie_id' => ['categorie_nom', 'categorie_id'],
        'sous_categorie_id' => ['sous_categorie_id', 'sous_categorie_nom'],
        'prix' => ['prix'],
        'prix_promotion' => ['prix_promotion', 'prix_promo'],
        'prix_achat' => ['prix_achat'],
        'stock' => ['stock', 'stock_dispo'],
        'statut' => ['statut'],
        'images_produit' => ['image_principale', 'images'],
        'poids' => ['poids'],
        'couleurs' => ['couleurs'],
        'taille' => ['taille'],
        'image_etiquette_fpl' => ['image_etiquette_fpl'],
    ];
}

/**
 * Manifest JSON pour l’admin (visibilité champs selon profil + config).
 *
 * @return array{slugs: array<int, string>, labels: array<string, string>, locked: array<int, string>}
 */
function produit_formulaire_champs_manifest() {
    $slugs = [];
    $labels = [];
    $locked = [];
    foreach (produit_formulaire_champs_list(true) as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        if ($slug === '' || !produit_formulaire_champ_visible($slug)) {
            continue;
        }
        $slugs[] = $slug;
        $labels[$slug] = (string) ($ch['label'] ?? $slug);
        if ((int) ($ch['verrouille'] ?? 0) === 1) {
            $locked[] = $slug;
        }
    }

    return [
        'slugs' => $slugs,
        'labels' => $labels,
        'locked' => $locked,
    ];
}

/**
 * @return string
 */
function produit_formulaire_champs_manifest_json() {
    return json_encode(produit_formulaire_champs_manifest(), JSON_UNESCAPED_UNICODE);
}

/**
 * Filtre un item API (recherche caisse/devis) selon champs visibles.
 *
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function produit_formulaire_filtrer_produit_api(array $item) {
    if (!produit_formulaire_acces_filtrage_admin_actif()) {
        return $item;
    }
    foreach (produit_formulaire_champ_clefs_donnees() as $slug => $keys) {
        if (produit_formulaire_champ_visible($slug)) {
            continue;
        }
        foreach ($keys as $key) {
            unset($item[$key]);
        }
    }
    if (isset($item['pf_custom']) && is_array($item['pf_custom'])) {
        foreach (array_keys($item['pf_custom']) as $cslug) {
            if (!produit_formulaire_champ_visible((string) $cslug)) {
                unset($item['pf_custom'][$cslug]);
            }
        }
    }

    return $item;
}

/**
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function produit_formulaire_filtrer_produits_api_liste(array $items) {
    if (!produit_formulaire_acces_filtrage_admin_actif() || $items === []) {
        return $items;
    }
    foreach ($items as $i => $item) {
        if (is_array($item)) {
            $items[$i] = produit_formulaire_filtrer_produit_api($item);
        }
    }

    return $items;
}

/**
 * Colonnes système export suivi / PDF (clé colonne => métadonnées).
 *
 * @return array<string, array<string, mixed>>
 */
function produit_formulaire_export_colonnes_systeme_base() {
    return [
        'img' => ['slug' => 'images_produit', 'label' => 'Image', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-img', 'css_cell' => 'page-produits-export-table__img'],
        'nom' => ['slug' => 'nom', 'label' => 'Pièce', 'locked' => true, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-nom', 'css_cell' => 'page-produits-export-table__nom'],
        'cat' => ['slug' => 'categorie_id', 'label' => 'Catégorie', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-cat', 'css_cell' => ''],
        'marque' => ['slug' => 'marque_id', 'label' => 'Marque', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-marque', 'css_cell' => ''],
        'identifiant' => ['slug' => 'identifiant_interne', 'label' => 'Réf. FPL', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-ident', 'css_cell' => ''],
        'fournisseur' => ['slug' => 'fournisseur_id', 'label' => 'Fournisseur', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-four', 'css_cell' => ''],
        'prix_achat' => ['slug' => 'prix_achat', 'label' => 'Prix grossiste', 'locked' => false, 'num' => true, 'editable' => true, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-prix-achat', 'css_cell' => 'page-produits-export-table__num'],
        'prix' => ['slug' => 'prix', 'label' => 'Prix vente', 'locked' => false, 'num' => true, 'editable' => true, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-prix', 'css_cell' => 'page-produits-export-table__num'],
        'promo' => ['slug' => 'prix_promotion', 'label' => 'Promo', 'locked' => false, 'num' => true, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-promo', 'css_cell' => 'page-produits-export-table__num'],
        'stock' => ['slug' => 'stock', 'label' => 'Stock', 'locked' => false, 'num' => true, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-stock', 'css_cell' => 'page-produits-export-table__num'],
        'statut' => ['slug' => 'statut', 'label' => 'Statut', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-statut', 'css_cell' => ''],
        'sous_cat' => ['slug' => 'sous_categorie_id', 'label' => 'Sous-catégorie', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-sous-cat', 'css_cell' => ''],
        'poids' => ['slug' => 'poids', 'label' => 'Poids', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-poids', 'css_cell' => ''],
        'couleurs' => ['slug' => 'couleurs', 'label' => 'Couleurs', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-couleurs', 'css_cell' => ''],
        'taille' => ['slug' => 'taille', 'label' => 'Tailles', 'locked' => false, 'num' => false, 'pdf' => true, 'suivi' => true, 'css_col' => 'page-produits-export-table__col-taille', 'css_cell' => ''],
    ];
}

/**
 * @param string $context suivi|pdf
 * @return array<string, array<string, mixed>>
 */
function produit_formulaire_export_colonnes_definitions($context = 'suivi') {
    $context = ($context === 'pdf') ? 'pdf' : 'suivi';
    $flag = ($context === 'pdf') ? 'pdf' : 'suivi';
    $defs = [];
    foreach (produit_formulaire_export_colonnes_systeme_base() as $key => $def) {
        if (empty($def[$flag])) {
            continue;
        }
        $slug = (string) ($def['slug'] ?? '');
        if ($slug === 'prix_achat' && function_exists('produits_has_column') && !produits_has_column('prix_achat')) {
            continue;
        }
        if (!produit_formulaire_champ_visible($slug)) {
            continue;
        }
        $defs[$key] = $def;
    }
    foreach (produit_formulaire_champs_custom_actifs() as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $key = 'custom_' . $slug;
        $defs[$key] = [
            'slug' => $slug,
            'label' => (string) ($ch['label'] ?? $slug),
            'locked' => false,
            'num' => false,
            'custom' => true,
            'champ_id' => (int) ($ch['id'] ?? 0),
            'pdf' => true,
            'suivi' => true,
            'css_col' => 'page-produits-export-table__col-custom',
            'css_cell' => '',
        ];
    }

    return $defs;
}

/**
 * @param string $context suivi|pdf
 * @return array<string, string>
 */
function produit_formulaire_export_colonnes_catalog($context = 'suivi') {
    $catalog = [];
    foreach (produit_formulaire_export_colonnes_definitions($context) as $key => $def) {
        $catalog[$key] = (string) ($def['label'] ?? $key);
    }

    return $catalog;
}

/**
 * @param array<int, int> $produit_ids
 * @return array<int, array<string, string>>
 */
function produit_formulaire_valeurs_custom_batch(array $produit_ids) {
    global $db;
    $produit_ids = array_values(array_unique(array_filter(array_map('intval', $produit_ids), function ($id) {
        return $id > 0;
    })));
    if ($produit_ids === [] || !$db || !produit_formulaire_champs_tables_ok()) {
        return [];
    }
    $out = [];
    foreach ($produit_ids as $pid) {
        $out[$pid] = [];
    }
    try {
        $placeholders = implode(',', array_fill(0, count($produit_ids), '?'));
        $st = $db->prepare(
            'SELECT v.produit_id, c.slug, v.valeur
             FROM produit_champ_valeur v
             INNER JOIN produit_formulaire_champ c ON c.id = v.champ_id
             WHERE v.produit_id IN (' . $placeholders . ')'
        );
        $st->execute($produit_ids);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) ($row['produit_id'] ?? 0);
            $slug = (string) ($row['slug'] ?? '');
            if ($pid <= 0 || $slug === '' || !produit_formulaire_champ_visible($slug)) {
                continue;
            }
            if (!isset($out[$pid])) {
                $out[$pid] = [];
            }
            $out[$pid][$slug] = (string) ($row['valeur'] ?? '');
        }
    } catch (PDOException $e) {
        return $out;
    }

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $produits
 * @return array<int, array<string, mixed>>
 */
function produit_formulaire_attacher_valeurs_custom_liste(array $produits) {
    if ($produits === []) {
        return $produits;
    }
    $ids = [];
    foreach ($produits as $p) {
        $ids[] = (int) ($p['id'] ?? 0);
    }
    $batch = produit_formulaire_valeurs_custom_batch($ids);
    foreach ($produits as $i => $p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (int) ($p['id'] ?? 0);
        $p['pf_custom'] = $batch[$pid] ?? [];
        $produits[$i] = produit_formulaire_filtrer_produit_acces($p);
    }

    return $produits;
}
