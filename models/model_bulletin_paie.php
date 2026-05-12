<?php
/**
 * Paramètres et bulletins de paie (RH)
 */
require_once __DIR__ . '/../conn/conn.php';

function bp_tables_parametres_disponibles() {
    global $db;
    try {
        $db->query('SELECT 1 FROM bulletin_paie_parametres LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function bp_tables_bulletins_disponibles() {
    global $db;
    try {
        $db->query('SELECT 1 FROM employe_bulletins_paie LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function bp_colonne_retenues_taux_disponible() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT retenues_taux_json FROM bulletin_paie_parametres LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function bp_colonne_jours_presence_defaut_disponible() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT jours_presence_defaut FROM bulletin_paie_parametres LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function bp_colonne_prime_transport_mensuelle_disponible() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT prime_transport_mensuelle FROM bulletin_paie_parametres LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function bp_colonne_conges_annuels_global_disponible() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT conges_annuels_global FROM bulletin_paie_parametres LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function bp_colonne_forfait_heures_sup_disponible() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT forfait_heures_sup_mensuel FROM bulletin_paie_parametres LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function bp_colonnes_bulletin_montants_disponibles() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT montant_irpp, montant_ipres, montant_css, montant_penalites_absence FROM employe_bulletins_paie LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Retenues calculées en % du salaire brut (paramètres généraux). L’IRPP est sur la fiche employé.
 *
 * @return list<string>
 */
function bp_retenues_codes_taux_brut() {
    return ['ipres_rg', 'ipres_cadre', 'css'];
}

/**
 * @return array<string, float>
 */
function bp_retenues_taux_defaut() {
    return [
        'ipres_rg' => 0.0,
        'ipres_cadre' => 0.0,
        'css' => 0.0,
    ];
}

/**
 * @param array<string, mixed>|null $stored
 * @return array<string, float>
 */
function bp_merge_retenues_taux($stored) {
    $def = bp_retenues_taux_defaut();
    if (!is_array($stored)) {
        return $def;
    }
    if (array_key_exists('ipres', $stored) && is_numeric($stored['ipres']) && !array_key_exists('ipres_rg', $stored)) {
        $stored['ipres_rg'] = $stored['ipres'];
    }
    foreach ($def as $k => $_) {
        if (array_key_exists($k, $stored) && is_numeric($stored[$k])) {
            $def[$k] = max(0.0, min(100.0, (float) $stored[$k]));
        }
    }
    return $def;
}

/**
 * Taux en % (0–100), accepte la virgule décimale.
 */
function bp_parse_taux_pct($v) {
    if ($v === null || $v === '') {
        return 0.0;
    }
    $s = str_replace([' ', "\xc2\xa0"], '', (string) $v);
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) {
        return 0.0;
    }
    return max(0.0, min(100.0, round((float) $s, 4)));
}

/**
 * @return array<string, mixed>
 */
function bp_rubriques_defaut() {
    return [
        'gains' => [
            'salaire_base' => true,
            'heures_sup' => true,
            'prime_performance' => true,
            'prime_transport' => true,
            'assurance_maladie' => false,
            'sursalaire' => false,
            'indemnite_transport' => true,
            'indemnite_logement' => true,
            'indemnite_fonction' => true,
        ],
        'retenues' => [
            'irpp' => true,
            'trimf' => true,
            'ipres_rg' => true,
            'ipres_cadre' => false,
            'css' => true,
            'accident_travail' => true,
            'pret_salaire' => true,
            'autres_retenues' => true,
        ],
        'travail' => [
            'heures_travaillees' => true,
            'heures_sup' => true,
            'jours_presence' => true,
            'conges' => false,
        ],
        'mentions' => [
            'date_paiement' => true,
            'mode_paiement' => true,
            'signature' => true,
        ],
    ];
}

/**
 * @param array<string, mixed>|null $stored
 * @return array<string, mixed>
 */
function bp_merge_rubriques($stored) {
    $def = bp_rubriques_defaut();
    if (!is_array($stored)) {
        return $def;
    }
    if (isset($stored['retenues']) && is_array($stored['retenues'])) {
        if (!empty($stored['retenues']['ipres']) && empty($stored['retenues']['ipres_rg']) && empty($stored['retenues']['ipres_cadre'])) {
            $stored['retenues']['ipres_rg'] = true;
        }
        unset($stored['retenues']['ipres']);
    }
    foreach ($def as $sect => $keys) {
        if (!isset($stored[$sect]) || !is_array($stored[$sect])) {
            $stored[$sect] = $keys;
            continue;
        }
        foreach ($keys as $k => $v) {
            if (!array_key_exists($k, $stored[$sect])) {
                $stored[$sect][$k] = $v;
            }
            $stored[$sect][$k] = (bool) $stored[$sect][$k];
        }
    }
    return $stored;
}

/**
 * @return array<string, mixed>|false
 */
function bp_get_parametres_row() {
    global $db;
    if (!bp_tables_parametres_disponibles()) {
        return false;
    }
    try {
        $stmt = $db->query('SELECT * FROM bulletin_paie_parametres WHERE id = 1 LIMIT 1');
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array{employeur_nom:string, employeur_adresse:string, employeur_ninea:string, employeur_rc:string, employeur_cnss_ref:string, rubriques:array, retenues_taux:array<string,float>, jours_presence_defaut:int}
 */
function bp_get_parametres_effectifs() {
    $row = bp_get_parametres_row();
    $rub = bp_rubriques_defaut();
    $taux = bp_retenues_taux_defaut();
    $jp_def = 0;
    $prime_transport_mensuelle = 0.0;
    $conges_annuels_global = 0;
    if ($row) {
        $j = json_decode((string) ($row['rubriques_json'] ?? ''), true);
        $rub = bp_merge_rubriques(is_array($j) ? $j : null);
        if (bp_colonne_retenues_taux_disponible()) {
            $tj = json_decode((string) ($row['retenues_taux_json'] ?? ''), true);
            $taux = bp_merge_retenues_taux(is_array($tj) ? $tj : null);
        }
        if (bp_colonne_jours_presence_defaut_disponible() && isset($row['jours_presence_defaut']) && $row['jours_presence_defaut'] !== null && $row['jours_presence_defaut'] !== '') {
            $jp_def = max(0, min(31, (int) $row['jours_presence_defaut']));
        }
        if (bp_colonne_prime_transport_mensuelle_disponible() && isset($row['prime_transport_mensuelle']) && $row['prime_transport_mensuelle'] !== null && $row['prime_transport_mensuelle'] !== '') {
            $prime_transport_mensuelle = max(0, round((float) $row['prime_transport_mensuelle'], 2));
        }
        if (bp_colonne_conges_annuels_global_disponible() && isset($row['conges_annuels_global']) && $row['conges_annuels_global'] !== null && $row['conges_annuels_global'] !== '') {
            $conges_annuels_global = max(0, min(365, (int) $row['conges_annuels_global']));
        }
    } else {
        $row = [];
    }
    $forfait_hs = 0.0;
    if (bp_colonne_forfait_heures_sup_disponible() && isset($row['forfait_heures_sup_mensuel']) && $row['forfait_heures_sup_mensuel'] !== null && $row['forfait_heures_sup_mensuel'] !== '') {
        $forfait_hs = max(0, round((float) $row['forfait_heures_sup_mensuel'], 2));
    }
    return [
        'employeur_nom' => trim((string) ($row['employeur_nom'] ?? '')),
        'employeur_adresse' => trim((string) ($row['employeur_adresse'] ?? '')),
        'employeur_ninea' => trim((string) ($row['employeur_ninea'] ?? '')),
        'employeur_rc' => trim((string) ($row['employeur_rc'] ?? '')),
        'employeur_cnss_ref' => trim((string) ($row['employeur_cnss_ref'] ?? '')),
        'rubriques' => $rub,
        'retenues_taux' => $taux,
        'jours_presence_defaut' => $jp_def,
        'prime_transport_mensuelle' => $prime_transport_mensuelle,
        'conges_annuels_global' => $conges_annuels_global,
        'forfait_heures_sup_mensuel' => $forfait_hs,
    ];
}

/**
 * Persiste le forfait HS (sursalaire) si la colonne existe.
 */
function bp_persist_forfait_heures_sup_mensuel($montant) {
    global $db;
    if (!bp_colonne_forfait_heures_sup_disponible()) {
        return;
    }
    try {
        $st = $db->prepare('UPDATE bulletin_paie_parametres SET forfait_heures_sup_mensuel = :f WHERE id = 1');
        $st->execute(['f' => round(max(0.0, (float) $montant), 2)]);
    } catch (PDOException $e) {
    }
}

/**
 * @param array{employeur_nom?:string, employeur_adresse?:string, employeur_ninea?:string, employeur_rc?:string, employeur_cnss_ref?:string, rubriques?:array, retenues_taux?:array, jours_presence_defaut?:int|null, forfait_heures_sup_mensuel?:mixed} $data
 */
function bp_save_parametres(array $data) {
    global $db;
    if (!bp_tables_parametres_disponibles()) {
        return false;
    }
    $rub = isset($data['rubriques']) && is_array($data['rubriques'])
        ? bp_merge_rubriques($data['rubriques']) : bp_rubriques_defaut();
    $taux_in = isset($data['retenues_taux']) && is_array($data['retenues_taux']) ? $data['retenues_taux'] : null;
    $taux = bp_merge_retenues_taux($taux_in);
    $prime_transport_mensuelle = bp_parse_montant_post($data['prime_transport_mensuelle'] ?? null);
    $conges_annuels_global = isset($data['conges_annuels_global']) ? (int) $data['conges_annuels_global'] : 0;
    $conges_annuels_global = max(0, min(365, $conges_annuels_global));
    $forfait_heures_sup_save = bp_parse_montant_post($data['forfait_heures_sup_mensuel'] ?? null);
    $ok_exec = false;
    try {
        if (bp_colonne_retenues_taux_disponible()) {
            if (bp_colonne_prime_transport_mensuelle_disponible() && bp_colonne_conges_annuels_global_disponible()) {
                $stmt = $db->prepare('
                    INSERT INTO bulletin_paie_parametres (id, employeur_nom, employeur_adresse, employeur_ninea, employeur_rc, employeur_cnss_ref, rubriques_json, retenues_taux_json, prime_transport_mensuelle, conges_annuels_global)
                    VALUES (1, :nom, :adr, :ni, :rc, :cnss, :rj, :tj, :ptm, :cag)
                    ON DUPLICATE KEY UPDATE
                        employeur_nom = VALUES(employeur_nom),
                        employeur_adresse = VALUES(employeur_adresse),
                        employeur_ninea = VALUES(employeur_ninea),
                        employeur_rc = VALUES(employeur_rc),
                        employeur_cnss_ref = VALUES(employeur_cnss_ref),
                        rubriques_json = VALUES(rubriques_json),
                        retenues_taux_json = VALUES(retenues_taux_json),
                        prime_transport_mensuelle = VALUES(prime_transport_mensuelle),
                        conges_annuels_global = VALUES(conges_annuels_global)
                ');
                $ok_exec = $stmt->execute([
                    'nom' => mb_substr(trim((string) ($data['employeur_nom'] ?? '')), 0, 255),
                    'adr' => trim((string) ($data['employeur_adresse'] ?? '')) !== '' ? trim((string) $data['employeur_adresse']) : null,
                    'ni' => mb_substr(trim((string) ($data['employeur_ninea'] ?? '')), 0, 80),
                    'rc' => mb_substr(trim((string) ($data['employeur_rc'] ?? '')), 0, 120),
                    'cnss' => mb_substr(trim((string) ($data['employeur_cnss_ref'] ?? '')), 0, 120),
                    'rj' => json_encode($rub, JSON_UNESCAPED_UNICODE),
                    'tj' => json_encode($taux, JSON_UNESCAPED_UNICODE),
                    'ptm' => round($prime_transport_mensuelle, 2),
                    'cag' => $conges_annuels_global,
                ]);
            } elseif (bp_colonne_prime_transport_mensuelle_disponible()) {
                $stmt = $db->prepare('
                    INSERT INTO bulletin_paie_parametres (id, employeur_nom, employeur_adresse, employeur_ninea, employeur_rc, employeur_cnss_ref, rubriques_json, retenues_taux_json)
                    VALUES (1, :nom, :adr, :ni, :rc, :cnss, :rj, :tj)
                    ON DUPLICATE KEY UPDATE
                        employeur_nom = VALUES(employeur_nom),
                        employeur_adresse = VALUES(employeur_adresse),
                        employeur_ninea = VALUES(employeur_ninea),
                        employeur_rc = VALUES(employeur_rc),
                        employeur_cnss_ref = VALUES(employeur_cnss_ref),
                        rubriques_json = VALUES(rubriques_json),
                        retenues_taux_json = VALUES(retenues_taux_json)
                ');
                $ok_exec = $stmt->execute([
                    'nom' => mb_substr(trim((string) ($data['employeur_nom'] ?? '')), 0, 255),
                    'adr' => trim((string) ($data['employeur_adresse'] ?? '')) !== '' ? trim((string) $data['employeur_adresse']) : null,
                    'ni' => mb_substr(trim((string) ($data['employeur_ninea'] ?? '')), 0, 80),
                    'rc' => mb_substr(trim((string) ($data['employeur_rc'] ?? '')), 0, 120),
                    'cnss' => mb_substr(trim((string) ($data['employeur_cnss_ref'] ?? '')), 0, 120),
                    'rj' => json_encode($rub, JSON_UNESCAPED_UNICODE),
                    'tj' => json_encode($taux, JSON_UNESCAPED_UNICODE),
                ]);
                if ($ok_exec && bp_colonne_conges_annuels_global_disponible()) {
                    $up = $db->prepare('UPDATE bulletin_paie_parametres SET conges_annuels_global = :cag WHERE id = 1');
                    $up->execute(['cag' => $conges_annuels_global]);
                }
            }
            if (!$ok_exec) {
                return false;
            }
            if (bp_colonne_jours_presence_defaut_disponible()) {
                bp_save_jours_presence_defaut_colonne($data);
            }
            bp_persist_forfait_heures_sup_mensuel($forfait_heures_sup_save);
            return true;
        }
        if (bp_colonne_prime_transport_mensuelle_disponible() && bp_colonne_conges_annuels_global_disponible()) {
            $stmt = $db->prepare('
                INSERT INTO bulletin_paie_parametres (id, employeur_nom, employeur_adresse, employeur_ninea, employeur_rc, employeur_cnss_ref, rubriques_json, prime_transport_mensuelle, conges_annuels_global)
                VALUES (1, :nom, :adr, :ni, :rc, :cnss, :rj, :ptm, :cag)
                ON DUPLICATE KEY UPDATE
                    employeur_nom = VALUES(employeur_nom),
                    employeur_adresse = VALUES(employeur_adresse),
                    employeur_ninea = VALUES(employeur_ninea),
                    employeur_rc = VALUES(employeur_rc),
                    employeur_cnss_ref = VALUES(employeur_cnss_ref),
                    rubriques_json = VALUES(rubriques_json),
                    prime_transport_mensuelle = VALUES(prime_transport_mensuelle),
                    conges_annuels_global = VALUES(conges_annuels_global)
            ');
            $ok_exec = $stmt->execute([
                'nom' => mb_substr(trim((string) ($data['employeur_nom'] ?? '')), 0, 255),
                'adr' => trim((string) ($data['employeur_adresse'] ?? '')) !== '' ? trim((string) $data['employeur_adresse']) : null,
                'ni' => mb_substr(trim((string) ($data['employeur_ninea'] ?? '')), 0, 80),
                'rc' => mb_substr(trim((string) ($data['employeur_rc'] ?? '')), 0, 120),
                'cnss' => mb_substr(trim((string) ($data['employeur_cnss_ref'] ?? '')), 0, 120),
                'rj' => json_encode($rub, JSON_UNESCAPED_UNICODE),
                'ptm' => round($prime_transport_mensuelle, 2),
                'cag' => $conges_annuels_global,
            ]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO bulletin_paie_parametres (id, employeur_nom, employeur_adresse, employeur_ninea, employeur_rc, employeur_cnss_ref, rubriques_json)
                VALUES (1, :nom, :adr, :ni, :rc, :cnss, :rj)
                ON DUPLICATE KEY UPDATE
                    employeur_nom = VALUES(employeur_nom),
                    employeur_adresse = VALUES(employeur_adresse),
                    employeur_ninea = VALUES(employeur_ninea),
                    employeur_rc = VALUES(employeur_rc),
                    employeur_cnss_ref = VALUES(employeur_cnss_ref),
                    rubriques_json = VALUES(rubriques_json)
            ');
            $ok_exec = $stmt->execute([
                'nom' => mb_substr(trim((string) ($data['employeur_nom'] ?? '')), 0, 255),
                'adr' => trim((string) ($data['employeur_adresse'] ?? '')) !== '' ? trim((string) $data['employeur_adresse']) : null,
                'ni' => mb_substr(trim((string) ($data['employeur_ninea'] ?? '')), 0, 80),
                'rc' => mb_substr(trim((string) ($data['employeur_rc'] ?? '')), 0, 120),
                'cnss' => mb_substr(trim((string) ($data['employeur_cnss_ref'] ?? '')), 0, 120),
                'rj' => json_encode($rub, JSON_UNESCAPED_UNICODE),
            ]);
        if ($ok_exec && bp_colonne_conges_annuels_global_disponible()) {
            $up = $db->prepare('UPDATE bulletin_paie_parametres SET conges_annuels_global = :cag WHERE id = 1');
            $up->execute(['cag' => $conges_annuels_global]);
        }
        }
        if (!$ok_exec) {
            return false;
        }
        if (bp_colonne_jours_presence_defaut_disponible()) {
            bp_save_jours_presence_defaut_colonne($data);
        }
        bp_persist_forfait_heures_sup_mensuel($forfait_heures_sup_save);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param array{jours_presence_defaut?:int|null} $data
 */
function bp_save_jours_presence_defaut_colonne(array $data) {
    global $db;
    if (!bp_colonne_jours_presence_defaut_disponible()) {
        return;
    }
    $v = $data['jours_presence_defaut'] ?? null;
    if ($v === null || $v === '') {
        $val = null;
    } else {
        $n = (int) $v;
        $val = $n > 0 ? min(31, $n) : null;
    }
    try {
        $st = $db->prepare('UPDATE bulletin_paie_parametres SET jours_presence_defaut = :jp WHERE id = 1');
        $st->execute(['jp' => $val]);
    } catch (PDOException $e) {
    }
}

/**
 * @param string $mois_ym Format YYYY-MM
 */
function bp_mois_annee_libelle($mois_ym) {
    $ts = strtotime($mois_ym . '-01');
    if ($ts === false) {
        return $mois_ym;
    }
    $n = (int) date('n', $ts);
    $y = date('Y', $ts);
    $mois_noms = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
    return ucfirst($mois_noms[$n] ?? date('F', $ts)) . ' ' . $y;
}

function bp_parse_montant_post($v) {
    if ($v === null || $v === '') {
        return 0.0;
    }
    $s = str_replace([' ', "\xc2\xa0"], '', (string) $v);
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) {
        return 0.0;
    }
    return max(0, round((float) $s, 2));
}

/**
 * Libellés fixes (Sénégal / usage courant)
 *
 * @return array<string, string>
 */
function bp_labels_gains() {
    return [
        'salaire_base' => 'Salaire de base',
        'heures_sup' => 'Heures supplémentaires',
        'prime_performance' => 'Primes (performance, etc.)',
        'prime_transport' => 'Prime de transport',
        'assurance_maladie' => 'Assurance maladie',
        'sursalaire' => 'Sursalaire (forfait HS)',
        'indemnite_transport' => 'Indemnité de transport',
        'indemnite_logement' => 'Indemnité de logement',
        'indemnite_fonction' => 'Indemnité de fonction',
    ];
}

/**
 * @return array<string, string>
 */
function bp_labels_retenues() {
    return [
        'irpp' => 'IRPP (impôt sur le revenu)',
        'trimf' => 'TRIMF',
        'ipres_rg' => 'IPRES régime général (RG)',
        'ipres_cadre' => 'IPRES cadre',
        'css' => 'CSS (sécurité sociale)',
        'accident_travail' => 'Accident du travail',
        'pret_salaire' => 'Retenue prêt / avance sur salaire',
        'autres_retenues' => 'Autres retenues',
        'penalites_absence' => 'Retenue pénalités d’absence',
    ];
}

/**
 * @param array{
 *   mois_paie:string,
 *   date_paiement:string,
 *   salaire_base:float|int|string,
 *   montant_brut:float|int|string,
 *   total_retenues:float|int|string,
 *   net_imposable:float|int|string,
 *   net_a_payer:float|int|string,
 *   montant_irpp?:float|null,
 *   montant_ipres?:float|null,
 *   montant_css?:float|null,
 *   montant_penalites_absence?:float|null
 * } $totaux
 * @return int|false id bulletin
 */
function bp_insert_bulletin($employe_id, $admin_id, array $totaux, array $snapshot) {
    global $db;
    if (!bp_tables_bulletins_disponibles()) {
        return false;
    }
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    try {
        if (bp_colonnes_bulletin_montants_disponibles()) {
            $stmt = $db->prepare('
                INSERT INTO employe_bulletins_paie (
                    employe_id, mois_paie, date_paiement, salaire_base, montant_brut, total_retenues, net_imposable, net_a_payer,
                    montant_irpp, montant_ipres, montant_css, montant_penalites_absence,
                    snapshot_json, admin_id
                ) VALUES (
                    :eid, :m, :dp, :sb, :brut, :retr, :ni, :net,
                    :mirpp, :mipres, :mcss, :mpen,
                    :sj, :aid
                )
            ');
            $ok = $stmt->execute([
                'eid' => $employe_id,
                'm' => $totaux['mois_paie'],
                'dp' => $totaux['date_paiement'],
                'sb' => $totaux['salaire_base'],
                'brut' => $totaux['montant_brut'],
                'retr' => $totaux['total_retenues'],
                'ni' => $totaux['net_imposable'],
                'net' => $totaux['net_a_payer'],
                'mirpp' => isset($totaux['montant_irpp']) && $totaux['montant_irpp'] !== '' ? round((float) $totaux['montant_irpp'], 2) : null,
                'mipres' => isset($totaux['montant_ipres']) && $totaux['montant_ipres'] !== '' ? round((float) $totaux['montant_ipres'], 2) : null,
                'mcss' => isset($totaux['montant_css']) && $totaux['montant_css'] !== '' ? round((float) $totaux['montant_css'], 2) : null,
                'mpen' => isset($totaux['montant_penalites_absence']) && $totaux['montant_penalites_absence'] !== '' ? round((float) $totaux['montant_penalites_absence'], 2) : null,
                'sj' => $json,
                'aid' => $admin_id > 0 ? $admin_id : null,
            ]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO employe_bulletins_paie (
                    employe_id, mois_paie, date_paiement, salaire_base, montant_brut, total_retenues, net_imposable, net_a_payer, snapshot_json, admin_id
                ) VALUES (
                    :eid, :m, :dp, :sb, :brut, :retr, :ni, :net, :sj, :aid
                )
            ');
            $ok = $stmt->execute([
                'eid' => $employe_id,
                'm' => $totaux['mois_paie'],
                'dp' => $totaux['date_paiement'],
                'sb' => $totaux['salaire_base'],
                'brut' => $totaux['montant_brut'],
                'retr' => $totaux['total_retenues'],
                'ni' => $totaux['net_imposable'],
                'net' => $totaux['net_a_payer'],
                'sj' => $json,
                'aid' => $admin_id > 0 ? $admin_id : null,
            ]);
        }
        if (!$ok) {
            return false;
        }
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function bp_list_bulletins_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0 || !bp_tables_bulletins_disponibles()) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT id, mois_paie, date_paiement, salaire_base, montant_brut, total_retenues, net_imposable, net_a_payer, date_creation
            FROM employe_bulletins_paie WHERE employe_id = :id ORDER BY mois_paie DESC, date_creation DESC
        ');
        $stmt->execute(['id' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<string, mixed>|false
 */
function bp_get_bulletin_by_id($bulletin_id) {
    global $db;
    $bulletin_id = (int) $bulletin_id;
    if ($bulletin_id <= 0 || !bp_tables_bulletins_disponibles()) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM employe_bulletins_paie WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $bulletin_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}
