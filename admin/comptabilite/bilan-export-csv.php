<?php
/**
 * Export CSV — bilan comptable (même filtres que bilan.php)
 * Fichier structuré pour Excel : métadonnées, synthèse, annexes détaillées, montants format FR.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_comptabilite()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

require_once __DIR__ . '/../../models/model_bilan_comptable.php';

/**
 * Montant pour affichage comptable FR (Excel FR : séparateur ; et nombres avec virgule décimale).
 */
function bilan_export_fmt_fcfa($value) {
    return number_format(round((float) $value, 2), 2, ',', ' ');
}

/**
 * @param mixed $dateSql Chaîne datetime ou date SQL
 */
function bilan_export_fmt_datetime($dateSql) {
    $s = trim((string) $dateSql);
    if ($s === '') {
        return '';
    }
    $ts = strtotime($s);
    if ($ts === false) {
        return $s;
    }
    return date('d/m/Y H:i', $ts);
}

function bilan_export_fmt_date_seul($dateSql) {
    $s = trim((string) $dateSql);
    if ($s === '') {
        return '';
    }
    $ts = strtotime(substr($s, 0, 10));
    if ($ts === false) {
        return $s;
    }
    return date('d/m/Y', $ts);
}

function bilan_export_libelle_statut_commande($statut) {
    $m = [
        'livree' => 'Livrée',
        'paye' => 'Payée',
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'en_preparation' => 'En préparation',
        'expediee' => 'Expédiée',
        'annulee' => 'Annulée',
    ];
    $k = strtolower((string) $statut);
    return $m[$k] ?? ucfirst(str_replace('_', ' ', $k));
}

function bilan_export_libelle_type_depense($type) {
    if ($type === 'avec_tva') {
        return 'Avec TVA';
    }
    if ($type === 'sans_tva') {
        return 'Sans TVA (HT = TTC)';
    }
    return (string) $type;
}

function bilan_export_libelle_statut_fm($st) {
    $m = [
        'brouillon' => 'Brouillon',
        'validee' => 'Validée',
        'payee' => 'Payée',
    ];
    $k = strtolower((string) $st);
    return $m[$k] ?? $st;
}

function bilan_export_libelle_statut_bl($st) {
    $m = [
        'brouillon' => 'Brouillon',
        'valide' => 'Validé',
        'paye' => 'Payé',
    ];
    $k = strtolower((string) $st);
    return $m[$k] ?? $st;
}

$periode = bilan_comptable_parse_periode($_GET);
$d1 = $periode['date_debut'];
$d2 = $periode['date_fin'];
$data = bilan_comptable_collecter_donnees($d1, $d2, 0);

$fn = 'bilan_FPL_' . preg_replace('/[^0-9-]/', '', $d1) . '_au_' . preg_replace('/[^0-9-]/', '', $d2) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fn . '"');
header('Cache-Control: no-store, no-cache');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

$csv_esc = static function ($v) {
    $s = str_replace('"', '""', (string) $v);
    return '"' . $s . '"';
};

$row = static function (array $cells) use ($csv_esc, $out) {
    fwrite($out, implode(';', array_map($csv_esc, $cells)) . "\r\n");
};

$blank = static function () use ($row) {
    $row(['', '', '', '', '', '', '', '']);
};

$banner = static function ($title) use ($row) {
    $row(['▪▪▪ ' . $title, '', '', '', '', '', '', '']);
};

/* ---------- Bloc 1 : identification du document ---------- */
$banner('DOCUMENT');
$row([
    'Titre',
    'Bilan comptable — synthèse et annexes',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Entité',
    'FOUTA POIDS LOURDS',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Export CSV',
    'Fichier structuré — ouvrir dans Excel (séparateur point-virgule, UTF-8)',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Généré le',
    date('d/m/Y à H\hi'),
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Utilisateur export',
    trim((string) ($_SESSION['admin_prenom'] ?? '') . ' ' . (string) ($_SESSION['admin_nom'] ?? '')) ?: (string) ($_SESSION['admin_email'] ?? ''),
    '',
    '',
    '',
    '',
    '',
    '',
]);

$blank();
$banner('FILTRE APPLIQUÉ À L’EXPORT');
$row(['Libellé période', $periode['libelle'], '', '', '', '', '', '']);
$row(['Date début (incluse)', bilan_export_fmt_date_seul($d1), '', '', '', '', '', '']);
$row(['Date fin (incluse)', bilan_export_fmt_date_seul($d2), '', '', '', '', '', '']);
$types_filtre = ['jour' => 'Un jour', 'mois' => 'Un mois calendaire', 'plage' => 'Plage libre (du / au)'];
$row(['Type de filtre', $types_filtre[$periode['type']] ?? $periode['type'], '', '', '', '', '', '']);

$blank();
$banner('RAPPEL MÉTHODOLOGIQUE');
$row([
    'E-commerce',
    'Commandes statuts Livrée / Payée · référence temporelle : date de commande · montants TTC.',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Caisse magasin',
    'Tickets payés · date retenue : encaissement (ou date vente) · montants TTC.',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Dépenses',
    'Date de dépense · colonnes HT / TVA / TTC.',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'BL B2B',
    'Date du bon de livraison · statuts validé / payé · montants HT.',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Factures mensuelles',
    'Période de facturation (mois) qui chevauche l’intervalle exporté · montants HT.',
    '',
    '',
    '',
    '',
    '',
    '',
]);
$row([
    'Important',
    'Les postes HT et TTC ne sont pas agrégés en un solde unique dans ce fichier — document d’aide à la comptabilité.',
    '',
    '',
    '',
    '',
    '',
    '',
]);

/* ---------- Bloc 2 : synthèse chiffrée ---------- */
$blank();
$banner('TABLEAU 1 — SYNTHÈSE (TOTAUX)');
$row([
    'Code',
    'Rubrique',
    'Nature',
    'Montant FCFA',
    'Unité',
    'Volume',
    'Détail',
    '',
]);

$st = $data['stats_web'];
$row([
    'WEB',
    'Chiffre d’affaires e-commerce (livrées + payées)',
    'TTC',
    bilan_export_fmt_fcfa($st['ca_total']),
    'FCFA',
    (string) (int) $st['nb'],
    'Livrée : ' . bilan_export_fmt_fcfa($st['ca_livree']) . ' · Payée : ' . bilan_export_fmt_fcfa($st['ca_paye']),
    '',
]);

$row([
    'CAISSE',
    'Encaissements caisse magasin',
    'TTC',
    bilan_export_fmt_fcfa($data['caisse_totaux']['total_ttc']),
    'FCFA',
    (string) (int) $data['caisse_totaux']['nb'],
    'Total tickets sur la période',
    '',
]);

$td = $data['totaux_dep'];
$row([
    'DEPENSES',
    'Charges enregistrées',
    'TTC (et détail HT/TVA ci-contre)',
    bilan_export_fmt_fcfa($td['sum_ttc']),
    'FCFA',
    (string) (int) $td['nb'],
    'HT ' . bilan_export_fmt_fcfa($td['sum_ht']) . ' · TVA ' . bilan_export_fmt_fcfa($td['sum_tva']),
    '',
]);

$bls = $data['stats_bl'];
$row([
    'BL',
    'Bons de livraison B2B (comptabilisés)',
    'HT',
    bilan_export_fmt_fcfa($bls['somme_bl_ht']),
    'FCFA',
    (string) (int) $bls['nb_bl'],
    'Clients distincts : ' . (int) $bls['nb_clients'],
    '',
]);

$fms = $data['stats_fm'];
$row([
    'FAC_MENS',
    'Factures mensuelles (mois chevauchants)',
    'HT',
    bilan_export_fmt_fcfa($fms['somme_ht']),
    'FCFA',
    (string) (int) $fms['nb_factures'],
    'Somme des total HT des fiches concernées',
    '',
]);

/* ---------- Annexes détaillées ---------- */
$blank();
$banner('ANNEXE A — COMMANDES E-COMMERCE (LIGNE À LIGNE)');
$row([
    'N°',
    'Date et heure commande',
    'N° commande',
    'Client',
    'E-mail',
    'Statut',
    'Montant TTC (FCFA)',
    'ID interne',
]);

$n = 0;
foreach ($data['commandes'] as $c) {
    $n++;
    $nom = trim(($c['user_prenom'] ?? '') . ' ' . ($c['user_nom'] ?? ''));
    $row([
        (string) $n,
        bilan_export_fmt_datetime($c['date_commande'] ?? ''),
        $c['numero_commande'] ?? '',
        $nom,
        $c['user_email'] ?? '',
        bilan_export_libelle_statut_commande($c['statut'] ?? ''),
        bilan_export_fmt_fcfa($c['montant_total'] ?? 0),
        (string) (int) ($c['id'] ?? 0),
    ]);
}
if ($n === 0) {
    $row(['—', 'Aucune commande sur cette période.', '', '', '', '', '', '']);
}

$blank();
$banner('ANNEXE B — TICKETS CAISSE');
$row([
    'N°',
    'Date / heure',
    'N° ticket',
    'Caissier',
    'Mode de paiement',
    'Montant TTC (FCFA)',
    'Notes',
    'ID interne',
]);

$n = 0;
foreach ($data['caisse_liste'] as $cv) {
    $n++;
    $adm = trim(($cv['admin_prenom'] ?? '') . ' ' . ($cv['admin_nom'] ?? ''));
    $row([
        (string) $n,
        bilan_export_fmt_datetime($cv['date_vente'] ?? ''),
        $cv['numero_ticket'] ?? '',
        $adm !== '' ? $adm : '—',
        caisse_compta_libelle_mode($cv['mode_paiement'] ?? ''),
        bilan_export_fmt_fcfa($cv['montant_total'] ?? 0),
        isset($cv['notes']) ? trim((string) $cv['notes']) : '',
        (string) (int) ($cv['id'] ?? 0),
    ]);
}
if ($n === 0) {
    $row(['—', 'Aucun ticket sur cette période.', '', '', '', '', '', '']);
}

$blank();
$banner('ANNEXE C — DÉPENSES');
$row([
    'N°',
    'Date',
    'Libellé',
    'Catégorie',
    'Type TVA',
    'HT (FCFA)',
    'TVA (FCFA)',
    'TTC (FCFA)',
]);

$n = 0;
foreach ($data['depenses'] as $dep) {
    $n++;
    $row([
        (string) $n,
        bilan_export_fmt_date_seul($dep['date_depense'] ?? ''),
        $dep['libelle'] ?? '',
        $dep['categorie_nom'] ?? '—',
        bilan_export_libelle_type_depense($dep['type_depense'] ?? ''),
        bilan_export_fmt_fcfa($dep['montant_ht'] ?? 0),
        bilan_export_fmt_fcfa($dep['montant_tva'] ?? 0),
        bilan_export_fmt_fcfa($dep['montant_ttc'] ?? 0),
    ]);
}
if ($n === 0) {
    $row(['—', 'Aucune dépense sur cette période.', '', '', '', '', '', '']);
}

$blank();
$banner('ANNEXE D — BONS DE LIVRAISON B2B');
$row([
    'N°',
    'Date BL',
    'N° BL',
    'Client (raison sociale)',
    'Statut',
    'Total HT (FCFA)',
    'ID BL',
    '',
]);

$n = 0;
foreach ($data['bl_detail'] as $b) {
    $n++;
    $num = $b['numero_bl'] ?? (string) ($b['id'] ?? '');
    $row([
        (string) $n,
        bilan_export_fmt_date_seul($b['date_bl'] ?? ''),
        (string) $num,
        $b['raison_sociale'] ?? '',
        bilan_export_libelle_statut_bl($b['statut'] ?? ''),
        bilan_export_fmt_fcfa($b['total_ht'] ?? 0),
        (string) (int) ($b['id'] ?? 0),
        '',
    ]);
}
if ($n === 0) {
    $row(['—', 'Aucun BL comptabilisé sur cette période.', '', '', '', '', '', '']);
}

$blank();
$banner('ANNEXE E — FACTURES MENSUELLES HT');
$row([
    'N°',
    'Période facture',
    'N° facture',
    'Client',
    'Statut',
    'Total HT (FCFA)',
    'ID',
    '',
]);

$n = 0;
foreach ($data['fm_detail'] as $f) {
    $n++;
    $per = ($f['annee'] ?? '') . ' / ' . str_pad((string) ($f['mois'] ?? ''), 2, '0', STR_PAD_LEFT);
    $row([
        (string) $n,
        $per,
        $f['numero_facture'] ?? '',
        $f['raison_sociale'] ?? '',
        bilan_export_libelle_statut_fm($f['statut'] ?? ''),
        bilan_export_fmt_fcfa($f['total_ht'] ?? 0),
        (string) (int) ($f['id'] ?? 0),
        '',
    ]);
}
if ($n === 0) {
    $row(['—', 'Aucune facture mensuelle rattachée à cette fenêtre de dates.', '', '', '', '', '', '']);
}

$blank();
$banner('FIN DU FICHIER');
$row(['Lignes générées automatiquement — ne pas modifier les montants sources en Excel sans contrôle.', '', '', '', '', '', '', '']);

fclose($out);
exit;
