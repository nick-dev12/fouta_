<?php
/**
 * Statistiques tableau de bord admin
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function dashboard_bl_disponible() {
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!function_exists('bl_tables_available')) {
        require_once __DIR__ . '/model_bl.php';
    }
    $ok = bl_tables_available();
    return $ok;
}

/**
 * Ventes par mois pour une année (boutique + BL validés)
 *
 * @return array<int, array{mois:int,label:string,qte:float,montant:float}>
 */
function dashboard_ventes_mensuelles_annee($annee = null) {
    global $db;
    $annee = (int) ($annee ?? date('Y'));
    $labels_fr = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
    $out = [];
    for ($m = 1; $m <= 12; $m++) {
        $out[$m] = [
            'mois' => $m,
            'label' => $labels_fr[$m],
            'qte' => 0.0,
            'montant' => 0.0,
        ];
    }

    try {
        $stmt = $db->prepare('
            SELECT MONTH(c.date_commande) AS mois,
                   COALESCE(SUM(cp.quantite), 0) AS qte,
                   COALESCE(SUM(cp.prix_total), 0) AS montant
            FROM commande_produits cp
            INNER JOIN commandes c ON c.id = cp.commande_id
            WHERE YEAR(c.date_commande) = :a
              AND c.statut IN (\'livree\', \'paye\')
            GROUP BY MONTH(c.date_commande)
        ');
        $stmt->execute(['a' => $annee]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $m = (int) ($row['mois'] ?? 0);
            if ($m >= 1 && $m <= 12) {
                $out[$m]['qte'] += (float) ($row['qte'] ?? 0);
                $out[$m]['montant'] += (float) ($row['montant'] ?? 0);
            }
        }
    } catch (PDOException $e) {
        error_log('[dashboard_ventes_mensuelles_annee/boutique] ' . $e->getMessage());
    }

    if (dashboard_bl_disponible()) {
        try {
            $stmt = $db->prepare('
                SELECT MONTH(b.date_bl) AS mois,
                       COALESCE(SUM(l.quantite), 0) AS qte,
                       COALESCE(SUM(l.total_ligne_ht), 0) AS montant
                FROM bl_lignes l
                INNER JOIN bons_livraison b ON b.id = l.bl_id
                WHERE YEAR(b.date_bl) = :a
                  AND b.statut IN (\'valide\', \'paye\')
                GROUP BY MONTH(b.date_bl)
            ');
            $stmt->execute(['a' => $annee]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $m = (int) ($row['mois'] ?? 0);
                if ($m >= 1 && $m <= 12) {
                    $out[$m]['qte'] += (float) ($row['qte'] ?? 0);
                    $out[$m]['montant'] += (float) ($row['montant'] ?? 0);
                }
            }
        } catch (PDOException $e) {
            error_log('[dashboard_ventes_mensuelles_annee/bl] ' . $e->getMessage());
        }
    }

    return array_values($out);
}

/**
 * Top produits vendus (boutique + BL)
 *
 * @return array<int, array<string, mixed>>
 */
function dashboard_top_produits_vendus($limit = 10) {
    global $db;
    $limit = max(1, min(50, (int) $limit));
    $rows = [];

    try {
        $sql = '
            SELECT produit_id, nom, SUM(qte) AS total_qte, SUM(montant) AS total_montant
            FROM (
                SELECT cp.produit_id,
                       COALESCE(NULLIF(TRIM(p.nom), \'\'), NULLIF(TRIM(cp.nom_produit), \'\'), CONCAT(\'Produit #\', cp.produit_id)) AS nom,
                       cp.quantite AS qte,
                       cp.prix_total AS montant
                FROM commande_produits cp
                INNER JOIN commandes c ON c.id = cp.commande_id
                LEFT JOIN produits p ON p.id = cp.produit_id
                WHERE c.statut IN (\'livree\', \'paye\')
        ';
        if (dashboard_bl_disponible()) {
            $sql .= '
                UNION ALL
                SELECT COALESCE(l.produit_id, 0) AS produit_id,
                       COALESCE(NULLIF(TRIM(l.designation), \'\'), CONCAT(\'Ligne BL #\', l.id)) AS nom,
                       l.quantite AS qte,
                       l.total_ligne_ht AS montant
                FROM bl_lignes l
                INNER JOIN bons_livraison b ON b.id = l.bl_id
                WHERE b.statut IN (\'valide\', \'paye\')
            ';
        }
        $sql .= '
            ) ventes
            GROUP BY produit_id, nom
            HAVING total_qte > 0
            ORDER BY total_qte DESC, total_montant DESC
            LIMIT ' . (int) $limit;
        $stmt = $db->query($sql);
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        error_log('[dashboard_top_produits_vendus] ' . $e->getMessage());
    }

    return $rows;
}

/**
 * Détails produits top vendus pour le tableau dashboard
 *
 * @return array<int, array<string, mixed>>
 */
function dashboard_produits_top_vendus_details($limit = 15) {
    global $db;
    $limit = max(1, min(30, (int) $limit));
    $tops = dashboard_top_produits_vendus($limit);
    if (empty($tops)) {
        return [];
    }

    $ids = [];
    foreach ($tops as $t) {
        $pid = (int) ($t['produit_id'] ?? 0);
        if ($pid > 0) {
            $ids[$pid] = $pid;
        }
    }

    $produits_by_id = [];
    if (!empty($ids)) {
        try {
            $in = implode(',', array_map('intval', $ids));
            $stmt = $db->query('
                SELECT p.*, c.nom AS categorie_nom
                FROM produits p
                LEFT JOIN categories c ON c.id = p.categorie_id
                WHERE p.id IN (' . $in . ')
            ');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $p) {
                $produits_by_id[(int) $p['id']] = $p;
            }
        } catch (PDOException $e) {
            error_log('[dashboard_produits_top_vendus_details] ' . $e->getMessage());
        }
    }

    $out = [];
    foreach ($tops as $t) {
        $pid = (int) ($t['produit_id'] ?? 0);
        $row = [
            'produit_id' => $pid,
            'nom' => (string) ($t['nom'] ?? ''),
            'total_qte' => (float) ($t['total_qte'] ?? 0),
            'total_montant' => (float) ($t['total_montant'] ?? 0),
            'prix' => null,
            'stock' => null,
            'statut' => '',
            'categorie_nom' => '',
            'image_principale' => '',
            'reference' => '',
        ];
        if ($pid > 0 && isset($produits_by_id[$pid])) {
            $p = $produits_by_id[$pid];
            $row['nom'] = (string) ($p['nom'] ?? $row['nom']);
            $row['prix'] = isset($p['prix_promotion']) && $p['prix_promotion'] !== null && $p['prix_promotion'] !== ''
                ? (float) $p['prix_promotion'] : (float) ($p['prix'] ?? 0);
            $row['stock'] = (int) ($p['stock'] ?? 0);
            $row['statut'] = (string) ($p['statut'] ?? '');
            $row['categorie_nom'] = (string) ($p['categorie_nom'] ?? '');
            $row['image_principale'] = (string) ($p['image_principale'] ?? '');
            $row['reference'] = (string) ($p['reference'] ?? $p['code_produit'] ?? '');
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * BL récents sur N jours (pour graphique)
 *
 * @return array<int, array<string, mixed>>
 */
function dashboard_bl_recents_jours($jours = 7, $limit = 10) {
    global $db;
    if (!dashboard_bl_disponible()) {
        return [];
    }
    $jours = max(1, min(30, (int) $jours));
    $limit = max(1, min(20, (int) $limit));
    $date_debut = date('Y-m-d', strtotime('-' . ($jours - 1) . ' days'));

    try {
        $stmt = $db->prepare('
            SELECT b.id, b.numero_bl, b.date_bl, b.total_ht, b.statut,
                   COALESCE(b.facture_bl_payee, 0) AS facture_bl_payee,
                   c.raison_sociale,
                   COALESCE(SUM(l.quantite), 0) AS nb_pieces
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON c.id = b.client_b2b_id
            LEFT JOIN bl_lignes l ON l.bl_id = b.id
            WHERE DATE(b.date_bl) >= :d
            GROUP BY b.id, b.numero_bl, b.date_bl, b.total_ht, b.statut, b.facture_bl_payee, c.raison_sociale
            ORDER BY b.date_bl DESC, b.id DESC
            LIMIT ' . (int) $limit
        );
        $stmt->execute(['d' => $date_debut]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[dashboard_bl_recents_jours] ' . $e->getMessage());
        return [];
    }
}

/**
 * Statistiques du jour (boutique + BL)
 *
 * @return array<string, float|int>
 */
function dashboard_stats_jour() {
    global $db;
    $today = date('Y-m-d');
    $out = [
        'ca_jour' => 0.0,
        'qte_produits_jour' => 0.0,
        'nb_commandes_jour' => 0,
        'nb_bl_jour' => 0,
        'bl_payes' => 0,
        'bl_impayes' => 0,
        'ca_bl_jour' => 0.0,
        'nb_clients_bl_jour' => 0,
    ];

    try {
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT c.id) AS nb_cmd,
                   COALESCE(SUM(c.montant_total), 0) AS ca
            FROM commandes c
            WHERE DATE(c.date_commande) = :d
              AND c.statut IN (\'livree\', \'paye\')
        ');
        $stmt->execute(['d' => $today]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $out['nb_commandes_jour'] = (int) ($r['nb_cmd'] ?? 0);
            $out['ca_jour'] += (float) ($r['ca'] ?? 0);
        }

        $stmt = $db->prepare('
            SELECT COALESCE(SUM(cp.quantite), 0) AS qte,
                   COALESCE(SUM(cp.prix_total), 0) AS ca
            FROM commande_produits cp
            INNER JOIN commandes c ON c.id = cp.commande_id
            WHERE DATE(c.date_commande) = :d
              AND c.statut IN (\'livree\', \'paye\')
        ');
        $stmt->execute(['d' => $today]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $out['qte_produits_jour'] += (float) ($r['qte'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('[dashboard_stats_jour/boutique] ' . $e->getMessage());
    }

    if (dashboard_bl_disponible()) {
        try {
            $stmt = $db->prepare('
                SELECT COUNT(*) AS nb,
                       COUNT(DISTINCT client_b2b_id) AS nb_clients,
                       COALESCE(SUM(total_ht), 0) AS ca
                FROM bons_livraison
                WHERE DATE(date_bl) = :d
                  AND statut IN (\'valide\', \'paye\', \'brouillon\')
            ');
            $stmt->execute(['d' => $today]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $out['nb_bl_jour'] = (int) ($r['nb'] ?? 0);
                $out['nb_clients_bl_jour'] = (int) ($r['nb_clients'] ?? 0);
                $out['ca_bl_jour'] = (float) ($r['ca'] ?? 0);
                $out['ca_jour'] += (float) ($r['ca'] ?? 0);
            }

            $stmt = $db->prepare('
                SELECT COALESCE(SUM(l.quantite), 0) AS qte
                FROM bl_lignes l
                INNER JOIN bons_livraison b ON b.id = l.bl_id
                WHERE DATE(b.date_bl) = :d
                  AND b.statut IN (\'valide\', \'paye\')
            ');
            $stmt->execute(['d' => $today]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $out['qte_produits_jour'] += (float) ($r['qte'] ?? 0);
            }

            if (function_exists('bl_col_facture_payee_ok') && bl_col_facture_payee_ok()) {
                $stmt = $db->query('
                    SELECT
                        COALESCE(SUM(CASE WHEN statut IN (\'valide\', \'paye\') AND COALESCE(facture_bl_payee, 0) = 1 THEN 1 ELSE 0 END), 0) AS payes,
                        COALESCE(SUM(CASE WHEN statut IN (\'valide\', \'paye\') AND COALESCE(facture_bl_payee, 0) = 0 THEN 1 ELSE 0 END), 0) AS impayes
                    FROM bons_livraison
                ');
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $out['bl_payes'] = (int) ($r['payes'] ?? 0);
                    $out['bl_impayes'] = (int) ($r['impayes'] ?? 0);
                }
            } else {
                $stmt = $db->query('
                    SELECT
                        COALESCE(SUM(CASE WHEN statut IN (\'valide\', \'paye\') THEN 1 ELSE 0 END), 0) AS payes,
                        COALESCE(SUM(CASE WHEN statut = \'brouillon\' THEN 1 ELSE 0 END), 0) AS impayes
                    FROM bons_livraison
                ');
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $out['bl_payes'] = (int) ($r['payes'] ?? 0);
                    $out['bl_impayes'] = (int) ($r['impayes'] ?? 0);
                }
            }
        } catch (PDOException $e) {
            error_log('[dashboard_stats_jour/bl] ' . $e->getMessage());
        }
    }

    return $out;
}

/**
 * Données JSON pour Chart.js
 *
 * @return array<string, mixed>
 */
function dashboard_charts_payload($annee = null) {
    $annee = (int) ($annee ?? date('Y'));
    $mensuel = dashboard_ventes_mensuelles_annee($annee);
    $top = dashboard_top_produits_vendus(10);
    $bl = dashboard_bl_recents_jours(7, 10);

    return [
        'annee' => $annee,
        'mensuel' => [
            'labels' => array_column($mensuel, 'label'),
            'qte' => array_map(function ($r) { return (float) $r['qte']; }, $mensuel),
            'montant' => array_map(function ($r) { return (float) $r['montant']; }, $mensuel),
        ],
        'top_produits' => [
            'labels' => array_map(function ($r) {
                $nom = (string) ($r['nom'] ?? '');
                return mb_strlen($nom) > 28 ? mb_substr($nom, 0, 25) . '…' : $nom;
            }, $top),
            'qte' => array_map(function ($r) { return (float) ($r['total_qte'] ?? 0); }, $top),
        ],
        'bl_recents' => [
            'labels' => array_map(function ($r) {
                $num = (string) ($r['numero_bl'] ?? ('BL#' . ($r['id'] ?? '')));
                return mb_strlen($num) > 16 ? mb_substr($num, 0, 13) . '…' : $num;
            }, $bl),
            'clients' => array_map(function ($r) {
                return (string) ($r['raison_sociale'] ?? '');
            }, $bl),
            'pieces' => array_map(function ($r) { return (float) ($r['nb_pieces'] ?? 0); }, $bl),
            'montant' => array_map(function ($r) { return (float) ($r['total_ht'] ?? 0); }, $bl),
        ],
    ];
}
