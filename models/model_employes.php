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
            INSERT INTO employes (
                nom, prenom, email, telephone, poste, service, date_embauche, statut, notes,
                statut_familial, type_contrat, contrat_pdf_chemin,
                salaire_base, montant_irpp_mensuel, categorie_paie,
                admin_id, date_creation
            )
            VALUES (
                :nom, :prenom, :email, :telephone, :poste, :service, :date_embauche, :statut, :notes,
                :statut_familial, :type_contrat, :contrat_pdf_chemin,
                :salaire_base, :montant_irpp_mensuel, :categorie_paie,
                :admin_id, NOW()
            )
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
            'statut_familial' => array_key_exists('statut_familial', $data) ? $data['statut_familial'] : null,
            'type_contrat' => array_key_exists('type_contrat', $data) ? $data['type_contrat'] : null,
            'contrat_pdf_chemin' => array_key_exists('contrat_pdf_chemin', $data) ? $data['contrat_pdf_chemin'] : null,
            'salaire_base' => array_key_exists('salaire_base', $data) ? $data['salaire_base'] : null,
            'montant_irpp_mensuel' => array_key_exists('montant_irpp_mensuel', $data) ? $data['montant_irpp_mensuel'] : null,
            'categorie_paie' => array_key_exists('categorie_paie', $data) ? $data['categorie_paie'] : null,
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
                statut = :statut, notes = :notes,
                statut_familial = :statut_familial, type_contrat = :type_contrat, contrat_pdf_chemin = :contrat_pdf_chemin,
                salaire_base = :salaire_base, montant_irpp_mensuel = :montant_irpp_mensuel, categorie_paie = :categorie_paie,
                admin_id = :admin_id, date_modification = NOW()
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
            'statut_familial' => array_key_exists('statut_familial', $data) ? $data['statut_familial'] : null,
            'type_contrat' => array_key_exists('type_contrat', $data) ? $data['type_contrat'] : null,
            'contrat_pdf_chemin' => array_key_exists('contrat_pdf_chemin', $data) ? $data['contrat_pdf_chemin'] : null,
            'salaire_base' => array_key_exists('salaire_base', $data) ? $data['salaire_base'] : null,
            'montant_irpp_mensuel' => array_key_exists('montant_irpp_mensuel', $data) ? $data['montant_irpp_mensuel'] : null,
            'categorie_paie' => array_key_exists('categorie_paie', $data) ? $data['categorie_paie'] : null,
            'admin_id' => !empty($data['admin_id']) ? (int) $data['admin_id'] : null,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_employe($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    try {
        $glob_dir = realpath(__DIR__ . '/../upload/employes_photos/');
        if ($glob_dir && is_dir($glob_dir)) {
            foreach (glob($glob_dir . DIRECTORY_SEPARATOR . 'employe_' . $id . '_*') ?: [] as $g) {
                if (is_file($g)) {
                    @unlink($g);
                }
            }
        }
        $contrat_dir = realpath(__DIR__ . '/../upload/employes_contrats/');
        if ($contrat_dir && is_dir($contrat_dir)) {
            foreach (glob($contrat_dir . DIRECTORY_SEPARATOR . 'employe_' . $id . '_*.pdf') ?: [] as $g) {
                if (is_file($g)) {
                    @unlink($g);
                }
            }
        }
        require_once __DIR__ . '/model_employe_documents.php';
        employe_documents_delete_all_files_for_employe($id);
        $stmt = $db->prepare('DELETE FROM employes WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Photo portrait (RH) — chemin relatif sous upload/.
 */
function employe_set_photo_chemin($employe_id, $chemin_relatif) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE employes SET photo_chemin = :p, date_modification = NOW() WHERE id = :id');
        return $stmt->execute([
            'id' => $employe_id,
            'p' => ($chemin_relatif !== null && trim((string) $chemin_relatif) !== '') ? trim($chemin_relatif) : null,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * PDF du contrat — chemin relatif sous upload/.
 */
function employe_set_contrat_pdf_chemin($employe_id, $chemin_relatif) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE employes SET contrat_pdf_chemin = :p, date_modification = NOW() WHERE id = :id');
        return $stmt->execute([
            'id' => $employe_id,
            'p' => ($chemin_relatif !== null && trim((string) $chemin_relatif) !== '') ? trim($chemin_relatif) : null,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le chemin relatif du fichier QR et le dernier payload écrit.
 */
function employe_update_qr_fields($employe_id, $chemin_relatif, $payload) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE employes SET qr_chemin = :p, qr_payload = :pl, date_modification = NOW() WHERE id = :id');
        return $stmt->execute([
            'id' => $employe_id,
            'p' => $chemin_relatif !== '' ? $chemin_relatif : null,
            'pl' => $payload !== '' ? mb_substr($payload, 0, 2040) : null,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}
