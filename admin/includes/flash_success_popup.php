<?php
/**
 * Popup de succès (message flash). Définir $flash_success_message (string non vide) avant include.
 */
if (!isset($flash_success_message) || $flash_success_message === '') {
    return;
}
$__flash_txt = htmlspecialchars((string) $flash_success_message, ENT_QUOTES, 'UTF-8');
unset($flash_success_message);
?>
<div class="admin-flash-popup" id="admin-flash-popup" role="alertdialog" aria-modal="true"
    aria-labelledby="admin-flash-popup-title">
    <div class="admin-flash-popup__panel">
        <div class="admin-flash-popup__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></div>
        <h2 id="admin-flash-popup-title" class="admin-flash-popup__title">Succès</h2>
        <p class="admin-flash-popup__text"><?php echo $__flash_txt; ?></p>
        <button type="button" class="admin-flash-popup__btn">
            OK
        </button>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('admin-flash-popup');
    if (!root) return;
    function closePopup() {
        root.classList.add('admin-flash-popup--closing');
        window.setTimeout(function () {
            if (root && root.parentNode) root.parentNode.removeChild(root);
        }, 260);
    }
    var btn = root.querySelector('.admin-flash-popup__btn');
    if (btn) btn.addEventListener('click', closePopup);
    root.addEventListener('click', function (e) {
        if (e.target === root) closePopup();
    });
})();
</script>
