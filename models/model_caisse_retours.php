<?php
/**
 * RETOURS CLIENTS EN CAISSE : REMBOURSEMENT EN ESPÈCES ET ÉCHANGE (11/09/2026).
 *
 * Mesuré : la caisse ne savait ni reprendre une pièce, ni rembourser, ni
 * échanger ; aucun remboursement n'avait jamais été saisi, même à la main.
 *
 * Décisions de la direction (11/09/2026) :
 * - le commercial général PRÉPARE le retour (constat, motif, solution) et le
 *   caissier le VALIDE : c'est lui qui rend ou reçoit l'argent, et c'est à la
 *   validation que le stock bouge, comme à l'encaissement d'un ticket ;
 * - le remboursement se fait SEULEMENT en espèces ; la différence d'un échange
 *   plus cher se reçoit aussi en espèces, pour une seule règle au comptoir ;
 * - pas de seuil de validation, pas de retour sans ticket ;
 * - une pièce montée ou abîmée par le client ne se reprend pas ;
 * - le délai de retour se règle dans les paramètres de l'informaticien
 *   (caisse_parametres, clé retour_delai_jours) ; non défini = pas de limite ;
 * - l'échange de la même pièce défectueuse passe aussi par le caissier.
 *
 * Règles :
 * - on rend le prix RÉELLEMENT PAYÉ : la part de la ligne dans le total du
 *   ticket, remise globale et TVA comprises, au prorata des pièces rendues ;
 * - une quantité ne se rend qu'une fois : les retours en attente et validés
 *   comptent, les annulés non ;
 * - mauvaise pièce intacte : elle rentre en stock vendable, et une pièce en
 *   rupture repasse en vente (règle de l'écran Entrée) ;
 * - pièce défectueuse : elle entre puis sort aussitôt comme « défectueuse » :
 *   le stock vendable ne bouge pas, le journal garde la trace pour réclamer au
 *   fournisseur ;
 * - échange : la pièce remise sort du stock à la validation, stock verrouillé ;
 *   la même pièce défectueuse s'échange sans argent ; une autre pièce vaut son
 *   prix du catalogue, TVA ajoutée si le ticket d'origine l'incluait ;
 * - le retour compte dans la caisse du JOUR de sa validation : la clôture
 *   retire les espèces rendues et ajoute les espèces reçues ; une clôture
 *   passée n'est jamais rouverte ;
 * - numéro : RTC, date du retour, identifiant sur 6 chiffres, comme les tickets.
 *
 * Tables : migrations/run_caisse_retours.php.
 */

require_once __DIR__ . '/model_caisse.php';
require_once __DIR__ . '/model_produits.php';
require_once __DIR__ . '/model_mouvements_stock.php';

/**
 * Les trois tables des retours existent-elles ?
 */
function caisse_retours_tables_ok()
{
    global $db;
    static $ok = false;
    if ($ok || !$db) {
        return $ok;
    }
    try {
        $n = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('caisse_retours', 'caisse_retours_lignes', 'caisse_parametres')")->fetchColumn();
        $ok = ($n === 3);
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * La valeur d'un réglage de la caisse, ou $defaut s'il n'est pas posé.
 */
function caisse_parametre_lire($cle, $defaut = null)
{
    global $db;
    if (!caisse_retours_tables_ok()) {
        return $defaut;
    }
    try {
        $st = $db->prepare('SELECT valeur FROM caisse_parametres WHERE cle = :cle AND sync_deleted_at IS NULL LIMIT 1');
        $st->execute(['cle' => (string) $cle]);
        $valeur = $st->fetchColumn();
        return ($valeur === false || $valeur === null) ? $defaut : (string) $valeur;
    } catch (PDOException $e) {
        error_log('[caisse_parametre_lire] ' . $e->getMessage());
        return $defaut;
    }
}

/**
 * Écrit un réglage de la caisse (null efface la valeur).
 */
function caisse_parametre_ecrire($cle, $valeur, $admin_id)
{
    global $db;
    if (!caisse_retours_tables_ok()) {
        return false;
    }
    $admin = (int) $admin_id > 0 ? (int) $admin_id : null;
    try {
        $existe = $db->prepare('SELECT id FROM caisse_parametres WHERE cle = :cle LIMIT 1');
        $existe->execute(['cle' => (string) $cle]);
        $id = $existe->fetchColumn();
        if ($id !== false) {
            $st = $db->prepare('UPDATE caisse_parametres SET valeur = :valeur, admin_id = :admin, date_modification = NOW() WHERE id = :id');
            $st->execute(['valeur' => $valeur, 'admin' => $admin, 'id' => (int) $id]);
        } else {
            $st = $db->prepare('INSERT INTO caisse_parametres (cle, valeur, admin_id, date_modification) VALUES (:cle, :valeur, :admin, NOW())');
            $st->execute(['cle' => (string) $cle, 'valeur' => $valeur, 'admin' => $admin]);
        }
        return true;
    } catch (PDOException $e) {
        error_log('[caisse_parametre_ecrire] ' . $e->getMessage());
        return false;
    }
}

/**
 * Le délai de retour en jours, ou null quand l'informaticien ne l'a pas fixé.
 */
function caisse_retour_delai_jours()
{
    $valeur = trim((string) caisse_parametre_lire('retour_delai_jours', ''));
    if ($valeur === '' || !ctype_digit($valeur) || (int) $valeur <= 0) {
        return null;
    }
    return (int) $valeur;
}

/** @return array<string, string> */
function caisse_retour_motifs()
{
    return ['mauvaise_piece' => 'Mauvaise pièce', 'defectueuse' => 'Pièce défectueuse'];
}

/** @return array<string, string> */
function caisse_retour_solutions()
{
    return ['remboursement' => 'Remboursement en espèces', 'echange' => 'Échange'];
}

function caisse_retour_statut_libelle($statut)
{
    $libelles = ['en_attente' => 'En attente de caisse', 'valide' => 'Validé', 'annule' => 'Annulé'];
    return $libelles[(string) $statut] ?? (string) $statut;
}

/**
 * Ce qu'un ticket permet de rendre : chaque ligne avec sa quantité vendue, ce
 * qui est déjà rendu ou réservé par un retour en attente, ce qui reste, et sa
 * valeur au prix payé. Dit aussi si le ticket peut recevoir un retour, et pourquoi.
 * Avec $verrou, la ligne du ticket est lue FOR UPDATE (dans une transaction).
 *
 * @throws PDOException
 * @return array<string, mixed>
 */
function caisse_retour_etat_vente($vente_id, $verrou = false, $sauf_retour_id = 0)
{
    global $db;
    $vente_id = (int) $vente_id;
    $st = $db->prepare('SELECT * FROM caisse_ventes WHERE id = :id' . ($verrou ? ' FOR UPDATE' : ''));
    $st->execute(['id' => $vente_id]);
    $vente = $st->fetch(PDO::FETCH_ASSOC);
    if (!$vente) {
        return ['ok' => false, 'error' => 'Ticket introuvable.'];
    }
    $st = $db->prepare('SELECT prenom, nom FROM admin WHERE id = :id');
    $st->execute(['id' => (int) $vente['admin_id']]);
    $vendeur = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $vente['vendeur_prenom'] = $vendeur['prenom'] ?? '';
    $vente['vendeur_nom'] = $vendeur['nom'] ?? '';

    $st = $db->prepare('SELECT * FROM caisse_vente_lignes WHERE vente_id = :id AND sync_deleted_at IS NULL ORDER BY id');
    $st->execute(['id' => $vente_id]);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $deja = [];
    $st = $db->prepare("SELECT l.vente_ligne_id, COALESCE(SUM(l.quantite), 0) AS quantite, COALESCE(SUM(l.montant), 0) AS montant
        FROM caisse_retours_lignes l
        INNER JOIN caisse_retours r ON r.id = l.retour_id
        WHERE r.vente_id = :vente AND r.id <> :sauf AND r.statut IN ('en_attente', 'valide')
          AND r.sync_deleted_at IS NULL AND l.sync_deleted_at IS NULL AND l.sens = 'rendue'
        GROUP BY l.vente_ligne_id");
    $st->execute(['vente' => $vente_id, 'sauf' => (int) $sauf_retour_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deja[(int) $d['vente_ligne_id']] = $d;
    }

    $somme = 0.0;
    foreach ($lignes as $l) {
        $somme += (float) $l['total_ligne'];
    }
    $total = (float) $vente['montant_total'];
    $etat_lignes = [];
    $disponible_total = 0;
    foreach ($lignes as $l) {
        $id = (int) $l['id'];
        $quantite = (int) $l['quantite'];
        $valeur = $somme > 0 ? round($total * (float) $l['total_ligne'] / $somme, 2) : 0.0;
        $deja_quantite = (int) ($deja[$id]['quantite'] ?? 0);
        $deja_montant = (float) ($deja[$id]['montant'] ?? 0);
        $disponible = (int) $l['produit_id'] > 0 ? max(0, $quantite - $deja_quantite) : 0;
        $disponible_total += $disponible;
        $etat_lignes[$id] = [
            'id' => $id,
            'produit_id' => (int) $l['produit_id'],
            'designation' => (string) $l['designation'],
            'quantite' => $quantite,
            'deja_rendue' => $deja_quantite,
            'disponible' => $disponible,
            'valeur_ligne' => $valeur,
            'valeur_unitaire' => $quantite > 0 ? round($valeur / $quantite, 2) : 0.0,
            'valeur_restante' => round(max(0.0, $valeur - $deja_montant), 2),
        ];
    }

    $statut = caisse_vente_statut($vente);
    $delai = caisse_retour_delai_jours();
    $moment = (string) ($vente['date_encaissement'] ?: $vente['date_vente']);
    $raison = '';
    if ($statut === 'en_attente') {
        $raison = 'Ce ticket n’est pas encore payé : il s’annule, il ne se rembourse pas.';
    } elseif ($statut === 'annule') {
        $raison = 'Ce ticket est annulé : il ne se rembourse pas.';
    } elseif ($delai !== null && $moment !== '' && time() > strtotime($moment) + $delai * 86400) {
        $raison = 'Le délai de retour de ' . $delai . ' jour(s) est dépassé : ce ticket a été encaissé le ' . date('d/m/Y', strtotime($moment)) . '.';
    } elseif ($disponible_total === 0) {
        $raison = 'Toutes les pièces de ce ticket ont déjà été rendues, ou sont dans un retour en attente.';
    }

    return [
        'ok' => true,
        'vente' => $vente,
        'lignes' => $etat_lignes,
        'retournable' => $raison === '',
        'raison' => $raison,
        'delai' => $delai,
        'moment' => $moment,
    ];
}

/**
 * Le message d'une erreur montrable au vendeur : une règle métier se dit,
 * une erreur de base de données reste dans le journal.
 */
function caisse_retour_message_erreur(Throwable $e, $defaut)
{
    if ($e instanceof PDOException || !($e instanceof RuntimeException)) {
        return $defaut;
    }
    return $e->getMessage();
}

/**
 * PRÉPARER UN RETOUR (commercial général). Rien ne bouge encore : ni stock, ni argent.
 *
 * @param array<int, int> $quantites [ligne du ticket => quantité rendue]
 * @param array<int, int> $remises   [pièce => quantité] pour un échange contre une autre pièce
 * @return array{ok:bool, error?:string, retour_id?:int, numero_retour?:string, especes_a_rendre?:float, especes_a_recevoir?:float}
 */
function caisse_retour_preparer($vente_id, array $quantites, $motif, $solution, $explication, $piece_intacte, $meme_piece, array $remises, $admin_id)
{
    global $db;
    if (!caisse_retours_tables_ok()) {
        return ['ok' => false, 'error' => 'Les retours attendent la mise à jour de la base (migrations/run_caisse_retours.php).'];
    }
    if ((int) $admin_id <= 0) {
        return ['ok' => false, 'error' => 'Vendeur inconnu : reconnectez-vous.'];
    }
    $motif = (string) $motif;
    $solution = (string) $solution;
    if (!isset(caisse_retour_motifs()[$motif])) {
        return ['ok' => false, 'error' => 'Choisissez le motif du retour : mauvaise pièce ou pièce défectueuse.'];
    }
    if (!isset(caisse_retour_solutions()[$solution])) {
        return ['ok' => false, 'error' => 'Choisissez ce que reçoit le client : remboursement ou échange.'];
    }
    $explication = trim((string) $explication);
    if (mb_strlen($explication) < 3) {
        return ['ok' => false, 'error' => 'Expliquez le retour en quelques mots.'];
    }
    $explication = mb_substr($explication, 0, 255);
    if ($motif === 'mauvaise_piece' && !$piece_intacte) {
        return ['ok' => false, 'error' => 'Une pièce montée ou abîmée par le client ne se reprend pas : confirmez que la pièce est intacte.'];
    }
    if ($solution === 'echange' && $meme_piece && $motif !== 'defectueuse') {
        return ['ok' => false, 'error' => 'L’échange contre la même pièce est réservé à une pièce défectueuse.'];
    }
    if ($solution === 'echange' && !$meme_piece && $remises === []) {
        return ['ok' => false, 'error' => 'Ajoutez la ou les pièces remises au client en échange.'];
    }

    try {
        $db->beginTransaction();
        $etat = caisse_retour_etat_vente((int) $vente_id, true);
        if (empty($etat['ok'])) {
            $db->rollBack();
            return $etat;
        }
        if (!$etat['retournable']) {
            $db->rollBack();
            return ['ok' => false, 'error' => $etat['raison']];
        }

        $rendues = [];
        foreach ($quantites as $ligne_id => $quantite) {
            $quantite = (int) $quantite;
            if ($quantite === 0) {
                continue;
            }
            $ligne = $etat['lignes'][(int) $ligne_id] ?? null;
            if ($ligne === null || $quantite < 0) {
                throw new RuntimeException('Une pièce rendue n’appartient pas à ce ticket.');
            }
            if ($quantite > $ligne['disponible']) {
                throw new RuntimeException('« ' . $ligne['designation'] . ' » : ' . $ligne['disponible'] . ' pièce(s) au plus peuvent encore être rendues.');
            }
            $montant = ($quantite === $ligne['disponible'])
                ? $ligne['valeur_restante']
                : round($ligne['valeur_ligne'] * $quantite / max(1, $ligne['quantite']), 2);
            $rendues[] = [
                'vente_ligne_id' => $ligne['id'],
                'produit_id' => $ligne['produit_id'],
                'designation' => $ligne['designation'],
                'quantite' => $quantite,
                'prix_unitaire' => round($montant / $quantite, 2),
                'montant' => $montant,
            ];
        }
        if ($rendues === []) {
            throw new RuntimeException('Indiquez au moins une pièce rendue.');
        }
        $montant_rendu = round(array_sum(array_column($rendues, 'montant')), 2);

        $remis = [];
        if ($solution === 'echange') {
            if ($meme_piece) {
                foreach ($rendues as $rendue) {
                    $remis[] = ['vente_ligne_id' => null] + $rendue;
                }
            } else {
                $majoration = !empty($etat['vente']['tva_incluse']) ? 1 + (float) CAISSE_TVA_TAUX_POURCENT / 100 : 1.0;
                foreach ($remises as $produit_id => $quantite) {
                    $quantite = (int) $quantite;
                    if ($quantite <= 0) {
                        continue;
                    }
                    $piece = get_produit_by_id_sans_filtre_acces((int) $produit_id);
                    if (!$piece || ($piece['statut'] ?? '') !== 'actif') {
                        throw new RuntimeException('Une pièce remise en échange n’est pas disponible à la vente.');
                    }
                    $prix = round((float) caisse_prix_unitaire_produit($piece), 2);
                    if ($prix <= 0) {
                        throw new RuntimeException(caisse_message_sans_prix($piece['nom']));
                    }
                    if ((int) $piece['stock'] < $quantite) {
                        throw new RuntimeException('Stock insuffisant pour « ' . $piece['nom'] . ' » : ' . (int) $piece['stock'] . ' en stock.');
                    }
                    $prix = round($prix * $majoration, 2);
                    $remis[] = [
                        'vente_ligne_id' => null,
                        'produit_id' => (int) $piece['id'],
                        'designation' => (string) $piece['nom'],
                        'quantite' => $quantite,
                        'prix_unitaire' => $prix,
                        'montant' => round($prix * $quantite, 2),
                    ];
                }
                if ($remis === []) {
                    throw new RuntimeException('Ajoutez la ou les pièces remises au client en échange.');
                }
            }
        }
        $montant_remis = round(array_sum(array_column($remis, 'montant')), 2);
        $a_rendre = max(0.0, round($montant_rendu - $montant_remis, 2));
        $a_recevoir = max(0.0, round($montant_remis - $montant_rendu, 2));

        $ins = $db->prepare("INSERT INTO caisse_retours
            (numero_retour, vente_id, statut, motif, solution, explication, montant_rendu, montant_remis,
             especes_a_rendre, especes_a_recevoir, admin_id, date_creation)
            VALUES (:numero, :vente, 'en_attente', :motif, :solution, :explication, :rendu, :remis,
                    :a_rendre, :a_recevoir, :admin, NOW())");
        $ins->execute([
            'numero' => 'TMP-' . strtoupper(bin2hex(random_bytes(8))),
            'vente' => (int) $etat['vente']['id'],
            'motif' => $motif,
            'solution' => $solution,
            'explication' => $explication,
            'rendu' => $montant_rendu,
            'remis' => $montant_remis,
            'a_rendre' => $a_rendre,
            'a_recevoir' => $a_recevoir,
            'admin' => (int) $admin_id,
        ]);
        $retour_id = (int) $db->lastInsertId();
        $ligne_ins = $db->prepare('INSERT INTO caisse_retours_lignes
            (retour_id, sens, vente_ligne_id, produit_id, designation, quantite, prix_unitaire, montant)
            VALUES (:retour, :sens, :vente_ligne, :produit, :designation, :quantite, :prix, :montant)');
        foreach (['rendue' => $rendues, 'remise' => $remis] as $sens => $lignes) {
            foreach ($lignes as $l) {
                $ligne_ins->execute([
                    'retour' => $retour_id,
                    'sens' => $sens,
                    'vente_ligne' => $l['vente_ligne_id'],
                    'produit' => $l['produit_id'],
                    'designation' => $l['designation'],
                    'quantite' => $l['quantite'],
                    'prix' => $l['prix_unitaire'],
                    'montant' => $l['montant'],
                ]);
            }
        }
        $db->prepare("UPDATE caisse_retours SET numero_retour = CONCAT('RTC', DATE_FORMAT(date_creation, '%Y%m%d'), LPAD(id, 6, '0')) WHERE id = :id")
            ->execute(['id' => $retour_id]);
        $numero = (string) $db->query('SELECT numero_retour FROM caisse_retours WHERE id = ' . $retour_id)->fetchColumn();
        $db->commit();

        return [
            'ok' => true,
            'retour_id' => $retour_id,
            'numero_retour' => $numero,
            'especes_a_rendre' => $a_rendre,
            'especes_a_recevoir' => $a_recevoir,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_retour_preparer] ' . $e->getMessage());
        return ['ok' => false, 'error' => caisse_retour_message_erreur($e, 'Le retour n’a pas pu être préparé.')];
    }
}

/**
 * Fait bouger le stock d'une pièce dans la transaction en cours, pièce
 * verrouillée, et l'écrit au journal. $delta > 0 : entrée ; $delta < 0 :
 * sortie, refusée si le stock ne suffit pas. Avec $toucher_statut, la règle de
 * l'écran Entrée s'applique : une pièce en rupture repasse en vente quand du
 * stock rentre, et une pièce vendue jusqu'à zéro passe en rupture.
 *
 * @throws RuntimeException|PDOException
 * @return array{avant:int, apres:int, nom:string}
 */
function caisse_retour_mouvement_stock($produit_id, $delta, $reference_type, array $retour, $notes, $caissier_id, $toucher_statut)
{
    global $db;
    $lire = $db->prepare('SELECT id, nom, stock, statut FROM produits WHERE id = :id FOR UPDATE');
    $lire->execute(['id' => (int) $produit_id]);
    $piece = $lire->fetch(PDO::FETCH_ASSOC);
    if (!$piece) {
        throw new RuntimeException('Pièce introuvable (n°' . (int) $produit_id . ').');
    }
    $avant = (int) $piece['stock'];
    $quantite = abs((int) $delta);
    if ($delta > 0) {
        $sql = 'UPDATE produits SET '
            . ($toucher_statut ? "statut = CASE WHEN statut = 'rupture_stock' THEN 'actif' ELSE statut END, " : '')
            . 'stock = stock + :q, date_modification = NOW() WHERE id = :id';
        $db->prepare($sql)->execute(['q' => $quantite, 'id' => (int) $produit_id]);
        $apres = $avant + $quantite;
    } else {
        if ($avant < $quantite) {
            throw new RuntimeException('Stock insuffisant pour « ' . $piece['nom'] . ' » : ' . $avant . ' en stock, ' . $quantite . ' demandée(s).');
        }
        $sql = 'UPDATE produits SET '
            . ($toucher_statut ? "statut = CASE WHEN stock - :q3 <= 0 AND statut = 'actif' THEN 'rupture_stock' ELSE statut END, " : '')
            . 'stock = stock - :q, date_modification = NOW() WHERE id = :id AND stock >= :q2';
        $parametres = ['q' => $quantite, 'q2' => $quantite, 'id' => (int) $produit_id];
        if ($toucher_statut) {
            $parametres['q3'] = $quantite;
        }
        $sortie = $db->prepare($sql);
        $sortie->execute($parametres);
        if ($sortie->rowCount() !== 1) {
            throw new RuntimeException('Stock insuffisant pour « ' . $piece['nom'] . ' ».');
        }
        $apres = $avant - $quantite;
    }
    $mouvement = create_stock_mouvement([
        'type' => $delta > 0 ? 'entree' : 'sortie',
        'produit_id' => (int) $produit_id,
        'quantite' => $quantite,
        'quantite_avant' => $avant,
        'quantite_apres' => $apres,
        'reference_type' => $reference_type,
        'reference_id' => (int) $retour['id'],
        'reference_numero' => (string) $retour['numero_retour'],
        'notes' => $notes,
        'admin_id' => (int) $caissier_id,
    ]);
    if ($mouvement === false) {
        throw new RuntimeException('Le journal de stock n’a pas pu être écrit : retour non validé.');
    }
    return ['avant' => $avant, 'apres' => $apres, 'nom' => (string) $piece['nom']];
}

/**
 * VALIDER UN RETOUR (caissier) : le stock bouge et l'argent change de main, tout ou rien.
 *
 * @return array{ok:bool, error?:string, numero_retour?:string, especes_a_rendre?:float, especes_a_recevoir?:float}
 */
function caisse_retour_valider($retour_id, $caissier_id)
{
    global $db;
    if (!caisse_retours_tables_ok()) {
        return ['ok' => false, 'error' => 'Les retours attendent la mise à jour de la base (migrations/run_caisse_retours.php).'];
    }
    if ((int) $caissier_id <= 0) {
        return ['ok' => false, 'error' => 'Caissier inconnu : reconnectez-vous.'];
    }
    $alertes = [];
    try {
        $db->beginTransaction();
        $st = $db->prepare('SELECT * FROM caisse_retours WHERE id = :id AND sync_deleted_at IS NULL FOR UPDATE');
        $st->execute(['id' => (int) $retour_id]);
        $retour = $st->fetch(PDO::FETCH_ASSOC);
        if (!$retour) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Retour introuvable.'];
        }
        if ($retour['statut'] !== 'en_attente') {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Ce retour n’est plus en attente : il a déjà été validé ou annulé.'];
        }
        $etat = caisse_retour_etat_vente((int) $retour['vente_id'], true, (int) $retour['id']);
        if (empty($etat['ok'])) {
            $db->rollBack();
            return $etat;
        }
        if (caisse_vente_statut($etat['vente']) !== 'paye') {
            throw new RuntimeException('Le ticket d’origine n’est plus payé : ce retour ne peut pas être validé.');
        }
        $st = $db->prepare('SELECT * FROM caisse_retours_lignes WHERE retour_id = :id AND sync_deleted_at IS NULL ORDER BY sens, id');
        $st->execute(['id' => (int) $retour['id']]);
        $lignes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $ticket = (string) $etat['vente']['numero_ticket'];

        foreach ($lignes as $l) {
            if ($l['sens'] !== 'rendue') {
                continue;
            }
            $ligne_ticket = $etat['lignes'][(int) $l['vente_ligne_id']] ?? null;
            if ($ligne_ticket === null || (int) $l['quantite'] > $ligne_ticket['disponible']) {
                throw new RuntimeException('Les quantités de ce retour dépassent ce qui reste à rendre sur le ticket ' . $ticket . '.');
            }
            $note = 'Retour client ' . $retour['numero_retour'] . ' du ticket ' . $ticket;
            if ($retour['motif'] === 'defectueuse') {
                caisse_retour_mouvement_stock((int) $l['produit_id'], (int) $l['quantite'], 'retour_caisse', $retour,
                    $note . ' : pièce défectueuse', $caissier_id, false);
                caisse_retour_mouvement_stock((int) $l['produit_id'], -(int) $l['quantite'], 'defectueux', $retour,
                    'Défaut constaté au retour client ' . $retour['numero_retour'] . ' : ' . $retour['explication'], $caissier_id, false);
            } else {
                caisse_retour_mouvement_stock((int) $l['produit_id'], (int) $l['quantite'], 'retour_caisse', $retour,
                    $note . ' : ' . $retour['explication'], $caissier_id, true);
            }
        }
        foreach ($lignes as $l) {
            if ($l['sens'] !== 'remise') {
                continue;
            }
            $sortie = caisse_retour_mouvement_stock((int) $l['produit_id'], -(int) $l['quantite'], 'echange_caisse', $retour,
                'Échange ' . $retour['numero_retour'] . ' du ticket ' . $ticket, $caissier_id, true);
            $alertes[] = [(int) $l['produit_id'], $sortie['avant'], $sortie['apres']];
        }

        $up = $db->prepare("UPDATE caisse_retours SET statut = 'valide', caissier_id = :caissier, date_validation = NOW()
            WHERE id = :id AND statut = 'en_attente'");
        $up->execute(['caissier' => (int) $caissier_id, 'id' => (int) $retour['id']]);
        if ($up->rowCount() !== 1) {
            throw new RuntimeException('Ce retour vient d’être traité à un autre poste.');
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_retour_valider] ' . $e->getMessage());
        return ['ok' => false, 'error' => caisse_retour_message_erreur($e, 'Le retour n’a pas pu être validé.')];
    }

    if ($alertes) {
        try {
            require_once __DIR__ . '/../includes/stock_alertes_notifications.php';
            if (function_exists('stock_alertes_notifier_baisse_stock')) {
                foreach ($alertes as $alerte) {
                    stock_alertes_notifier_baisse_stock($alerte[0], $alerte[1], $alerte[2]);
                }
            }
        } catch (Throwable $e) {
            error_log('[caisse_retour_valider alertes] ' . $e->getMessage());
        }
    }

    return [
        'ok' => true,
        'numero_retour' => (string) $retour['numero_retour'],
        'especes_a_rendre' => (float) $retour['especes_a_rendre'],
        'especes_a_recevoir' => (float) $retour['especes_a_recevoir'],
    ];
}

/**
 * ANNULER UN RETOUR EN ATTENTE, avec un motif : le vendeur qui l'a préparé, ou le caissier.
 *
 * @return array{ok:bool, error?:string, numero_retour?:string}
 */
function caisse_retour_annuler($retour_id, $admin_id, $motif, $est_caissier)
{
    global $db;
    if (!caisse_retours_tables_ok()) {
        return ['ok' => false, 'error' => 'Les retours attendent la mise à jour de la base (migrations/run_caisse_retours.php).'];
    }
    $motif = trim((string) $motif);
    if ((int) $admin_id <= 0 || mb_strlen($motif) < 3) {
        return ['ok' => false, 'error' => 'Indiquez le motif de l’annulation.'];
    }
    try {
        $db->beginTransaction();
        $st = $db->prepare('SELECT id, admin_id, statut, numero_retour FROM caisse_retours WHERE id = :id AND sync_deleted_at IS NULL FOR UPDATE');
        $st->execute(['id' => (int) $retour_id]);
        $retour = $st->fetch(PDO::FETCH_ASSOC);
        if (!$retour) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Retour introuvable.'];
        }
        if ($retour['statut'] !== 'en_attente') {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Seul un retour en attente s’annule : celui-ci est ' . mb_strtolower(caisse_retour_statut_libelle($retour['statut'])) . '.'];
        }
        if (!$est_caissier && (int) $retour['admin_id'] !== (int) $admin_id) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Seul le vendeur qui a préparé ce retour, ou le caissier, peut l’annuler.'];
        }
        $up = $db->prepare("UPDATE caisse_retours SET statut = 'annule', annule_par = :admin, date_annulation = NOW(), motif_annulation = :motif
            WHERE id = :id AND statut = 'en_attente'");
        $up->execute(['admin' => (int) $admin_id, 'motif' => mb_substr($motif, 0, 255), 'id' => (int) $retour_id]);
        $db->commit();
        return ['ok' => true, 'numero_retour' => (string) $retour['numero_retour']];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_retour_annuler] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Le retour n’a pas pu être annulé.'];
    }
}

/**
 * Les retours, les plus récents d'abord, filtrés par statut, vendeur ou ticket.
 *
 * @return array<int, array<string, mixed>>
 */
function caisse_retours_liste(array $filtre = [])
{
    global $db;
    if (!caisse_retours_tables_ok()) {
        return [];
    }
    $where = ['r.sync_deleted_at IS NULL'];
    $bind = [];
    if (!empty($filtre['statut'])) {
        $where[] = 'r.statut = :statut';
        $bind['statut'] = (string) $filtre['statut'];
    }
    if (!empty($filtre['admin_id'])) {
        $where[] = 'r.admin_id = :admin';
        $bind['admin'] = (int) $filtre['admin_id'];
    }
    if (!empty($filtre['vente_id'])) {
        $where[] = 'r.vente_id = :vente';
        $bind['vente'] = (int) $filtre['vente_id'];
    }
    $limite = max(1, min(500, (int) ($filtre['limite'] ?? 50)));
    try {
        $st = $db->prepare('SELECT r.*, v.numero_ticket, a.prenom AS vendeur_prenom, a.nom AS vendeur_nom,
                c.prenom AS caissier_prenom, c.nom AS caissier_nom
            FROM caisse_retours r
            LEFT JOIN caisse_ventes v ON v.id = r.vente_id
            LEFT JOIN admin a ON a.id = r.admin_id
            LEFT JOIN admin c ON c.id = r.caissier_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY r.date_creation DESC, r.id DESC
            LIMIT ' . $limite);
        $st->execute($bind);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_retours_liste] ' . $e->getMessage());
        return [];
    }
}

/**
 * Un retour avec ses lignes et les noms de ceux qui l'ont préparé, validé ou annulé.
 *
 * @return array<string, mixed>|null
 */
function caisse_retour_par_id($id)
{
    global $db;
    if (!caisse_retours_tables_ok() || (int) $id <= 0) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT r.*, v.numero_ticket,
                a.prenom AS vendeur_prenom, a.nom AS vendeur_nom,
                c.prenom AS caissier_prenom, c.nom AS caissier_nom,
                x.prenom AS annule_prenom, x.nom AS annule_nom
            FROM caisse_retours r
            LEFT JOIN caisse_ventes v ON v.id = r.vente_id
            LEFT JOIN admin a ON a.id = r.admin_id
            LEFT JOIN admin c ON c.id = r.caissier_id
            LEFT JOIN admin x ON x.id = r.annule_par
            WHERE r.id = :id AND r.sync_deleted_at IS NULL');
        $st->execute(['id' => (int) $id]);
        $retour = $st->fetch(PDO::FETCH_ASSOC);
        if (!$retour) {
            return null;
        }
        $st = $db->prepare('SELECT * FROM caisse_retours_lignes WHERE retour_id = :id AND sync_deleted_at IS NULL ORDER BY sens, id');
        $st->execute(['id' => (int) $id]);
        $retour['lignes'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $retour;
    } catch (PDOException $e) {
        error_log('[caisse_retour_par_id] ' . $e->getMessage());
        return null;
    }
}
