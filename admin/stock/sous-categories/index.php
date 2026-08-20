<?php
/**
 * Liste des sous-catégories, formulaire d’ajout, suppression (POST + PRG).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';
require_once __DIR__ . '/../../../models/model_categories.php';
require_once __DIR__ . '/../../../models/model_produits.php';
require_once __DIR__ . '/../../../models/model_sous_categories.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$stock_sous_cat_ok = produits_has_column('sous_categorie_id')
    && function_exists('sous_categories_table_ok')
    && sous_categories_table_ok();

if (!$stock_sous_cat_ok) {
    $_SESSION['produit_form_notice'] = 'Sous-catégories indisponibles (migration ou colonne produits manquante).';
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_sous_categorie'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $_SESSION['error_message_souscat'] = 'Session expirée ou jeton invalide. Réessayez.';
    } else {
        $sid = isset($_POST['sous_categorie_id']) ? (int) $_POST['sous_categorie_id'] : 0;
        if ($sid <= 0) {
            $_SESSION['error_message_souscat'] = 'Sous-catégorie invalide.';
        } elseif (delete_sous_categorie($sid)) {
            $_SESSION['success_message_souscat'] = 'Sous-catégorie supprimée. Les produits liés ont perdu ce classement (défini sur vide).';
        } else {
            $_SESSION['error_message_souscat'] = 'Suppression impossible.';
        }
    }
    header('Location: index.php');
    exit;
}

$success_flash = '';
if (!empty($_SESSION['success_message_souscat'])) {
    $success_flash = (string) $_SESSION['success_message_souscat'];
    unset($_SESSION['success_message_souscat']);
}
$error_flash = '';
if (!empty($_SESSION['error_message_souscat'])) {
    $error_flash = (string) $_SESSION['error_message_souscat'];
    unset($_SESSION['error_message_souscat']);
}

$categories = get_all_categories();
$liste = get_all_sous_categories_with_categorie_nom();
$nb = count($liste);
$csrf = (string) $_SESSION['admin_csrf'];
$return_url_form = '../stock/sous-categories/index.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sous-catégories — Stock</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../..//includes/fpl_head.php'; ?>
    <style>
        .sc-page { max-width: 1180px; margin: 0 auto; padding: 1rem 1.25rem 3rem; }
        .sc-hero {
            display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
            gap: 1rem 1.5rem; padding: 1.5rem 1.6rem; margin-bottom: 1.25rem;
            background: var(--fond-principal); border: 1px solid var(--border-input);
            border-radius: 16px; box-shadow: var(--ombre-douce); border-left: 4px solid var(--couleur-dominante);
        }
        .sc-hero h1 { margin: 0 0 0.35rem; font-family: var(--font-titres); font-size: clamp(1.35rem, 2.2vw, 1.75rem); color: var(--titres); display: flex; align-items: center; gap: 0.6rem; }
        .sc-hero h1 i { color: var(--couleur-dominante); }
        .sc-hero__actions { display: flex; flex-wrap: wrap; gap: 0.55rem; }
        .sc-btn {
            display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.65rem 1.05rem;
            border-radius: 10px; font-weight: 600; font-size: 0.88rem; text-decoration: none;
            border: 2px solid transparent; cursor: pointer; font-family: inherit;
        }
        .sc-btn--ghost { background: var(--fond-principal); color: var(--couleur-dominante); border-color: var(--border-input); }
        .sc-btn--ghost:hover { border-color: var(--couleur-dominante); }
        .sc-btn--primary { background: var(--couleur-dominante); color: var(--texte-clair); }
        .sc-btn--primary:hover { background: var(--couleur-dominante-hover); }
        .sc-flash { padding: 0.85rem 1rem; border-radius: 10px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .sc-flash--ok { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--titres); }
        .sc-flash--err { background: var(--error-bg); border: 1px solid var(--error-border); color: var(--titres); }
        .sc-add {
            background: var(--fond-principal); border: 1px solid var(--glass-border); border-radius: 16px;
            padding: 1.25rem 1.35rem; margin-bottom: 1.5rem; box-shadow: var(--glass-shadow);
        }
        .sc-add h2 { margin: 0 0 1rem; font-size: 1.05rem; font-family: var(--font-titres); color: var(--titres); display: flex; align-items: center; gap: 0.45rem; }
        .sc-add .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        @media (max-width: 700px) { .sc-add .form-row { grid-template-columns: 1fr; } }
        .sc-add label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem; color: var(--titres); }
        .sc-add input, .sc-add select, .sc-add textarea {
            width: 100%; padding: 0.55rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-input);
            font-family: inherit; font-size: 0.92rem; box-sizing: border-box;
        }
        .sc-add textarea { min-height: 88px; resize: vertical; }
        .sc-add__actions { display: flex; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.25rem; }
        .sc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }
        .sc-card {
            background: var(--fond-principal); border: 1px solid var(--border-input); border-radius: 14px;
            padding: 1.05rem 1.1rem; display: flex; flex-direction: column; gap: 0.65rem;
            box-shadow: 0 2px 12px rgba(53, 100, 166, 0.06);
        }
        .sc-card__parent { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--couleur-dominante); font-weight: 700; }
        .sc-card__title { margin: 0; font-size: 1.02rem; font-weight: 700; color: var(--titres); font-family: var(--font-titres); }
        .sc-card__desc { margin: 0; font-size: 0.82rem; line-height: 1.45; color: var(--gris-fonce); flex: 1;
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .sc-card__meta { font-size: 0.78rem; color: var(--gris-moyen); }
        .sc-card__actions { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.25rem; }
        .sc-card__actions a, .sc-card__actions button {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
            padding: 0.45rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; font-family: inherit; width: 100%; box-sizing: border-box;
        }
        .sc-a-prod { background: var(--bleu-pale); color: var(--couleur-dominante); border: 1px solid var(--border-input); }
        .sc-a-prod:hover { background: var(--couleur-dominante); color: var(--texte-clair); }
        .sc-a-edit { background: var(--fond-secondaire); color: var(--gris-fonce); border: 1px solid var(--border-input); }
        .sc-a-edit:hover { border-color: var(--couleur-dominante); color: var(--couleur-dominante); }
        .sc-a-del { background: var(--error-bg); color: var(--orange-fonce); border: 1px solid var(--error-border); }
        .sc-a-del:hover { background: var(--orange); color: var(--texte-clair); border-color: var(--orange); }
        .sc-empty { text-align: center; padding: 2.5rem 1rem; background: var(--fond-secondaire); border-radius: 12px; border: 1px dashed var(--border-input); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="sc-page admin-content">
        <header class="sc-hero">
            <div>
                <h1><i class="fas fa-sitemap" aria-hidden="true"></i> Sous-catégories</h1>
                <p style="margin:0;font-size:0.9rem;color:var(--gris-fonce);"><?php echo (int) $nb; ?> sous-catégorie<?php echo $nb > 1 ? 's' : ''; ?> — rattachées aux catégories du catalogue.</p>
            </div>
            <div class="sc-hero__actions">
                <a href="../index.php" class="sc-btn sc-btn--ghost"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour au stock</a>
                <a href="../../produits/ajouter.php" class="sc-btn sc-btn--primary"><i class="fas fa-plus" aria-hidden="true"></i> Nouveau produit</a>
            </div>
        </header>

        <?php if ($success_flash !== ''): ?>
            <div class="sc-flash sc-flash--ok" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_flash, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error_flash !== ''): ?>
            <div class="sc-flash sc-flash--err" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_flash, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['produit_form_notice'])): ?>
            <div class="sc-flash sc-flash--ok" role="status"><i class="fas fa-info-circle" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $_SESSION['produit_form_notice'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php unset($_SESSION['produit_form_notice']); ?>
        <?php endif; ?>

        <section class="sc-add" aria-labelledby="sc-add-title">
            <h2 id="sc-add-title"><i class="fas fa-folder-plus" aria-hidden="true"></i> Nouvelle sous-catégorie</h2>
            <form method="post" action="../../categories/sous_categorie_enregistrer.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url_form, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-row">
                    <div>
                        <label for="sc_nom">Nom *</label>
                        <input id="sc_nom" type="text" name="nom" required maxlength="190" autocomplete="off">
                    </div>
                    <div>
                        <label for="sc_cat">Catégorie parente *</label>
                        <select id="sc_cat" name="categorie_id" required>
                            <option value="">— Choisir —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars((string) $c['nom'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:1rem;">
                    <label for="sc_descr">Description</label>
                    <textarea id="sc_descr" name="description" rows="3" placeholder="Facultatif"></textarea>
                </div>
                <div class="sc-add__actions">
                    <button type="submit" class="sc-btn sc-btn--primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer la sous-catégorie</button>
                </div>
            </form>
        </section>

        <h2 class="hub-section-title" style="margin:0 0 1rem;font-size:1.1rem;border-bottom:1px solid var(--border-input);padding-bottom:0.6rem;">
            <i class="fas fa-list" aria-hidden="true"></i> Sous-catégories créées
        </h2>

        <?php if (empty($liste)): ?>
            <div class="sc-empty">
                <p style="margin:0;color:var(--gris-fonce);">Aucune sous-catégorie pour le moment. Utilisez le formulaire ci-dessus.</p>
            </div>
        <?php else: ?>
            <div class="sc-grid">
                <?php foreach ($liste as $sc): ?>
                    <?php
                    $sid = (int) $sc['id'];
                    $nbp = count_produits_by_sous_categorie_id($sid);
                    $nom_esc = htmlspecialchars((string) ($sc['nom'] ?? ''), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="sc-card">
                        <span class="sc-card__parent"><?php echo htmlspecialchars((string) ($sc['categorie_nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <h3 class="sc-card__title"><?php echo $nom_esc; ?></h3>
                        <p class="sc-card__desc"><?php echo htmlspecialchars((string) ($sc['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="sc-card__meta"><i class="fas fa-box" aria-hidden="true"></i> <?php echo (int) $nbp; ?> produit<?php echo $nbp > 1 ? 's' : ''; ?></p>
                        <div class="sc-card__actions">
                            <a class="sc-a-prod" href="produits.php?id=<?php echo $sid; ?>"><i class="fas fa-box-open" aria-hidden="true"></i> Voir les produits</a>
                            <a class="sc-a-edit" href="modifier.php?id=<?php echo $sid; ?>"><i class="fas fa-edit" aria-hidden="true"></i> Modifier</a>
                            <form method="post" action="index.php" style="margin:0;"
                                onsubmit='return confirm(<?php echo json_encode(
                                    'Supprimer définitivement la sous-catégorie « ' . ($sc['nom'] ?? '') . ' » ? Les produits associés ne seront plus classés dans cette sous-catégorie.',
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
                                ); ?>);'>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="sous_categorie_id" value="<?php echo $sid; ?>">
                                <button type="submit" name="supprimer_sous_categorie" value="1" class="sc-a-del"><i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
