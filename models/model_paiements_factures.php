<?php
/**
 * ENREGISTRER LE PAIEMENT D'UNE FACTURE, PAS SEULEMENT LA COCHER (10/09/2026).
 *
 * Mesuré : une facture de devis se marquait payée d'un clic, sans montant, sans
 * moyen ni auteur, et le bouton s'offrait au vendeur ; le bon de livraison payé
 * seul et la facture mensuelle se cochaient de même.
 *
 * Chaque paiement reçu est une ligne : montant, moyen, date de réception,
 * référence, note, auteur. Une facture se paie en plusieurs fois ; elle devient
 * « payée » quand la somme des paiements atteint le total imprimé sur la
 * facture, et les drapeaux existants (factures_devis.payee,
 * bons_livraison.facture_bl_payee, factures_mensuelles.statut) sont posés dans
 * la même transaction : tous les écrans qui les lisent restent justes. Un
 * paiement erroné ne s'efface pas : il s'annule avec un motif, et la facture
 * redevient impayée si ce qui reste n'est plus couvert. Un numéro de référence
 * FPL déjà attribué n'est jamais repris.
 *
 * Les factures payées avant ce registre n'ont pas de détail : elles restent
 * payées, sans ligne de paiement, et l'écran le dit.
 *
 * Qui enregistre : admin_can_enregistrer_paiement_facture(), réglé sur la
 * comptabilité en attendant la décision de la direction.
 *
 * Table : migrations/run_paiements_factures.php.
 */

require_once __DIR__ . '/model_factures_devis.php';
require_once __DIR__ . '/model_bl.php';
require_once __DIR__ . '/model_factures_mensuelles.php';
require_once __DIR__ . '/../includes/fiscal_tva.php';

/**
 * Les trois sortes de facture qui se paient, et la colonne qui les vise.
 *
 * @return array<string, array{colonne:string, libelle:string}>
 */
function paiements_factures_types()
{
    return [
        'facture_devis' => ['colonne' => 'facture_devis_id', 'libelle' => 'facture de devis'],
        'bl' => ['colonne' => 'bl_id', 'libelle' => 'facture de bon de livraison'],
        'facture_mensuelle' => ['colonne' => 'facture_mensuelle_id', 'libelle' => 'facture mensuelle'],
    ];
}

/**
 * Les moyens de paiement d'une facture.
 *
 * @return array<string, string>
 */
function paiements_factures_modes()
{
    return [
        'especes' => 'Espèces',
        'virement' => 'Virement',
        'cheque' => 'Chèque',
        'orange_money' => 'Orange Money',
        'wave' => 'Wave',
        'carte' => 'Carte bancaire',
        'autre' => 'Autre',
    ];
}

/**
 * La table des paiements existe-t-elle ?
 */
function paiements_factures_table_ok()
{
    global $db;
    static $ok = false;
    if ($ok || !$db) {
        return $ok;
    }
    try {
        $ok = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiements_factures'")->fetchColumn() === 1;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Les paiements d'une facture, annulés compris, du plus ancien au plus récent.
 *
 * @throws PDOException
 * @return array<int, array<string, mixed>>
 */
function paiement_facture_lignes($type, $id)
{
    global $db;
    $types = paiements_factures_types();
    if (!isset($types[$type])) {
        return [];
    }
    $colonne = $types[$type]['colonne'];
    $st = $db->prepare("SELECT p.*, a.prenom AS auteur_prenom, a.nom AS auteur_nom, x.prenom AS annule_prenom, x.nom AS annule_nom
        FROM paiements_factures p
        LEFT JOIN admin a ON a.id = p.admin_id
        LEFT JOIN admin x ON x.id = p.annule_par
        WHERE p.$colonne = :id AND p.sync_deleted_at IS NULL
        ORDER BY p.date_paiement ASC, p.id ASC");
    $st->execute(['id' => (int) $id]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * L'état de paiement d'une facture : le total dû (celui imprimé sur la facture),
 * ce qui est payé, le reste, si elle peut recevoir un paiement et pourquoi.
 * Avec $verrou, la ligne de la facture est lue FOR UPDATE (dans une transaction).
 *
 * @throws PDOException
 * @return array<string, mixed>
 */
function paiement_facture_etat($type, $id, $verrou = false)
{
    global $db;
    $types = paiements_factures_types();
    $id = (int) $id;
    if (!isset($types[$type]) || $id <= 0) {
        return ['ok' => false, 'error' => 'Facture inconnue.'];
    }
    $pour = $verrou ? ' FOR UPDATE' : '';
    $payable = true;
    $raison = '';

    if ($type === 'facture_devis') {
        $st = $db->prepare("SELECT * FROM factures_devis WHERE id = :id$pour");
        $st->execute(['id' => $id]);
        $doc = $st->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            return ['ok' => false, 'error' => 'Facture introuvable.'];
        }
        $du = round((float) $doc['montant_total'], 2);
        $payee = !empty($doc['payee']);
        $date_paiement = $doc['date_paiement'] ?? null;
        $numero = (string) $doc['numero_facture'];
    } elseif ($type === 'bl') {
        $st = $db->prepare("SELECT * FROM bons_livraison WHERE id = :id$pour");
        $st->execute(['id' => $id]);
        $doc = $st->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            return ['ok' => false, 'error' => 'Bon de livraison introuvable.'];
        }
        // Le total imprimé par bl_facture.php : TTC si la TVA est incluse, sinon le total HT.
        $tva_incluse = bl_tva_columns_ok() && !empty($doc['tva_incluse']);
        $taux = (bl_tva_columns_ok() && (float) ($doc['taux_tva_pourcent'] ?? 0) > 0) ? (float) $doc['taux_tva_pourcent'] : null;
        $decomposition = fiscal_decomposer_net_ht((float) $doc['total_ht'], $tva_incluse, $taux);
        $du = $tva_incluse ? round((float) $decomposition['montant_ttc'], 2) : round((float) $doc['total_ht'], 2);
        $payee = !empty($doc['facture_bl_payee']);
        $date_paiement = $doc['date_paiement_bl'] ?? null;
        $numero = (string) $doc['numero_bl'];
        if (!bl_est_statut_verrouille($doc['statut'] ?? '')) {
            $payable = false;
            $raison = 'Validez d’abord le bon de livraison : il se paie une fois livré.';
        } else {
            $fm_du_bl = bl_facture_mensuelle_du_bl($id);
            if ($fm_du_bl !== null) {
                $payable = false;
                $raison = 'Ce bon fait partie de la facture mensuelle ' . $fm_du_bl . ' : son paiement s’enregistre sur cette facture.';
            }
        }
    } else {
        $st = $db->prepare("SELECT * FROM factures_mensuelles WHERE id = :id$pour");
        $st->execute(['id' => $id]);
        $doc = $st->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            return ['ok' => false, 'error' => 'Facture mensuelle introuvable.'];
        }
        // Le total imprimé par facture_mensuelle.php : TTC si la TVA est incluse, sinon le total HT.
        $tva_incluse = function_exists('factures_mensuelles_tva_incluse_column_ok')
            && factures_mensuelles_tva_incluse_column_ok() && !empty($doc['tva_incluse']);
        $decomposition = fiscal_decomposer_net_ht((float) $doc['total_ht'], true, fiscal_taux_tva_pourcent());
        $du = $tva_incluse ? round((float) $decomposition['montant_ttc'], 2) : round((float) $doc['total_ht'], 2);
        $payee = ($doc['statut'] ?? '') === 'payee';
        $date_paiement = $doc['date_paiement'] ?? null;
        $numero = (string) $doc['numero_facture'];
        if (($doc['statut'] ?? '') === 'brouillon') {
            $payable = false;
            $raison = 'Validez d’abord la facture du mois : un brouillon ne se paie pas.';
        }
    }

    $lignes = paiement_facture_lignes($type, $id);
    $paye = 0.0;
    $actifs = 0;
    foreach ($lignes as $ligne) {
        if (empty($ligne['date_annulation'])) {
            $paye += (float) $ligne['montant'];
            $actifs++;
        }
    }
    $paye = round($paye, 2);
    $reste = $payee ? 0.0 : round(max(0.0, $du - $paye), 2);

    return [
        'ok' => true,
        'type' => $type,
        'id' => $id,
        'numero' => $numero,
        'du' => $du,
        'paye' => $paye,
        'reste' => $reste,
        'payee' => $payee,
        'payable' => $payable && !$payee,
        'raison' => $payee ? 'Cette facture est payée.' : $raison,
        'anciens_sans_detail' => $payee && $actifs === 0,
        'date_paiement' => $date_paiement,
        'paiements' => $lignes,
    ];
}

/**
 * Pose l'état « payée » d'une facture soldée, dans la transaction en cours.
 * Rend la référence FPL d'une facture de devis (attribuée une seule fois).
 *
 * @throws PDOException|RuntimeException
 */
function paiement_facture_poser_payee($type, $id, $date)
{
    global $db;
    $id = (int) $id;
    if ($type === 'facture_devis') {
        for ($essai = 0; $essai < 5; $essai++) {
            $numero = generate_numero_reference_fpl_facture_devis();
            try {
                $st = $db->prepare('UPDATE factures_devis
                    SET payee = 1, date_paiement = :date, numero_reference_fpl = COALESCE(numero_reference_fpl, :numero)
                    WHERE id = :id');
                $st->execute(['date' => $date, 'numero' => $numero, 'id' => $id]);
                return (string) $db->query('SELECT numero_reference_fpl FROM factures_devis WHERE id = ' . $id)->fetchColumn();
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
        throw new RuntimeException('Impossible d’attribuer un numéro de référence FPL unique.');
    }
    if ($type === 'bl') {
        $st = $db->prepare('UPDATE bons_livraison SET facture_bl_payee = 1, date_paiement_bl = :date, date_modification = NOW() WHERE id = :id');
        $st->execute(['date' => $date, 'id' => $id]);
        return null;
    }
    $st = $db->prepare("UPDATE factures_mensuelles SET statut = 'payee', date_paiement = :date, date_modification = NOW()
        WHERE id = :id AND statut = 'validee'");
    $st->execute(['date' => $date, 'id' => $id]);
    return null;
}

/**
 * Retire l'état « payée » d'une facture qui n'est plus couverte, dans la transaction en cours.
 * La référence FPL d'une facture de devis est gardée : un numéro attribué n'est jamais repris.
 *
 * @throws PDOException
 */
function paiement_facture_retirer_payee($type, $id)
{
    global $db;
    $id = (int) $id;
    if ($type === 'facture_devis') {
        $db->prepare('UPDATE factures_devis SET payee = 0, date_paiement = NULL WHERE id = :id')->execute(['id' => $id]);
    } elseif ($type === 'bl') {
        $db->prepare('UPDATE bons_livraison SET facture_bl_payee = 0, date_paiement_bl = NULL, date_modification = NOW() WHERE id = :id')->execute(['id' => $id]);
    } else {
        $db->prepare("UPDATE factures_mensuelles SET statut = 'validee', date_paiement = NULL, date_modification = NOW()
            WHERE id = :id AND statut = 'payee'")->execute(['id' => $id]);
    }
}

/**
 * ENREGISTRER UN PAIEMENT REÇU.
 *
 * @return array{ok:bool, error?:string, paiement_id?:int, soldee?:bool, reste?:float, numero_reference_fpl?:string|null}
 */
function paiement_facture_enregistrer($type, $id, $montant_saisi, $mode, $date_paiement, $reference, $notes, $admin_id)
{
    global $db;
    if (!paiements_factures_table_ok()) {
        return ['ok' => false, 'error' => 'Le registre des paiements attend la mise à jour de la base (migrations/run_paiements_factures.php).'];
    }
    $types = paiements_factures_types();
    if (!isset($types[$type]) || (int) $id <= 0) {
        return ['ok' => false, 'error' => 'Facture inconnue.'];
    }
    if ((int) $admin_id <= 0) {
        return ['ok' => false, 'error' => 'Auteur inconnu : reconnectez-vous.'];
    }
    $modes = paiements_factures_modes();
    $mode = (string) $mode;
    if (!isset($modes[$mode])) {
        return ['ok' => false, 'error' => 'Choisissez le moyen de paiement.'];
    }
    $brut = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) $montant_saisi));
    $brut = str_replace(',', '.', $brut);
    if ($brut === '' || !is_numeric($brut) || (float) $brut <= 0) {
        return ['ok' => false, 'error' => 'Saisissez le montant reçu, en FCFA.'];
    }
    $montant = round((float) $brut, 2);
    $date = trim((string) $date_paiement);
    $jour = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$jour || $jour->format('Y-m-d') !== $date) {
        return ['ok' => false, 'error' => 'Indiquez le jour où le paiement a été reçu.'];
    }
    if ($date > date('Y-m-d')) {
        return ['ok' => false, 'error' => 'Un paiement ne peut pas être reçu dans le futur.'];
    }
    $reference = mb_substr(trim((string) $reference), 0, 100);
    $notes = mb_substr(trim((string) $notes), 0, 255);

    try {
        $db->beginTransaction();
        $etat = paiement_facture_etat($type, $id, true);
        if (empty($etat['ok'])) {
            $db->rollBack();
            return $etat;
        }
        if (!$etat['payable']) {
            $db->rollBack();
            return ['ok' => false, 'error' => $etat['raison']];
        }
        if ($montant > $etat['reste'] + 0.5) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Ce montant dépasse le reste à payer, ' . number_format($etat['reste'], 0, ',', ' ') . ' FCFA.'];
        }
        $colonne = $types[$type]['colonne'];
        $ins = $db->prepare("INSERT INTO paiements_factures ($colonne, montant, mode_paiement, date_paiement, reference, notes, admin_id, date_creation)
            VALUES (:document, :montant, :mode, :date, :reference, :notes, :admin, NOW())");
        $ins->execute([
            'document' => (int) $id,
            'montant' => $montant,
            'mode' => $mode,
            'date' => $date,
            'reference' => $reference !== '' ? $reference : null,
            'notes' => $notes !== '' ? $notes : null,
            'admin' => (int) $admin_id,
        ]);
        $paiement_id = (int) $db->lastInsertId();
        $reste = round(max(0.0, $etat['reste'] - $montant), 2);
        $soldee = $reste < 0.5;
        $reference_fpl = $soldee ? paiement_facture_poser_payee($type, (int) $id, $date) : null;
        $db->commit();

        return ['ok' => true, 'paiement_id' => $paiement_id, 'soldee' => $soldee, 'reste' => $soldee ? 0.0 : $reste, 'numero_reference_fpl' => $reference_fpl];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[paiement_facture_enregistrer] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Le paiement n’a pas pu être enregistré.'];
    }
}

/**
 * ANNULER UN PAIEMENT ERRONÉ : il reste visible, barré, avec son motif.
 *
 * @return array{ok:bool, error?:string, type?:string, id?:int, rouverte?:bool, reste?:float}
 */
function paiement_facture_annuler($paiement_id, $admin_id, $motif)
{
    global $db;
    if (!paiements_factures_table_ok()) {
        return ['ok' => false, 'error' => 'Le registre des paiements attend la mise à jour de la base (migrations/run_paiements_factures.php).'];
    }
    $motif = trim((string) $motif);
    if ((int) $admin_id <= 0 || mb_strlen($motif) < 3) {
        return ['ok' => false, 'error' => 'Indiquez le motif de l’annulation.'];
    }
    try {
        $db->beginTransaction();
        $st = $db->prepare('SELECT * FROM paiements_factures WHERE id = :id AND sync_deleted_at IS NULL FOR UPDATE');
        $st->execute(['id' => (int) $paiement_id]);
        $paiement = $st->fetch(PDO::FETCH_ASSOC);
        if (!$paiement) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Paiement introuvable.'];
        }
        $type = null;
        $document = 0;
        foreach (paiements_factures_types() as $code => $definition) {
            if (!empty($paiement[$definition['colonne']])) {
                $type = $code;
                $document = (int) $paiement[$definition['colonne']];
            }
        }
        if ($type === null) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Ce paiement ne vise aucune facture.'];
        }
        if (!empty($paiement['date_annulation'])) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Ce paiement est déjà annulé.', 'type' => $type, 'id' => $document];
        }
        $etat = paiement_facture_etat($type, $document, true);
        $up = $db->prepare('UPDATE paiements_factures SET annule_par = :admin, date_annulation = NOW(), motif_annulation = :motif
            WHERE id = :id AND date_annulation IS NULL');
        $up->execute(['admin' => (int) $admin_id, 'motif' => mb_substr($motif, 0, 255), 'id' => (int) $paiement_id]);
        $paye_apres = round((float) $etat['paye'] - (float) $paiement['montant'], 2);
        $reste = round(max(0.0, (float) $etat['du'] - $paye_apres), 2);
        $rouverte = false;
        if (!empty($etat['payee']) && $reste >= 0.5) {
            paiement_facture_retirer_payee($type, $document);
            $rouverte = true;
        }
        $db->commit();

        return ['ok' => true, 'type' => $type, 'id' => $document, 'rouverte' => $rouverte, 'reste' => $reste];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[paiement_facture_annuler] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'L’annulation n’a pas pu être enregistrée.'];
    }
}
