<?php
/**
 * RETOURS CLIENTS (11/09/2026).
 *
 * La liste et le détail des retours. Le caissier valide un retour en attente :
 * il rend ou reçoit les espèces, et le stock bouge. Le vendeur qui l'a préparé,
 * ou le caissier, peut annuler un retour en attente avec un motif. Le vendeur
 * ne voit que ses retours ; le caissier les voit tous.
 * Règles : models/model_caisse_retours.php.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
$est_caissier = admin_can_encaisser_ticket();
$peut_preparer = admin_can_preparer_retour_caisse();
if (!$est_caissier && !$peut_preparer) {
    admin_redirect_role_home();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse_retours.php';

$page_title = 'Retours clients';
$tables_ok = caisse_tables_exist() && caisse_retours_tables_ok();

$flash_ok = (string) ($_SESSION['caisse_flash_success'] ?? '');
$flash_err = (string) ($_SESSION['caisse_flash_error'] ?? '');
unset($_SESSION['caisse_flash_success'], $_SESSION['caisse_flash_error']);

$moi = (int) $_SESSION['admin_id'];
$seulement_moi = $est_caissier ? 0 : $moi;
$vue = null;
if ($tables_ok && (int) ($_GET['retour'] ?? 0) > 0) {
    $vue = caisse_retour_par_id((int) $_GET['retour']);
    if ($vue && !$est_caissier && (int) $vue['admin_id'] !== $moi) {
        $vue = null;
    }
}
$en_attente = $tables_ok ? caisse_retours_liste(['statut' => 'en_attente', 'admin_id' => $seulement_moi, 'limite' => 100]) : [];
$derniers = $tables_ok ? caisse_retours_liste(['admin_id' => $seulement_moi, 'limite' => 40]) : [];

$motifs = caisse_retour_motifs();
$solutions = caisse_retour_solutions();
$lien_ticket = $est_caissier ? 'encaisser-ticket.php?ticket=' : 'index.php?ticket=';
$fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ');
};
$nom_de = static function ($prenom, $nom) {
    $complet = trim((string) $prenom . ' ' . (string) $nom);
    return $complet !== '' ? $complet : '—';
};
$argent = static function (array $r) use ($fcfa) {
    $valide = ($r['statut'] ?? '') === 'valide';
    if ((float) $r['especes_a_rendre'] >= 0.5) {
        return $fcfa($r['especes_a_rendre']) . ' FCFA ' . ($valide ? 'rendus au client' : 'à rendre au client');
    }
    if ((float) $r['especes_a_recevoir'] >= 0.5) {
        return $fcfa($r['especes_a_recevoir']) . ' FCFA ' . ($valide ? 'reçus du client' : 'à recevoir du client');
    }
    return 'aucun argent';
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
        .retours-section { margin-top: 18px; }
        .retours-section h2 { margin: 0; font-size: 1.15rem; color: #10316F; }
        .retours-section h3 { margin: 18px 0 8px; font-size: 1rem; color: #10316F; }
        .retours-entete { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 10px; }
        .retours-entete .btn-secondary { margin-left: auto; }
        .retours-meta { margin: 0 0 8px; color: #4A5468; }
        .retours-explication { margin: 0 0 12px; padding: 10px 12px; background: #F3F5F9; border-radius: 8px; }
        .retours-flash { display: flex; gap: 10px; align-items: center; margin-top: 14px; padding: 12px 14px; border-radius: 8px; }
        .retours-flash--ok { background: #E6F4EC; color: #1E6B42; }
        .retours-flash--err { background: #FBE9EA; color: #A61E25; }
        .retours-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 0.85em; font-weight: 700; white-space: nowrap; }
        .retours-pill--en_attente { background: #FBF0DC; color: #7F4C00; }
        .retours-pill--valide { background: #E6F4EC; color: #1E6B42; }
        .retours-pill--annule { background: #ECEFF4; color: #4A5468; }
        .retours-table-wrap { overflow-x: auto; }
        .retours-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .retours-table th, .retours-table td { padding: 8px 10px; border-bottom: 1px solid #E3E7EF; text-align: left; vertical-align: top; }
        .retours-table .num { text-align: right; white-space: nowrap; }
        .retours-bilan { margin: 14px 0; padding: 12px 14px; border: 1px solid #E3E7EF; border-radius: 8px; display: grid; gap: 4px; font-variant-numeric: tabular-nums; }
        .retours-bilan strong { font-size: 1.1em; color: #10316F; }
        .retours-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 12px; }
        .retours-annuler { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .retours-annuler input { min-width: 220px; padding: 8px 10px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; }
        .retours-note { margin: 0; color: #4A5468; }
        .retours-vide { color: #4A5468; }
        .retours-section :focus-visible { outline: 2px solid #10316F; outline-offset: 1px; }
        @media print {
            .no-print { display: none !important; }
            .retours-liste { display: none !important; }
        }
    </style>
</head>
<body class="admin-caisse-page admin-caisse-retours">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="caisse-page-wrap">
    <header class="caisse-page-head caisse-hist-head no-print">
        <div class="caisse-page-head-inner">
            <h1 class="caisse-page-title"><i class="fas fa-undo"></i> <?php echo htmlspecialchars($page_title); ?></h1>
            <p class="caisse-page-lead"><?php echo $est_caissier
                ? 'Validez les retours préparés par les vendeurs : vous rendez ou recevez les espèces, et le stock bouge.'
                : 'Vos retours clients. Le client présente le retour au caissier, qui le valide.'; ?></p>
            <div class="caisse-hist-head-actions">
                <?php if ($peut_preparer): ?>
                <a href="retour.php" class="btn-primary"><i class="fas fa-undo"></i> Faire un retour</a>
                <?php endif; ?>
                <?php if ($est_caissier): ?>
                <a href="encaisser-ticket.php" class="btn-secondary"><i class="fas fa-cash-register"></i> Encaissement des tickets</a>
                <?php else: ?>
                <a href="index.php" class="btn-secondary"><i class="fas fa-cash-register"></i> Caisse magasin</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($flash_ok !== ''): ?>
    <div class="retours-flash retours-flash--ok no-print" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <span><?php echo htmlspecialchars($flash_ok); ?></span></div>
    <?php endif; ?>
    <?php if ($flash_err !== ''): ?>
    <div class="retours-flash retours-flash--err no-print" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <span><?php echo htmlspecialchars($flash_err); ?></span></div>
    <?php endif; ?>

    <?php if (!$tables_ok): ?>
    <div class="caisse-banner caisse-banner--warn">
        <i class="fas fa-database"></i>
        <span>Les retours attendent la mise à jour de la base : exécutez migrations/run_caisse_retours.php.</span>
    </div>
    <?php else: ?>

    <?php if ($vue): ?>
    <?php
    $vue_rendues = array_values(array_filter($vue['lignes'], static function ($l) { return $l['sens'] === 'rendue'; }));
    $vue_remises = array_values(array_filter($vue['lignes'], static function ($l) { return $l['sens'] === 'remise'; }));
    $peut_annuler = $vue['statut'] === 'en_attente' && ($est_caissier || (int) $vue['admin_id'] === $moi);
    if ((float) $vue['especes_a_rendre'] >= 0.5) {
        $confirmation = 'Rendre ' . $fcfa($vue['especes_a_rendre']) . ' FCFA en espèces au client et valider le retour ?';
    } elseif ((float) $vue['especes_a_recevoir'] >= 0.5) {
        $confirmation = 'Recevoir ' . $fcfa($vue['especes_a_recevoir']) . ' FCFA en espèces du client et valider l’échange ?';
    } else {
        $confirmation = 'Valider ce retour ? Aucun argent ne change de main.';
    }
    ?>
    <section class="card-style-caisse retours-section" aria-labelledby="retours-vue-titre">
        <div class="retours-entete">
            <h2 id="retours-vue-titre">Retour <code><?php echo htmlspecialchars((string) $vue['numero_retour']); ?></code></h2>
            <span class="retours-pill retours-pill--<?php echo htmlspecialchars((string) $vue['statut']); ?>"><?php echo htmlspecialchars(caisse_retour_statut_libelle($vue['statut'])); ?></span>
            <button type="button" class="btn-secondary no-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        </div>
        <p class="retours-meta">Ticket d’origine <a href="<?php echo $lien_ticket . (int) $vue['vente_id']; ?>"><code><?php echo htmlspecialchars((string) $vue['numero_ticket']); ?></code></a>.
            <?php echo htmlspecialchars($motifs[$vue['motif']] ?? (string) $vue['motif']); ?>,
            <?php echo htmlspecialchars(mb_strtolower($solutions[$vue['solution']] ?? (string) $vue['solution'])); ?>.</p>
        <p class="retours-meta">Préparé par <?php echo htmlspecialchars($nom_de($vue['vendeur_prenom'], $vue['vendeur_nom'])); ?>
            le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $vue['date_creation']))); ?>.
            <?php if ($vue['statut'] === 'valide'): ?>
            Validé par <?php echo htmlspecialchars($nom_de($vue['caissier_prenom'], $vue['caissier_nom'])); ?>
            le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $vue['date_validation']))); ?>.
            <?php elseif ($vue['statut'] === 'annule'): ?>
            Annulé par <?php echo htmlspecialchars($nom_de($vue['annule_prenom'], $vue['annule_nom'])); ?>
            le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $vue['date_annulation']))); ?> :
            <?php echo htmlspecialchars((string) $vue['motif_annulation']); ?>
            <?php endif; ?></p>
        <p class="retours-explication">« <?php echo htmlspecialchars((string) $vue['explication']); ?> »</p>

        <h3>Pièces rendues par le client</h3>
        <div class="retours-table-wrap">
            <table class="retours-table">
                <thead><tr><th>Pièce</th><th class="num">Quantité</th><th class="num">Valeur au prix payé</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($vue_rendues as $l): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $l['designation']); ?></td>
                        <td class="num"><?php echo (int) $l['quantite']; ?></td>
                        <td class="num"><?php echo $fcfa($l['montant']); ?> FCFA</td>
                        <td><?php echo $vue['motif'] === 'defectueuse' ? 'Défectueuse : notée au journal, ne revient pas en vente' : 'Rentre en stock'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($vue_remises): ?>
        <h3>Pièces remises au client</h3>
        <div class="retours-table-wrap">
            <table class="retours-table">
                <thead><tr><th>Pièce</th><th class="num">Quantité</th><th class="num">Valeur</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($vue_remises as $l): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $l['designation']); ?></td>
                        <td class="num"><?php echo (int) $l['quantite']; ?></td>
                        <td class="num"><?php echo $fcfa($l['montant']); ?> FCFA</td>
                        <td>Sort du stock</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="retours-bilan">
            <span>Valeur rendue : <?php echo $fcfa($vue['montant_rendu']); ?> FCFA<?php echo $vue_remises ? ' · valeur remise : ' . $fcfa($vue['montant_remis']) . ' FCFA' : ''; ?></span>
            <strong>Espèces : <?php echo htmlspecialchars($argent($vue)); ?></strong>
        </div>

        <?php if ($vue['statut'] === 'en_attente'): ?>
        <div class="retours-actions no-print">
            <?php if ($est_caissier): ?>
            <form method="post" action="post.php" onsubmit="return confirm(<?php echo json_encode($confirmation, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="caisse_action" value="retour_valider">
                <input type="hidden" name="retour_id" value="<?php echo (int) $vue['id']; ?>">
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Valider le retour</button>
            </form>
            <?php else: ?>
            <p class="retours-note">Le client présente ce retour au caissier, qui le valide : <?php echo htmlspecialchars($argent($vue)); ?>.</p>
            <?php endif; ?>
            <?php if ($peut_annuler): ?>
            <form method="post" action="post.php" class="retours-annuler" onsubmit="return confirm('Annuler ce retour ? Les pièces redeviendront rendables sur le ticket.');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="caisse_action" value="retour_annuler">
                <input type="hidden" name="retour_id" value="<?php echo (int) $vue['id']; ?>">
                <input type="text" name="motif_annulation" required minlength="3" maxlength="255" placeholder="Motif de l’annulation" aria-label="Motif de l’annulation">
                <button type="submit" class="btn-secondary">Annuler le retour</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php foreach ([['titre' => 'Retours en attente de caisse', 'lignes' => $en_attente, 'vide' => 'Aucun retour en attente.'], ['titre' => 'Derniers retours', 'lignes' => $derniers, 'vide' => 'Aucun retour enregistré.']] as $bloc): ?>
    <section class="card-style-caisse retours-section retours-liste no-print">
        <h2><?php echo htmlspecialchars($bloc['titre']); ?></h2>
        <?php if (!$bloc['lignes']): ?>
        <p class="retours-vide"><?php echo htmlspecialchars($bloc['vide']); ?></p>
        <?php else: ?>
        <div class="retours-table-wrap">
            <table class="retours-table">
                <thead><tr><th>Retour</th><th>Ticket</th><th>Préparé par</th><th>Solution</th><th>Espèces</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bloc['lignes'] as $r): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars((string) $r['numero_retour']); ?></code><br><small><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['date_creation']))); ?></small></td>
                        <td><code><?php echo htmlspecialchars((string) $r['numero_ticket']); ?></code></td>
                        <td><?php echo htmlspecialchars($nom_de($r['vendeur_prenom'], $r['vendeur_nom'])); ?></td>
                        <td><?php echo htmlspecialchars($solutions[$r['solution']] ?? (string) $r['solution']); ?></td>
                        <td><?php echo htmlspecialchars($argent($r)); ?></td>
                        <td><span class="retours-pill retours-pill--<?php echo htmlspecialchars((string) $r['statut']); ?>"><?php echo htmlspecialchars(caisse_retour_statut_libelle($r['statut'])); ?></span></td>
                        <td><a class="btn-secondary btn-sm" href="retours.php?retour=<?php echo (int) $r['id']; ?>">Voir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
