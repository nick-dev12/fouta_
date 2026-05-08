<?php
/**
 * Fiche client B2B — BL + facturation mensuelle HT (comptabilité)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_clients_b2b.php';
require_once __DIR__ . '/../../models/model_factures_mensuelles.php';

if (!bl_tables_available()) {
    header('Location: index.php?tab=bl');
    exit;
}

$client_b2b_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($client_b2b_id <= 0) {
    header('Location: index.php?tab=bl');
    exit;
}

$client = get_client_b2b_by_id($client_b2b_id);
if (!$client) {
    header('Location: index.php?tab=bl');
    exit;
}

$fm_tables_ok = factures_mensuelles_table_ok();
$bl_list = get_all_bl_for_client_b2b($client_b2b_id, true);
$nb_bl_fm_archives = $fm_tables_ok ? count_bl_lies_fm_tout_statut_pour_client($client_b2b_id) : 0;
$raison = $client['raison_sociale'] ?? '';
$contact_nom = trim(($client['nom_contact'] ?? '') . ' ' . ($client['prenom_contact'] ?? ''));

$initials = '?';
if ($raison !== '') {
    $words = preg_split('/\s+/u', $raison, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) >= 2) {
        $initials = mb_strtoupper(
            mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1),
            'UTF-8'
        );
    } else {
        $initials = mb_strtoupper(mb_substr($raison, 0, min(2, mb_strlen($raison, 'UTF-8')), 'UTF-8'), 'UTF-8');
    }
}

$nb_bl = count($bl_list);

$nb_bl_a_facturer = $fm_tables_ok ? count(get_bl_valides_non_factures($client_b2b_id)) : 0;
$fm_derniere = $fm_tables_ok ? get_facture_mensuelle_derniere_pour_client($client_b2b_id) : false;

$mois_sel_fr = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];

$fm_erreur = $_SESSION['fm_erreur'] ?? null;
if (isset($_SESSION['fm_erreur'])) {
    unset($_SESSION['fm_erreur']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bons de livraison — <?php echo htmlspecialchars($raison); ?> — Comptabilité</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-truck-loading" aria-hidden="true"></i> Bons de livraison</h1>
            <p class="bl-page-header__sub">Client professionnel — suivi comptable &amp; facture mensuelle HT</p>
        </div>
        <div class="header-actions bl-page-header__actions">
            <a href="index.php?tab=bl" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour comptabilité (BL)</a>
        </div>
    </div>

    <?php if (!empty($fm_erreur)): ?>
        <div class="message error" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($fm_erreur); ?></span>
        </div>
    <?php endif; ?>

    <section class="content-section bl-detail-page">
        <div class="bl-tab-surface">
            <header class="bl-client-banner" aria-labelledby="bl-client-banner-title">
                <div class="bl-client-banner__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                <div class="bl-client-banner__body">
                    <h2 id="bl-client-banner-title" class="bl-client-banner__title"><?php echo htmlspecialchars($raison ?: '—'); ?></h2>
                    <?php if ($contact_nom !== ''): ?>
                        <p class="bl-client-banner__contact">
                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($contact_nom); ?>
                        </p>
                    <?php endif; ?>
                    <ul class="bl-client-banner__meta">
                        <li>
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                            <span><?php echo htmlspecialchars($client['telephone'] ?? '—'); ?></span>
                        </li>
                        <li>
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                            <span><?php echo !empty($client['email']) ? htmlspecialchars($client['email']) : '—'; ?></span>
                        </li>
                        <?php if (!empty($client['adresse'])): ?>
                        <li class="bl-client-banner__meta--full">
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                            <span><?php echo nl2br(htmlspecialchars($client['adresse'])); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="bl-client-banner__stat">
                    <span class="bl-client-banner__stat-num"><?php echo (int) $nb_bl; ?></span>
                    <span class="bl-client-banner__stat-label">BL enregistré<?php echo $nb_bl > 1 ? 's' : ''; ?></span>
                </div>
            </header>

            <?php if ($fm_tables_ok): ?>
                <div class="bl-facture-bar">
                    <div class="bl-facture-bar__text">
                        <strong><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Facturation HT (facture mensuelle)</strong>
                    </div>
                    <div class="bl-facture-bar__actions">
                        <?php if (!empty($fm_derniere)): ?>
                            <a href="../devis/facture_mensuelle.php?id=<?php echo (int) $fm_derniere['id']; ?>" class="btn-secondary"><i class="fas fa-eye" aria-hidden="true"></i> Voir la dernière facture</a>
                        <?php endif; ?>
                        <a href="bl-factures-archives.php?client=<?php echo (int) $client_b2b_id; ?>" class="btn-secondary"><i class="fas fa-list" aria-hidden="true"></i> Liste des factures</a>
                        <a href="../devis/facture_mensuelle_generer.php?client_b2b_id=<?php echo (int) $client_b2b_id; ?>" class="btn-primary"><i class="fas fa-magic" aria-hidden="true"></i> Générer / mettre à jour la facture</a>
                    </div>
                </div>
                <div class="bl-fm-period-row form-hint" style="margin-top:14px;padding:14px 16px;border-radius:12px;border:1px solid var(--glass-border);background:var(--blanc-neige);">
                    <strong style="display:block;margin-bottom:8px;"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Période comptable (facultatif)</strong>
                    <form method="get" action="../devis/facture_mensuelle_generer.php" class="bl-fm-period-form" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;">
                        <input type="hidden" name="client_b2b_id" value="<?php echo (int) $client_b2b_id; ?>">
                        <label style="display:flex;flex-direction:column;gap:4px;font-size:0.88rem;">
                            Mois
                            <select name="mois" class="input-field" style="min-width:160px;padding:8px 10px;border-radius:8px;border:1px solid var(--border-input);">
                                <?php
                                $m_cur = (int) date('n');
                                foreach ($mois_sel_fr as $mv => $ml):
                                ?>
                                    <option value="<?php echo (int) $mv; ?>"<?php echo $mv === $m_cur ? ' selected' : ''; ?>><?php echo htmlspecialchars($ml); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="display:flex;flex-direction:column;gap:4px;font-size:0.88rem;">
                            Année
                            <select name="annee" class="input-field" style="min-width:100px;padding:8px 10px;border-radius:8px;border:1px solid var(--border-input);">
                                <?php
                                $y_cur = (int) date('Y');
                                for ($yy = $y_cur + 1; $yy >= $y_cur - 3; $yy--):
                                ?>
                                    <option value="<?php echo (int) $yy; ?>"<?php echo $yy === $y_cur ? ' selected' : ''; ?>><?php echo (int) $yy; ?></option>
                                <?php    
                                endfor;
                                ?>
                            </select>
                        </label>
                        <button type="submit" class="btn-secondary" style="padding:10px 16px;border-radius:10px;cursor:pointer;border:1px solid var(--border-input);background:var(--blanc);font-weight:600;color:var(--couleur-dominante);">
                            <i class="fas fa-magic" aria-hidden="true"></i> Générer pour cette période
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <p class="form-hint bl-facture-bar--warn"><i class="fas fa-database"></i> Factures mensuelles indisponibles : exécutez la migration <code>migrations/migration_admin_b2b_structure.sql</code>.</p>
            <?php endif; ?>
        </div>

        <?php if (empty($bl_list)): ?>
            <div class="bl-empty-state bl-empty-state--compact" role="status">
                <div class="bl-empty-state__visual" aria-hidden="true">
                    <span class="bl-empty-state__ring"></span>
                    <i class="fas fa-file-invoice"></i>
                </div>
                <?php if ($nb_bl_fm_archives > 0): ?>
                    <h3 class="bl-empty-state__title">Aucun BL actif à afficher</h3>
                    <div class="bl-empty-state__actions" style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
                        <a href="bl-factures-archives.php?client=<?php echo (int) $client_b2b_id; ?>" class="btn-primary bl-empty-state__btn"><i class="fas fa-list" aria-hidden="true"></i> Liste des factures</a>
                        <a href="index.php?tab=bl" class="btn-secondary bl-empty-state__btn"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour comptabilité</a>
                    </div>
                <?php else: ?>
                    <h3 class="bl-empty-state__title">Aucun bon de livraison</h3>
                    <p class="bl-empty-state__text">Ce contact n’a pas encore de BL hors facture mensuelle, ou tous les BL sont encore en brouillon.</p>
                    <a href="index.php?tab=bl" class="btn-primary bl-empty-state__btn"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bl-list-section">
                <h2 id="bl-fiche-bl-list-title" class="bl-list-section__title"><i class="fas fa-list-ul" aria-hidden="true"></i> Liste des bons de livraison</h2>
                <div class="bl-fiche-bl-table-wrap">
                    <table class="bl-fiche-bl-table" aria-labelledby="bl-fiche-bl-list-title">
                        <thead>
                            <tr>
                                <th scope="col" class="bl-fiche-bl-table__th"><span class="bl-fiche-bl-table__th-in"><i class="fas fa-file-alt" aria-hidden="true"></i> N° BL</span></th>
                                <th scope="col" class="bl-fiche-bl-table__th"><span class="bl-fiche-bl-table__th-in"><i class="fas fa-calendar-day" aria-hidden="true"></i> Date</span></th>
                                <th scope="col" class="bl-fiche-bl-table__th"><span class="bl-fiche-bl-table__th-in"><i class="fas fa-flag" aria-hidden="true"></i> Statut</span></th>
                                <th scope="col" class="bl-fiche-bl-table__th bl-fiche-bl-table__th--amount"><span class="bl-fiche-bl-table__th-in"><i class="fas fa-coins" aria-hidden="true"></i> Total HT</span></th>
                                <th scope="col" class="bl-fiche-bl-table__th bl-fiche-bl-table__th--actions"><span class="bl-fiche-bl-table__th-in"><i class="fas fa-bolt" aria-hidden="true"></i> Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                    <?php foreach ($bl_list as $b): ?>
                        <?php
                        $bst = $b['statut'] ?? 'brouillon';
                        $bst_label = bl_libelle_statut_court($bst);
                        $bid = (int) $b['id'];
                        $dt_bl = !empty($b['date_bl']) ? htmlspecialchars((string) $b['date_bl']) : '—';
                        ?>
                            <tr class="bl-fiche-bl-table__row">
                                <td class="bl-fiche-bl-table__td"><strong class="bl-fiche-bl-table__ref"><?php echo htmlspecialchars($b['numero_bl'] ?? ''); ?></strong></td>
                                <td class="bl-fiche-bl-table__td"><?php echo $dt_bl; ?></td>
                                <td class="bl-fiche-bl-table__td bl-fiche-bl-table__td--statut"><span class="commande-statut statut-<?php echo htmlspecialchars($bst); ?>"><?php echo htmlspecialchars($bst_label); ?></span></td>
                                <td class="bl-fiche-bl-table__td bl-fiche-bl-table__td--montant"><?php echo number_format((float) ($b['total_ht'] ?? 0), 0, ',', ' '); ?> <small>FCFA</small></td>
                                <td class="bl-fiche-bl-table__td bl-fiche-bl-table__td--actions">
                                    <div class="bl-fiche-bl-table__actions">
                                        <a href="../devis/bl_voir.php?id=<?php echo $bid; ?>" class="bl-record-card__btn bl-record-card__btn--primary bl-fiche-bl-table__btn"><i class="fas fa-eye" aria-hidden="true"></i> Ouvrir</a>
                                        <?php if (!bl_est_statut_verrouille($bst)): ?>
                                        <a href="../devis/bl_modifier.php?id=<?php echo $bid; ?>" class="bl-record-card__btn bl-record-card__btn--secondary bl-fiche-bl-table__btn"><i class="fas fa-edit" aria-hidden="true"></i> Réajuster</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
