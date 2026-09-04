<?php
/**
 * TOUTES LES ÉTIQUETTES — les listes (pièces et barres) et la trace des
 * impressions : qui a imprimé quoi, quand, et si c'était un marquage à la
 * main. Programmation procédurale uniquement
 *
 * Portage de fpl_natif/models/model_etiquettes.php, aux tables de CE dépôt :
 *   - les pièces vivent dans `produits` (image = image_principale) ;
 *   - les barres sont les nœuds du niveau `barre` de la hiérarchie libre
 *     (entrepot_hierarchie_noeud + entrepot_hierarchie_niveau, slug 'barre',
 *     le niveau qui porte l'étiquette QR — est_etiquette_qr = 1) ;
 *   - la trace vit dans `etiquette_impressions`
 *     (migrations/run_etiquette_impressions.php) ;
 *   - les utilisateurs vivent dans `admin` (prenom, nom).
 */

require_once __DIR__ . '/../conn/conn.php';
// Les formats (table etiquette_formats) et leurs aides vivent là :
require_once __DIR__ . '/model_produit_etiquette_parametres.php';

/** La table des traces est-elle là ? (une vérification par requête) */
function etiquette_impressions_table_ok()
{
    static $ok = null;
    global $db;

    if ($ok === null) {
        $ok = false;
        try {
            $s = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etiquette_impressions'");
            $ok = (int) $s->fetchColumn() > 0;
        } catch (PDOException $e) {
            $ok = false;
        }
    }

    return $ok;
}

/** La dernière trace d'impression d'une cible, ou false. */
function etiquette_derniere_impression($type, $id)
{
    global $db;

    if (!etiquette_impressions_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT i.*, TRIM(CONCAT(COALESCE(a.prenom, ''), ' ', COALESCE(a.nom, ''))) AS admin_nom
                              FROM etiquette_impressions i
                              LEFT JOIN admin a ON a.id = i.admin_id
                              WHERE i.imprimable_type = :t AND i.imprimable_id = :id
                                AND i.sync_deleted_at IS NULL
                              ORDER BY i.date_impression DESC, i.id DESC
                              LIMIT 1");
        $stmt->execute(['t' => $type, 'id' => (int) $id]);
        $trace = $stmt->fetch(PDO::FETCH_ASSOC);

        return $trace ? $trace : false;
    } catch (PDOException $e) {
        return false;
    }
}

/** Écrit une trace d'impression : qui, quand, à la main ou non. */
function etiquette_tracer_impression($type, $id, $format_id, $admin_id, $manuel = false)
{
    global $db;

    if (!etiquette_impressions_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare("INSERT INTO etiquette_impressions
            (imprimable_type, imprimable_id, format_id, admin_id, manuel, date_impression, date_creation, date_modification)
            VALUES (:t, :id, :f, :a, :m, NOW(), NOW(), NOW())");

        return $stmt->execute(['t' => $type, 'id' => (int) $id, 'f' => $format_id ?: null,
            'a' => (int) $admin_id, 'm' => $manuel ? 1 : 0]);
    } catch (PDOException $e) {
        return false;
    }
}

/** Retire la DERNIÈRE trace (marquage par erreur). L'historique plus ancien reste. */
function etiquette_retirer_derniere_impression($type, $id)
{
    global $db;

    if (!etiquette_impressions_table_ok()) {
        return false;
    }
    try {
        $derniere = $db->prepare("SELECT id FROM etiquette_impressions
                                  WHERE imprimable_type = :t AND imprimable_id = :id
                                    AND sync_deleted_at IS NULL
                                  ORDER BY date_impression DESC, id DESC LIMIT 1");
        $derniere->execute(['t' => $type, 'id' => (int) $id]);
        $trace_id = $derniere->fetchColumn();
        if (!$trace_id) {
            return false;
        }
        // Suppression DOUCE : la trace reste lisible dans l'historique.
        $stmt = $db->prepare("UPDATE etiquette_impressions
                              SET sync_deleted_at = NOW(), date_modification = NOW()
                              WHERE id = :id");

        return $stmt->execute(['id' => (int) $trace_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Les PIÈCES de la liste des étiquettes, filtrées et paginées.
 * @return array{lignes: array, total: int, page: int, par: int, derniere: int}
 */
function etiquettes_pieces_liste($q, $etat, $du, $au, $page, $par)
{
    global $db;

    $ou = ['p.sync_deleted_at IS NULL'];
    $params = [];
    if ($q !== '') {
        /* Même recherche que le catalogue (elle cherchait la réf OEM mais PAS
           la réf FOURNISSEUR, ni en forme normalisée) : on ajoute la référence
           fournisseur et on compare les références en forme tolérante — O↔0,
           sans espaces ni tirets — pour retrouver la pièce quelle que soit la
           façon dont la référence est tapée ou stockée (FCS-BZAX-O16-2 =
           FCS-BZAX-016-2, « 131 900 » = « 131900 »). */
        require_once __DIR__ . '/model_produits.php';
        $norm = produits_ref_normalise($q);
        $ou[] = "(p.nom LIKE :q1 OR p.identifiant_interne LIKE :q2 OR p.reference_oem LIKE :q3
                  OR ma.nom LIKE :q4
                  OR EXISTS (SELECT 1 FROM categories c2 WHERE c2.id = p.categorie_id AND c2.nom LIKE :q5)
                  OR EXISTS (SELECT 1 FROM sous_categories sc2 WHERE sc2.id = p.sous_categorie_id AND sc2.nom LIKE :q6)
                  OR p.reference_fournisseur LIKE :q7
                  OR " . produits_ref_normalise_sql('p.identifiant_interne') . " LIKE :qn
                  OR " . produits_ref_normalise_sql('COALESCE(p.reference_oem, \'\')') . " LIKE :qn
                  OR " . produits_ref_normalise_sql('COALESCE(p.reference_fournisseur, \'\')') . " LIKE :qn)";
        for ($i = 1; $i <= 7; $i++) {
            $params['q' . $i] = '%' . $q . '%';
        }
        $params['qn'] = '%' . $norm . '%';
    }
    if ($etat === 'a_imprimer' && etiquette_impressions_table_ok()) {
        $ou[] = "NOT EXISTS (SELECT 1 FROM etiquette_impressions ei
                             WHERE ei.imprimable_type = 'produit' AND ei.imprimable_id = p.id
                               AND ei.sync_deleted_at IS NULL)";
    } elseif ($etat === 'imprimees' && etiquette_impressions_table_ok()) {
        $ou[] = "EXISTS (SELECT 1 FROM etiquette_impressions ei
                         WHERE ei.imprimable_type = 'produit' AND ei.imprimable_id = p.id
                           AND ei.sync_deleted_at IS NULL)";
    }
    if ($du) {
        $ou[] = 'DATE(p.date_creation) >= :du';
        $params['du'] = $du;
    }
    if ($au) {
        $ou[] = 'DATE(p.date_creation) <= :au';
        $params['au'] = $au;
    }
    $ouSql = implode(' AND ', $ou);

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits p LEFT JOIN marques ma ON ma.id = p.marque_id WHERE $ouSql");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $derniere = max(1, (int) ceil($total / $par));
        $page = min(max(1, $page), $derniere);

        $stmt = $db->prepare("SELECT p.id, p.nom, p.identifiant_interne, p.reference_oem,
                                     p.reference_fournisseur, p.image_principale,
                                     c.nom AS categorie_nom, sc.nom AS sous_categorie_nom
                              FROM produits p
                              LEFT JOIN marques ma ON ma.id = p.marque_id
                              LEFT JOIN categories c ON c.id = p.categorie_id
                              LEFT JOIN sous_categories sc ON sc.id = p.sous_categorie_id
                              WHERE $ouSql
                              ORDER BY p.nom
                              LIMIT " . (int) $par . ' OFFSET ' . (($page - 1) * $par));
        $stmt->execute($params);

        return ['lignes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total, 'page' => $page, 'par' => $par, 'derniere' => $derniere];
    } catch (PDOException $e) {
        return ['lignes' => [], 'total' => 0, 'page' => 1, 'par' => $par, 'derniere' => 1];
    }
}

/**
 * Les CONTENANTS de la liste des étiquettes : les nœuds des niveaux qui portent
 * l'étiquette QR — les barres ET les boxes (depuis le 04/09, piloté par
 * est_etiquette_qr plutôt que le seul slug 'barre'), filtrés et paginés.
 */
function etiquettes_barres_liste($q, $etat, $du, $au, $page, $par)
{
    global $db;

    $ou = ['v.est_etiquette_qr = 1', 'n.sync_deleted_at IS NULL'];
    $params = [];
    if ($q !== '') {
        $ou[] = '(n.nom LIKE :q1 OR n.numero LIKE :q2 OR n.code_scan LIKE :q3)';
        for ($i = 1; $i <= 3; $i++) {
            $params['q' . $i] = '%' . $q . '%';
        }
    }
    if ($etat === 'a_imprimer' && etiquette_impressions_table_ok()) {
        $ou[] = "NOT EXISTS (SELECT 1 FROM etiquette_impressions ei
                             WHERE ei.imprimable_type = 'noeud' AND ei.imprimable_id = n.id
                               AND ei.sync_deleted_at IS NULL)";
    } elseif ($etat === 'imprimees' && etiquette_impressions_table_ok()) {
        $ou[] = "EXISTS (SELECT 1 FROM etiquette_impressions ei
                         WHERE ei.imprimable_type = 'noeud' AND ei.imprimable_id = n.id
                           AND ei.sync_deleted_at IS NULL)";
    }
    if ($du) {
        $ou[] = 'DATE(n.date_creation) >= :du';
        $params['du'] = $du;
    }
    if ($au) {
        $ou[] = 'DATE(n.date_creation) <= :au';
        $params['au'] = $au;
    }
    $ouSql = implode(' AND ', $ou);

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM entrepot_hierarchie_noeud n
                              JOIN entrepot_hierarchie_niveau v ON v.id = n.niveau_id WHERE $ouSql");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $derniere = max(1, (int) ceil($total / $par));
        $page = min(max(1, $page), $derniere);

        $stmt = $db->prepare("SELECT n.* FROM entrepot_hierarchie_noeud n
                              JOIN entrepot_hierarchie_niveau v ON v.id = n.niveau_id
                              WHERE $ouSql
                              ORDER BY n.numero, n.nom
                              LIMIT " . (int) $par . ' OFFSET ' . (($page - 1) * $par));
        $stmt->execute($params);

        return ['lignes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total, 'page' => $page, 'par' => $par, 'derniere' => $derniere];
    } catch (PDOException $e) {
        return ['lignes' => [], 'total' => 0, 'page' => 1, 'par' => $par, 'derniere' => 1];
    }
}

/* =====================================================================
 *  L'ÉTIQUETTE DE BARRE — formats, disposition par format, géométrie
 *  AUTO-ADAPTÉE au contenu (portage de fpl_natif/model_etiquettes.php,
 *  25/08). La disposition vit dans etiquette_formats.disposition_barre
 *  (JSON) : position du QR, échelles, écart, décalages, marge.
 * ===================================================================== */

/** Les formats d'étiquette de BARRE, dans l'ordre. */
function etiquette_formats_barres()
{
    global $db;
    if (!fpl_etiquette_formats_table_ok()) {
        return [];
    }
    try {
        return $db->query("SELECT * FROM etiquette_formats
                           WHERE type = 'barre' AND sync_deleted_at IS NULL
                           ORDER BY ordre, id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Le format de barre par défaut : celui dont les mm sont ceux des réglages
 * d'entrepôt, sinon le premier — sinon un format de fortune bâti sur les
 * réglages (id 0 : la disposition ne se mémorise pas dessus).
 */
function etiquette_format_barre_defaut()
{
    $dims = function_exists('entrepot_etiquette_dims') ? entrepot_etiquette_dims() : null;
    $formats = etiquette_formats_barres();
    if ($dims !== null) {
        foreach ($formats as $f) {
            if (abs((float) $f['largeur_mm'] - (float) $dims['largeur_mm']) < 0.01
                && abs((float) $f['hauteur_mm'] - (float) $dims['hauteur_mm']) < 0.01) {
                return $f;
            }
        }
    }
    if ($formats !== []) {
        return $formats[0];
    }
    if ($dims !== null) {
        return ['id' => 0, 'nom' => rtrim(rtrim(number_format((float) $dims['largeur_mm'], 1, ',', ''), '0'), ',')
                . ' × ' . rtrim(rtrim(number_format((float) $dims['hauteur_mm'], 1, ',', ''), '0'), ',') . ' mm',
            'largeur_mm' => $dims['largeur_mm'], 'hauteur_mm' => $dims['hauteur_mm'],
            'disposition_barre' => null];
    }

    return false;
}

/** Les réglages de disposition par défaut (tout en automatique). */
function etiquette_layout_barre_defauts()
{
    return [
        'qr_position' => 'droite',
        'qr_echelle' => 100,
        'code_echelle' => 100,
        'decal_x' => 0.0,
        'decal_y' => 0.0,
        'marge' => null,
        'ecart' => null,
    ];
}

/** Les réglages effectifs d'un format de barre : défauts + écarts enregistrés. */
function etiquette_layout_barre($format)
{
    $defauts = etiquette_layout_barre_defauts();
    $enregistre = !empty($format['disposition_barre']) ? json_decode($format['disposition_barre'], true) : null;
    if (!is_array($enregistre)) {
        return $defauts;
    }

    return array_replace($defauts, array_intersect_key($enregistre, $defauts));
}

/**
 * Les cotes de l'étiquette de BARRE — proportionnelles au format ET au
 * contenu réel : le code prend la place que sa longueur permet, le QR se
 * règle sur la hauteur utile, les curseurs enregistrés modulent le tout.
 */
function etiquette_geometrie_barre($format, $code = '')
{
    $l = etiquette_layout_barre($format);
    $largeur = (float) $format['largeur_mm'];
    $hauteur = (float) $format['hauteur_mm'];

    $petit = min($largeur, $hauteur);
    $pad = $l['marge'] !== null
        ? round(min((float) $l['marge'], $petit / 2 - 1), 2)
        : round($petit * 3 / 40, 2);
    $gap = $l['ecart'] !== null ? round((float) $l['ecart'], 2) : $pad;

    $utileL = $largeur - 2 * $pad;
    $utileH = $hauteur - 2 * $pad;

    // Le QR : dynamique selon la longueur du code et le format
    $qrBase = min($utileH * 0.55, $utileL * 0.25);
    $n = max(1, mb_strlen(trim((string) $code)) ?: 6);
    $depassement = max(0, $n - 6);
    $qr = $qrBase - ($depassement * 1.0);
    $qr = max($qr, min(14.5, $utileH));
    $qr = min($qr, 26.0);
    $qr = $qr * ((int) $l['qr_echelle']) / 100;
    $qr = max(min(10.0, $utileH), min($qr, min($utileH, $utileL * 0.6)));
    $qr = round($qr, 2);

    // Le code : tout ce qui reste, à la taille que le contenu permet
    $largeurCode = max(1.0, $utileL - $qr - $gap);
    $taille = min($largeurCode / ($n * 0.62), $utileH * 0.92);
    $taille = $taille * ((int) $l['code_echelle']) / 100;

    return [
        'largeur' => $largeur,
        'hauteur' => $hauteur,
        'pad' => $pad,
        'gap' => $gap,
        'qr' => $qr,
        'code_largeur' => round($largeurCode, 2),
        'caracteres' => $n,
        'code' => round($taille, 2),
        'qr_position' => $l['qr_position'],
        'decal_x' => round((float) $l['decal_x'], 2),
        'decal_y' => round((float) $l['decal_y'], 2),
        'code_echelle' => (int) $l['code_echelle'],
        'qr_echelle' => (int) $l['qr_echelle'],
        'marge_auto' => $l['marge'] === null,
        'ecart_auto' => $l['ecart'] === null,
    ];
}

/** Enregistre (ou réinitialise) la disposition de barre d'un format. */
function etiquette_maj_disposition_barre($format_id, $disposition)
{
    global $db;

    if (!fpl_etiquette_formats_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare("UPDATE etiquette_formats SET disposition_barre = :d, date_modification = NOW() WHERE id = :id");

        return $stmt->execute([
            'd' => $disposition === null ? null : json_encode($disposition, JSON_UNESCAPED_UNICODE),
            'id' => (int) $format_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}
