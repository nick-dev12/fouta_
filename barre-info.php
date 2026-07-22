<?php
/**
 * Page publique affichée lors du scan du QR code d’une barre entrepôt.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start();

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/models/model_entrepot_referentiel.php';
require_once __DIR__ . '/includes/entrepot_barcode_service.php';
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/fpl_public_branding.php';

if (file_exists(__DIR__ . '/includes/asset_version.php')) {
    require_once __DIR__ . '/includes/asset_version.php';
}

$code = '';
if (isset($_GET['c'])) {
    $code = strtoupper(trim((string) $_GET['c']));
} elseif (isset($_POST['c'])) {
    $code = strtoupper(trim((string) $_POST['c']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $code !== '') {
    header('Location: barre-info.php?c=' . rawurlencode($code));
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
        $error_scan = 'Code barre inconnu.';
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

$brand = fpl_public_branding_coords();
$logo_url = fpl_public_branding_logo_url();
$qr_web = ($barre !== null) ? get_qrcode_barre_web_path((int) $barre['id']) : '';

$page_title = $barre ? ('Barre — ' . ($barre['nom'] ?? '')) : 'Barre entrepôt';
$av = function_exists('asset_version_query') ? asset_version_query() : '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo $av; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/barre-info-public.css<?php echo $av; ?>">
</head>

<body class="page-barre-info-public">
    <div class="bip-shell<?php echo $barre === null ? ' bip-shell--narrow' : ''; ?>">
        <?php if ($barre === null): ?>
            <section class="bip-card bip-card--scan">
                <div class="bip-scan-icon" aria-hidden="true"><i class="fas fa-qrcode"></i></div>
                <h1 class="bip-title">Barre entrepôt</h1>
                <p class="bip-lead">Scannez le QR code collé sur la barre ou saisissez le code <code>FOUTA-BAR-…</code>.</p>
                <?php if ($error_scan !== ''): ?>
                    <div class="bip-alert" role="alert">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($error_scan); ?>
                    </div>
                <?php endif; ?>
                <form method="get" action="barre-info.php" class="bip-form" autocomplete="off">
                    <label for="c">Code de la barre</label>
                    <div class="bip-form__row">
                        <input type="text" id="c" name="c" value="<?php echo htmlspecialchars($code); ?>"
                            placeholder="FOUTA-BAR-000001" autofocus required>
                        <button type="submit" class="bip-btn">Afficher</button>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <header class="bip-masthead">
                <div class="bip-masthead__brand">
                    <div class="bip-masthead__brand-top">
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="" class="bip-masthead__logo" width="64" height="64">
                        <div>
                            <p class="bip-masthead__company"><?php echo htmlspecialchars($brand['nom']); ?></p>
                            <p class="bip-masthead__tagline"><?php echo htmlspecialchars($brand['tagline']); ?></p>
                        </div>
                    </div>
                    <ul class="bip-masthead__legal">
                        <li><strong>R.C :</strong> <?php echo htmlspecialchars($brand['rc']); ?></li>
                        <li><strong>N.I.N.E.A :</strong> <?php echo htmlspecialchars($brand['ninea']); ?></li>
                        <li><?php echo htmlspecialchars($brand['adresse']); ?></li>
                        <li><a href="<?php echo htmlspecialchars($brand['telephone_href']); ?>"><?php echo htmlspecialchars($brand['telephone']); ?></a></li>
                        <li>
                            <a href="<?php echo htmlspecialchars($brand['site']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($brand['site']); ?></a>
                            · <a href="mailto:<?php echo htmlspecialchars($brand['email']); ?>"><?php echo htmlspecialchars($brand['email']); ?></a>
                        </li>
                    </ul>
                </div>
                <div class="bip-masthead__barre">
                    <div class="bip-masthead__barre-head">
                        <div class="bip-masthead__barre-text">
                            <p class="bip-masthead__kicker"><i class="fas fa-grip-lines" aria-hidden="true"></i> Barre entrepôt</p>
                            <h1 class="bip-masthead__barre-title"><?php echo htmlspecialchars($barre['nom'] ?? 'Barre'); ?></h1>
                            <?php if ($chemin !== ''): ?>
                                <p class="bip-masthead__chemin"><?php echo htmlspecialchars($chemin); ?></p>
                            <?php endif; ?>
                            <p class="bip-masthead__code"><code><?php echo htmlspecialchars($barre['code_scan'] ?? $code); ?></code></p>
                        </div>
                        <?php if ($qr_web !== ''): ?>
                        <div class="bip-masthead__qr-wrap">
                            <img src="<?php echo htmlspecialchars($qr_web); ?>" alt="QR code barre" class="bip-masthead__qr" width="88" height="88">
                            <span class="bip-masthead__qr-label">QR barre</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="bip-path-strip" aria-label="Position détaillée">
                        <span class="bip-path-pill">
                            <span class="bip-path-pill__label">Niveau</span>
                            <span class="bip-path-pill__value"><?php echo htmlspecialchars($contexte['etage_nom'] ?? '—'); ?></span>
                        </span>
                        <span class="bip-path-pill">
                            <span class="bip-path-pill__label">Rayon</span>
                            <span class="bip-path-pill__value"><?php echo htmlspecialchars($contexte['rayon_nom'] ?? '—'); ?></span>
                        </span>
                        <span class="bip-path-pill">
                            <span class="bip-path-pill__label">Allée</span>
                            <span class="bip-path-pill__value"><?php echo htmlspecialchars($contexte['allee_nom'] ?? '—'); ?></span>
                        </span>
                        <span class="bip-path-pill">
                            <span class="bip-path-pill__label">Zone</span>
                            <span class="bip-path-pill__value"><?php echo htmlspecialchars($contexte['zone_nom'] ?? '—'); ?></span>
                        </span>
                        <span class="bip-path-pill bip-path-pill--accent">
                            <span class="bip-path-pill__label">Barre</span>
                            <span class="bip-path-pill__value"><?php echo htmlspecialchars($contexte['barre_nom'] ?? '—'); ?></span>
                        </span>
                    </div>
                    <ul class="bip-kpis">
                        <li><strong><?php echo (int) $nb; ?></strong><span>Produit(s)</span></li>
                        <li><strong><?php echo (int) $stock_total; ?></strong><span>Stock total</span></li>
                        <li><strong><?php echo (int) ($contexte['rayon_num'] ?? 0); ?></strong><span>Rayon n°</span></li>
                    </ul>
                </div>
            </header>

            <section class="bip-table-card" aria-labelledby="bip-products-title">
                <div class="bip-table-card__head">
                    <h2 id="bip-products-title" class="bip-table-card__title">
                        <i class="fas fa-boxes-stacked" aria-hidden="true"></i> Produits sur cette barre
                    </h2>
                    <span class="bip-table-card__count"><?php echo (int) $nb; ?> référence(s)</span>
                </div>
                <?php if ($produits === []): ?>
                    <div class="bip-empty">
                        <i class="fas fa-box-open" aria-hidden="true"></i>
                        <p>Aucun produit assigné à cette barre pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="bip-table-scroll">
                        <table class="bip-table">
                            <thead>
                                <tr>
                                    <th scope="col">Réf. FPL</th>
                                    <th scope="col">Produit</th>
                                    <th scope="col">Position</th>
                                    <th scope="col">Stock</th>
                                    <th scope="col">Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produits as $p):
                                    $stock = (int) ($p['stock'] ?? 0);
                                    $stock_class = $stock <= 0 ? 'is-zero' : ($stock <= 5 ? 'is-low' : 'is-ok');
                                    $pid = (int) ($p['id'] ?? 0);
                                ?>
                                <tr>
                                    <td class="bip-table__ref"><code><?php echo htmlspecialchars($p['identifiant_interne'] ?? '—'); ?></code></td>
                                    <td class="bip-table__nom"><?php echo htmlspecialchars($p['nom'] ?? ''); ?></td>
                                    <td>
                                        <span class="bip-pos-badge">
                                            <?php echo htmlspecialchars($p['position_nom'] ?? ('Position #' . (int) ($p['position_num'] ?? 0))); ?>
                                        </span>
                                    </td>
                                    <td><span class="bip-stock <?php echo $stock_class; ?>"><?php echo $stock; ?></span></td>
                                    <td>
                                        <?php if ($pid > 0): ?>
                                        <a href="stock-info.php?id=<?php echo $pid; ?>" class="bip-table__link">
                                            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Fiche
                                        </a>
                                        <?php else: ?>
                                        —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <form method="get" action="barre-info.php" class="bip-rescan">
                <label for="c2"><i class="fas fa-barcode" aria-hidden="true"></i> Scanner une autre barre</label>
                <div class="bip-form__row">
                    <input type="text" id="c2" name="c" placeholder="FOUTA-BAR-…">
                    <button type="submit" class="bip-btn">OK</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
