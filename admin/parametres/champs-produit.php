<?php
/**
 * Configuration des champs du formulaire produit (ajout / modification).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_produit_formulaire_champs.php';

if (!admin_is_full_admin() && !produit_formulaire_peut_gerer_champs()) {
    header('Location: ../dashboard.php');
    exit;
}

produit_formulaire_champs_ensure_schema();
produit_formulaire_champs_seed_systeme();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['admin_csrf'];
$error_message = '';
$success_message = '';
$peut_gerer = produit_formulaire_peut_gerer_champs();

if (isset($_SESSION['success_message_champs_produit'])) {
    $success_message = (string) $_SESSION['success_message_champs_produit'];
    unset($_SESSION['success_message_champs_produit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals($csrf, $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!$peut_gerer) {
        $error_message = 'Vous n’avez pas les droits pour modifier cette configuration.';
    } elseif (isset($_POST['ajouter_champ_produit'])) {
        $roles_acces = isset($_POST['roles_acces']) && is_array($_POST['roles_acces'])
            ? array_map('strval', $_POST['roles_acces']) : [];
        $res = produit_formulaire_champ_ajouter(
            isset($_POST['label_champ']) ? (string) $_POST['label_champ'] : '',
            isset($_POST['type_champ']) ? (string) $_POST['type_champ'] : 'texte',
            isset($_POST['section_champ']) ? (string) $_POST['section_champ'] : 'info',
            isset($_POST['options_champ']) ? (string) $_POST['options_champ'] : '',
            !empty($_POST['obligatoire_champ']),
            $roles_acces
        );
        if ($res['success']) {
            $_SESSION['success_message_champs_produit'] = $res['message'];
            header('Location: champs-produit.php');
            exit;
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['enregistrer_acces_champ']) && $peut_gerer) {
        /* VOIR N'EST PAS MODIFIER (31/08) : chaque type de compte reçoit un
         * niveau — rien, « voir », ou « voir et modifier ». La liste déroulante
         * envoie acces[role] ; la case à cocher d'avant n'existe plus. */
        $acces_bruts = isset($_POST['acces']) && is_array($_POST['acces']) ? $_POST['acces'] : [];
        $roles_acces = [];
        $niveaux_acces = [];
        foreach ($acces_bruts as $role_recu => $niveau_recu) {
            $niveau_recu = (string) $niveau_recu;
            if ($niveau_recu !== 'voir' && $niveau_recu !== 'modifier') {
                continue;
            }
            $roles_acces[] = (string) $role_recu;
            $niveaux_acces[(string) $role_recu] = $niveau_recu;
        }
        $res = produit_formulaire_champ_roles_enregistrer((int) ($_POST['champ_id'] ?? 0), $roles_acces, $niveaux_acces);
        if ($res['success']) {
            $_SESSION['success_message_champs_produit'] = $res['message'];
            header('Location: champs-produit.php');
            exit;
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['toggle_champ_produit'])) {
        $cid = (int) ($_POST['champ_id'] ?? 0);
        $actif = (int) ($_POST['actif'] ?? 0) === 1 ? 1 : 0;
        $res = produit_formulaire_champ_set_actif($cid, $actif);
        if ($res['success']) {
            $_SESSION['success_message_champs_produit'] = $res['message'];
            header('Location: champs-produit.php');
            exit;
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['supprimer_champ_produit'])) {
        if (empty($_POST['confirm_suppression_champ'])) {
            $error_message = 'Confirmez la suppression en cochant la case d’impact.';
        } else {
            $res = produit_formulaire_champ_retirer((int) ($_POST['champ_id'] ?? 0));
            if ($res['success']) {
                $_SESSION['success_message_champs_produit'] = $res['message'];
                header('Location: champs-produit.php');
                exit;
            }
            $error_message = $res['message'];
        }
    }
}

$champs = produit_formulaire_champs_list(false);
$sections_labels = produit_formulaire_sections_labels();
$champs_par_section = [];
foreach ($sections_labels as $sec => $lab) {
    $champs_par_section[$sec] = [];
}
foreach ($champs as $ch) {
    $sec = (string) ($ch['section'] ?? 'info');
    if (!isset($champs_par_section[$sec])) {
        $champs_par_section[$sec] = [];
    }
    $champs_par_section[$sec][] = $ch;
}

$champs_impact = [];
foreach ($champs as $ch) {
    $cid = (int) ($ch['id'] ?? 0);
    if ($cid <= 0) {
        continue;
    }
    $imp = produit_formulaire_champ_impact_suppression($cid);
    if ($imp !== null) {
        $champs_impact[$cid] = $imp;
    }
}

$roles_disponibles = produit_formulaire_roles_disponibles();
$champs_roles_map = [];
foreach ($champs as $ch) {
    $cid = (int) ($ch['id'] ?? 0);
    if ($cid > 0) {
        $champs_roles_map[$cid] = produit_formulaire_champ_roles_get($cid);
    }
}

$tables_ok = produit_formulaire_champs_tables_ok();
$nb_custom = 0;
foreach ($champs as $ch) {
    if ((string) ($ch['type_champ'] ?? '') !== 'systeme') {
        $nb_custom++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Champs du formulaire pièce — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-champs-produit-page.css'); ?>
</head>

<body class="page-champs-produit">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="cp-wrap">
        <header class="cp-hero">
            <a href="../parametres.php" class="cp-hero__back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>
            <div class="cp-hero__row">
                <div class="cp-hero__icon" aria-hidden="true"><i class="fas fa-list-check"></i></div>
                <div>
                    <p class="cp-hero__eyebrow">Catalogue produits</p>
                    <h1 class="cp-hero__title">Champs du formulaire pièce</h1>
                    <p class="cp-hero__lead">Activez, désactivez ou créez des champs affichés lors de l’ajout et de la modification des produits. Définissez quels types de compte admin peuvent voir chaque champ et ses données dans tout l’espace admin, y compris les colonnes du <strong>suivi catalogue</strong>. Les champs verrouillés (nom, stock, catégorie) restent obligatoires.</p>
                </div>
            </div>
            <?php if (!$tables_ok): ?>
            <div class="cp-alert cp-alert--warn" role="alert">
                <i class="fas fa-database" aria-hidden="true"></i>
                Tables absentes — exécutez <code>php migrations/run_create_produit_formulaire_champs.php</code>
            </div>
            <?php endif; ?>
        </header>

        <?php if ($success_message !== ''): ?>
        <div class="cp-alert cp-alert--ok" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error_message !== ''): ?>
        <div class="cp-alert cp-alert--err" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($peut_gerer): ?>
        <div class="cp-toolbar">
            <button type="button" class="cp-btn cp-btn--secondary" onclick="cpOpenModal('modalAjouterChampProduit')">
                <i class="fas fa-plus-circle" aria-hidden="true"></i> Ajouter un champ
            </button>
            <span class="cp-toolbar__meta"><?php echo $nb_custom; ?> champ(s) personnalisé(s) · <?php echo count($champs); ?> au total</span>
        </div>
        <?php else: ?>
        <div class="cp-alert cp-alert--info" role="status"><i class="fas fa-eye" aria-hidden="true"></i> Consultation seule — vous ne pouvez pas modifier les champs.</div>
        <?php endif; ?>

        <?php foreach ($sections_labels as $sec_key => $sec_label): ?>
        <?php $rows = $champs_par_section[$sec_key] ?? []; if ($rows === []) continue; ?>
        <section class="cp-section-card" aria-labelledby="cp-sec-<?php echo htmlspecialchars($sec_key, ENT_QUOTES, 'UTF-8'); ?>">
            <header class="cp-section-card__head">
                <h2 id="cp-sec-<?php echo htmlspecialchars($sec_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sec_label, ENT_QUOTES, 'UTF-8'); ?></h2>
            </header>
            <ul class="cp-champs-list">
                <?php foreach ($rows as $ch): ?>
                <?php
                $cid = (int) ($ch['id'] ?? 0);
                $est_sys = (int) ($ch['est_systeme'] ?? 0) === 1;
                $verrou = (int) ($ch['verrouille'] ?? 0) === 1;
                $actif = (int) ($ch['actif'] ?? 0) === 1;
                $icon = (string) ($ch['icon'] ?? 'fa-cube');
                $type = (string) ($ch['type_champ'] ?? 'systeme');
                $roles_resume = produit_formulaire_champ_roles_resume($cid);
                ?>
                <li class="cp-champ-row<?php echo !$actif ? ' cp-champ-row--off' : ''; ?>">
                    <div class="cp-champ-row__main">
                        <span class="cp-champ-row__icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                        <div>
                            <strong class="cp-champ-row__label"><?php echo htmlspecialchars((string) ($ch['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="cp-champ-row__meta">
                                <?php echo $est_sys ? 'Système' : 'Personnalisé'; ?>
                                · <?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ((int) ($ch['obligatoire'] ?? 0) === 1): ?> · obligatoire<?php endif; ?>
                                <?php if ($verrou): ?> · verrouillé<?php endif; ?>
                            </span>
                            <span class="cp-champ-row__access" title="Types de compte autorisés">
                                <i class="fas fa-user-lock" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($roles_resume, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="cp-champ-row__actions">
                        <?php if ($peut_gerer): ?>
                        <button type="button"
                            class="cp-btn-icon"
                            title="Configurer les accès par type de compte"
                            aria-label="Accès au champ <?php echo htmlspecialchars((string) ($ch['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            onclick="cpOpenAccesChamp(<?php echo $cid; ?>)">
                            <i class="fas fa-user-shield" aria-hidden="true"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($peut_gerer && !$verrou): ?>
                        <form method="post" class="cp-champ-row__toggle">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="toggle_champ_produit" value="1">
                            <input type="hidden" name="champ_id" value="<?php echo $cid; ?>">
                            <input type="hidden" name="actif" value="<?php echo $actif ? '0' : '1'; ?>">
                            <button type="submit" class="cp-switch<?php echo $actif ? ' cp-switch--on' : ''; ?>" title="<?php echo $actif ? 'Désactiver' : 'Activer'; ?>">
                                <span class="cp-switch__knob"></span>
                            </button>
                        </form>
                        <?php elseif (!$peut_gerer || $verrou): ?>
                        <span class="cp-badge<?php echo $actif ? ' cp-badge--on' : ' cp-badge--off'; ?>"><?php echo $actif ? 'Actif' : 'Inactif'; ?></span>
                        <?php endif; ?>
                        <?php if ($peut_gerer): ?>
                        <button type="button"
                            class="cp-btn-icon cp-btn-icon--danger"
                            title="Retirer ce champ des formulaires"
                            aria-label="Retirer le champ <?php echo htmlspecialchars((string) ($ch['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            onclick="cpOpenDeleteChamp(<?php echo $cid; ?>)">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>
    </div>

    <?php if ($peut_gerer): ?>
    <div class="cp-modal" id="modalAjouterChampProduit" aria-hidden="true">
        <div class="cp-modal__backdrop" onclick="cpCloseModal('modalAjouterChampProduit')"></div>
        <div class="cp-modal__dialog" role="dialog">
            <div class="cp-modal__head">
                <h2 class="cp-modal__title"><i class="fas fa-plus-circle" aria-hidden="true"></i> Ajouter un champ</h2>
                <button type="button" class="cp-modal__close" onclick="cpCloseModal('modalAjouterChampProduit')"><i class="fas fa-xmark"></i></button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="ajouter_champ_produit" value="1">
                <div class="cp-modal__body">
                    <div class="cp-field">
                        <label for="label_champ">Libellé</label>
                        <input type="text" id="label_champ" name="label_champ" maxlength="100" required placeholder="Ex : Origine, Certification…">
                    </div>
                    <div class="cp-field">
                        <label for="type_champ">Type</label>
                        <select id="type_champ" name="type_champ" required>
                            <option value="texte">Texte court</option>
                            <option value="textarea">Texte long</option>
                            <option value="nombre">Nombre</option>
                            <option value="select">Liste déroulante</option>
                        </select>
                    </div>
                    <div class="cp-field">
                        <label for="section_champ">Section du formulaire</label>
                        <select id="section_champ" name="section_champ" required>
                            <?php foreach ($sections_labels as $sk => $sl): ?>
                            <option value="<?php echo htmlspecialchars($sk, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sl, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cp-field" id="cp_options_wrap" hidden>
                        <label for="options_champ">Options (une par ligne ou séparées par des virgules)</label>
                        <textarea id="options_champ" name="options_champ" rows="4" placeholder="Bio&#10;Conventionnel&#10;Local"></textarea>
                    </div>
                    <label class="cp-checkbox">
                        <input type="checkbox" name="obligatoire_champ" value="1">
                        <span>Champ obligatoire à la saisie</span>
                    </label>
                    <fieldset class="cp-roles-fieldset">
                        <legend>Types de compte autorisés à voir ce champ</legend>
                        <p class="cp-roles-fieldset__hint">Ces droits s’appliquent au formulaire produit et à l’affichage des données dans tout l’admin.</p>
                        <div class="cp-roles-grid">
                            <?php foreach ($roles_disponibles as $role_key => $role_label): ?>
                            <label class="cp-role-chip">
                                <input type="checkbox" name="roles_acces[]" value="<?php echo htmlspecialchars($role_key, ENT_QUOTES, 'UTF-8'); ?>" checked>
                                <span><?php echo htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
                <div class="cp-modal__footer">
                    <button type="button" class="cp-btn cp-btn--ghost" onclick="cpCloseModal('modalAjouterChampProduit')">Annuler</button>
                    <button type="submit" class="cp-btn cp-btn--primary">Créer le champ</button>
                </div>
            </form>
        </div>
    </div>

    <div class="cp-modal" id="modalAccesChampProduit" aria-hidden="true">
        <div class="cp-modal__backdrop" onclick="cpCloseModal('modalAccesChampProduit')"></div>
        <div class="cp-modal__dialog cp-modal__dialog--wide" role="dialog" aria-labelledby="cp_acces_title">
            <div class="cp-modal__head">
                <h2 class="cp-modal__title" id="cp_acces_title"><i class="fas fa-user-shield" aria-hidden="true"></i> Accès au champ</h2>
                <button type="button" class="cp-modal__close" onclick="cpCloseModal('modalAccesChampProduit')"><i class="fas fa-xmark"></i></button>
            </div>
            <form method="post" id="cp_form_acces_champ">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="enregistrer_acces_champ" value="1">
                <input type="hidden" name="champ_id" id="cp_acces_champ_id" value="">
                <div class="cp-modal__body">
                    <p class="cp-impact__intro" id="cp_acces_intro"></p>
                    <fieldset class="cp-roles-fieldset">
                        <legend>Qui a le droit, et jusqu'où</legend>
                        <p class="cp-roles-fieldset__hint">Cochez les types de compte autorisés. Si aucune restriction n’est enregistrée, tous les types voient le champ (sauf informaticien/développeur qui voient toujours tout).</p>
                        <div class="cp-roles-grid" id="cp_acces_roles_grid">
                            <?php foreach ($roles_disponibles as $role_key => $role_label): ?>
                            <label class="cp-role-chip cp-role-chip--niveau">
                                <span><?php echo htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <select name="acces[<?php echo htmlspecialchars($role_key, ENT_QUOTES, 'UTF-8'); ?>]"
                                        data-role="<?php echo htmlspecialchars($role_key, ENT_QUOTES, 'UTF-8'); ?>">
                                    <option value="">Aucun accès</option>
                                    <option value="voir">Voir seulement</option>
                                    <option value="modifier">Voir et modifier</option>
                                </select>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
                <div class="cp-modal__footer">
                    <button type="button" class="cp-btn cp-btn--ghost" onclick="cpCloseModal('modalAccesChampProduit')">Annuler</button>
                    <button type="submit" class="cp-btn cp-btn--primary">Enregistrer les accès</button>
                </div>
            </form>
        </div>
    </div>

    <div class="cp-modal" id="modalConfirmerRetraitChamp" aria-hidden="true">
        <div class="cp-modal__backdrop" onclick="cpCloseModal('modalConfirmerRetraitChamp')"></div>
        <div class="cp-modal__dialog cp-modal__dialog--wide" role="dialog" aria-labelledby="cp_retrait_title">
            <div class="cp-modal__head">
                <h2 class="cp-modal__title" id="cp_retrait_title"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <span id="cp_retrait_title_text">Confirmer le retrait</span></h2>
                <button type="button" class="cp-modal__close" onclick="cpCloseModal('modalConfirmerRetraitChamp')"><i class="fas fa-xmark"></i></button>
            </div>
            <form method="post" id="cp_form_retrait_champ">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="supprimer_champ_produit" value="1">
                <input type="hidden" name="confirm_suppression_champ" id="cp_confirm_suppression_champ" value="">
                <input type="hidden" name="champ_id" id="cp_retrait_champ_id" value="">
                <div class="cp-modal__body">
                    <p id="cp_retrait_intro" class="cp-impact__intro"></p>
                    <div class="cp-impact cp-impact--inline" id="cp_retrait_impact">
                        <ul class="cp-impact__list" id="cp_retrait_warnings"></ul>
                        <label class="cp-impact__confirm" id="cp_retrait_confirm_wrap">
                            <input type="checkbox" id="cp_retrait_check">
                            <span id="cp_retrait_confirm_label">Je comprends les conséquences et souhaite retirer ce champ.</span>
                        </label>
                    </div>
                </div>
                <div class="cp-modal__footer">
                    <button type="button" class="cp-btn cp-btn--ghost" onclick="cpCloseModal('modalConfirmerRetraitChamp')">Annuler</button>
                    <button type="submit" class="cp-btn cp-btn--danger" id="cp_btn_retrait_champ" disabled>Retirer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    window.CP_CHAMPS_IMPACT = <?php echo json_encode($champs_impact, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.CP_CHAMPS_ROLES = <?php echo json_encode($champs_roles_map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.CP_CHAMPS_NIVEAUX = <?php
        /* Le niveau de chaque droit, pour rouvrir la fenêtre sur ce qui est
           réellement enregistré (31/08). */
        $niveaux_map = [];
        foreach ($champs as $ch) {
            $cid_n = (int) ($ch['id'] ?? 0);
            if ($cid_n > 0) {
                $niveaux_map[$cid_n] = produit_formulaire_champ_niveaux_get($cid_n);
            }
        }
        echo json_encode($niveaux_map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>;
    window.CP_CHAMPS_LABELS = <?php
        $labels_map = [];
        foreach ($champs as $ch) {
            $cid = (int) ($ch['id'] ?? 0);
            if ($cid > 0) {
                $labels_map[$cid] = (string) ($ch['label'] ?? '');
            }
        }
        echo json_encode($labels_map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>;
    </script>
    <script src="/js/admin-champs-produit.js<?php echo asset_version_query(); ?>"></script>
    <?php endif; ?>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>
