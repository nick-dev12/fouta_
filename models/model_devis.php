<?php
/**
 * Modèle pour la gestion des devis
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin_activite.php';

/**
 * Génère un numéro de devis unique (format DEV + 5 chiffres)
 */
function generate_numero_devis() {
    global $db;
    try {
        $stmt = $db->query("SELECT MAX(id) as max_id FROM devis");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && $row['max_id']) ? (int) $row['max_id'] + 1 : 1;
        return 'DEV' . str_pad($next, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'DEV' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * Crée un devis
 * @param array $items [['produit_id'=>int, 'quantite'=>int, 'prix_unitaire'=>float, 'nom_produit'=>string|null], ...]
 * @param string $client_nom
 * @param string $client_prenom
 * @param string $client_telephone
 * @param string $adresse_livraison
 * @param string|null $client_email
 * @param string|null $notes
 * @param int|null $zone_livraison_id
 * @param float $frais_livraison
 * @param int|null $user_id
 * @param int|null $admin_createur_id Admin ayant créé le devis (traçabilité)
 * @return array|false ['success'=>true, 'devis_id'=>int, 'numero_devis'=>string] ou false
 */
function create_devis($items, $client_nom, $client_prenom, $client_telephone, $adresse_livraison, $client_email = null, $notes = null, $zone_livraison_id = null, $frais_livraison = 0, $user_id = null, $admin_createur_id = null) {
    global $db;

    if (empty($items) || empty(trim($client_nom)) || empty(trim($client_prenom)) || empty(trim($client_telephone)) || empty(trim($adresse_livraison))) {
        return false;
    }

    $montant_total = 0;
    foreach ($items as $it) {
        $qte = (int) ($it['quantite'] ?? 1);
        $pu = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
        $montant_total += $qte * $pu;
    }
    $frais_livraison = (float) ($frais_livraison ?? 0);
    $montant_total += $frais_livraison;

    $numero = generate_numero_devis();
    try {
        $stmt = $db->prepare("SELECT id FROM devis WHERE numero_devis = :num");
        $stmt->execute(['num' => $numero]);
        if ($stmt->fetch()) {
            $numero = generate_numero_devis() . '-' . substr(uniqid(), -3);
        }

        $has_admin = admin_activite_column_exists('devis', 'admin_createur_id');
        $aid = $has_admin && $admin_createur_id !== null && (int) $admin_createur_id > 0 ? (int) $admin_createur_id : null;

        if ($has_admin) {
            $stmt = $db->prepare("
                INSERT INTO devis (
                    numero_devis, client_nom, client_prenom, client_telephone, client_email,
                    adresse_livraison, zone_livraison_id, frais_livraison, user_id, admin_createur_id,
                    montant_total, notes, statut
                ) VALUES (
                    :numero_devis, :client_nom, :client_prenom, :client_telephone, :client_email,
                    :adresse_livraison, :zone_livraison_id, :frais_livraison, :user_id, :admin_createur_id,
                    :montant_total, :notes, 'brouillon'
                )
            ");
            $stmt->execute([
                'numero_devis' => $numero,
                'client_nom' => trim($client_nom),
                'client_prenom' => trim($client_prenom),
                'client_telephone' => trim($client_telephone),
                'client_email' => $client_email && trim($client_email) !== '' ? trim($client_email) : null,
                'adresse_livraison' => trim($adresse_livraison),
                'zone_livraison_id' => $zone_livraison_id && (int) $zone_livraison_id > 0 ? (int) $zone_livraison_id : null,
                'frais_livraison' => $frais_livraison,
                'user_id' => $user_id && (int) $user_id > 0 ? (int) $user_id : null,
                'admin_createur_id' => $aid,
                'montant_total' => $montant_total,
                'notes' => $notes ? trim($notes) : null
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO devis (
                    numero_devis, client_nom, client_prenom, client_telephone, client_email,
                    adresse_livraison, zone_livraison_id, frais_livraison, user_id,
                    montant_total, notes, statut
                ) VALUES (
                    :numero_devis, :client_nom, :client_prenom, :client_telephone, :client_email,
                    :adresse_livraison, :zone_livraison_id, :frais_livraison, :user_id,
                    :montant_total, :notes, 'brouillon'
                )
            ");
            $stmt->execute([
                'numero_devis' => $numero,
                'client_nom' => trim($client_nom),
                'client_prenom' => trim($client_prenom),
                'client_telephone' => trim($client_telephone),
                'client_email' => $client_email && trim($client_email) !== '' ? trim($client_email) : null,
                'adresse_livraison' => trim($adresse_livraison),
                'zone_livraison_id' => $zone_livraison_id && (int) $zone_livraison_id > 0 ? (int) $zone_livraison_id : null,
                'frais_livraison' => $frais_livraison,
                'user_id' => $user_id && (int) $user_id > 0 ? (int) $user_id : null,
                'montant_total' => $montant_total,
                'notes' => $notes ? trim($notes) : null
            ]);
        }
        $devis_id = (int) $db->lastInsertId();
        if ($devis_id <= 0) return false;

        $stmt_prod = $db->prepare("
            INSERT INTO devis_produits (devis_id, produit_id, nom_produit, quantite, prix_unitaire, prix_total)
            VALUES (:devis_id, :produit_id, :nom_produit, :quantite, :prix_unitaire, :prix_total)
        ");

        foreach ($items as $it) {
            $produit_id = (int) ($it['produit_id'] ?? 0);
            $quantite = (int) ($it['quantite'] ?? 1);
            $prix_unitaire = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
            if ($produit_id <= 0 || $quantite <= 0 || $prix_unitaire <= 0) continue;
            $prix_total = $quantite * $prix_unitaire;
            $nom_produit = isset($it['nom_produit']) && trim($it['nom_produit']) !== '' ? trim($it['nom_produit']) : null;
            $stmt_prod->execute([
                'devis_id' => $devis_id,
                'produit_id' => $produit_id,
                'nom_produit' => $nom_produit,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total
            ]);
        }

        return ['success' => true, 'devis_id' => $devis_id, 'numero_devis' => $numero];
    } catch (PDOException $e) {
        error_log('[create_devis] ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère tous les devis
 * @param string|null $statut Filtrer par statut
 * @return array
 */
function get_all_devis($statut = null) {
    global $db;
    try {
        $sql = "SELECT d.* FROM devis d WHERE 1=1";
        $params = [];
        if ($statut) {
            $sql .= " AND d.statut = :statut";
            $params['statut'] = $statut;
        }
        $sql .= " ORDER BY d.date_creation DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un devis par ID
 * @param int $devis_id
 * @return array|false
 */
function get_devis_by_id($devis_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM devis WHERE id = :id");
        $stmt->execute(['id' => (int) $devis_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les produits d'un devis
 * @param int $devis_id
 * @return array
 */
function get_produits_by_devis($devis_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT dp.*, p.nom as produit_nom_defaut,
                   COALESCE(NULLIF(TRIM(dp.nom_produit), ''), p.nom) as produit_nom
            FROM devis_produits dp
            INNER JOIN produits p ON dp.produit_id = p.id
            WHERE dp.devis_id = :devis_id
            ORDER BY dp.id
        ");
        $stmt->execute(['devis_id' => (int) $devis_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['produit_nom'] = $r['produit_nom'] ?? $r['produit_nom_defaut'] ?? '';
        }
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Met à jour un devis (produits + infos)
 * @param int $devis_id
 * @param array $items
 * @param array $infos [client_nom, client_prenom, client_telephone, adresse_livraison, ...]
 * @return bool
 */
function update_devis($devis_id, $items, $infos) {
    global $db;
    $devis_id = (int) $devis_id;
    if ($devis_id <= 0) {
        return false;
    }
    $ex = get_devis_by_id($devis_id);
    if (!$ex || ($ex['statut'] ?? '') !== 'brouillon') {
        return false;
    }

    try {
        $db->beginTransaction();

        $montant_total = 0;
        foreach ($items as $it) {
            $qte = (int) ($it['quantite'] ?? 1);
            $pu = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
            $montant_total += $qte * $pu;
        }
        $frais = (float) ($infos['frais_livraison'] ?? 0);
        $montant_total += $frais;

        $stmt = $db->prepare("
            UPDATE devis SET
                client_nom = :client_nom, client_prenom = :client_prenom,
                client_telephone = :client_telephone, client_email = :client_email,
                adresse_livraison = :adresse_livraison, zone_livraison_id = :zone_livraison_id,
                frais_livraison = :frais_livraison, montant_total = :montant_total, notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([
            'client_nom' => trim($infos['client_nom'] ?? ''),
            'client_prenom' => trim($infos['client_prenom'] ?? ''),
            'client_telephone' => trim($infos['client_telephone'] ?? ''),
            'client_email' => !empty(trim($infos['client_email'] ?? '')) ? trim($infos['client_email']) : null,
            'adresse_livraison' => trim($infos['adresse_livraison'] ?? ''),
            'zone_livraison_id' => !empty($infos['zone_livraison_id']) ? (int) $infos['zone_livraison_id'] : null,
            'frais_livraison' => $frais,
            'montant_total' => $montant_total,
            'notes' => !empty(trim($infos['notes'] ?? '')) ? trim($infos['notes']) : null,
            'id' => $devis_id
        ]);

        $db->prepare("DELETE FROM devis_produits WHERE devis_id = :id")->execute(['id' => $devis_id]);

        $stmt_prod = $db->prepare("
            INSERT INTO devis_produits (devis_id, produit_id, nom_produit, quantite, prix_unitaire, prix_total)
            VALUES (:devis_id, :produit_id, :nom_produit, :quantite, :prix_unitaire, :prix_total)
        ");
        foreach ($items as $it) {
            $produit_id = (int) ($it['produit_id'] ?? 0);
            $quantite = (int) ($it['quantite'] ?? 1);
            $prix_unitaire = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
            if ($produit_id <= 0 || $quantite <= 0 || $prix_unitaire <= 0) continue;
            $prix_total = $quantite * $prix_unitaire;
            $nom_produit = isset($it['nom_produit']) && trim($it['nom_produit']) !== '' ? trim($it['nom_produit']) : null;
            $stmt_prod->execute([
                'devis_id' => $devis_id,
                'produit_id' => $produit_id,
                'nom_produit' => $nom_produit,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total
            ]);
        }

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[update_devis] ' . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un devis (uniquement si statut brouillon)
 * @param int $devis_id
 * @return bool
 */
function delete_devis($devis_id) {
    global $db;
    $devis_id = (int) $devis_id;
    if ($devis_id <= 0) {
        return false;
    }
    $d = get_devis_by_id($devis_id);
    if (!$d || ($d['statut'] ?? '') !== 'brouillon') {
        return false;
    }
    require_once __DIR__ . '/model_factures_devis.php';
    if (function_exists('get_facture_devis_by_devis') && get_facture_devis_by_devis($devis_id)) {
        return false;
    }
    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM devis_produits WHERE devis_id = :id')->execute(['id' => $devis_id]);
        $db->prepare('DELETE FROM devis WHERE id = :id')->execute(['id' => $devis_id]);
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[delete_devis] ' . $e->getMessage());
        return false;
    }
}
