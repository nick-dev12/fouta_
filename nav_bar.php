<?php
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/includes/asset_version.php';
}
$asset_version = isset($asset_version) ? $asset_version : get_asset_version();
// Compter les articles du panier si l'utilisateur est connecté
$panier_count = 0;
if (isset($_SESSION['user_id'])) {
    $conn_path = file_exists(__DIR__ . '/conn/conn.php') ? __DIR__ . '/conn/conn.php' : dirname(__DIR__) . '/conn/conn.php';
    if (file_exists($conn_path)) {
        require_once $conn_path;
    }
    $model_path = file_exists(__DIR__ . '/models/model_panier.php')
        ? __DIR__ . '/models/model_panier.php'
        : dirname(__DIR__) . '/models/model_panier.php';

    if (file_exists($model_path)) {
        require_once $model_path;
        $panier_count = count_panier_items($_SESSION['user_id']);
    }
}
?>
<link rel="stylesheet" href="/css/variables.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="/css/nabare.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
    /* Nav style Planète Gâteau - fond dégradé, barre recherche, Mon compte, panier */
    .nav-planete-gateau {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 12px 30px;
        background: var(--blanc);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.5);
    }

    .section1 {
        z-index: 100;
    }

    .nav-planete-gateau .logo {
        flex-shrink: 0;
    }

    .nav-planete-gateau .logo img {
        height: 70px;
        width: auto;
        max-width: 160px;
        object-fit: contain;
    }

    .nav-top-row {
        display: contents;
    }

    .nav-top-row .logo {
        order: 1;
    }

    .nav-search-wrapper {
        order: 2;
    }

    .nav-top-row .nav-panier-link {
        order: 3;
    }

    .nav-top-row .nav-compte-btn {
        order: 4;
    }

    /* Barre de recherche avec filtres */
    .nav-search-wrapper {
        display: flex;
        flex: 1;
        max-width: 500px;
        margin: 0 20px;
        position: relative;
        z-index: 9999;
    }

    .nav-search-form {
        display: flex;
        align-items: stretch;
        flex: 1;
        border-radius: 25px;
        overflow: hidden;
        box-shadow: var(--ombre-douce);
    }

    .nav-search-filters-btn {
        margin-left: 8px;
        padding: 12px 14px;
        background: var(--bleu-pale);
        border: 2px solid var(--border-input);
        border-radius: 12px;
        color: var(--couleur-dominante);
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-search-filters-btn:hover,
    .nav-search-filters-btn.active {
        background: var(--couleur-dominante);
        color: var(--texte-clair);
        border-color: var(--couleur-dominante);
    }

    .nav-search-filters-panel {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        margin-top: 10px;
        background: var(--blanc);
        border-radius: 14px;
        box-shadow: var(--glass-shadow);
        padding: 20px;
        z-index: 10001;
        display: none;
        border: 1px solid var(--border-input);
    }

    .nav-search-filters-panel.show {
        display: block;
    }

    .nav-search-filters-panel h4 {
        font-size: 14px;
        color: var(--titres);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-search-filters-panel h4 i {
        color: var(--couleur-dominante);
    }

    .nav-search-filters-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 15px;
    }

    .nav-search-filters-row:last-of-type {
        margin-bottom: 0;
    }

    .nav-search-filters-group {
        flex: 1;
        min-width: 120px;
    }

    .nav-search-filters-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--texte-fonce);
        margin-bottom: 6px;
    }

    .nav-search-filters-group input,
    .nav-search-filters-group select {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid var(--border-input);
        border-radius: 8px;
        font-size: 14px;
    }

    .nav-search-filters-group input:focus,
    .nav-search-filters-group select:focus {
        outline: none;
        border-color: var(--couleur-dominante);
    }

    .nav-search-filters-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .nav-search-filters-actions button {
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .nav-search-filters-actions .btn-apply {
        background: var(--couleur-dominante);
        color: var(--texte-clair);
    }

    .nav-search-filters-actions .btn-apply:hover {
        background: var(--couleur-dominante-hover);
    }

    .nav-search-filters-actions .btn-reset {
        background: var(--blanc);
        color: var(--texte-fonce);
    }

    .nav-search-filters-actions .btn-reset:hover {
        background: var(--blanc-neige);
    }

    .nav-search-btn {
        padding: 12px 20px;
        background: var(--couleur-dominante);
        border: none;
        color: var(--texte-clair);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s;
    }

    .nav-search-btn:hover {
        background: var(--couleur-dominante-hover);
    }

    .nav-search-btn i {
        font-size: 18px;
    }

    .nav-search-input {
        flex: 1;
        padding: 12px 20px;
        border: 2px solid var(--border-input);
        border-left: none;
        background: var(--blanc);
        font-size: 15px;
        outline: none;
        border-radius: 0 25px 25px 0;
    }

    .nav-search-input::placeholder {
        color: var(--gris-clair);
    }

    .nav-search-input:focus {
        border-color: var(--couleur-dominante);
    }

    /* Bouton Mon compte */
    .nav-compte-btn {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        padding: 10px 20px;
        background: var(--couleur-dominante);
        color: var(--texte-clair);
        text-decoration: none;
        border-radius: 25px;
        transition: all 0.3s;
        position: relative;
        min-width: 140px;
    }

    .nav-compte-btn:hover {
        background: var(--couleur-dominante-hover);
        color: var(--texte-clair);
        transform: translateY(-1px);
    }

    .nav-compte-title {
        font-size: 14px;
        font-weight: 700;
        display: block;
        line-height: 1.2;
    }

    .nav-compte-subtitle {
        font-size: 12px;
        opacity: 0.95;
        font-weight: 400;
    }

    .nav-compte-chevron {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        opacity: 0.9;
    }

    /* Panier */
    .nav-panier-link {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        color: var(--texte-fonce);
        text-decoration: none;
        transition: color 0.3s;
    }

    .nav-panier-link:hover {
        color: var(--couleur-dominante);
    }

    .nav-panier-link i {
        font-size: 26px;
    }

    .nav-panier-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background: var(--orange);
        color: var(--texte-clair);
        border-radius: 50%;
        min-width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        border: 2px solid var(--blanc);
        padding: 0 4px;
        box-shadow: var(--ombre-promo);
    }

    @media (max-width: 992px) {
        .nav-planete-gateau {
            padding: 10px 20px;
            gap: 12px;
        }

        .nav-planete-gateau .logo img {
            height: 55px;
        }

        .nav-search-wrapper {
            max-width: 320px;
        }

        .nav-search-filters-btn {
            padding: 10px 12px;
        }

        .nav-search-input {
            font-size: 14px;
            padding: 10px 16px;
        }

        .nav-compte-btn {
            min-width: 130px;
            padding: 8px 14px;
        }

        .nav-compte-title {
            font-size: 12px;
        }

        .nav-compte-subtitle {
            font-size: 11px;
        }
    }

    @media (max-width: 768px) {
        .nav-planete-gateau {
            flex-wrap: wrap;
            padding: 10px 12px;
            gap: 10px;
        }

        .nav-top-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            order: 1;
            flex-shrink: 0;
        }

        .nav-planete-gateau .logo {
            flex-shrink: 0;
        }

        .nav-planete-gateau .logo img {
            height: 45px;
            max-width: 120px;
        }

        .nav-panier-link {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
        }

        .nav-panier-link i {
            font-size: 22px;
        }

        .nav-panier-badge {
            min-width: 18px;
            height: 18px;
            font-size: 10px;
        }

        .nav-compte-btn {
            min-width: auto;
            padding: 8px 12px;
            flex-direction: row;
            gap: 6px;
            flex-shrink: 0;
        }

        .nav-compte-title {
            display: none;
        }

        .nav-compte-subtitle {
            font-size: 12px;
            font-weight: 600;
        }

        .nav-compte-chevron {
            display: none;
        }

        .nav-search-wrapper {
            order: 2;
            width: 100%;
            max-width: 100%;
            margin: 0;
            flex-direction: row;
        }

        .nav-search-form {
            flex: 1;
        }

        .nav-search-btn {
            padding: 10px 14px;
        }

        .nav-search-input {
            padding: 10px 14px;
            font-size: 14px;
        }

        .nav-search-filters-btn {
            padding: 10px 12px;
            flex-shrink: 0;
        }

        .nav-search-filters-panel {
            left: 0;
            right: 0;
            padding: 15px;
        }
    }

    @media (max-width: 480px) {
        .nav-planete-gateau {
            padding: 8px 10px;
            gap: 8px;
        }

        .nav-planete-gateau .logo img {
            height: 40px;
            max-width: 100px;
        }

        .nav-compte-btn {
            padding: 6px 10px;
        }

        .nav-compte-subtitle {
            font-size: 11px;
        }

        .nav-panier-link {
            width: 38px;
            height: 38px;
        }

        .nav-panier-link i {
            font-size: 20px;
        }

        .nav-search-btn {
            padding: 8px 12px;
        }

        .nav-search-input {
            padding: 8px 12px;
            font-size: 13px;
        }

        .nav-search-filters-btn {
            padding: 8px 10px;
        }
    }
</style>

<div class="info">

</div>
<nav class="nav-planete-gateau">
    <div class="nav-top-row">
        <a class="logo" href="/index.php">
            <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS">
        </a>
        <a href="<?php echo isset($_SESSION['user_id']) ? '/panier.php' : '/user/connexion.php?redirect=panier'; ?>"
            class="nav-panier-link"
            title="<?php echo isset($_SESSION['user_id']) ? 'Voir mon panier (' . $panier_count . ' article' . ($panier_count > 1 ? 's' : '') . ')' : 'Se connecter pour voir le panier'; ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <?php if (isset($_SESSION['user_id']) && $panier_count > 0): ?>
                <span class="nav-panier-badge"><?php echo $panier_count > 99 ? '99+' : $panier_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php
        if (isset($_SESSION['commercant_id']))
            echo '/view/profil_commercent.php';
        elseif (isset($_SESSION['user_id']))
            echo '/user/mon-compte.php';
        else
            echo '/user/connexion.php';
        ?>" class="nav-compte-btn">
            <span class="nav-compte-title">Mon compte</span>
            <span class="nav-compte-subtitle"><?php
            if (isset($_SESSION['commercant_id']) && isset($commercant) && !empty($commercant['nom'])) {
                $explode_nom = explode(' ', $commercant['nom']);
                echo htmlspecialchars($explode_nom[0] ?? $commercant['nom']);
            } elseif (isset($_SESSION['user_id']) && !empty($_SESSION['user_prenom'])) {
                echo htmlspecialchars($_SESSION['user_prenom']);
            } else {
                echo 'Identifiez-vous';
            }
            ?></span>
            <i class="fa-solid fa-chevron-down nav-compte-chevron"></i>
        </a>
    </div>

    <div class="nav-search-wrapper">
        <form class="nav-search-form" action="/produits.php" method="get" id="nav-search-form">
            <button type="submit" class="nav-search-btn" aria-label="Rechercher">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" name="recherche" id="nav-search" class="nav-search-input"
                placeholder="Que recherchez-vous ?"
                value="<?php echo !empty($_GET['recherche']) ? htmlspecialchars($_GET['recherche']) : ''; ?>">
            <input type="hidden" name="prix_min" id="nav-prix-min"
                value="<?php echo isset($_GET['prix_min']) ? htmlspecialchars($_GET['prix_min']) : ''; ?>">
            <input type="hidden" name="prix_max" id="nav-prix-max"
                value="<?php echo isset($_GET['prix_max']) ? htmlspecialchars($_GET['prix_max']) : ''; ?>">
            <input type="hidden" name="categorie" id="nav-categorie"
                value="<?php echo isset($_GET['categorie']) ? htmlspecialchars($_GET['categorie']) : ''; ?>">
            <input type="hidden" name="tri" id="nav-tri"
                value="<?php echo isset($_GET['tri']) ? htmlspecialchars($_GET['tri']) : ''; ?>">
        </form>
        <button type="button" class="nav-search-filters-btn" id="nav-filters-toggle" aria-label="Filtres"
            title="Filtres de recherche">
            <i class="fa-solid fa-sliders"></i>
        </button>
        <div class="nav-search-filters-panel" id="nav-filters-panel">
            <h4><i class="fa-solid fa-filter"></i> Filtres</h4>
            <div class="nav-search-filters-row">
                <div class="nav-search-filters-group">
                    <label for="filter-prix-min">Prix min (FCFA)</label>
                    <input type="number" id="filter-prix-min" name="prix_min" placeholder="0" min="0" step="100"
                        value="<?php echo isset($_GET['prix_min']) ? htmlspecialchars($_GET['prix_min']) : ''; ?>">
                </div>
                <div class="nav-search-filters-group">
                    <label for="filter-prix-max">Prix max (FCFA)</label>
                    <input type="number" id="filter-prix-max" name="prix_max" placeholder="Aucune limite" min="0"
                        step="100"
                        value="<?php echo isset($_GET['prix_max']) ? htmlspecialchars($_GET['prix_max']) : ''; ?>">
                </div>
            </div>
            <div class="nav-search-filters-row">
                <div class="nav-search-filters-group" style="flex: 1;">
                    <label for="filter-categorie">Catégorie</label>
                    <select id="filter-categorie" name="categorie">
                        <option value="">Toutes les catégories</option>
                        <?php if (!empty($categories_menu)): ?>
                            <?php foreach ($categories_menu as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['categorie']) && $_GET['categorie'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="nav-search-filters-row">
                <div class="nav-search-filters-group" style="flex: 1;">
                    <label for="filter-tri">Trier par</label>
                    <select id="filter-tri" name="tri">
                        <option value="">Plus récents</option>
                        <option value="prix_asc" <?php echo (isset($_GET['tri']) && $_GET['tri'] == 'prix_asc') ? 'selected' : ''; ?>>Prix
                            croissant</option>
                        <option value="prix_desc" <?php echo (isset($_GET['tri']) && $_GET['tri'] == 'prix_desc') ? 'selected' : ''; ?>>Prix
                            décroissant</option>
                        <option value="nom" <?php echo (isset($_GET['tri']) && $_GET['tri'] == 'nom') ? 'selected' : ''; ?>>Nom A-Z
                        </option>
                    </select>
                </div>
            </div>
            <div class="nav-search-filters-actions">
                <button type="button" class="btn-apply" onclick="appliquerFiltres()"><i class="fa-solid fa-check"></i>
                    Appliquer</button>
                <button type="button" class="btn-reset" onclick="reinitialiserFiltres()"><i
                        class="fa-solid fa-rotate-left"></i> Réinitialiser</button>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.getElementById('nav-filters-toggle');
            var panel = document.getElementById('nav-filters-panel');
            if (toggle && panel) {
                toggle.addEventListener('click', function () {
                    panel.classList.toggle('show');
                    toggle.classList.toggle('active', panel.classList.contains('show'));
                });
                document.addEventListener('click', function (e) {
                    if (!toggle.contains(e.target) && !panel.contains(e.target)) {
                        panel.classList.remove('show');
                        toggle.classList.remove('active');
                    }
                });
            }
        });

        function appliquerFiltres() {
            document.getElementById('nav-prix-min').value = document.getElementById('filter-prix-min').value;
            document.getElementById('nav-prix-max').value = document.getElementById('filter-prix-max').value;
            document.getElementById('nav-categorie').value = document.getElementById('filter-categorie').value;
            document.getElementById('nav-tri').value = document.getElementById('filter-tri').value;
            document.getElementById('nav-search-form').submit();
        }

        function reinitialiserFiltres() {
            document.getElementById('filter-prix-min').value = '';
            document.getElementById('filter-prix-max').value = '';
            document.getElementById('filter-categorie').value = '';
            document.getElementById('filter-tri').value = '';
            document.getElementById('nav-prix-min').value = '';
            document.getElementById('nav-prix-max').value = '';
            document.getElementById('nav-categorie').value = '';
            document.getElementById('nav-tri').value = '';
            document.getElementById('nav-search').value = '';
            document.getElementById('nav-search-form').submit();
        }
    </script>
</nav>

<?php
$categories_menu = [];
if (file_exists(__DIR__ . '/models/model_categories.php')) {
    require_once __DIR__ . '/models/model_categories.php';
    $categories_menu = get_all_categories();
}
?>

<!-- Overlay et sidebar menu latéral (apparaît au clic sur MENU) -->
<div class="nav-sidebar-overlay" id="navSidebarOverlay"></div>
<aside class="nav-sidebar" id="navSidebar">
    <div class="nav-sidebar-header">
        <a href="/index.php" class="nav-sidebar-logo">
            <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS">
        </a>
        <p class="nav-sidebar-slogan">FOUTA POIDS LOURDS</p>
    </div>
    <div class="nav-sidebar-content">
        <a href="/nouveautes.php" class="nav-sidebar-item nav-sidebar-nouveautes">
            <i class="fa-solid fa-cake-candles"></i>
            <span>NOUVEAUTÉS</span>
        </a>
        <a href="/promo.php" class="nav-sidebar-item nav-sidebar-promo">
            <i class="fa-solid fa-percent"></i>
            <span>PROMO</span>
        </a>
        <div class="nav-sidebar-categories">
            <?php if (!empty($categories_menu)): ?>
                <?php foreach ($categories_menu as $categorie): ?>
                    <a href="categorie.php?id=<?php echo $categorie['id']; ?>" class="nav-sidebar-category">
                        <span><?php echo htmlspecialchars($categorie['nom']); ?></span>
                        <span class="nav-sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="produits.php" class="nav-sidebar-category">
                    <span>Tous les produits</span>
                    <span class="nav-sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="nav-sidebar-footer">
        <a href="/contact.php" class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-phone"></i>
            <span>CONTACTEZ<br>NOUS</span>
        </a>
        <a href="/contact.php#livraison" class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-truck"></i>
            <span>PORTS ET<br>EXPÉDITION</span>
        </a>
        <a href="<?php echo isset($_SESSION['user_id']) ? '/user/mon-compte.php' : '/user/connexion.php'; ?>"
            class="nav-sidebar-footer-btn">
            <i class="fa-solid fa-briefcase"></i>
            <span>COMPTE<br>PRO</span>
        </a>
    </div>
</aside>

<section class="section1">
    <div class="section1-left">
        <button type="button" class="toggle-categories-btn" id="navMenuToggle" aria-label="Ouvrir le menu">
            <i class="fa-solid fa-bars"></i>
            <span>MENU</span>
        </button>
    </div>
    <div class="section1-right">
        <a href="/nouveautes.php" class="nav-action-btn nav-btn-nouveautes">
            <i class="fa-solid fa-gift"></i>
            <span>NOUVEAUTÉS</span>
        </a>
        <a href="/promo.php" class="nav-action-btn nav-btn-promo">
            <i class="fa-solid fa-percent"></i>
            <span>PROMO</span>
        </a>
        <a href="/contact.php" class="nav-action-btn nav-btn-contact">
            <i class="fa-solid fa-phone"></i>
            <span>CONTACT</span>
        </a>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('navMenuToggle');
        var sidebar = document.getElementById('navSidebar');
        var overlay = document.getElementById('navSidebarOverlay');

        function openMenu() {
            if (sidebar) sidebar.classList.add('open');
            if (overlay) overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
            var icon = toggle ? toggle.querySelector('i') : null;
            if (icon) { icon.classList.remove('fa-bars'); icon.classList.add('fa-times'); }
        }
        function closeMenu() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('show');
            document.body.style.overflow = '';
            var icon = toggle ? toggle.querySelector('i') : null;
            if (icon) { icon.classList.remove('fa-times'); icon.classList.add('fa-bars'); }
        }

        if (toggle) toggle.addEventListener('click', function () {
            if (sidebar && sidebar.classList.contains('open')) closeMenu();
            else openMenu();
        });
        if (overlay) overlay.addEventListener('click', closeMenu);

        window.addEventListener('resize', function () {
            if (window.innerWidth > 992 && sidebar && sidebar.classList.contains('open')) closeMenu();
        });
    });
</script>