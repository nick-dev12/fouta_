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
require_once __DIR__ . '/../../includes/admin_permissions.php';

$contacts_compta_readonly = (admin_current_role() === 'comptabilite');

if ($contacts_compta_readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['contacts_error'] = 'Les modifications du carnet contacts ne sont pas autorisées pour le profil comptabilité.';
    header('Location: index.php' . (!empty($_GET['recherche']) ? '?recherche=' . urlencode((string) $_GET['recherche']) : ''));
    exit;
}

require_once __DIR__ . '/../../models/model_contacts.php';
require_once __DIR__ . '/../../models/model_clients_b2b.php';

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
    $type_bl = ($_POST['type_client_bl'] ?? '') === 'vip' ? 'vip' : 'standard';

    if (empty($nom) || empty($telephone)) {
        $error_message = 'Le nom et le téléphone sont obligatoires.';
    } elseif (create_contact($nom, $prenom, $telephone, $email, $type_bl)) {
        update_client_b2b_type_client_bl_by_telephone($telephone, $type_bl);
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
    $type_bl = ($_POST['type_client_bl'] ?? '') === 'vip' ? 'vip' : 'standard';

    if ($id <= 0 || empty($nom) || empty($telephone)) {
        $error_message = 'Données invalides.';
    } elseif (update_contact($id, $nom, $prenom, $telephone, $email, $type_bl)) {
        update_client_b2b_type_client_bl_by_telephone($telephone, $type_bl);
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

$contacts = contacts_list_with_compta_stats(get_all_contacts($recherche));
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
        .contacts-admin-table-wrap { overflow-x: auto; border-radius: 14px; border: 1px solid var(--glass-border); background: var(--glass-bg); box-shadow: var(--glass-shadow); margin-top: 8px; }
        .contacts-admin-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.88rem; }
        .contacts-admin-table thead th { padding: 12px 10px; text-align: left; background: linear-gradient(165deg, var(--bleu-principal) 0%, var(--bleu-fonce) 100%); color: var(--texte-clair); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .contacts-admin-table tbody td { padding: 11px 10px; border-bottom: 1px solid rgba(53, 100, 166, 0.08); vertical-align: middle; }
        .contacts-admin-table tbody tr:nth-child(even) td { background: rgba(53, 100, 166, 0.03); }
        .contacts-admin-table tbody tr:hover td { background: rgba(53, 100, 166, 0.07); }
        .contacts-admin-table .cnt-num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
        .contacts-admin-table thead th.cnt-num--payee {
            background: linear-gradient(165deg, #15803d 0%, #166534 100%);
            color: #fff;
        }
        .contacts-admin-table thead th.cnt-num--due {
            background: linear-gradient(165deg, var(--orange-fonce) 0%, var(--orange) 100%);
            color: #fff;
        }
        .contacts-admin-table tbody tr:nth-child(even) td.cnt-num--payee { background: rgba(22, 163, 74, 0.1); color: #14532d; }
        .contacts-admin-table tbody tr:nth-child(odd) td.cnt-num--payee { background: rgba(22, 163, 74, 0.06); color: #14532d; }
        .contacts-admin-table tbody tr:nth-child(even) td.cnt-num--due { background: rgba(255, 107, 53, 0.12); color: #9a3412; }
        .contacts-admin-table tbody tr:nth-child(odd) td.cnt-num--due { background: rgba(255, 107, 53, 0.07); color: #9a3412; }
        .contacts-admin-table tbody tr:hover td.cnt-num--payee { background: rgba(22, 163, 74, 0.2); }
        .contacts-admin-table tbody tr:hover td.cnt-num--due { background: rgba(255, 107, 53, 0.22); }
        .contacts-admin-table .cnt-actions { white-space: nowrap; }
        .contacts-admin-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; justify-content: flex-end; }
        .contacts-admin-actions a { display: inline-flex; align-items: center; gap: 4px; padding: 5px 8px; border-radius: 8px; font-size: 0.75rem; font-weight: 600; text-decoration: none; border: 1px solid var(--border-input); background: var(--blanc); color: var(--couleur-dominante); }
        .contacts-admin-actions a:hover { background: var(--bleu-pale); }
        .contacts-admin-actions a.cnt-act-primary { background: var(--couleur-dominante); color: #fff; border-color: var(--couleur-dominante); }
        .contacts-admin-actions a.cnt-act-primary:hover { filter: brightness(1.05); color: #fff; }
        .contacts-admin-actions a.is-disabled { opacity: 0.45; pointer-events: none; cursor: not-allowed; }
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
        .form-group input,
        .form-group select { width: 100%; padding: 12px 14px; border: 1px solid #d9d9d9; border-radius: 8px; }
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
            <?php if ($contacts_compta_readonly): ?>
                <a href="../comptabilite/index.php" class="btn-back"><i class="fas fa-calculator"></i> Comptabilité</a>
            <?php else: ?>
                <a href="../users/index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
                <button type="button" class="btn-primary" id="btn-import-contacts">
                    <i class="fas fa-mobile-alt"></i> Importer depuis le répertoire
                </button>
                <button type="button" class="btn-primary" id="btn-add-contact">
                    <i class="fas fa-plus"></i> Ajouter un contact
                </button>
            <?php endif; ?>
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
            <div class="contacts-admin-table-wrap">
                <table class="contacts-admin-table" id="contacts-admin-list">
                    <thead>
                        <tr>
                            <th scope="col">Contact</th>
                            <th scope="col">Téléphone</th>
                            <th scope="col">Email</th>
                            <th scope="col" class="cnt-num cnt-num--payee">Fact. devis payées</th>
                            <th scope="col" class="cnt-num cnt-num--due">Fact. devis impayées</th>
                            <th scope="col" class="cnt-num cnt-num--payee">Fact. BL payées</th>
                            <th scope="col" class="cnt-num cnt-num--due">Fact. BL non payées</th>
                            <th scope="col" class="cnt-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                <?php foreach ($contacts as $c):
                    $co = $c['_compta'] ?? [];
                    $cid = (int) ($c['id'] ?? 0);
                    $b2b_id = (int) ($co['b2b_id'] ?? 0);
                    $href_bl = $b2b_id > 0
                        ? '../comptabilite/bl-factures-archives.php?client=' . $b2b_id
                        : '../comptabilite/bl-factures-archives.php';
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? ''))); ?></strong></td>
                            <td><?php echo htmlspecialchars($c['telephone'] ?? '—'); ?></td>
                            <td><?php echo !empty($c['email']) ? htmlspecialchars((string) $c['email']) : '—'; ?></td>
                            <td class="cnt-num cnt-num--payee"><?php echo (int) ($co['fd_payees'] ?? 0); ?></td>
                            <td class="cnt-num cnt-num--due"><?php echo (int) ($co['fd_impayees'] ?? 0); ?></td>
                            <td class="cnt-num cnt-num--payee"><?php echo (int) ($co['fm_payees'] ?? 0); ?></td>
                            <td class="cnt-num cnt-num--due"><?php echo (int) ($co['fm_impayees'] ?? 0); ?></td>
                            <td class="cnt-actions">
                                <div class="contacts-admin-actions">
                                    <a href="<?php echo htmlspecialchars($href_bl); ?>" class="cnt-act-primary" title="Archives factures mensuelles BL"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> BL</a>
                                    <a href="../comptabilite/index.php?tab=devis_payes" title="Toutes les factures devis payées (compta)"><i class="fas fa-check-circle" aria-hidden="true"></i> Devis payés</a>
                                    <?php if (!$contacts_compta_readonly): ?>
                                    <button type="button" class="btn-modifier-contact btn-edit-contact" style="margin:0;" data-id="<?php echo $cid; ?>"
                                        data-nom="<?php echo htmlspecialchars($c['nom']); ?>"
                                        data-prenom="<?php echo htmlspecialchars($c['prenom'] ?? ''); ?>"
                                        data-telephone="<?php echo htmlspecialchars($c['telephone']); ?>"
                                        data-email="<?php echo htmlspecialchars($c['email'] ?? ''); ?>"
                                        data-type-bl="<?php echo htmlspecialchars(($c['type_client_bl'] ?? 'standard')); ?>">
                                        <i class="fas fa-edit"></i> Modifier
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                <?php endforeach; ?>
                    </tbody>
                </table>
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
                    <div class="form-group">
                        <label>Type client (plafonds BL)</label>
                        <select name="type_client_bl">
                            <option value="standard">Standard</option>
                            <option value="vip">VIP</option>
                        </select>
                        <p class="form-hint" style="margin-top:8px;font-size:12px;color:#666;"><i class="fas fa-info-circle"></i> Utilisé pour les plafonds cumulés des bons de livraison.</p>
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
                    <div class="form-group">
                        <label>Type client (plafonds BL)</label>
                        <select name="type_client_bl" id="edit_type_client_bl">
                            <option value="standard">Standard</option>
                            <option value="vip">VIP</option>
                        </select>
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
        function openModalEdit(id, nom, prenom, telephone, email, typeBl) {
            document.getElementById('edit_contact_id').value = id;
            document.getElementById('edit_nom').value = nom || '';
            document.getElementById('edit_prenom').value = prenom || '';
            document.getElementById('edit_telephone').value = telephone || '';
            document.getElementById('edit_email').value = email || '';
            var sel = document.getElementById('edit_type_client_bl');
            if (sel) sel.value = (typeBl === 'vip') ? 'vip' : 'standard';
            if (modalEdit) modalEdit.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeModalEdit() {
            if (modalEdit) modalEdit.classList.remove('show');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.btn-edit-contact').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openModalEdit(btn.dataset.id, btn.dataset.nom, btn.dataset.prenom, btn.dataset.telephone, btn.dataset.email, btn.dataset.typeBl);
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
            if (btnImport) btnImport.style.display = 'none';
        }
    })();
    </script>
</body>
</html>
