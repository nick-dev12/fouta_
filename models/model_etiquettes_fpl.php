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
        $ou[] = "(p.nom LIKE :q1 OR p.identifiant_interne LIKE :q2 OR COALESCE(p.reference_fpl, '') LIKE :q8 OR p.reference_oem LIKE :q3
                  OR ma.nom LIKE :q4
                  OR EXISTS (SELECT 1 FROM categories c2 WHERE c2.id = p.categorie_id AND c2.nom LIKE :q5)
                  OR EXISTS (SELECT 1 FROM sous_categories sc2 WHERE sc2.id = p.sous_categorie_id AND sc2.nom LIKE :q6)
                  OR p.reference_fournisseur LIKE :q7
                  OR " . produits_ref_normalise_sql('p.identifiant_interne') . " LIKE :qn
                  OR " . produits_ref_normalise_sql('COALESCE(p.reference_oem, \'\')') . " LIKE :qn
                  OR " . produits_ref_normalise_sql('COALESCE(p.reference_fournisseur, \'\')') . " LIKE :qn)";
        for ($i = 1; $i <= 8; $i++) {
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

        $stmt = $db->prepare("SELECT p.id, p.nom, p.identifiant_interne, p.reference_fpl, p.reference_oem,
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
        /* LES COTES EN MILLIMÈTRES (07/09, décision de la direction pour le
         * 150×60 : QR 43 mm, écriture 80 × 20 mm, écart 0,8 mm). Les
         * pourcentages ci-dessus ne savaient pas tenir une cote exacte — le QR
         * était plafonné à 26 mm avant l'échelle, et l'écriture n'avait pas de
         * hauteur réglable. Quand une de ces trois valeurs est donnée, elle
         * PRIME sur le pourcentage correspondant ; à null, tout se calcule
         * comme avant (aucun format existant ne bouge). */
        'qr_mm' => null,
        'texte_l_mm' => null,
        'texte_h_mm' => null,
    ];
}

/**
 * LA TAILLE DE POLICE QUI REMPLIT EXACTEMENT UNE BOÎTE (07/09).
 *
 * Mesurée sur LA police d'impression (Barlow Condensed 700, celle du PDF) et
 * À L'ÉCHELLE DU RENDU (12 pixels par millimètre) : mesurer à l'échelle du
 * millimètre arrondissait la boîte au pixel entier, soit ±1 mm d'erreur — et
 * pas la même d'une machine à l'autre (Windows et le serveur ne rendaient pas
 * le même « 20 mm »). On cherche donc par dichotomie la plus grande taille
 * dont la boîte MESURÉE tient dans largeur × hauteur, et on arrondit VERS LE
 * BAS : l'écriture ne déborde jamais de la cote demandée.
 *
 * Retourne des MILLIMÈTRES (l'unité de « code » dans la géométrie).
 *
 * @param string $libelle    le texte à poser (ex. « AR1-01 »)
 * @param float  $largeur_mm largeur de la boîte
 * @param float  $hauteur_mm hauteur de la boîte
 * @return float|null la taille de police en mm, null si la mesure est impossible
 */
function etiquette_barre_taille_police($libelle, $largeur_mm, $hauteur_mm)
{
    $libelle = trim((string) $libelle);
    $largeur_mm = (float) $largeur_mm;
    $hauteur_mm = (float) $hauteur_mm;
    if ($libelle === '' || $largeur_mm <= 0 || $hauteur_mm <= 0) {
        return null;
    }
    $police = dirname(__DIR__) . '/fonts/etiquette70/barlow-condensed-700.ttf';
    if (!is_file($police) || !function_exists('imagettfbbox')) {
        return null;
    }

    $ppm = 12.0;              /* pixels par mm — l'échelle du dessin du PDF */
    $ppp = 96.0 / 72.0;       /* points GD → pixels */
    $mesure = static function ($mm) use ($police, $libelle, $ppm, $ppp) {
        $b = @imagettfbbox(($mm * $ppm) / $ppp, 0, $police, $libelle);
        if (!is_array($b)) {
            return null;
        }

        return ['l' => abs($b[2] - $b[0]) / $ppm, 'h' => abs($b[7] - $b[1]) / $ppm];
    };

    if ($mesure(10.0) === null) {
        return null;
    }
    $bas = 0.0;
    $haut = max($largeur_mm, $hauteur_mm) * 4.0;   /* aucune police ne tient au-delà */
    for ($i = 0; $i < 24; $i++) {
        $milieu = ($bas + $haut) / 2;
        $m = $mesure($milieu);
        if ($m !== null && $m['l'] <= $largeur_mm && $m['h'] <= $hauteur_mm) {
            $bas = $milieu;
        } else {
            $haut = $milieu;
        }
    }
    if ($bas <= 0) {
        return null;
    }

    return floor($bas * 100) / 100;   /* jamais au-dessus de la cote demandée */
}

/**
 * Normalise une disposition de barre venue de l'extérieur (corps JSON du
 * panneau « Régler la disposition », ou paramètres d'URL du PDF) : mêmes
 * bornes partout, une seule source de vérité (07/09).
 *
 * @param array<string, mixed> $src
 * @return array{qr_position:string,qr_echelle:int,code_echelle:int,decal_x:float,decal_y:float,marge:float|null,ecart:float|null}
 */
function etiquette_disposition_barre_normaliser(array $src)
{
    $num = static function ($v) { return $v !== null && $v !== '' && is_numeric($v); };

    return [
        'qr_position' => isset($src['qr_position']) && $src['qr_position'] === 'gauche' ? 'gauche' : 'droite',
        'qr_echelle' => max(40, min(170, $num($src['qr_echelle'] ?? null) ? (int) $src['qr_echelle'] : 100)),
        'code_echelle' => max(40, min(170, $num($src['code_echelle'] ?? null) ? (int) $src['code_echelle'] : 100)),
        'decal_x' => max(-20, min(20, $num($src['decal_x'] ?? null) ? (float) $src['decal_x'] : 0.0)),
        'decal_y' => max(-20, min(20, $num($src['decal_y'] ?? null) ? (float) $src['decal_y'] : 0.0)),
        'marge' => $num($src['marge'] ?? null) ? max(0, min(15, (float) $src['marge'])) : null,
        'ecart' => $num($src['ecart'] ?? null) ? max(0, min(20, (float) $src['ecart'])) : null,
        /* les cotes absolues : vides = automatique (le format ne change pas) */
        'qr_mm' => $num($src['qr_mm'] ?? null) ? max(5, min(200, round((float) $src['qr_mm'], 2))) : null,
        'texte_l_mm' => $num($src['texte_l_mm'] ?? null) ? max(5, min(400, round((float) $src['texte_l_mm'], 2))) : null,
        'texte_h_mm' => $num($src['texte_h_mm'] ?? null) ? max(2, min(200, round((float) $src['texte_h_mm'], 2))) : null,
    ];
}

/** Une requête porte-t-elle une disposition de barre (au moins une clé) ? */
function etiquette_disposition_barre_dans_requete(array $src)
{
    foreach (array_keys(etiquette_layout_barre_defauts()) as $k) {
        if (isset($src[$k]) && $src[$k] !== '') {
            return true;
        }
    }

    return false;
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

    $n = max(1, mb_strlen(trim((string) $code)) ?: 6);

    // LE QR : la cote demandée si la direction en a fixé une (07/09), sinon le
    // calcul d'origine (dynamique selon la longueur du code et le format).
    if ($l['qr_mm'] !== null) {
        $qr = round(max(1.0, min((float) $l['qr_mm'], $utileH, $utileL)), 2);
    } else {
        $qrBase = min($utileH * 0.55, $utileL * 0.25);
        $depassement = max(0, $n - 6);
        $qr = $qrBase - ($depassement * 1.0);
        $qr = max($qr, min(14.5, $utileH));
        $qr = min($qr, 26.0);
        $qr = $qr * ((int) $l['qr_echelle']) / 100;
        $qr = max(min(10.0, $utileH), min($qr, min($utileH, $utileL * 0.6)));
        $qr = round($qr, 2);
    }

    // L'ÉCRITURE : sa boîte, puis la plus grande police qui la remplit.
    // Boîte demandée en mm = elle prime ; sinon toute la place restante et la
    // règle d'avant (largeur estimée au caractère, modulée par le pourcentage).
    $restant = max(1.0, $utileL - $qr - $gap);
    $largeurCode = $l['texte_l_mm'] !== null
        ? round(min((float) $l['texte_l_mm'], $restant), 2)
        : round($restant, 2);
    $hauteurCode = $l['texte_h_mm'] !== null
        ? round(min((float) $l['texte_h_mm'], $utileH), 2)
        : null;

    if ($l['texte_l_mm'] !== null || $l['texte_h_mm'] !== null) {
        $mesure = etiquette_barre_taille_police($code, $largeurCode, $hauteurCode !== null ? $hauteurCode : $utileH * 0.92);
        $taille = $mesure !== null
            ? $mesure
            : min($largeurCode / ($n * 0.62), $hauteurCode !== null ? $hauteurCode : $utileH * 0.92);
    } else {
        $taille = min($largeurCode / ($n * 0.62), $utileH * 0.92);
        $taille = $taille * ((int) $l['code_echelle']) / 100;
    }

    return [
        'largeur' => $largeur,
        'hauteur' => $hauteur,
        'pad' => $pad,
        'gap' => $gap,
        'qr' => $qr,
        'code_largeur' => round($largeurCode, 2),
        'code_hauteur' => $hauteurCode,
        'caracteres' => $n,
        'code' => round($taille, 2),
        'qr_position' => $l['qr_position'],
        'decal_x' => round((float) $l['decal_x'], 2),
        'decal_y' => round((float) $l['decal_y'], 2),
        'code_echelle' => (int) $l['code_echelle'],
        'qr_echelle' => (int) $l['qr_echelle'],
        'marge_auto' => $l['marge'] === null,
        'ecart_auto' => $l['ecart'] === null,
        'qr_mm' => $l['qr_mm'],
        'texte_l_mm' => $l['texte_l_mm'],
        'texte_h_mm' => $l['texte_h_mm'],
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
