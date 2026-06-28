<?php
/**
 * Liste des factures mensuelles B2B (brouillon, impayée ou payée) — par client
 * Liste des dossiers, puis tableau des factures par client (?client=id)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_factures_mensuelles.php';

require_once __DIR__ . '/../../includes/fiscal_tva.php';

if (!bl_tables_available() || !factures_mensuelles_table_ok()) {
    header('Location: index.php?tab=bl');
    exit;
}

$groupes_all = get_bl_fm_archive_groupes_par_client();
$client_filter = isset($_GET['client']) ? (int) $_GET['client'] : 0;
$arch_type = isset($_GET['type']) ? (string) $_GET['type'] : 'ht';
if (!in_array($arch_type, ['ht', 'ttc'], true)) {
    $arch_type = 'ht';
}
$detail_client = null;
$detail_bls = [];

if ($client_filter > 0) {
    foreach ($groupes_all as $g) {
        if ((int) ($g['client']['id'] ?? 0) === $client_filter) {
            $detail_client = $g['client'];
            $detail_bls = $g['bls'] ?? [];
            break;
        }
    }
}

$factures_groupees = [];
$factures_groupees = [];
$kpi_arch_nb_fm = 0;
$kpi_arch_nb_bl = 0;
$kpi_arch_total_paye = 0.0;
$kpi_arch_total_impaye = 0.0;
$arch_derniere_fm_id = 0;
if ($client_filter > 0 && $detail_client && !empty($detail_bls)) {
    $kpi_arch_nb_bl = count($detail_bls);
    $par_fm = [];
    $taux_arch = fiscal_taux_tva_pourcent();
    foreach ($detail_bls as $b) {
        $fmid = (int) ($b['facture_mensuelle_id'] ?? 0);
        if ($fmid <= 0) {
            continue;
        }
        if (!isset($par_fm[$fmid])) {
            $fm_tva_incl = !empty($b['fm_tva_incluse']);
            $fm_total_ht = (float) ($b['fm_total_ht'] ?? 0);
            if ($fm_total_ht <= 0) {
                $fm_total_ht = (float) ($b['total_ht'] ?? 0);
            }
            $par_fm[$fmid] = [
                'id' => $fmid,
                'numero' => (string) ($b['fm_numero_facture'] ?? ''),
                'statut' => (string) ($b['fm_statut'] ?? ''),
                'mois' => (int) ($b['fm_mois'] ?? 0),
                'annee' => (int) ($b['fm_annee'] ?? 0),
                'tva_incluse' => $fm_tva_incl,
                'total_ht' => $fm_total_ht,
                'nb_bl' => 0,
                'somme_bl_ht' => 0.0,
            ];
        }
        $par_fm[$fmid]['nb_bl']++;
        $par_fm[$fmid]['somme_bl_ht'] += (float) ($b['total_ht'] ?? 0);
        if ((float) ($par_fm[$fmid]['total_ht'] ?? 0) <= 0) {
            $par_fm[$fmid]['total_ht'] = $par_fm[$fmid]['somme_bl_ht'];
        }
    }
    uasort($par_fm, static function ($a, $b) {
        if ($a['annee'] !== $b['annee']) {
            return $b['annee'] <=> $a['annee'];
        }
        return $b['mois'] <=> $a['mois'];
    });
    $factures_groupees_all = array_values($par_fm);
    $factures_groupees = array_values(array_filter($factures_groupees_all, static function ($fg) use ($arch_type) {
        $incl = !empty($fg['tva_incluse']);
        return ($arch_type === 'ttc') ? $incl : !$incl;
    }));
    $kpi_arch_nb_fm = count($factures_groupees);
    $arch_derniere_fm_id = (int) ($factures_groupees[0]['id'] ?? 0);
    foreach ($factures_groupees as $fg) {
        $ht_fg = (float) ($fg['total_ht'] ?? $fg['somme_bl_ht'] ?? 0);
        if (!empty($fg['tva_incluse'])) {
            $mont_fg = fiscal_decomposer_net_ht($ht_fg, true, $taux_arch)['montant_ttc'];
        } else {
            $mont_fg = $ht_fg;
        }
        if (($fg['statut'] ?? '') === 'payee') {
            $kpi_arch_total_paye += $mont_fg;
        } else {
            $kpi_arch_total_impaye += $mont_fg;
        }
    }
}

$nb_clients = count($groupes_all);
$nb_bl_total = 0;
foreach ($groupes_all as $g) {
    $nb_bl_total += count($g['bls'] ?? []);
}

$vue_liste = $client_filter <= 0;
$mois_fr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des factures (B2B) — Comptabilité</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Liste des factures (B2B)</h1>
        </div>
        <div class="header-actions bl-page-header__actions">
            <?php if (!$vue_liste): ?>
                <a href="bl-factures-archives.php" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour</a>
            <?php else: ?>
                <a href="index.php?tab=bl" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="content-section bl-detail-page">
        <div class="bl-tab-surface" style="margin-bottom:20px;">
            <p class="form-hint" style="margin:0;">
                <?php if ($vue_liste): ?>
                    <strong><?php echo (int) $nb_clients; ?></strong> dossier<?php echo $nb_clients > 1 ? 's' : ''; ?> client ·
                    <strong><?php echo (int) $nb_bl_total; ?></strong> ligne<?php echo $nb_bl_total > 1 ? 's' : ''; ?> de BL sur facture<?php echo $nb_bl_total > 1 ? 's' : ''; ?> (tous statuts)
                <?php else: ?>
                    <strong><?php echo (int) $kpi_arch_nb_fm; ?></strong> facture<?php echo $kpi_arch_nb_fm > 1 ? 's' : ''; ?> ·
                    <strong><?php echo (int) $kpi_arch_nb_bl; ?></strong> BL ·
                    <?php echo $arch_type === 'ttc' ? 'Factures TTC' : 'Notes de prix HT'; ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($vue_liste): ?>
            <?php if (empty($groupes_all)): ?>
                <div class="bl-empty-state bl-empty-state--compact" role="status">
                    <div class="bl-empty-state__visual" aria-hidden="true">
                        <span class="bl-empty-state__ring"></span>
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3 class="bl-empty-state__title">Aucune facture pour l’instant</h3>
                    <p class="bl-empty-state__text">Générez une facture depuis la <strong>fiche client</strong> : elle apparaîtra ici dès qu’elle contient des BL (y compris en brouillon).</p>
                    <a href="index.php?tab=bl" class="btn-primary bl-empty-state__btn"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour</a>
                </div>
            <?php else: ?>
                <div class="bl-tab-surface compta-bl-tab-surface">
                    <header class="bl-contacts-hero">
                        <div class="bl-contacts-hero__icon-wrap" aria-hidden="true">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="bl-contacts-hero__copy">
                            <h2 id="bl-arch-dossiers-title" class="bl-contacts-hero__title">Dossiers clients — factures</h2>
                        </div>
                        <div class="bl-contacts-hero__stat">
                            <span class="bl-contacts-hero__stat-num"><?php echo (int) $nb_clients; ?></span>
                            <span class="bl-contacts-hero__stat-label">client<?php echo $nb_clients > 1 ? 's' : ''; ?></span>
                        </div>
                    </header>
                    <div class="bl-arch-dossiers-table-wrap">
                        <table class="bl-arch-dossiers-table" aria-labelledby="bl-arch-dossiers-title">
                            <thead>
                                <tr>
                                    <th scope="col" class="bl-arch-dossiers-table__th"><span class="bl-arch-dossiers-table__th-in"><i class="fas fa-building" aria-hidden="true"></i> Client</span></th>
                                    <th scope="col" class="bl-arch-dossiers-table__th"><span class="bl-arch-dossiers-table__th-in"><i class="fas fa-phone-alt" aria-hidden="true"></i> Téléphone</span></th>
                                    <th scope="col" class="bl-arch-dossiers-table__th"><span class="bl-arch-dossiers-table__th-in"><i class="fas fa-envelope" aria-hidden="true"></i> Email</span></th>
                                    <th scope="col" class="bl-arch-dossiers-table__th"><span class="bl-arch-dossiers-table__th-in"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Adresse</span></th>
                                    <th scope="col" class="bl-arch-dossiers-table__th bl-arch-dossiers-table__th--num"><span class="bl-arch-dossiers-table__th-in"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Facturés</span></th>
                                    <th scope="col" class="bl-arch-dossiers-table__th"><span class="bl-arch-dossiers-table__th-in"><i class="fas fa-clock" aria-hidden="true"></i> Dernier BL</span></th>
                                    <th scope="col" class="bl-arch-dossiers-table__th bl-arch-dossiers-table__th--actions"><span class="bl-arch-dossiers-table__th-in"><i class="fas fa-folder-open" aria-hidden="true"></i> Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php foreach ($groupes_all as $g):
                        $cl = $g['client'];
                        $bls_g = $g['bls'] ?? [];
                        $nb_g = count($bls_g);
                        $cid = (int) ($cl['id'] ?? 0);
                        $rs_c = trim((string) ($cl['raison_sociale'] ?? ''));
                        $adr_short_c = '';
                        if (!empty($cl['adresse'])) {
                            $adr_short_c = mb_substr((string) $cl['adresse'], 0, 110);
                            if (mb_strlen((string) $cl['adresse'], 'UTF-8') > 110) {
                                $adr_short_c .= '…';
                            }
                        }
                        $tel_raw = trim((string) ($cl['telephone'] ?? ''));
                        $email_raw = trim((string) ($cl['email'] ?? ''));
                        $last_arch = '';
                        $ts_last = 0;
                        foreach ($bls_g as $_bg) {
                            $dc = !empty($_bg['date_creation']) ? strtotime((string) $_bg['date_creation']) : 0;
                            if ($dc > $ts_last) {
                                $ts_last = $dc;
                            }
                        }
                        if ($ts_last > 0) {
                            $last_arch = date('d/m/Y · H:i', $ts_last);
                        } else {
                            $last_arch = '—';
                        }
                        ?>
                                <tr class="bl-arch-dossiers-table__row">
                                    <td class="bl-arch-dossiers-table__td bl-arch-dossiers-table__td--client">
                                        <span class="bl-arch-dossiers-table__company"><?php echo htmlspecialchars($rs_c ?: '—'); ?></span>
                                    </td>
                                    <td class="bl-arch-dossiers-table__td bl-arch-dossiers-table__td--meta">
                                        <?php if ($tel_raw !== ''): ?>
                                            <a class="bl-arch-dossiers-table__link" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $tel_raw)); ?>"><?php echo htmlspecialchars($tel_raw); ?></a>
                                        <?php else: ?>
                                            <span class="bl-arch-dossiers-table__empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="bl-arch-dossiers-table__td bl-arch-dossiers-table__td--meta">
                                        <?php if ($email_raw !== ''): ?>
                                            <a class="bl-arch-dossiers-table__link" href="mailto:<?php echo htmlspecialchars($email_raw); ?>"><?php echo htmlspecialchars($email_raw); ?></a>
                                        <?php else: ?>
                                            <span class="bl-arch-dossiers-table__empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="bl-arch-dossiers-table__td bl-arch-dossiers-table__td--addr">
                                        <?php if ($adr_short_c !== ''): ?>
                                            <span class="bl-arch-dossiers-table__addr"><?php echo htmlspecialchars($adr_short_c); ?></span>
                                        <?php else: ?>
                                            <span class="bl-arch-dossiers-table__empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="bl-arch-dossiers-table__td bl-arch-dossiers-table__td--count">
                                        <span class="bl-arch-dossiers-table__pill" title="BL liés à une facture mensuelle (tout statut)"><?php echo (int) $nb_g; ?></span>
                                    </td>
                                    <td class="bl-arch-dossiers-table__td"><?php echo htmlspecialchars($last_arch); ?></td>
                                    <td class="bl-arch-dossiers-table__td bl-arch-dossiers-table__td--cta">
                                        <a href="bl-factures-archives.php?client=<?php echo $cid; ?>" class="bl-arch-dossiers-table__cta" aria-label="Ouvrir les archives de ce client"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Archives</a>
                                    </td>
                                </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <?php if (!$detail_client): ?>
                <div class="message error" role="alert" style="max-width:720px;margin:0 auto;">
                    <i class="fas fa-exclamation-circle"></i> Aucun dossier archives pour ce client (ou client introuvable).
                    <a href="bl-factures-archives.php">Retour à la liste</a>
                </div>
            <?php else:
                $raison = trim((string) ($detail_client['raison_sociale'] ?? ''));
                $contact_nom = trim(($detail_client['nom_contact'] ?? '') . ' ' . ($detail_client['prenom_contact'] ?? ''));
                $cid = (int) ($detail_client['id'] ?? 0);
                $initials = '?';
                if ($raison !== '') {
                    $words = preg_split('/\s+/u', $raison, -1, PREG_SPLIT_NO_EMPTY);
                    if (count($words) >= 2) {
                        $initials = mb_strtoupper(
                            mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1),
                            'UTF-8'
                        );
                    } else {
                        $initials = mb_strtoupper(mb_substr($raison, 0, min(2, mb_strlen($raison, 'UTF-8')), 'UTF-8'), 'UTF-8');
                    }
                }
                ?>
                <div class="bl-tab-surface" style="margin-bottom:28px;">
                    <header class="bl-client-banner" aria-labelledby="bl-arch-banner-detail">
                        <div class="bl-client-banner__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="bl-client-banner__body">
                            <h2 id="bl-arch-banner-detail" class="bl-client-banner__title"><?php echo htmlspecialchars($raison ?: '—'); ?></h2>
                            <?php if ($contact_nom !== ''): ?>
                                <p class="bl-client-banner__contact">
                                    <i class="fas fa-user-tie" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($contact_nom); ?>
                                </p>
                            <?php endif; ?>
                            <ul class="bl-client-banner__meta">
                                <li>
                                    <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                                    <span><?php echo htmlspecialchars($detail_client['telephone'] ?? '—'); ?></span>
                                </li>
                                <li>
                                    <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                    <span><?php echo !empty($detail_client['email']) ? htmlspecialchars((string) $detail_client['email']) : '—'; ?></span>
                                </li>
                                <?php if (!empty($detail_client['adresse'])): ?>
                                <li class="bl-client-banner__meta--full">
                                    <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                                    <span><?php echo nl2br(htmlspecialchars((string) $detail_client['adresse'])); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="bl-client-banner__stat">
                            <span class="bl-client-banner__stat-num"><?php echo (int) $kpi_arch_nb_fm; ?></span>
                            <span class="bl-client-banner__stat-label"><?php echo $arch_type === 'ttc' ? 'factures TTC' : 'notes HT'; ?></span>
                        </div>
                    </header>

                    <div class="compta-bl-kpis bl-arch-kpis" role="list">
                        <div class="compta-bl-kpi" role="listitem">
                            <div class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-file-invoice"></i></div>
                            <div class="compta-bl-kpi__body">
                                <span class="compta-bl-kpi__label">Factures</span>
                                <span class="compta-bl-kpi__value"><?php echo (int) $kpi_arch_nb_fm; ?></span>
                            </div>
                        </div>
                        <div class="compta-bl-kpi" role="listitem">
                            <div class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-truck"></i></div>
                            <div class="compta-bl-kpi__body">
                                <span class="compta-bl-kpi__label">Bons de livraison</span>
                                <span class="compta-bl-kpi__value"><?php echo (int) $kpi_arch_nb_bl; ?></span>
                            </div>
                        </div>
                        <div class="compta-bl-kpi" role="listitem">
                            <div class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-check-circle"></i></div>
                            <div class="compta-bl-kpi__body">
                                <span class="compta-bl-kpi__label">Total factures payées</span>
                                <span class="compta-bl-kpi__value"><?php echo number_format($kpi_arch_total_paye, 0, ',', ' '); ?> <small>FCFA</small></span>
                            </div>
                        </div>
                        <div class="compta-bl-kpi" role="listitem">
                            <div class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-clock"></i></div>
                            <div class="compta-bl-kpi__body">
                                <span class="compta-bl-kpi__label">Total factures impayées</span>
                                <span class="compta-bl-kpi__value"><?php echo number_format($kpi_arch_total_impaye, 0, ',', ' '); ?> <small>FCFA</small></span>
                            </div>
                        </div>
                    </div>

                    <nav class="admin-devis-bl-tabs bl-arch-type-tabs" aria-label="Type de factures" style="margin-bottom:20px;">
                        <a href="bl-factures-archives.php?client=<?php echo (int) $cid; ?>&amp;type=ht"
                            class="admin-tab<?php echo $arch_type === 'ht' ? ' is-active' : ''; ?>">
                            <i class="fas fa-file-alt" aria-hidden="true"></i> Note de prix HT
                        </a>
                        <a href="bl-factures-archives.php?client=<?php echo (int) $cid; ?>&amp;type=ttc"
                            class="admin-tab<?php echo $arch_type === 'ttc' ? ' is-active' : ''; ?>">
                            <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Facture TTC
                        </a>
                    </nav>

                    <div class="bl-list-section" style="padding-top:0;">
                        <h2 class="bl-list-section__title" id="bl-arch-factures-list">
                            <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
                            <?php echo $arch_type === 'ttc' ? 'Factures TTC du client' : 'Notes de prix HT du client'; ?>
                        </h2>
                        <?php if (empty($factures_groupees)): ?>
                            <p class="form-hint" role="status">Aucune <?php echo $arch_type === 'ttc' ? 'facture TTC' : 'note de prix HT'; ?> avec BL pour ce dossier.</p>
                        <?php else: ?>
                        <div class="bl-fm-archive-table-wrap">
                            <table class="data-table bl-fm-archive-table" aria-labelledby="bl-arch-factures-list">
                                <thead>
                                    <tr>
                                        <th scope="col">N° facture</th>
                                        <th scope="col">Période</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col" class="bl-fm-archive-table__col-num">BL</th>
                                        <th scope="col" class="bl-fm-archive-table__col-num"><?php echo $arch_type === 'ttc' ? 'Total TTC' : 'Total HT'; ?></th>
                                        <th scope="col" class="bl-fm-archive-table__col-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($factures_groupees as $fg):
                                    $fid = (int) ($fg['id'] ?? 0);
                                    $fnum = (string) ($fg['numero'] ?? '');
                                    $fst = (string) ($fg['statut'] ?? '');
                                    $fm_m = (int) ($fg['mois'] ?? 0);
                                    $fm_a = (int) ($fg['annee'] ?? 0);
                                    $per_fg = ($fm_m >= 1 && $fm_m <= 12 && $fm_a > 0)
                                        ? ($mois_fr[$fm_m] . ' ' . $fm_a)
                                        : '—';
                                    $fst_label = ($fst === 'payee') ? 'Payé' : 'Impayé';
                                    $fst_slug = ($fst === 'payee') ? 'paye' : 'impaye';
                                    $nb_bl_fg = (int) ($fg['nb_bl'] ?? 0);
                                    $ht_fg = (float) ($fg['total_ht'] ?? $fg['somme_bl_ht'] ?? 0);
                                    if (!empty($fg['tva_incluse'])) {
                                        $sum_fg = fiscal_decomposer_net_ht($ht_fg, true)['montant_ttc'];
                                    } else {
                                        $sum_fg = $ht_fg;
                                    }
                                    $ref_facture = $fnum !== '' ? $fnum : ('#' . $fid);
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ref_facture); ?></strong></td>
                                        <td><?php echo htmlspecialchars($per_fg); ?></td>
                                        <td>
                                            <span class="commande-statut statut-<?php echo htmlspecialchars($fst_slug); ?>"><?php echo htmlspecialchars($fst_label); ?></span>
                                        </td>
                                        <td class="bl-fm-archive-table__col-num"><?php echo (int) $nb_bl_fg; ?></td>
                                        <td class="bl-fm-archive-table__col-num"><?php echo number_format($sum_fg, 0, ',', ' '); ?> FCFA</td>
                                        <td class="bl-fm-archive-table__actions">
                                            <a href="../devis/facture_mensuelle.php?id=<?php echo $fid; ?>" class="bl-fm-archive-table__link"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Facture</a>
                                            <a href="bl-factures-archives-fm-bls.php?client=<?php echo (int) $cid; ?>&amp;fm=<?php echo $fid; ?>" class="bl-fm-archive-table__link bl-fm-archive-table__link--secondary"><i class="fas fa-truck-loading" aria-hidden="true"></i> BL</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
