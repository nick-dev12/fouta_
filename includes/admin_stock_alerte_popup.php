<?php
/**
 * Bandeau / popup temporaire (30 s) — produits sous seuil d’alerte.
 * À inclure dans le body des pages admin concernées (après session + accès).
 */
if (!isset($_SESSION['admin_id'])) {
    return;
}
require_once __DIR__ . '/admin_permissions.php';
if (!admin_can_receive_stock_alerte_popup()) {
    return;
}
require_once __DIR__ . '/produit_formulaire_champs.php';
if (!pf_champ_visible('stock')) {
    return;
}
require_once __DIR__ . '/../models/model_stock_alertes.php';

$__stock_alert_popup = stock_alertes_resume_pour_popup(25);
if (($__stock_alert_popup['total'] ?? 0) < 1) {
    return;
}
$__items = $__stock_alert_popup['items'];
$__total = (int) $__stock_alert_popup['total'];
$__rest = max(0, $__total - count($__items));
?>
<div id="stock-alerte-popup-root" class="stock-alerte-popup" role="alert" aria-live="polite">
    <div class="stock-alerte-popup__inner">
        <button type="button" class="stock-alerte-popup__close" onclick="document.getElementById('stock-alerte-popup-root').remove()" aria-label="Fermer l’alerte">&times;</button>
        <div class="stock-alerte-popup__title">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span>Alerte stock</span>
            <span class="stock-alerte-popup__badge"><?php echo $__total; ?> produit<?php echo $__total > 1 ? 's' : ''; ?></span>
        </div>
        <p class="stock-alerte-popup__lead">Au moins un produit est en dessous d’un seuil configuré — réapprovisionnement ou vérification recommandé(e).</p>
        <ul class="stock-alerte-popup__list">
            <?php foreach ($__items as $it): ?>
            <li>
                <span class="stock-alerte-popup__nom"><?php echo htmlspecialchars($it['nom'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="stock-alerte-popup__meta">
                    <span class="stock-alerte-popup__niveau stock-alerte-popup__niveau--<?php echo htmlspecialchars($it['niveau'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($it['niveau_libelle'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="stock-alerte-popup__stock"><?php echo (int) $it['stock']; ?> en stock<?php $__ref = isset($it['seuil_ref']) ? (string) $it['seuil_ref'] : ''; if ($__ref !== '') { echo ' · seuil&nbsp;≤&nbsp;' . htmlspecialchars($__ref, ENT_QUOTES, 'UTF-8'); } ?></span>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($__rest > 0): ?>
        <p class="stock-alerte-popup__more">… et <?php echo $__rest; ?> autre(s) — voir Produits ou Stock.</p>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('stock-alerte-popup-root');
    if (!root) return;
    window.setTimeout(function () {
        if (root && root.parentNode) {
            root.classList.add('stock-alerte-popup--out');
            window.setTimeout(function () {
                try { root.remove(); } catch (e) {}
            }, 420);
        }
    }, 30000);
})();
</script>
