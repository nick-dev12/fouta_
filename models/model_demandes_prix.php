<?php
/**
 * DEMANDES DE PRIX (11/09/2026).
 *
 * Décision de la direction : le vendeur ne tape plus aucun prix. Le prix d'un
 * ticket, d'un devis, d'un bon de livraison ou d'une commande vient toujours du
 * catalogue. Une pièce sans prix ne se vend pas : le vendeur demande son prix,
 * et seul le profil qui a le droit d'écrire le prix de la fiche pièce (le
 * responsable de stock) le fixe, depuis admin/produits/prix-demandes.php.
 *
 * Règles :
 * - une demande porte sur une colonne de prix : le prix de vente, ou le Prix
 *   Entreprise / Grossiste choisi pour le total d'un devis ; une promotion ne
 *   se demande pas ;
 * - une pièce n'a qu'une demande en attente par colonne : une nouvelle demande
 *   s'y ajoute (nombre de demandes, dernier demandeur) ; le même vendeur qui
 *   insiste dans les dix minutes ne compte qu'une fois ;
 * - une pièce qui a déjà ce prix ne se demande pas ;
 * - une demande se solde d'elle-même dès que la pièce a ce prix au catalogue,
 *   quel que soit l'écran où il a été fixé : demandes_prix_solder_prix_poses()
 *   lit d'abord et n'écrit que s'il y a quelque chose à solder ;
 * - dans un document, le prix envoyé par l'écran est ignoré :
 *   demandes_prix_lignes_document(), _lignes_commande() et _lignes_bl()
 *   reprennent le catalogue, et demandes_prix_refus_document() demande le prix
 *   des pièces qui n'en ont pas.
 *
 * Tables : migrations/run_demandes_prix.php.
 */

require_once __DIR__ . '/../conn/conn.php';

/** La table des demandes de prix, avec sa colonne « champ », existe-t-elle ? */
function demandes_prix_table_ok()
{
    global $db;
    static $ok = false;
    if ($ok || !$db) {
        return $ok;
    }
    try {
        $ok = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'demandes_prix' AND COLUMN_NAME = 'champ'")->fetchColumn() === 1;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Les colonnes utiles de la table des pièces, telles qu'elles existent sur ce serveur.
 *
 * @return array<string, true>
 */
function demandes_prix_colonnes_produits()
{
    global $db;
    static $colonnes = null;
    if ($colonnes !== null) {
        return $colonnes;
    }
    $colonnes = [];
    try {
        $st = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits'
              AND COLUMN_NAME IN ('prix', 'prix_promotion', 'prix_entreprise', 'prix_achat', 'sync_updated_at', 'admin_dernier_modificateur_id')");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $nom) {
            $colonnes[(string) $nom] = true;
        }
    } catch (PDOException $e) {
        error_log('[demandes_prix_colonnes_produits] ' . $e->getMessage());
    }
    return $colonnes;
}

/** Les colonnes de prix qui se demandent : le prix de vente, et les prix d'un devis hors promotion. */
function demandes_prix_champs_demandables()
{
    $colonnes = demandes_prix_colonnes_produits();
    return array_values(array_filter(['prix', 'prix_entreprise', 'prix_achat'], static function ($champ) use ($colonnes) {
        return isset($colonnes[$champ]);
    }));
}

/** Le nom d'une colonne de prix, tel que la fiche pièce l'affiche. */
function demandes_prix_libelle_champ($champ)
{
    static $cache = [];
    $champ = (string) $champ;
    if (isset($cache[$champ])) {
        return $cache[$champ];
    }
    require_once __DIR__ . '/model_produit_formulaire_champs.php';
    $ligne = produit_formulaire_champ_get_by_slug($champ);
    $libelle = is_array($ligne) ? trim((string) ($ligne['label'] ?? '')) : '';
    if ($libelle === '') {
        $defauts = ['prix' => 'Prix de vente', 'prix_promotion' => 'Prix promotionnel', 'prix_entreprise' => 'Prix Entreprise', 'prix_achat' => 'Prix Grossiste'];
        $libelle = $defauts[$champ] ?? $champ;
    }
    return $cache[$champ] = $libelle;
}

/**
 * Le prix d'une pièce au catalogue dans une colonne. Pour le prix de vente : la
 * promotion si elle existe, sinon le prix, comme la vente directe.
 */
function demandes_prix_valeur_catalogue(array $piece, $champ)
{
    $champ = (string) $champ;
    if ($champ === 'prix') {
        $promo = (float) ($piece['prix_promotion'] ?? 0);
        return round($promo > 0 ? $promo : (float) ($piece['prix'] ?? 0), 2);
    }
    return round((float) ($piece[$champ] ?? 0), 2);
}

/**
 * La condition SQL « la pièce a maintenant le prix que sa demande attend »
 * (alias d = demandes_prix, p = produits).
 */
function demandes_prix_sql_prix_pose()
{
    $colonnes = demandes_prix_colonnes_produits();
    $cas = ["(d.champ = 'prix' AND (COALESCE(p.prix, 0) > 0 OR COALESCE(p.prix_promotion, 0) > 0))"];
    foreach (['prix_entreprise', 'prix_achat'] as $champ) {
        if (isset($colonnes[$champ])) {
            $cas[] = "(d.champ = '$champ' AND COALESCE(p.$champ, 0) > 0)";
        }
    }
    return '(' . implode(' OR ', $cas) . ')';
}

/**
 * Une pièce et ses prix ; verrouillée pour une écriture quand $verrou.
 *
 * @return array<string, mixed>|null
 */
function demandes_prix_piece($produit_id, $verrou = false)
{
    global $db;
    $colonnes = ['id', 'nom', 'identifiant_interne', 'stock', 'statut'];
    foreach (['prix', 'prix_promotion', 'prix_entreprise', 'prix_achat'] as $champ) {
        if (isset(demandes_prix_colonnes_produits()[$champ])) {
            $colonnes[] = $champ;
        }
    }
    $st = $db->prepare('SELECT ' . implode(', ', $colonnes) . ' FROM produits WHERE id = :id AND sync_deleted_at IS NULL' . ($verrou ? ' FOR UPDATE' : ''));
    $st->execute(['id' => (int) $produit_id]);
    $piece = $st->fetch(PDO::FETCH_ASSOC);
    return $piece ?: null;
}

/**
 * Le vendeur demande le prix d'une pièce, dans une colonne.
 *
 * @return array{ok:bool, error?:string, a_deja_prix?:bool, deja?:bool, nom?:string, nb?:int, champ?:string, libelle?:string}
 */
function demande_prix_creer($produit_id, $admin_id, $champ = 'prix')
{
    global $db;
    if (!demandes_prix_table_ok()) {
        return ['ok' => false, 'error' => 'Les demandes de prix attendent la mise à jour de la base (migrations/run_demandes_prix.php).'];
    }
    $produit_id = (int) $produit_id;
    $admin_id = (int) $admin_id;
    $champ = (string) $champ;
    if ($admin_id <= 0) {
        return ['ok' => false, 'error' => 'Vendeur inconnu : reconnectez-vous.'];
    }
    if (!in_array($champ, demandes_prix_champs_demandables(), true)) {
        return ['ok' => false, 'error' => 'Ce prix ne se demande pas : prenez le prix de vente pour le total.'];
    }
    $libelle = demandes_prix_libelle_champ($champ);
    try {
        $db->beginTransaction();
        $piece = demandes_prix_piece($produit_id, true);
        if (!$piece) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Pièce introuvable.'];
        }
        $prix = demandes_prix_valeur_catalogue($piece, $champ);
        if ($prix > 0) {
            $db->rollBack();
            return ['ok' => false, 'a_deja_prix' => true, 'error' => '« ' . $piece['nom'] . ' » a déjà son prix au catalogue ('
                . $libelle . ' : ' . number_format($prix, 0, ',', ' ') . ' FCFA). Recherchez-la de nouveau.'];
        }
        $st = $db->prepare("SELECT id, nb_demandes, dernier_demandeur_id, date_derniere_demande > NOW() - INTERVAL 10 MINUTE AS recente
            FROM demandes_prix WHERE produit_id = :p AND champ = :c AND statut = 'en_attente' AND sync_deleted_at IS NULL LIMIT 1 FOR UPDATE");
        $st->execute(['p' => $produit_id, 'c' => $champ]);
        $ouverte = $st->fetch(PDO::FETCH_ASSOC);
        if ($ouverte) {
            $nb = (int) $ouverte['nb_demandes'];
            if (!((int) $ouverte['dernier_demandeur_id'] === $admin_id && !empty($ouverte['recente']))) {
                $db->prepare('UPDATE demandes_prix SET nb_demandes = nb_demandes + 1, date_derniere_demande = NOW(), dernier_demandeur_id = :a WHERE id = :id')
                    ->execute(['a' => $admin_id, 'id' => (int) $ouverte['id']]);
                $nb++;
            }
            $db->commit();
            return ['ok' => true, 'deja' => true, 'nom' => (string) $piece['nom'], 'nb' => $nb, 'champ' => $champ, 'libelle' => $libelle];
        }
        $db->prepare("INSERT INTO demandes_prix (produit_id, champ, admin_id, statut, nb_demandes, date_demande, date_derniere_demande, dernier_demandeur_id)
            VALUES (:p, :c, :a, 'en_attente', 1, NOW(), NOW(), :a2)")
            ->execute(['p' => $produit_id, 'c' => $champ, 'a' => $admin_id, 'a2' => $admin_id]);
        $db->commit();
        return ['ok' => true, 'deja' => false, 'nom' => (string) $piece['nom'], 'nb' => 1, 'champ' => $champ, 'libelle' => $libelle];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[demande_prix_creer] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'La demande de prix n’a pas pu être enregistrée.'];
    }
}

/** Le message montré au vendeur après sa demande. */
function demandes_prix_message_demande(array $res)
{
    $champ = (string) ($res['champ'] ?? 'prix');
    $libelle = $champ === 'prix' ? 'prix' : (string) ($res['libelle'] ?? 'prix');
    $nb = (int) ($res['nb'] ?? 1);
    if (!empty($res['deja'])) {
        return 'Le ' . $libelle . ' de « ' . $res['nom'] . ' » est déjà demandé : votre demande s’ajoute ('
            . $nb . ' demande' . ($nb > 1 ? 's' : '') . '). Le responsable de stock le fixera.';
    }
    return ($champ === 'prix' ? 'Prix' : $libelle) . ' demandé pour « ' . $res['nom'] . ' » : le responsable de stock le fixera.';
}

/**
 * Solde les demandes en attente dont la pièce a maintenant le prix attendu,
 * d'où qu'il ait été fixé. Lit d'abord ; n'écrit que s'il y a quelque chose à solder.
 *
 * @return int nombre de demandes soldées
 */
function demandes_prix_solder_prix_poses($traite_par = 0)
{
    global $db;
    if (!demandes_prix_table_ok()) {
        return 0;
    }
    try {
        $colonnes = ['p.prix', 'p.prix_promotion'];
        foreach (['prix_entreprise', 'prix_achat'] as $champ) {
            if (isset(demandes_prix_colonnes_produits()[$champ])) {
                $colonnes[] = 'p.' . $champ;
            }
        }
        $a_solder = $db->query('SELECT d.id, d.champ, ' . implode(', ', $colonnes) . "
            FROM demandes_prix d INNER JOIN produits p ON p.id = d.produit_id
            WHERE d.statut = 'en_attente' AND d.sync_deleted_at IS NULL AND " . demandes_prix_sql_prix_pose())->fetchAll(PDO::FETCH_ASSOC);
        if (!$a_solder) {
            return 0;
        }
        $maj = $db->prepare("UPDATE demandes_prix SET statut = 'traitee', date_traitement = NOW(), prix_fixe = :prix, traite_par = :par
            WHERE id = :id AND statut = 'en_attente'");
        $n = 0;
        foreach ($a_solder as $ligne) {
            $maj->execute([
                'prix' => demandes_prix_valeur_catalogue($ligne, $ligne['champ']),
                'par' => (int) $traite_par > 0 ? (int) $traite_par : null,
                'id' => (int) $ligne['id'],
            ]);
            $n += $maj->rowCount();
        }
        return $n;
    } catch (PDOException $e) {
        error_log('[demandes_prix_solder_prix_poses] ' . $e->getMessage());
        return 0;
    }
}

/** Le nombre de prix en attente, pour le menu de qui les fixe. */
function demandes_prix_nb_en_attente()
{
    global $db;
    if (!demandes_prix_table_ok()) {
        return 0;
    }
    try {
        return (int) $db->query("SELECT COUNT(*) FROM demandes_prix d INNER JOIN produits p ON p.id = d.produit_id
            WHERE d.statut = 'en_attente' AND d.sync_deleted_at IS NULL AND p.sync_deleted_at IS NULL
              AND NOT " . demandes_prix_sql_prix_pose())->fetchColumn();
    } catch (PDOException $e) {
        error_log('[demandes_prix_nb_en_attente] ' . $e->getMessage());
        return 0;
    }
}

/** Une demande, avec le nom de sa pièce. */
function demande_prix_par_id($demande_id)
{
    global $db;
    if (!demandes_prix_table_ok()) {
        return null;
    }
    $st = $db->prepare('SELECT d.id, d.produit_id, d.champ, d.statut, d.nb_demandes, p.nom, p.identifiant_interne
        FROM demandes_prix d INNER JOIN produits p ON p.id = d.produit_id
        WHERE d.id = :id AND d.sync_deleted_at IS NULL');
    $st->execute(['id' => (int) $demande_id]);
    $ligne = $st->fetch(PDO::FETCH_ASSOC);
    return $ligne ?: null;
}

/**
 * Lit un montant tapé par le responsable de stock : « 12500 », « 12 500 »,
 * « 12.500 » (point de milliers) ou « 12500,50 ».
 *
 * @return float|false|null null pour une case vide, false pour autre chose qu'un montant
 */
function demandes_prix_lire_montant($brut)
{
    $texte = trim(str_ireplace('fcfa', '', (string) $brut));
    $texte = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $texte);
    if ($texte === '') {
        return null;
    }
    if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $texte)) {
        return (float) str_replace(['.', ','], '', $texte);
    }
    if (preg_match('/^\d+([.,]\d{1,2})?$/', $texte)) {
        return (float) str_replace(',', '.', $texte);
    }
    return false;
}

/**
 * Les prix en attente, les plus demandés d'abord, avec les derniers prix
 * pratiqués pour la pièce (vente directe, devis) comme repères.
 *
 * @return array<int, array<string, mixed>>
 */
function demandes_prix_en_attente($limite = 100)
{
    global $db;
    if (!demandes_prix_table_ok()) {
        return [];
    }
    try {
        $lignes = $db->query("SELECT d.id, d.produit_id, d.champ, d.nb_demandes, d.date_demande, d.date_derniere_demande,
                p.nom, p.identifiant_interne, p.stock,
                a.prenom AS demandeur_prenom, a.nom AS demandeur_nom,
                x.prenom AS dernier_prenom, x.nom AS dernier_nom,
                (SELECT l.prix_unitaire FROM caisse_vente_lignes l INNER JOIN caisse_ventes v ON v.id = l.vente_id
                 WHERE l.produit_id = p.id AND v.statut <> 'annule' AND v.sync_deleted_at IS NULL AND l.sync_deleted_at IS NULL
                 ORDER BY v.date_vente DESC, l.id DESC LIMIT 1) AS dernier_prix_vendu,
                (SELECT dp.prix_unitaire FROM devis_produits dp INNER JOIN devis dv ON dv.id = dp.devis_id
                 WHERE dp.produit_id = p.id AND dv.sync_deleted_at IS NULL AND dp.sync_deleted_at IS NULL
                 ORDER BY dv.date_creation DESC, dp.id DESC LIMIT 1) AS dernier_prix_devis
            FROM demandes_prix d
            INNER JOIN produits p ON p.id = d.produit_id
            LEFT JOIN admin a ON a.id = d.admin_id
            LEFT JOIN admin x ON x.id = d.dernier_demandeur_id
            WHERE d.statut = 'en_attente' AND d.sync_deleted_at IS NULL AND p.sync_deleted_at IS NULL
              AND NOT " . demandes_prix_sql_prix_pose() . "
            ORDER BY d.nb_demandes DESC, d.date_demande ASC, d.id ASC
            LIMIT " . max(1, min(500, (int) $limite)))->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($lignes as $i => $ligne) {
            $lignes[$i]['libelle'] = demandes_prix_libelle_champ($ligne['champ']);
        }
        return $lignes;
    } catch (PDOException $e) {
        error_log('[demandes_prix_en_attente] ' . $e->getMessage());
        return [];
    }
}

/**
 * Les prix fixés ces derniers jours : qui, quand, à combien.
 *
 * @return array<int, array<string, mixed>>
 */
function demandes_prix_traitees_recentes($jours = 7, $limite = 30)
{
    global $db;
    if (!demandes_prix_table_ok()) {
        return [];
    }
    try {
        $lignes = $db->query("SELECT d.id, d.produit_id, d.champ, d.nb_demandes, d.prix_fixe, d.date_traitement, d.traite_par,
                p.nom, p.identifiant_interne,
                t.prenom AS traite_prenom, t.nom AS traite_nom,
                a.prenom AS demandeur_prenom, a.nom AS demandeur_nom
            FROM demandes_prix d
            INNER JOIN produits p ON p.id = d.produit_id
            LEFT JOIN admin t ON t.id = d.traite_par
            LEFT JOIN admin a ON a.id = d.admin_id
            WHERE d.statut = 'traitee' AND d.sync_deleted_at IS NULL
              AND d.date_traitement >= NOW() - INTERVAL " . max(1, (int) $jours) . " DAY
            ORDER BY d.date_traitement DESC, d.id DESC
            LIMIT " . max(1, min(200, (int) $limite)))->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($lignes as $i => $ligne) {
            $lignes[$i]['libelle'] = demandes_prix_libelle_champ($ligne['champ']);
        }
        return $lignes;
    } catch (PDOException $e) {
        error_log('[demandes_prix_traitees_recentes] ' . $e->getMessage());
        return [];
    }
}

/**
 * Les demandes d'un vendeur : en attente, et celles dont le prix a été fixé
 * ces derniers jours, pour qu'il rappelle son client.
 *
 * @return array<int, array<string, mixed>>
 */
function demandes_prix_du_vendeur($admin_id, $jours = 7, $limite = 20)
{
    global $db;
    if (!demandes_prix_table_ok() || (int) $admin_id <= 0) {
        return [];
    }
    try {
        $st = $db->prepare("SELECT d.id, d.produit_id, d.champ, d.statut, d.nb_demandes, d.date_demande, d.date_traitement, d.prix_fixe,
                p.nom, p.identifiant_interne
            FROM demandes_prix d
            INNER JOIN produits p ON p.id = d.produit_id
            WHERE d.sync_deleted_at IS NULL
              AND (d.admin_id = :a OR d.dernier_demandeur_id = :a2)
              AND (d.statut = 'en_attente' OR (d.statut = 'traitee' AND d.date_traitement >= NOW() - INTERVAL " . max(1, (int) $jours) . " DAY))
            ORDER BY (d.statut = 'en_attente') DESC, COALESCE(d.date_traitement, d.date_demande) DESC, d.id DESC
            LIMIT " . max(1, min(100, (int) $limite)));
        $st->execute(['a' => (int) $admin_id, 'a2' => (int) $admin_id]);
        $lignes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($lignes as $i => $ligne) {
            $lignes[$i]['libelle'] = demandes_prix_libelle_champ($ligne['champ']);
        }
        return $lignes;
    } catch (PDOException $e) {
        error_log('[demandes_prix_du_vendeur] ' . $e->getMessage());
        return [];
    }
}

/**
 * Le responsable de stock fixe le prix demandé : il va au catalogue, et chaque
 * demande en attente de cette pièce pour cette colonne se solde à son nom. Le
 * droit d'écrire le prix se vérifie avant, dans la page.
 *
 * @return array{ok:bool, error?:string, nom?:string, champ?:string, libelle?:string, prix?:float, nb?:int, produit_id?:int}
 */
function demande_prix_fixer($demande_id, $prix, $admin_id)
{
    global $db;
    if (!demandes_prix_table_ok()) {
        return ['ok' => false, 'error' => 'Les demandes de prix attendent la mise à jour de la base (migrations/run_demandes_prix.php).'];
    }
    $prix = round((float) $prix, 2);
    if ($prix <= 0 || $prix >= 100000000) {
        return ['ok' => false, 'error' => 'Le prix doit être un montant en FCFA plus grand que zéro.'];
    }
    $admin_id = (int) $admin_id;
    try {
        $db->beginTransaction();
        $st = $db->prepare("SELECT id, produit_id, champ, nb_demandes FROM demandes_prix
            WHERE id = :id AND statut = 'en_attente' AND sync_deleted_at IS NULL FOR UPDATE");
        $st->execute(['id' => (int) $demande_id]);
        $demande = $st->fetch(PDO::FETCH_ASSOC);
        if (!$demande) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Cette demande n’est plus en attente : son prix a déjà été fixé.'];
        }
        $champ = (string) $demande['champ'];
        $piece = in_array($champ, demandes_prix_champs_demandables(), true) ? demandes_prix_piece((int) $demande['produit_id'], true) : null;
        if (!$piece) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'La pièce de cette demande est introuvable.'];
        }
        $libelle = demandes_prix_libelle_champ($champ);
        $deja = demandes_prix_valeur_catalogue($piece, $champ);
        if ($deja > 0) {
            $db->rollBack();
            return ['ok' => false, 'error' => '« ' . $piece['nom'] . ' » a reçu son prix entre-temps (' . $libelle . ' : '
                . number_format($deja, 0, ',', ' ') . ' FCFA) : rien n’a été changé.'];
        }
        $colonnes = demandes_prix_colonnes_produits();
        $sets = ["`$champ` = :prix", 'date_modification = NOW()'];
        $params = ['prix' => $prix, 'id' => (int) $piece['id']];
        if (isset($colonnes['sync_updated_at'])) {
            $sets[] = 'sync_updated_at = NOW()';   // la table des pièces n'a plus de déclencheurs de synchro (08/09)
        }
        if (isset($colonnes['admin_dernier_modificateur_id']) && $admin_id > 0) {
            $sets[] = 'admin_dernier_modificateur_id = :a';
            $params['a'] = $admin_id;
        }
        $db->prepare('UPDATE produits SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        $db->prepare("UPDATE demandes_prix SET statut = 'traitee', traite_par = :a, date_traitement = NOW(), prix_fixe = :prix
            WHERE produit_id = :p AND champ = :c AND statut = 'en_attente' AND sync_deleted_at IS NULL")
            ->execute(['a' => $admin_id > 0 ? $admin_id : null, 'prix' => $prix, 'p' => (int) $piece['id'], 'c' => $champ]);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[demande_prix_fixer] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Le prix n’a pas pu être enregistré.'];
    }
    demandes_prix_catalogue_rafraichi();
    return ['ok' => true, 'nom' => (string) $piece['nom'], 'champ' => $champ, 'libelle' => $libelle, 'prix' => $prix,
        'nb' => (int) $demande['nb_demandes'], 'produit_id' => (int) $piece['id']];
}

/** La vente directe garde son catalogue 90 s en cache : un prix fixé doit s'y voir tout de suite. */
function demandes_prix_catalogue_rafraichi()
{
    require_once __DIR__ . '/model_caisse.php';
    caisse_catalog_live_cache_invalidate();
}

/**
 * Le prix d'une pièce au catalogue pour une ligne de devis ou de bon, dans la
 * colonne choisie pour le total. Une colonne inconnue, ou que le profil ne
 * voit pas, cède la place au prix de vente.
 *
 * @return array{produit_id:int, nom:string, champ:string, libelle:string, prix:float}|null
 */
function demandes_prix_prix_document($produit_id, $champ_calcul)
{
    require_once __DIR__ . '/model_produit_formulaire_champs.php';
    $champ_calcul = trim((string) $champ_calcul);
    $choisi = null;
    $vente = null;
    foreach (produit_formulaire_champs_prix_devis() as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        if ($slug === $champ_calcul) {
            $choisi = $ch;
        }
        if ($slug === 'prix') {
            $vente = $ch;
        }
    }
    $champ = $choisi ?? $vente ?? ['slug' => 'prix', 'label' => 'Prix de vente', 'source' => 'system', 'key' => 'prix'];
    $piece = demandes_prix_piece($produit_id);
    if (!$piece) {
        return null;
    }
    if (($champ['source'] ?? '') === 'custom') {
        $piece['pf_custom'] = produit_formulaire_valeurs_custom((int) $produit_id);
    }
    return [
        'produit_id' => (int) $piece['id'],
        'nom' => (string) $piece['nom'],
        'champ' => (string) $champ['slug'],
        'libelle' => (string) ($champ['label'] ?? $champ['slug']),
        'prix' => round((float) produit_formulaire_devis_prix_valeur_produit($piece, $champ), 2),
    ];
}

/**
 * Les lignes d'un devis ou d'un bon, prix repris au catalogue : le prix envoyé
 * par l'écran est ignoré. Les pièces sans ce prix sont mises à part.
 *
 * @return array{items: array<int, array<string, mixed>>, sans_prix: array<int, array<string, mixed>>}
 */
function demandes_prix_lignes_document(array $lignes_postees, $champ_calcul)
{
    $items = [];
    $sans_prix = [];
    foreach (array_values($lignes_postees) as $l) {
        if (!is_array($l)) {
            continue;
        }
        $produit_id = (int) ($l['produit_id'] ?? 0);
        $quantite = (int) ($l['quantite'] ?? 1);
        if ($produit_id <= 0 || $quantite <= 0) {
            continue;
        }
        $catalogue = demandes_prix_prix_document($produit_id, $champ_calcul);
        if ($catalogue === null) {
            continue;
        }
        if ($catalogue['prix'] <= 0) {
            $sans_prix[] = $catalogue;
            continue;
        }
        $items[] = [
            'produit_id' => $produit_id,
            'quantite' => $quantite,
            'prix_unitaire' => $catalogue['prix'],
            'nom_produit' => isset($l['nom_produit']) ? trim((string) $l['nom_produit']) : null,
        ];
    }
    return ['items' => $items, 'sans_prix' => $sans_prix];
}

/**
 * Les lignes d'une commande manuelle, prix et promotion repris au catalogue.
 *
 * @return array{items: array<int, array<string, mixed>>, sans_prix: array<int, array<string, mixed>>}
 */
function demandes_prix_lignes_commande(array $lignes_postees)
{
    $items = [];
    $sans_prix = [];
    foreach (array_values($lignes_postees) as $l) {
        if (!is_array($l)) {
            continue;
        }
        $produit_id = (int) ($l['produit_id'] ?? 0);
        $quantite = (int) ($l['quantite'] ?? 1);
        if ($produit_id <= 0 || $quantite <= 0) {
            continue;
        }
        $piece = demandes_prix_piece($produit_id);
        if (!$piece) {
            continue;
        }
        $prix = round((float) ($piece['prix'] ?? 0), 2);
        $promo = round((float) ($piece['prix_promotion'] ?? 0), 2);
        if ($prix <= 0 && $promo <= 0) {
            $sans_prix[] = ['produit_id' => $produit_id, 'nom' => (string) $piece['nom'], 'champ' => 'prix',
                'libelle' => demandes_prix_libelle_champ('prix'), 'prix' => 0.0];
            continue;
        }
        $items[] = [
            'produit_id' => $produit_id,
            'quantite' => $quantite,
            'prix_unitaire' => $prix > 0 ? $prix : $promo,
            'prix_promotion' => $promo > 0 ? $promo : null,
            'nom_produit' => isset($l['nom_produit']) ? trim((string) $l['nom_produit']) : null,
        ];
    }
    return ['items' => $items, 'sans_prix' => $sans_prix];
}

/**
 * Les lignes d'un bon de livraison modifié. Une pièce déjà sur le bon garde son
 * prix enregistré ; une pièce ajoutée prend son prix au catalogue ; une ligne
 * hors catalogue garde son prix si elle était déjà sur le bon, sinon elle est
 * refusée. Le prix envoyé par l'écran est toujours ignoré.
 *
 * @param array<int, array<string, mixed>> $lignes_enregistrees get_lignes_bl()
 * @return array{lignes: array<int, array<string, mixed>>, sans_prix: array<int, array<string, mixed>>, libres: array<int, string>}
 */
function demandes_prix_lignes_bl(array $lignes_postees, array $lignes_enregistrees)
{
    $prix_pieces = [];
    $prix_libres = [];
    foreach ($lignes_enregistrees as $e) {
        $pu = round((float) ($e['prix_unitaire_ht'] ?? 0), 2);
        if (!empty($e['produit_id'])) {
            if (!array_key_exists((int) $e['produit_id'], $prix_pieces)) {
                $prix_pieces[(int) $e['produit_id']] = $pu;
            }
            continue;
        }
        $cle = mb_strtolower(trim((string) ($e['designation'] ?? '')), 'UTF-8');
        if ($cle !== '' && !array_key_exists($cle, $prix_libres)) {
            $prix_libres[$cle] = $pu;
        }
    }
    $lignes = [];
    $sans_prix = [];
    $libres = [];
    foreach ($lignes_postees as $l) {
        if (!is_array($l)) {
            continue;
        }
        $designation = trim((string) ($l['designation'] ?? $l['nom_produit'] ?? ''));
        $brut = $l['quantite'] ?? 0;
        $quantite = is_numeric($brut) ? (float) $brut : (float) str_replace(',', '.', (string) $brut);
        $produit_id = !empty($l['produit_id']) ? (int) $l['produit_id'] : 0;
        $vide = ($designation === '' || $quantite <= 0);   // replace_bl_lignes() l'écarte
        $pu = 0.0;
        if ($produit_id > 0) {
            if (array_key_exists($produit_id, $prix_pieces)) {
                $pu = $prix_pieces[$produit_id];
            } else {
                $piece = demandes_prix_piece($produit_id);
                if (!$piece) {
                    continue;
                }
                $pu = demandes_prix_valeur_catalogue($piece, 'prix');
                if ($pu <= 0 && !$vide) {
                    $sans_prix[] = ['produit_id' => $produit_id, 'nom' => (string) $piece['nom'], 'champ' => 'prix',
                        'libelle' => demandes_prix_libelle_champ('prix'), 'prix' => 0.0];
                    continue;
                }
            }
        } elseif (!$vide) {
            $cle = mb_strtolower($designation, 'UTF-8');
            if (!array_key_exists($cle, $prix_libres)) {
                $libres[] = $designation;
                continue;
            }
            $pu = $prix_libres[$cle];
        }
        $lignes[] = ['produit_id' => $produit_id > 0 ? $produit_id : null, 'designation' => $designation,
            'quantite' => $quantite, 'prix_unitaire_ht' => $pu];
    }
    return ['lignes' => $lignes, 'sans_prix' => $sans_prix, 'libres' => $libres];
}

/**
 * Refuse un document qui contient des pièces sans prix : demande leur prix au
 * responsable de stock, au nom du vendeur, et dit quoi faire.
 *
 * @param array<int, array{produit_id:int, nom:string, champ:string, libelle:string}> $sans_prix
 */
function demandes_prix_refus_document(array $sans_prix, $admin_id)
{
    $demandees = [];
    $autres = [];
    foreach ($sans_prix as $s) {
        $cle = (int) $s['produit_id'] . '|' . $s['champ'];
        if (isset($demandees[$cle]) || isset($autres[$cle])) {
            continue;
        }
        $nom = '« ' . $s['nom'] . ' »' . ($s['champ'] !== 'prix' ? ' (' . $s['libelle'] . ')' : '');
        $res = in_array($s['champ'], demandes_prix_champs_demandables(), true)
            ? demande_prix_creer((int) $s['produit_id'], (int) $admin_id, (string) $s['champ'])
            : ['ok' => false];
        if (!empty($res['ok'])) {
            $demandees[$cle] = $nom;
        } else {
            $autres[$cle] = $nom;
        }
    }
    $phrases = [];
    $n = count($demandees);
    if ($n === 1) {
        $phrases[] = reset($demandees) . ' n’a pas de prix au catalogue : son prix est demandé au responsable de stock. Retirez la pièce, ou attendez qu’il le fixe.';
    } elseif ($n > 1) {
        $phrases[] = $n . ' pièces n’ont pas de prix au catalogue : ' . implode(', ', $demandees)
            . '. Leur prix est demandé au responsable de stock. Retirez ces pièces, ou attendez qu’il les fixe.';
    }
    if ($autres !== []) {
        $phrases[] = implode(', ', $autres) . (count($autres) === 1 ? ' n’a' : ' n’ont')
            . ' pas ce prix au catalogue : choisissez une autre colonne de prix pour le total, ou demandez-le au responsable de stock.';
    }
    return implode(' ', $phrases);
}

/** Le refus des lignes hors catalogue tapées dans un bon. */
function demandes_prix_message_lignes_libres(array $designations)
{
    $noms = array_map(static function ($d) {
        return '« ' . $d . ' »';
    }, array_values(array_unique($designations)));
    return (count($noms) === 1
        ? 'La ligne ' . $noms[0] . ' n’est pas une pièce du catalogue'
        : 'Les lignes ' . implode(', ', $noms) . ' ne sont pas des pièces du catalogue')
        . ' : un prix ne se tape pas. Ajoutez la pièce par la recherche.';
}
