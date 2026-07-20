<?php
/**
 * Scan QR / code-barres d’une barre entrepôt — liste des produits (admin / stock).
 * URL QR : /admin/entrepot/barre-info.php?c=FOUTA-BAR-000001
 * Code-barres Code128 : même code FOUTA-BAR-… (saisie / scanner → redirection ici)
 */
session_start();

require_once __DIR__ . '/../../models/model_entrepot_referentiel.php';
require_once __DIR__ . '/../../includes/entrepot_barcode_service.php';
require_once __DIR__ . '/../../includes/asset_version.php';

$code = '';
if (isset($_GET['c'])) {
    $code = strtoupper(trim((string) $_GET['c']));
} elseif (isset($_POST['c'])) {
    $code = strtoupper(trim((string) $_POST['c']));
}

// Redirection propre après saisie code-barres
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $code !== '') {
    header('Location: barre-info.php?c=' . rawurlencode($code));
    exit;
}

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    $next = '/admin/entrepot/barre-info.php' . ($code !== '' ? ('?c=' . rawurlencode($code)) : '');
    $_SESSION['admin_login_redirect'] = $next;
    header('Location: ../login.php?next=' . rawurlencode($next));
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

if (!admin_can_scan_entrepot_barre()) {
    header('Location: ../dashboard.php');
    exit;
}

$barre = null;
$contexte = null;
$produits = [];
$chemin = '';
$error_scan = '';

if ($code !== '') {
    $data = entrepot_produits_par_code_barre($code);
    $barre = $data['barre'];
    $contexte = $data['contexte'] ?? null;
    $produits = $data['produits'] ?? [];
    if ($barre === null) {
        $error_scan = 'Code barre inconnu : ' . $code;
    } else {
        $chemin = entrepot_build_chemin_barre((int) $barre['id']);
        if ($contexte === null) {
            $contexte = entrepot_get_barre_contexte((int) $barre['id']);
        }
    }
}

$nb = count($produits);
$stock_total = 0;
foreach ($produits as $p) {
    $stock_total += (int) ($p['stock'] ?? 0);
}

$qr_web = ($barre !== null) ? get_qrcode_barre_web_path((int) $barre['id']) : '';
$bc_web = ($barre !== null) ? get_barcode_barre_web_path((int) $barre['id']) : '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $barre ? ('Barre ' . htmlspecialchars($barre['nom'] ?? '')) : 'Scan barre'; ?> — Entrepôt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-emplacement-entrepot.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-barre-info.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-emplacement-entrepot page-barre-info">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="bi-page">
        <?php if ($barre === null): ?>
            <section class="bi-scan-card">
                <div class="bi-scan-card__icon" aria-hidden="true"><i class="fas fa-qrcode"></i></div>
                <h1 class="bi-scan-card__title">Scanner une barre entrepôt</h1>
                <p class="bi-scan-card__lead">Scannez le QR code (ouverture directe) ou saisissez / scannez le code-barres <code>FOUTA-BAR-XXXXXX</code>.</p>
                <?php if ($error_scan !== ''): ?>
                    <div class="bi-alert bi-alert--error" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_scan); ?>
                    </div>
                <?php endif; ?>
                <form method="get" action="barre-info.php" class="bi-scan-form" autocomplete="off">
                    <label for="c">Code de la barre</label>
                    <div class="bi-scan-form__row">
                        <input type="text" id="c" name="c" value="<?php echo htmlspecialchars($code); ?>"
                            placeholder="FOUTA-BAR-000001" autofocus required>
                        <button type="submit" class="bi-btn-primary"><i class="fas fa-search"></i> Afficher</button>
                    </div>
                </form>
                <p class="bi-scan-card__hint">
                    <a href="../parametres/emplacement-entrepot.php"><i class="fas fa-arrow-left"></i> Paramètres entrepôt</a>
                </p>
            </section>
        <?php else: ?>
            <header class="bi-hero">
                <div class="bi-hero__top">
                    <a class="bi-back" href="../parametres/emplacement-entrepot-etage.php?etage=<?php echo (int) ($contexte['numero_etage'] ?? 0); ?>">
                        <i class="fas fa-arrow-left"></i> Retour étage
                    </a>
                    <span class="bi-hero__badge"><i class="fas fa-warehouse"></i> Scan barre</span>
                </div>
                <div class="bi-hero__grid">
                    <div class="bi-hero__main">
                        <p class="bi-hero__etage">
                            <i class="fas fa-building"></i>
                            <?php echo htmlspecialchars($contexte['etage_nom'] ?? ('Étage ' . (int) ($contexte['numero_etage'] ?? 0))); ?>
                            <?php if (!empty($contexte['etage_code'])): ?>
                                <code><?php echo htmlspecialchars($contexte['etage_code']); ?></code>
                            <?php endif; ?>
                        </p>
                        <h1 class="bi-hero__title"><?php echo htmlspecialchars($barre['nom'] ?? 'Barre'); ?></h1>
                        <p class="bi-hero__chemin"><?php echo htmlspecialchars($chemin !== '' ? $chemin : '—'); ?></p>
                        <p class="bi-hero__code"><code><?php echo htmlspecialchars($barre['code_scan'] ?? $code); ?></code></p>
                    </div>
                    <div class="bi-hero__codes">
                        <?php if ($qr_web !== ''): ?>
                            <img src="<?php echo htmlspecialchars($qr_web); ?>" alt="QR code barre" width="96" height="96">
                        <?php endif; ?>
                        <?php if ($bc_web !== ''): ?>
                            <img src="<?php echo htmlspecialchars($bc_web); ?>" alt="Code-barres barre" class="bi-hero__barcode">
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="bi-kpis" aria-label="Synthèse">
                    <li>
                        <strong><?php echo (int) $nb; ?></strong>
                        <span>Produit(s)</span>
                    </li>
                    <li>
                        <strong><?php echo (int) $stock_total; ?></strong>
                        <span>Stock total</span>
                    </li>
                    <li>
                        <strong><?php echo (int) ($contexte['numero_etage'] ?? 0); ?></strong>
                        <span>N° étage</span>
                    </li>
                </ul>
            </header>

            <section class="bi-path-card" aria-label="Emplacement de la barre">
                <h2><i class="fas fa-map-signs"></i> Emplacement</h2>
                <div class="bi-path-grid">
                    <div class="bi-path-item">
                        <span class="bi-path-item__label">Étage</span>
                        <span class="bi-path-item__value"><?php echo htmlspecialchars($contexte['etage_nom'] ?? '—'); ?></span>
                    </div>
                    <div class="bi-path-item">
                        <span class="bi-path-item__label">Rayon</span>
                        <span class="bi-path-item__value"><?php echo htmlspecialchars($contexte['rayon_nom'] ?? '—'); ?></span>
                    </div>
                    <div class="bi-path-item">
                        <span class="bi-path-item__label">Allée</span>
                        <span class="bi-path-item__value"><?php echo htmlspecialchars($contexte['allee_nom'] ?? '—'); ?></span>
                    </div>
                    <div class="bi-path-item">
                        <span class="bi-path-item__label">Zone</span>
                        <span class="bi-path-item__value"><?php echo htmlspecialchars($contexte['zone_nom'] ?? '—'); ?></span>
                    </div>
                    <div class="bi-path-item bi-path-item--accent">
                        <span class="bi-path-item__label">Barre</span>
                        <span class="bi-path-item__value"><?php echo htmlspecialchars($contexte['barre_nom'] ?? '—'); ?></span>
                    </div>
                </div>
            </section>

            <section class="bi-products" aria-labelledby="bi-products-title">
                <div class="bi-products__head">
                    <h2 id="bi-products-title"><i class="fas fa-boxes-stacked"></i> Produits sur cette barre</h2>
                    <p>Tous les produits enregistrés avec une position liée à cette barre (étage <?php echo (int) ($contexte['numero_etage'] ?? 0); ?>).</p>
                </div>

                <?php if ($produits === []): ?>
                    <div class="bi-empty">
                        <i class="fas fa-box-open" aria-hidden="true"></i>
                        <h3>Aucun produit sur cette barre</h3>
                        <p>Assignez une position de cette barre sur une fiche produit pour la voir apparaître ici.</p>
                    </div>
                <?php else: ?>
                    <div class="bi-table-wrap">
                        <table class="bi-table">
                            <thead>
                                <tr>
                                    <th>Réf. FPL</th>
                                    <th>Produit</th>
                                    <th>Position</th>
                                    <th>Stock</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produits as $p):
                                    $stock = (int) ($p['stock'] ?? 0);
                                    $statut = (string) ($p['statut'] ?? '');
                                    $stock_class = $stock <= 0 ? 'is-zero' : ($stock <= 5 ? 'is-low' : 'is-ok');
                                ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($p['identifiant_interne'] ?? '—'); ?></code></td>
                                    <td class="bi-table__nom"><?php echo htmlspecialchars($p['nom'] ?? ''); ?></td>
                                    <td>
                                        <span class="bi-pos-pill">
                                            <?php echo htmlspecialchars($p['position_nom'] ?? ('Position #' . (int) ($p['position_num'] ?? 0))); ?>
                                        </span>
                                    </td>
                                    <td><span class="bi-stock <?php echo $stock_class; ?>"><?php echo $stock; ?></span></td>
                                    <td><?php echo htmlspecialchars($statut !== '' ? $statut : '—'); ?></td>
                                    <td class="bi-table__actions">
                                        <a href="../produits/modifier.php?id=<?php echo (int) $p['id']; ?>" title="Fiche produit"><i class="fas fa-pen"></i></a>
                                        <a href="../produits/ajuster-stock.php?id=<?php echo (int) $p['id']; ?>" title="Ajuster le stock"><i class="fas fa-cubes"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <form method="get" action="barre-info.php" class="bi-rescan">
                <label for="c2"><i class="fas fa-barcode"></i> Scanner une autre barre</label>
                <div class="bi-scan-form__row">
                    <input type="text" id="c2" name="c" placeholder="FOUTA-BAR-…">
                    <button type="submit" class="bi-btn-primary">OK</button>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
