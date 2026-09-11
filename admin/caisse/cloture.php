<?php
/**
 * CLÔTURE DE CAISSE (10/09/2026).
 *
 * Le caissier compte les espèces du tiroir et clôture : la période couverte va
 * de la clôture précédente à maintenant. L'écart avec les espèces encaissées est
 * enregistré et doit être expliqué dès qu'il n'est pas nul ; les tickets de la
 * période ne se corrigent plus ensuite. La page montre aussi l'historique des
 * clôtures et des corrections de paiement : la comptabilité et la direction la
 * consultent en lecture. Les règles vivent dans models/model_caisse_cloture.php,
 * l'enregistrement passe par post.php (action cloturer_caisse).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
$peut_cloturer = admin_can_encaisser_ticket();
if (!$peut_cloturer && !admin_can_comptabilite()) {
    admin_redirect_role_home();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse_cloture.php';

$page_title = 'Clôture de caisse';
$tables_ok = caisse_tables_exist() && caisse_cloture_tables_ok();

$flash_ok = (string) ($_SESSION['caisse_flash_success'] ?? '');
$flash_err = (string) ($_SESSION['caisse_flash_error'] ?? '');
$saisie = is_array($_SESSION['caisse_cloture_saisie'] ?? null) ? $_SESSION['caisse_cloture_saisie'] : [];
unset($_SESSION['caisse_flash_success'], $_SESSION['caisse_flash_error'], $_SESSION['caisse_cloture_saisie']);

$en_cours = $tables_ok ? caisse_cloture_periode_en_cours() : null;
$clotures = $tables_ok ? caisse_clotures_liste(30) : [];
$corrections = $tables_ok ? caisse_corrections_liste(0, 50) : [];
$vue = null;
$vue_tickets = [];
if ($tables_ok && (int) ($_GET['cloture'] ?? 0) > 0) {
    $vue = caisse_cloture_par_id((int) $_GET['cloture']);
    $vue_tickets = $vue ? caisse_cloture_tickets($vue) : [];
}

$libelles_canaux = [
    'especes' => 'Espèces',
    'carte' => 'Carte bancaire',
    'orange_money' => 'Orange Money',
    'wave' => 'Wave',
    'cheque' => 'Chèque',
    'autre' => 'Autre',
];
$fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ');
};
$le = static function ($d) {
    return $d ? date('d/m/Y à H:i', strtotime((string) $d)) : '';
};
$nom_de = static function (array $row, $prefixe) {
    $nom = trim((string) ($row[$prefixe . '_prenom'] ?? '') . ' ' . (string) ($row[$prefixe . '_nom'] ?? ''));
    return $nom !== '' ? $nom : '—';
};
$pastille_ecart = static function ($ecart) use ($fcfa) {
    $e = (float) $ecart;
    if (abs($e) < 0.5) {
        return '<span class="cloture-pill cloture-pill--juste">Juste</span>';
    }
    return '<span class="cloture-pill cloture-pill--ecart">' . ($e > 0 ? '+' : '−') . $fcfa(abs($e)) . ' FCFA</span>';
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-dashboard-caisse-pages.css'); ?>
    <style>
        .cloture-section { margin-top: 20px; }
        .cloture-section h2 { margin: 0 0 6px; font-size: 1.15rem; color: #10316F; }
        .cloture-section-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .cloture-meta { margin: 0 0 12px; color: #4A5468; }
        .cloture-grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 14px; align-items: start; }
        .cloture-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .cloture-table th, .cloture-table td { padding: 8px 10px; border-bottom: 1px solid #E3E7EF; text-align: left; vertical-align: top; }
        .cloture-table .num { text-align: right; white-space: nowrap; }
        .cloture-table tfoot th { border-top: 2px solid #10316F; border-bottom: 0; }
        .cloture-bilan { display: grid; gap: 10px; margin: 0; }
        .cloture-bilan div { display: flex; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid #E3E7EF; border-radius: 8px; }
        .cloture-bilan dt { color: #4A5468; }
        .cloture-bilan dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; }
        .cloture-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .85em; font-weight: 700; white-space: nowrap; }
        .cloture-pill--juste { background: #E6F4EC; color: #1E6B42; }
        .cloture-pill--ecart { background: #FBE9EA; color: #A61E25; }
        .cloture-note { margin: 0 0 10px; padding: 10px 12px; background: #F3F5F9; border-radius: 8px; color: #333C4E; }
        .cloture-form label { display: block; font-weight: 600; margin: 12px 0 4px; }
        .cloture-form input[type="text"], .cloture-form textarea { width: 100%; max-width: 440px; padding: 9px 11px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; box-sizing: border-box; }
        .cloture-form input:focus-visible, .cloture-form textarea:focus-visible { outline: 2px solid #10316F; outline-offset: 1px; }
        .cloture-form button { margin-top: 14px; }
        .cloture-ecart-ligne { margin: 8px 0 0; }
        .cloture-ecart { font-weight: 700; font-variant-numeric: tabular-nums; }
        .cloture-ecart--juste { color: #1E6B42; }
        .cloture-ecart--ecart { color: #A61E25; }
        .cloture-flash { display: flex; gap: 10px; align-items: center; margin-top: 14px; padding: 12px 14px; border-radius: 8px; }
        .cloture-flash--ok { background: #E6F4EC; color: #1E6B42; }
        .cloture-flash--err { background: #FBE9EA; color: #A61E25; }
        .cloture-tickets { margin-top: 14px; }
        .cloture-tickets summary { cursor: pointer; font-weight: 600; }
        @media print {
            .no-print { display: none !important; }
            .cloture-arrete { box-shadow: none; border: 0; }
            .cloture-tickets:not([open]) > :not(summary) { display: block; }
        }
    </style>
</head>
<body class="admin-caisse-page admin-caisse-cloture">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="caisse-page-wrap">
    <header class="caisse-page-head caisse-hist-head no-print">
        <div class="caisse-page-head-inner">
            <h1 class="caisse-page-title"><i class="fas fa-lock"></i> <?php echo htmlspecialchars($page_title); ?></h1>
            <p class="caisse-page-lead">Comptez les espèces du tiroir et arrêtez la caisse. L’écart avec les espèces encaissées est enregistré, et les tickets arrêtés ne se corrigent plus.</p>
            <?php if ($peut_cloturer): ?>
            <div class="caisse-hist-head-actions">
                <a href="encaisser-ticket.php" class="btn-secondary"><i class="fas fa-cash-register"></i> Retour à l’encaissement</a>
                <a href="historique-encaissements.php" class="btn-secondary"><i class="fas fa-history"></i> Historique des encaissements</a>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flash_ok !== ''): ?>
    <div class="cloture-flash cloture-flash--ok no-print" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <span><?php echo htmlspecialchars($flash_ok); ?></span></div>
    <?php endif; ?>
    <?php if ($flash_err !== ''): ?>
    <div class="cloture-flash cloture-flash--err no-print" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <span><?php echo htmlspecialchars($flash_err); ?></span></div>
    <?php endif; ?>

    <?php if (!$tables_ok): ?>
    <div class="caisse-banner caisse-banner--warn">
        <i class="fas fa-database"></i>
        <span>La clôture attend la mise à jour de la base : exécutez migrations/run_caisse_cloture.php.</span>
    </div>
    <?php else: ?>

    <?php if ($vue): ?>
    <section class="card-style-caisse cloture-section cloture-arrete" aria-labelledby="cloture-vue-titre">
        <div class="cloture-section-head">
            <h2 id="cloture-vue-titre">Arrêté de caisse du <?php echo htmlspecialchars($le($vue['periode_fin'])); ?></h2>
            <button type="button" class="btn-secondary no-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        </div>
        <p class="cloture-meta">Clôturé par <strong><?php echo htmlspecialchars($nom_de($vue, 'caissier')); ?></strong>.
            Période : <?php echo $vue['periode_debut'] ? 'du ' . htmlspecialchars($le($vue['periode_debut'])) : 'depuis le premier encaissement'; ?>
            au <?php echo htmlspecialchars($le($vue['periode_fin'])); ?>.</p>
        <div class="cloture-grille">
            <div class="caisse-hist-table-wrap">
                <table class="cloture-table">
                    <thead><tr><th>Canal</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                    <?php foreach ($libelles_canaux as $canal => $libelle): ?>
                        <tr><td><?php echo $libelle; ?></td><td class="num"><?php echo $fcfa($vue['montant_' . $canal] ?? 0); ?> FCFA</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th><?php echo (int) $vue['nb_tickets']; ?> ticket(s) encaissé(s)</th><th class="num"><?php echo $fcfa($vue['total_encaisse']); ?> FCFA</th></tr></tfoot>
                </table>
            </div>
            <dl class="cloture-bilan">
                <?php if ((int) ($vue['nb_retours'] ?? 0) > 0): ?>
                <div><dt>Retours clients</dt><dd><?php echo (int) $vue['nb_retours']; ?> : <?php echo $fcfa($vue['retours_especes_rendues']); ?> FCFA rendus, <?php echo $fcfa($vue['retours_especes_recues']); ?> FCFA reçus</dd></div>
                <?php endif; ?>
                <div><dt>Espèces attendues</dt><dd><?php echo $fcfa($vue['especes_attendues']); ?> FCFA</dd></div>
                <div><dt>Espèces comptées</dt><dd><?php echo $fcfa($vue['especes_comptees']); ?> FCFA</dd></div>
                <div><dt>Écart</dt><dd><?php echo $pastille_ecart($vue['ecart']); ?></dd></div>
                <?php if (trim((string) ($vue['commentaire'] ?? '')) !== ''): ?>
                <div><dt>Explication</dt><dd><?php echo htmlspecialchars((string) $vue['commentaire']); ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>
        <?php if ($vue_tickets): ?>
        <details class="cloture-tickets">
            <summary>Les <?php echo count($vue_tickets); ?> ticket(s) de la période</summary>
            <div class="caisse-hist-table-wrap">
                <table class="cloture-table">
                    <thead><tr><th>N° ticket</th><th>Encaissé le</th><th>Paiement</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                    <?php foreach ($vue_tickets as $t): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars((string) $t['numero_ticket']); ?></code></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $t['moment']))); ?></td>
                            <td><?php echo htmlspecialchars(caisse_compta_libelle_paiement_ticket($t)); ?></td>
                            <td class="num"><?php echo $fcfa($t['montant_total']); ?> FCFA</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card-style-caisse cloture-section no-print" aria-labelledby="cloture-encours-titre">
        <h2 id="cloture-encours-titre">Caisse en cours</h2>
        <?php if (!$en_cours): ?>
        <p class="cloture-note">Les montants de la caisse en cours n’ont pas pu être calculés. Rechargez la page.</p>
        <?php else: ?>
        <p class="cloture-meta"><?php
            if ($en_cours['derniere']) {
                echo 'Depuis la clôture du ' . htmlspecialchars($le($en_cours['derniere']['periode_fin']))
                    . ' par ' . htmlspecialchars($nom_de($en_cours['derniere'], 'caissier')) . '.';
            } elseif ($en_cours['tickets']) {
                echo 'Aucune clôture encore : la caisse court depuis le premier encaissement enregistré, le '
                    . htmlspecialchars($le($en_cours['tickets'][0]['moment'])) . '.';
            } else {
                echo 'Aucune clôture et aucun encaissement enregistrés.';
            }
        ?></p>
        <div class="caisse-hist-stats">
            <article class="caisse-hist-stat-card">
                <span class="caisse-hist-stat-card__label"><i class="fas fa-ticket-alt"></i> Tickets encaissés</span>
                <span class="caisse-hist-stat-card__value"><?php echo (int) $en_cours['nb']; ?></span>
            </article>
            <article class="caisse-hist-stat-card caisse-hist-stat-card--accent">
                <span class="caisse-hist-stat-card__label"><i class="fas fa-coins"></i> Total encaissé</span>
                <span class="caisse-hist-stat-card__value"><?php echo $fcfa($en_cours['total']); ?> <small>FCFA</small></span>
            </article>
            <article class="caisse-hist-stat-card caisse-hist-stat-card--note">
                <span class="caisse-hist-stat-card__label"><i class="fas fa-money-bill-wave"></i> Espèces attendues dans le tiroir</span>
                <span class="caisse-hist-stat-card__value"><?php echo $fcfa($en_cours['especes_attendues']); ?> <small>FCFA</small></span>
            </article>
        </div>
        <div class="cloture-grille">
            <div class="caisse-hist-table-wrap">
                <table class="cloture-table">
                    <thead><tr><th>Canal</th><th class="num">Tickets</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                    <?php foreach ($libelles_canaux as $canal => $libelle): $c = $en_cours['canaux'][$canal] ?? ['nb' => 0, 'montant' => 0]; ?>
                        <tr><td><?php echo $libelle; ?></td><td class="num"><?php echo (int) $c['nb']; ?></td><td class="num"><?php echo $fcfa($c['montant']); ?> FCFA</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div>
                <?php if ($en_cours['depenses'] > 0): ?>
                <p class="cloture-note"><i class="fas fa-wallet" aria-hidden="true"></i> Dépenses saisies sur ces dates : <strong><?php echo $fcfa($en_cours['depenses']); ?> FCFA</strong>. Elles ne sont pas déduites, car la saisie ne dit pas si elles ont été payées avec l’argent du tiroir. Si c’est le cas, dites-le dans le commentaire.</p>
                <?php endif; ?>
                <?php if (!empty($en_cours['retours']['nb'])): ?>
                <p class="cloture-note"><i class="fas fa-undo" aria-hidden="true"></i> <?php echo (int) $en_cours['retours']['nb']; ?> retour(s) client validé(s) sur la période : <strong><?php echo $fcfa($en_cours['retours']['especes_rendues']); ?> FCFA</strong> rendus et <strong><?php echo $fcfa($en_cours['retours']['especes_recues']); ?> FCFA</strong> reçus en espèces, déjà comptés dans les espèces attendues (<?php echo $fcfa($en_cours['especes_encaissees']); ?> FCFA encaissés en espèces).</p>
                <?php endif; ?>
                <?php if ($en_cours['corrections'] > 0): ?>
                <p class="cloture-note"><i class="fas fa-edit" aria-hidden="true"></i> <?php echo (int) $en_cours['corrections']; ?> correction(s) de paiement sur la période, détaillées plus bas.</p>
                <?php endif; ?>

                <?php if ($peut_cloturer): ?>
                <form method="post" action="post.php" class="cloture-form"
                    onsubmit="return confirm('Clôturer la caisse maintenant ? Les tickets encaissés jusqu’ici ne pourront plus être corrigés.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="caisse_action" value="cloturer_caisse">
                    <label for="cloture_especes">Espèces comptées dans le tiroir (FCFA)</label>
                    <input type="text" name="especes_comptees" id="cloture_especes" inputmode="decimal" autocomplete="off" required
                        data-attendu="<?php echo htmlspecialchars((string) $en_cours['especes_attendues']); ?>"
                        value="<?php echo htmlspecialchars((string) ($saisie['especes_comptees'] ?? '')); ?>" placeholder="Ex. 125000">
                    <p class="cloture-ecart-ligne">Écart : <output id="cloture_ecart" class="cloture-ecart" for="cloture_especes">—</output></p>
                    <label for="cloture_commentaire">Commentaire</label>
                    <textarea name="commentaire" id="cloture_commentaire" rows="2" maxlength="255"
                        placeholder="Obligatoire s’il y a un écart : monnaie mal rendue, dépense payée en espèces…"><?php echo htmlspecialchars((string) ($saisie['commentaire'] ?? '')); ?></textarea>
                    <button type="submit" class="btn-primary"><i class="fas fa-lock"></i> Clôturer la caisse</button>
                </form>
                <?php else: ?>
                <p class="cloture-note"><i class="fas fa-eye" aria-hidden="true"></i> Consultation : seul le caissier compte le tiroir et clôture la caisse.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <section class="card-style-caisse cloture-section no-print" aria-labelledby="cloture-historique-titre">
        <h2 id="cloture-historique-titre">Clôtures précédentes</h2>
        <div class="caisse-hist-table-wrap">
            <table class="caisse-hist-table cloture-table">
                <thead>
                    <tr>
                        <th>Clôturée le</th><th>Par</th><th class="num">Tickets</th><th class="num">Total encaissé</th>
                        <th class="num">Espèces attendues</th><th class="num">Espèces comptées</th><th>Écart</th><th>Commentaire</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$clotures): ?>
                    <tr><td colspan="9" class="caisse-hist-empty">Aucune clôture enregistrée.</td></tr>
                <?php else: foreach ($clotures as $cl): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($le($cl['periode_fin'])); ?></td>
                        <td><?php echo htmlspecialchars($nom_de($cl, 'caissier')); ?></td>
                        <td class="num"><?php echo (int) $cl['nb_tickets']; ?></td>
                        <td class="num"><?php echo $fcfa($cl['total_encaisse']); ?></td>
                        <td class="num"><?php echo $fcfa($cl['especes_attendues']); ?></td>
                        <td class="num"><?php echo $fcfa($cl['especes_comptees']); ?></td>
                        <td><?php echo $pastille_ecart($cl['ecart']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($cl['commentaire'] ?? '')); ?></td>
                        <td><a class="btn-secondary btn-sm" href="cloture.php?cloture=<?php echo (int) $cl['id']; ?>">Voir</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card-style-caisse cloture-section no-print" aria-labelledby="cloture-corrections-titre">
        <h2 id="cloture-corrections-titre">Corrections de paiement</h2>
        <p class="cloture-meta">Chaque correction d’un ticket encaissé, avec l’ancienne valeur, son auteur et son motif.</p>
        <div class="caisse-hist-table-wrap">
            <table class="caisse-hist-table cloture-table">
                <thead><tr><th>Corrigé le</th><th>Ticket</th><th>Par</th><th>Avant</th><th>Après</th><th>Motif</th></tr></thead>
                <tbody>
                <?php if (!$corrections): ?>
                    <tr><td colspan="6" class="caisse-hist-empty">Aucune correction de paiement enregistrée.</td></tr>
                <?php else: foreach ($corrections as $corr): $numero = htmlspecialchars((string) ($corr['numero_ticket'] ?? '')); ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $corr['date_correction']))); ?></td>
                        <td><?php if ($peut_cloturer): ?><a href="encaisser-ticket.php?ticket=<?php echo (int) $corr['vente_id']; ?>"><code><?php echo $numero; ?></code></a><?php else: ?><code><?php echo $numero; ?></code><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($nom_de($corr, 'auteur')); ?></td>
                        <td><?php echo htmlspecialchars(caisse_paiement_libelle_instantane($corr['paiement_avant'])); ?></td>
                        <td><?php echo htmlspecialchars(caisse_paiement_libelle_instantane($corr['paiement_apres'])); ?></td>
                        <td><?php echo htmlspecialchars((string) $corr['motif']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>

<script>
(function () {
    var champ = document.getElementById('cloture_especes');
    var sortie = document.getElementById('cloture_ecart');
    if (!champ || !sortie) {
        return;
    }
    var attendu = parseFloat(champ.getAttribute('data-attendu')) || 0;
    function majEcart() {
        var brut = champ.value.replace(/[\s  ]/g, '').replace(',', '.');
        if (brut === '' || isNaN(Number(brut))) {
            sortie.textContent = '—';
            sortie.className = 'cloture-ecart';
            return;
        }
        var ecart = Math.round((Number(brut) - attendu) * 100) / 100;
        var juste = Math.abs(ecart) < 0.5;
        sortie.textContent = juste ? 'aucun, le tiroir est juste'
            : (ecart > 0 ? '+' : '−') + String(Math.round(Math.abs(ecart))).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA, à expliquer';
        sortie.className = 'cloture-ecart ' + (juste ? 'cloture-ecart--juste' : 'cloture-ecart--ecart');
    }
    champ.addEventListener('input', majEcart);
    majEcart();
})();
</script>
</body>
</html>
