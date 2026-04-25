<?php
/**
 * Modèle pour la gestion des contacts (manuels)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère tous les contacts
 * @param string|null $recherche Recherche sur nom, prénom, téléphone
 * @return array
 */
function get_all_contacts($recherche = null) {
    global $db;
    try {
        $sql = "SELECT * FROM contacts WHERE 1=1";
        $params = [];
        if (!empty(trim($recherche ?? ''))) {
            $term = '%' . trim($recherche) . '%';
            $sql .= " AND (nom LIKE :term OR prenom LIKE :term2 OR telephone LIKE :term3 OR email LIKE :term4)";
            $params = ['term' => $term, 'term2' => $term, 'term3' => $term, 'term4' => $term];
        }
        $sql .= " ORDER BY nom ASC, prenom ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un contact par téléphone (chiffres uniquement, comparaison stricte)
 */
function get_contact_by_telephone($telephone) {
    global $db;
    $tel = preg_replace('/\D+/', '', $telephone ?? '');
    if ($tel === '') {
        return false;
    }
    try {
        $stmt = $db->query('SELECT * FROM contacts');
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

/**
 * Carnet contacts : si aucune ligne avec ce numéro (normalisé), crée le contact.
 * Utilisé lors de la création d'un bon de livraison.
 *
 * @return int|false id du contact existant ou créé
 */
function ensure_contact_from_bl($nom, $prenom, $telephone, $email = null) {
    $telephone = trim($telephone ?? '');
    if ($telephone === '') {
        return false;
    }
    $existing = get_contact_by_telephone($telephone);
    if ($existing) {
        return (int) $existing['id'];
    }
    $id = create_contact(trim($nom ?? ''), trim($prenom ?? ''), $telephone, $email && trim($email) !== '' ? trim($email) : null);
    return $id ? (int) $id : false;
}

/**
 * Vérifie si un téléphone existe (users ou contacts)
 */
function telephone_exists_in_users_or_contacts($telephone) {
    global $db;
    $tel = preg_replace('/\D/', '', $telephone);
    if (empty($tel) || strlen($tel) < 8) return false;
    try {
        $stmt = $db->prepare("
            SELECT 1 FROM users WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel
            UNION ALL
            SELECT 1 FROM contacts WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel2
            LIMIT 1
        ");
        $stmt->execute(['tel' => '%' . $tel . '%', 'tel2' => '%' . $tel . '%']);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un contact par ID
 */
function get_contact_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM contacts WHERE id = :id");
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un contact
 */
function update_contact($id, $nom, $prenom, $telephone, $email = null) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE contacts SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email WHERE id = :id");
        return $stmt->execute([
            'id' => (int) $id,
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'telephone' => trim($telephone),
            'email' => $email && trim($email) !== '' ? trim($email) : null
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un contact
 */
function create_contact($nom, $prenom, $telephone, $email = null) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO contacts (nom, prenom, telephone, email) VALUES (:nom, :prenom, :telephone, :email)");
        $stmt->execute([
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'telephone' => trim($telephone),
            'email' => $email && trim($email) !== '' ? trim($email) : null
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recherche clients (users + contacts) pour commande manuelle
 */
function search_clients_for_commande($recherche, $limit = 20) {
    global $db;
    $term = '%' . trim($recherche) . '%';
    if (strlen(trim($recherche)) < 1) return [];
    try {
        $stmt = $db->prepare("
            (SELECT id, nom, prenom, telephone, email, 'user' as source FROM users WHERE statut = 'actif' AND (nom LIKE :t1 OR prenom LIKE :t2 OR email LIKE :t3 OR telephone LIKE :t4))
            UNION ALL
            (SELECT id, nom, prenom, telephone, email, 'contact' as source FROM contacts WHERE nom LIKE :t5 OR prenom LIKE :t6 OR email LIKE :t7 OR telephone LIKE :t8)
            LIMIT :limit
        ");
        $stmt->bindValue('t1', $term, PDO::PARAM_STR);
        $stmt->bindValue('t2', $term, PDO::PARAM_STR);
        $stmt->bindValue('t3', $term, PDO::PARAM_STR);
        $stmt->bindValue('t4', $term, PDO::PARAM_STR);
        $stmt->bindValue('t5', $term, PDO::PARAM_STR);
        $stmt->bindValue('t6', $term, PDO::PARAM_STR);
        $stmt->bindValue('t7', $term, PDO::PARAM_STR);
        $stmt->bindValue('t8', $term, PDO::PARAM_STR);
        $stmt->bindValue('limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}
