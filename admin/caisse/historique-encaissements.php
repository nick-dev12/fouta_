<?php
/**
 * Historique des encaissements (tickets payés) — filtre par période, défaut : jour courant
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_encaisser_ticket()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_caisse.php';
require_once __DIR__ . '/../../models/model_caisse_compta.php';

$tables_ok = caisse_tables_exist();
$page_title = 'Historique des encaissements';

$today = date('Y-m-d');
$d1 = isset($_GET['date_debut']) ? trim((string) $_GET['date_debut']) : '';
$d2 = isset($_GET['date_fin']) ? trim((string) $_GET['date_fin']) : '';

if ($d1 === '' && $d2 === '') {
    $d1 = $today;
    $d2 = $today;
} else {
    if ($d1 === '') {
        $d1 = $d2 !== '' ? $d2 : $today;
    }
    if ($d2 === '') {
        $d2 = $d1;
    }
}

$ts1 = strtotime($d1 . ' 12:00:00');
$ts2 = strtotime($d2 . ' 12:00:00');
if ($ts1 === false) {
    $d1 = $today;
}
if ($ts2 === false) {
    $d2 = $today;
}
if ($d1 > $d2) {
    $tmp = $d1;
    $d1 = $d2;
    $d2 = $tmp;
}

$mode = isset($_GET['mode_paiement']) ? trim((string) $_GET['mode_paiement']) : '';
$caissier_id = isset($_GET['caissier_id']) ? (int) $_GET['caissier_id'] : 0;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$lignes = [];
$totaux = ['total_ttc' => 0.0, 'nb' => 0, 'par_canal' => []];
if ($tables_ok) {
    $lignes = caisse_encaissements_historique_fetch([
        'date_debut' => $d1,
        'date_fin' => $d2,
        'mode_paiement' => $mode,
        'caissier_id' => $caissier_id,
        'q' => $q,
        'limit' => 800,
    ]);
    $totaux = caisse_compta_calculer_totaux($lignes);
}

$moyenne = ($totaux['nb'] > 0) ? round($totaux['total_ttc'] / $totaux['nb'], 2) : 0.0;
$admins_liste = caisse_compta_liste_admins_actifs();
$is_defaut_jour = ($d1 === $today && $d2 === $today && $mode === '' && $caissier_id === 0 && $q === '');
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
</head>
<body class="admin-caisse-page admin-caisse-historique">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="caisse-page-wrap">
    <header class="caisse-page-head caisse-hist-head">
        <div class="caisse-page-head-inner">
            <h1 class="caisse-page-title"><i class="fas fa-history"></i> <?php echo htmlspecialchars($page_title); ?></h1>
            <p class="caisse-page-lead">Tickets encaissés sur la période. Par défaut : <strong>aujourd’hui uniquement</strong> — utilisez les filtres pour voir les jours précédents.</p>
            <div class="caisse-hist-head-actions no-print">
                <a href="encaisser-ticket.php" class="btn-secondary"><i class="fas fa-cash-register"></i> Retour à l’encaissement</a>
            </div>
        </div>
    </header>

    <?php if (!$tables_ok): ?>
    <div class="caisse-banner caisse-banner--warn">
        <i class="fas fa-database"></i>
        <span>Tables caisse absentes — exécutez les migrations.</span>
    </div>
    <?php else: ?>

    <div class="caisse-hist-stats no-print">
        <article class="caisse-hist-stat-card">
            <span class="caisse-hist-stat-card__label"><i class="fas fa-ticket-alt"></i> Tickets encaissés</span>
            <span class="caisse-hist-stat-card__value"><?php echo number_format((int) $totaux['nb'], 0, ',', ' '); ?></span>
        </article>
        <article class="caisse-hist-stat-card caisse-hist-stat-card--accent">
            <span class="caisse-hist-stat-card__label"><i class="fas fa-coins"></i> Montant total TTC</span>
            <span class="caisse-hist-stat-card__value"><?php echo number_format($totaux['total_ttc'], 0, ',', ' '); ?> <small>FCFA</small></span>
        </article>
        <?php if ($totaux['nb'] > 0): ?>
        <article class="caisse-hist-stat-card caisse-hist-stat-card--note">
            <span class="caisse-hist-stat-card__label"><i class="fas fa-chart-pie"></i> Ticket moyen</span>
            <span class="caisse-hist-stat-card__value"><?php echo number_format($moyenne, 0, ',', ' '); ?> <small>FCFA</small></span>
        </article>
        <?php endif; ?>
    </div>

    <section class="card-style-caisse caisse-hist-filters">
        <h2 class="caisse-hist-filters__title"><i class="fas fa-filter"></i> Filtres</h2>
        <form method="get" action="historique-encaissements.php" class="caisse-hist-filters__form">
            <div class="caisse-hist-filters__grid">
                <div class="caisse-hist-field">
                    <label for="hist_d1">Du</label>
                    <input type="date" name="date_debut" id="hist_d1" value="<?php echo htmlspecialchars($d1); ?>" class="caisse-search-input">
                </div>
                <div class="caisse-hist-field">
                    <label for="hist_d2">Au</label>
                    <input type="date" name="date_fin" id="hist_d2" value="<?php echo htmlspecialchars($d2); ?>" class="caisse-search-input">
                </div>
                <div class="caisse-hist-field">
                    <label for="hist_mode">Mode de paiement</label>
                    <select name="mode_paiement" id="hist_mode" class="caisse-search-select">
                        <option value="">Tous</option>
                        <option value="especes" <?php echo $mode === 'especes' ? 'selected' : ''; ?>>Espèces</option>
                        <option value="orange_money" <?php echo $mode === 'orange_money' ? 'selected' : ''; ?>>Orange Money</option>
                        <option value="wave" <?php echo $mode === 'wave' ? 'selected' : ''; ?>>Wave</option>
                        <option value="carte" <?php echo $mode === 'carte' ? 'selected' : ''; ?>>Carte</option>
                        <option value="cheque" <?php echo $mode === 'cheque' ? 'selected' : ''; ?>>Chèque</option>
                        <option value="mixte" <?php echo $mode === 'mixte' ? 'selected' : ''; ?>>Paiement mixte</option>
                        <option value="autre" <?php echo $mode === 'autre' ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
                <div class="caisse-hist-field">
                    <label for="hist_caissier">Caissier (encaissement)</label>
                    <select name="caissier_id" id="hist_caissier" class="caisse-search-select">
                        <option value="0">Tous</option>
                        <?php foreach ($admins_liste as $ad):
                            $aid = (int) ($ad['id'] ?? 0);
                            ?>
                        <option value="<?php echo $aid; ?>" <?php echo $caissier_id === $aid ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(trim(($ad['prenom'] ?? '') . ' ' . ($ad['nom'] ?? ''))); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="caisse-hist-field caisse-hist-field--grow">
                    <label for="hist_q">Recherche (n° ticket, note)</label>
                    <input type="search" name="q" id="hist_q" value="<?php echo htmlspecialchars($q); ?>" class="caisse-search-input" placeholder="TKT…">
                </div>
            </div>
            <div class="caisse-hist-filters__actions">
                <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Appliquer</button>
                <a href="historique-encaissements.php" class="btn-secondary">Aujourd’hui uniquement</a>
            </div>
        </form>
        <?php if ($is_defaut_jour): ?>
        <p class="caisse-hist-hint"><i class="fas fa-info-circle"></i> Affichage : encaissements du <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($today))); ?></strong> uniquement.</p>
        <?php endif; ?>
    </section>

    <div class="caisse-hist-table-wrap">
        <table class="caisse-hist-table">
            <thead>
                <tr>
                    <th>N° ticket</th>
                    <th>Date encaissement</th>
                    <th>Montant TTC</th>
                    <th>Paiement</th>
                    <th>Vendeur</th>
                    <th>Caissier</th>
                    <th class="caisse-hist-col-actions no-print"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                <tr><td colspan="7" class="caisse-hist-empty">Aucun ticket encaissé pour cette période.</td></tr>
                <?php else: ?>
                    <?php foreach ($lignes as $row):
                        $idv = (int) ($row['id'] ?? 0);
                        $d_enc = $row['date_encaissement'] ?? $row['date_vente'] ?? '';
                        $lib_m = caisse_compta_libelle_paiement_ticket($row);
                        $nom_v = trim(($row['vendeur_prenom'] ?? '') . ' ' . ($row['vendeur_nom'] ?? ''));
                        $nom_c = trim(($row['encaiss_prenom'] ?? '') . ' ' . ($row['encaiss_nom'] ?? ''));
                        if ($nom_c === '' && !empty($row['caissier_id'])) {
                            $nom_c = '—';
                        }
                    ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($row['numero_ticket'] ?? ''); ?></code></td>
                    <td><?php echo $d_enc !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($d_enc))) : '—'; ?></td>
                    <td><strong><?php echo number_format((float) ($row['montant_total'] ?? 0), 0, ',', ' '); ?></strong> FCFA</td>
                    <td><?php echo htmlspecialchars($lib_m); ?></td>
                    <td><?php echo htmlspecialchars($nom_v !== '' ? $nom_v : '—'); ?></td>
                    <td><?php echo htmlspecialchars($nom_c !== '' ? $nom_c : '—'); ?></td>
                    <td class="no-print">
                        <a href="encaisser-ticket.php?ticket=<?php echo $idv; ?>" class="btn-secondary btn-sm">Voir</a>
                    </td>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
