<?php
/**
 * CLÔTURE DE CAISSE ET HISTORIQUE DES CORRECTIONS DE PAIEMENT (10/09/2026).
 *
 * Une clôture est un ARRÊTÉ DE CAISSE : elle couvre les tickets encaissés depuis
 * la clôture précédente jusqu'à l'instant où le caissier clôture. Le caissier
 * compte les espèces du tiroir ; l'écart avec les espèces encaissées est
 * enregistré, et doit être expliqué dès qu'il n'est pas nul. On peut clôturer
 * plusieurs fois le même jour (relève de midi, fin de journée) sans jamais
 * bloquer les ventes qui suivent : elles ouvrent la période suivante.
 *
 * Un ticket couvert par une clôture ne se corrige plus : son argent a été compté.
 * Avant la clôture, chaque correction de paiement est gardée avec l'ancienne
 * valeur, la nouvelle, son auteur, sa date et son motif
 * (caisse_corriger_paiement_vente_payee dans model_caisse.php).
 *
 * Les dépenses (table depenses) ne portent ni heure ni moyen de paiement : elles
 * sont montrées pour mémoire sur les dates de la période, jamais déduites des
 * espèces attendues. Aucune n'était saisie le 10/09/2026.
 *
 * Tables : migrations/run_caisse_cloture.php.
 */

require_once __DIR__ . '/model_caisse.php';
require_once __DIR__ . '/model_caisse_compta.php';

/**
 * Les deux tables de la clôture existent-elles ?
 */
function caisse_cloture_tables_ok()
{
    global $db;
    static $ok = false;
    if ($ok || !$db) {
        return $ok;
    }
    try {
        $n = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('caisse_clotures', 'caisse_corrections_paiement')")->fetchColumn();
        $ok = ($n === 2);
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * L'instantané du paiement d'un ticket, tel que l'historique le garde.
 *
 * @return array<string, mixed>
 */
function caisse_paiement_instantane(array $vente)
{
    $montant = static function ($valeur) {
        return ($valeur === null || $valeur === '') ? null : round((float) $valeur, 2);
    };
    $instantane = ['mode_paiement' => (string) ($vente['mode_paiement'] ?? '')];
    foreach (['montant_especes', 'montant_carte', 'montant_orange_money', 'montant_wave', 'montant_mobile_money', 'montant_recu', 'monnaie_rendue'] as $colonne) {
        $instantane[$colonne] = $montant($vente[$colonne] ?? null);
    }
    $instantane['notes'] = (isset($vente['notes']) && $vente['notes'] !== '') ? (string) $vente['notes'] : null;
    return $instantane;
}

/**
 * Le libellé lisible d'un instantané de paiement (JSON enregistré ou tableau).
 */
function caisse_paiement_libelle_instantane($instantane)
{
    $p = is_array($instantane) ? $instantane : json_decode((string) $instantane, true);
    if (!is_array($p) || (string) ($p['mode_paiement'] ?? '') === '') {
        return '—';
    }
    $libelle = caisse_compta_libelle_paiement_ticket($p);
    if (($p['mode_paiement'] ?? '') !== 'mixte' && isset($p['montant_recu']) && (float) $p['montant_recu'] >= 0.005) {
        $libelle .= ', reçu ' . number_format((float) $p['montant_recu'], 0, ',', ' ') . ' FCFA';
    }
    return $libelle;
}

/**
 * La dernière clôture enregistrée, avec le nom du caissier, ou null.
 *
 * @throws PDOException
 */
function caisse_cloture_derniere()
{
    global $db;
    $st = $db->query("SELECT c.*, a.prenom AS caissier_prenom, a.nom AS caissier_nom
        FROM caisse_clotures c
        LEFT JOIN admin a ON a.id = c.caissier_id
        WHERE c.sync_deleted_at IS NULL
        ORDER BY c.periode_fin DESC, c.id DESC
        LIMIT 1");
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Ce que la caisse a encaissé après $debut (exclu ; null = depuis toujours)
 * et jusqu'à $fin (inclus) : tickets, montants par canal (ventilation de la
 * comptabilité), espèces attendues, dépenses des mêmes dates pour mémoire,
 * nombre de corrections de paiement.
 *
 * @throws PDOException
 * @return array<string, mixed>
 */
function caisse_cloture_calculer_periode($debut, $fin)
{
    global $db;
    $debut = ($debut === null || $debut === '') ? null : (string) $debut;
    $moment = 'COALESCE(v.date_encaissement, v.date_vente)';
    $bind = ['fin' => (string) $fin];
    $apres_debut = '';
    if ($debut !== null) {
        $apres_debut = " AND $moment > :debut";
        $bind['debut'] = $debut;
    }

    $st = $db->prepare("SELECT v.id, v.numero_ticket, v.montant_total, v.mode_paiement, v.montant_especes, v.montant_carte,
            v.montant_orange_money, v.montant_wave, v.montant_mobile_money, v.caissier_id, $moment AS moment
        FROM caisse_ventes v
        WHERE v.statut = 'paye' AND $moment <= :fin$apres_debut
        ORDER BY $moment ASC, v.id ASC");
    $st->execute($bind);
    $tickets = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $canaux = [];
    foreach (caisse_compta_canaux_tri() as $canal) {
        $canaux[$canal] = ['nb' => 0, 'montant' => 0.0];
    }
    $total = 0.0;
    foreach ($tickets as $ticket) {
        $total += (float) $ticket['montant_total'];
        foreach (array_keys($canaux) as $canal) {
            $part = caisse_compta_montant_vente_canal($ticket, $canal);
            if ($part >= 0.005) {
                $canaux[$canal]['nb']++;
                $canaux[$canal]['montant'] += $part;
            }
        }
    }
    foreach ($canaux as $canal => $valeurs) {
        $canaux[$canal]['montant'] = round($valeurs['montant'], 2);
    }

    $depenses = 0.0;
    try {
        $dep = $db->prepare('SELECT COALESCE(SUM(COALESCE(montant_ttc, montant_ht)), 0) FROM depenses
            WHERE sync_deleted_at IS NULL AND date_depense <= DATE(:fin)' . ($debut !== null ? ' AND date_depense >= DATE(:debut)' : ''));
        $dep->execute($bind);
        $depenses = (float) $dep->fetchColumn();
    } catch (PDOException $e) {
        $depenses = 0.0; // pas de table des dépenses sur ce serveur : rien à montrer
    }

    $corr = $db->prepare('SELECT COUNT(*) FROM caisse_corrections_paiement
        WHERE sync_deleted_at IS NULL AND date_correction <= :fin' . ($debut !== null ? ' AND date_correction > :debut' : ''));
    $corr->execute($bind);

    /* LES RETOURS CLIENTS (11/09/2026) : un retour compte dans la caisse du jour
     * où le caissier le valide. Les espèces rendues sortent du tiroir, celles
     * reçues pour un échange plus cher y entrent. Une clôture passée n'est
     * jamais rouverte : le retour d'un vieux ticket tombe dans la caisse en cours. */
    $retours = ['nb' => 0, 'especes_rendues' => 0.0, 'especes_recues' => 0.0];
    try {
        $ret = $db->prepare("SELECT COUNT(*) AS nb, COALESCE(SUM(especes_a_rendre), 0) AS rendues, COALESCE(SUM(especes_a_recevoir), 0) AS recues
            FROM caisse_retours
            WHERE statut = 'valide' AND sync_deleted_at IS NULL AND date_validation <= :fin" . ($debut !== null ? ' AND date_validation > :debut' : ''));
        $ret->execute($bind);
        $ligne = $ret->fetch(PDO::FETCH_ASSOC) ?: [];
        $retours = [
            'nb' => (int) ($ligne['nb'] ?? 0),
            'especes_rendues' => round((float) ($ligne['rendues'] ?? 0), 2),
            'especes_recues' => round((float) ($ligne['recues'] ?? 0), 2),
        ];
    } catch (PDOException $e) {
        // pas encore de table des retours sur ce serveur : rien à retirer du tiroir
    }
    $especes_encaissees = (float) ($canaux['especes']['montant'] ?? 0.0);

    return [
        'debut' => $debut,
        'fin' => (string) $fin,
        'tickets' => $tickets,
        'nb' => count($tickets),
        'total' => round($total, 2),
        'canaux' => $canaux,
        'especes_encaissees' => round($especes_encaissees, 2),
        'retours' => $retours,
        'especes_attendues' => round($especes_encaissees - $retours['especes_rendues'] + $retours['especes_recues'], 2),
        'depenses' => round($depenses, 2),
        'corrections' => (int) $corr->fetchColumn(),
    ];
}

/**
 * Les colonnes des retours clients existent-elles sur caisse_clotures ?
 * (migrations/run_caisse_retours.php, 11/09/2026)
 */
function caisse_cloture_colonnes_retours_ok()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $n = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'caisse_clotures' AND COLUMN_NAME IN ('nb_retours', 'retours_especes_rendues', 'retours_especes_recues')")->fetchColumn();
        $ok = ($n === 3);
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * La caisse en cours : depuis la dernière clôture jusqu'à maintenant.
 * La clé « derniere » porte la dernière clôture (ou null). Null si le calcul échoue.
 *
 * @return array<string, mixed>|null
 */
function caisse_cloture_periode_en_cours()
{
    global $db;
    if (!caisse_cloture_tables_ok()) {
        return null;
    }
    try {
        $derniere = caisse_cloture_derniere();
        $fin = (string) $db->query('SELECT NOW()')->fetchColumn();
        $periode = caisse_cloture_calculer_periode($derniere ? (string) $derniere['periode_fin'] : null, $fin);
        $periode['derniere'] = $derniere;
        return $periode;
    } catch (PDOException $e) {
        error_log('[caisse_cloture_periode_en_cours] ' . $e->getMessage());
        return null;
    }
}

/**
 * CLÔTURER : compter le tiroir, constater l'écart, arrêter la période.
 *
 * @return array{ok:bool, error?:string, cloture_id?:int, ecart?:float}
 */
function caisse_cloturer($caissier_id, $especes_comptees_saisie, $commentaire)
{
    global $db;
    if (!caisse_cloture_tables_ok()) {
        return ['ok' => false, 'error' => 'La clôture attend la mise à jour de la base (migrations/run_caisse_cloture.php).'];
    }
    $caissier_id = (int) $caissier_id;
    if ($caissier_id <= 0) {
        return ['ok' => false, 'error' => 'Caissier inconnu : reconnectez-vous.'];
    }
    $brut = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) $especes_comptees_saisie));
    $brut = str_replace(',', '.', $brut);
    if ($brut === '' || !is_numeric($brut) || (float) $brut < 0) {
        return ['ok' => false, 'error' => 'Saisissez les espèces comptées dans le tiroir : un montant en FCFA, 0 si le tiroir est vide.'];
    }
    $comptees = round((float) $brut, 2);
    $commentaire = trim((string) $commentaire);
    if (mb_strlen($commentaire) > 255) {
        $commentaire = mb_substr($commentaire, 0, 255);
    }

    try {
        $db->beginTransaction();
        // Verrou sur la dernière clôture : deux clôtures simultanées ne couvrent pas deux fois la même période.
        $derniere = $db->query('SELECT id, periode_fin FROM caisse_clotures WHERE sync_deleted_at IS NULL
            ORDER BY periode_fin DESC, id DESC LIMIT 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        $fin = (string) $db->query('SELECT NOW()')->fetchColumn();
        $debut = $derniere ? (string) $derniere['periode_fin'] : null;
        if ($debut !== null && $debut >= $fin) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Une clôture vient d’être enregistrée à l’instant : rechargez la page.'];
        }

        $p = caisse_cloture_calculer_periode($debut, $fin);
        $ecart = round($comptees - (float) $p['especes_attendues'], 2);
        if (abs($ecart) >= 0.5 && $commentaire === '') {
            $db->rollBack();
            return [
                'ok' => false,
                'ecart' => $ecart,
                'error' => 'Écart de ' . ($ecart > 0 ? '+' : '−') . number_format(abs($ecart), 0, ',', ' ')
                    . ' FCFA entre les espèces comptées et les espèces encaissées : expliquez-le dans le commentaire.',
            ];
        }

        $colonnes = 'caissier_id, periode_debut, periode_fin, nb_tickets, total_encaisse, montant_especes, montant_carte,
             montant_orange_money, montant_wave, montant_cheque, montant_autre, especes_attendues, especes_comptees,
             ecart, commentaire, date_creation';
        $valeurs = ':caissier, :debut, :fin, :nb, :total, :especes, :carte, :orange, :wave, :cheque, :autre,
                    :attendues, :comptees, :ecart, :commentaire, NOW()';
        $parametres = [
            'caissier' => $caissier_id,
            'debut' => $debut,
            'fin' => $fin,
            'nb' => (int) $p['nb'],
            'total' => $p['total'],
            'especes' => $p['canaux']['especes']['montant'],
            'carte' => $p['canaux']['carte']['montant'],
            'orange' => $p['canaux']['orange_money']['montant'],
            'wave' => $p['canaux']['wave']['montant'],
            'cheque' => $p['canaux']['cheque']['montant'],
            'autre' => $p['canaux']['autre']['montant'],
            'attendues' => $p['especes_attendues'],
            'comptees' => $comptees,
            'ecart' => $ecart,
            'commentaire' => $commentaire !== '' ? $commentaire : null,
        ];
        // Ce que les retours clients ont fait au tiroir, gardé sur l'arrêté (11/09/2026).
        if (caisse_cloture_colonnes_retours_ok()) {
            $colonnes .= ', nb_retours, retours_especes_rendues, retours_especes_recues';
            $valeurs .= ', :nb_retours, :retours_rendues, :retours_recues';
            $parametres['nb_retours'] = (int) $p['retours']['nb'];
            $parametres['retours_rendues'] = $p['retours']['especes_rendues'];
            $parametres['retours_recues'] = $p['retours']['especes_recues'];
        }
        $ins = $db->prepare("INSERT INTO caisse_clotures ($colonnes) VALUES ($valeurs)");
        $ins->execute($parametres);
        $id = (int) $db->lastInsertId();
        $db->commit();

        return ['ok' => true, 'cloture_id' => $id, 'ecart' => $ecart];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_cloturer] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'La clôture n’a pas pu être enregistrée.'];
    }
}

/**
 * Les dernières clôtures, les plus récentes d'abord.
 *
 * @return array<int, array<string, mixed>>
 */
function caisse_clotures_liste($limit = 30)
{
    global $db;
    if (!caisse_cloture_tables_ok()) {
        return [];
    }
    try {
        return $db->query('SELECT c.*, a.prenom AS caissier_prenom, a.nom AS caissier_nom
            FROM caisse_clotures c
            LEFT JOIN admin a ON a.id = c.caissier_id
            WHERE c.sync_deleted_at IS NULL
            ORDER BY c.periode_fin DESC, c.id DESC
            LIMIT ' . max(1, min(500, (int) $limit)))->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_clotures_liste] ' . $e->getMessage());
        return [];
    }
}

/**
 * Une clôture par son id, avec le nom du caissier, ou null.
 *
 * @return array<string, mixed>|null
 */
function caisse_cloture_par_id($id)
{
    global $db;
    if (!caisse_cloture_tables_ok() || (int) $id <= 0) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT c.*, a.prenom AS caissier_prenom, a.nom AS caissier_nom
            FROM caisse_clotures c
            LEFT JOIN admin a ON a.id = c.caissier_id
            WHERE c.id = :id AND c.sync_deleted_at IS NULL');
        $st->execute(['id' => (int) $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('[caisse_cloture_par_id] ' . $e->getMessage());
        return null;
    }
}

/**
 * Les tickets d'une clôture, pour son détail imprimable.
 *
 * @return array<int, array<string, mixed>>
 */
function caisse_cloture_tickets(array $cloture)
{
    try {
        return caisse_cloture_calculer_periode($cloture['periode_debut'] ?? null, (string) $cloture['periode_fin'])['tickets'];
    } catch (PDOException $e) {
        error_log('[caisse_cloture_tickets] ' . $e->getMessage());
        return [];
    }
}

/**
 * La clôture qui couvre un ticket encaissé, ou null s'il est dans la caisse en cours.
 * Les périodes s'enchaînent : la première clôture dont la fin suit le ticket le couvre.
 *
 * @return array<string, mixed>|null
 */
function caisse_cloture_couvrant_vente(array $vente)
{
    global $db;
    if (!caisse_cloture_tables_ok()) {
        return null;
    }
    $moment = (string) ($vente['date_encaissement'] ?? '');
    if ($moment === '') {
        $moment = (string) ($vente['date_vente'] ?? '');
    }
    if ($moment === '') {
        return null;
    }
    try {
        $st = $db->prepare('SELECT * FROM caisse_clotures
            WHERE periode_fin >= :moment AND sync_deleted_at IS NULL
            ORDER BY periode_fin ASC, id ASC LIMIT 1');
        $st->execute(['moment' => $moment]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('[caisse_cloture_couvrant_vente] ' . $e->getMessage());
        return null;
    }
}

/**
 * Les corrections de paiement, les plus récentes d'abord ; d'un seul ticket si $vente_id > 0.
 *
 * @return array<int, array<string, mixed>>
 */
function caisse_corrections_liste($vente_id = 0, $limit = 50)
{
    global $db;
    if (!caisse_cloture_tables_ok()) {
        return [];
    }
    $vente_id = (int) $vente_id;
    try {
        $st = $db->prepare('SELECT r.*, v.numero_ticket, a.prenom AS auteur_prenom, a.nom AS auteur_nom
            FROM caisse_corrections_paiement r
            LEFT JOIN caisse_ventes v ON v.id = r.vente_id
            LEFT JOIN admin a ON a.id = r.admin_id
            WHERE r.sync_deleted_at IS NULL' . ($vente_id > 0 ? ' AND r.vente_id = :vente' : '') . '
            ORDER BY r.date_correction DESC, r.id DESC
            LIMIT ' . max(1, min(500, (int) $limit)));
        $st->execute($vente_id > 0 ? ['vente' => $vente_id] : []);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_corrections_liste] ' . $e->getMessage());
        return [];
    }
}
