<?php
/**
 * Édition du référentiel nommé d’un étage (structure, rayons, barres, positions…).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_is_full_admin()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_entrepot_emplacement.php';
require_once __DIR__ . '/../../models/model_entrepot_referentiel.php';
require_once __DIR__ . '/../../includes/entrepot_barcode_service.php';
require_once __DIR__ . '/../../includes/site_url.php';
require_once __DIR__ . '/../../includes/asset_version.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$numero_etage = isset($_GET['etage']) ? (int) $_GET['etage'] : 0;
if ($numero_etage <= 0) {
    header('Location: emplacement-entrepot.php');
    exit;
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message_emplacement_etage'])) {
    $success_message = (string) $_SESSION['success_message_emplacement_etage'];
    unset($_SESSION['success_message_emplacement_etage']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token)) {
        $error_message = 'Session expirée ou jeton invalide.';
    } elseif (isset($_POST['sync_referentiel'])) {
        $res = entrepot_sync_referentiel_depuis_config($numero_etage);
        if ($res['success']) {
            $_SESSION['success_message_emplacement_etage'] = $res['message'];
            header('Location: emplacement-entrepot-etage.php?etage=' . $numero_etage);
            exit;
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['generer_codes_barre'])) {
        $bid = isset($_POST['barre_id']) ? (int) $_POST['barre_id'] : 0;
        if ($bid > 0 && entrepot_generer_codes_barre($bid)) {
            $_SESSION['success_message_emplacement_etage'] = 'Codes barre / QR générés.';
        } else {
            $error_message = 'Impossible de générer les codes.';
        }
        header('Location: emplacement-entrepot-etage.php?etage=' . $numero_etage . '#section-rayons');
        exit;
    } elseif (isset($_POST['enregistrer_referentiel'])) {
        $res = entrepot_enregistrer_referentiel_etage($numero_etage, $_POST);
        if ($res['success']) {
            $_SESSION['success_message_emplacement_etage'] = $res['message'];
            header('Location: emplacement-entrepot-etage.php?etage=' . $numero_etage);
            exit;
        }
        $error_message = $res['message'];
    }
}

$sync_ref = entrepot_sync_referentiel_depuis_config($numero_etage);
$ref = entrepot_get_referentiel_etage_complet($numero_etage);
if ($ref === null) {
    $msg = 'Impossible d’ouvrir ce niveau.';
    if (!$sync_ref['success'] && !empty($sync_ref['message'])) {
        $msg .= ' ' . $sync_ref['message'];
        if (stripos($sync_ref['message'], 'Duplicata') !== false || stripos($sync_ref['message'], 'Integrity constraint') !== false) {
            $msg .= ' — exécutez : php migrations/run_migrate_entrepot_barre_par_rayon.php';
        }
    } elseif (!entrepot_referentiel_tables_ok()) {
        $msg .= ' Tables référentiel absentes — exécutez migrations/run_create_entrepot_referentiel_nomme.php';
    }
    $_SESSION['error_message_emplacement_entrepot'] = $msg;
    header('Location: emplacement-entrepot.php');
    exit;
}

$etage = $ref['etage'];
$cfg_row = entrepot_emplacement_get_etage($numero_etage);
$nb_rayons = (int) ($cfg_row['nb_rayons'] ?? 1);
$nb_allees = (int) ($cfg_row['nb_allees'] ?? 1);
$nb_zones = (int) ($cfg_row['nb_zones'] ?? 1);
$nb_positions = (int) ($cfg_row['nb_positions'] ?? 1);
$nb_barres = (int) ($cfg_row['nb_barres'] ?? 1);
$total_barres = $nb_rayons * $nb_barres;
$total_positions = $total_barres * $nb_positions;
$barres_par_rayon = $ref['barres_par_rayon'] ?? entrepot_barres_grouper_par_rayon($ref['barres']);
$origin_et = get_request_origin_base_url();
$barre_etiq_css = '/css/entrepot-barre-etiquette.css' . asset_version_query();

$structure_fields = [
    ['name' => 'nb_rayons', 'label' => 'Rayons', 'icon' => 'fa-th-large', 'value' => $nb_rayons, 'max' => ENTREPOT_EMPLACEMENT_NB_RAYONS_MAX],
    ['name' => 'nb_allees', 'label' => 'Allées', 'icon' => 'fa-road', 'value' => $nb_allees, 'max' => ENTREPOT_EMPLACEMENT_NB_PETIT_MAX],
    ['name' => 'nb_zones', 'label' => 'Zones', 'icon' => 'fa-map-marker-alt', 'value' => $nb_zones, 'max' => ENTREPOT_EMPLACEMENT_NB_PETIT_MAX],
    ['name' => 'nb_barres', 'label' => 'Barres / rayon', 'icon' => 'fa-grip-lines', 'value' => $nb_barres, 'max' => ENTREPOT_EMPLACEMENT_NB_PETIT_MAX],
    ['name' => 'nb_positions', 'label' => 'Positions / barre', 'icon' => 'fa-crosshairs', 'value' => $nb_positions, 'max' => ENTREPOT_EMPLACEMENT_NB_PETIT_MAX],
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étage <?php echo (int) $numero_etage; ?> — Emplacement entrepôt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-emplacement-entrepot.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/entrepot-barre-etiquette.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-parametres-admin page-emplacement-entrepot ee-etage-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page ee-wrap ee-etage-wrap">
        <header class="ee-hero ee-etage-hero">
            <a class="ee-hero__back" href="emplacement-entrepot.php"><i class="fas fa-arrow-left"></i> Emplacement entrepôt</a>
            <div class="ee-hero__row">
                <div class="ee-hero__icon"><i class="fas fa-layer-group"></i></div>
                <div class="ee-hero__text">
                    <p class="ee-etage-kicker">Étage <?php echo (int) $numero_etage; ?> · <code><?php echo htmlspecialchars($etage['code'] ?? ('E' . $numero_etage)); ?></code></p>
                    <h1 class="ee-hero__title"><?php echo htmlspecialchars($etage['nom'] ?? ('Étage ' . $numero_etage)); ?></h1>
                    <p class="ee-hero__lead">Configurez la structure (quantités) et les noms lisibles. Les barres reçoivent un QR / code-barres pour lister les produits au scan.</p>
                </div>
            </div>
            <ul class="ee-etage-stats" aria-label="Synthèse structure">
                <li><strong><?php echo $nb_rayons; ?></strong><span>Rayons</span></li>
                <li><strong><?php echo $nb_barres; ?></strong><span>Barres / rayon</span></li>
                <li><strong><?php echo $total_barres; ?></strong><span>Barres total</span></li>
                <li><strong><?php echo $nb_positions; ?></strong><span>Pos. / barre</span></li>
                <li><strong><?php echo $total_positions; ?></strong><span>Emplacements</span></li>
            </ul>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="message success ee-flash"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error ee-flash"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="ee-etage-layout">
            <nav class="ee-etage-nav" aria-label="Sections de l’étage">
                <p class="ee-etage-nav__label">Navigation</p>
                <a href="#section-identite" class="ee-etage-nav__link is-active"><i class="fas fa-building"></i> Identité &amp; structure</a>
                <a href="#section-rayons" class="ee-etage-nav__link"><i class="fas fa-th-large"></i> Rayons <span class="ee-etage-nav__count"><?php echo count($ref['rayons']); ?></span></a>
                <a href="#section-allees" class="ee-etage-nav__link"><i class="fas fa-road"></i> Allées <span class="ee-etage-nav__count"><?php echo count($ref['allees']); ?></span></a>
                <a href="#section-zones" class="ee-etage-nav__link"><i class="fas fa-map-marker-alt"></i> Zones <span class="ee-etage-nav__count"><?php echo count($ref['zones']); ?></span></a>
            </nav>

            <form method="post" id="formReferentielEtage" class="ee-etage-main">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="enregistrer_referentiel" value="1">

                <section id="section-identite" class="ee-panel ee-panel--primary">
                    <header class="ee-panel__head">
                        <h2 class="ee-panel__title"><i class="fas fa-building"></i> Identité &amp; structure</h2>
                        <p class="ee-panel__desc">Nom de l’étage et volumes (rayons, allées, zones, barres, positions). À l’enregistrement, le référentiel est synchronisé automatiquement.</p>
                    </header>
                    <div class="ee-panel__body">
                        <div class="ee-identite-grid">
                            <div class="form-group ee-field-block">
                                <label for="nom_etage"><i class="fas fa-tag"></i> Nom affiché</label>
                                <input type="text" id="nom_etage" name="nom_etage" value="<?php echo htmlspecialchars($etage['nom'] ?? ''); ?>" required placeholder="Ex. Rez-de-chaussée pièces lourdes">
                            </div>
                            <div class="form-group ee-field-block">
                                <label for="code_etage"><i class="fas fa-barcode"></i> Code court</label>
                                <input type="text" id="code_etage" name="code_etage" value="<?php echo htmlspecialchars($etage['code'] ?? ''); ?>" maxlength="20" required placeholder="Ex. E1">
                            </div>
                        </div>

                        <div class="ee-structure-grid">
                            <?php foreach ($structure_fields as $sf): ?>
                            <div class="ee-qty-card">
                                <label for="<?php echo htmlspecialchars($sf['name']); ?>" class="ee-qty-card__label">
                                    <i class="fas <?php echo htmlspecialchars($sf['icon']); ?>" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($sf['label']); ?>
                                </label>
                                <input type="number"
                                    id="<?php echo htmlspecialchars($sf['name']); ?>"
                                    name="<?php echo htmlspecialchars($sf['name']); ?>"
                                    class="ee-qty-card__input"
                                    min="1"
                                    max="<?php echo (int) $sf['max']; ?>"
                                    step="1"
                                    value="<?php echo (int) $sf['value']; ?>"
                                    required>
                                <span class="ee-qty-card__hint">Max. <?php echo (int) $sf['max']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <p class="ee-panel__note">
                            <i class="fas fa-circle-info"></i>
                            Réduire une quantité supprime les éléments excédentaires (et dissocie les produits concernés).
                        </p>
                    </div>
                </section>

                <section id="section-rayons" class="ee-panel ee-panel--collapsible" data-panel-open="true">
                    <header class="ee-panel__head ee-panel__head--toggle">
                        <button type="button" class="ee-panel__toggle" aria-expanded="true" aria-controls="panel-rayons">
                            <span class="ee-panel__title"><i class="fas fa-th-large"></i> Rayons &amp; barres</span>
                            <span class="ee-panel__badge"><?php echo count($ref['rayons']); ?> rayon(s) · <?php echo (int) $nb_barres; ?> barre(s)/rayon</span>
                            <i class="fas fa-chevron-down ee-panel__chevron" aria-hidden="true"></i>
                        </button>
                    </header>
                    <div id="panel-rayons" class="ee-panel__body">
                        <p class="ee-panel__note ee-panel__note--inline">
                            <i class="fas fa-circle-info"></i>
                            Chaque rayon contient <strong><?php echo (int) $nb_barres; ?></strong> barre(s). Dépliez un rayon pour configurer ses barres, liens et étiquettes 90×30&nbsp;mm.
                        </p>
                        <div class="ee-panel__toolbar">
                            <label class="ee-search">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <input type="search" class="ee-search__input" placeholder="Filtrer un rayon…" data-filter-target="rayons">
                            </label>
                        </div>
                        <div class="ee-rayons-stack" data-naming-list="rayons">
                            <?php foreach ($ref['rayons'] as $r):
                                $rid = (int) $r['id'];
                                $barres_rayon = $barres_par_rayon[$rid] ?? [];
                            ?>
                            <article class="ee-rayon-block" data-filter-text="<?php echo htmlspecialchars(strtolower($r['nom'] . ' ' . $r['numero'])); ?>">
                                <div class="ee-rayon-block__head">
                                    <span class="ee-rayon-block__badge">#<?php echo (int) $r['numero']; ?></span>
                                    <input type="text" name="rayons[<?php echo $rid; ?>][nom]" value="<?php echo htmlspecialchars($r['nom']); ?>" required aria-label="Nom rayon <?php echo (int) $r['numero']; ?>" class="ee-rayon-block__nom">
                                    <input type="hidden" name="rayons[<?php echo $rid; ?>][code]" value="<?php echo htmlspecialchars($r['code'] ?? ''); ?>">
                                    <span class="ee-rayon-block__count"><?php echo count($barres_rayon); ?> barre(s)</span>
                                    <button type="button" class="ee-rayon-block__toggle" aria-expanded="false" aria-controls="rayon-barres-<?php echo $rid; ?>" aria-label="Afficher ou masquer les barres du rayon <?php echo (int) $r['numero']; ?>">
                                        <i class="fas fa-chevron-down ee-rayon-block__chevron" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div id="rayon-barres-<?php echo $rid; ?>" class="ee-rayon-block__barres" hidden>
                                    <?php if ($barres_rayon === []): ?>
                                    <p class="ee-rayon-block__empty">Aucune barre — enregistrez la structure ou synchronisez le référentiel.</p>
                                    <?php else: ?>
                                    <?php foreach ($barres_rayon as $b):
                                        $bid = (int) $b['id'];
                                        $qc = get_qrcode_barre_web_path($bid);
                                        if ($qc === '' && !empty($b['code_scan'])) {
                                            entrepot_generer_codes_barre($bid);
                                            $qc = get_qrcode_barre_web_path($bid);
                                        }
                                        $etiq_lib = entrepot_barre_etiquette_libelle($b, $etage, $r);
                                        $nom_form = entrepot_barre_nom_valeur_formulaire($b['nom'] ?? '', (int) $b['numero']);
                                    ?>
                                    <div class="ee-barre-in-rayon" data-barre-id="<?php echo $bid; ?>">
                                        <div class="ee-barre-in-rayon__head">
                                            <strong class="ee-barre-in-rayon__title">Barre #<?php echo (int) $b['numero']; ?></strong>
                                            <?php if (!empty($b['code_scan'])): ?>
                                            <code class="ee-barre-card__code"><?php echo htmlspecialchars($b['code_scan']); ?></code>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="barres[<?php echo $bid; ?>][rayon_id]" value="<?php echo $rid; ?>">
                                        <div class="ee-barre-meta">
                                            <p class="ee-barre-meta__kicker">
                                                <i class="fas fa-link" aria-hidden="true"></i>
                                                Liens facultatifs
                                            </p>
                                            <div class="ee-barre-meta__grid">
                                                <div class="ee-barre-meta__field">
                                                    <label for="barre-nom-<?php echo $bid; ?>">
                                                        <i class="fas fa-tag" aria-hidden="true"></i>
                                                        Nom de la barre
                                                        <span class="ee-barre-meta__optional">facultatif</span>
                                                    </label>
                                                    <input type="text" id="barre-nom-<?php echo $bid; ?>" name="barres[<?php echo $bid; ?>][nom]" value="<?php echo htmlspecialchars($nom_form, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex. B01-01" autocomplete="off">
                                                </div>
                                                <div class="ee-barre-meta__field">
                                                    <label for="barre-allee-<?php echo $bid; ?>">
                                                        <i class="fas fa-road" aria-hidden="true"></i>
                                                        Allée liée
                                                        <span class="ee-barre-meta__optional">facultatif</span>
                                                    </label>
                                                    <select id="barre-allee-<?php echo $bid; ?>" name="barres[<?php echo $bid; ?>][allee_id]">
                                                        <option value=""<?php echo empty($b['allee_id']) ? ' selected' : ''; ?>>— Choisir une allée —</option>
                                                        <?php foreach ($ref['allees'] as $a): ?>
                                                        <option value="<?php echo (int) $a['id']; ?>" <?php echo (int) ($b['allee_id'] ?? 0) === (int) $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nom']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="ee-barre-meta__field">
                                                    <label for="barre-zone-<?php echo $bid; ?>">
                                                        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                                        Zone liée
                                                        <span class="ee-barre-meta__optional">facultatif</span>
                                                    </label>
                                                    <select id="barre-zone-<?php echo $bid; ?>" name="barres[<?php echo $bid; ?>][zone_id]">
                                                        <option value=""<?php echo empty($b['zone_id']) ? ' selected' : ''; ?>>— Choisir une zone —</option>
                                                        <?php foreach ($ref['zones'] as $z): ?>
                                                        <option value="<?php echo (int) $z['id']; ?>" <?php echo (int) ($b['zone_id'] ?? 0) === (int) $z['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($z['nom']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ee-barre-etiq-block" id="ee-barre-etiq-root-<?php echo $bid; ?>" data-css-url="<?php echo htmlspecialchars($origin_et . $barre_etiq_css, ENT_QUOTES, 'UTF-8'); ?>">
                                            <p class="ee-barre-etiq-block__label">Étiquette 90×30 mm</p>
                                            <div class="ee-barre-etiq-row">
                                                <div class="ee-barre-etiq-preview-wrap">
                                                    <div class="ee-barre-etiq-preview-scale">
                                                        <article class="ee-barre-etiq" data-barre-etiq>
                                                            <span class="ee-barre-etiq__text"><?php echo htmlspecialchars($etiq_lib, ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <div class="ee-barre-etiq__qr-box">
                                                                <?php if ($qc !== ''): ?>
                                                                <img src="<?php echo htmlspecialchars($qc, ENT_QUOTES, 'UTF-8'); ?>" width="96" height="96" alt="QR barre" class="ee-barre-etiq__qr">
                                                                <?php endif; ?>
                                                            </div>
                                                        </article>
                                                    </div>
                                                </div>
                                                <div class="ee-barre-etiq-actions">
                                                    <button type="button" class="ee-barre-etiq-print-btn" data-barre-print="<?php echo $bid; ?>">
                                                        <i class="fas fa-print" aria-hidden="true"></i> Imprimer l’étiquette
                                                    </button>
                                                    <a href="emplacement-barre-etiquette.php?id=<?php echo $bid; ?>" class="ee-btn-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> PDF</a>
                                                </div>
                                            </div>
                                        </div>
                                        <h4 class="ee-barre-positions-title">Positions sur cette barre</h4>
                                        <div class="ee-naming-list ee-naming-list--compact">
                                            <?php foreach ($b['positions'] as $p): ?>
                                            <div class="ee-naming-row">
                                                <span class="ee-naming-row__num">#<?php echo (int) $p['numero']; ?></span>
                                                <input type="text" name="barres[<?php echo $bid; ?>][positions][<?php echo (int) $p['id']; ?>][nom]" value="<?php echo htmlspecialchars($p['nom']); ?>" required aria-label="Position <?php echo (int) $p['numero']; ?> barre <?php echo (int) $b['numero']; ?>">
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section id="section-allees" class="ee-panel ee-panel--collapsible">
                    <header class="ee-panel__head ee-panel__head--toggle">
                        <button type="button" class="ee-panel__toggle" aria-expanded="false" aria-controls="panel-allees">
                            <span class="ee-panel__title"><i class="fas fa-road"></i> Allées</span>
                            <span class="ee-panel__badge"><?php echo count($ref['allees']); ?> élément(s)</span>
                            <i class="fas fa-chevron-down ee-panel__chevron" aria-hidden="true"></i>
                        </button>
                    </header>
                    <div id="panel-allees" class="ee-panel__body" hidden>
                        <div class="ee-panel__toolbar">
                            <label class="ee-search">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <input type="search" class="ee-search__input" placeholder="Filtrer une allée…" data-filter-target="allees">
                            </label>
                        </div>
                        <div class="ee-naming-list" data-naming-list="allees">
                            <?php foreach ($ref['allees'] as $a): ?>
                            <div class="ee-naming-row" data-filter-text="<?php echo htmlspecialchars(strtolower($a['nom'] . ' ' . $a['numero'])); ?>">
                                <span class="ee-naming-row__num">#<?php echo (int) $a['numero']; ?></span>
                                <input type="text" name="allees[<?php echo (int) $a['id']; ?>][nom]" value="<?php echo htmlspecialchars($a['nom']); ?>" required aria-label="Nom allée <?php echo (int) $a['numero']; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section id="section-zones" class="ee-panel ee-panel--collapsible">
                    <header class="ee-panel__head ee-panel__head--toggle">
                        <button type="button" class="ee-panel__toggle" aria-expanded="false" aria-controls="panel-zones">
                            <span class="ee-panel__title"><i class="fas fa-map-marker-alt"></i> Zones</span>
                            <span class="ee-panel__badge"><?php echo count($ref['zones']); ?> élément(s)</span>
                            <i class="fas fa-chevron-down ee-panel__chevron" aria-hidden="true"></i>
                        </button>
                    </header>
                    <div id="panel-zones" class="ee-panel__body" hidden>
                        <div class="ee-panel__toolbar">
                            <label class="ee-search">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <input type="search" class="ee-search__input" placeholder="Filtrer une zone…" data-filter-target="zones">
                            </label>
                        </div>
                        <div class="ee-naming-list" data-naming-list="zones">
                            <?php foreach ($ref['zones'] as $z): ?>
                            <div class="ee-naming-row" data-filter-text="<?php echo htmlspecialchars(strtolower($z['nom'] . ' ' . $z['numero'])); ?>">
                                <span class="ee-naming-row__num">#<?php echo (int) $z['numero']; ?></span>
                                <input type="text" name="zones[<?php echo (int) $z['id']; ?>][nom]" value="<?php echo htmlspecialchars($z['nom']); ?>" required aria-label="Nom zone <?php echo (int) $z['numero']; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <div class="ee-submit-bar ee-submit-bar--sticky">
                    <a href="emplacement-entrepot.php" class="ee-modal__cancel ee-submit-bar__cancel">Annuler</a>
                    <button type="submit" class="ee-modal__submit"><i class="fas fa-check"></i> Enregistrer l’étage</button>
                </div>
            </form>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    window.EE_BARRE_ETIQ_ORIGIN = <?php echo json_encode(rtrim($origin_et, '/') . '/', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    </script>
    <script src="/js/admin-emplacement-referentiel.js<?php echo asset_version_query(); ?>"></script>
</body>
</html>
