<?php
/**
 * Hiérarchie entrepôt libre — définitions de niveaux + nœuds génériques.
 */
if (defined('ENTREPOT_HIERARCHIE_LIBRE_LOADED')) {
    return;
}
define('ENTREPOT_HIERARCHIE_LIBRE_LOADED', true);

require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function entrepot_hierarchie_libre_schema_ok()
{
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT id, slug, label, icon, ordre, actif FROM entrepot_hierarchie_niveau LIMIT 1');
        $db->query('SELECT id, etage_id, niveau_id, parent_id, numero, nom FROM entrepot_hierarchie_noeud LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * Colonnes étiquette / QR présentes ?
 *
 * @return bool
 */
function entrepot_hierarchie_etiquette_schema_ok($force_refresh = false)
{
    global $db;
    if (!$db || !entrepot_hierarchie_libre_schema_ok()) {
        return false;
    }
    static $ok = null;
    if ($force_refresh) {
        $ok = null;
    }
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query(
            'SELECT est_etiquette_qr, etiquette_lie_type, etiquette_lie_niveau_id
             FROM entrepot_hierarchie_niveau LIMIT 1'
        );
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * @return bool
 */
function entrepot_hierarchie_etiquette_ensure_schema()
{
    if (entrepot_hierarchie_etiquette_schema_ok()) {
        return true;
    }
    $runner = __DIR__ . '/../migrations/run_migrate_entrepot_hierarchie_etiquette.php';
    if (!is_file($runner)) {
        return false;
    }
    ob_start();
    include $runner;
    ob_end_clean();

    return entrepot_hierarchie_etiquette_schema_ok(true);
}

/**
 * @return bool
 */
function entrepot_hierarchie_libre_ensure_schema()
{
    if (!entrepot_hierarchie_libre_schema_ok()) {
        $runner = __DIR__ . '/../migrations/run_migrate_entrepot_hierarchie_libre.php';
        if (!is_file($runner)) {
            return false;
        }
        ob_start();
        include $runner;
        ob_end_clean();
    }
    if (!entrepot_hierarchie_libre_schema_ok()) {
        return false;
    }
    entrepot_hierarchie_etiquette_ensure_schema();
    entrepot_hierarchie_def_ensure_racine_etage();

    return true;
}

/**
 * Slug réservé pour la hiérarchie « Niveau » (étages / code abrégé).
 */
function entrepot_hierarchie_def_slug_etage()
{
    return 'etage';
}

/**
 * @param array<string, mixed>|null $def
 * @return bool
 */
function entrepot_hierarchie_def_est_etage($def)
{
    if (!is_array($def)) {
        return false;
    }

    return (string) ($def['slug'] ?? '') === entrepot_hierarchie_def_slug_etage();
}

/**
 * Garantit la présence du niveau système « Niveau » (créé en tête si absent).
 * Ne force pas la position ensuite — l’ordre reste librement modifiable.
 *
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_def_ensure_racine_etage()
{
    global $db;
    if (!$db || !entrepot_hierarchie_libre_schema_ok()) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT * FROM entrepot_hierarchie_niveau WHERE slug = :s LIMIT 1');
        $st->execute([':s' => entrepot_hierarchie_def_slug_etage()]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ((int) ($row['actif'] ?? 0) !== 1) {
                $db->prepare('UPDATE entrepot_hierarchie_niveau SET actif = 1 WHERE id = :id')
                    ->execute([':id' => (int) $row['id']]);
                $st->execute([':s' => entrepot_hierarchie_def_slug_etage()]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: $row;
            }

            return $row;
        }
        $db->exec('UPDATE entrepot_hierarchie_niveau SET ordre = ordre + 10');
        $db->prepare(
            'INSERT INTO entrepot_hierarchie_niveau (slug, label, icon, ordre, actif, date_creation)
             VALUES (:slug, :label, :icon, 1, 1, NOW())'
        )->execute([
                    ':slug' => entrepot_hierarchie_def_slug_etage(),
                    ':label' => 'Niveau',
                    ':icon' => 'fa-layer-group',
                ]);
        $id = (int) $db->lastInsertId();

        return entrepot_hierarchie_def_get($id);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param int $id
 * @return bool
 */
function entrepot_hierarchie_def_id_est_etage($id)
{
    return entrepot_hierarchie_def_est_etage(entrepot_hierarchie_def_get((int) $id));
}

/**
 * @param string $label
 * @return string
 */
function entrepot_hierarchie_def_slug_depuis_label($label)
{
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
        $slug = 'niveau';
    }
    if (strlen($slug) > 60) {
        $slug = substr($slug, 0, 60);
    }

    return $slug;
}

/**
 * @param bool $actifs_seulement
 * @return array<int, array<string, mixed>>
 */
function entrepot_hierarchie_def_list($actifs_seulement = false)
{
    global $db;
    entrepot_hierarchie_libre_ensure_schema();
    if (!$db || !entrepot_hierarchie_libre_schema_ok()) {
        return [];
    }
    try {
        $sql = 'SELECT * FROM entrepot_hierarchie_niveau';
        if ($actifs_seulement) {
            $sql .= ' WHERE actif = 1';
        }
        $sql .= ' ORDER BY ordre ASC, id ASC';
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_def_get($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !entrepot_hierarchie_libre_schema_ok()) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT * FROM entrepot_hierarchie_niveau WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Chemin affiché : Niveau → Zone → …
 *
 * @return string
 */
function entrepot_hierarchie_chemin_libelle()
{
    $parts = [];
    foreach (entrepot_hierarchie_def_list(true) as $def) {
        $lab = trim((string) ($def['label'] ?? ''));
        if ($lab !== '') {
            $parts[] = $lab;
        }
    }
    if ($parts === []) {
        return 'Niveau';
    }

    return implode(' → ', $parts);
}

/**
 * Définitions utilisables pour les nœuds (hors « Niveau » / étages).
 *
 * @param bool $actifs_seulement
 * @return array<int, array<string, mixed>>
 */
function entrepot_hierarchie_def_list_noeuds($actifs_seulement = false)
{
    $out = [];
    foreach (entrepot_hierarchie_def_list($actifs_seulement) as $def) {
        if (!entrepot_hierarchie_def_est_etage($def)) {
            $out[] = $def;
        }
    }

    return $out;
}

/**
 * Dernier niveau actif (feuille pour assignation produit).
 *
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_def_feuille()
{
    $list = entrepot_hierarchie_def_list_noeuds(true);
    if ($list === []) {
        $list = entrepot_hierarchie_def_list(true);
    }
    if ($list === []) {
        return null;
    }

    return $list[count($list) - 1];
}

/**
 * Normalise les options étiquette / QR.
 *
 * @param mixed $est_etiquette_qr
 * @param mixed $lie_type
 * @param mixed $lie_niveau_id
 * @param int $exclude_id
 * @return array{ok: bool, message: string, est: int, lie_type: string, lie_id: int|null}
 */
function entrepot_hierarchie_def_normaliser_etiquette($est_etiquette_qr, $lie_type, $lie_niveau_id, $exclude_id = 0)
{
    $est = !empty($est_etiquette_qr) ? 1 : 0;
    $lie_type = trim((string) $lie_type);
    if ($lie_type !== 'niveau') {
        $lie_type = 'etage';
    }
    $lie_id = (int) $lie_niveau_id;
    if ($est === 0) {
        return ['ok' => true, 'message' => '', 'est' => 0, 'lie_type' => 'etage', 'lie_id' => null];
    }
    if ($lie_type === 'niveau') {
        if ($lie_id <= 0) {
            return [
                'ok' => false,
                'message' => 'Choisissez le niveau hiérarchique lié pour l’étiquette / QR.',
                'est' => 1,
                'lie_type' => 'niveau',
                'lie_id' => null,
            ];
        }
        if ($exclude_id > 0 && $lie_id === (int) $exclude_id) {
            return [
                'ok' => false,
                'message' => 'Le niveau lié doit être différent du niveau étiquette / QR.',
                'est' => 1,
                'lie_type' => 'niveau',
                'lie_id' => null,
            ];
        }
        if (entrepot_hierarchie_def_get($lie_id) === null) {
            return [
                'ok' => false,
                'message' => 'Le niveau lié est introuvable.',
                'est' => 1,
                'lie_type' => 'niveau',
                'lie_id' => null,
            ];
        }

        return ['ok' => true, 'message' => '', 'est' => 1, 'lie_type' => 'niveau', 'lie_id' => $lie_id];
    }

    return ['ok' => true, 'message' => '', 'est' => 1, 'lie_type' => 'etage', 'lie_id' => null];
}

/**
 * Un seul niveau peut porter l’étiquette / QR.
 *
 * @param int $keep_id
 * @return void
 */
function entrepot_hierarchie_def_clear_autres_etiquette($keep_id)
{
    global $db;
    $keep_id = (int) $keep_id;
    if (!$db || !entrepot_hierarchie_etiquette_schema_ok()) {
        return;
    }
    try {
        if ($keep_id > 0) {
            $db->prepare(
                'UPDATE entrepot_hierarchie_niveau
                 SET est_etiquette_qr = 0, etiquette_lie_type = \'etage\', etiquette_lie_niveau_id = NULL
                 WHERE id != :id AND est_etiquette_qr = 1'
            )->execute([':id' => $keep_id]);
        } else {
            $db->exec(
                'UPDATE entrepot_hierarchie_niveau
                 SET est_etiquette_qr = 0, etiquette_lie_type = \'etage\', etiquette_lie_niveau_id = NULL
                 WHERE est_etiquette_qr = 1'
            );
        }
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * Niveau configuré pour étiquette / QR (ou null).
 *
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_def_etiquette()
{
    if (!entrepot_hierarchie_etiquette_ensure_schema()) {
        return null;
    }
    foreach (entrepot_hierarchie_def_list(true) as $def) {
        if ((int) ($def['est_etiquette_qr'] ?? 0) === 1) {
            return $def;
        }
    }
    foreach (entrepot_hierarchie_def_list(false) as $def) {
        if ((int) ($def['est_etiquette_qr'] ?? 0) === 1) {
            return $def;
        }
    }

    return null;
}

/**
 * @param string $label
 * @param string $icon
 * @param mixed $est_etiquette_qr
 * @param mixed $lie_type
 * @param mixed $lie_niveau_id
 * @return array{success: bool, message: string, niveau?: array<string, mixed>}
 */
function entrepot_hierarchie_def_ajouter($label, $icon = 'fa-cube', $est_etiquette_qr = 0, $lie_type = 'etage', $lie_niveau_id = null)
{
    global $db;
    if (!entrepot_hierarchie_libre_ensure_schema() || !$db) {
        return ['success' => false, 'message' => 'Schéma hiérarchie libre indisponible.'];
    }
    $label = trim((string) $label);
    if ($label === '') {
        return ['success' => false, 'message' => 'Le nom du niveau est obligatoire.'];
    }
    $icon = trim((string) $icon);
    if ($icon === '' || !preg_match('/^fa-[a-z0-9-]+$/', $icon)) {
        $icon = 'fa-cube';
    }
    $etiq = entrepot_hierarchie_def_normaliser_etiquette($est_etiquette_qr, $lie_type, $lie_niveau_id, 0);
    if (!$etiq['ok']) {
        return ['success' => false, 'message' => $etiq['message']];
    }
    $slug_base = entrepot_hierarchie_def_slug_depuis_label($label);
    if ($slug_base === entrepot_hierarchie_def_slug_etage()) {
        $slug_base = 'niveau_custom';
    }
    $slug = $slug_base;
    $suffix = 1;
    while (true) {
        $st = $db->prepare('SELECT id FROM entrepot_hierarchie_niveau WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $slug]);
        if (!$st->fetchColumn()) {
            break;
        }
        $suffix++;
        $slug = $slug_base . '_' . $suffix;
    }
    $ordre = (int) $db->query('SELECT COALESCE(MAX(ordre), 0) FROM entrepot_hierarchie_niveau')->fetchColumn();
    $ordre += 10;
    $has_etiq = entrepot_hierarchie_etiquette_schema_ok();
    try {
        if ($has_etiq) {
            $db->prepare(
                'INSERT INTO entrepot_hierarchie_niveau
                 (slug, label, icon, ordre, actif, est_etiquette_qr, etiquette_lie_type, etiquette_lie_niveau_id, date_creation)
                 VALUES (:slug, :label, :icon, :ordre, 1, :est, :lie_t, :lie_id, NOW())'
            )->execute([
                        ':slug' => $slug,
                        ':label' => $label,
                        ':icon' => $icon,
                        ':ordre' => $ordre,
                        ':est' => $etiq['est'],
                        ':lie_t' => $etiq['lie_type'],
                        ':lie_id' => $etiq['lie_id'],
                    ]);
        } else {
            $db->prepare(
                'INSERT INTO entrepot_hierarchie_niveau (slug, label, icon, ordre, actif, date_creation)
                 VALUES (:slug, :label, :icon, :ordre, 1, NOW())'
            )->execute([
                        ':slug' => $slug,
                        ':label' => $label,
                        ':icon' => $icon,
                        ':ordre' => $ordre,
                    ]);
        }
        $id = (int) $db->lastInsertId();
        if ($has_etiq && $etiq['est'] === 1) {
            entrepot_hierarchie_def_clear_autres_etiquette($id);
        }
        $niveau = entrepot_hierarchie_def_get($id);
        $msg = 'Niveau « ' . $label . ' » ajouté. Ajoutez ensuite ses éléments via la barre d’outils.';
        if ($etiq['est'] === 1) {
            $msg .= ' Configuré comme niveau étiquette / QR.';
        }

        return [
            'success' => true,
            'message' => $msg,
            'niveau' => $niveau,
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @param string $label
 * @param string $icon
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_def_renommer($id, $label, $icon = '')
{
    return entrepot_hierarchie_def_modifier($id, $label, $icon, null, null, null);
}

/**
 * Modifie nom, icône et options étiquette / QR.
 * Passer null pour $est_etiquette_qr (ou lie_*) conserve la valeur actuelle.
 *
 * @param int $id
 * @param string $label
 * @param string $icon
 * @param mixed|null $est_etiquette_qr
 * @param mixed|null $lie_type
 * @param mixed|null $lie_niveau_id
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_def_modifier($id, $label, $icon = '', $est_etiquette_qr = null, $lie_type = null, $lie_niveau_id = null)
{
    global $db;
    $id = (int) $id;
    $def = entrepot_hierarchie_def_get($id);
    if ($def === null) {
        return ['success' => false, 'message' => 'Niveau introuvable.'];
    }
    $label = trim((string) $label);
    if ($label === '') {
        return ['success' => false, 'message' => 'Le nom du niveau est obligatoire.'];
    }
    $icon = trim((string) $icon);
    if ($icon === '' || !preg_match('/^fa-[a-z0-9-]+$/', $icon)) {
        $icon = (string) ($def['icon'] ?? 'fa-cube');
    }

    $has_etiq = entrepot_hierarchie_etiquette_ensure_schema();
    $est = $est_etiquette_qr === null ? (int) ($def['est_etiquette_qr'] ?? 0) : $est_etiquette_qr;
    $lt = $lie_type === null ? (string) ($def['etiquette_lie_type'] ?? 'etage') : $lie_type;
    $lid = $lie_niveau_id === null ? ($def['etiquette_lie_niveau_id'] ?? null) : $lie_niveau_id;
    $etiq = ['ok' => true, 'est' => (int) ($def['est_etiquette_qr'] ?? 0), 'lie_type' => 'etage', 'lie_id' => null];
    if ($has_etiq && $est_etiquette_qr !== null) {
        $etiq = entrepot_hierarchie_def_normaliser_etiquette($est, $lt, $lid, $id);
        if (!$etiq['ok']) {
            return ['success' => false, 'message' => $etiq['message']];
        }
    }

    try {
        if ($has_etiq && $est_etiquette_qr !== null) {
            $db->prepare(
                'UPDATE entrepot_hierarchie_niveau
                 SET label = :l, icon = :i, est_etiquette_qr = :est,
                     etiquette_lie_type = :lie_t, etiquette_lie_niveau_id = :lie_id
                 WHERE id = :id'
            )->execute([
                        ':l' => $label,
                        ':i' => $icon,
                        ':est' => $etiq['est'],
                        ':lie_t' => $etiq['lie_type'],
                        ':lie_id' => $etiq['lie_id'],
                        ':id' => $id,
                    ]);
            if ($etiq['est'] === 1) {
                entrepot_hierarchie_def_clear_autres_etiquette($id);
            }
        } else {
            $db->prepare(
                'UPDATE entrepot_hierarchie_niveau SET label = :l, icon = :i WHERE id = :id'
            )->execute([':l' => $label, ':i' => $icon, ':id' => $id]);
        }

        return ['success' => true, 'message' => 'Hiérarchie « ' . $label . ' » mise à jour.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Segment texte pour libellé étiquette (nom de champ / nœud lié).
 *
 * @param string $raw
 * @param int $fallback_num
 * @return string
 */
function entrepot_noeud_etiquette_segment_lie($raw, $fallback_num = 0)
{
    $segment = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $raw)));
    if ($segment !== '') {
        if (strlen($segment) > 20) {
            $segment = substr($segment, 0, 20);
        }

        return $segment;
    }
    $fallback_num = max(0, (int) $fallback_num);
    if ($fallback_num > 0) {
        return sprintf('%02d', $fallback_num);
    }

    return '';
}

/**
 * Libellé étiquette pour un nœud niveau QR.
 * Format : {code_abrégé Niveau}[{nom champ lié}]-{n° étiquette}
 * — lié à Niveau (étage) : C-01
 * — lié à un autre niveau (ex. Zone « A1 ») : CA1-01
 *
 * @param int $noeud_id
 * @return string
 */
function entrepot_noeud_etiquette_libelle($noeud_id)
{
    global $db;
    $noeud_id = (int) $noeud_id;
    if ($noeud_id <= 0 || !entrepot_hierarchie_etiquette_ensure_schema() || !$db) {
        return '';
    }
    $etiq_def = entrepot_hierarchie_def_etiquette();
    if ($etiq_def === null) {
        return '';
    }
    $etiq_niveau_id = (int) ($etiq_def['id'] ?? 0);
    $lie_type = (string) ($etiq_def['etiquette_lie_type'] ?? 'etage');
    $lie_niveau_id = (int) ($etiq_def['etiquette_lie_niveau_id'] ?? 0);

    $chemin = [];
    $current = $noeud_id;
    $guard = 0;
    $etage_id = 0;
    try {
        $st = $db->prepare(
            'SELECT id, parent_id, niveau_id, numero, nom, etage_id FROM entrepot_hierarchie_noeud WHERE id = :id LIMIT 1'
        );
        while ($current > 0 && $guard < 40) {
            $guard++;
            $st->execute([':id' => $current]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                break;
            }
            array_unshift($chemin, $row);
            $etage_id = (int) ($row['etage_id'] ?? 0);
            $current = (int) ($row['parent_id'] ?? 0);
        }
    } catch (PDOException $e) {
        return '';
    }
    if ($chemin === []) {
        return '';
    }

    // Trouver le nœud du niveau étiquette (soi-même ou ancêtre / descendant proche sur le chemin)
    $noeud_etiq = null;
    foreach ($chemin as $n) {
        if ((int) ($n['niveau_id'] ?? 0) === $etiq_niveau_id) {
            $noeud_etiq = $n;
            break;
        }
    }
    if ($noeud_etiq === null) {
        // Si on part d’un enfant, remonter déjà fait ; si on part d’un parent, pas d’étiquette
        return '';
    }

    $code = 'N';
    if ($etage_id > 0) {
        try {
            $ste = $db->prepare('SELECT code_abrege, code, numero_etage FROM entrepot_etage WHERE id = :id LIMIT 1');
            $ste->execute([':id' => $etage_id]);
            $et = $ste->fetch(PDO::FETCH_ASSOC);
            if ($et) {
                $raw = trim((string) ($et['code_abrege'] ?? ''));
                if ($raw === '') {
                    $raw = trim((string) ($et['code'] ?? ''));
                }
                if ($raw === '') {
                    $raw = 'E' . (int) ($et['numero_etage'] ?? 0);
                }
                $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
            }
        } catch (PDOException $e) {
            // keep default
        }
    }
    if ($code === '') {
        $code = 'N';
    }
    if (strlen($code) > 10) {
        $code = substr($code, 0, 10);
    }

    $num_etiq = max(1, (int) ($noeud_etiq['numero'] ?? 1));
    $segment_lie = '';

    // Nom du champ / nœud lié sur le chemin (pas le numéro seul).
    if ($lie_type === 'niveau' && $lie_niveau_id > 0) {
        foreach ($chemin as $n) {
            $nid = (int) ($n['niveau_id'] ?? 0);
            if ($nid === $etiq_niveau_id) {
                break;
            }
            if ($nid === $lie_niveau_id) {
                $segment_lie = entrepot_noeud_etiquette_segment_lie(
                    (string) ($n['nom'] ?? ''),
                    (int) ($n['numero'] ?? 1)
                );
                break;
            }
        }
    }

    // Format : {code_abrégé}[{nom_lie}]-{n°_étiquette}
    // — lié à Niveau (étage) : C-01
    // — lié à un autre niveau : CA1-01 (nom du nœud lié)
    if ($lie_type === 'niveau' && $segment_lie !== '') {
        return sprintf('%s%s-%02d', $code, $segment_lie, $num_etiq);
    }

    return sprintf('%s-%02d', $code, $num_etiq);
}

/**
 * Génère (si besoin) le QR PNG d’un nœud étiquette.
 *
 * @param int $noeud_id
 * @param string $libelle
 * @param bool $force
 * @return string Chemin web /upload/... ou ''
 */
function entrepot_noeud_etiquette_qr_web_path($noeud_id, $libelle = '', $force = false)
{
    $noeud_id = (int) $noeud_id;
    if ($noeud_id <= 0) {
        return '';
    }
    $dir = __DIR__ . '/../upload/qrcodes/';
    $file = $dir . 'noeud_' . $noeud_id . '.png';
    $meta = $dir . 'noeud_' . $noeud_id . '.txt';
    $web = '/upload/qrcodes/noeud_' . $noeud_id . '.png';
    $libelle = trim((string) $libelle);
    if ($libelle === '') {
        $libelle = entrepot_noeud_etiquette_libelle($noeud_id);
    }
    if ($libelle === '') {
        return '';
    }
    $prev = is_file($meta) ? trim((string) file_get_contents($meta)) : '';
    if (!$force && is_file($file) && $prev === $libelle) {
        return $web;
    }
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return is_file($file) ? $web : '';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale' => 6,
            'imageBase64' => false,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $png = $qr->render($libelle);
        if (!is_string($png) || $png === '') {
            return is_file($file) ? $web : '';
        }
        if (file_put_contents($file, $png) === false || !is_file($file)) {
            return '';
        }
        @file_put_contents($meta, $libelle);

        return $web;
    } catch (Throwable $e) {
        return is_file($file) ? $web : '';
    }
}

/**
 * Données d’affichage étiquette pour un nœud (niveau étiquette / QR).
 *
 * @param int $noeud_id
 * @return array<string, mixed>|null
 */
function entrepot_noeud_etiquette_payload($noeud_id)
{
    $noeud_id = (int) $noeud_id;
    if ($noeud_id <= 0) {
        return null;
    }
    $etiq_def = entrepot_hierarchie_def_etiquette();
    if ($etiq_def === null) {
        return null;
    }
    $noeud = entrepot_noeud_get($noeud_id);
    if ($noeud === null) {
        return null;
    }
    if ((int) ($noeud['niveau_id'] ?? 0) !== (int) ($etiq_def['id'] ?? 0)) {
        return null;
    }

    $libelle = entrepot_noeud_etiquette_libelle($noeud_id);
    if ($libelle === '') {
        return null;
    }

    $legacy_barre_id = 0;
    if (($noeud['legacy_table'] ?? '') === 'entrepot_barre' && !empty($noeud['legacy_id'])) {
        $legacy_barre_id = (int) $noeud['legacy_id'];
    }

    // PDF toujours via nœud (libellé = code abrégé + lié + barre).
    $pdf_url = 'emplacement-noeud-etiquette.php?id=' . $noeud_id;
    $print_key = 'n' . $noeud_id;
    $qr = '';

    if ($legacy_barre_id > 0) {
        require_once __DIR__ . '/../includes/entrepot_barcode_service.php';
        entrepot_generer_codes_barre($legacy_barre_id);
        $qr = get_qrcode_barre_web_path($legacy_barre_id);
    }
    if ($qr === '') {
        $qr = entrepot_noeud_etiquette_qr_web_path($noeud_id, $libelle, true);
    }

    return [
        'libelle' => $libelle,
        'qr_url' => $qr,
        'pdf_url' => $pdf_url,
        'print_key' => $print_key,
        'legacy_barre_id' => $legacy_barre_id > 0 ? $legacy_barre_id : null,
    ];
}

/**
 * @param int $id
 * @param int $actif
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_def_set_actif($id, $actif)
{
    global $db;
    $id = (int) $id;
    $def = entrepot_hierarchie_def_get($id);
    if ($def === null) {
        return ['success' => false, 'message' => 'Niveau introuvable.'];
    }
    if (entrepot_hierarchie_def_est_etage($def) && !$actif) {
        return ['success' => false, 'message' => 'Le niveau « Niveau » (étages) ne peut pas être désactivé.'];
    }
    $actif = $actif ? 1 : 0;
    if ($actif === 0) {
        $actifs = entrepot_hierarchie_def_list(true);
        if (count($actifs) <= 1) {
            return ['success' => false, 'message' => 'Au moins un niveau actif est requis.'];
        }
    }
    try {
        $db->prepare('UPDATE entrepot_hierarchie_niveau SET actif = :a WHERE id = :id')
            ->execute([':a' => $actif, ':id' => $id]);

        return ['success' => true, 'message' => $actif ? 'Niveau activé.' : 'Niveau désactivé.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param array<int, int> $ids_ordonnes
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_def_reordonner(array $ids_ordonnes)
{
    global $db;
    if (!$db || !entrepot_hierarchie_libre_schema_ok()) {
        return ['success' => false, 'message' => 'Schéma indisponible.'];
    }
    $ids = [];
    foreach ($ids_ordonnes as $id) {
        $id = (int) $id;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    if ($ids === []) {
        return ['success' => false, 'message' => 'Aucun niveau à réordonner.'];
    }
    $has_noeuds = (int) $db->query('SELECT COUNT(*) FROM entrepot_hierarchie_noeud')->fetchColumn() > 0;
    try {
        $ordre = 10;
        $st = $db->prepare('UPDATE entrepot_hierarchie_niveau SET ordre = :o WHERE id = :id');
        foreach ($ids as $id) {
            $st->execute([':o' => $ordre, ':id' => $id]);
            $ordre += 10;
        }
        $msg = 'Ordre de la hiérarchie enregistré.';
        if ($has_noeuds) {
            $msg .= ' Attention : des éléments existent déjà — vérifiez la cohérence parent/enfant.';
        }

        return ['success' => true, 'message' => $msg];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $niveau_id
 * @return array{noeuds: int, produits: int, descendants: int}
 */
function entrepot_hierarchie_def_impact_suppression($niveau_id)
{
    global $db;
    $niveau_id = (int) $niveau_id;
    $out = ['noeuds' => 0, 'produits' => 0, 'descendants' => 0];
    if ($niveau_id <= 0 || !entrepot_hierarchie_libre_schema_ok()) {
        return $out;
    }
    $def = entrepot_hierarchie_def_get($niveau_id);
    if (entrepot_hierarchie_def_est_etage($def)) {
        try {
            $out['noeuds'] = (int) $db->query('SELECT COUNT(*) FROM entrepot_etage')->fetchColumn();
            if (function_exists('entrepot_hierarchie_libre_schema_ok')) {
                $out['produits'] = (int) $db->query(
                    'SELECT COUNT(*) FROM produits WHERE entrepot_noeud_id IS NOT NULL AND entrepot_noeud_id > 0'
                )->fetchColumn();
            }
        } catch (PDOException $e) {
            // ignore
        }

        return $out;
    }
    try {
        $st = $db->prepare('SELECT id FROM entrepot_hierarchie_noeud WHERE niveau_id = :n');
        $st->execute([':n' => $niveau_id]);
        $root_ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $out['noeuds'] = count($root_ids);
        $all = entrepot_noeud_collect_ids_avec_descendants($root_ids);
        $out['descendants'] = max(0, count($all) - count($root_ids));
        $out['produits'] = entrepot_noeud_compter_produits($all);
    } catch (PDOException $e) {
        // ignore
    }

    return $out;
}

/**
 * @param int $id
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_def_supprimer($id)
{
    global $db;
    $id = (int) $id;
    $def = entrepot_hierarchie_def_get($id);
    if ($def === null) {
        return ['success' => false, 'message' => 'Niveau introuvable.'];
    }
    if (entrepot_hierarchie_def_est_etage($def)) {
        return ['success' => false, 'message' => 'Le niveau « Niveau » (étages) est système et ne peut pas être supprimé.'];
    }
    $total = (int) $db->query('SELECT COUNT(*) FROM entrepot_hierarchie_niveau')->fetchColumn();
    if ($total <= 1) {
        return ['success' => false, 'message' => 'Impossible de supprimer le dernier niveau.'];
    }
    $label = (string) ($def['label'] ?? '');
    $impact = entrepot_hierarchie_def_impact_suppression($id);
    try {
        $st = $db->prepare('SELECT id FROM entrepot_hierarchie_noeud WHERE niveau_id = :n');
        $st->execute([':n' => $id]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $all = entrepot_noeud_collect_ids_avec_descendants($ids);
        entrepot_noeud_detacher_produits($all);
        if ($all !== []) {
            $placeholders = implode(',', array_fill(0, count($all), '?'));
            // Supprimer enfants d'abord (FK CASCADE sur parent_id devrait suffire si on delete roots)
            $db->prepare('DELETE FROM entrepot_hierarchie_noeud WHERE id IN (' . $placeholders . ')')->execute($all);
        }
        $db->prepare('DELETE FROM entrepot_hierarchie_niveau WHERE id = :id')->execute([':id' => $id]);
        $msg = 'Niveau « ' . $label . ' » supprimé.';
        if ($impact['noeuds'] > 0 || $impact['produits'] > 0) {
            $msg .= ' ' . (int) $impact['noeuds'] . ' élément(s) et '
                . (int) $impact['produits'] . ' produit(s) détaché(s).';
        }

        return ['success' => true, 'message' => $msg];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param array<int, int> $ids
 * @return array<int, int>
 */
function entrepot_noeud_collect_ids_avec_descendants(array $ids)
{
    global $db;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($ids === [] || !$db) {
        return [];
    }
    $all = $ids;
    $frontier = $ids;
    try {
        while ($frontier !== []) {
            $placeholders = implode(',', array_fill(0, count($frontier), '?'));
            $st = $db->prepare(
                'SELECT id FROM entrepot_hierarchie_noeud WHERE parent_id IN (' . $placeholders . ')'
            );
            $st->execute($frontier);
            $children = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $frontier = [];
            foreach ($children as $cid) {
                if (!in_array($cid, $all, true)) {
                    $all[] = $cid;
                    $frontier[] = $cid;
                }
            }
        }
    } catch (PDOException $e) {
        return $ids;
    }

    return $all;
}

/**
 * @param array<int, int> $noeud_ids
 * @return int
 */
function entrepot_noeud_compter_produits(array $noeud_ids)
{
    global $db;
    if (!function_exists('produits_has_column')) {
        require_once __DIR__ . '/model_produits.php';
    }
    $noeud_ids = array_values(array_unique(array_filter(array_map('intval', $noeud_ids))));
    if ($noeud_ids === [] || !$db || !produits_has_column('entrepot_noeud_id')) {
        return 0;
    }
    try {
        $placeholders = implode(',', array_fill(0, count($noeud_ids), '?'));
        $st = $db->prepare(
            'SELECT COUNT(*) FROM produits WHERE entrepot_noeud_id IN (' . $placeholders . ')'
        );
        $st->execute($noeud_ids);

        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param array<int, int> $noeud_ids
 * @return int
 */
function entrepot_noeud_detacher_produits(array $noeud_ids)
{
    global $db;
    if (!function_exists('produits_has_column')) {
        require_once __DIR__ . '/model_produits.php';
    }
    $count = entrepot_noeud_compter_produits($noeud_ids);
    if ($count <= 0 || !produits_has_column('entrepot_noeud_id')) {
        return 0;
    }
    $noeud_ids = array_values(array_unique(array_filter(array_map('intval', $noeud_ids))));
    try {
        $placeholders = implode(',', array_fill(0, count($noeud_ids), '?'));
        $db->prepare(
            'UPDATE produits SET entrepot_noeud_id = NULL WHERE entrepot_noeud_id IN (' . $placeholders . ')'
        )->execute($noeud_ids);

        return $count;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function entrepot_noeud_get($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !entrepot_hierarchie_libre_schema_ok()) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT * FROM entrepot_hierarchie_noeud WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param int $etage_id
 * @param int $niveau_id
 * @param int $parent_id
 * @return array<int, array<string, mixed>>
 */
function entrepot_noeud_liste($etage_id, $niveau_id = 0, $parent_id = -1)
{
    global $db;
    $etage_id = (int) $etage_id;
    if ($etage_id <= 0 || !entrepot_hierarchie_libre_schema_ok()) {
        return [];
    }
    try {
        $sql = 'SELECT * FROM entrepot_hierarchie_noeud WHERE etage_id = :e';
        $params = [':e' => $etage_id];
        if ($niveau_id > 0) {
            $sql .= ' AND niveau_id = :n';
            $params[':n'] = (int) $niveau_id;
        }
        if ($parent_id === 0) {
            $sql .= ' AND parent_id IS NULL';
        } elseif ($parent_id > 0) {
            $sql .= ' AND parent_id = :p';
            $params[':p'] = (int) $parent_id;
        }
        $sql .= ' ORDER BY numero ASC, id ASC';
        $st = $db->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $etage_id
 * @param int $niveau_id
 * @param int $parent_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string, noeud?: array<string, mixed>}
 */
function entrepot_noeud_ajouter($etage_id, $niveau_id, $parent_id, $nom, $numero = 0)
{
    global $db;
    $etage_id = (int) $etage_id;
    $niveau_id = (int) $niveau_id;
    $parent_id = (int) $parent_id;
    $nom = trim((string) $nom);
    if (!entrepot_hierarchie_libre_schema_ok() || !$db) {
        return ['success' => false, 'message' => 'Schéma indisponible.'];
    }
    if ($etage_id <= 0 || $niveau_id <= 0) {
        return ['success' => false, 'message' => 'Étage ou niveau invalide.'];
    }
    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom est obligatoire.'];
    }
    $defs = entrepot_hierarchie_def_list(true);
    $idx = -1;
    foreach ($defs as $i => $d) {
        if ((int) $d['id'] === $niveau_id) {
            $idx = $i;
            break;
        }
    }
    if ($idx < 0) {
        return ['success' => false, 'message' => 'Niveau inactif ou introuvable.'];
    }
    if (entrepot_hierarchie_def_est_etage($defs[$idx] ?? null)) {
        return ['success' => false, 'message' => 'Utilisez « Ajouter un niveau » pour créer un étage.'];
    }
    if ($idx === 0) {
        $parent_id = 0;
    } else {
        $prev = $defs[$idx - 1] ?? null;
        if (entrepot_hierarchie_def_est_etage($prev)) {
            // Parent = Niveau (étage) : pas de nœud parent, seulement etage_id
            $parent_id = 0;
        } else {
            if ($parent_id <= 0) {
                return ['success' => false, 'message' => 'Choisissez un élément parent.'];
            }
            $parent = entrepot_noeud_get($parent_id);
            $prev_id = (int) ($prev['id'] ?? 0);
            if ($parent === null || (int) ($parent['niveau_id'] ?? 0) !== $prev_id) {
                return ['success' => false, 'message' => 'Le parent doit appartenir au niveau précédent.'];
            }
            if ((int) ($parent['etage_id'] ?? 0) !== $etage_id) {
                return ['success' => false, 'message' => 'Le parent doit être sur le même étage.'];
            }
        }
    }
    if ($numero <= 0) {
        $sql = 'SELECT COALESCE(MAX(numero), 0) FROM entrepot_hierarchie_noeud WHERE etage_id = :e AND niveau_id = :n';
        $params = [':e' => $etage_id, ':n' => $niveau_id];
        if ($parent_id > 0) {
            $sql .= ' AND parent_id = :p';
            $params[':p'] = $parent_id;
        } else {
            $sql .= ' AND parent_id IS NULL';
        }
        $st = $db->prepare($sql);
        $st->execute($params);
        $numero = (int) $st->fetchColumn() + 1;
    } else {
        // Doublon uniquement parmi les éléments du même type (niveau_id) sous le même parent / étage
        $dup_sql = 'SELECT id FROM entrepot_hierarchie_noeud
                    WHERE etage_id = :e AND niveau_id = :n AND numero = :num';
        $dup_params = [':e' => $etage_id, ':n' => $niveau_id, ':num' => $numero];
        if ($parent_id > 0) {
            $dup_sql .= ' AND parent_id = :p';
            $dup_params[':p'] = $parent_id;
        } else {
            $dup_sql .= ' AND parent_id IS NULL';
        }
        $dup_sql .= ' LIMIT 1';
        $dup = $db->prepare($dup_sql);
        $dup->execute($dup_params);
        if ($dup->fetchColumn()) {
            return [
                'success' => false,
                'message' => 'Ce numéro existe déjà parmi les éléments du même type sous le même parent (pas de contrôle global).',
            ];
        }
    }
    try {
        $db->prepare(
            'INSERT INTO entrepot_hierarchie_noeud
             (etage_id, niveau_id, parent_id, numero, nom, date_creation)
             VALUES (:e, :n, :p, :num, :nom, NOW())'
        )->execute([
                    ':e' => $etage_id,
                    ':n' => $niveau_id,
                    ':p' => $parent_id > 0 ? $parent_id : null,
                    ':num' => $numero,
                    ':nom' => $nom,
                ]);
        $id = (int) $db->lastInsertId();

        return [
            'success' => true,
            'message' => 'Élément « ' . $nom . ' » ajouté.',
            'noeud' => entrepot_noeud_get($id),
        ];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro existe déjà parmi les éléments du même type sous le même parent.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string}
 */
function entrepot_noeud_modifier($id, $nom, $numero)
{
    global $db;
    $id = (int) $id;
    $noeud = entrepot_noeud_get($id);
    if ($noeud === null) {
        return ['success' => false, 'message' => 'Élément introuvable.'];
    }
    $nom = trim((string) $nom);
    $numero = max(1, (int) $numero);
    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom est obligatoire.'];
    }
    $etage_id = (int) ($noeud['etage_id'] ?? 0);
    $niveau_id = (int) ($noeud['niveau_id'] ?? 0);
    $parent_id = (int) ($noeud['parent_id'] ?? 0);
    $dup_sql = 'SELECT id FROM entrepot_hierarchie_noeud
                WHERE etage_id = :e AND niveau_id = :n AND numero = :num AND id != :id';
    $dup_params = [':e' => $etage_id, ':n' => $niveau_id, ':num' => $numero, ':id' => $id];
    if ($parent_id > 0) {
        $dup_sql .= ' AND parent_id = :p';
        $dup_params[':p'] = $parent_id;
    } else {
        $dup_sql .= ' AND parent_id IS NULL';
    }
    $dup_sql .= ' LIMIT 1';
    try {
        $dup = $db->prepare($dup_sql);
        $dup->execute($dup_params);
        if ($dup->fetchColumn()) {
            return [
                'success' => false,
                'message' => 'Ce numéro existe déjà parmi les éléments du même type sous le même parent.',
            ];
        }
        $db->prepare(
            'UPDATE entrepot_hierarchie_noeud
             SET nom = :nom, numero = :num, date_modification = NOW()
             WHERE id = :id'
        )->execute([':nom' => $nom, ':num' => $numero, ':id' => $id]);

        return ['success' => true, 'message' => 'Élément modifié.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro existe déjà parmi les éléments du même type sous le même parent.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @return array{success: bool, message: string}
 */
function entrepot_noeud_supprimer($id)
{
    global $db;
    $id = (int) $id;
    $noeud = entrepot_noeud_get($id);
    if ($noeud === null) {
        return ['success' => false, 'message' => 'Élément introuvable.'];
    }
    $all = entrepot_noeud_collect_ids_avec_descendants([$id]);
    $produits = entrepot_noeud_detacher_produits($all);
    try {
        $placeholders = implode(',', array_fill(0, count($all), '?'));
        $db->prepare('DELETE FROM entrepot_hierarchie_noeud WHERE id IN (' . $placeholders . ')')->execute($all);
        $msg = 'Élément « ' . ($noeud['nom'] ?? '') . ' » supprimé.';
        if (count($all) > 1) {
            $msg .= ' ' . (count($all) - 1) . ' enfant(s) retiré(s).';
        }
        if ($produits > 0) {
            $msg .= ' ' . $produits . ' produit(s) détaché(s).';
        }

        return ['success' => true, 'message' => $msg];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Chemin libellé d’un nœud (racine → feuille).
 *
 * @param int $noeud_id
 * @return string
 */
function entrepot_noeud_chemin_libelle($noeud_id)
{
    global $db;
    $noeud_id = (int) $noeud_id;
    if ($noeud_id <= 0 || !entrepot_hierarchie_libre_schema_ok()) {
        return '';
    }
    $parts = [];
    $current = $noeud_id;
    $guard = 0;
    try {
        $st = $db->prepare('SELECT id, parent_id, nom, etage_id FROM entrepot_hierarchie_noeud WHERE id = :id LIMIT 1');
        while ($current > 0 && $guard < 40) {
            $guard++;
            $st->execute([':id' => $current]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                break;
            }
            array_unshift($parts, (string) ($row['nom'] ?? ''));
            $current = (int) ($row['parent_id'] ?? 0);
            if ($guard === 1) {
                $etage_id = (int) ($row['etage_id'] ?? 0);
                if ($etage_id > 0 && function_exists('entrepot_get_etage_ref_by_id')) {
                    // filled below
                } elseif ($etage_id > 0) {
                    require_once __DIR__ . '/model_entrepot_referentiel.php';
                }
            }
        }
        // Préfixer nom étage
        $noeud = entrepot_noeud_get($noeud_id);
        if ($noeud && function_exists('entrepot_get_etage_by_id') === false) {
            require_once __DIR__ . '/model_entrepot_referentiel.php';
        }
        if ($noeud) {
            $etage_id = (int) ($noeud['etage_id'] ?? 0);
            if ($etage_id > 0) {
                try {
                    $ste = $db->prepare('SELECT nom, numero_etage FROM entrepot_etage WHERE id = :id LIMIT 1');
                    $ste->execute([':id' => $etage_id]);
                    $et = $ste->fetch(PDO::FETCH_ASSOC);
                    if ($et) {
                        $enom = trim((string) ($et['nom'] ?? ''));
                        if ($enom === '') {
                            $enom = 'Étage ' . (int) ($et['numero_etage'] ?? 0);
                        }
                        array_unshift($parts, $enom);
                    }
                } catch (PDOException $e) {
                    // ignore
                }
            }
        }
    } catch (PDOException $e) {
        return '';
    }

    return implode(' · ', array_filter($parts));
}

/**
 * Arbre d’un étage selon defs actives (enfants imbriqués).
 *
 * @param int $numero_etage
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_arbre_etage($numero_etage)
{
    global $db;
    $numero_etage = (int) $numero_etage;
    if ($numero_etage <= 0 || !entrepot_hierarchie_libre_schema_ok()) {
        return null;
    }
    require_once __DIR__ . '/model_entrepot_referentiel.php';
    $etage = entrepot_get_etage_ref_by_numero($numero_etage);
    if ($etage === null) {
        return null;
    }
    $etage_id = (int) $etage['id'];
    $defs = entrepot_hierarchie_def_list_noeuds(true);
    $etiq_def = entrepot_hierarchie_def_etiquette();
    $etiq_niveau_id = $etiq_def ? (int) ($etiq_def['id'] ?? 0) : 0;
    $all = entrepot_noeud_liste($etage_id);
    $by_parent = [];
    foreach ($all as $n) {
        $pid = (int) ($n['parent_id'] ?? 0);
        if (!isset($by_parent[$pid])) {
            $by_parent[$pid] = [];
        }
        $by_parent[$pid][] = $n;
    }
    $build = function ($parent_id, $depth) use (&$build, $by_parent, $etiq_niveau_id) {
        $nodes = $by_parent[$parent_id] ?? [];
        $out = [];
        foreach ($nodes as $n) {
            $nid = (int) $n['id'];
            $niveau_id = (int) ($n['niveau_id'] ?? 0);
            $node = [
                'id' => $nid,
                'niveau_id' => $niveau_id,
                'parent_id' => (int) ($n['parent_id'] ?? 0),
                'numero' => (int) ($n['numero'] ?? 0),
                'nom' => (string) ($n['nom'] ?? ''),
                'legacy_table' => (string) ($n['legacy_table'] ?? ''),
                'legacy_id' => (int) ($n['legacy_id'] ?? 0),
                'enfants' => $build($nid, $depth + 1),
            ];
            if ($etiq_niveau_id > 0 && $niveau_id === $etiq_niveau_id) {
                $node['has_etiquette'] = true;
                $node['etiquette_print_key'] = 'n' . $nid;
            }
            $out[] = $node;
        }

        return $out;
    };

    $defs_out = [];
    foreach ($defs as $d) {
        $did = (int) ($d['id'] ?? 0);
        $defs_out[] = [
            'id' => $did,
            'label' => (string) ($d['label'] ?? ''),
            'icon' => (string) ($d['icon'] ?? 'fa-cube'),
            'slug' => (string) ($d['slug'] ?? ''),
            'est_etiquette_qr' => ($etiq_niveau_id > 0 && $did === $etiq_niveau_id) ? 1 : 0,
        ];
    }

    return [
        'etage' => $etage,
        'defs' => $defs_out,
        'racines' => $build(0, 0),
        'mode' => 'libre',
        'etiquette_niveau_id' => $etiq_niveau_id,
    ];
}

/**
 * JSON produit (hiérarchie libre) pour cascade dynamique.
 *
 * Format :
 * {
 *   mode: "libre",
 *   etages: [{ id, numero_etage, nom, code_abrege }],
 *   noeuds_par_niveau: { niveau_id: [{ id, nom, numero, parent_id, etage_id, etage_numero }] },
 *   defs: [...]
 * }
 *
 * @return array<string, mixed>
 */
function entrepot_hierarchie_libre_json_produit()
{
    $out = [
        'mode' => 'libre',
        'etages' => [],
        'noeuds_par_niveau' => [],
        'defs' => [],
    ];
    if (!entrepot_hierarchie_libre_schema_ok()) {
        return $out;
    }
    require_once __DIR__ . '/model_entrepot_referentiel.php';
    if (!function_exists('entrepot_hierarchie_liste_niveaux')) {
        require_once __DIR__ . '/model_entrepot_hierarchie.php';
    }

    $out['defs'] = entrepot_hierarchie_def_list(true);
    $etages = [];
    $etage_num_by_id = [];
    foreach (entrepot_hierarchie_liste_niveaux() as $et) {
        $eid = (int) ($et['id'] ?? 0);
        $n = (int) ($et['numero_etage'] ?? 0);
        if ($eid <= 0 || $n <= 0) {
            continue;
        }
        $etages[] = [
            'id' => $eid,
            'numero_etage' => $n,
            'nom' => (string) ($et['nom'] ?? ('Niveau ' . $n)),
            'code_abrege' => (string) ($et['code_abrege'] ?? ''),
        ];
        $etage_num_by_id[$eid] = $n;
    }
    $out['etages'] = $etages;

    $by_niveau = [];
    foreach (entrepot_hierarchie_def_list_noeuds(true) as $def) {
        $nid = (int) ($def['id'] ?? 0);
        if ($nid <= 0) {
            continue;
        }
        $liste = [];
        foreach ($etages as $et) {
            foreach (entrepot_noeud_liste((int) $et['id'], $nid) as $n) {
                $eid = (int) ($n['etage_id'] ?? 0);
                $liste[] = [
                    'id' => (int) ($n['id'] ?? 0),
                    'nom' => (string) ($n['nom'] ?? ''),
                    'numero' => (int) ($n['numero'] ?? 0),
                    'parent_id' => (int) ($n['parent_id'] ?? 0),
                    'etage_id' => $eid,
                    'etage_numero' => (int) ($etage_num_by_id[$eid] ?? 0),
                    'niveau_id' => $nid,
                ];
            }
        }
        $by_niveau[$nid] = $liste;
    }
    $out['noeuds_par_niveau'] = $by_niveau;

    return $out;
}

/**
 * @param int $noeud_id
 * @return array<int, int> map niveau_id => noeud_id along the path
 */
function entrepot_noeud_selection_path($noeud_id)
{
    $path = [];
    $current = (int) $noeud_id;
    $guard = 0;
    while ($current > 0 && $guard < 40) {
        $guard++;
        $n = entrepot_noeud_get($current);
        if ($n === null) {
            break;
        }
        $path[(int) $n['niveau_id']] = (int) $n['id'];
        $current = (int) ($n['parent_id'] ?? 0);
    }

    return $path;
}
