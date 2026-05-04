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
$st = $data['stats_web'];
$td = $data['totaux_dep'];
$ct = $data['caisse_totaux'];
$bls = $data['stats_bl'];
$fms = $data['stats_fm'];

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/compta-bilan.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-compta-bilan">
    <?php include '../includes/nav.php'; ?>

    <div class="page-compta-admin bilan-wrap">
        <header class="bilan-hero">
            <div class="bilan-hero__grid">
                <div class="bilan-hero__text">
                    <p class="bilan-hero__eyebrow"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Synthèse financière</p>
                    <h1>Bilan comptable</h1>
                    <p class="bilan-hero__lead">Vue consolidée sur la période choisie : ventes en ligne, caisse magasin, dépenses, BL et factures HT B2B. Export CSV aligné sur les mêmes filtres.</p>
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
            <p class="bilan-filter-card__hint">Les montants e-commerce utilisent la <strong>date de commande</strong> (livrées / payées). La caisse utilise la <strong>date d’encaissement</strong>. Les BL utilisent la <strong>date du bon</strong>. Les dépenses : <strong>date de dépense</strong>. Les factures mensuelles : mois comptable qui <strong>chevauche</strong> l’intervalle.</p>

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

        <section class="bilan-note" role="note">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            <p>Les montants <strong>HT</strong> et <strong>TTC</strong> ne sont pas additionnés dans un « solde net » automatique : le tableau sert de <strong>base documentaire</strong> pour votre tenue de comptes.</p>
        </section>

        <div class="bilan-kpi-grid" aria-label="Indicateurs du bilan">
            <article class="bilan-kpi bilan-kpi--web">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-cart-shopping"></i></div>
                <h3 class="bilan-kpi__title">E-commerce</h3>
                <p class="bilan-kpi__value"><?php echo number_format($st['ca_total'], 0, ',', ' '); ?> <span class="bilan-kpi__cur">FCFA</span></p>
                <p class="bilan-kpi__meta"><?php echo (int) $st['nb']; ?> commande(s) · TTC · Livrées / payées</p>
            </article>
            <article class="bilan-kpi bilan-kpi--caisse">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-cash-register"></i></div>
                <h3 class="bilan-kpi__title">Caisse magasin</h3>
                <p class="bilan-kpi__value"><?php echo number_format($ct['total_ttc'], 0, ',', ' '); ?> <span class="bilan-kpi__cur">FCFA</span></p>
                <p class="bilan-kpi__meta"><?php echo (int) $ct['nb']; ?> ticket(s) · TTC</p>
            </article>
            <article class="bilan-kpi bilan-kpi--dep">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-arrow-trend-down"></i></div>
                <h3 class="bilan-kpi__title">Dépenses</h3>
                <p class="bilan-kpi__value"><?php echo number_format($td['sum_ttc'], 0, ',', ' '); ?> <span class="bilan-kpi__cur">FCFA TTC</span></p>
                <p class="bilan-kpi__meta"><?php echo (int) $td['nb']; ?> ligne(s) · HT <?php echo number_format($td['sum_ht'], 0, ',', ' '); ?></p>
            </article>
            <article class="bilan-kpi bilan-kpi--bl">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-truck-fast"></i></div>
                <h3 class="bilan-kpi__title">BL B2B</h3>
                <p class="bilan-kpi__value"><?php echo number_format($bls['somme_bl_ht'], 0, ',', ' '); ?> <span class="bilan-kpi__cur">FCFA HT</span></p>
                <p class="bilan-kpi__meta"><?php echo (int) $bls['nb_bl']; ?> BL · <?php echo (int) $bls['nb_clients']; ?> client(s)</p>
            </article>
            <article class="bilan-kpi bilan-kpi--fm">
                <div class="bilan-kpi__icon" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></div>
                <h3 class="bilan-kpi__title">Factures mensuelles</h3>
                <p class="bilan-kpi__value"><?php echo number_format($fms['somme_ht'], 0, ',', ' '); ?> <span class="bilan-kpi__cur">FCFA HT</span></p>
                <p class="bilan-kpi__meta"><?php echo (int) $fms['nb_factures']; ?> facture(s) · Mois chevauchants</p>
            </article>
        </div>

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
