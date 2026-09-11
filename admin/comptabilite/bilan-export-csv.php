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
require_once __DIR__ . '/../../models/model_compta_synthese.php';

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
        'validee' => 'Impayée',
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
// La synthèse comptable unique (point 16) : sans elle, pas de fichier plutôt qu'un fichier aux totaux faux.
$synthese = compta_synthese_periode($d1, $d2);

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
$row(['Ventes', 'Caisse : tickets payés, date d’encaissement ; retours clients validés à leur date. Factures de devis : date de facture. Bons de livraison validés : date du bon, bons de retour à leur date ; les factures du mois ne s’ajoutent pas, elles regroupent ces bons. Site : commandes livrées ou payées, date de commande. Montants dus par le client.', '', '', '', '', '', '']);
$row(['Encaissements', 'Caisse par moyen de paiement, moins les espèces rendues et plus les espèces reçues aux retours validés. Factures : registre des paiements, à la date du paiement ; factures payées avant le registre : montant de la facture à la date de paiement notée.', '', '', '', '', '', '']);
$row(['À encaisser', 'À la date de l’export, quelle que soit la période : factures et bons pas encore soldés (un bon regroupé dans une facture du mois validée se compte par sa facture), commandes livrées non payées.', '', '', '', '', '', '']);
$row(['Solde', 'Encaissements moins dépenses. Ce n’est pas un bénéfice : le coût d’achat des pièces n’est pas compté.', '', '', '', '', '', '']);

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

$sv = $synthese['ventes'];
$se = $synthese['encaissements'];
$sa = $synthese['a_encaisser'];
$td = $data['totaux_dep'];
$fr = 'bilan_export_fmt_fcfa';
$row(['V_CAISSE', 'Ventes caisse magasin, retours déduits', 'Dû par le client', $fr($sv['caisse']['net']), 'FCFA', (string) $sv['caisse']['nb'],
    'Tickets ' . $fr($sv['caisse']['brut']) . ' · retours rendus ' . $fr($sv['caisse']['rendu']) . ' · pièces remises ' . $fr($sv['caisse']['remis']), '']);
$row(['V_DEVIS', 'Ventes par factures de devis', 'Dû par le client', $fr($sv['devis']['net']), 'FCFA', (string) $sv['devis']['nb'], 'À la date de facture', '']);
$row(['V_BL', 'Ventes par bons de livraison validés, retours déduits', 'Dû par le client', $fr($sv['bons']['net']), 'FCFA', (string) $sv['bons']['nb'],
    'Bons ' . $fr($sv['bons']['brut']) . ' · bons de retour ' . $fr($sv['bons']['retours']) . ' · factures du mois non ajoutées', '']);
$row(['V_SITE', 'Ventes du site (livrées ou payées)', 'TTC', $fr($sv['site']['net']), 'FCFA', (string) $sv['site']['nb'], 'À la date de commande', '']);
$row(['V_NETTES', 'VENTES NETTES DE LA PÉRIODE', 'Dû par le client', $fr($sv['total']), 'FCFA', '', 'Somme des quatre chemins', '']);
$canaux_txt = [];
foreach ($se['caisse']['canaux'] as $canal => $montant) {
    if ($montant >= 0.5) {
        $canaux_txt[] = compta_synthese_libelle_moyen($canal) . ' ' . $fr($montant);
    }
}
$row(['E_CAISSE', 'Encaissé en caisse, espèces des retours comprises', 'TTC', $fr($se['caisse']['net']), 'FCFA', (string) $se['caisse']['nb'],
    implode(' · ', $canaux_txt) . ' · espèces rendues ' . $fr($se['caisse']['especes_rendues']) . ' · reçues ' . $fr($se['caisse']['especes_recues']), '']);
$row(['E_FACTURES', 'Paiements de factures enregistrés', 'Montant payé', $fr($se['factures']['total']), 'FCFA', (string) $se['factures']['nb'],
    'Devis ' . $fr($se['factures']['types']['facture_devis']) . ' · bons ' . $fr($se['factures']['types']['bl']) . ' · factures du mois ' . $fr($se['factures']['types']['facture_mensuelle']), '']);
$row(['E_AVANT_REGISTRE', 'Factures payées avant le registre des paiements', 'Montant de la facture', $fr($se['avant_registre']['total']), 'FCFA', (string) $se['avant_registre']['nb'], 'Moyen de paiement inconnu', '']);
$row(['E_SITE', 'Commandes du site payées', 'TTC', $fr($se['site']['total']), 'FCFA', (string) $se['site']['nb'], 'À la date de livraison, sinon de commande', '']);
$row(['E_TOTAL', 'ENCAISSÉ SUR LA PÉRIODE', '', $fr($se['total']), 'FCFA', '', '', '']);
$row(['DEPENSES', 'Charges enregistrées', 'TTC (et détail HT/TVA ci-contre)', $fr($synthese['depenses']['montant']), 'FCFA', (string) $synthese['depenses']['nb'],
    'HT ' . $fr($td['sum_ht']) . ' · TVA ' . $fr($td['sum_tva']), '']);
$row(['SOLDE', 'Encaissé moins dépenses (pas un bénéfice)', '', $fr($synthese['solde']), 'FCFA', '', '', '']);
$row(['A_ENCAISSER', 'À encaisser à la date de l’export', 'Dû par le client', $fr($sa['total']), 'FCFA', '',
    'Devis ' . $fr($sa['devis']['montant']) . ' · bons ' . $fr($sa['bons']['montant']) . ' · factures du mois ' . $fr($sa['factures_mois']['montant']) . ' · site ' . $fr($sa['site']['montant']), '']);
$avoirs_txt = [];
foreach ($sa['avoirs']['lignes'] as $avoir) {
    $avoirs_txt[] = $avoir['document'] . ' ' . $fr($avoir['montant']);
}
$row(['AVOIRS', 'Avoirs à émettre (retours après une facture close)', 'Dû au client', $fr($sa['avoirs']['montant']), 'FCFA', (string) $sa['avoirs']['nb'], implode(' · ', $avoirs_txt), '']);

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
if ($n < (int) $synthese['ventes']['caisse']['nb']) {
    $row(['!', 'Liste limitée à ' . $n . ' tickets sur ' . (int) $synthese['ventes']['caisse']['nb'] . ' : le tableau 1 les compte tous.', '', '', '', '', '', '']);
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
