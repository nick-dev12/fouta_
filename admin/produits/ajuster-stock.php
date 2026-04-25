<?php
/**
 * Page d'ajustement du stock d'un produit
 * Affiche: stock total, quantité vendue, stock restant (total - vendu), comptabilité, formulaire d'ajustement, historique
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_ajuster_stock_produit($produit_id);

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: ajuster-stock.php?id=' . $produit_id);
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_commandes.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/barcode_fpl.php';
$code_fpl_live = ensure_produit_identifiant_interne($produit_id);
if ($code_fpl_live !== null && $code_fpl_live !== '') {
    $produit['identifiant_interne'] = $code_fpl_live;
}
if (get_barcode_produit_web_path($produit_id) === '') {
    generer_barcode_produit_fpl($produit_id);
}
$barcode_url = get_barcode_produit_web_path($produit_id);

$quantite_vendue = get_quantite_vendue_produit($produit_id);
$stock_actuel = (int) ($produit['stock'] ?? 0);
$nombre_total = $stock_actuel + $quantite_vendue;
$stock_restant = $nombre_total - $quantite_vendue;

$prix_produit = (float) ($produit['prix'] ?? 0);
if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < $prix_produit) {
    $prix_produit = (float) $produit['prix_promotion'];
}
$valeur_stock_actuel = $stock_actuel * $prix_produit;
$valeur_ventes = $quantite_vendue * $prix_produit;

$mouvements = get_stock_mouvements(null, $produit_id, null, null, 50);

// QR code : utiliser le fichier sauvegardé ou générer à la volée
$qr_code_data_uri = '';
$stock_info_url = '';
$qr_file = __DIR__ . '/../../upload/qrcodes/produit_' . $produit_id . '.png';
require_once __DIR__ . '/../../includes/site_url.php';
$stock_info_url = get_site_base_url() . '/stock-info.php?id=' . $produit_id;
if (file_exists($qr_file)) {
    $qr_code_data_uri = 'data:image/png;base64,' . base64_encode(file_get_contents($qr_file));
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale'        => 8,
            'outputBase64' => true,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $qr_code_data_uri = $qr->render($stock_info_url);
    } catch (Throwable $e) {
        try {
            $qro = new \chillerlan\QRCode\QROptions([
                'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
                'scale'        => 8,
                'outputBase64' => true,
            ]);
            $qr = new \chillerlan\QRCode\QRCode($qro);
            $qr_code_data_uri = $qr->render($stock_info_url);
        } catch (Throwable $e2) {
            $qr_code_data_uri = '';
        }
    }
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuster le stock - <?php echo htmlspecialchars($produit['nom']); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-ajuster-stock.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-ajuster-stock-body">

    <?php include '../includes/nav.php'; ?>

    <div class="page-ajuster-stock">
        <div class="content-header dashboard-hero page-ajuster-stock-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Stock &amp; inventaire</p>
                <h1 id="page-ajuster-stock-title"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Ajuster le stock</h1>
                <p class="dashboard-subtitle page-ajuster-stock-hero__intro">
                    Produit <strong class="page-ajuster-stock-hero__nom"><?php echo htmlspecialchars($produit['nom']); ?></strong>
                </p>
                <div class="page-ajuster-stock-hero__actions">
                    <a href="index.php" class="btn-back page-ajuster-stock-back">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
                    </a>
                </div>
            </div>
        </div>

    <?php if (!empty($success_message)): ?>
        <div class="message success page-ajuster-stock-flash page-ajuster-stock-flash--success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
        <div class="message error page-ajuster-stock-flash page-ajuster-stock-flash--error" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($result['message']); ?>
        </div>
    <?php endif; ?>

    <div class="produit-preview page-ajuster-stock-preview" aria-label="Aperçu produit">
        <div class="page-ajuster-stock-preview__media">
            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>"
                alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                onerror="this.src='/image/produit1.jpg'" width="96" height="96" loading="eager" decoding="async">
        </div>
        <div class="produit-preview-info page-ajuster-stock-preview__info">
            <h3 class="page-ajuster-stock-preview__title"><?php echo htmlspecialchars($produit['nom']); ?></h3>
            <span class="prix page-ajuster-stock-preview__prix"><?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA <span class="page-ajuster-stock-preview__prix-unit">/ unité</span></span>
            <p class="page-ajuster-stock-preview__legend">Prix retenu pour la valorisation (promo si applicable).</p>
            <?php
            $meta_ref = !empty($produit['identifiant_interne']) ? trim((string) $produit['identifiant_interne']) : '';
            $meta_etage = isset($produit['etage']) && (string) $produit['etage'] !== '' ? trim((string) $produit['etage']) : '';
            $meta_rayon = isset($produit['numero_rayon']) && (string) $produit['numero_rayon'] !== '' ? trim((string) $produit['numero_rayon']) : '';
            $has_preview_meta = ($meta_ref !== '' || $meta_etage !== '' || $meta_rayon !== '');
            ?>
            <?php if ($has_preview_meta): ?>
            <div class="page-ajuster-stock-meta-cards" role="list" aria-label="Informations magasin">
                <?php if ($meta_ref !== ''): ?>
                <div class="page-ajuster-stock-meta-card" role="listitem">
                    <span class="page-ajuster-stock-meta-card__ic" aria-hidden="true"><i class="fas fa-barcode"></i></span>
                    <div class="page-ajuster-stock-meta-card__body">
                        <span class="page-ajuster-stock-meta-card__label">Référence FPL</span>
                        <span class="page-ajuster-stock-meta-card__value"><?php echo htmlspecialchars($meta_ref); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($meta_etage !== ''): ?>
                <div class="page-ajuster-stock-meta-card" role="listitem">
                    <span class="page-ajuster-stock-meta-card__ic" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <div class="page-ajuster-stock-meta-card__body">
                        <span class="page-ajuster-stock-meta-card__label">Étage</span>
                        <span class="page-ajuster-stock-meta-card__value"><?php echo htmlspecialchars($meta_etage); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($meta_rayon !== ''): ?>
                <div class="page-ajuster-stock-meta-card" role="listitem">
                    <span class="page-ajuster-stock-meta-card__ic" aria-hidden="true"><i class="fas fa-th-large"></i></span>
                    <div class="page-ajuster-stock-meta-card__body">
                        <span class="page-ajuster-stock-meta-card__label">N° rayon</span>
                        <span class="page-ajuster-stock-meta-card__value"><?php echo htmlspecialchars($meta_rayon); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ajuster-stock-layout page-ajuster-stock-layout">
        <div class="ajuster-stock-card page-ajuster-stock-card page-ajuster-stock-card--etat">
            <h2 class="page-ajuster-stock-card__title"><i class="fas fa-chart-bar" aria-hidden="true"></i> État du stock</h2>
            <p class="page-ajuster-stock-card__hint">Le <strong>total</strong> = stock actuel + quantités déjà vendues (historique). Le <strong>restant</strong> correspond au stock saisi en base.</p>
            <div class="stock-stats-grid page-ajuster-stock-stats" role="list">
                <div class="stock-stat-card stock-total page-ajuster-stock-stat" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Nombre total</h4>
                    <div class="value page-ajuster-stock-stat__value" aria-label="Total cumulé"><?php echo (int) $nombre_total; ?></div>
                </div>
                <div class="stock-stat-card stock-vendu page-ajuster-stock-stat" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Quantité vendue</h4>
                    <div class="value page-ajuster-stock-stat__value" aria-label="Unités vendues"><?php echo (int) $quantite_vendue; ?></div>
                </div>
                <div class="stock-stat-card stock-restant page-ajuster-stock-stat" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Stock restant</h4>
                    <div class="value page-ajuster-stock-stat__value" aria-label="Stock actuel"><?php echo (int) $stock_restant; ?></div>
                </div>
            </div>

            <h2 class="page-ajuster-stock-card__title page-ajuster-stock-card__title--spaced"><i class="fas fa-calculator" aria-hidden="true"></i> Comptabilité (valorisation)</h2>
            <div class="comptabilite-grid page-ajuster-stock-compta">
                <div class="comptabilite-item page-ajuster-stock-compta__item">
                    <label class="page-ajuster-stock-compta__label">Valeur du stock actuel</label>
                    <span class="montant page-ajuster-stock-compta__montant"><?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail page-ajuster-stock-compta__detail"><?php echo (int) $stock_actuel; ?> ×
                        <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="comptabilite-item page-ajuster-stock-compta__item">
                    <label class="page-ajuster-stock-compta__label">Chiffre d'affaires (ventes)</label>
                    <span class="montant page-ajuster-stock-compta__montant"><?php echo number_format($valeur_ventes, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail page-ajuster-stock-compta__detail"><?php echo (int) $quantite_vendue; ?> vendu(s) ×
                        <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</span>
                </div>
            </div>
        </div>

        <div class="page-ajuster-stock-side">
            <div class="stock-form-block page-ajuster-stock-form">
                <h3 class="page-ajuster-stock-form__title"><i class="fas fa-edit" aria-hidden="true"></i> Mettre à jour le stock</h3>
                <p class="page-ajuster-stock-form__intro">Saisissez la <strong>quantité réelle</strong> disponible. Les ventes enregistrées ne sont pas modifiées.</p>
                <form method="POST" action="?id=<?php echo $produit_id; ?>" class="page-ajuster-stock-form__form">
                    <input type="hidden" name="ajuster_stock" value="1">
                    <div class="form-group page-ajuster-stock-form__field">
                        <label for="nouveau_stock">Nouvelle quantité de stock</label>
                        <input type="number" id="nouveau_stock" name="nouveau_stock" min="0" required
                            value="<?php echo (int) $stock_actuel; ?>" placeholder="0" inputmode="numeric" autocomplete="off">
                    </div>
                    <button type="submit" class="btn-primary page-ajuster-stock-form__submit">
                        <i class="fas fa-check" aria-hidden="true"></i> Enregistrer le stock
                    </button>
                </form>
            </div>

            <?php if (!empty($barcode_url) && !empty($produit['identifiant_interne'])): ?>
            <div class="stock-form-block barcode-fpl-block page-ajuster-stock-aux" id="barcode-fpl-print-area"
                data-barcode-src="<?php echo htmlspecialchars($barcode_url); ?>"
                data-code="<?php echo htmlspecialchars($produit['identifiant_interne']); ?>"
                data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-barcode" aria-hidden="true"></i> Code-barres (réf. FPL)</h3>
                <p class="barcode-fpl-desc page-ajuster-stock-aux__desc">Code <strong>Code 128</strong> : même référence que sur l’étiquette produit. Utilisable avec un scanner ou l’API <code>/api/produit_par_code_fpl.php</code>.</p>
                <div class="barcode-fpl-wrap page-ajuster-stock-barcode-wrap">
                    <?php
                    $barcode_fs = __DIR__ . '/../../upload/barcodes/produit_' . $produit_id . '.png';
                    $barcode_ver = is_file($barcode_fs) ? (int) filemtime($barcode_fs) : 1;
                    ?>
                    <img src="<?php echo htmlspecialchars($barcode_url); ?>?v=<?php echo $barcode_ver; ?>" alt="Code-barres <?php echo htmlspecialchars($produit['identifiant_interne']); ?>" class="barcode-fpl-img page-ajuster-stock-barcode-img" width="280" height="100">
                    <div class="barcode-fpl-code"><?php echo htmlspecialchars($produit['identifiant_interne']); ?></div>
                </div>
                <div class="barcode-fpl-actions page-ajuster-stock-aux__actions">
                    <button type="button" class="btn-primary btn-print-barcode page-ajuster-stock-print-btn" onclick="imprimerCodeBarresFPL()">
                        <i class="fas fa-print" aria-hidden="true"></i> Imprimer le code-barres
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($qr_code_data_uri)): ?>
            <div class="stock-form-block qr-code-block page-ajuster-stock-aux" id="qr-code-print-area" data-qr="<?php echo htmlspecialchars($qr_code_data_uri); ?>" data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-qrcode" aria-hidden="true"></i> QR code du produit</h3>
                <p class="qr-code-desc page-ajuster-stock-aux__desc">Scannez ce QR code pour afficher les détails du stock sur mobile.</p>
                <div class="qr-code-wrap page-ajuster-stock-qr-wrap">
                    <img src="<?php echo htmlspecialchars($qr_code_data_uri); ?>" alt="QR Code - <?php echo htmlspecialchars($produit['nom']); ?>" class="qr-code-img" width="180" height="180">
                </div>
                <p class="qr-code-produit"><?php echo htmlspecialchars($produit['nom']); ?></p>
                <div class="qr-code-actions page-ajuster-stock-aux__actions">
                    <button type="button" class="btn-primary btn-print-qr page-ajuster-stock-print-btn" onclick="imprimerQRCode()">
                        <i class="fas fa-print" aria-hidden="true"></i> Imprimer le QR code
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <section class="mouvements-section page-ajuster-stock-mouvements" aria-labelledby="page-ajuster-stock-mouv-heading">
        <h2 id="page-ajuster-stock-mouv-heading" class="page-ajuster-stock-mouv__head"><i class="fas fa-history" aria-hidden="true"></i> Historique des mouvements <span class="page-ajuster-stock-mouv__count">(<?php echo count($mouvements); ?>)</span></h2>
        <?php if (empty($mouvements)): ?>
            <p class="page-ajuster-stock-mouv__empty">Aucun mouvement enregistré pour ce produit.</p>
        <?php else: ?>
            <div class="mouvements-produit-table-wrap page-ajuster-stock-mouv-table-wrap" tabindex="0" role="region" aria-label="Tableau des mouvements de stock">
                <table class="mouvements-produit-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Type</th>
                            <th scope="col">Quantité</th>
                            <th scope="col">Avant</th>
                            <th scope="col">Après</th>
                            <th scope="col">Référence</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $m): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                <td>
                                    <?php
                                    $badge = 'badge-' . $m['type'];
                                    $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                                    ?>
                                    <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                                </td>
                                <td><?php echo (int) $m['quantite']; ?></td>
                                <td><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></td>
                                <td><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></td>
                                <td><?php echo htmlspecialchars($m['reference_numero'] ?? ($m['reference_type'] ?? '-')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($m['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mouvements-produit-cards">
                <?php foreach ($mouvements as $m):
                    $badge = 'badge-' . $m['type'];
                    $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                    $ref = htmlspecialchars($m['reference_numero'] ?? ($m['reference_type'] ?? '-'));
                ?>
                <div class="mouvement-produit-card">
                    <div class="mouvement-produit-card-header">
                        <span class="mouvement-produit-card-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></span>
                        <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                    </div>
                    <div class="mouvement-produit-card-body">
                        <div class="mouvement-produit-card-row">
                            <span class="label">Quantité</span>
                            <span class="value"><?php echo (int) $m['quantite']; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Avant</span>
                            <span class="value"><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Après</span>
                            <span class="value"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Référence</span>
                            <span class="value"><?php echo $ref; ?></span>
                        </div>
                    </div>
                    <?php if (!empty($m['notes'])): ?>
                    <div class="mouvement-produit-card-notes"><?php echo htmlspecialchars($m['notes']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    </div><!-- .page-ajuster-stock -->

    <?php include '../includes/footer.php'; ?>

    <script>
    function imprimerCodeBarresFPL() {
        var block = document.getElementById('barcode-fpl-print-area');
        if (!block) return;
        var src = block.getAttribute('data-barcode-src');
        var code = block.getAttribute('data-code') || '';
        var nom = block.getAttribute('data-nom') || 'Produit';
        if (!src) return;
        var w = window.open('', '_blank', 'width=420,height=360');
        w.document.write('<!DOCTYPE html><html><head><title>Code-barres ' + code + '</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;} img{max-width:100%;height:auto;} .code{font-size:18px;font-weight:700;margin-top:12px;letter-spacing:0.08em;font-family:monospace;} h2{font-size:15px;margin:0 0 8px;text-align:center;color:#333;}</style></head><body><h2>' + nom.replace(/</g,'&lt;') + '</h2><img src="' + src + '" alt="Code-barres"><div class="code">' + code.replace(/</g,'&lt;') + '</div><p style="font-size:12px;color:#666;">Référence FPL</p></body></html>');
        w.document.close();
        w.focus();
        setTimeout(function() { w.print(); w.close(); }, 300);
    }
    function imprimerQRCode() {
        var block = document.getElementById('qr-code-print-area');
        if (!block) return;
        var qr = block.getAttribute('data-qr');
        var nom = block.getAttribute('data-nom') || 'Produit';
        var w = window.open('', '_blank', 'width=400,height=500');
        w.document.write('<!DOCTYPE html><html><head><title>QR Code - ' + nom + '</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;} img{max-width:280px;height:auto;} h2{font-size:16px;margin-top:16px;text-align:center;}</style></head><body><img src="' + qr + '" alt="QR Code"><h2>' + nom + '</h2><p style="font-size:12px;color:#666;">Scannez pour voir le stock</p></body></html>');
        w.document.close();
        w.focus();
        setTimeout(function() { w.print(); w.close(); }, 300);
    }
    </script>
</body>

</html>