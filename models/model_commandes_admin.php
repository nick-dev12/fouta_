<?php
/**
 * Modèle pour la gestion des commandes (Admin)
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin_activite.php';

function _admin_cp_has_option_columns() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'couleur'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

function _admin_cp_has_nom_produit() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'nom_produit'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

function _admin_cp_has_variante_columns() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'variante_id'");
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

/**
 * Récupère toutes les commandes
 * @param string $statut Filtrer par statut (optionnel)
 * @return array|false Tableau des commandes ou False en cas d'erreur
 */
function get_all_commandes($statut = null) {
    global $db;

    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if ($statut) {
            $sql .= " AND c.statut = :statut";
            $params['statut'] = $statut;
        }
        $sql .= " ORDER BY c.date_commande DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $commandes ? $commandes : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une commande par son ID
 * @param int $commande_id L'ID de la commande
 * @param int|null $user_id L'ID de l'utilisateur (optionnel, pour vérifier l'appartenance côté client)
 * @return array|false Les données de la commande ou False si non trouvé
 */
function get_commande_by_id($commande_id, $user_id = null) {
    global $db;

    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email,
                   COALESCE(u.telephone, c.client_telephone, c.telephone_livraison) as user_telephone
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = :id
        ";
        $params = ['id' => $commande_id];
        if ($user_id !== null) {
            $sql .= " AND c.user_id = :user_id";
            $params['user_id'] = (int) $user_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);

        return $commande ? $commande : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les produits d'une commande
 * @param int $commande_id L'ID de la commande
 * @return array|false Tableau des produits ou False en cas d'erreur
 */
function get_produits_by_commande($commande_id) {
    global $db;
    
    try {
        $has_opts = _admin_cp_has_option_columns();
        $has_var = _admin_cp_has_variante_columns();
        
        $produit_nom_col = _admin_cp_has_nom_produit() ? "COALESCE(NULLIF(TRIM(cp.nom_produit), ''), p.nom) as produit_nom" : "p.nom as produit_nom";
        $cols = "cp.*, $produit_nom_col, p.image_principale, c.nom as categorie_nom";
        if ($has_opts) $cols .= ", cp.couleur, cp.poids, cp.taille";
        if ($has_var) {
            $cols .= ", cp.variante_id, COALESCE(NULLIF(TRIM(cp.variante_nom), ''), pv.nom) as variante_nom, cp.surcout_poids, cp.surcout_taille";
            $cols .= ", COALESCE(pv.image, p.image_principale) as image_afficher";
            $join_pv = "LEFT JOIN produits_variantes pv ON cp.variante_id = pv.id AND pv.produit_id = p.id";
        } else {
            $cols .= ", p.image_principale as image_afficher";
            $join_pv = "";
        }
        
        $sql = "
            SELECT $cols
            FROM commande_produits cp
            INNER JOIN produits p ON cp.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            $join_pv
            WHERE cp.commande_id = :commande_id
            ORDER BY cp.id
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['commande_id' => $commande_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut d'une commande
 * Lorsque le statut passe à 'paye', le stock est décrémenté et l'historique des mouvements est enregistré
 * @param int $commande_id L'ID de la commande
 * @param string $statut Le nouveau statut
 * @param int|null $admin_traitant_id Admin ayant effectué le changement de statut (traçabilité)
 * @return bool True en cas de succès, False sinon
 */
function update_commande_statut($commande_id, $statut, $admin_traitant_id = null) {
    global $db;

    $commande = get_commande_by_id($commande_id);
    if (!$commande) return false;

    $ancien_statut = $commande['statut'] ?? '';
    $numero_commande = $commande['numero_commande'] ?? '';

    $set_traitement = '';
    $params_trait = ['id' => $commande_id, 'statut' => $statut];
    if (admin_activite_column_exists('commandes', 'admin_dernier_traitement_id')
        && $admin_traitant_id !== null && (int) $admin_traitant_id > 0) {
        $set_traitement = ', admin_dernier_traitement_id = :traitant';
        $params_trait['traitant'] = (int) $admin_traitant_id;
    }

    if ($statut === 'paye' && $ancien_statut !== 'paye') {
        require_once __DIR__ . '/model_produits.php';
        require_once __DIR__ . '/model_mouvements_stock.php';
        require_once __DIR__ . '/model_commandes.php';

        $produits_commande = get_commande_produits($commande_id);
        if (empty($produits_commande)) {
            return false;
        }

        try {
            $db->beginTransaction();

            foreach ($produits_commande as $item) {
                $produit_id = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
                $quantite = (int) ($item['quantite'] ?? 0);
                if ($produit_id <= 0 || $quantite <= 0) continue;

                $produit = get_produit_by_id($produit_id);
                if (!$produit) continue;

                $quantite_avant = (int) ($produit['stock'] ?? 0);
                decrement_produit_stock($produit_id, $quantite);
                $quantite_apres = max(0, $quantite_avant - $quantite);

                create_stock_mouvement([
                    'type' => 'sortie',
                    'stock_article_id' => null,
                    'produit_id' => $produit_id,
                    'quantite' => $quantite,
                    'quantite_avant' => $quantite_avant,
                    'quantite_apres' => $quantite_apres,
                    'reference_type' => 'commande',
                    'reference_id' => $commande_id,
                    'reference_numero' => $numero_commande,
                    'notes' => 'Vente commande ' . $numero_commande . ' (statut payé)'
                ]);
            }

            $stmt = $db->prepare("
                UPDATE commandes
                SET statut = :statut,
                    date_livraison = CASE WHEN :statut IN ('livree', 'paye') THEN NOW() ELSE date_livraison END
                    $set_traitement
                WHERE id = :id
            ");
            $stmt->execute($params_trait);

            $db->commit();
            return true;
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[update_commande_statut paye] ' . $e->getMessage());
            return false;
        }
    }

    try {
        $stmt = $db->prepare("
            UPDATE commandes
            SET statut = :statut,
                date_livraison = CASE WHEN :statut IN ('livree', 'paye') THEN NOW() ELSE date_livraison END
                $set_traitement
            WHERE id = :id
        ");
        return $stmt->execute($params_trait);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Compte les commandes par statut
 * @param string $statut Le statut à compter
 * @return int Le nombre de commandes
 */
function count_commandes_by_statut($statut = null) {
    global $db;
    
    try {
        if ($statut) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM commandes WHERE statut = :statut");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM commandes");
            $stmt->execute();
        }
        
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Retourne le montant total des commandes (comptabilité)
 * @param string|null $statut Filtrer par statut (optionnel). Si null, toutes les commandes.
 * @return float Montant total en FCFA
 */
function get_montant_total_commandes($statut = null) {
    global $db;
    
    try {
        if ($statut) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(montant_total), 0) FROM commandes WHERE statut = :statut");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->query("SELECT COALESCE(SUM(montant_total), 0) FROM commandes");
        }
        
        return (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Récupère les commandes par période (pour historique ventes / comptabilité)
 * @param string $periode 'jour'|'plage'|'mois'|'annee'
 * @param int|null $annee Année (optionnel, défaut: année courante)
 * @param int|null $mois Mois 1-12 (optionnel, pour jour / mois)
 * @param string|null $date_debut Date début Y-m-d (pour plage)
 * @param string|null $date_fin Date fin Y-m-d (pour plage)
 * @param int|null $jour Jour du mois 1-31 (optionnel, pour jour)
 * @param bool $filtrer_vendues_uniquement Si true : uniquement statuts livrée et payée (ventes finalisées)
 * @return array Tableau des commandes
 */
function get_commandes_by_periode($periode, $annee = null, $mois = null, $date_debut = null, $date_fin = null, $jour = null, $filtrer_vendues_uniquement = false) {
    global $db;
    $annee = $annee ?? (int) date('Y');
    $mois = $mois ?? (int) date('n');
    $jour = $jour ?? (int) date('j');
    
    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($filtrer_vendues_uniquement) {
            $sql .= " AND c.statut IN ('livree', 'paye')";
        }
        
        switch ($periode) {
            case 'jour':
                $sql .= " AND DATE(c.date_commande) = :date_jour";
                $params['date_jour'] = sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
                break;
            case 'plage':
                if (!empty($date_debut) && !empty($date_fin)) {
                    $sql .= " AND DATE(c.date_commande) BETWEEN :date_debut AND :date_fin";
                    $params['date_debut'] = $date_debut;
                    $params['date_fin'] = $date_fin;
                } elseif (!empty($date_debut)) {
                    $sql .= " AND DATE(c.date_commande) >= :date_debut";
                    $params['date_debut'] = $date_debut;
                } elseif (!empty($date_fin)) {
                    $sql .= " AND DATE(c.date_commande) <= :date_fin";
                    $params['date_fin'] = $date_fin;
                } else {
                    $sql .= " AND DATE(c.date_commande) = CURDATE()";
                }
                break;
            case 'mois':
                $sql .= " AND YEAR(c.date_commande) = :annee_mois AND MONTH(c.date_commande) = :num_mois";
                $params['annee_mois'] = $annee;
                $params['num_mois'] = $mois;
                break;
            case 'annee':
                $sql .= " AND YEAR(c.date_commande) = :annee";
                $params['annee'] = $annee;
                break;
            default:
                $sql .= " AND DATE(c.date_commande) = CURDATE()";
        }
        
        $sql .= " ORDER BY c.date_commande DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Totaux globaux (toutes dates) des commandes vendues : statuts livrée et payée uniquement.
 * @return array{nb:int,ca_total:float,ca_livree:float,ca_paye:float}
 */
function get_stats_commandes_vendues_globales() {
    global $db;
    try {
        $sql = "
            SELECT
                COUNT(*) AS nb,
                COALESCE(SUM(montant_total), 0) AS ca_total,
                COALESCE(SUM(CASE WHEN statut = 'livree' THEN montant_total ELSE 0 END), 0) AS ca_livree,
                COALESCE(SUM(CASE WHEN statut = 'paye' THEN montant_total ELSE 0 END), 0) AS ca_paye
            FROM commandes
            WHERE statut IN ('livree', 'paye')
        ";
        $stmt = $db->query($sql);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return ['nb' => 0, 'ca_total' => 0.0, 'ca_livree' => 0.0, 'ca_paye' => 0.0];
        }
        return [
            'nb' => (int) $row['nb'],
            'ca_total' => (float) $row['ca_total'],
            'ca_livree' => (float) $row['ca_livree'],
            'ca_paye' => (float) $row['ca_paye'],
        ];
    } catch (PDOException $e) {
        error_log('[get_stats_commandes_vendues_globales] ' . $e->getMessage());
        return ['nb' => 0, 'ca_total' => 0.0, 'ca_livree' => 0.0, 'ca_paye' => 0.0];
    }
}

/**
 * Liste de toutes les commandes vendues (livrée + payée), plus récentes en premier.
 * @return array
 */
function get_all_commandes_vendues() {
    global $db;
    try {
        $sql = "
            SELECT c.*,
                   COALESCE(u.nom, c.client_nom) as user_nom,
                   COALESCE(u.prenom, c.client_prenom) as user_prenom,
                   COALESCE(u.email, c.client_email) as user_email
            FROM commandes c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.statut IN ('livree', 'paye')
            ORDER BY c.date_commande DESC
        ";
        $stmt = $db->query($sql);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        error_log('[get_all_commandes_vendues] ' . $e->getMessage());
        return [];
    }
}

/**
 * Statistiques pour des commandes déjà limitées aux statuts livrée / payée
 * @param array $commandes
 * @return array{nb:int,ca_total:float,ca_livree:float,ca_paye:float}
 */
function get_stats_ventes_commandes_vendues($commandes) {
    $nb = count($commandes);
    $ca_total = 0.0;
    $ca_livree = 0.0;
    $ca_paye = 0.0;
    foreach ($commandes as $c) {
        $mt = (float) ($c['montant_total'] ?? 0);
        $ca_total += $mt;
        $st = $c['statut'] ?? '';
        if ($st === 'livree') {
            $ca_livree += $mt;
        } elseif ($st === 'paye') {
            $ca_paye += $mt;
        }
    }
    return [
        'nb' => $nb,
        'ca_total' => $ca_total,
        'ca_livree' => $ca_livree,
        'ca_paye' => $ca_paye,
    ];
}

/**
 * Statistiques comptabilité par période
 * @param array $commandes Tableau des commandes (déjà filtrées)
 * @return array [montant_total, nb_commandes, montant_livrees, montant_non_traitees, nb_livrees, nb_non_traitees]
 */
function get_stats_comptabilite_periode($commandes) {
    $montant_total = 0;
    $montant_livrees = 0;
    $montant_non_traitees = 0;
    $nb_livrees = 0;
    $nb_non_traitees = 0;
    
    foreach ($commandes as $c) {
        $mt = (float) ($c['montant_total'] ?? 0);
        $montant_total += $mt;
        if ($c['statut'] === 'livree') {
            $montant_livrees += $mt;
            $nb_livrees++;
        } elseif ($c['statut'] !== 'annulee') {
            $montant_non_traitees += $mt;
            $nb_non_traitees++;
        }
    }
    
    return [
        'montant_total' => $montant_total,
        'nb_commandes' => count($commandes),
        'montant_livrees' => $montant_livrees,
        'montant_non_traitees' => $montant_non_traitees,
        'nb_livrees' => $nb_livrees,
        'nb_non_traitees' => $nb_non_traitees
    ];
}

?>

