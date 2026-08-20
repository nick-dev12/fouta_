<?php
/**
 * BL rattachés à une facture mensuelle (brouillon, validée ou payée) — vue par facture
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
require_once __DIR__ . '/../../models/model_factures_mensuelles.php';

if (!bl_tables_available() || !factures_mensuelles_table_ok()) {
    header('Location: index.php?tab=bl');
    exit;
}

$client_id = isset($_GET['client']) ? (int) $_GET['client'] : 0;
$fm_id = isset($_GET['fm']) ? (int) $_GET['fm'] : 0;

$bls = [];
$fm_row = ($client_id > 0 && $fm_id > 0) ? get_facture_mensuelle_by_id($fm_id) : false;
$fm_access_ok = $fm_row
    && in_array((string) ($fm_row['statut'] ?? ''), ['brouillon', 'validee', 'payee'], true)
    && (int) ($fm_row['client_b2b_id'] ?? 0) === $client_id;
if ($fm_access_ok) {
    $bls = get_bl_fm_archive_pour_fm_et_client($fm_id, $client_id);
}

$mois_fr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$fm_num = $fm_row ? (string) ($fm_row['numero_facture'] ?? '') : '';
$fm_m = $fm_row ? (int) ($fm_row['mois'] ?? 0) : 0;
$fm_a = $fm_row ? (int) ($fm_row['annee'] ?? 0) : 0;
$per_fm = ($fm_m >= 1 && $fm_m <= 12 && $fm_a > 0) ? ($mois_fr[$fm_m] . ' ' . $fm_a) : '—';
$fm_st = $fm_row ? (string) ($fm_row['statut'] ?? '') : '';
$fm_st_label = $fm_st === 'payee' ? 'Payée' : ($fm_st === 'validee' ? 'Impayée' : ($fm_st === 'brouillon' ? 'Brouillon' : $fm_st));

$client_row = null;
if ($client_id > 0) {
    $groupes = get_bl_fm_archive_groupes_par_client();
    foreach ($groupes as $g) {
        if ((int) ($g['client']['id'] ?? 0) === $client_id) {
            $client_row = $g['client'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BL — <?php echo htmlspecialchars($fm_num !== '' ? $fm_num : 'Facture'); ?> — Archives</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-truck-loading" aria-hidden="true"></i> Bons de livraison de la facture</h1>
            <p class="bl-page-header__sub">
                <?php if ($fm_access_ok): ?>
                    <strong><?php echo htmlspecialchars($fm_num !== '' ? $fm_num : '#' . $fm_id); ?></strong>
                    · <?php echo htmlspecialchars($per_fm); ?>
                    · <?php echo htmlspecialchars($fm_st_label); ?>
                    <?php if ($client_row): ?>
                        · <?php echo htmlspecialchars(trim((string) ($client_row['raison_sociale'] ?? '')) ?: 'Client'); ?>
                    <?php endif; ?>
                <?php else: ?>
                    Archives introuvables ou accès refusé.
                <?php endif; ?>
            </p>
        </div>
        <div class="header-actions bl-page-header__actions">
            <a href="bl-factures-archives.php?client=<?php echo (int) $client_id; ?>" class="btn-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Factures du client
            </a>
            <?php if ($fm_access_ok && $fm_id > 0): ?>
                <a href="../devis/facture_mensuelle.php?id=<?php echo (int) $fm_id; ?>" class="btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Voir la facture HT
                </a>
            <?php endif; ?>
            <a href="bl-factures-archives.php" class="btn-back"><i class="fas fa-box-archive" aria-hidden="true"></i> Tous les dossiers</a>
        </div>
    </div>

    <section class="content-section bl-detail-page">
        <?php if (!$fm_access_ok): ?>
            <div class="message error" role="alert" style="max-width:720px;margin:0 auto;">
                <i class="fas fa-exclamation-circle"></i>
                Impossible d’afficher les bons pour cette facture (facture absente, client incorrect ou statut invalide).
                <a href="bl-factures-archives.php?client=<?php echo (int) $client_id; ?>">Retour</a>
            </div>
        <?php else: ?>
            <div class="bl-tab-surface" style="margin-bottom:18px;">
                <p class="form-hint" style="margin:0;">
                    <strong><?php echo count($bls); ?></strong> bon<?php echo count($bls) > 1 ? 's' : ''; ?> de livraison
                    <?php if (count($bls) > 0): ?>
                        · Total HT (BL) : <strong><?php echo number_format(array_sum(array_map(static function ($x) {
                            return (float) ($x['total_ht'] ?? 0);
                        }, $bls)), 0, ',', ' '); ?> FCFA</strong>
                    <?php endif; ?>
                </p>
            </div>
            <div class="bl-list-section" style="padding-top:0;">
                <?php if (count($bls) === 0): ?>
                    <p class="form-hint" role="status">Aucun bon de livraison n’est lié à cette facture dans les archives.</p>
                <?php endif; ?>
                <div class="bl-record-grid" role="list">
                    <?php foreach ($bls as $b):
                        $bst = $b['statut'] ?? 'brouillon';
                        $bst_label = function_exists('bl_libelle_statut_court') ? bl_libelle_statut_court($bst) : $bst;
                        $bid = (int) $b['id'];
                        ?>
                        <article class="bl-record-card" role="listitem">
                            <div class="bl-record-card__top">
                                <div class="bl-record-card__ids">
                                    <h3 class="bl-record-card__num"><?php echo htmlspecialchars($b['numero_bl'] ?? ''); ?></h3>
                                    <?php if (!empty($b['date_bl'])): ?>
                                        <p class="bl-record-card__date"><i class="fas fa-calendar-day" aria-hidden="true"></i> <?php echo htmlspecialchars($b['date_bl']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="commande-statut statut-<?php echo htmlspecialchars($bst); ?>"><?php echo htmlspecialchars($bst_label); ?></span>
                            </div>
                            <div class="bl-record-card__amount">
                                <span class="bl-record-card__amount-label">Total HT</span>
                                <span class="bl-record-card__amount-val"><?php echo number_format((float) ($b['total_ht'] ?? 0), 0, ',', ' '); ?> <small>FCFA</small></span>
                            </div>
                            <div class="bl-record-card__actions">
                                <a href="../devis/bl_voir.php?id=<?php echo $bid; ?>" class="bl-record-card__btn bl-record-card__btn--primary"><i class="fas fa-eye" aria-hidden="true"></i> Ouvrir le BL</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
