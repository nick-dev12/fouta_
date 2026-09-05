<?php
/**
 * Emplacement entrepôt produit — champs, validation et rendu formulaire.
 */

require_once __DIR__ . '/../models/model_entrepot_emplacement.php';
require_once __DIR__ . '/../models/model_entrepot_referentiel.php';
require_once __DIR__ . '/../models/model_entrepot_structure_champs.php';
require_once __DIR__ . '/../models/model_entrepot_hierarchie_libre.php';

/**
 * @return bool
 */
function produit_emplacement_use_referentiel() {
    $has_link = (function_exists('produits_has_column') && produits_has_column('entrepot_position_id'))
        || (function_exists('produits_has_column') && produits_has_column('entrepot_noeud_id'));

    return $has_link
        && entrepot_referentiel_tables_ok()
        && entrepot_emplacement_est_configure();
}

/**
 * Encode JSON sûr pour balise <script type="application/json"> (sans htmlspecialchars).
 *
 * @param mixed $data
 * @return string
 */
function produit_emplacement_json_script($data) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $json = json_encode($data, $flags);

    return $json !== false ? $json : '{}';
}

/**
 * Métadonnées statiques des champs (min/max dynamiques via entrepot).
 *
 * @return array<string, array{label: string, icon: string, hint_tpl: string}>
 */
function produit_emplacement_champs_meta() {
    return [
        'etage' => [
            'label' => 'Étage (entrepôt)',
            'icon' => 'fa-warehouse',
            'hint_tpl' => 'Niveau de l’entrepôt (1 à %max%).',
        ],
        'numero_rayon' => [
            'label' => 'N° de rayon',
            'icon' => 'fa-th-large',
            'hint_tpl' => 'Numéro du rayon (1 à %max%).',
        ],
        'allee' => [
            'label' => 'Allée',
            'icon' => 'fa-road',
            'hint_tpl' => 'Allée empruntée pour rejoindre le rayon (1 à %max%).',
        ],
        'zone_emplacement' => [
            'label' => 'Zone',
            'icon' => 'fa-map-marker-alt',
            'hint_tpl' => 'Zone dans le rayon (1 à %max%).',
        ],
        'position_emplacement' => [
            'label' => 'Position',
            'icon' => 'fa-crosshairs',
            'hint_tpl' => 'Position précise dans la zone (1 à %max%).',
        ],
        'barre_rayon' => [
            'label' => 'Barre',
            'icon' => 'fa-grip-lines',
            'hint_tpl' => 'Barre / étagère du rayon (1 à %max%).',
        ],
    ];
}

/**
 * @param int|null $numero_etage
 * @return array<string, array{label: string, min: int, max: int, icon: string, hint: string}>
 */
function produit_emplacement_champs_config($numero_etage = null) {
    $meta = produit_emplacement_champs_meta();
    $out = [];
    foreach ($meta as $col => $m) {
        $limites = entrepot_emplacement_get_limites_champ($col, $numero_etage);
        $hint = str_replace('%max%', (string) $limites['max'], $m['hint_tpl']);
        $out[$col] = [
            'label' => $m['label'],
            'min' => $limites['min'],
            'max' => $limites['max'],
            'icon' => $m['icon'],
            'hint' => $hint,
        ];
    }

    return $out;
}

/**
 * @return bool
 */
function produit_emplacement_colonne_active($col) {
    if (!function_exists('produits_has_column')) {
        return false;
    }

    return produits_has_column($col);
}

/**
 * @param array<string, mixed> $source
 * @return array<string, string|null>
 */
function produit_emplacement_from_source(array $source) {
    if (produit_emplacement_use_referentiel()) {
        return produit_emplacement_from_source_referentiel($source);
    }

    $numero_etage = null;
    if (isset($source['etage']) && trim((string) $source['etage']) !== '' && ctype_digit((string) $source['etage'])) {
        $numero_etage = (int) $source['etage'];
    }

    $out = [];
    foreach (produit_emplacement_champs_meta() as $col => $meta) {
        if (!produit_emplacement_colonne_active($col)) {
            continue;
        }
        $raw = isset($source[$col]) ? trim((string) $source[$col]) : '';
        if ($raw === '') {
            $out[$col] = null;
            continue;
        }
        if (!ctype_digit($raw)) {
            $out[$col] = null;
            continue;
        }
        $n = (int) $raw;
        $limites = entrepot_emplacement_get_limites_champ($col, $col === 'etage' ? null : $numero_etage);

        if ($n < $limites['min'] || $n > $limites['max']) {
            $out[$col] = null;
            continue;
        }

        if ($col === 'etage') {
            $out[$col] = (string) $n;
            $numero_etage = $n;
            continue;
        }

        if ($numero_etage === null) {
            $out[$col] = null;
            continue;
        }

        $out[$col] = (string) $n;
    }

    return $out;
}

/**
 * Lit un ID entier positif depuis une source formulaire.
 *
 * @param array<string, mixed> $source
 * @param string $key
 * @return int
 */
function produit_emplacement_id_from_source(array $source, $key) {
    $raw = isset($source[$key]) ? trim((string) $source[$key]) : '';
    if ($raw === '' || !ctype_digit($raw)) {
        return 0;
    }

    return (int) $raw;
}

/**
 * Validation emplacement via référentiel nommé (choix indépendants + position).
 *
 * @param array<string, mixed> $source
 * @return array<string, string|null|int>
 */
function produit_emplacement_from_source_referentiel(array $source) {
    global $db;
    if (!function_exists('entrepot_hierarchie_libre_schema_ok')) {
        require_once __DIR__ . '/../models/model_entrepot_hierarchie_libre.php';
    }

    $out = [
        'entrepot_position_id' => null,
        'entrepot_noeud_id' => null,
        'etage' => null,
        'numero_rayon' => null,
        'allee' => null,
        'zone_emplacement' => null,
        'position_emplacement' => null,
        'barre_rayon' => null,
        'ref_numero_etage' => null,
        'ref_zone_id' => null,
        'ref_rayon_id' => null,
        'ref_etagere_id' => null,
        'ref_allee_id' => null,
        'ref_barre_id' => null,
        'chemin_libelle' => '',
    ];

    // Mode hiérarchie libre
    if (entrepot_hierarchie_libre_schema_ok()) {
        $numero_etage = produit_emplacement_id_from_source($source, 'ref_etage');
        if ($numero_etage <= 0 && isset($source['etage']) && ctype_digit((string) $source['etage'])) {
            $numero_etage = (int) $source['etage'];
        }
        $noeud_id = produit_emplacement_id_from_source($source, 'entrepot_noeud_id');
        if ($noeud_id <= 0) {
            // Dernier ref_niveau_* sélectionné
            $defs = entrepot_hierarchie_def_list(true);
            foreach (array_reverse($defs) as $def) {
                if (function_exists('entrepot_hierarchie_def_est_etage') && entrepot_hierarchie_def_est_etage($def)) {
                    continue;
                }
                $nid = (int) ($def['id'] ?? 0);
                $key = 'ref_niveau_' . $nid;
                $val = produit_emplacement_id_from_source($source, $key);
                if ($val > 0) {
                    $noeud_id = $val;
                    break;
                }
            }
        }
        if ($noeud_id <= 0 && $numero_etage <= 0) {
            return $out;
        }
        if ($noeud_id > 0) {
            $noeud = entrepot_noeud_get($noeud_id);
            if ($noeud === null) {
                return $out;
            }
            $out['entrepot_noeud_id'] = $noeud_id;
            $out['chemin_libelle'] = entrepot_noeud_chemin_libelle($noeud_id);
            $path = entrepot_noeud_selection_path($noeud_id);
            foreach ($path as $niveau_id => $nid) {
                $out['ref_niveau_' . (int) $niveau_id] = (string) (int) $nid;
            }
            if (!empty($noeud['etage_id'])) {
                try {
                    $st = $db->prepare('SELECT numero_etage FROM entrepot_etage WHERE id = :id LIMIT 1');
                    $st->execute([':id' => (int) $noeud['etage_id']]);
                    $num = (int) $st->fetchColumn();
                    if ($num > 0) {
                        $out['etage'] = (string) $num;
                        $out['ref_numero_etage'] = (string) $num;
                    }
                } catch (PDOException $e) {
                    // ignore
                }
            }
            if (($noeud['legacy_table'] ?? '') === 'entrepot_position' && !empty($noeud['legacy_id'])) {
                $out['entrepot_position_id'] = (int) $noeud['legacy_id'];
            }

            return $out;
        }
        if ($numero_etage > 0) {
            $out['etage'] = (string) $numero_etage;
            $out['ref_numero_etage'] = (string) $numero_etage;
        }

        return $out;
    }

    $numero_etage = produit_emplacement_id_from_source($source, 'ref_etage');
    if ($numero_etage <= 0 && isset($source['etage']) && ctype_digit((string) $source['etage'])) {
        $numero_etage = (int) $source['etage'];
    }
    $rayon_id = produit_emplacement_id_from_source($source, 'ref_rayon');
    $etagere_id = produit_emplacement_id_from_source($source, 'ref_etagere');
    $allee_id = produit_emplacement_id_from_source($source, 'ref_allee');
    $zone_id = produit_emplacement_id_from_source($source, 'ref_zone');
    $barre_id = produit_emplacement_id_from_source($source, 'ref_barre');
    $position_id = produit_emplacement_id_from_source($source, 'entrepot_position_id');
    $ref_champs_custom = [];
    foreach ($source as $k => $v) {
        if (strpos((string) $k, 'ref_champ_') !== 0) {
            continue;
        }
        $cid = (int) substr((string) $k, strlen('ref_champ_'));
        $eid = produit_emplacement_id_from_source($source, (string) $k);
        if ($cid > 0 && $eid > 0) {
            $ref_champs_custom[$cid] = $eid;
        }
    }

    // Aucune sélection
    if ($numero_etage <= 0 && $barre_id <= 0 && $position_id <= 0) {
        return $out;
    }

    if ($position_id > 0) {
        $st = $db->prepare(
            'SELECT p.id, p.numero, p.nom AS position_nom, p.barre_id,
                    b.numero AS barre_num, b.nom AS barre_nom, b.etage_id,
                    e.numero_etage, e.nom AS etage_nom
             FROM entrepot_position p
             INNER JOIN entrepot_barre b ON b.id = p.barre_id
             INNER JOIN entrepot_etage e ON e.id = b.etage_id
             WHERE p.id = :id LIMIT 1'
        );
        $st->execute([':id' => $position_id]);
        $pos = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pos) {
            return $out;
        }
        $barre_id = (int) $pos['barre_id'];
        $numero_etage = (int) $pos['numero_etage'];
        $out['entrepot_position_id'] = $position_id;
        $out['position_emplacement'] = (string) (int) $pos['numero'];
        $out['barre_rayon'] = (string) (int) $pos['barre_num'];
    } elseif ($barre_id > 0) {
        $st = $db->prepare(
            'SELECT b.id, b.numero, b.nom, b.champ_element_id, e.numero_etage, e.nom AS etage_nom
             FROM entrepot_barre b
             INNER JOIN entrepot_etage e ON e.id = b.etage_id
             WHERE b.id = :id LIMIT 1'
        );
        $st->execute([':id' => $barre_id]);
        $barre = $st->fetch(PDO::FETCH_ASSOC);
        if (!$barre) {
            return $out;
        }
        $numero_etage = (int) $barre['numero_etage'];
        $out['barre_rayon'] = (string) (int) $barre['numero'];
        if (!empty($barre['champ_element_id'])) {
            $out['ref_champ_lie_barre'] = (string) (int) $barre['champ_element_id'];
        }
    }

    if ($numero_etage > 0) {
        $out['etage'] = (string) $numero_etage;
        $out['ref_numero_etage'] = (string) $numero_etage;
    }

    require_once __DIR__ . '/../models/model_entrepot_hierarchie.php';
    $hierarchie_strict = entrepot_hierarchie_schema_ok();

    if ($hierarchie_strict && $position_id > 0) {
        $st = $db->prepare(
            'SELECT p.id AS position_id, p.numero AS position_num, p.barre_id,
                    b.numero AS barre_num, b.etagere_id, b.rayon_id,
                    r.zone_id, r.etage_id, e.numero_etage
             FROM entrepot_position p
             INNER JOIN entrepot_barre b ON b.id = p.barre_id
             INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
             INNER JOIN entrepot_etage e ON e.id = r.etage_id
             WHERE p.id = :id LIMIT 1'
        );
        $st->execute([':id' => $position_id]);
        $chain = $st->fetch(PDO::FETCH_ASSOC);
        if (!$chain) {
            return $out;
        }
        if ($barre_id > 0 && (int) $chain['barre_id'] !== $barre_id) {
            return $out;
        }
        if ($etagere_id > 0 && (int) ($chain['etagere_id'] ?? 0) !== $etagere_id) {
            return $out;
        }
        if ($rayon_id > 0 && (int) $chain['rayon_id'] !== $rayon_id) {
            return $out;
        }
        if ($zone_id > 0 && (int) ($chain['zone_id'] ?? 0) !== $zone_id) {
            return $out;
        }
        if ($numero_etage > 0 && (int) $chain['numero_etage'] !== $numero_etage) {
            return $out;
        }
    } elseif ($hierarchie_strict && $barre_id > 0) {
        $st = $db->prepare(
            'SELECT b.id, b.etagere_id, b.rayon_id, r.zone_id, e.numero_etage
             FROM entrepot_barre b
             INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
             INNER JOIN entrepot_etage e ON e.id = r.etage_id
             WHERE b.id = :id LIMIT 1'
        );
        $st->execute([':id' => $barre_id]);
        $chain = $st->fetch(PDO::FETCH_ASSOC);
        if (!$chain) {
            return $out;
        }
        if ($etagere_id > 0 && (int) ($chain['etagere_id'] ?? 0) !== $etagere_id) {
            return $out;
        }
        if ($rayon_id > 0 && (int) $chain['rayon_id'] !== $rayon_id) {
            return $out;
        }
        if ($zone_id > 0 && (int) ($chain['zone_id'] ?? 0) !== $zone_id) {
            return $out;
        }
        if ($numero_etage > 0 && (int) $chain['numero_etage'] !== $numero_etage) {
            return $out;
        }
    }

    // Appliquer uniquement les liens manuels explicitement choisis (legacy)
    if (!$hierarchie_strict && $barre_id > 0 && ($rayon_id > 0 || $allee_id > 0 || $zone_id > 0)) {
        entrepot_barre_definir_liens(
            $barre_id,
            $rayon_id > 0 ? $rayon_id : null,
            $allee_id > 0 ? $allee_id : null,
            $zone_id > 0 ? $zone_id : null
        );
    }

    $parts = [];
    $etage_ref = $numero_etage > 0 ? entrepot_get_etage_ref_by_numero($numero_etage) : null;
    if ($etage_ref) {
        $parts[] = (string) $etage_ref['nom'];
    }

    if ($zone_id > 0) {
        $st = $db->prepare('SELECT numero, nom FROM entrepot_zone WHERE id = :id LIMIT 1');
        $st->execute([':id' => $zone_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['zone_emplacement'] = (string) (int) $row['numero'];
            $out['ref_zone_id'] = (string) $zone_id;
            $parts[] = (string) $row['nom'];
        }
    }
    if ($etagere_id > 0) {
        $st = $db->prepare('SELECT numero, nom FROM entrepot_etagere WHERE id = :id LIMIT 1');
        $st->execute([':id' => $etagere_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['ref_etagere_id'] = (string) $etagere_id;
            $parts[] = (string) $row['nom'];
        }
    }
    if ($rayon_id > 0) {
        $st = $db->prepare('SELECT numero, nom FROM entrepot_rayon WHERE id = :id LIMIT 1');
        $st->execute([':id' => $rayon_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['numero_rayon'] = (string) (int) $row['numero'];
            $out['ref_rayon_id'] = (string) $rayon_id;
            $parts[] = (string) $row['nom'];
        }
    }
    if (!$hierarchie_strict && $allee_id > 0) {
        $st = $db->prepare('SELECT numero, nom FROM entrepot_allee WHERE id = :id LIMIT 1');
        $st->execute([':id' => $allee_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['allee'] = (string) (int) $row['numero'];
            $out['ref_allee_id'] = (string) $allee_id;
            $parts[] = (string) $row['nom'];
        }
    }
    if ($barre_id > 0) {
        $st = $db->prepare('SELECT numero, nom, champ_element_id FROM entrepot_barre WHERE id = :id LIMIT 1');
        $st->execute([':id' => $barre_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['barre_rayon'] = (string) (int) $row['numero'];
            $out['ref_barre_id'] = (string) $barre_id;
            $parts[] = (string) $row['nom'];
            if (!empty($row['champ_element_id'])) {
                $st_el = $db->prepare('SELECT nom FROM entrepot_champ_element WHERE id = :id LIMIT 1');
                $st_el->execute([':id' => (int) $row['champ_element_id']]);
                $el_nom = $st_el->fetchColumn();
                if ($el_nom !== false && $el_nom !== '') {
                    $parts[] = (string) $el_nom;
                    $out['ref_champ_lie_barre'] = (string) (int) $row['champ_element_id'];
                }
            }
        }
    }
    foreach ($ref_champs_custom as $cid => $eid) {
        $st = $db->prepare('SELECT nom FROM entrepot_champ_element WHERE id = :id AND champ_id = :c LIMIT 1');
        $st->execute([':id' => (int) $eid, ':c' => (int) $cid]);
        $nom_el = $st->fetchColumn();
        if ($nom_el !== false && $nom_el !== '') {
            $parts[] = (string) $nom_el;
            $out['ref_champ_' . (int) $cid] = (string) (int) $eid;
        }
    }
    if ($position_id > 0) {
        $st = $db->prepare('SELECT numero, nom FROM entrepot_position WHERE id = :id LIMIT 1');
        $st->execute([':id' => $position_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $out['position_emplacement'] = (string) (int) $row['numero'];
            $parts[] = (string) $row['nom'];
        }
    }

    // Compléter depuis la position si certains liens manuels absents
    if ($position_id > 0) {
        $legacy = entrepot_legacy_columns_from_position_id($position_id);
        foreach ($legacy as $col => $val) {
            if (($out[$col] === null || $out[$col] === '') && $val !== null && $val !== '') {
                $out[$col] = $val;
            }
        }
        $out['entrepot_position_id'] = $position_id;
    }

    $out['chemin_libelle'] = implode(' · ', array_filter($parts));

    return $out;
}

/**
 * Enrichit les valeurs formulaire avec métadonnées référentiel (cascade + chemin).
 *
 * @param array<string, mixed> $vals
 * @return array<string, string|null|int>
 */
function produit_emplacement_enrich_referentiel_form_values(array $vals) {
    if (!produit_emplacement_use_referentiel()) {
        return $vals;
    }
    // Déjà enrichi (POST avec choix manuels)
    if (!empty($vals['chemin_libelle']) && (!empty($vals['ref_barre_id']) || !empty($vals['entrepot_position_id']))) {
        return $vals;
    }
    if (empty($vals['entrepot_position_id'])) {
        return $vals;
    }
    $position_id = (int) $vals['entrepot_position_id'];
    if ($position_id <= 0) {
        return $vals;
    }
    $meta = entrepot_get_position_meta($position_id);
    $chemin = entrepot_get_chemin_complet($position_id);
    $vals['entrepot_position_id'] = (string) $position_id;
    if (empty($vals['chemin_libelle'])) {
        $vals['chemin_libelle'] = $chemin['libelle'] ?? '';
    }
    if (empty($vals['ref_numero_etage']) && $meta['numero_etage'] > 0) {
        $vals['ref_numero_etage'] = (string) $meta['numero_etage'];
    }
    if (empty($vals['ref_rayon_id']) && $meta['rayon_id'] > 0) {
        $vals['ref_rayon_id'] = (string) $meta['rayon_id'];
    }
    if (empty($vals['ref_allee_id']) && $meta['allee_id'] > 0) {
        $vals['ref_allee_id'] = (string) $meta['allee_id'];
    }
    if (empty($vals['ref_zone_id']) && $meta['zone_id'] > 0) {
        $vals['ref_zone_id'] = (string) $meta['zone_id'];
    }
    if (empty($vals['ref_etagere_id']) && !empty($meta['barre_id'])) {
        global $db;
        $st_et = $db->prepare('SELECT etagere_id FROM entrepot_barre WHERE id = :id LIMIT 1');
        $st_et->execute([':id' => (int) $meta['barre_id']]);
        $eid = (int) $st_et->fetchColumn();
        if ($eid > 0) {
            $vals['ref_etagere_id'] = (string) $eid;
        }
    }
    if (empty($vals['ref_barre_id']) && $meta['barre_id'] > 0) {
        $vals['ref_barre_id'] = (string) $meta['barre_id'];
    }
    if ($meta['barre_id'] > 0) {
        global $db;
        $st = $db->prepare('SELECT champ_element_id FROM entrepot_barre WHERE id = :id LIMIT 1');
        $st->execute([':id' => (int) $meta['barre_id']]);
        $el_id = (int) $st->fetchColumn();
        if ($el_id > 0) {
            $lie = entrepot_structure_champ_get_lie_barre();
            if ($lie !== null) {
                $vals['ref_champ_lie_barre'] = (string) $el_id;
            }
        }
    }

    return $vals;
}

/**
 * Valeurs pour le formulaire ajout / modification produit.
 *
 * @param array<string, mixed> $post
 * @param array<string, mixed>|null $produit
 * @return array<string, string|null|int>
 */
function produit_emplacement_form_values_for_form(array $post = [], $produit = null) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $post !== []) {
        return produit_emplacement_enrich_referentiel_form_values(produit_emplacement_from_source($post));
    }
    if ($produit !== null && is_array($produit)) {
        $p = $produit;
        if (
            produit_emplacement_use_referentiel()
            && empty($p['entrepot_position_id'])
            && !empty($p['etage'])
        ) {
            $resolved = entrepot_resolve_position_id_from_legacy($p);
            if ($resolved !== null && $resolved > 0) {
                $p['entrepot_position_id'] = $resolved;
            }
        }

        return produit_emplacement_from_produit($p);
    }

    return produit_emplacement_enrich_referentiel_form_values(produit_emplacement_from_source([]));
}

/**
 * @param array<string, mixed> $produit
 * @return array<string, string|null|int>
 */
function produit_emplacement_from_produit(array $produit) {
    if (!function_exists('entrepot_hierarchie_libre_schema_ok')) {
        require_once __DIR__ . '/../models/model_entrepot_hierarchie_libre.php';
    }
    if (produit_emplacement_use_referentiel() && entrepot_hierarchie_libre_schema_ok() && !empty($produit['entrepot_noeud_id'])) {
        $noeud_id = (int) $produit['entrepot_noeud_id'];
        $vals = [
            'entrepot_noeud_id' => $noeud_id,
            'chemin_libelle' => entrepot_noeud_chemin_libelle($noeud_id),
        ];
        $path = entrepot_noeud_selection_path($noeud_id);
        foreach ($path as $niveau_id => $nid) {
            $vals['ref_niveau_' . (int) $niveau_id] = (string) (int) $nid;
        }
        $noeud = entrepot_noeud_get($noeud_id);
        if ($noeud && !empty($noeud['etage_id'])) {
            global $db;
            try {
                $st = $db->prepare('SELECT numero_etage FROM entrepot_etage WHERE id = :id LIMIT 1');
                $st->execute([':id' => (int) $noeud['etage_id']]);
                $num = (int) $st->fetchColumn();
                if ($num > 0) {
                    $vals['etage'] = (string) $num;
                    $vals['ref_numero_etage'] = (string) $num;
                }
            } catch (PDOException $e) {
                // ignore
            }
        }
        if (($noeud['legacy_table'] ?? '') === 'entrepot_position' && !empty($noeud['legacy_id'])) {
            $vals['entrepot_position_id'] = (int) $noeud['legacy_id'];
        }

        return $vals;
    }
    if (produit_emplacement_use_referentiel() && !empty($produit['entrepot_position_id'])) {
        $position_id = (int) $produit['entrepot_position_id'];
        $legacy = entrepot_legacy_columns_from_position_id($position_id);
        $vals = $legacy;
        $vals['entrepot_position_id'] = $position_id;
        // Si nœud legacy existe, lier aussi
        if (entrepot_hierarchie_libre_schema_ok()) {
            global $db;
            try {
                $st = $db->prepare(
                    "SELECT id FROM entrepot_hierarchie_noeud
                     WHERE legacy_table = 'entrepot_position' AND legacy_id = :id LIMIT 1"
                );
                $st->execute([':id' => $position_id]);
                $nid = (int) $st->fetchColumn();
                if ($nid > 0) {
                    $vals['entrepot_noeud_id'] = $nid;
                    $vals['chemin_libelle'] = entrepot_noeud_chemin_libelle($nid);
                    foreach (entrepot_noeud_selection_path($nid) as $niveau_id => $vid) {
                        $vals['ref_niveau_' . (int) $niveau_id] = (string) (int) $vid;
                    }
                }
            } catch (PDOException $e) {
                // ignore
            }
        }

        return produit_emplacement_enrich_referentiel_form_values($vals);
    }

    $vals = [];
    $numero_etage = null;
    if (!empty($produit['etage']) && ctype_digit((string) $produit['etage'])) {
        $numero_etage = (int) $produit['etage'];
    }
    foreach (produit_emplacement_champs_config($numero_etage) as $col => $cfg) {
        if (!produit_emplacement_colonne_active($col)) {
            continue;
        }
        $v = isset($produit[$col]) ? trim((string) $produit[$col]) : '';
        $vals[$col] = $v !== '' ? $v : null;
    }

    return $vals;
}

/**
 * @param array<string, string|null> $vals
 * @return bool
 */
function produit_emplacement_a_des_donnees(array $vals) {
    if (!empty($vals['entrepot_noeud_id']) || !empty($vals['entrepot_position_id'])) {
        return true;
    }
    foreach ($vals as $k => $v) {
        if ($k === 'chemin_libelle' || strpos((string) $k, 'ref_') === 0) {
            continue;
        }
        if ($v !== null && $v !== '') {
            return true;
        }
    }

    return false;
}

/**
 * @return string
 */
function produit_emplacement_option_prefix($col) {
    $map = [
        'etage' => 'Étage',
        'numero_rayon' => 'Rayon',
        'allee' => 'Allée',
        'zone_emplacement' => 'Zone',
        'position_emplacement' => 'Position',
        'barre_rayon' => 'Barre',
    ];

    return $map[$col] ?? 'Valeur';
}

/**
 * @param string $col
 * @param int|string $n
 * @return string
 */
function produit_emplacement_option_label($col, $n) {
    return produit_emplacement_option_prefix($col) . ' ' . (int) $n;
}

/**
 * @param array<string, string|null> $vals
 * @return string
 */
function produit_emplacement_resume_court(array $vals) {
    if (!empty($vals['chemin_libelle'])) {
        return (string) $vals['chemin_libelle'];
    }

    $parts = [];
    foreach (produit_emplacement_champs_meta() as $col => $cfg) {
        if (!empty($vals[$col])) {
            $parts[] = produit_emplacement_option_label($col, $vals[$col]);
        }
    }

    return implode(' · ', $parts);
}

/**
 * @param array<string, string|null> $vals
 * @return string
 */
function produit_emplacement_compact_suffix(array $vals) {
    $keys = [
        'etage' => 'E',
        'numero_rayon' => 'R',
        'allee' => 'A',
        'zone_emplacement' => 'Z',
        'position_emplacement' => 'P',
        'barre_rayon' => 'B',
    ];
    $parts = [];
    foreach ($keys as $col => $letter) {
        if (!empty($vals[$col])) {
            $parts[] = $letter . (int) $vals[$col];
        }
    }

    return implode('|', $parts);
}

/**
 * @param string $fpl_code
 * @param array<string, string|null> $vals
 * @return string
 */
function produit_emplacement_barcode_payload($fpl_code, array $vals) {
    $fpl_code = strtoupper(trim((string) $fpl_code));
    $position_id = 0;
    if (!empty($vals['entrepot_position_id']) && ctype_digit((string) $vals['entrepot_position_id'])) {
        $position_id = (int) $vals['entrepot_position_id'];
    }
    if ($position_id > 0 && produit_emplacement_use_referentiel()) {
        $chemin = entrepot_get_chemin_complet($position_id);
        $parts = [$fpl_code];
        if (!empty($chemin['code_scan'])) {
            $parts[] = 'BAR=' . strtoupper(trim((string) $chemin['code_scan']));
        }
        if (!empty($chemin['position_num'])) {
            $parts[] = 'POS=' . (int) $chemin['position_num'];
        }

        return implode(';', $parts);
    }

    $suffix = produit_emplacement_compact_suffix($vals);
    if ($suffix === '') {
        return $fpl_code;
    }

    return $fpl_code . ';' . $suffix;
}

/**
 * @param string $raw
 * @return string
 */
function produit_emplacement_extraire_fpl_du_scan($raw) {
    $raw = strtoupper(trim((string) $raw));
    if ($raw === '') {
        return '';
    }
    if (strpos($raw, ';') !== false) {
        $raw = trim((string) explode(';', $raw, 2)[0]);
    }

    /* LE QR DE LA VITRINE CLIENT (04/09) : il encode /p/{ean13} — une
       douchette 2D qui lit le QR reçoit l'URL entière. On en sort le code
       pour que QR et code-barres rendent LE MÊME contenu au scan (décision
       de la direction) ; le code extrait suit le circuit EAN ci-dessous. */
    if (preg_match('#/P/([0-9A-Z]{1,20})#', $raw, $m)) {
        $raw = $m[1];
    }

    /* L'EAN-13 DE LA NOUVELLE ÉTIQUETTE (01/09) : 200 (plage GS1 réservée à
       l'usage interne) + les 9 chiffres de l'identifiant + la clé. La
       douchette tape ces 13 chiffres : on les rend comme l'identifiant FPL,
       après vérification de la clé — un vrai EAN du commerce qui commencerait
       par 200 sans bonne clé ne passe pas. */
    if (preg_match('/^200(\d{9})(\d)$/', $raw, $m)) {
        $somme = 0;
        for ($i = 0; $i < 12; $i++) {
            $somme += ((int) $raw[$i]) * ($i % 2 === 0 ? 1 : 3);
        }
        if ((int) $m[2] === (10 - $somme % 10) % 10) {
            return 'FPL' . $m[1];
        }
    }

    return $raw;
}

/**
 * @param int $produit_id
 * @param array<string, mixed> $produit
 * @return string
 */
function produit_emplacement_stock_info_url($produit_id, array $produit = []) {
    require_once __DIR__ . '/site_url.php';
    $produit_id = (int) $produit_id;
    $base = rtrim(get_site_base_url(), '/') . '/stock-info.php?id=' . $produit_id;
    if ($produit_id <= 0) {
        return $base;
    }

    $vals = produit_emplacement_from_produit($produit);
    if (!empty($vals['chemin_libelle'])) {
        return $base . '&chemin=' . rawurlencode((string) $vals['chemin_libelle']);
    }

    $query_keys = [
        'etage' => 'e',
        'numero_rayon' => 'r',
        'allee' => 'a',
        'zone_emplacement' => 'z',
        'position_emplacement' => 'p',
        'barre_rayon' => 'b',
    ];
    $params = [];
    foreach ($query_keys as $col => $key) {
        if (!empty($vals[$col])) {
            $params[$key] = (int) $vals[$col];
        }
    }
    if ($params === []) {
        return $base;
    }

    return $base . '&' . http_build_query($params);
}

/**
 * @param string $col
 * @param array{label: string, min: int, max: int, icon: string, hint: string} $cfg
 * @param string|null $selected
 * @param string $extra_class
 * @param bool $hidden
 */
function produit_emplacement_render_select($col, array $cfg, $selected, $extra_class = '', $hidden = false) {
    $id = htmlspecialchars($col, ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars($cfg['icon'], ENT_QUOTES, 'UTF-8');
    $hint = htmlspecialchars($cfg['hint'], ENT_QUOTES, 'UTF-8');
    $sel = $selected !== null && $selected !== '' ? (string) $selected : '';
    $group_class = 'form-group pm-emplacement-field';
    if ($extra_class !== '') {
        $group_class .= ' ' . $extra_class;
    }
    $hidden_attr = $hidden ? ' hidden' : '';

    echo '<div class="' . htmlspecialchars($group_class, ENT_QUOTES, 'UTF-8') . '" data-emplacement-field="' . $id . '"' . $hidden_attr . '>';
    echo '<label for="' . $id . '"><i class="fas ' . $icon . '" aria-hidden="true"></i> ' . $label . '</label>';
    echo '<select id="' . $id . '" name="' . $id . '" data-emplacement-select="' . $id . '">';
    echo '<option value="">— Non renseigné —</option>';
    for ($i = $cfg['min']; $i <= $cfg['max']; $i++) {
        $s = ((string) $i === $sel) ? ' selected' : '';
        $opt_label = htmlspecialchars(produit_emplacement_option_label($col, $i), ENT_QUOTES, 'UTF-8');
        echo '<option value="' . $i . '"' . $s . '>' . $opt_label . '</option>';
    }
    echo '</select>';
    echo '<small class="form-hint">' . $hint . '</small>';
    echo '</div>';
}

/**
 * Formulaire cascade nommée (référentiel entrepôt).
 *
 * @param array<string, string|null|int> $values
 */
function produit_emplacement_render_form_fields_referentiel(array $values) {
    $ref_json = entrepot_get_referentiel_json_produit();
    $mode_attr = 'referentiel';
    if (!function_exists('entrepot_hierarchie_libre_schema_ok')) {
        require_once __DIR__ . '/../models/model_entrepot_hierarchie_libre.php';
    }
    if (entrepot_hierarchie_libre_schema_ok()) {
        $mode_attr = 'libre';
    }
    $ref_empty = $ref_json === []
        || ($mode_attr === 'libre' && empty($ref_json['etages']) && empty($ref_json['noeuds_par_niveau']));
    if ($ref_empty) {
        echo '<div class="pm-emplacement-alert"><p class="form-hint form-hint--warning"><i class="fas fa-warehouse" aria-hidden="true"></i> ';
        echo 'Référentiel entrepôt vide. <a href="/admin/parametres/emplacement-entrepot.php">Configurez et nommez les emplacements</a>';
        if (function_exists('entrepot_hierarchie_chemin_libelle')) {
            echo ' · <a href="/admin/parametres/hierarchie-entrepot.php">Hiérarchie</a>';
        }
        echo '.</p></div>';
        return;
    }

    $cascade_fields = produit_emplacement_cascade_fields_config();
    $sel = [
        'numero_etage' => $values['ref_numero_etage'] ?? ($values['etage'] ?? ''),
        'zone_id' => $values['ref_zone_id'] ?? '',
        'rayon_id' => $values['ref_rayon_id'] ?? '',
        'etagere_id' => $values['ref_etagere_id'] ?? '',
        'allee_id' => $values['ref_allee_id'] ?? '',
        'barre_id' => $values['ref_barre_id'] ?? '',
        'position_id' => $values['entrepot_position_id'] ?? '',
        'entrepot_noeud_id' => $values['entrepot_noeud_id'] ?? '',
        'ref_etage' => $values['ref_numero_etage'] ?? ($values['etage'] ?? ''),
    ];
    foreach ($cascade_fields as $field) {
        $type = (string) ($field['type'] ?? '');
        $key = (string) ($field['key'] ?? '');
        if ($key === '' || $type === 'etage') {
            continue;
        }
        if ($type === 'custom' || $type === 'noeud') {
            $sel[$key] = $values[$key] ?? ($type === 'noeud' && !empty($field['niveau_id'])
                ? ($values['ref_niveau_' . (int) $field['niveau_id']] ?? '')
                : '');
        }
    }
    $chemin = trim((string) ($values['chemin_libelle'] ?? ''));
    $chemin_hint = function_exists('entrepot_hierarchie_chemin_libelle')
        ? entrepot_hierarchie_chemin_libelle()
        : 'Niveau → …';

    echo '<div id="pm-emplacement-form" class="pm-emplacement-form pm-emplacement-form--referentiel" data-mode="' . htmlspecialchars($mode_attr, ENT_QUOTES, 'UTF-8') . '">';
    echo '<script type="application/json" id="pm-emplacement-referentiel">' . produit_emplacement_json_script($ref_json) . '</script>';
    echo '<script type="application/json" id="pm-emplacement-selection">' . produit_emplacement_json_script($sel) . '</script>';
    echo '<script type="application/json" id="pm-emplacement-structure">' . produit_emplacement_json_script($cascade_fields) . '</script>';

    echo '<div class="pm-emplacement-steps" aria-hidden="true">';
    foreach ($cascade_fields as $field) {
        $icon = htmlspecialchars((string) ($field['icon'] ?? 'fa-cube'), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<span class="pm-emplacement-step"><i class="fas ' . $icon . '"></i> ' . $label . '</span>';
    }
    echo '</div>';

    echo '<p class="form-hint pm-hint pm-emplacement-intro">Choisissez chaque niveau selon la hiérarchie configurée : <strong>'
        . htmlspecialchars($chemin_hint, ENT_QUOTES, 'UTF-8')
        . '</strong>.</p>';

    echo '<div id="pm-emplacement-cascade" class="pm-emplacement-cascade">';

    $pairs = array_chunk($cascade_fields, 2);
    foreach ($pairs as $pair) {
        echo '<div class="form-row pm-emplacement-row">';
        foreach ($pair as $f) {
            $fid = (string) ($f['key'] ?? '');
            if ($fid === '') {
                continue;
            }
            $label = (string) ($f['label'] ?? '');
            $icon = (string) ($f['icon'] ?? 'fa-cube');
            $hint = (string) ($f['hint'] ?? '');
            $ftype = (string) ($f['type'] ?? '');
            $champ_id = (int) ($f['champ_id'] ?? 0);
            $empty = '— Choisir ' . ($label !== '' ? $label : '') . ' —';
            $fslug = (string) ($f['niveau'] ?? '');
            if ($ftype === 'positions') {
                $empty = '— Choisissez d’abord une barre —';
            } elseif ($ftype === 'barres') {
                $empty = '— Choisir un rayon ou une barre —';
            } elseif ($ftype === 'etage') {
                $empty = '— Choisir ' . ($label !== '' ? $label : 'Niveau') . ' —';
            } elseif ($fslug === 'box') {
                // La box est FACULTATIVE : une pièce peut rester directement sur la barre.
                $empty = '— Aucune box (pièce directement sur la barre) —';
            } elseif ($ftype === 'noeud') {
                $empty = '— Choisir ' . ($label !== '' ? $label : '') . ' —';
            }
            $field_class = 'form-group pm-emplacement-field pm-emplacement-field--ref';
            if ($ftype === 'etage') {
                $field_class .= ' pm-emplacement-field--etage';
            }
            echo '<div class="' . $field_class . '" data-emplacement-ref="' . htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') . '" data-field-type="' . htmlspecialchars($ftype, ENT_QUOTES, 'UTF-8') . '"';
            if ($champ_id > 0) {
                echo ' data-champ-id="' . $champ_id . '"';
            }
            echo '>';
            echo '<label for="' . htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') . '"><i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
            echo '<select id="' . htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') . '" data-emplacement-ref-select="' . htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') . '" data-field-type="' . htmlspecialchars($ftype, ENT_QUOTES, 'UTF-8') . '">';
            echo '<option value="">' . htmlspecialchars($empty, ENT_QUOTES, 'UTF-8') . '</option>';
            echo '</select>';
            echo '<small class="form-hint">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</small>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';

    echo '<div id="pm-emplacement-apercu" class="pm-emplacement-apercu"' . ($chemin !== '' ? '' : ' hidden') . '>';
    echo '<span class="pm-emplacement-apercu__label"><i class="fas fa-map-pin" aria-hidden="true"></i> Chemin sélectionné</span>';
    echo '<p class="pm-emplacement-apercu__text" id="pm-emplacement-apercu-text">' . htmlspecialchars($chemin) . '</p>';
    echo '</div>';

    echo '</div>';
}

/**
 * @param array<string, string|null> $values
 */
function produit_emplacement_render_form_fields(array $values) {
    if (produit_emplacement_use_referentiel()) {
        produit_emplacement_render_form_fields_referentiel($values);
        return;
    }

    $meta = produit_emplacement_champs_meta();
    $actifs = [];
    foreach ($meta as $col => $cfg) {
        if (produit_emplacement_colonne_active($col)) {
            $actifs[$col] = $cfg;
        }
    }
    if ($actifs === []) {
        echo '<p class="form-hint form-hint--warning"><i class="fas fa-info-circle" aria-hidden="true"></i> Emplacement entrepôt : exécutez les migrations <code>migration_admin_b2b_structure</code> et <code>run_add_produits_emplacement_entrepot.php</code>.</p>';
        return;
    }

    $numero_etage = null;
    if (!empty($values['etage']) && ctype_digit((string) $values['etage'])) {
        $numero_etage = (int) $values['etage'];
    }
    $champs = produit_emplacement_champs_config($numero_etage);
    $limites_json = entrepot_emplacement_json_limites_par_etage();
    $nb_etages = (int) (entrepot_emplacement_get_config_row()['nb_etages'] ?? 0);

    echo '<div id="pm-emplacement-form" class="pm-emplacement-form" data-nb-etages="' . (int) $nb_etages . '">';
    echo '<script type="application/json" id="pm-emplacement-limites">' . produit_emplacement_json_script($limites_json) . '</script>';
    echo '<p class="form-hint pm-hint pm-emplacement-intro">Choisissez d’abord un étage, puis le parcours (rayon → allée → zone → position → barre). Tous les champs sont facultatifs ; limites selon la configuration entrepôt.</p>';

    if (isset($champs['etage'])) {
        echo '<div class="form-row pm-emplacement-row pm-emplacement-row--etage">';
        produit_emplacement_render_select('etage', $champs['etage'], $values['etage'] ?? null, 'pm-emplacement-field--etage');
        echo '</div>';
    }

    $enfants = ['numero_rayon', 'allee', 'zone_emplacement', 'position_emplacement', 'barre_rayon'];
    $show_enfants = $numero_etage !== null && $numero_etage > 0;
    echo '<div id="pm-emplacement-enfants" class="pm-emplacement-enfants"' . ($show_enfants ? '' : ' hidden') . '>';

    $pairs = array_chunk($enfants, 2);
    foreach ($pairs as $pair) {
        echo '<div class="form-row pm-emplacement-row">';
        foreach ($pair as $col) {
            if (!isset($champs[$col]) || !produit_emplacement_colonne_active($col)) {
                continue;
            }
            produit_emplacement_render_select($col, $champs[$col], $values[$col] ?? null, 'pm-emplacement-field--enfant');
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}

/**
 * @param array<string, string|null> $vals
 */
function produit_emplacement_render_apercu(array $vals) {
    if (!produit_emplacement_a_des_donnees($vals)) {
        return;
    }

    $etapes = [
        ['col' => 'etage', 'label' => 'Étage', 'icon' => 'fa-building'],
        ['col' => 'numero_rayon', 'label' => 'Rayon', 'icon' => 'fa-th-large'],
        ['col' => 'allee', 'label' => 'Allée', 'icon' => 'fa-road'],
        ['col' => 'zone_emplacement', 'label' => 'Zone', 'icon' => 'fa-map-marker-alt'],
        ['col' => 'position_emplacement', 'label' => 'Position', 'icon' => 'fa-crosshairs'],
        ['col' => 'barre_rayon', 'label' => 'Barre', 'icon' => 'fa-grip-lines'],
    ];

    $resume = produit_emplacement_resume_court($vals);
    if (!empty($vals['chemin_libelle'])) {
        ?>
    <section class="pas-emplacement" aria-labelledby="pas-emplacement-title">
        <div class="pas-emplacement__head">
            <h4 id="pas-emplacement-title" class="pas-emplacement__title">
                <i class="fas fa-map-pin" aria-hidden="true"></i> Position exacte en entrepôt
            </h4>
            <p class="pas-emplacement__resume" title="Chemin nommé"><?php echo htmlspecialchars($resume); ?></p>
        </div>
    </section>
        <?php
        return;
    }

    ?>
    <section class="pas-emplacement" aria-labelledby="pas-emplacement-title">
        <div class="pas-emplacement__head">
            <h4 id="pas-emplacement-title" class="pas-emplacement__title">
                <i class="fas fa-map-pin" aria-hidden="true"></i> Position exacte en entrepôt
            </h4>
            <?php if ($resume !== ''): ?>
            <p class="pas-emplacement__resume" title="Résumé du parcours"><?php echo htmlspecialchars($resume); ?></p>
            <?php endif; ?>
        </div>
        <div class="pas-emplacement__grille" role="list" aria-label="Détail des coordonnées">
            <?php foreach ($etapes as $etape):
                $col = $etape['col'];
                if (empty($vals[$col])) {
                    continue;
                }
            ?>
            <div class="pas-emplacement__cell" role="listitem">
                <span class="pas-emplacement__cell-ic" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($etape['icon']); ?>"></i></span>
                <div class="pas-emplacement__cell-body">
                    <span class="pas-emplacement__cell-label"><?php echo htmlspecialchars($etape['label']); ?></span>
                    <span class="pas-emplacement__cell-value"><?php echo htmlspecialchars(produit_emplacement_option_label($col, $vals[$col])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
