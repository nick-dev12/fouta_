<?php
/**
 * Matricule RH format FPL + 6 chiffres (unicité garantie par la table).
 */
require_once __DIR__ . '/../conn/conn.php';

define('EMPLOYE_MATRICULE_PREFIX', 'FPL');
define('EMPLOYE_MATRICULE_LEN_CHIFFRES', 6);
define('EMPLOYE_MATRICULE_MAX_ESSAIS', 100);

function employe_matricule_random_code_candidate() {
    $n = random_int(0, 999999);
    return EMPLOYE_MATRICULE_PREFIX . str_pad((string) $n, EMPLOYE_MATRICULE_LEN_CHIFFRES, '0', STR_PAD_LEFT);
}

/**
 * Dénormalisation sur employes.matricule (colonnes après migration uniquement).
 *
 * @return bool true si mise à jour exécutée ou rien à faire ; false si colonne absente ou erreur
 */
function employe_matricule_colonne_mettre_a_jour($employe_id, $matricule) {
    global $db;
    $employe_id = (int) $employe_id;
    $matricule = strtoupper(trim((string) $matricule));
    if ($employe_id <= 0 || !preg_match('/^FPL\d{6}$/', $matricule)) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE employes SET matricule = :m, date_modification = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute(['m' => $matricule, 'id' => $employe_id]);
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'Unknown column') !== false) {
            return false;
        }
        return false;
    }
}

/**
 * @return string|false Chaîne MAT ou false
 */
function employe_matricule_par_employe_id($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT matricule FROM employes_matricules WHERE employe_id = :eid LIMIT 1');
        $stmt->execute(['eid' => $employe_id]);
        $m = $stmt->fetchColumn();
        return $m !== false ? (string) $m : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Enregistre un matricule pour un employé (unique matricule + unique employé_id).
 *
 * @return bool
 */
function employe_matricule_inserer($employe_id, $matricule) {
    global $db;
    $employe_id = (int) $employe_id;
    $matricule = strtoupper(trim((string) $matricule));
    if ($employe_id <= 0 || !preg_match('/^FPL\d{6}$/', $matricule)) {
        return false;
    }
    try {
        $stmt = $db->prepare('INSERT INTO employes_matricules (employe_id, matricule, date_creation) VALUES (:e, :m, NOW())');
        $ok = $stmt->execute(['e' => $employe_id, 'm' => $matricule]);
        if ($ok) {
            employe_matricule_colonne_mettre_a_jour($employe_id, $matricule);
        }
        return $ok;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Assigne un code aléatoire unique (réessaie en cas de collision jusqu’à la limite).
 *
 * @return string|false Le matricule créé ou false
 */
function employe_matricule_assigner_unique_nouvelle_ligne($employe_id) {
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }

    $existe = employe_matricule_par_employe_id($employe_id);
    if ($existe !== false) {
        employe_matricule_colonne_mettre_a_jour($employe_id, $existe);
        return $existe;
    }

    for ($i = 0; $i < EMPLOYE_MATRICULE_MAX_ESSAIS; $i++) {
        $code = employe_matricule_random_code_candidate();
        if (employe_matricule_inserer($employe_id, $code)) {
            return $code;
        }
    }

    return false;
}

/**
 * Pour les fiches existantes : garantit une ligne et retourne toujours une chaîne conforme ou false si échec.
 *
 * @return string|false
 */
function employe_matricule_assigner_si_absent($employe_id) {
    $exist = employe_matricule_par_employe_id($employe_id);
    if ($exist !== false) {
        employe_matricule_colonne_mettre_a_jour((int) $employe_id, $exist);
        return $exist;
    }
    return employe_matricule_assigner_unique_nouvelle_ligne($employe_id);
}
