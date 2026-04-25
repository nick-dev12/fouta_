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
                INSERT INTO clients_b2b (raison_sociale, nom_contact, prenom_contact, email, telephone, adresse, notes, statut, admin_createur_id, date_creation)
                VALUES (:raison_sociale, :nom_contact, :prenom_contact, :email, :telephone, :adresse, :notes, :statut, :admin_createur_id, NOW())
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
                'admin_createur_id' => $aid,
            ]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO clients_b2b (raison_sociale, nom_contact, prenom_contact, email, telephone, adresse, notes, statut, date_creation)
                VALUES (:raison_sociale, :nom_contact, :prenom_contact, :email, :telephone, :adresse, :notes, :statut, NOW())
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
            ]);
        }
        return $ok ? (int) $db->lastInsertId() : false;
    } catch (PDOException $e) {
        return false;
    }
}
