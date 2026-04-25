<?php
/**
 * Modèle — fiches employés (RH)
 */
require_once __DIR__ . '/../conn/conn.php';

function get_all_employes($statut = null) {
    global $db;
    try {
        if ($statut) {
            $stmt = $db->prepare('SELECT e.*, a.email AS admin_email FROM employes e LEFT JOIN admin a ON e.admin_id = a.id WHERE e.statut = :s ORDER BY e.nom ASC, e.prenom ASC');
            $stmt->execute(['s' => $statut]);
        } else {
            $stmt = $db->query('SELECT e.*, a.email AS admin_email FROM employes e LEFT JOIN admin a ON e.admin_id = a.id ORDER BY e.nom ASC, e.prenom ASC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_employe_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare('SELECT e.*, a.email AS admin_email, a.prenom AS admin_prenom, a.nom AS admin_nom FROM employes e LEFT JOIN admin a ON e.admin_id = a.id WHERE e.id = :id');
        $stmt->execute(['id' => (int) $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Fiche employé liée à un compte admin (optionnel).
 * @return array|false
 */
function get_employe_by_admin_id($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT e.*, a.email AS admin_email FROM employes e LEFT JOIN admin a ON e.admin_id = a.id WHERE e.admin_id = :aid LIMIT 1');
        $stmt->execute(['aid' => $admin_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function count_employes_by_statut($statut = null) {
    global $db;
    try {
        if ($statut) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM employes WHERE statut = :s');
            $stmt->execute(['s' => $statut]);
        } else {
            $stmt = $db->query('SELECT COUNT(*) FROM employes');
        }
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function create_employe($data) {
    global $db;
    try {
        $stmt = $db->prepare('
            INSERT INTO employes (nom, prenom, email, telephone, poste, service, date_embauche, statut, notes, admin_id, date_creation)
            VALUES (:nom, :prenom, :email, :telephone, :poste, :service, :date_embauche, :statut, :notes, :admin_id, NOW())
        ');
        $ok = $stmt->execute([
            'nom' => trim($data['nom']),
            'prenom' => trim($data['prenom']),
            'email' => $data['email'] !== '' ? trim($data['email']) : null,
            'telephone' => $data['telephone'] !== '' ? trim($data['telephone']) : null,
            'poste' => $data['poste'] !== '' ? trim($data['poste']) : null,
            'service' => $data['service'] !== '' ? trim($data['service']) : null,
            'date_embauche' => !empty($data['date_embauche']) ? $data['date_embauche'] : null,
            'statut' => in_array($data['statut'] ?? 'actif', ['actif', 'inactif', 'suspendu'], true) ? $data['statut'] : 'actif',
            'notes' => $data['notes'] !== '' ? trim($data['notes']) : null,
            'admin_id' => !empty($data['admin_id']) ? (int) $data['admin_id'] : null,
        ]);
        if ($ok) {
            return (int) $db->lastInsertId();
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

function update_employe($id, $data) {
    global $db;
    try {
        $stmt = $db->prepare('
            UPDATE employes SET
                nom = :nom, prenom = :prenom, email = :email, telephone = :telephone,
                poste = :poste, service = :service, date_embauche = :date_embauche,
                statut = :statut, notes = :notes, admin_id = :admin_id, date_modification = NOW()
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => (int) $id,
            'nom' => trim($data['nom']),
            'prenom' => trim($data['prenom']),
            'email' => $data['email'] !== '' ? trim($data['email']) : null,
            'telephone' => $data['telephone'] !== '' ? trim($data['telephone']) : null,
            'poste' => $data['poste'] !== '' ? trim($data['poste']) : null,
            'service' => $data['service'] !== '' ? trim($data['service']) : null,
            'date_embauche' => !empty($data['date_embauche']) ? $data['date_embauche'] : null,
            'statut' => in_array($data['statut'] ?? 'actif', ['actif', 'inactif', 'suspendu'], true) ? $data['statut'] : 'actif',
            'notes' => $data['notes'] !== '' ? trim($data['notes']) : null,
            'admin_id' => !empty($data['admin_id']) ? (int) $data['admin_id'] : null,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_employe($id) {
    global $db;
    try {
        $stmt = $db->prepare('DELETE FROM employes WHERE id = :id');
        return $stmt->execute(['id' => (int) $id]);
    } catch (PDOException $e) {
        return false;
    }
}
