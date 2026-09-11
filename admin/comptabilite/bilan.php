<?php
/**
 * Bilan comptable — synthèse multi-postes + export CSV (filtre jour / mois / période)
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

require_once __DIR__ . '/../../models/model_bilan_comptable.php';
require_once __DIR__ . '/../../models/model_caisse_compta.php';

$periode = bilan_comptable_parse_periode($_GET);
$d1 = $periode['date_debut'];
$d2 = $periode['date_fin'];

$data = bilan_comptable_collecter_donnees($d1, $d2, 400);

/* LA SYNTHÈSE COMPTABLE UNIQUE (point 16, 11/09/2026). Les totaux ne viennent
 * plus des listes : le bilan additionnait au plus 400 tickets, posait côte à
 * côte les bons et les factures du mois qui les contiennent, et ne déduisait
 * aucun retour. Un seul calcul : models/model_compta_synthese.php. Les listes
 * plus bas restent des aperçus. */
require_once __DIR__ . '/../../models/model_compta_synthese.php';
$synthese = null;
try {
    $synthese = compta_synthese_periode($d1, $d2);
} catch (Throwable $e) {
    error_log('[comptabilite/bilan synthese] ' . $e->getMessage());
}
$fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ');
};
$signe = static function ($n) {
    $n = round((float) $n);
    return ($n < 0 ? '− ' : ($n > 0 ? '+ ' : '')) . number_format(abs($n), 0, ',', ' ');
};

$export_q = ['b_periode' => $periode['type']];
if ($periode['type'] === 'jour') {
    $export_q['b_date_jour'] = $periode['b_date_jour'];
} elseif ($periode['type'] === 'mois') {
    $export_q['b_annee_mois'] = $periode['annee'];
    $export_q['b_mois'] = $periode['mois'];
} else {
    $export_q['b_date_debut'] = $periode['date_debut'];
    $export_q['b_date_fin'] = $periode['date_fin'];
}
$export_url = 'bilan-export-csv.php?' . http_build_query($export_q);

$b_type = $periode['type'];
$mois_labels = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan comptable — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('compta-bilan.css'); ?>
    <style>
        /* En pixels : la comptabilité hérite encore d'une base de 10 px (refonte e76948f), où 1rem ne fait que 10 px. */
        .bilan-synthese { margin-bottom: 24px; }
        .bilan-synthese__wrap { overflow-x: auto; }
        .bilan-synthese__table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; font-size: 14px; line-height: 1.45; }
        .bilan-synthese__table th, .bilan-synthese__table td { padding: 8px 10px; border-bottom: 1px solid rgba(16, 49, 111, 0.12); text-align: left; vertical-align: top; }
        .bilan-synthese__table thead th { font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em; color: #56617A; white-space: nowrap; }
        .bilan-synthese__table .num { text-align: right; white-space: nowrap; }
        .bilan-synthese__table td small { display: block; margin-top: 2px; color: #56617A; font-size: 12px; line-height: 1.4; }
        .bilan-synthese__table td:first-child { min-width: 190px; }
        .bilan-synthese__table tfoot th { border-bottom: 0; border-top: 2px solid #10316F; color: #10316F; }
        .bilan-synthese__groupe td { font-weight: 600; color: #10316F; background: rgba(16, 49, 111, 0.05); }
        .bilan-synthese__note { margin: 12px 0 0; color: #56617A; font-size: 13px; line-height: 1.5; max-width: 80ch; }
        .bilan-synthese h3 { margin: 20px 0 8px; font-size: 14px; color: #10316F; }
    </style>
</head>
<body class="page-compta-bilan">
    <?php include '../includes/nav.php'; ?>

    <div class="page-compta-admin bilan-wrap">
        <header class="bilan-hero">
            <div class="bilan-hero__grid">
                <div class="bilan-hero__text">
                    <p class="bilan-hero__eyebrow"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Synthèse financière</p>
                    <h1>Bilan comptable</h1>
                    <p class="bilan-hero__lead">Ce qui a été vendu, encaissé et dépensé sur la période, par les quatre chemins de vente, retours déduits, et ce que les clients doivent encore. Le CSV suit les mêmes règles.</p>
                </div>
                <div class="bilan-hero__badge" aria-label="Période sélectionnée">
                    <span class="bilan-hero__badge-label">Période</span>
                    <span class="bilan-hero__badge-value"><?php echo htmlspecialchars($periode['libelle']); ?></span>
                    <span class="bilan-hero__badge-dates"><?php echo htmlspecialchars($d1); ?> → <?php echo htmlspecialchars($d2); ?></span>
                </div>
            </div>
            <div class="bilan-hero__actions">
                <a href="index.php" class="bilan-btn bilan-btn--ghost"><i class="fas fa-arrow-left" aria-hidden="true"></i> Hub comptabilité</a>
                <a href="<?php echo htmlspecialchars($export_url, ENT_QUOTES, 'UTF-8'); ?>" class="bilan-btn bilan-btn--export"><i class="fas fa-file-csv" aria-hidden="true"></i> Télécharger le CSV</a>
            </div>
        </header>

        <section class="bilan-filter-card" aria-labelledby="bilan-filtre-title">
            <h2 id="bilan-filtre-title" class="bilan-filter-card__title"><i class="fas fa-calendar-days" aria-hidden="true"></i> Filtrer par date</h2>
            <p class="bilan-filter-card__hint">Ventes : la caisse à la <strong>date d’encaissement</strong>, les factures de devis à leur <strong>date</strong>, les bons de livraison validés à la <strong>date du bon</strong>, le site à la <strong>date de commande</strong> ; un retour compte le <strong>jour où il est fait</strong>. Encaissements : <strong>date du paiement</strong>. Dépenses : <strong>date de dépense</strong>.</p>

            <form method="get" action="bilan.php" class="bilan-filter-form" id="bilan-filter-form">
                <div class="bilan-filter-form__mode-row">
                    <span class="bilan-filter-form__mode-label">Mode</span>
                    <div class="bilan-seg" role="group" aria-label="Type de période">
                        <label class="bilan-seg__item">
                            <input type="radio" name="b_periode" value="jour" <?php echo $b_type === 'jour' ? 'checked' : ''; ?>>
                            <span class="bilan-seg__face">Un jour</span>
                        </label>
                        <label class="bilan-seg__item">
                            <input type="radio" name="b_periode" value="mois" <?php echo $b_type === 'mois' ? 'checked' : ''; ?>>
                            <span class="bilan-seg__face">Un mois</span>
                        </label>
                        <label class="bilan-seg__item">
                            <input type="radio" name="b_periode" value="plage" <?php echo $b_type === 'plage' ? 'checked' : ''; ?>>
                            <span class="bilan-seg__face">Période (du … au …)</span>
                        </label>
                    </div>
                    <button type="submit" class="bilan-btn bilan-btn--primary"><i class="fas fa-rotate" aria-hidden="true"></i> Actualiser le bilan</button>
                </div>

                <div id="bilan-panel-jour" class="bilan-filter-panel <?php echo $b_type === 'jour' ? '' : 'is-hidden'; ?>">
                    <label for="b_date_jour" class="bilan-field-label">Jour</label>
                    <input type="date" name="b_date_jour" id="b_date_jour" class="bilan-input" value="<?php echo htmlspecialchars($periode['b_date_jour']); ?>">
                </div>

                <div id="bilan-panel-mois" class="bilan-filter-panel bilan-filter-panel--row <?php echo $b_type === 'mois' ? '' : 'is-hidden'; ?>">
                    <div class="bilan-field">
                        <label for="b_annee_mois" class="bilan-field-label">Année</label>
                        <select name="b_annee_mois" id="b_annee_mois" class="bilan-select">
                            <?php for ($ay = (int) date('Y'); $ay >= (int) date('Y') - 8; $ay--): ?>
                                <option value="<?php echo $ay; ?>" <?php echo $periode['annee'] === $ay ? 'selected' : ''; ?>><?php echo $ay; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="bilan-field bilan-field--grow">
                        <label for="b_mois" class="bilan-field-label">Mois</label>
                        <select name="b_mois" id="b_mois" class="bilan-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $periode['mois'] === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($mois_labels[$m]); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div id="bilan-panel-plage" class="bilan-filter-panel bilan-filter-panel--row <?php echo $b_type === 'plage' ? '' : 'is-hidden'; ?>">
                    <div class="bilan-field">
                        <label for="b_date_debut" class="bilan-field-label">Du</label>
                        <input type="date" name="b_date_debut" id="b_date_debut" class="bilan-input" value="<?php echo htmlspecialchars($periode['type'] === 'plage' ? $periode['b_date_debut'] : $d1); ?>">
                    </div>
                    <div class="bilan-field">
                        <label for="b_date_fin" class="bilan-field-label">Au</label>
                        <input type="date" name="b_date_fin" id="b_date_fin" class="bilan-input" value="<?php echo htmlspecialchars($periode['type'] === 'plage' ? $periode['b_date_fin'] : $d2); ?>">
                    </div>
                </div>
            </form>
        </section>

        <?php if (!$synthese): ?>
        <section class="bilan-note" role="alert">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <p>La synthèse de la période n’a pas pu être calculée. Rechargez la page ; si le message revient, prévenez l’informaticien.</p>
        </section>
        <?php else:
            $sv = $synthese['ventes'];
            $se = $synthese['encaissements'];
            $sa = $synthese['a_encaisser'];
            $sd = $synthese['depenses'];
        ?>
        <div class="bilan-kpi-grid" aria-label="Indicateurs du bilan">
            <article class="bilan-kpi bilan-kpi--caisse">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-cart-shopping"></i></div>
                <h3 class="bilan-kpi__title">Ventes nettes</h3>
                <p class="bilan-kpi__value"><?php echo $fcfa($sv['total']); ?> <span class="bilan-kpi__cur">FCFA</span></p>
                <p class="bilan-kpi__meta">Caisse, devis, bons et site, retours déduits</p>
            </article>
            <article class="bilan-kpi bilan-kpi--web">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-hand-holding-dollar"></i></div>
                <h3 class="bilan-kpi__title">Encaissé</h3>
                <p class="bilan-kpi__value"><?php echo $fcfa($se['total']); ?> <span class="bilan-kpi__cur">FCFA</span></p>
                <p class="bilan-kpi__meta">Argent reçu sur la période</p>
            </article>
            <article class="bilan-kpi bilan-kpi--dep">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-arrow-trend-down"></i></div>
                <h3 class="bilan-kpi__title">Dépenses</h3>
                <p class="bilan-kpi__value"><?php echo $fcfa($sd['montant']); ?> <span class="bilan-kpi__cur">FCFA</span></p>
                <p class="bilan-kpi__meta"><?php echo (int) $sd['nb']; ?> dépense(s) saisie(s)</p>
            </article>
            <article class="bilan-kpi bilan-kpi--fm">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-scale-balanced"></i></div>
                <h3 class="bilan-kpi__title">Encaissé moins dépenses</h3>
                <p class="bilan-kpi__value"><?php echo $synthese['solde'] < 0 ? '− ' . $fcfa(abs($synthese['solde'])) : $fcfa($synthese['solde']); ?> <span class="bilan-kpi__cur">FCFA</span></p>
                <p class="bilan-kpi__meta">Pas un bénéfice : le coût d’achat des pièces n’est pas compté</p>
            </article>
            <article class="bilan-kpi bilan-kpi--bl">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-hourglass-half"></i></div>
                <h3 class="bilan-kpi__title">À encaisser à ce jour</h3>
                <p class="bilan-kpi__value"><?php echo $fcfa($sa['total']); ?> <span class="bilan-kpi__cur">FCFA</span></p>
                <p class="bilan-kpi__meta">Avoirs à émettre : <?php echo $fcfa($sa['avoirs']['montant']); ?> FCFA</p>
            </article>
        </div>

        <section class="bilan-detail bilan-synthese" aria-labelledby="bilan-ventes-titre">
            <div class="bilan-detail__head">
                <h2 id="bilan-ventes-titre">Ventes de la période</h2>
            </div>
            <div class="bilan-synthese__wrap">
                <table class="bilan-synthese__table">
                    <thead>
                        <tr><th>Chemin de vente</th><th class="num">Documents</th><th class="num">Vendu</th><th class="num">Retours</th><th class="num">Net</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Caisse magasin<small>Tickets payés, à la date d’encaissement ; retours clients validés à leur date</small></td>
                            <td class="num"><?php echo (int) $sv['caisse']['nb']; ?> ticket(s)</td>
                            <td class="num"><?php echo $fcfa($sv['caisse']['brut']); ?></td>
                            <td class="num"><?php echo $sv['caisse']['retours_nb'] ? $signe($sv['caisse']['remis'] - $sv['caisse']['rendu']) : '—'; ?></td>
                            <td class="num"><?php echo $fcfa($sv['caisse']['net']); ?></td>
                        </tr>
                        <tr>
                            <td>Factures de devis<small>À la date de la facture</small></td>
                            <td class="num"><?php echo (int) $sv['devis']['nb']; ?> facture(s)</td>
                            <td class="num"><?php echo $fcfa($sv['devis']['net']); ?></td>
                            <td class="num">—</td>
                            <td class="num"><?php echo $fcfa($sv['devis']['net']); ?></td>
                        </tr>
                        <tr>
                            <td>Bons de livraison<small>Validés, à la date du bon ; bons de retour à leur date. Les factures du mois regroupent ces bons : elles ne s’ajoutent pas.</small></td>
                            <td class="num"><?php echo (int) $sv['bons']['nb']; ?> bon(s)</td>
                            <td class="num"><?php echo $fcfa($sv['bons']['brut']); ?></td>
                            <td class="num"><?php echo $sv['bons']['retours_nb'] ? $signe(-$sv['bons']['retours']) : '—'; ?></td>
                            <td class="num"><?php echo $fcfa($sv['bons']['net']); ?></td>
                        </tr>
                        <tr>
                            <td>Site<small>Commandes livrées ou payées, à la date de commande</small></td>
                            <td class="num"><?php echo (int) $sv['site']['nb']; ?> commande(s)</td>
                            <td class="num"><?php echo $fcfa($sv['site']['net']); ?></td>
                            <td class="num">—</td>
                            <td class="num"><?php echo $fcfa($sv['site']['net']); ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Ventes nettes</th>
                            <th></th>
                            <th class="num"><?php echo $fcfa($sv['caisse']['brut'] + $sv['devis']['net'] + $sv['bons']['brut'] + $sv['site']['net']); ?></th>
                            <th class="num"><?php echo $signe($sv['caisse']['remis'] - $sv['caisse']['rendu'] - $sv['bons']['retours']); ?></th>
                            <th class="num"><?php echo $fcfa($sv['total']); ?> FCFA</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="bilan-synthese__note">Pas comptés comme ventes : <?php echo (int) $sv['hors']['tickets_attente']['nb']; ?> ticket(s) en attente de caisse (<?php echo $fcfa($sv['hors']['tickets_attente']['montant']); ?> FCFA) et <?php echo (int) $sv['hors']['bons_brouillon']['nb']; ?> bon(s) de livraison en brouillon (<?php echo $fcfa($sv['hors']['bons_brouillon']['montant']); ?> FCFA). Factures du mois émises sur la période : <?php echo (int) $sv['hors']['factures_mois']['nb']; ?> (<?php echo $fcfa($sv['hors']['factures_mois']['montant']); ?> FCFA), déjà comptées dans leurs bons.</p>
        </section>

        <section class="bilan-detail bilan-synthese" aria-labelledby="bilan-encaisse-titre">
            <div class="bilan-detail__head">
                <h2 id="bilan-encaisse-titre">Encaissements de la période</h2>
            </div>
            <div class="bilan-synthese__wrap">
                <table class="bilan-synthese__table">
                    <thead><tr><th>Origine</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                        <tr class="bilan-synthese__groupe"><td colspan="2">Caisse magasin : <?php echo (int) $se['caisse']['nb']; ?> ticket(s)</td></tr>
                        <?php foreach ($se['caisse']['canaux'] as $canal => $montant): if ($montant < 0.5) { continue; } ?>
                        <tr><td><?php echo htmlspecialchars(compta_synthese_libelle_moyen($canal)); ?></td><td class="num"><?php echo $fcfa($montant); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ($se['caisse']['especes_rendues'] >= 0.5 || $se['caisse']['especes_recues'] >= 0.5): ?>
                        <tr><td>Retours clients : espèces rendues<small>Le jour de la validation par le caissier</small></td><td class="num"><?php echo $signe(-$se['caisse']['especes_rendues']); ?></td></tr>
                        <tr><td>Retours clients : espèces reçues pour un échange</td><td class="num"><?php echo $signe($se['caisse']['especes_recues']); ?></td></tr>
                        <?php endif; ?>
                        <tr class="bilan-synthese__groupe"><td colspan="2">Factures</td></tr>
                        <tr><td>Paiements enregistrés<small>Registre des paiements, à la date du paiement : devis <?php echo $fcfa($se['factures']['types']['facture_devis']); ?> · bons <?php echo $fcfa($se['factures']['types']['bl']); ?> · factures du mois <?php echo $fcfa($se['factures']['types']['facture_mensuelle']); ?></small></td><td class="num"><?php echo $fcfa($se['factures']['total']); ?></td></tr>
                        <tr><td>Payées avant le registre<small>Montant de la facture, à la date de paiement notée ; moyen de paiement inconnu (<?php echo (int) $se['avant_registre']['nb']; ?> facture(s))</small></td><td class="num"><?php echo $fcfa($se['avant_registre']['total']); ?></td></tr>
                        <tr class="bilan-synthese__groupe"><td colspan="2">Site</td></tr>
                        <tr><td>Commandes payées<small>À la date de livraison, sinon de commande</small></td><td class="num"><?php echo $fcfa($se['site']['total']); ?></td></tr>
                    </tbody>
                    <tfoot><tr><th>Encaissé</th><th class="num"><?php echo $fcfa($se['total']); ?> FCFA</th></tr></tfoot>
                </table>
            </div>
        </section>

        <section class="bilan-detail bilan-synthese" aria-labelledby="bilan-reste-titre">
            <div class="bilan-detail__head">
                <h2 id="bilan-reste-titre">À encaisser à ce jour</h2>
            </div>
            <p class="bilan-synthese__note">Ce que les clients doivent encore aujourd’hui, quelle que soit la période choisie. Un bon regroupé dans une facture du mois validée se compte par sa facture.</p>
            <div class="bilan-synthese__wrap">
                <table class="bilan-synthese__table">
                    <thead><tr><th>Ce qui reste dû</th><th class="num">Documents</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                        <tr><td>Factures de devis impayées<small>Montant de la facture moins les paiements enregistrés</small></td><td class="num"><?php echo (int) $sa['devis']['nb']; ?></td><td class="num"><?php echo $fcfa($sa['devis']['montant']); ?></td></tr>
                        <tr><td>Bons livrés, pas encore sur une facture du mois validée<small>Retours déduits, moins les paiements enregistrés</small></td><td class="num"><?php echo (int) $sa['bons']['nb']; ?></td><td class="num"><?php echo $fcfa($sa['bons']['montant']); ?></td></tr>
                        <tr><td>Factures du mois validées, impayées</td><td class="num"><?php echo (int) $sa['factures_mois']['nb']; ?></td><td class="num"><?php echo $fcfa($sa['factures_mois']['montant']); ?></td></tr>
                        <tr><td>Commandes du site livrées, non payées</td><td class="num"><?php echo (int) $sa['site']['nb']; ?></td><td class="num"><?php echo $fcfa($sa['site']['montant']); ?></td></tr>
                    </tbody>
                    <tfoot><tr><th>À encaisser</th><th></th><th class="num"><?php echo $fcfa($sa['total']); ?> FCFA</th></tr></tfoot>
                </table>
            </div>
            <h3>Avoirs à émettre</h3>
            <?php if (!$sa['avoirs']['lignes']): ?>
            <p class="bilan-empty">Aucun avoir à émettre.</p>
            <?php else: ?>
            <p class="bilan-synthese__note">Des pièces sont revenues après une facture qui ne se recalcule plus : la différence se rend au client par un avoir.</p>
            <div class="bilan-synthese__wrap">
                <table class="bilan-synthese__table">
                    <thead><tr><th>Document</th><th>Nature</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                        <?php foreach ($sa['avoirs']['lignes'] as $avoir): ?>
                        <tr><td><code><?php echo htmlspecialchars($avoir['document']); ?></code></td><td><?php echo htmlspecialchars($avoir['nature']); ?></td><td class="num"><?php echo $fcfa($avoir['montant']); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th>Avoirs à émettre</th><th></th><th class="num"><?php echo $fcfa($sa['avoirs']['montant']); ?> FCFA</th></tr></tfoot>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="bilan-detail" aria-labelledby="bilan-detail-title">
            <div class="bilan-detail__head">
                <h2 id="bilan-detail-title">Aperçu des flux</h2>
                <a href="<?php echo htmlspecialchars($export_url, ENT_QUOTES, 'UTF-8'); ?>" class="bilan-btn bilan-btn--secondary bilan-btn--sm"><i class="fas fa-download" aria-hidden="true"></i> CSV complet</a>
            </div>

            <div class="bilan-columns">
                <div class="bilan-col">
                    <h3><i class="fas fa-receipt" aria-hidden="true"></i> Commandes web</h3>
                    <?php if (empty($data['commandes'])): ?>
                        <p class="bilan-empty">Aucune commande vendue sur cette période.</p>
                    <?php else: ?>
                        <ul class="bilan-mini-list">
                            <?php foreach (array_slice($data['commandes'], 0, 8) as $c): ?>
                                <li>
                                    <span class="bilan-mini-list__ref"><?php echo htmlspecialchars($c['numero_commande'] ?? ''); ?></span>
                                    <span class="bilan-mini-list__amt"><?php echo number_format((float) ($c['montant_total'] ?? 0), 0, ',', ' '); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($data['commandes']) > 8): ?>
                            <p class="bilan-more">+ <?php echo count($data['commandes']) - 8; ?> autre(s) dans le CSV…</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="bilan-col">
                    <h3><i class="fas fa-wallet" aria-hidden="true"></i> Dépenses</h3>
                    <?php if (empty($data['depenses'])): ?>
                        <p class="bilan-empty">Aucune dépense sur cette période.</p>
                    <?php else: ?>
                        <ul class="bilan-mini-list">
                            <?php foreach (array_slice($data['depenses'], 0, 8) as $dep): ?>
                                <li>
                                    <span class="bilan-mini-list__ref"><?php echo htmlspecialchars(mb_substr($dep['libelle'] ?? '', 0, 42)); ?><?php echo mb_strlen($dep['libelle'] ?? '', 'UTF-8') > 42 ? '…' : ''; ?></span>
                                    <span class="bilan-mini-list__amt"><?php echo number_format((float) ($dep['montant_ttc'] ?? 0), 0, ',', ' '); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($data['depenses']) > 8): ?>
                            <p class="bilan-more">+ <?php echo count($data['depenses']) - 8; ?> dans le CSV…</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="bilan-col">
                    <h3><i class="fas fa-cash-register" aria-hidden="true"></i> Caisse</h3>
                    <?php if (empty($data['caisse_liste'])): ?>
                        <p class="bilan-empty">Aucun ticket sur cette période.</p>
                    <?php else: ?>
                        <ul class="bilan-mini-list">
                            <?php foreach (array_slice($data['caisse_liste'], 0, 8) as $cv): ?>
                                <li>
                                    <span class="bilan-mini-list__ref"><?php echo htmlspecialchars($cv['numero_ticket'] ?? ''); ?></span>
                                    <span class="bilan-mini-list__amt"><?php echo number_format((float) ($cv['montant_total'] ?? 0), 0, ',', ' '); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($data['caisse_liste']) > 8): ?>
                            <p class="bilan-more">+ <?php echo count($data['caisse_liste']) - 8; ?> dans le CSV…</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <script>
    (function () {
        var form = document.getElementById('bilan-filter-form');
        if (!form) return;
        var panels = {
            jour: document.getElementById('bilan-panel-jour'),
            mois: document.getElementById('bilan-panel-mois'),
            plage: document.getElementById('bilan-panel-plage')
        };
        function syncPanels() {
            var v = form.querySelector('input[name="b_periode"]:checked');
            var mode = v ? v.value : 'mois';
            Object.keys(panels).forEach(function (k) {
                var el = panels[k];
                if (!el) return;
                if (k === mode) {
                    el.classList.remove('is-hidden');
                } else {
                    el.classList.add('is-hidden');
                }
            });
        }
        form.querySelectorAll('input[name="b_periode"]').forEach(function (r) {
            r.addEventListener('change', syncPanels);
        });
        form.addEventListener('submit', function () {
            Object.keys(panels).forEach(function (k) {
                var el = panels[k];
                if (!el || !el.classList.contains('is-hidden')) return;
                el.querySelectorAll('input, select').forEach(function (inp) { inp.disabled = true; });
            });
        });
        syncPanels();
    })();
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
