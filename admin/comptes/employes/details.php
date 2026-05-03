<?php
/**
 * Détail employé — infos, QR badge, absences et justificatifs (fiche employes uniquement).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../models/model_employes.php';
require_once __DIR__ . '/../../../models/model_employe_absences.php';
require_once __DIR__ . '/../../../includes/carte_employe_rh.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$carte_prep = employes_carte_rh_preparer_variables($id);
if (!$carte_prep) {
    header('Location: index.php');
    exit;
}

$f = $carte_prep['f'];
$file_abs = (string) $carte_prep['upload_disk'];
$photo_rel = (string) $carte_prep['photo_rel'];
$photo_disk_ok = !empty($carte_prep['photo_disk_ok']);
$upload_public = (string) $carte_prep['upload_public'];
$carte_matricule = (string) $carte_prep['matricule'];
$carte_html_ecran = employes_carte_rh_rendre_html($carte_prep, '');

$lignes_abs_brutes = employe_absences_detail_pour_fiche_employe($id);
$fusion_abs = [];
foreach ($lignes_abs_brutes as $r) {
    $aid = (int) ($r['absence_id'] ?? 0);
    if ($aid <= 0) {
        continue;
    }
    if (!isset($fusion_abs[$aid])) {
        $fusion_abs[$aid] = $r;
    }
    if (!empty($r['justif_id'])) {
        $fusion_abs[$aid]['justif_id'] = $r['justif_id'];
    }
}
$lignes_abs = array_values($fusion_abs);
usort($lignes_abs, function ($a, $b) {
    $da = (string) ($a['date_absence'] ?? '');
    $db = (string) ($b['date_absence'] ?? '');
    if ($da === $db) {
        return (int) ($b['absence_id'] ?? 0) <=> (int) ($a['absence_id'] ?? 0);
    }
    return strcmp($db, $da);
});
$nb_absences = count($lignes_abs);

$justifs_par_id = [];
foreach ($lignes_abs_brutes as $r) {
    if (empty($r['justif_id'])) {
        continue;
    }
    $jid = (int) $r['justif_id'];
    if ($jid <= 0 || isset($justifs_par_id[$jid])) {
        continue;
    }
    $lib = '';
    if (!empty($r['justif_nom_fichier'])) {
        $lib = (string) $r['justif_nom_fichier'];
    } elseif (!empty($r['justif_texte'])) {
        $lib = 'Texte : ' . mb_strimwidth(trim((string) $r['justif_texte']), 0, 80, '…', 'UTF-8');
    } else {
        $lib = 'Justificatif';
    }
    $justifs_par_id[$jid] = [
        'absence_id'   => (int) $r['absence_id'],
        'date_absence' => $r['date_absence'],
        'libelle'      => $lib,
        'fichier_rel'  => $r['justif_fichier_chemin'] ?? '',
        'snippet'      => $r['justif_texte'] ?? '',
        'date_justif'  => $r['justif_creation'] ?? '',
    ];
}
$lignes_justifs = array_values($justifs_par_id);

$titre = htmlspecialchars(trim(($f['prenom'] ?? '') . ' ' . ($f['nom'] ?? ''))) . ' — Détails';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titre; ?></title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employes-rh.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-employes-rh page-employes-detail">
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-comptes-wrap er-page">
        <header class="er-detail-hero">
            <?php if ($photo_disk_ok): ?>
                <div class="er-detail-hero__avatar er-detail-hero__avatar--photo" aria-hidden="true">
                    <img src="<?php echo htmlspecialchars($upload_public . $photo_rel); ?>" alt=""
                        class="er-detail-hero__photo-img" width="108" height="108" decoding="async">
                </div>
            <?php else: ?>
                <div class="er-detail-hero__avatar" aria-hidden="true"><?php echo strtoupper(substr((string) ($f['prenom'] ?? '?'), 0, 1)); ?></div>
            <?php endif; ?>
            <div class="er-detail-hero__intro">
                <p class="page-comptes-eyebrow">Fiche employé</p>
                <h1><?php echo htmlspecialchars(trim(($f['prenom'] ?? '') . ' ' . ($f['nom'] ?? ''))); ?></h1>
                <p class="er-detail-hero__poste"><?php echo htmlspecialchars(($f['poste'] ?? '') !== '' ? $f['poste'] : '—'); ?></p>
            </div>
            <div class="er-detail-hero__actions">
                <a href="carte_imprimer.php?id=<?php echo (int) $id; ?>" class="page-comptes-cta er-btn-primary" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimer la carte</a>
                <a href="modifier.php?id=<?php echo (int) $id; ?>" class="page-comptes-cta page-comptes-cta--secondary"><i class="fas fa-pen"></i> Modifier</a>
                <a href="index.php" class="page-comptes-cta page-comptes-cta--ghost"><i class="fas fa-arrow-left"></i> Liste</a>
            </div>
        </header>

        <div class="er-detail-kpis">
            <div class="er-detail-kpi"><span class="er-detail-kpi__v"><?php echo $nb_absences; ?></span><span class="er-detail-kpi__l">Absence(s) enregistrée(s)</span></div>
            <div class="er-detail-kpi er-detail-kpi--orange"><span class="er-detail-kpi__v"><?php echo count($lignes_justifs); ?></span><span class="er-detail-kpi__l">Justificatif(s)</span></div>
        </div>

        <div class="er-detail-grid">
            <section class="er-detail-card">
                <h2 class="er-detail-card__title"><i class="fas fa-address-card" aria-hidden="true"></i> Informations</h2>
                <ul class="er-detail-infos">
                    <li><span class="l">Nom</span><span class="v"><?php echo htmlspecialchars($f['nom'] ?? ''); ?></span></li>
                    <li><span class="l">Prénom</span><span class="v"><?php echo htmlspecialchars($f['prenom'] ?? ''); ?></span></li>
                    <li><span class="l">Matricule</span><span class="v"><?php echo htmlspecialchars($carte_matricule); ?></span></li>
                    <li><span class="l">Téléphone</span><span class="v"><?php echo !empty($f['telephone']) ? htmlspecialchars((string) $f['telephone']) : '—'; ?></span></li>
                    <li><span class="l">Fonction</span><span class="v"><?php echo !empty($f['poste']) ? htmlspecialchars((string) $f['poste']) : '—'; ?></span></li>
                    <?php if (!empty($f['email'])): ?>
                    <li><span class="l">Email</span><span class="v"><?php echo htmlspecialchars($f['email']); ?></span></li>
                    <?php endif; ?>
                    <li><span class="l">Statut</span><span class="v"><?php echo htmlspecialchars($f['statut'] ?? ''); ?></span></li>
                </ul>
            </section>

            <section class="er-detail-card er-detail-card--carte-rh" aria-label="Carte d'identité employé">
                <?php echo $carte_html_ecran; ?>
            </section>
        </div>

        <section class="er-detail-card er-detail-card--full">
            <h2 class="er-detail-card__title"><i class="fas fa-calendar-xmark" aria-hidden="true"></i> Liste des absences</h2>
            <?php if (empty($lignes_abs)): ?>
                <p class="er-detail-muted">Aucune absence liée à cette fiche (hors comptes admin).</p>
            <?php else: ?>
                <div class="er-table-scroll">
                    <table class="er-detail-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Motif</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes_abs as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($r['date_absence']))); ?></td>
                                    <td><?php echo htmlspecialchars(mb_strimwidth((string) $r['motif'], 0, 100, '…', 'UTF-8')); ?></td>
                                    <td><?php echo !empty($r['justif_id']) ? '<span class="er-pill ok">Justifiée</span>' : '<span class="er-pill wait">En attente</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="er-detail-card er-detail-card--full">
            <h2 class="er-detail-card__title"><i class="fas fa-file-signature" aria-hidden="true"></i> Justificatifs d’absence</h2>
            <?php if (empty($lignes_justifs)): ?>
                <p class="er-detail-muted">Aucun justificatif enregistré pour cette personne.</p>
            <?php else: ?>
                <div class="er-table-scroll">
                    <table class="er-detail-table">
                        <thead>
                            <tr>
                                <th>Date absence</th>
                                <th>Nom du justificatif</th>
                                <th>Détail / fichier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes_justifs as $j): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($j['date_absence']))); ?></td>
                                    <td><?php echo htmlspecialchars($j['libelle']); ?></td>
                                    <td>
                                        <?php if (!empty($j['fichier_rel'])): ?>
                                            <a href="<?php echo htmlspecialchars($upload_public . $j['fichier_rel']); ?>" target="_blank" rel="noopener" class="er-link"><i class="fas fa-image"></i> Ouvrir le fichier</a>
                                        <?php endif; ?>
                                        <?php if (!empty($j['snippet']) && empty($j['fichier_rel'])): ?>
                                            <span class="er-detail-muted"><?php echo htmlspecialchars(mb_strimwidth(trim((string) $j['snippet']), 0, 120, '…', 'UTF-8')); ?></span>
                                        <?php elseif (!empty($j['snippet']) && !empty($j['fichier_rel'])): ?>
                                            <div class="er-detail-muted sm"><?php echo htmlspecialchars(mb_strimwidth(trim((string) $j['snippet']), 0, 100, '…', 'UTF-8')); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
