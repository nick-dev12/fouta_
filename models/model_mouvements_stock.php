<?php
/**
 * Modèle pour les mouvements de stock (entrées, sorties, inventaires)
 * Stock géré uniquement par produits.stock (table stock_articles supprimée)
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Colonne présente sur stock_mouvements (cache SHOW COLUMNS)
 */
function stock_mouvements_has_column($name) {
    static $cols = null;
    global $db;
    if ($cols === null) {
        $cols = [];
        if (!$db) {
            return false;
        }
        try {
            $stmt = $db->query('SHOW COLUMNS FROM stock_mouvements');
            if ($stmt) {
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[$r['Field']] = true;
                }
            }
        } catch (PDOException $e) {
            $cols = [];
        }
    }
    return isset($cols[$name]);
}

/**
 * Enregistre un mouvement de stock
 * @param array $data ['type', 'produit_id'?, 'quantite', 'quantite_avant'?, 'quantite_apres'?, 'reference_type'?, 'reference_id'?, 'reference_numero'?, 'notes'?, 'admin_id'?]
 * @return int|false ID du mouvement ou False
 */
function create_stock_mouvement($data)
{
    global $db;

    try {
        $cols = 'type, produit_id, quantite, quantite_avant, quantite_apres, reference_type, reference_id, reference_numero, date_mouvement, notes';
        $ph = ':type, :produit_id, :quantite, :quantite_avant, :quantite_apres, :reference_type, :reference_id, :reference_numero, NOW(), :notes';
        $params = [
            'type' => $data['type'],
            'produit_id' => $data['produit_id'] ?? null,
            'quantite' => (int) $data['quantite'],
            'quantite_avant' => isset($data['quantite_avant']) ? (int) $data['quantite_avant'] : null,
            'quantite_apres' => isset($data['quantite_apres']) ? (int) $data['quantite_apres'] : null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_numero' => $data['reference_numero'] ?? null,
            'notes' => $data['notes'] ?? null
        ];
        if (stock_mouvements_has_column('admin_id') && !empty($data['admin_id'])) {
            $cols = 'type, produit_id, quantite, quantite_avant, quantite_apres, reference_type, reference_id, reference_numero, date_mouvement, notes, admin_id';
            $ph = ':type, :produit_id, :quantite, :quantite_avant, :quantite_apres, :reference_type, :reference_id, :reference_numero, NOW(), :notes, :admin_id';
            $params['admin_id'] = (int) $data['admin_id'];
        }
        /* LE TRANSFERT D'EMPLACEMENT (24/08) : d'où la pièce part, où elle va.
         * Les deux colonnes existaient dans la table — rien ne les écrivait. */
        if (stock_mouvements_has_column('emplacement_source_id') && !empty($data['emplacement_source_id'])) {
            $cols .= ', emplacement_source_id';
            $ph .= ', :emplacement_source_id';
            $params['emplacement_source_id'] = (int) $data['emplacement_source_id'];
        }
        if (stock_mouvements_has_column('emplacement_destination_id') && !empty($data['emplacement_destination_id'])) {
            $cols .= ', emplacement_destination_id';
            $ph .= ', :emplacement_destination_id';
            $params['emplacement_destination_id'] = (int) $data['emplacement_destination_id'];
        }
        $stmt = $db->prepare("INSERT INTO stock_mouvements ($cols) VALUES ($ph)");
        $stmt->execute($params);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les mouvements avec filtres (produits uniquement)
 * @param int|null $stock_article_id Ignoré (conservé pour compatibilité)
 * @param int|null $produit_id Filtrer par produit
 * @param int|null $categorie_id Filtrer par catégorie
 * @param string|null $type Filtrer par type (entree, sortie, inventaire)
 * @param int $limit Nombre max
 * @return array
 */
function stock_mouvements_build_where($categorie_id = null, $type = null, $search = null, &$params = [], $du = null, $au = null)
{
    $sql = '';
    /* Les DATES (24/08) : le registre global se consulte par période. */
    if ($du !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $du)) {
        $sql .= ' AND DATE(m.date_mouvement) >= :du';
        $params['du'] = (string) $du;
    }
    if ($au !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $au)) {
        $sql .= ' AND DATE(m.date_mouvement) <= :au';
        $params['au'] = (string) $au;
    }
    if ($categorie_id !== null && (int) $categorie_id > 0) {
        $sql .= ' AND m.produit_id IS NOT NULL AND p.categorie_id = :categorie_id';
        $params['categorie_id'] = (int) $categorie_id;
    }
    // 'transfert' ajouté le 24/08 : l'Historique le propose au filtre.
    if ($type !== null && in_array($type, ['entree', 'sortie', 'transfert', 'inventaire'], true)) {
        $sql .= ' AND m.type = :type';
        $params['type'] = $type;
    }
    if ($search !== null && trim((string) $search) !== '') {
        $sql .= ' AND (
            p.nom LIKE :search OR
            COALESCE(m.reference_numero, \'\') LIKE :search OR
            COALESCE(m.notes, \'\') LIKE :search OR
            COALESCE(m.reference_type, \'\') LIKE :search OR
            CAST(COALESCE(m.reference_id, \'\') AS CHAR) LIKE :search
        )';
        $params['search'] = '%' . trim((string) $search) . '%';
    }

    return $sql;
}

function count_stock_mouvements($categorie_id = null, $type = null, $search = null, $produit_id = null, $du = null, $au = null)
{
    global $db;

    try {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM stock_mouvements m LEFT JOIN produits p ON m.produit_id = p.id WHERE 1=1';
        if ($produit_id !== null && (int) $produit_id > 0) {
            $sql .= ' AND m.produit_id = :produit_id';
            $params['produit_id'] = (int) $produit_id;
        }
        $sql .= stock_mouvements_build_where($categorie_id, $type, $search, $params, $du, $au);
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function get_stock_mouvements_paginated($categorie_id = null, $type = null, $search = null, $offset = 0, $limit = 25, $produit_id = null, $du = null, $au = null)
{
    global $db;

    try {
        $params = ['limit' => (int) $limit, 'offset' => (int) $offset];
        /* D'OÙ ET VERS OÙ — repris de la fiche de FPL natif. Les deux colonnes
         * emplacement_source_id et emplacement_destination_id existaient déjà
         * ici ; personne n'allait chercher le nom du nœud au bout. On le fait
         * en sous-requêtes plutôt qu'en jointures, pour ne rien changer au
         * nombre de lignes rendues aux écrans qui appellent déjà cette
         * fonction. Les clés ajoutées ne gênent aucun appelant existant. */
        $sql = 'SELECT m.*, p.nom as produit_nom, p.categorie_id as produit_categorie_id,
                       (SELECT ns.code_scan FROM entrepot_hierarchie_noeud ns WHERE ns.id = m.emplacement_source_id LIMIT 1) AS source_code,
                       (SELECT ns.nom       FROM entrepot_hierarchie_noeud ns WHERE ns.id = m.emplacement_source_id LIMIT 1) AS source_nom,
                       (SELECT nd.code_scan FROM entrepot_hierarchie_noeud nd WHERE nd.id = m.emplacement_destination_id LIMIT 1) AS destination_code,
                       (SELECT nd.nom       FROM entrepot_hierarchie_noeud nd WHERE nd.id = m.emplacement_destination_id LIMIT 1) AS destination_nom
                FROM stock_mouvements m
                LEFT JOIN produits p ON m.produit_id = p.id
                WHERE 1=1';
        if ($produit_id !== null && (int) $produit_id > 0) {
            $sql .= ' AND m.produit_id = :produit_id';
            $params['produit_id'] = (int) $produit_id;
        }
        $sql .= stock_mouvements_build_where($categorie_id, $type, $search, $params, $du, $au);
        $sql .= ' ORDER BY m.date_mouvement DESC LIMIT :limit OFFSET :offset';
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_stock_mouvements($stock_article_id = null, $produit_id = null, $categorie_id = null, $type = null, $limit = 100)
{
    return get_stock_mouvements_paginated($categorie_id, $type, null, 0, (int) $limit, $produit_id);
}

/**
 * Le motif LISIBLE d'un mouvement (25/08) — l'écran Entrée et le Rapport
 * journalier parlent d'une seule voix.
 * @param array<string, mixed> $m
 */
function stock_mouvement_motif_libelle(array $m)
{
    $ref = (string) ($m['reference_type'] ?? '');
    switch ($ref) {
        case 'entree_manuelle': return 'Entrée en stock';
        case 'defectueux': return 'Pièce défectueuse';
        case 'correction': return 'Correction';
        case 'transfert_emplacement': return 'Transfert d\'emplacement';
        case 'ajustement': return 'Ajustement';
        case 'creation_produit': return 'Stock initial';
    }
    if ($ref !== '') {
        return ucfirst(str_replace('_', ' ', $ref));
    }
    $type = (string) ($m['type'] ?? '');
    if ($type === 'entree') {
        return 'Entrée';
    }
    if ($type === 'sortie') {
        return 'Sortie';
    }
    if ($type === 'transfert') {
        return 'Transfert d\'emplacement';
    }
    if ($type === 'inventaire') {
        return 'Inventaire';
    }

    return '—';
}

/**
 * Le SIGNE d'un mouvement, pour l'affichage : − une sortie, ⇄ un transfert,
 * + le reste (entrée, inventaire). Recopié à l'identique sur Mon travail, le
 * Rapport du jour et l'Historique avant d'être centralisé ici.
 *
 * @param string $type
 * @return string
 */
function stock_mouvement_signe($type)
{
    if ($type === 'sortie') {
        return '−';
    }
    if ($type === 'transfert') {
        return '⇄';
    }

    return '+';
}
