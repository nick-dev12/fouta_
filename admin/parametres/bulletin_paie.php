<?php
/**
 * Paramètres bulletins de paie — en-tête employeur & rubriques affichées
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_bulletin_paie.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message_bp_params'])) {
    $success_message = (string) $_SESSION['success_message_bp_params'];
    unset($_SESSION['success_message_bp_params']);
}

$lg = bp_labels_gains();
$lr = bp_labels_retenues();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action_bulletin_paie'] ?? '') === 'save') {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!bp_tables_parametres_disponibles()) {
        $error_message = 'Table bulletin_paie_parametres absente — exécutez la migration.';
    } else {
        $rub = bp_rubriques_defaut();
        $posted_g = isset($_POST['rub_gain']) && is_array($_POST['rub_gain']) ? $_POST['rub_gain'] : [];
        foreach (array_keys($rub['gains']) as $k) {
            $rub['gains'][$k] = !empty($posted_g[$k]);
        }
        $rub['gains']['prime_transport'] = true;
        $posted_r = isset($_POST['rub_ret']) && is_array($_POST['rub_ret']) ? $_POST['rub_ret'] : [];
        foreach (array_keys($rub['retenues']) as $k) {
            $rub['retenues'][$k] = !empty($posted_r[$k]);
        }
        $posted_t = isset($_POST['rub_trav']) && is_array($_POST['rub_trav']) ? $_POST['rub_trav'] : [];
        foreach (array_keys($rub['travail']) as $k) {
            $rub['travail'][$k] = !empty($posted_t[$k]);
        }
        $rub['travail']['conges'] = false;
        $posted_m = isset($_POST['rub_men']) && is_array($_POST['rub_men']) ? $_POST['rub_men'] : [];
        foreach (array_keys($rub['mentions']) as $k) {
            $rub['mentions'][$k] = !empty($posted_m[$k]);
        }
        $taux_eff = bp_get_parametres_effectifs()['retenues_taux'];
        $posted_taux = isset($_POST['taux_retenue']) && is_array($_POST['taux_retenue']) ? $_POST['taux_retenue'] : [];
        if (bp_colonne_retenues_taux_disponible()) {
            foreach (bp_retenues_codes_taux_brut() as $tc) {
                if (!empty($rub['retenues'][$tc])) {
                    $taux_eff[$tc] = bp_parse_taux_pct($posted_taux[$tc] ?? null);
                    if ($taux_eff[$tc] <= 0) {
                        $error_message = 'Renseignez un taux supérieur à 0 % pour : ' . ($lr[$tc] ?? $tc) . '.';
                        break;
                    }
                }
            }
        }
        if ($error_message === '' && !empty($rub['travail']['jours_presence']) && bp_colonne_jours_presence_defaut_disponible()) {
            $raw_jp = isset($_POST['jours_presence_defaut']) ? trim((string) $_POST['jours_presence_defaut']) : '';
            if ($raw_jp === '' || !ctype_digit($raw_jp)) {
                $error_message = 'Indiquez un nombre de jours de présence (référence mensuelle, 1 à 31) ou décochez la rubrique.';
            } else {
                $njp = (int) $raw_jp;
                if ($njp < 1 || $njp > 31) {
                    $error_message = 'Le nombre de jours de présence doit être compris entre 1 et 31.';
                }
            }
        }
        $prime_transport_save = bp_parse_montant_post($_POST['prime_transport_mensuelle'] ?? null);
        $conges_annuels_save = isset($_POST['conges_annuels_global']) ? (int) $_POST['conges_annuels_global'] : 0;
        if ($error_message === '' && $prime_transport_save < 0) {
            $error_message = 'Le montant de la prime de transport doit être supérieur ou égal à 0.';
        }
        $forfait_hs_save = bp_parse_montant_post($_POST['forfait_heures_sup_mensuel'] ?? null);
        if ($error_message === '' && $forfait_hs_save < 0) {
            $error_message = 'Le forfait HS (sursalaire) doit être supérieur ou égal à 0.';
        }
        if ($error_message === '' && ($conges_annuels_save < 0 || $conges_annuels_save > 365)) {
            $error_message = 'Le quota annuel de congés doit être compris entre 0 et 365 jours.';
        }
        if ($error_message === '') {
            $jp_save = null;
            if (!empty($rub['travail']['jours_presence']) && bp_colonne_jours_presence_defaut_disponible()) {
                $jp_save = (int) ($_POST['jours_presence_defaut'] ?? 0);
                $jp_save = $jp_save > 0 ? min(31, $jp_save) : null;
            }
            $ok = bp_save_parametres([
                'employeur_nom' => $_POST['employeur_nom'] ?? '',
                'employeur_adresse' => $_POST['employeur_adresse'] ?? '',
                'employeur_ninea' => $_POST['employeur_ninea'] ?? '',
                'employeur_rc' => $_POST['employeur_rc'] ?? '',
                'employeur_cnss_ref' => $_POST['employeur_cnss_ref'] ?? '',
                'rubriques' => $rub,
                'retenues_taux' => $taux_eff,
                'jours_presence_defaut' => $jp_save,
                'prime_transport_mensuelle' => $prime_transport_save,
                'conges_annuels_global' => $conges_annuels_save,
                'forfait_heures_sup_mensuel' => $forfait_hs_save,
            ]);
            if ($ok) {
                $_SESSION['success_message_bp_params'] = 'Paramètres bulletin de paie enregistrés.';
                header('Location: bulletin_paie.php');
                exit;
            }
            $error_message = 'Enregistrement impossible.';
        }
    }
}

$cur = bp_get_parametres_effectifs();
$rub = $cur['rubriques'];
$taux_cur = $cur['retenues_taux'];
$jp_def_cur = (int) ($cur['jours_presence_defaut'] ?? 0);
$prime_transport_cur = (float) ($cur['prime_transport_mensuelle'] ?? 0);
$conges_annuels_cur = (int) ($cur['conges_annuels_global'] ?? 0);
$forfait_hs_cur = (float) ($cur['forfait_heures_sup_mensuel'] ?? 0);
$csrf = (string) $_SESSION['admin_csrf'];
$pct_ret_codes = bp_retenues_codes_taux_brut();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin de paie — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-bulletin-paie.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-parametres-admin page-bulletin-paie-params">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page">
        <div class="bpp-hero">
            <div class="bpp-hero__icon-wrap" aria-hidden="true">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="bpp-hero__text">
                <p class="bpp-hero__eyebrow"><a href="../parametres.php">← Paramètres</a></p>
                <h1 class="bpp-hero__title">Bulletin de paie (Sénégal)</h1>
                <p class="bpp-hero__lead">Configurez l’en-tête <strong>employeur</strong> et les rubriques affichées sur chaque bulletin. Le <strong>salaire de base</strong> et la <strong>période</strong> sont toujours saisis à la génération ; le reste suit les options ci-dessous.</p>
            </div>
        </div>

        <?php if ($error_message !== ''): ?>
            <div class="message error" role="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message !== ''): ?>
            <div class="message success" role="status"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!bp_tables_parametres_disponibles()): ?>
            <div class="message error"><i class="fas fa-database"></i> Exécutez <code>php migrations/run_create_bulletin_paie.php</code> sur le serveur.</div>
        <?php else: ?>

        <form method="post" class="bpp-form" action="bulletin_paie.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action_bulletin_paie" value="save">

            <div class="bpp-section bpp-section--employer">
                <div class="bpp-section__head">
                    <span class="bpp-ic bpp-ic--employer" aria-hidden="true"><i class="fas fa-building"></i></span>
                    <div class="bpp-section__head-text">
                        <h2 class="bpp-section__title">Informations employeur</h2>
                        <p class="bpp-section__subtitle">Identité légale et mentions obligatoires en en-tête de bulletin.</p>
                    </div>
                </div>
                <div class="bpp-section__body bpp-employer-body">
                    <p class="bpp-employer-lead">Ces données servent d’<strong>en-tête officiel</strong> sur tous les bulletins générés. Renseignez-les avec soin : elles figurent sur le document remis au salarié.</p>
                    <div class="bpp-employer-layout">
                        <div class="bpp-employer-stack">
                            <div class="bpp-field bpp-field--primary">
                                <div class="bpp-field__shell">
                                    <span class="bpp-field__ic" aria-hidden="true"><i class="fas fa-building"></i></span>
                                    <div class="bpp-field__body">
                                        <label for="employeur_nom">Raison sociale <span class="req">*</span></label>
                                        <input type="text" id="employeur_nom" name="employeur_nom" required maxlength="255"
                                            placeholder="Ex. Fouta Production SARL"
                                            value="<?php echo htmlspecialchars($cur['employeur_nom']); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="bpp-field bpp-field--address">
                                <div class="bpp-field__shell bpp-field__shell--top">
                                    <span class="bpp-field__ic" aria-hidden="true"><i class="fas fa-map-location-dot"></i></span>
                                    <div class="bpp-field__body">
                                        <label for="employeur_adresse">Siège &amp; adresse postale</label>
                                        <textarea id="employeur_adresse" name="employeur_adresse" rows="3"
                                            placeholder="Rue, quartier, ville, pays…"><?php echo htmlspecialchars($cur['employeur_adresse']); ?></textarea>
                                        <span class="bpp-field__hint">Apparaît telle quelle sur le bulletin (retours à la ligne conservés).</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bpp-employer-legal" role="group" aria-labelledby="bpp-employer-legal-title">
                            <h3 class="bpp-employer-legal__title" id="bpp-employer-legal-title">
                                <i class="fas fa-stamp" aria-hidden="true"></i> Mentions légales &amp; cotisation
                            </h3>
                            <p class="bpp-employer-legal__lead">Numéros d’identification habituels pour un employeur au Sénégal.</p>
                            <div class="bpp-employer-legal-grid">
                                <div class="bpp-field bpp-field--compact">
                                    <div class="bpp-field__shell">
                                        <span class="bpp-field__ic bpp-field__ic--sm" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                                        <div class="bpp-field__body">
                                            <label for="employeur_ninea">NINEA</label>
                                            <input type="text" id="employeur_ninea" name="employeur_ninea" maxlength="80"
                                                placeholder="—"
                                                value="<?php echo htmlspecialchars($cur['employeur_ninea']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="bpp-field bpp-field--compact">
                                    <div class="bpp-field__shell">
                                        <span class="bpp-field__ic bpp-field__ic--sm" aria-hidden="true"><i class="fas fa-scale-balanced"></i></span>
                                        <div class="bpp-field__body">
                                            <label for="employeur_rc">Registre du commerce</label>
                                            <input type="text" id="employeur_rc" name="employeur_rc" maxlength="120"
                                                placeholder="—"
                                                value="<?php echo htmlspecialchars($cur['employeur_rc']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="bpp-field bpp-field--compact">
                                    <div class="bpp-field__shell">
                                        <span class="bpp-field__ic bpp-field__ic--sm" aria-hidden="true"><i class="fas fa-id-card"></i></span>
                                        <div class="bpp-field__body">
                                            <label for="employeur_cnss_ref">Référence CNSS</label>
                                            <input type="text" id="employeur_cnss_ref" name="employeur_cnss_ref" maxlength="120"
                                                placeholder="—"
                                                value="<?php echo htmlspecialchars($cur['employeur_cnss_ref']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bpp-section bpp-section--gains">
                <div class="bpp-section__head">
                    <span class="bpp-ic bpp-ic--gains" aria-hidden="true"><i class="fas fa-circle-plus"></i></span>
                    <div class="bpp-section__head-text">
                        <h2 class="bpp-section__title">Rubriques — gains</h2>
                        <p class="bpp-section__subtitle">Compléments au salaire de base (le salaire de base reste toujours demandé à la génération).</p>
                    </div>
                </div>
                <div class="bpp-section__body">
                <div class="bpp-check-grid">
                    <?php foreach ($rub['gains'] as $code => $on) :
                        if (!isset($lg[$code])) {
                            continue;
                        }
                        if ($code === 'prime_transport') : ?>
                        <div class="bpp-ret-row bpp-ret-row--with-taux">
                            <label class="bpp-check is-checked">
                                <input type="checkbox" checked disabled>
                                <span><?php echo htmlspecialchars($lg[$code]); ?></span>
                            </label>
                            <div class="bpp-ret-taux">
                                <label for="prime_transport_mensuelle_inp">Montant mensuel (FCFA)</label>
                                <input type="number" class="bpp-input-taux" id="prime_transport_mensuelle_inp"
                                    name="prime_transport_mensuelle"
                                    step="0.01" min="0"
                                    value="<?php echo htmlspecialchars(number_format($prime_transport_cur, 2, '.', '')); ?>">
                            </div>
                        </div>
                        <?php
                            continue;
                        endif; ?>
                        <?php if ($code === 'sursalaire') : ?>
                        <div class="bpp-ret-row bpp-ret-row--with-taux">
                            <label class="bpp-check<?php echo $on ? ' is-checked' : ''; ?>">
                                <input type="checkbox" name="rub_gain[<?php echo htmlspecialchars($code); ?>]" value="1"<?php echo $on ? ' checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($lg[$code]); ?></span>
                            </label>
                            <div class="bpp-ret-taux">
                                <label for="forfait_heures_sup_mensuel_inp">Forfait HS mensuel (FCFA)</label>
                                <input type="number" class="bpp-input-taux" id="forfait_heures_sup_mensuel_inp"
                                    name="forfait_heures_sup_mensuel"
                                    step="0.01" min="0"
                                    value="<?php echo htmlspecialchars(number_format($forfait_hs_cur, 2, '.', '')); ?>">
                            </div>
                        </div>
                        <?php
                            continue;
                        endif; ?>
                        <label class="bpp-check<?php echo $on ? ' is-checked' : ''; ?>">
                            <input type="checkbox" name="rub_gain[<?php echo htmlspecialchars($code); ?>]" value="1"<?php echo $on ? ' checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($lg[$code]); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>

            <div class="bpp-section bpp-section--retenues">
                <div class="bpp-section__head">
                    <span class="bpp-ic bpp-ic--retenues" aria-hidden="true"><i class="fas fa-arrow-trend-down"></i></span>
                    <div class="bpp-section__head-text">
                        <h2 class="bpp-section__title">Rubriques — retenues</h2>
                        <p class="bpp-section__subtitle">Cotisations (taux sur brut), IRPP (montant sur la fiche employé) et autres déductions.</p>
                    </div>
                </div>
                <div class="bpp-section__body">
                <p class="bpp-section__subtitle bpp-section__subtitle--inline">Pour <strong>TRIMF</strong>, <strong>IPRES RG</strong>, <strong>IPRES cadre</strong> et <strong>CSS</strong>, indiquez le pourcentage appliqué au <strong>salaire brut</strong>. L’<strong>IRPP</strong> se saisit sur chaque fiche employé (montant fixe mensuel).</p>
                <div class="bpp-check-grid bpp-check-grid--retenues">
                    <?php foreach ($rub['retenues'] as $code => $on) :
                        if (!isset($lr[$code])) {
                            continue;
                        }
                        $is_pct = in_array($code, $pct_ret_codes, true);
                        $tv = isset($taux_cur[$code]) ? (float) $taux_cur[$code] : 0.0;
                        ?>
                        <div class="bpp-ret-row<?php echo $is_pct ? ' bpp-ret-row--with-taux' : ''; ?>">
                        <label class="bpp-check<?php echo $on ? ' is-checked' : ''; ?>">
                            <input type="checkbox" name="rub_ret[<?php echo htmlspecialchars($code); ?>]" value="1"<?php echo $on ? ' checked' : ''; ?><?php echo $is_pct ? ' data-bpp-taux-toggle="' . htmlspecialchars($code) . '"' : ''; ?>>
                            <span><?php echo htmlspecialchars($lr[$code]); ?></span>
                        </label>
                        <?php if ($is_pct && bp_colonne_retenues_taux_disponible()) : ?>
                        <div class="bpp-ret-taux" data-bpp-taux-wrap="<?php echo htmlspecialchars($code); ?>">
                            <label for="taux_ret_<?php echo htmlspecialchars($code); ?>">Taux % (brut)</label>
                            <input type="number" class="bpp-input-taux" id="taux_ret_<?php echo htmlspecialchars($code); ?>"
                                name="taux_retenue[<?php echo htmlspecialchars($code); ?>]"
                                step="0.01" min="0" max="100"
                                value="<?php echo htmlspecialchars((string) $tv); ?>"
                                <?php echo $on ? '' : 'disabled'; ?>>
                        </div>
                        <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>

            <div class="bpp-section bpp-section--travail">
                <div class="bpp-section__head">
                    <span class="bpp-ic bpp-ic--travail" aria-hidden="true"><i class="fas fa-clock"></i></span>
                    <div class="bpp-section__head-text">
                        <h2 class="bpp-section__title">Informations de travail</h2>
                        <p class="bpp-section__subtitle">Champs optionnels (heures, présence) sur le document.</p>
                    </div>
                </div>
                <div class="bpp-section__body">
                <p class="bpp-section__subtitle bpp-section__subtitle--inline">Pour <strong>Jours de présence</strong>, le nombre est <strong>commun à tous les employés</strong> (référence du mois) ; les absences avec retenue sur salaire pour le mois de paie le diminuent automatiquement sur chaque bulletin.</p>
                <div class="bpp-ret-row bpp-ret-row--with-taux bpp-trav-row--jours">
                    <label class="bpp-check is-checked">
                        <input type="checkbox" checked disabled>
                        <span>Congés annuels (gestion RH, hors bulletin)</span>
                    </label>
                    <div class="bpp-ret-taux">
                        <label for="conges_annuels_global_inp">Quota annuel global (jours / employé)</label>
                        <input type="number" class="bpp-input-taux" id="conges_annuels_global_inp"
                            name="conges_annuels_global"
                            min="0" max="365" step="1"
                            value="<?php echo htmlspecialchars((string) $conges_annuels_cur); ?>">
                    </div>
                </div>
                <div class="bpp-check-grid">
                    <?php
                    $lt = [
                        'heures_travaillees' => 'Nombre d’heures travaillées',
                        'heures_sup' => 'Heures supplémentaires (quantité)',
                        'jours_presence' => 'Jours de présence',
                    ];
                    foreach ($rub['travail'] as $code => $on) :
                        if (!isset($lt[$code])) {
                            continue;
                        }
                        if ($code === 'jours_presence' && bp_colonne_jours_presence_defaut_disponible()) :
                            $jval = $jp_def_cur > 0 ? (string) $jp_def_cur : '';
                            ?>
                        <div class="bpp-ret-row bpp-ret-row--with-taux bpp-trav-row--jours">
                        <label class="bpp-check<?php echo $on ? ' is-checked' : ''; ?>">
                            <input type="checkbox" name="rub_trav[<?php echo htmlspecialchars($code); ?>]" value="1"<?php echo $on ? ' checked' : ''; ?> data-bpp-jours-pres-toggle="1">
                            <span><?php echo htmlspecialchars($lt[$code]); ?></span>
                        </label>
                        <div class="bpp-ret-taux" data-bpp-jours-pres-wrap="1">
                            <label for="jours_presence_defaut_inp">Jours (tous salariés / mois)</label>
                            <input type="number" class="bpp-input-taux" id="jours_presence_defaut_inp"
                                name="jours_presence_defaut"
                                min="1" max="31" step="1"
                                value="<?php echo htmlspecialchars($jval); ?>"
                                <?php echo $on ? '' : 'disabled'; ?>>
                        </div>
                        </div>
                            <?php
                            continue;
                        endif; ?>
                        <label class="bpp-check<?php echo $on ? ' is-checked' : ''; ?>">
                            <input type="checkbox" name="rub_trav[<?php echo htmlspecialchars($code); ?>]" value="1"<?php echo $on ? ' checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($lt[$code]); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>

            <div class="bpp-section bpp-section--mentions">
                <div class="bpp-section__head">
                    <span class="bpp-ic bpp-ic--mentions" aria-hidden="true"><i class="fas fa-signature"></i></span>
                    <div class="bpp-section__head-text">
                        <h2 class="bpp-section__title">Mentions finales</h2>
                        <p class="bpp-section__subtitle">Date de paiement, mode de règlement et zone de signature.</p>
                    </div>
                </div>
                <div class="bpp-section__body">
                <div class="bpp-check-grid">
                    <?php
                    $lm = [
                        'date_paiement' => 'Date de paiement (rappel sur le bulletin)',
                        'mode_paiement' => 'Mode de paiement (virement, espèces…)',
                        'signature' => 'Zone signature employeur',
                    ];
                    foreach ($rub['mentions'] as $code => $on) :
                        if (!isset($lm[$code])) {
                            continue;
                        } ?>
                        <label class="bpp-check<?php echo $on ? ' is-checked' : ''; ?>">
                            <input type="checkbox" name="rub_men[<?php echo htmlspecialchars($code); ?>]" value="1"<?php echo $on ? ' checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($lm[$code]); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>

            <div class="bpp-note" role="note">
                <span class="bpp-ic bpp-ic--info" aria-hidden="true"><i class="fas fa-circle-info"></i></span>
                <div>
                    <strong>Rappel de calcul (indicatif)</strong><br>
                    TRIMF, IPRES (RG et cadre) et CSS sont en % du <strong>brut</strong>. L’IRPP est le <strong>montant mensuel</strong> enregistré sur la fiche du salarié. <strong>Net imposable</strong> = brut − (IPRES RG + IPRES cadre + CSS + accident du travail) ; <strong>net à payer</strong> = net imposable − (IRPP + TRIMF + retenue prêt + autres + pénalités d’absence si applicable).
                </div>
            </div>

            <div class="bpp-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer les paramètres</button>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
      function syncChecks(root) {
        root.querySelectorAll('.bpp-check input[type="checkbox"]').forEach(function (inp) {
          var lbl = inp.closest('.bpp-check');
          if (lbl) {
            lbl.classList.toggle('is-checked', inp.checked);
          }
        });
      }
      document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.page-bulletin-paie-params .bpp-form');
        if (!form) {
          return;
        }
        syncChecks(form);
        var jcb = form.querySelector('input[data-bpp-jours-pres-toggle="1"]');
        var jwrap = form.querySelector('[data-bpp-jours-pres-wrap="1"]');
        var jinp = jwrap ? jwrap.querySelector('input[type="number"]') : null;
        if (jcb && jinp) {
          jinp.disabled = !jcb.checked;
        }
        form.addEventListener('change', function (e) {
          if (e.target && e.target.matches && e.target.matches('.bpp-check input[type="checkbox"]')) {
            syncChecks(form);
            var t = e.target.getAttribute('data-bpp-taux-toggle');
            if (t) {
              var wrap = form.querySelector('[data-bpp-taux-wrap="' + t + '"]');
              var inp = wrap ? wrap.querySelector('input.bpp-input-taux') : null;
              if (inp) {
                inp.disabled = !e.target.checked;
              }
            }
            if (e.target.getAttribute('data-bpp-jours-pres-toggle')) {
              var jw = form.querySelector('[data-bpp-jours-pres-wrap="1"]');
              var ji = jw ? jw.querySelector('input[type="number"]') : null;
              if (ji) {
                ji.disabled = !e.target.checked;
              }
            }
          }
        });
      });
    })();
    </script>
</body>
</html>
