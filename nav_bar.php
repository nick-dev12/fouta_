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

$categories_menu = [];
if (file_exists(__DIR__ . '/models/model_categories.php')) {
    require_once __DIR__ . '/models/model_categories.php';
    $categories_menu = get_all_categories();
}
?>
<?php require_once __DIR__ . '/includes/fpl_assets.php'; fpl_public_styles(['/css/nabare.css', '/css/gtranslate-nav.css']); ?>
<link rel="stylesheet" href="/css/fpl-public-nav.css<?php echo $asset_version ? '?v=' . $asset_version : ''; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        <div class="nav-lang-wrap" role="navigation" aria-label="Langue du site">
            <div id="gtranslate_dropdown_nav" class="gtranslate-dropdown-mount"></div>
        </div>
    </div>
    <script>
        window.gtranslateSettings = {
            default_language: 'fr',
            languages: ['fr', 'en', 'es'],
            wrapper_selector: '#gtranslate_dropdown_nav',
            native_language_names: false,
            /* Français par défaut : ne pas passer sur la langue du navigateur */
            detect_browser_language: false
        };
    </script>
    <script src="https://cdn.gtranslate.net/widgets/latest/dropdown.js" defer></script>
    <script>
        /**
         * Façade pilule : drapeau + code ISO (FR / EN / ES), chevron —
         * sync avec le select GTranslate (toujours fonctionnel au clic).
         */
        (function () {
            var LANG_FACE = {
                fr: { code: 'FR', flag: 'https://flagcdn.com/w80/fr.png' },
                en: { code: 'EN', flag: 'https://flagcdn.com/w80/gb.png' },
                es: { code: 'ES', flag: 'https://flagcdn.com/w80/es.png' }
            };

            /**
             * GTranslate pose souvent le cookie googtrans=/fr/en (traduction vers EN).
             * Le <select> peut encore afficher « Select Language » après changement :
             * on lit donc ce cookie comme source principale lorsqu’elle est présente.
             */
            function readGtCookieTargetLang() {
                try {
                    var mm = document.cookie.match(/(?:^|;)\s*googtrans=([^;]+)/i);
                    if (!mm) {
                        return null;
                    }
                    var raw = decodeURIComponent(mm[1].trim().replace(/^["']+|["';]+$/g, ''));
                    if (raw.indexOf('/') !== 0) {
                        raw = '/' + String(raw).replace(/^\/+/, '');
                    }
                    var segments = raw.split(/\//).map(function (s) {
                        return (s || '').trim().toLowerCase();
                    }).filter(function (s) {
                        return s && s !== 'auto';
                    });
                    if (segments.length === 0) {
                        return null;
                    }
                    /* Ex. /fr/en → langue affichée = en ; /auto/en → en */
                    var last = segments[segments.length - 1];
                    if (LANG_FACE[last]) {
                        return last;
                    }
                    for (var s = segments.length - 1; s >= 0; s--) {
                        if (LANG_FACE[segments[s]]) {
                            return segments[s];
                        }
                    }
                } catch (e) {}
                return null;
            }

            function decodeOptionLang(opt) {
                if (!opt) {
                    return null;
                }
                var val = String(opt.value || '').trim().toLowerCase();
                if (val && LANG_FACE[val]) {
                    return val;
                }
                var parts = val.split(/[|/]+/).map(function (s) {
                    return s.trim();
                }).filter(Boolean);
                var p;
                for (p = 0; p < parts.length; p++) {
                    var seg = parts[p].toLowerCase();
                    if (LANG_FACE[seg]) {
                        return seg;
                    }
                }
                var tx = (opt.textContent || '').toLowerCase();
                if (tx.indexOf('french') !== -1 || tx.indexOf('français') !== -1) {
                    return 'fr';
                }
                if (tx.indexOf('english') !== -1 || tx.indexOf('anglais') !== -1) {
                    return 'en';
                }
                if (tx.indexOf('spanish') !== -1 || tx.indexOf('español') !== -1) {
                    return 'es';
                }
                if (val.indexOf('fr') !== -1) {
                    return 'fr';
                }
                if (val.indexOf('en') !== -1) {
                    return 'en';
                }
                if (val.indexOf('es') !== -1) {
                    return 'es';
                }
                return null;
            }

            function resolveLang(sel) {
                var v = (sel.value || '').trim().toLowerCase();
                if (v && LANG_FACE[v]) {
                    return v;
                }
                /*
                 * Cookie googtrans reflète la langue active après traduction (souvent avant que le select UI soit cohérent).
                 */
                var ck = readGtCookieTargetLang();
                if (ck) {
                    return ck;
                }

                var optSel = null;
                var oi;
                for (oi = 0; oi < sel.options.length; oi++) {
                    if (sel.options[oi].selected) {
                        optSel = sel.options[oi];
                        break;
                    }
                }
                var fromOpt = decodeOptionLang(optSel);
                if (fromOpt) {
                    return fromOpt;
                }

                if (sel.selectedIndex >= 0) {
                    var alt = decodeOptionLang(sel.options[sel.selectedIndex]);
                    if (alt) {
                        return alt;
                    }
                }
                return 'fr';
            }

            function setFace(sel, flagEl, codeEl, pillEl) {
                var key = resolveLang(sel);
                if (!LANG_FACE[key]) {
                    key = 'fr';
                }
                var meta = LANG_FACE[key];
                flagEl.src = meta.flag;
                flagEl.dataset.langKey = key;
                flagEl.alt = meta.code;
                codeEl.textContent = meta.code;
                codeEl.setAttribute('lang', key);
                pillEl.setAttribute('data-lang', key);
            }

            function attachLangSync(sel, flagEl, codeEl, pillEl) {
                function sync() {
                    setFace(sel, flagEl, codeEl, pillEl);
                }
                function syncDelayed() {
                    sync();
                    [35, 100, 250, 600, 1200].forEach(function (ms) {
                        window.setTimeout(sync, ms);
                    });
                }
                sel.addEventListener('change', syncDelayed);
                sel.addEventListener('input', sync);
                sel.addEventListener('keyup', sync);
                /* GTranslate peut mettre à jour options / attributs après coup */
                var moTimer = null;
                function moDebounced() {
                    window.clearTimeout(moTimer);
                    moTimer = window.setTimeout(syncDelayed, 80);
                }
                var mo = new MutationObserver(moDebounced);
                mo.observe(sel, {
                    attributes: true,
                    childList: true,
                    subtree: true,
                    attributeFilter: ['value', 'class', 'data-gt-lang']
                });
            }

            function installPill(sel) {
                var host = document.getElementById('gtranslate_dropdown_nav');
                if (!host || !sel.parentNode || sel.dataset.navLangPill === '1') {
                    return;
                }

                sel.classList.add('nav-lang-select-overlay', 'gt_selector');

                var pill = document.createElement('div');
                pill.className = 'nav-lang-custom-pill';
                var face = document.createElement('div');
                face.className = 'nav-lang-face';
                face.setAttribute('aria-hidden', 'true');
                var flag = document.createElement('img');
                flag.className = 'nav-lang-flag-img';
                flag.width = 20;
                flag.height = 15;
                flag.loading = 'lazy';
                flag.decoding = 'async';
                var code = document.createElement('span');
                code.className = 'nav-lang-code';
                var chevron = document.createElement('span');
                chevron.className = 'nav-lang-chevron';
                chevron.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';

                pill.appendChild(face);
                face.append(flag, code, chevron);
                sel.parentNode.insertBefore(pill, sel);
                pill.appendChild(sel);

                sel.dataset.navLangPill = '1';

                attachLangSync(sel, flag, code, pill);

                sel.addEventListener('focus', function () {
                    pill.classList.add('is-open');
                });
                sel.addEventListener('blur', function () {
                    window.setTimeout(function () {
                        pill.classList.remove('is-open');
                        /* GTranslate peut mettre à jour après blur */
                        setFace(sel, flag, code, pill);
                    }, 220);
                });

                sel.setAttribute(
                    'aria-label',
                    sel.getAttribute('aria-label') || 'Choisir la langue du site'
                );

                setFace(sel, flag, code, pill);
            }

            function tryMount() {
                var host = document.getElementById('gtranslate_dropdown_nav');
                if (!host) {
                    return;
                }
                var sel = host.querySelector('select.gt_selector');
                if (sel && !host.querySelector('.nav-lang-custom-pill')) {
                    installPill(sel);
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                tryMount();
                var obs = new MutationObserver(function () {
                    tryMount();
                });
                var host = document.getElementById('gtranslate_dropdown_nav');
                if (host) {
                    obs.observe(host, { childList: true, subtree: true });
                }
                var n = 0;
                var t = window.setInterval(function () {
                    tryMount();
                    n += 1;
                    if (n > 50) {
                        window.clearInterval(t);
                    }
                }, 120);
            });
        })();

    </script>
</nav>

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