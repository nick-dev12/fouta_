<?php
/**
 * Affichage / impression d’un bulletin de paie — mise en page A4
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh', 'informaticien', 'developpeur'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../models/model_bulletin_paie.php';
require_once __DIR__ . '/../../../models/model_employes.php';
require_once __DIR__ . '/../../../includes/asset_version.php';
require_once __DIR__ . '/../../../includes/site_url.php';

$bp_logo_url = get_site_logo_url_for_current_request();

$bid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bid <= 0) {
    header('Location: index.php');
    exit;
}

$brow = bp_get_bulletin_by_id($bid);
if (!$brow) {
    header('Location: index.php');
    exit;
}

$employe_id = (int) ($brow['employe_id'] ?? 0);
$emp = get_employe_by_id($employe_id);

$snap = json_decode((string) ($brow['snapshot_json'] ?? ''), true);
if (!is_array($snap)) {
    $snap = [];
}

function bp_voir_fmt_cell($n) {
    return number_format((float) $n, 0, ',', ' ');
}

$e = $snap['employeur'] ?? [];
$em = $snap['employe'] ?? [];
$p = $snap['periode'] ?? [];
$rub_cfg = bp_merge_rubriques(isset($snap['rubriques_config']) && is_array($snap['rubriques_config']) ? $snap['rubriques_config'] : null);

$gains_all = isset($snap['gains']) && is_array($snap['gains']) ? $snap['gains'] : [];
$gains = [];
foreach ($gains_all as $row) {
    $code = (string) ($row['code'] ?? '');
    if ($code === 'salaire_base' || !empty($rub_cfg['gains'][$code])) {
        $gains[] = $row;
    }
}

$retenues_all = isset($snap['retenues']) && is_array($snap['retenues']) ? $snap['retenues'] : [];
$retenues = [];
foreach ($retenues_all as $row) {
    $code = (string) ($row['code'] ?? '');
    $ret_active = !empty($rub_cfg['retenues'][$code]);
    if (!$ret_active && $code === 'ipres') {
        $ret_active = true;
    }
    if ($code === 'penalites_absence' || $ret_active) {
        $retenues[] = $row;
    }
}

$travail_in = isset($snap['travail']) && is_array($snap['travail']) ? $snap['travail'] : [];
$travail = [];
$trw = $rub_cfg['travail'] ?? [];
if (!empty($trw['heures_travaillees']) && array_key_exists('heures_travaillees', $travail_in)) {
    $travail['heures_travaillees'] = $travail_in['heures_travaillees'];
}
if (!empty($trw['heures_sup']) && array_key_exists('heures_sup_nombre', $travail_in)) {
    $travail['heures_sup_nombre'] = $travail_in['heures_sup_nombre'];
}
if (!empty($trw['jours_presence'])) {
    foreach (['jours_presence_reference', 'jours_presence', 'jours_absence_retenus'] as $tk) {
        if (array_key_exists($tk, $travail_in)) {
            $travail[$tk] = $travail_in[$tk];
        }
    }
}
if (!empty($trw['conges']) && array_key_exists('conges_jours', $travail_in)) {
    $travail['conges_jours'] = $travail_in['conges_jours'];
}

$t = $snap['totaux'] ?? [];
$men = isset($snap['mentions']) && is_array($snap['mentions']) ? $snap['mentions'] : [];

$men_show_date = !empty($rub_cfg['mentions']['date_paiement']);
$men_show_mode = !empty($rub_cfg['mentions']['mode_paiement']);
$men_show_sign = !empty($rub_cfg['mentions']['signature']) && !empty($men['afficher_signature']);
$men_section_visible = $men_show_date || $men_show_mode || $men_show_sign;

$dt_paiement = '';
if (!empty($p['date_paiement'])) {
    $dt_paiement = date('d/m/Y', strtotime((string) $p['date_paiement']));
} elseif (!empty($brow['date_paiement'])) {
    $dt_paiement = date('d/m/Y', strtotime((string) $brow['date_paiement']));
}

$somme_gains = 0.0;
foreach ($gains as $gr) {
    $somme_gains += (float) ($gr['montant'] ?? 0);
}

$bp_sn = 0;
$titre_page = 'Bulletin de paie — ' . trim(($em['prenom'] ?? '') . ' ' . ($em['nom'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titre_page); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-bulletin-paie.css<?php echo asset_version_query(); ?>">
</head>
<body class="bp-a4-page">
    <header class="bp-toolbar no-print" role="toolbar">
        <div class="bp-toolbar__inner">
            <a href="details.php?id=<?php echo (int) $employe_id; ?>&tab=bp" class="bp-toolbar__back">
                <span class="bp-toolbar__back-ic" aria-hidden="true">←</span>
                <span>Retour fiche employé</span>
            </a>
            <div class="bp-toolbar__actions">
                <button type="button" class="bp-toolbar__btn bp-toolbar__btn--primary" id="bpBtnPrint" title="Imprimer en A4">
                    <i class="fas fa-print" aria-hidden="true"></i>
                    Imprimer (A4)
                </button>
            </div>
        </div>
    </header>

    <div class="bp-canvas no-print-bg">
        <article class="bp-sheet" aria-label="Bulletin de paie">
            <header class="bp-sheet__top">
                <div class="bp-sheet__lead">
                    <div class="bp-sheet__title-block">
                        <h1 class="bp-sheet__title">Bulletin de paie</h1>
                    </div>
                </div>
                <dl class="bp-sheet__refs">
                    <div><dt>Réf.</dt><dd>#<?php echo (int) $bid; ?></dd></div>
                    <div><dt>Période</dt><dd><?php echo htmlspecialchars((string) ($p['mois_label'] ?? $brow['mois_paie'] ?? '—')); ?></dd></div>
                    <div><dt>Paiement</dt><dd><?php echo htmlspecialchars($dt_paiement !== '' ? $dt_paiement : '—'); ?></dd></div>
                </dl>
            </header>

            <section class="bp-block bp-ident" aria-labelledby="bp-sec-ident">
                <div class="bp-block__head" id="bp-sec-ident">
                    <span class="bp-block__letter" aria-hidden="true"><?php echo ++$bp_sn; ?></span>
                    <h2 class="bp-block__title">Identité employeur &amp; salarié</h2>
                </div>
                <div class="bp-ident__hero<?php echo $bp_logo_url === '' ? ' bp-ident__hero--full' : ''; ?>">
                    <?php if ($bp_logo_url !== '') : ?>
                    <div class="bp-ident__logo-box">
                        <img class="bp-ident__logo" src="<?php echo htmlspecialchars($bp_logo_url); ?>"
                            alt="<?php echo htmlspecialchars('Logo ' . (string) ($e['nom'] ?? 'entreprise')); ?>"
                            width="280" height="112" decoding="async">
                    </div>
                    <?php endif; ?>
                    <div class="bp-ident__employeur">
                        <p class="bp-ident__kicker">Employeur</p>
                        <p class="bp-ident__rs"><?php echo htmlspecialchars((string) ($e['nom'] ?? '—')); ?></p>
                        <div class="bp-ident__addr"><?php echo nl2br(htmlspecialchars((string) ($e['adresse'] ?? '—'))); ?></div>
                        <dl class="bp-ident__regs">
                            <div><dt>NINEA</dt><dd><?php echo htmlspecialchars((string) ($e['ninea'] ?? '—')); ?></dd></div>
                            <div><dt>R.C.</dt><dd><?php echo htmlspecialchars((string) ($e['rc'] ?? '—')); ?></dd></div>
                            <div><dt>Réf. CNSS</dt><dd><?php echo htmlspecialchars((string) ($e['cnss_ref'] ?? '—')); ?></dd></div>
                        </dl>
                    </div>
                </div>
                <div class="bp-ident__salarié">
                    <p class="bp-ident__kicker">Salarié(e)</p>
                    <div class="bp-ident__sal-grid">
                        <div class="bp-ident__sal-cell bp-ident__sal-cell--name">
                            <span class="bp-ident__sal-lab">Nom &amp; prénom</span>
                            <span class="bp-ident__sal-val"><?php echo htmlspecialchars(trim(($em['prenom'] ?? '') . ' ' . ($em['nom'] ?? ''))); ?></span>
                        </div>
                        <div class="bp-ident__sal-cell">
                            <span class="bp-ident__sal-lab">Poste</span>
                            <span class="bp-ident__sal-val"><?php echo htmlspecialchars((string) ($em['poste'] ?? '—')); ?></span>
                        </div>
                        <div class="bp-ident__sal-cell">
                            <span class="bp-ident__sal-lab">Matricule</span>
                            <span class="bp-ident__sal-val"><?php echo htmlspecialchars((string) ($em['matricule'] ?? '—')); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (!empty($gains)) : ?>
            <section class="bp-block" aria-labelledby="bp-sec-gains">
                <div class="bp-block__head" id="bp-sec-gains">
                    <span class="bp-block__letter" aria-hidden="true"><?php echo ++$bp_sn; ?></span>
                    <h2 class="bp-block__title">Éléments de rémunération <span class="bp-block__hint">(brut)</span></h2>
                </div>
                <div class="bp-table-shell">
                    <table class="bp-data-table">
                        <thead>
                            <tr>
                                <th scope="col">Libellé</th>
                                <th scope="col" class="bp-col-num">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gains as $row) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($row['label'] ?? '')); ?></td>
                                    <td class="bp-col-num"><?php echo bp_voir_fmt_cell($row['montant'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">Total gains</th>
                                <td class="bp-col-num"><?php echo bp_voir_fmt_cell($somme_gains); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($retenues)) : ?>
            <section class="bp-block" aria-labelledby="bp-sec-ret">
                <div class="bp-block__head" id="bp-sec-ret">
                    <span class="bp-block__letter" aria-hidden="true"><?php echo ++$bp_sn; ?></span>
                    <h2 class="bp-block__title">Cotisations &amp; retenues</h2>
                </div>
                <div class="bp-table-shell">
                    <table class="bp-data-table">
                        <thead>
                            <tr>
                                <th scope="col">Libellé</th>
                                <th scope="col" class="bp-col-pct">Taux</th>
                                <th scope="col" class="bp-col-num">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($retenues as $row) : ?>
                                <?php
                                $ret_code = (string) ($row['code'] ?? '');
                                $pct = $row['pourcent'] ?? null;
                                $pct_txt = '—';
                                if ($ret_code !== 'irpp' && $ret_code !== 'ipres' && $pct !== null && $pct !== '' && is_numeric($pct)) {
                                    $pct_txt = number_format((float) $pct, 2, ',', ' ') . ' %';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($row['label'] ?? '')); ?></td>
                                    <td class="bp-col-pct"><?php echo htmlspecialchars($pct_txt); ?></td>
                                    <td class="bp-col-num"><?php echo bp_voir_fmt_cell($row['montant'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <section class="bp-block bp-block--synth" aria-labelledby="bp-sec-synth">
                <div class="bp-block__head" id="bp-sec-synth">
                    <span class="bp-block__letter" aria-hidden="true"><?php echo ++$bp_sn; ?></span>
                    <h2 class="bp-block__title">Synthèse de paie</h2>
                </div>
                <div class="bp-synth">
                    <table class="bp-synth-table">
                        <tbody>
                            <tr>
                                <th scope="row">Salaire brut</th>
                                <td><?php echo bp_voir_fmt_cell($t['montant_brut'] ?? $brow['montant_brut'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Total des retenues</th>
                                <td class="bp-synth-table__deduct"><?php echo bp_voir_fmt_cell($t['total_retenues'] ?? $brow['total_retenues'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Net imposable</th>
                                <td><?php echo bp_voir_fmt_cell($t['net_imposable'] ?? $brow['net_imposable'] ?? 0); ?></td>
                            </tr>
                            <tr class="bp-synth-table__net-row">
                                <th scope="row">Net à payer</th>
                                <td><?php echo bp_voir_fmt_cell($t['net_a_payer'] ?? $brow['net_a_payer'] ?? 0); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if (!empty($travail)) : ?>
            <section class="bp-block" aria-labelledby="bp-sec-time">
                <div class="bp-block__head" id="bp-sec-time">
                    <span class="bp-block__letter" aria-hidden="true"><?php echo ++$bp_sn; ?></span>
                    <h2 class="bp-block__title">Temps de travail</h2>
                </div>
                <ul class="bp-inline-kv">
                    <?php if (isset($travail['heures_travaillees'])) : ?>
                        <li><span class="bp-inline-kv__k">H. travaillées</span><span class="bp-inline-kv__v"><?php echo htmlspecialchars((string) $travail['heures_travaillees']); ?></span></li>
                    <?php endif; ?>
                    <?php if (isset($travail['heures_sup_nombre'])) : ?>
                        <li><span class="bp-inline-kv__k">H. sup.</span><span class="bp-inline-kv__v"><?php echo htmlspecialchars((string) $travail['heures_sup_nombre']); ?></span></li>
                    <?php endif; ?>
                    <?php if (array_key_exists('jours_presence_reference', $travail)) : ?>
                        <li><span class="bp-inline-kv__k">J. référence (tous salariés)</span><span class="bp-inline-kv__v"><?php echo htmlspecialchars((string) $travail['jours_presence_reference']); ?></span></li>
                    <?php endif; ?>
                    <?php if (isset($travail['jours_presence'])) : ?>
                        <li><span class="bp-inline-kv__k">J. présence (après absences)</span><span class="bp-inline-kv__v"><?php echo htmlspecialchars((string) $travail['jours_presence']); ?></span></li>
                    <?php endif; ?>
                    <?php if (isset($travail['jours_absence_retenus']) && (int) $travail['jours_absence_retenus'] > 0) : ?>
                        <li><span class="bp-inline-kv__k">J. absence (retenus sur salaire)</span><span class="bp-inline-kv__v"><?php echo htmlspecialchars((string) $travail['jours_absence_retenus']); ?></span></li>
                    <?php endif; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php if ($men_section_visible) : ?>
            <section class="bp-block bp-block--last" aria-labelledby="bp-sec-mentions">
                <div class="bp-block__head" id="bp-sec-mentions">
                    <span class="bp-block__letter" aria-hidden="true"><?php echo ++$bp_sn; ?></span>
                    <h2 class="bp-block__title">Paiement &amp; mentions</h2>
                </div>
                <div class="bp-foot-grid<?php echo (($men_show_date || $men_show_mode) && $men_show_sign) ? '' : ' bp-foot-grid--stack'; ?>">
                    <?php if ($men_show_date || $men_show_mode) : ?>
                    <table class="bp-kv-table bp-kv-table--compact">
                        <tbody>
                            <?php if ($men_show_date) : ?>
                            <tr><th scope="row">Date de paiement</th><td><?php echo !empty($men['date_paiement']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $men['date_paiement']))) : '—'; ?></td></tr>
                            <?php endif; ?>
                            <?php if ($men_show_mode) : ?>
                            <tr><th scope="row">Mode de paiement</th><td><?php
                                $mop = trim((string) ($men['mode_paiement'] ?? ''));
                                echo htmlspecialchars($mop !== '' ? $mop : '—');
                            ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <?php if ($men_show_sign) : ?>
                    <div class="bp-sign">
                        <p class="bp-sign__lab">Signature et cachet employeur</p>
                        <div class="bp-sign__line"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <footer class="bp-meta no-print">
                <?php if ($emp) : ?>
                    <?php
                    $mat_footer = trim((string) ($em['matricule'] ?? ''));
                    if ($mat_footer === '') {
                        $mat_footer = trim((string) ($emp['matricule'] ?? ''));
                    }
                    if ($mat_footer === '') {
                        $mat_footer = '—';
                    }
                    ?>
                    <p>Bulletin n° <?php echo (int) $bid; ?> — Matricule <?php echo htmlspecialchars($mat_footer); ?> — Généré depuis l’administration.</p>
                <?php endif; ?>
            </footer>
        </article>
    </div>

    <script>
    (function () {
      var b = document.getElementById('bpBtnPrint');
      if (b) b.addEventListener('click', function () { window.print(); });
    })();
    </script>
</body>
</html>
