<?php
/**
 * Page de gestion des contacts (Admin)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../models/model_contacts.php';

$recherche = trim($_GET['recherche'] ?? '');

$success_message = isset($_SESSION['contacts_success']) ? $_SESSION['contacts_success'] : '';
$error_message = isset($_SESSION['contacts_error']) ? $_SESSION['contacts_error'] : '';
unset($_SESSION['contacts_success'], $_SESSION['contacts_error']);

// Traitement ajout contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;

    if (empty($nom) || empty($telephone)) {
        $error_message = 'Le nom et le téléphone sont obligatoires.';
    } elseif (create_contact($nom, $prenom, $telephone, $email)) {
        $_SESSION['contacts_success'] = 'Contact ajouté avec succès.';
        header('Location: index.php' . ($recherche ? '?recherche=' . urlencode($recherche) : ''));
        exit;
    } else {
        $error_message = 'Erreur lors de l\'ajout du contact.';
    }
}

// Traitement modification contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    $id = (int) ($_POST['contact_id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;

    if ($id <= 0 || empty($nom) || empty($telephone)) {
        $error_message = 'Données invalides.';
    } elseif (update_contact($id, $nom, $prenom, $telephone, $email)) {
        $_SESSION['contacts_success'] = 'Contact modifié avec succès.';
        header('Location: index.php' . ($recherche ? '?recherche=' . urlencode($recherche) : ''));
        exit;
    } else {
        $error_message = 'Erreur lors de la modification.';
    }
}

// Traitement import (JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_contacts'])) {
    $json = $_POST['import_contacts_data'] ?? '';
    $imported = 0;
    $data = json_decode($json, true);
    if (is_array($data)) {
        foreach ($data as $c) {
            $nom = trim($c['nom'] ?? $c['name'] ?? '');
            $prenom = trim($c['prenom'] ?? $c['prenom'] ?? '');
            $tel = trim($c['telephone'] ?? $c['tel'] ?? $c['phone'] ?? '');
            $email = trim($c['email'] ?? '') ?: null;
            if (!empty($tel) && !get_contact_by_telephone($tel)) {
                if (empty($nom)) $nom = $prenom ?: 'Sans nom';
                if (create_contact($nom, $prenom, $tel, $email)) $imported++;
            }
        }
        $_SESSION['contacts_success'] = $imported . ' contact(s) importé(s).';
        header('Location: index.php' . ($recherche ? '?recherche=' . urlencode($recherche) : ''));
        exit;
    } else {
        $error_message = 'Aucun contact à importer.';
    }
}

$contacts = get_all_contacts($recherche);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .contacts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .contact-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 12px; padding: 16px; }
        .contact-card-nom { font-weight: 600; color: var(--titres); margin-bottom: 4px; }
        .contact-card-tel { font-size: 14px; color: #555; }
        .contact-card-email { font-size: 12px; color: #888; margin-top: 4px; }
        .modal-actions { display: flex; gap: 12px; margin-top: 20px; }
        .modal-fullscreen { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
        .modal-fullscreen.show { display: flex; }
        .modal-fullscreen-content { background: #fff; border-radius: 12px; max-width: 500px; width: 100%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-fullscreen-header { padding: 20px 24px; border-bottom: 1px solid #ececec; display: flex; align-items: center; justify-content: space-between; }
        .modal-fullscreen-header h2 { margin: 0; font-size: 20px; }
        .modal-fullscreen-body { padding: 24px; }
        .modal-close-btn { width: 36px; height: 36px; border: none; background: #f5f5f5; border-radius: 8px; cursor: pointer; font-size: 18px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px 14px; border: 1px solid #d9d9d9; border-radius: 8px; }
        .admin-filters-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; padding: 16px; background: #fff; border: 1px solid #ececec; border-radius: 12px; margin-bottom: 20px; }
        .admin-filter-field { flex: 1 1 220px; }
        .admin-filter-field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .admin-filter-field input { width: 100%; padding: 11px 14px; border: 1px solid #d9d9d9; border-radius: 10px; }
        .contact-card-actions { margin-top: 12px; }
        .btn-modifier-contact { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; background: var(--boutons-secondaires, #20C5C7); color: #fff; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; }
        .btn-modifier-contact:hover { opacity: 0.9; color: #fff; }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-address-book"></i> Contacts</h1>
        <div class="header-actions">
            <a href="../users/index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
            <button type="button" class="btn-primary" id="btn-import-contacts">
                <i class="fas fa-mobile-alt"></i> Importer depuis le répertoire
            </button>
            <button type="button" class="btn-primary" id="btn-add-contact">
                <i class="fas fa-plus"></i> Ajouter un contact
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <section class="produits-section">
        <form method="GET" class="admin-filters-bar" style="margin-bottom:20px;">
            <div class="admin-filter-field">
                <label>Rechercher</label>
                <input type="text" name="recherche" placeholder="Nom, téléphone, email..." value="<?php echo htmlspecialchars($recherche); ?>">
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filtrer</button>
        </form>

        <div class="section-title">
            <h2><i class="fas fa-list"></i> Liste des contacts (<?php echo count($contacts); ?>)</h2>
        </div>

        <?php if (empty($contacts)): ?>
            <div class="empty-state">
                <i class="fas fa-address-book"></i>
                <h3>Aucun contact</h3>
                <p>Ajoutez des contacts manuellement ou importez-les depuis votre répertoire téléphonique.</p>
            </div>
        <?php else: ?>
            <div class="contacts-grid">
                <?php foreach ($contacts as $c): ?>
                    <div class="contact-card">
                        <div class="contact-card-nom"><?php echo htmlspecialchars(trim($c['prenom'] . ' ' . $c['nom'])); ?></div>
                        <div class="contact-card-tel"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($c['telephone']); ?></div>
                        <?php if (!empty($c['email'])): ?>
                            <div class="contact-card-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($c['email']); ?></div>
                        <?php endif; ?>
                        <div class="contact-card-actions">
                            <button type="button" class="btn-modifier-contact btn-edit-contact" data-id="<?php echo (int) $c['id']; ?>"
                                data-nom="<?php echo htmlspecialchars($c['nom']); ?>"
                                data-prenom="<?php echo htmlspecialchars($c['prenom'] ?? ''); ?>"
                                data-telephone="<?php echo htmlspecialchars($c['telephone']); ?>"
                                data-email="<?php echo htmlspecialchars($c['email'] ?? ''); ?>">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Modal Ajouter contact -->
    <div class="modal-fullscreen" id="modal-add-contact">
        <div class="modal-fullscreen-content" style="max-width:480px;">
            <div class="modal-fullscreen-header">
                <h2><i class="fas fa-user-plus"></i> Ajouter un contact</h2>
                <button type="button" class="modal-close-btn" id="modal-add-close">&times;</button>
            </div>
            <div class="modal-fullscreen-body">
                <form method="POST">
                    <input type="hidden" name="add_contact" value="1">
                    <div class="form-group">
                        <label>Nom <span style="color:var(--accent-promo);">*</span></label>
                        <input type="text" name="nom" required placeholder="Nom de famille">
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" placeholder="Prénom">
                    </div>
                    <div class="form-group">
                        <label>Téléphone <span style="color:var(--accent-promo);">*</span></label>
                        <input type="tel" name="telephone" required placeholder="Ex: 77 12 34 56 78">
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color:#888; font-weight:400;">(optionnel)</span></label>
                        <input type="email" name="email" placeholder="email@exemple.com">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        <button type="button" class="btn-cancel" id="modal-add-cancel">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modifier contact -->
    <div class="modal-fullscreen" id="modal-edit-contact">
        <div class="modal-fullscreen-content" style="max-width:480px;">
            <div class="modal-fullscreen-header">
                <h2><i class="fas fa-user-edit"></i> Modifier le contact</h2>
                <button type="button" class="modal-close-btn" id="modal-edit-close">&times;</button>
            </div>
            <div class="modal-fullscreen-body">
                <form method="POST" id="form-edit-contact">
                    <input type="hidden" name="update_contact" value="1">
                    <input type="hidden" name="contact_id" id="edit_contact_id" value="">
                    <div class="form-group">
                        <label>Nom <span style="color:var(--accent-promo);">*</span></label>
                        <input type="text" name="nom" id="edit_nom" required placeholder="Nom de famille">
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" id="edit_prenom" placeholder="Prénom">
                    </div>
                    <div class="form-group">
                        <label>Téléphone <span style="color:var(--accent-promo);">*</span></label>
                        <input type="tel" name="telephone" id="edit_telephone" required placeholder="Ex: 77 12 34 56 78">
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color:#888; font-weight:400;">(optionnel)</span></label>
                        <input type="email" name="email" id="edit_email" placeholder="email@exemple.com">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        <button type="button" class="btn-cancel" id="modal-edit-cancel">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulaire caché pour import -->
    <form method="POST" id="form-import" style="display:none;">
        <input type="hidden" name="import_contacts" value="1">
        <input type="hidden" name="import_contacts_data" id="import_contacts_data">
    </form>

    <?php include '../includes/footer.php'; ?>
    <script>
    (function() {
        var modal = document.getElementById('modal-add-contact');
        var btnAdd = document.getElementById('btn-add-contact');
        var btnClose = document.getElementById('modal-add-close');
        var btnCancel = document.getElementById('modal-add-cancel');

        function openModal() { if (modal) modal.classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeModal() { if (modal) modal.classList.remove('show'); document.body.style.overflow = ''; }

        if (btnAdd) btnAdd.addEventListener('click', openModal);
        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);
        if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

        var modalEdit = document.getElementById('modal-edit-contact');
        var btnEditClose = document.getElementById('modal-edit-close');
        var btnEditCancel = document.getElementById('modal-edit-cancel');
        function openModalEdit(id, nom, prenom, telephone, email) {
            document.getElementById('edit_contact_id').value = id;
            document.getElementById('edit_nom').value = nom || '';
            document.getElementById('edit_prenom').value = prenom || '';
            document.getElementById('edit_telephone').value = telephone || '';
            document.getElementById('edit_email').value = email || '';
            if (modalEdit) modalEdit.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeModalEdit() {
            if (modalEdit) modalEdit.classList.remove('show');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.btn-edit-contact').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openModalEdit(btn.dataset.id, btn.dataset.nom, btn.dataset.prenom, btn.dataset.telephone, btn.dataset.email);
            });
        });
        if (btnEditClose) btnEditClose.addEventListener('click', closeModalEdit);
        if (btnEditCancel) btnEditCancel.addEventListener('click', closeModalEdit);
        if (modalEdit) modalEdit.addEventListener('click', function(e) { if (e.target === modalEdit) closeModalEdit(); });

        // Import depuis répertoire (Contact Picker API)
        var btnImport = document.getElementById('btn-import-contacts');
        if (btnImport && 'contacts' in navigator && 'ContactsManager' in window) {
            btnImport.addEventListener('click', function() {
                navigator.contacts.select(['name', 'tel', 'email'], { multiple: true }).then(function(contacts) {
                    var data = [];
                    contacts.forEach(function(c) {
                        var nom = (c.name && c.name[0]) ? c.name[0].split(' ').pop() || '' : '';
                        var prenom = (c.name && c.name[0]) ? c.name[0].split(' ').slice(0, -1).join(' ') || '' : '';
                        var tel = (c.tel && c.tel[0]) ? c.tel[0] : '';
                        var email = (c.email && c.email[0]) ? c.email[0] : '';
                        if (tel) data.push({ nom: nom, prenom: prenom, telephone: tel, email: email });
                    });
                    if (data.length > 0) {
                        document.getElementById('import_contacts_data').value = JSON.stringify(data);
                        document.getElementById('form-import').submit();
                    } else {
                        alert('Aucun contact avec numéro de téléphone trouvé.');
                    }
                }).catch(function(err) {
                    alert('Impossible d\'accéder aux contacts. Vérifiez les permissions du navigateur.');
                });
            });
        } else {
            btnImport.style.display = 'none';
        }
    })();
    </script>
</body>
</html>
