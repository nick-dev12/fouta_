<?php
/**
 * Page de détails d'une commande (Admin)
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de la commande
$commande_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($commande_id <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer la commande et ses produits
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_factures.php';
require_once __DIR__ . '/../../includes/format_commande_options.php';
$commande = get_commande_by_id($commande_id);
$produits = get_produits_by_commande($commande_id);
$produits = is_array($produits) ? $produits : [];
$facture = get_facture_by_commande($commande_id);

if (!$commande) {
    header('Location: index.php');
    exit;
}

// Vérifier si la commande est annulée ou livrée (pas de modification possible)
$is_annulee = $commande['statut'] === 'annulee';
$is_livree = $commande['statut'] === 'livree';
$is_paye = $commande['statut'] === 'paye';

// Traiter les actions de statut (uniquement si la commande n'est pas annulée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_annulee) {
    $statut_mis_a_jour = null;

    $admin_traitant = (int) ($_SESSION['admin_id'] ?? 0);

    if (isset($_POST['prendre_en_charge'])) {
        if (update_commande_statut($commande_id, 'prise_en_charge', $admin_traitant)) {
            $statut_mis_a_jour = 'prise_en_charge';
        }
    } elseif (isset($_POST['expedier'])) {
        if (update_commande_statut($commande_id, 'livraison_en_cours', $admin_traitant)) {
            $statut_mis_a_jour = 'livraison_en_cours';
        }
    } elseif (isset($_POST['changer_statut'])) {
        $nouveau_statut = $_POST['statut'] ?? '';
        if (in_array($nouveau_statut, ['en_attente', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'paye', 'annulee'])) {
            if (update_commande_statut($commande_id, $nouveau_statut, $admin_traitant)) {
                $statut_mis_a_jour = $nouveau_statut;
            } else {
                $_SESSION['error_message'] = 'Impossible de mettre à jour le statut. Vérifiez que la migration "add_statut_paye_commandes" a été exécutée et que la commande contient des produits.';
            }
        }
    }

    if ($statut_mis_a_jour !== null) {
        $user_email = trim($commande['user_email'] ?? '');
        if (!empty($user_email)) {
            require_once __DIR__ . '/../../services/send_commande_notification.php';
            send_commande_status_notification(
                (int) ($commande['user_id'] ?? 0),
                $commande['numero_commande'],
                $statut_mis_a_jour,
                $user_email
            );
        }
        $_SESSION['success_message'] = !empty($user_email)
            ? 'Statut de la commande mis à jour avec succès. Une notification et un email ont été envoyés au client.'
            : 'Statut de la commande mis à jour avec succès.';
        header('Location: details.php?id=' . $commande_id);
        exit;
    }
}

$cmd_statut_display = ucfirst(str_replace('_', ' ', $commande['statut'] ?? ''));
if (($commande['statut'] ?? '') === 'annulee') {
    $cmd_statut_display = 'Annulée';
} elseif (($commande['statut'] ?? '') === 'paye') {
    $cmd_statut_display = 'Payée';
}
$cmd_detail_has_alert = isset($_SESSION['success_message']) || isset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Commande #<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-commande-detail">
    <div class="content-header dashboard-hero page-cmd-detail-hero">
        <div class="page-cmd-detail-hero__row">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Commande e-commerce</p>
                <h1>
                    <i class="fas fa-shopping-bag" aria-hidden="true"></i> Commande #<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?>
                </h1>
                <p class="dashboard-subtitle">Statut : <strong><?php echo htmlspecialchars($cmd_statut_display); ?></strong> ·
                    passée le <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?> ·
                    <?php echo number_format((float) ($commande['montant_total'] ?? 0), 0, ',', ' '); ?> FCFA
                </p>
            </div>
            <div class="header-actions page-cmd-detail-actions">
            <?php if ($facture): ?>
                <a href="facture.php?id=<?php echo (int) $facture['id']; ?>" class="btn-primary" target="_blank" rel="noopener">
                    <i class="fas fa-file-invoice" aria-hidden="true"></i> Voir la facture
                </a>
            <?php else: ?>
                <a href="generer_facture.php?id=<?php echo $commande_id; ?>" class="btn-primary">
                    <i class="fas fa-file-invoice" aria-hidden="true"></i> Générer une facture
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn-back page-cmd-detail-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
            </a>
            </div>
        </div>
    </div>

    <?php if ($cmd_detail_has_alert): ?>
    <div class="page-cmd-detail-alerts" role="region" aria-label="Messages">
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success page-cmd-detail-msg">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message'] ?? '');
            unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message error page-cmd-detail-msg">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message'] ?? '');
            unset($_SESSION['error_message']); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($cmd_detail_has_alert): ?>
    </div>
    <?php endif; ?>

    <!-- Détails de la commande -->
    <div class="commande-details-grid page-cmd-detail-grid">
        <div class="detail-box">
            <h3><i class="fas fa-user"></i> Informations Client</h3>
            <div class="detail-item">
                <label>Nom complet</label>
                <div class="value">
                    <?php echo htmlspecialchars(trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''))); ?>
                </div>
            </div>
            <div class="detail-item">
                <label>Email</label>
                <div class="value"><?php echo htmlspecialchars($commande['user_email'] ?? ''); ?></div>
            </div>
            <div class="detail-item">
                <label>Téléphone</label>
                <div class="value"><?php echo htmlspecialchars($commande['user_telephone'] ?? ''); ?></div>
            </div>
        </div>

        <div class="detail-box">
            <h3><i class="fas fa-map-marker-alt"></i> Livraison</h3>
            <div class="detail-item">
                <label>Adresse</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($commande['adresse_livraison'] ?? '')); ?></div>
            </div>
            <div class="detail-item">
                <label>Téléphone livraison</label>
                <div class="value"><?php echo htmlspecialchars($commande['telephone_livraison'] ?? ''); ?></div>
            </div>
            <?php if (!empty($commande['frais_livraison'])): ?>
                <div class="detail-item">
                    <label>Frais de livraison</label>
                    <div class="value"><?php echo number_format($commande['frais_livraison'], 0, ',', ' '); ?> FCFA</div>
                </div>
            <?php endif; ?>
            <div class="detail-item">
                <label>Date commande</label>
                <div class="value"><?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></div>
            </div>
            <?php if ($commande['date_livraison']): ?>
                <div class="detail-item">
                    <label>Date livraison</label>
                    <div class="value"><?php echo date('d/m/Y à H:i', strtotime($commande['date_livraison'])); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Produits de la commande -->
    <section class="content-section page-cmd-detail-section" aria-labelledby="cmd-produits-title">
        <div class="section-title page-cmd-section-title">
            <h2 id="cmd-produits-title"><i class="fas fa-box" aria-hidden="true"></i> Produits commandés</h2>
        </div>

        <div class="produits-list">
            <?php foreach ($produits as $produit): ?>
                <?php $img_src = !empty($produit['image_afficher']) ? $produit['image_afficher'] : ($produit['image_principale'] ?? ''); ?>
                <?php $nom_affichage = !empty($produit['variante_nom']) ? $produit['produit_nom'] . ' → ' . $produit['variante_nom'] : ($produit['produit_nom'] ?? ''); ?>
                <div class="produit-item">
                    <img src="/upload/<?php echo htmlspecialchars($img_src ?? ''); ?>"
                        alt="<?php echo htmlspecialchars($nom_affichage ?? ''); ?>"
                        onerror="this.src='/image/produit1.jpg'">
                    <div class="produit-info">
                        <h4><?php echo htmlspecialchars($nom_affichage ?? ''); ?></h4>
                        <div class="produit-info-lignes">
                            <div class="info-ligne">Quantité: <?php echo $produit['quantite']; ?></div>
                            <div class="info-ligne">Prix unitaire: <?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <?php if (!empty($produit['couleur']) || !empty($produit['poids']) || !empty($produit['taille']) || !empty($produit['variante_nom']) || (!empty($produit['surcout_poids']) && $produit['surcout_poids'] > 0) || (!empty($produit['surcout_taille']) && $produit['surcout_taille'] > 0)): ?>
                        <div class="produit-options-detail">
                            <?php if (!empty($produit['variante_nom'])): ?>
                            <div class="option-detail option-variante">
                                <span class="option-label">Variante:</span>
                                <span class="option-value"><?php echo htmlspecialchars($produit['variante_nom'] ?? ''); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($produit['couleur'])): ?>
                            <?php
                            $hex = trim($produit['couleur']);
                            $is_hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex);
                            $nom_couleur = format_couleur_commande($hex);
                            ?>
                            <div class="option-detail option-couleur">
                                <span class="option-label">Couleur:</span>
                                <?php if ($is_hex): ?>
                                <span class="couleur-swatch-large" style="background-color:<?php echo htmlspecialchars($hex ?? ''); ?>;" title="<?php echo htmlspecialchars($hex ?? ''); ?>"></span>
                                <?php endif; ?>
                                <span class="option-value"><?php echo htmlspecialchars($nom_couleur ?? ''); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php 
                            $poids_raw = $produit['poids'] ?? '';
                            $taille_raw = $produit['taille'] ?? '';
                            $surcout_p = isset($produit['surcout_poids']) ? (float)$produit['surcout_poids'] : 0;
                            $surcout_t = isset($produit['surcout_taille']) ? (float)$produit['surcout_taille'] : 0;
                            $poids_lignes = parse_poids_taille_commande($poids_raw, $surcout_p);
                            $taille_lignes = parse_poids_taille_commande($taille_raw, $surcout_t);
                            $afficher_poids = !empty($poids_lignes);
                            $afficher_taille = !empty($taille_lignes);
                            ?>
                            <?php if ($afficher_poids): ?>
                            <div class="option-detail option-poids">
                                <span class="option-label">Poids:</span>
                                <div class="option-value options-lignes">
                                    <?php foreach ($poids_lignes as $opt): ?>
                                    <div class="option-ligne"><?php 
                                    echo htmlspecialchars($opt['v'] ?? ''); 
                                    if (($opt['s'] ?? 0) > 0) echo ' (poids +' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)';
                                    ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($afficher_taille): ?>
                            <div class="option-detail option-taille">
                                <span class="option-label">Taille:</span>
                                <div class="option-value options-lignes">
                                    <?php foreach ($taille_lignes as $opt): ?>
                                    <div class="option-ligne"><?php 
                                    echo htmlspecialchars($opt['v'] ?? ''); 
                                    if (($opt['s'] ?? 0) > 0) echo ' (taille +' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)';
                                    ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="produit-total">
                        <?php echo number_format($produit['prix_total'], 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="produits-list-total page-cmd-total-bloc">
                <?php
                $sous_total = array_sum(array_column($produits, 'prix_total'));
                $frais = isset($commande['frais_livraison']) ? (float) $commande['frais_livraison'] : 0;
                ?>
                <?php if ($frais > 0): ?>
                    <p class="page-cmd-total-line">Sous-total produits : <strong><?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA</strong></p>
                    <p class="page-cmd-total-line">Frais de livraison : <strong><?php echo number_format($frais, 0, ',', ' '); ?> FCFA</strong></p>
                <?php endif; ?>
                <h3 class="page-cmd-total-final">Total : <span class="total-value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span></h3>
            </div>
        </div>
    </section>

    <!-- Statut & actions -->
    <section class="content-section page-cmd-detail-section" aria-labelledby="cmd-statut-title">
        <div class="section-title page-cmd-section-title">
            <h2 id="cmd-statut-title"><i class="fas fa-tasks" aria-hidden="true"></i> Statut de la commande</h2>
        </div>

        <?php if ($is_annulee): ?>
            <div class="alert-annulee">
                <h3><i class="fas fa-ban"></i> Commande Annulée</h3>
                <p>Cette commande a été annulée. Les actions de modification ne sont pas disponibles. Vous pouvez uniquement
                    consulter les détails.</p>
            </div>
        <?php elseif ($is_livree): ?>
            <div class="alert-livree">
                <h3><i class="fas fa-check-circle"></i> Commande livrée</h3>
                <p>Le client a confirmé la réception du colis. La commande est terminée. Aucune modification n'est possible.
                </p>
            </div>
        <?php elseif ($is_paye): ?>
            <div class="alert-livree">
                <h3><i class="fas fa-money-bill-wave"></i> Commande payée</h3>
                <p>La commande a été marquée comme payée. Le stock a été décrémenté. La commande est terminée.
                </p>
            </div>
        <?php else: ?>
            <div class="statut-form">
                <div class="form-group">
                    <label>Statut actuel</label>
                    <div class="statut-current-wrap">
                        <span class="commande-statut statut-<?php echo $commande['statut']; ?>">
                            <?php
                            $statut_display = ucfirst(str_replace('_', ' ', $commande['statut']));
                            if ($commande['statut'] == 'annulee') {
                                $statut_display = 'Annulée';
                            } elseif ($commande['statut'] == 'paye') {
                                $statut_display = 'Payée';
                            }
                            echo $statut_display;
                            ?>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <?php if (in_array($commande['statut'], ['en_attente', 'confirmee'])): ?>
                        <form method="POST" action="">
                            <button type="submit" name="prendre_en_charge" class="btn-primary btn-prise-charge">
                                <i class="fas fa-hand-paper"></i> Prendre en charge la commande
                            </button>
                        </form>

                    <?php elseif ($commande['statut'] == 'prise_en_charge'): ?>
                        <form method="POST" action="">
                            <button type="submit" name="expedier" class="btn-primary btn-expedier">
                                <i class="fas fa-shipping-fast"></i> Mettre en livraison
                            </button>
                        </form>

                    <?php elseif ($commande['statut'] == 'livraison_en_cours'): ?>
                        <div class="alert-livraison">
                            <p><i class="fas fa-truck"></i> Commande en cours de livraison</p>
                            <p class="sub">Vous pouvez changer le statut manuellement ci-dessous pour la marquer comme "Payée" (décrémente le stock)</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de changement manuel de statut (masqué si livrée ou payée) -->
                <div class="actions-divider">
                    <h3>Changer le statut manuellement</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="statut">Nouveau statut</label>
                            <select id="statut" name="statut" required>
                                <option value="en_attente" <?php echo $commande['statut'] == 'en_attente' ? 'selected' : ''; ?>>En Attente</option>
                                <option value="prise_en_charge" <?php echo $commande['statut'] == 'prise_en_charge' ? 'selected' : ''; ?>>Prise en charge</option>
                                <option value="en_preparation" <?php echo $commande['statut'] == 'en_preparation' ? 'selected' : ''; ?>>En Préparation</option>
                                <option value="livraison_en_cours" <?php echo $commande['statut'] == 'livraison_en_cours' ? 'selected' : ''; ?>>Livraison en cours</option>
                                <option value="paye" <?php echo $commande['statut'] == 'paye' ? 'selected' : ''; ?>>Payée (décrémente le stock)</option>
                                <option value="annulee" <?php echo $commande['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                        <?php if ($commande['notes']): ?>
                            <div class="form-group">
                                <label>Notes</label>
                                <div class="notes-box">
                                    <?php echo nl2br(htmlspecialchars($commande['notes'] ?? '')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <button type="submit" name="changer_statut" class="btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour le statut
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>

    </div>

    <?php include '../includes/footer.php'; ?>