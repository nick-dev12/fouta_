<?php
/**
 * Détails et traitement d'une commande personnalisée (Admin)
 * Design élégant, ergonomique et responsive
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$cp_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($cp_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../../models/model_factures_personnalisees.php';
require_once __DIR__ . '/../../includes/site_url.php';
$cp = get_commande_personnalisee_by_id($cp_id);
$facture_cp = get_facture_personnalisee_by_cp($cp_id);

if (!$cp) {
    header('Location: index.php');
    exit;
}

$statuts_labels = get_statuts_commande_personnalisee();
$is_annulee = $cp['statut'] === 'annulee';
$is_refusee = $cp['statut'] === 'refusee';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_annulee && !$is_refusee) {
    if (isset($_POST['changer_statut'])) {
        $nouveau_statut = $_POST['statut'] ?? '';
        $notes_admin = isset($_POST['notes_admin']) ? trim($_POST['notes_admin']) : null;
        if (in_array($nouveau_statut, array_keys($statuts_labels))) {
            if (update_commande_personnalisee_statut($cp_id, $nouveau_statut, $notes_admin)) {
                $_SESSION['success_message'] = 'Statut mis à jour avec succès.';
                header('Location: details.php?id=' . $cp_id);
                exit;
            }
        }
    } elseif (isset($_POST['sauvegarder_notes'])) {
        $notes_admin = isset($_POST['notes_admin']) ? trim($_POST['notes_admin']) : '';
        if (update_commande_personnalisee_notes($cp_id, $notes_admin)) {
            $_SESSION['success_message'] = 'Notes enregistrées.';
            header('Location: details.php?id=' . $cp_id);
            exit;
        }
    } elseif (isset($_POST['enregistrer_prix'])) {
        $prix_raw = isset($_POST['prix']) ? trim($_POST['prix']) : '';
        $prix = $prix_raw !== '' ? (float) str_replace([' ', ','], ['', '.'], $prix_raw) : null;
        if ($prix !== null && $prix < 0) {
            $_SESSION['error_message'] = 'Le prix ne peut pas être négatif.';
        } elseif (update_commande_personnalisee_prix($cp_id, $prix)) {
            $_SESSION['success_message'] = $prix !== null ? 'Prix enregistré : ' . number_format($prix, 0, ',', ' ') . ' CFA.' : 'Prix supprimé.';
            header('Location: details.php?id=' . $cp_id);
            exit;
        } else {
            $_SESSION['error_message'] = 'Erreur lors de l\'enregistrement du prix. Vérifiez que la migration a été exécutée.';
        }
    }
}

$cp = get_commande_personnalisee_by_id($cp_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande personnalisée #<?php echo $cp['id']; ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-personnalisees.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="cp-details-header">
        <h1><i class="fas fa-palette"></i> Demande #<?php echo $cp['id']; ?></h1>
        <div class="header-actions">
            <?php if ($facture_cp): ?>
                <a href="facture.php?id=<?php echo (int) $facture_cp['id']; ?>" class="btn-primary" target="_blank">
                    <i class="fas fa-file-invoice"></i> Voir la facture
                </a>
                <?php
                $tel = preg_replace('/\D/', '', $cp['telephone'] ?? '');
                if (strlen($tel) === 9 && in_array(substr($tel, 0, 2), ['70', '76', '77', '78'])) $tel = '221' . $tel;
                elseif (strlen($tel) === 10 && $tel[0] === '0') $tel = '221' . substr($tel, 1);
                $base = get_site_base_url();
                $token = $facture_cp['token'] ?? '';
                $facture_url = $base . '/facture-cp.php?token=' . $token;
                $client_nom = trim(($cp['prenom'] ?? '') . ' ' . ($cp['nom'] ?? ''));
                $montant_aff = ($facture_cp['montant_total'] ?? 0) > 0 ? number_format($facture_cp['montant_total'], 0, ',', ' ') . ' CFA' : 'À définir';
                $msg_wa = "Bonjour " . $client_nom . ",\n\nVotre devis/facture n°" . ($facture_cp['numero_facture'] ?? '') . " pour la demande #" . $cp['id'] . " est prête.\n\nMontant : " . $montant_aff . "\n\nConsultez : " . $facture_url . "\n\nCordialement, FOUTA POIDS LOURDS";
                $wa_url = !empty($tel) ? 'https://wa.me/' . $tel . '?text=' . urlencode($msg_wa) : '';
                ?>
                <?php if (!empty($wa_url)): ?>
                <a href="<?php echo htmlspecialchars($wa_url); ?>" class="btn-whatsapp-cp" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-whatsapp"></i> Envoyer sur WhatsApp
                </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="generer_facture.php?id=<?php echo $cp_id; ?>" class="btn-primary">
                    <i class="fas fa-file-invoice"></i> Générer une facture
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
        </div>
    <?php endif; ?>

    <div class="cp-details-grid">
        <div class="cp-detail-box">
            <h3><i class="fas fa-user"></i> Client</h3>
            <div class="cp-detail-item">
                <label>Nom complet</label>
                <div class="value"><?php echo htmlspecialchars($cp['prenom'] . ' ' . $cp['nom']); ?></div>
            </div>
            <div class="cp-detail-item">
                <label>Email</label>
                <div class="value"><a href="mailto:<?php echo htmlspecialchars($cp['email']); ?>"><?php echo htmlspecialchars($cp['email']); ?></a></div>
            </div>
            <div class="cp-detail-item">
                <label>Téléphone</label>
                <div class="value"><a href="tel:<?php echo htmlspecialchars($cp['telephone']); ?>"><?php echo htmlspecialchars($cp['telephone']); ?></a></div>
            </div>
            <div class="cp-detail-item">
                <label>Compte client</label>
                <div class="value"><?php echo $cp['user_id'] ? 'Oui (ID: ' . $cp['user_id'] . ')' : 'Visiteur (non inscrit)'; ?></div>
            </div>
        </div>

        <div class="cp-detail-box">
            <h3><i class="fas fa-file-alt"></i> Demande</h3>
            <div class="cp-detail-item">
                <label>Description</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($cp['description'])); ?></div>
            </div>
            <?php if ($cp['type_produit']): ?>
            <div class="cp-detail-item">
                <label>Type de produit</label>
                <div class="value"><?php echo htmlspecialchars($cp['type_produit']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($cp['quantite']): ?>
            <div class="cp-detail-item">
                <label>Quantité souhaitée</label>
                <div class="value"><?php echo htmlspecialchars($cp['quantite']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($cp['date_souhaitee']): ?>
            <div class="cp-detail-item">
                <label>Date souhaitée</label>
                <div class="value"><?php echo date('d/m/Y', strtotime($cp['date_souhaitee'])); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($cp['zone_livraison_id']) && (!empty($cp['zone_ville']) || !empty($cp['zone_quartier']))): ?>
            <?php
            $zone_libelle = trim(($cp['zone_ville'] ?? '') . ' - ' . ($cp['zone_quartier'] ?? ''), ' -');
            ?>
            <div class="cp-detail-item">
                <label>Zone de livraison</label>
                <div class="value">
                    <?php echo htmlspecialchars($zone_libelle); ?>
                    <?php if (isset($cp['zone_prix_livraison']) && (float) $cp['zone_prix_livraison'] > 0): ?>
                        <span class="zone-prix">(<?php echo number_format($cp['zone_prix_livraison'], 0, ',', ' '); ?> FCFA)</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif (!empty($cp['zone_livraison_id'])): ?>
            <div class="cp-detail-item">
                <label>Zone de livraison</label>
                <div class="value">Zone #<?php echo (int) $cp['zone_livraison_id']; ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($cp['image_reference'])): ?>
            <div class="cp-detail-item">
                <label>Image de référence</label>
                <button type="button" class="cp-image-trigger" id="cpImageTrigger">
                    <img src="/upload/<?php echo htmlspecialchars($cp['image_reference'] ?? ''); ?>"
                        alt="Image de référence de la commande personnalisée"
                        onerror="this.src='/image/produit1.jpg'">
                    <span class="cp-image-trigger-caption">
                        <strong>Voir l'image jointe</strong>
                        <small>Cliquez pour l'afficher en grand format</small>
                    </span>
                    <span class="cp-image-zoom-icon"><i class="fas fa-up-right-and-down-left-from-center"></i></span>
                </button>
            </div>
            <?php endif; ?>
            <div class="cp-detail-item">
                <label>Date de demande</label>
                <div class="value"><?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?></div>
            </div>
        </div>
    </div>

    <section class="cp-prix-section">
        <h2><i class="fas fa-tag"></i> Prix de la commande</h2>
        <div class="cp-prix-form-wrap">
            <?php
            $prix_actuel = isset($cp['prix']) && $cp['prix'] !== null && $cp['prix'] !== '' ? (float) $cp['prix'] : null;
            $prix_aff = $prix_actuel !== null ? number_format($prix_actuel, 0, ',', ' ') . ' CFA' : 'Non défini';
            ?>
            <div class="cp-prix-current">
                <label>Prix actuel</label>
                <div class="value"><?php echo htmlspecialchars($prix_aff); ?></div>
            </div>
            <form method="POST" action="" class="cp-prix-form">
                <div class="form-group">
                    <label for="prix">Définir ou modifier le prix (CFA)</label>
                    <input type="text" id="prix" name="prix" placeholder="Ex: 15000" value="<?php echo $prix_actuel !== null ? (int) $prix_actuel : ''; ?>" inputmode="numeric" pattern="[0-9\s,]*">
                </div>
                <div class="cp-prix-buttons">
                    <button type="submit" name="enregistrer_prix" class="cp-btn-submit">
                        <i class="fas fa-save"></i> <?php echo $prix_actuel !== null ? 'Modifier le prix' : 'Enregistrer le prix'; ?>
                    </button>
                </div>
            </form>
            <p class="cp-prix-hint">
                <i class="fas fa-info-circle"></i>
                Le prix défini ici sera utilisé dans la facture. Si une facture existe déjà, elle sera mise à jour automatiquement. Laissez le champ vide et enregistrez pour supprimer le prix.
            </p>
        </div>
    </section>

    <section class="cp-traitement-section">
        <h2><i class="fas fa-cog"></i> Traitement</h2>

        <?php if ($is_annulee || $is_refusee): ?>
            <div class="cp-alert-closed">
                <h3><i class="fas fa-ban"></i> Demande <?php echo $is_annulee ? 'annulée' : 'refusée'; ?></h3>
                <p>Cette demande n'est plus en cours de traitement.</p>
            </div>
        <?php else: ?>
            <div class="cp-statut-form">
                <div class="form-group">
                    <label>Statut actuel</label>
                    <div class="statut-current-wrap">
                        <span class="commande-statut statut-<?php echo $cp['statut']; ?>">
                            <?php echo $statuts_labels[$cp['statut']] ?? $cp['statut']; ?>
                        </span>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="statut">Changer le statut</label>
                        <select id="statut" name="statut" required>
                            <?php foreach ($statuts_labels as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $cp['statut'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes_admin">Notes internes (optionnel)</label>
                        <textarea id="notes_admin" name="notes_admin" rows="4" placeholder="Notes pour le suivi interne..."><?php echo htmlspecialchars($cp['notes_admin'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="changer_statut" class="cp-btn-submit">
                        <i class="fas fa-save"></i> Mettre à jour le statut
                    </button>
                </form>

                <p class="cp-info-hint">
                    <i class="fas fa-info-circle"></i>
                    <strong>Statuts :</strong> En attente → Confirmée → En préparation → Devis envoyé → Acceptée → Terminée.
                    Refusée ou Annulée pour clôturer sans suite.
                </p>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($cp['image_reference'])): ?>
    <div class="cp-image-modal" id="cpImageModal" aria-hidden="true">
        <div class="cp-image-modal-backdrop" data-close-image-modal="1"></div>
        <div class="cp-image-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cpImageModalTitle">
            <button type="button" class="cp-image-modal-close" id="cpImageModalClose" aria-label="Fermer l'image">
                <i class="fas fa-times"></i>
            </button>
            <div class="cp-image-modal-header">
                <h3 id="cpImageModalTitle"><i class="fas fa-image"></i> Image de référence</h3>
                <p>Demande personnalisée #<?php echo (int) $cp['id']; ?></p>
            </div>
            <div class="cp-image-modal-body">
                <img src="/upload/<?php echo htmlspecialchars($cp['image_reference'] ?? ''); ?>"
                    alt="Image de référence de la demande personnalisée"
                    onerror="this.src='/image/produit1.jpg'">
            </div>
        </div>
    </div>
    <script>
        (function () {
            var modal = document.getElementById('cpImageModal');
            var trigger = document.getElementById('cpImageTrigger');
            var closeButton = document.getElementById('cpImageModalClose');
            var closeBackdrop = modal ? modal.querySelector('[data-close-image-modal="1"]') : null;

            if (!modal || !trigger) {
                return;
            }

            function openImageModal() {
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeImageModal() {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            trigger.addEventListener('click', openImageModal);

            if (closeButton) {
                closeButton.addEventListener('click', closeImageModal);
            }

            if (closeBackdrop) {
                closeBackdrop.addEventListener('click', closeImageModal);
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('show')) {
                    closeImageModal();
                }
            });
        })();
    </script>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
