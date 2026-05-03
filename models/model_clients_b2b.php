<?php
/**
 * Clients B2B (professionnels — BL / facturation)
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin_activite.php';

function get_all_clients_b2b($statut = 'actif') {
    global $db;
    try {
        if ($statut) {
            $stmt = $db->prepare('SELECT * FROM clients_b2b WHERE statut = :s ORDER BY raison_sociale ASC');
            $stmt->execute(['s' => $statut]);
        } else {
            $stmt = $db->query('SELECT * FROM clients_b2b ORDER BY raison_sociale ASC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_client_b2b_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare('SELECT * FROM clients_b2b WHERE id = :id');
        $stmt->execute(['id' => (int) $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recherche par téléphone (normalisé chiffres) pour éviter les doublons
 */
function find_client_b2b_by_telephone($telephone) {
    global $db;
    $tel = preg_replace('/\D+/', '', $telephone ?? '');
    if ($tel === '') {
        return false;
    }
    try {
        $stmt = $db->query('SELECT * FROM clients_b2b');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $t2 = preg_replace('/\D+/', '', $r['telephone'] ?? '');
            if ($t2 !== '' && $t2 === $tel) {
                return $r;
            }
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

function clients_b2b_normalize_type_bl($code)
{
    return (($code ?? '') === 'vip') ? 'vip' : 'standard';
}

function update_client_b2b_type_client_bl_by_id($client_b2b_id, $code_type)
{
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0) {
        return false;
    }
    $t = clients_b2b_normalize_type_bl($code_type);
    try {
        $stmt = $db->prepare('UPDATE clients_b2b SET type_client_bl = :t WHERE id = :id');
        return $stmt->execute(['t' => $t, 'id' => $client_b2b_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function update_client_b2b_type_client_bl_by_telephone($telephone, $code_type)
{
    $row = find_client_b2b_by_telephone($telephone);
    if (!$row || empty($row['id'])) {
        return false;
    }
    return update_client_b2b_type_client_bl_by_id((int) $row['id'], $code_type);
}

/**
 * Aligner la fiche B2B sur le carnet contacts (même numéro normalisé).
 */
function sync_client_b2b_type_bl_depuis_contact($telephone)
{
    require_once __DIR__ . '/model_contacts.php';
    $c = get_contact_by_telephone($telephone);
    if (!$c) {
        return;
    }
    update_client_b2b_type_client_bl_by_telephone($telephone, $c['type_client_bl'] ?? 'standard');
}

function create_client_b2b($data) {
    global $db;
    try {
        $has_admin = admin_activite_column_exists('clients_b2b', 'admin_createur_id');
        $aid = null;
        if ($has_admin && isset($data['admin_createur_id']) && (int) ($data['admin_createur_id'] ?? 0) > 0) {
            $aid = (int) $data['admin_createur_id'];
        }

        if ($has_admin) {
            $stmt = $db->prepare('
                INSERT INTO clients_b2b (raison_sociale, nom_contact, prenom_contact, email, telephone, adresse, notes, statut, type_client_bl, admin_createur_id, date_creation)
                VALUES (:raison_sociale, :nom_contact, :prenom_contact, :email, :telephone, :adresse, :notes, :statut, :type_client_bl, :admin_createur_id, NOW())
            ');
            $ok = $stmt->execute([
                'raison_sociale' => trim($data['raison_sociale'] ?? ''),
                'nom_contact' => trim($data['nom_contact'] ?? '') !== '' ? trim($data['nom_contact'] ?? '') : null,
                'prenom_contact' => trim($data['prenom_contact'] ?? '') !== '' ? trim($data['prenom_contact'] ?? '') : null,
                'email' => ($data['email'] ?? '') !== '' ? trim((string) $data['email']) : null,
                'telephone' => $data['telephone'] !== '' ? trim($data['telephone']) : null,
                'adresse' => $data['adresse'] !== '' ? trim($data['adresse']) : null,
                'notes' => $data['notes'] !== '' ? trim($data['notes']) : null,
                'statut' => ($data['statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif',
                'type_client_bl' => clients_b2b_normalize_type_bl($data['type_client_bl'] ?? 'standard'),
                'admin_createur_id' => $aid,
            ]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO clients_b2b (raison_sociale, nom_contact, prenom_contact, email, telephone, adresse, notes, statut, type_client_bl, date_creation)
                VALUES (:raison_sociale, :nom_contact, :prenom_contact, :email, :telephone, :adresse, :notes, :statut, :type_client_bl, NOW())
            ');
            $ok = $stmt->execute([
                'raison_sociale' => trim($data['raison_sociale'] ?? ''),
                'nom_contact' => trim($data['nom_contact'] ?? '') !== '' ? trim($data['nom_contact'] ?? '') : null,
                'prenom_contact' => trim($data['prenom_contact'] ?? '') !== '' ? trim($data['prenom_contact'] ?? '') : null,
                'email' => ($data['email'] ?? '') !== '' ? trim((string) $data['email']) : null,
                'telephone' => $data['telephone'] !== '' ? trim($data['telephone']) : null,
                'adresse' => $data['adresse'] !== '' ? trim($data['adresse']) : null,
                'notes' => $data['notes'] !== '' ? trim($data['notes']) : null,
                'statut' => ($data['statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif',
                'type_client_bl' => clients_b2b_normalize_type_bl($data['type_client_bl'] ?? 'standard'),
            ]);
        }
        return $ok ? (int) $db->lastInsertId() : false;
    } catch (PDOException $e) {
        return false;
    }
}
