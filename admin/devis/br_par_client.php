<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_clients_b2b.php';
require_once __DIR__ . '/../../models/model_bons_retour.php';
if (!br_retour_tables_available()) {
    header('Location: index.php?tab=br');
    exit;
}

$client_b2b_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($client_b2b_id <= 0) {
    header('Location: index.php?tab=br');
    exit;
}

$client = get_client_b2b_by_id($client_b2b_id);
if (!$client) {
    header('Location: index.php?tab=br');
    exit;
}

$br_list = br_get_all_for_client_b2b($client_b2b_id);
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

$nb_br = count($br_list);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bons de retour — <?php echo htmlspecialchars($raison); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-undo" aria-hidden="true"></i> Bons de retour</h1>
            <p class="bl-page-header__sub">Historique des bons de retour pour ce client professionnel</p>
        </div>
        <div class="header-actions bl-page-header__actions">
            <a href="index.php?tab=br" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Contacts BR</a>
            <a href="bl_par_client.php?id=<?php echo (int) $client_b2b_id; ?>" class="btn-secondary"><i class="fas fa-truck-loading" aria-hidden="true"></i> BL du même client</a>
        </div>
    </div>

    <section class="content-section bl-detail-page">
        <div class="bl-tab-surface">
            <header class="bl-client-banner" aria-labelledby="br-client-banner-title">
                <div class="bl-client-banner__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                <div class="bl-client-banner__body">
                    <h2 id="br-client-banner-title" class="bl-client-banner__title"><?php echo htmlspecialchars($raison ?: '—'); ?></h2>
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
                    <span class="bl-client-banner__stat-num"><?php echo (int) $nb_br; ?></span>
                    <span class="bl-client-banner__stat-label">BR enregistré<?php echo $nb_br > 1 ? 's' : ''; ?></span>
                </div>
            </header>
        </div>

        <?php if (empty($br_list)): ?>
            <div class="bl-empty-state bl-empty-state--compact" role="status">
                <div class="bl-empty-state__visual" aria-hidden="true">
                    <span class="bl-empty-state__ring"></span>
                    <i class="fas fa-undo"></i>
                </div>
                <h3 class="bl-empty-state__title">Aucun bon de retour</h3>
                <p class="bl-empty-state__text">Aucune sortie marchandise n’a été enregistrée pour ce contact, ou les données ont été retirées.</p>
                <a href="index.php?tab=br" class="btn-primary bl-empty-state__btn"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux contacts</a>
            </div>
        <?php else: ?>
            <div class="bl-list-section">
                <h2 class="bl-list-section__title"><i class="fas fa-list-ul" aria-hidden="true"></i> Liste des bons de retour</h2>
                <p class="bl-list-section__hint"><?php echo (int) $nb_br; ?> document<?php echo $nb_br > 1 ? 's' : ''; ?> — cliquez sur « Ouvrir » pour le détail ou sur le BL associé pour le bon de livraison.</p>
                <div class="bl-record-grid" role="list">
                    <?php foreach ($br_list as $br): ?>
                        <?php
                        $brid = (int) ($br['id'] ?? 0);
                        $bid = (int) ($br['bl_id'] ?? 0);
                        $dt_retour = !empty($br['date_retour'])
                            ? date('d/m/Y à H:i', strtotime($br['date_retour']))
                            : '—';
                        ?>
                        <article class="bl-record-card bl-record-card--br" role="listitem">
                            <div class="bl-record-card__top">
                                <div class="bl-record-card__ids">
                                    <h3 class="bl-record-card__num"><?php echo htmlspecialchars($br['numero_br'] ?? ''); ?></h3>
                                    <p class="bl-record-card__date"><i class="fas fa-undo" aria-hidden="true"></i> <?php echo htmlspecialchars($dt_retour); ?></p>
                                    <?php if ($bid > 0): ?>
                                        <p class="bl-record-card__link-bl">
                                            <i class="fas fa-file-invoice" aria-hidden="true"></i>
                                            <a href="bl_voir.php?id=<?php echo $bid; ?>" class="bl-dl__link"><?php echo htmlspecialchars($br['numero_bl'] ?? ''); ?></a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="bl-record-card__tag-br" title="Bon de retour">BR</span>
                            </div>
                            <div class="bl-record-card__amount">
                                <span class="bl-record-card__amount-label">Total HT retour</span>
                                <span class="bl-record-card__amount-val"><?php echo number_format((float) ($br['total_ht_retour'] ?? 0), 0, ',', ' '); ?> <small>FCFA</small></span>
                            </div>
                            <div class="bl-record-card__actions">
                                <a href="br_voir.php?id=<?php echo $brid; ?>" class="bl-record-card__btn bl-record-card__btn--primary"><i class="fas fa-eye" aria-hidden="true"></i> Ouvrir</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
