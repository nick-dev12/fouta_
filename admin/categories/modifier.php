<?php
/**
 * Page de modification de catégorie
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

// Récupérer l'ID de la catégorie
$categorie_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($categorie_id <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer la catégorie
require_once __DIR__ . '/../../models/model_categories.php';
$categorie = get_categorie_by_id($categorie_id);

if (!$categorie) {
    header('Location: index.php');
    exit;
}

// Traiter le formulaire
require_once __DIR__ . '/../../controllers/controller_categories.php';
$result = process_update_categorie($categorie_id);

// Si la modification est réussie, rediriger vers la liste
if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une Catégorie - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .form-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: #6b2f20;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #000000;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #918a44;
            box-shadow: 0 0 0 3px rgba(145, 138, 68, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .error-message {
            background: #fee;
            border-left: 4px solid #c26638;
            color: #6b2f20;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .btn-back {
            background: #e0e0e0;
            color: #6b2f20;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #d0d0d0;
        }

        .current-image {
            margin-top: 10px;
            max-width: 200px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header">
        <h1><i class="fas fa-edit"></i> Modifier une Catégorie</h1>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    <div class="form-container">
        <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nom">Nom de la catégorie *</label>
                <input type="text" id="nom" name="nom" required
                       value="<?php echo htmlspecialchars($categorie['nom']); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($categorie['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="image">Image de la catégorie</label>
                <?php if ($categorie['image']): ?>
                    <div>
                        <img src="../../upload/<?php echo htmlspecialchars($categorie['image']); ?>" 
                             alt="Image actuelle" class="current-image">
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">Image actuelle (laisser vide pour conserver)</p>
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/*">
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Formats acceptés: JPG, PNG, GIF, WEBP (max 5MB)</small>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>

