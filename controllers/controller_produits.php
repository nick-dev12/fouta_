<?php
/**
 * Contrôleur pour la gestion des produits
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/barcode_fpl.php';

/**
 * Génère et sauvegarde le QR code d'un produit (pointant vers stock-info.php)
 * @param int $produit_id ID du produit
 * @return bool True si succès
 */
function generer_qrcode_produit($produit_id) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../includes/site_url.php';
    $base = get_site_base_url();
    $url = $base . '/stock-info.php?id=' . (int) $produit_id;
    $dir = __DIR__ . '/../upload/qrcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'produit_' . (int) $produit_id . '.png';
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale'        => 10,
            'outputBase64' => false,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $qr->render($url, $file);
        return file_exists($file);
    } catch (Throwable $e) {
        return false;
    }
}
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../models/model_variantes.php';
require_once __DIR__ . '/../models/model_mouvements_stock.php';

/**
 * Upload une image de produit
 * @param array $file Le fichier $_FILES
 * @param string $field_name Le nom du champ
 * @return string|false Le nom du fichier ou False en cas d'erreur
 */
function upload_produit_image($file, $field_name = 'image') {
    if (!isset($file[$field_name]) || $file[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../upload/produits/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    $file_info = $file[$field_name];
    
    // Vérifier le type
    if (!in_array($file_info['type'], $allowed_types)) {
        return false;
    }
    
    // Générer un nom unique
    $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
    $filename = uniqid('produit_', true) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file_info['tmp_name'], $filepath)) {
        return 'produits/' . $filename;
    }
    
    return false;
}

/**
 * Upload plusieurs images supplémentaires
 * @param array $files $_FILES avec name en tableau (ex: images_supplementaires[])
 * @param string $field_name Le nom du champ (ex: images_supplementaires)
 * @return array Tableau des chemins des images uploadées
 */
function upload_produit_images_multiples($files, $field_name = 'images_supplementaires') {
    $uploaded = [];
    if (!isset($files[$field_name]) || !is_array($files[$field_name]['name'])) {
        return $uploaded;
    }
    
    $count = count($files[$field_name]['name']);
    for ($i = 0; $i < $count; $i++) {
        $file = [
            'name' => $files[$field_name]['name'][$i],
            'type' => $files[$field_name]['type'][$i],
            'tmp_name' => $files[$field_name]['tmp_name'][$i],
            'error' => $files[$field_name]['error'][$i],
            'size' => $files[$field_name]['size'][$i]
        ];
        $fake_files = [$field_name => $file];
        $path = upload_produit_image($fake_files, $field_name);
        if ($path) {
            $uploaded[] = $path;
        }
    }
    return $uploaded;
}

/**
 * Traite l'ajout d'un nouveau produit
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_produit() {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération et validation des données (stock géré via produits.stock)
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $prix = isset($_POST['prix']) ? trim($_POST['prix']) : '';
    $prix_promotion = isset($_POST['prix_promotion']) && !empty($_POST['prix_promotion']) ? trim($_POST['prix_promotion']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : 'unité';
    $couleurs = null;
    if (isset($_POST['couleurs']) && trim($_POST['couleurs']) !== '') {
        $raw = trim($_POST['couleurs']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $valid = array_values(array_unique(array_filter($decoded, function($c) {
                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
            })));
            $couleurs = !empty($valid) ? json_encode($valid) : null;
        } else {
            $couleurs = $raw;
        }
    }
    $poids = null;
    $taille = null;
    if (isset($_POST['poids']) && trim($_POST['poids']) !== '') {
        $raw = trim($_POST['poids']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $poids = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$poids && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $poids = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    if (isset($_POST['taille']) && trim($_POST['taille']) !== '') {
        $raw = trim($_POST['taille']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $taille = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$taille && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $taille = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    
    // Validation
    if (empty($nom)) {
        $errors[] = 'Le nom du produit est obligatoire.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }
    
    if (empty($prix) || !is_numeric($prix) || $prix <= 0) {
        $errors[] = 'Le prix doit être un nombre positif.';
    }
    
    if ($prix_promotion !== null && (!is_numeric($prix_promotion) || $prix_promotion <= 0 || $prix_promotion >= $prix)) {
        $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }
    
    if ($categorie_id <= 0) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }
    
    // Vérifier que la catégorie existe
    if ($categorie_id > 0 && !get_categorie_by_id($categorie_id)) {
        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
    }
    
    // Upload des images : images_produit[] (1ère = principale, reste = galerie)
    // Si lié à un article en stock, on utilise son image si pas d'upload
    $image_principale = null;
    $images_supp = [];
    if (isset($_FILES['images_produit']) && is_array($_FILES['images_produit']['name'])) {
        $uploaded = upload_produit_images_multiples($_FILES, 'images_produit');
        if (!empty($uploaded)) {
            $image_principale = $uploaded[0];
            $images_supp = array_slice($uploaded, 1);
        }
    }
    if (!$image_principale) {
        $errors[] = 'Au moins une image est obligatoire.';
    }
    
    // Construire le tableau images (principale + supplémentaires) en JSON
    $images_json = null;
    if ($image_principale) {
        $all_images = array_merge([$image_principale], $images_supp);
        $images_json = json_encode($all_images);
    }
    
    // Si aucune erreur, créer le produit
    if (empty($errors)) {
        $etage = isset($_POST['etage']) ? trim($_POST['etage']) : '';
        $numero_rayon = isset($_POST['numero_rayon']) ? trim($_POST['numero_rayon']) : '';
        $data = [
            'nom' => $nom,
            'description' => $description,
            'prix' => $prix,
            'prix_promotion' => $prix_promotion,
            'stock' => $stock,
            'categorie_id' => $categorie_id,
            'image_principale' => $image_principale,
            'images' => $images_json,
            'poids' => $poids,
            'unite' => $unite,
            'couleurs' => $couleurs,
            'taille' => $taille,
            'statut' => $stock > 0 ? $statut : 'rupture_stock',
            'etage' => $etage !== '' ? $etage : null,
            'numero_rayon' => $numero_rayon !== '' ? $numero_rayon : null
        ];
        
        $produit_id = create_produit($data);
        
        if ($produit_id) {
            $success = true;
            $message = 'Produit ajouté avec succès !';
            // Générer et sauvegarder le QR code du produit
            generer_qrcode_produit($produit_id);
            generer_barcode_produit_fpl($produit_id);
            // Créer les variantes
            $variantes_nom = isset($_POST['variantes_nom']) && is_array($_POST['variantes_nom']) ? array_values($_POST['variantes_nom']) : [];
            $variantes_prix = isset($_POST['variantes_prix']) && is_array($_POST['variantes_prix']) ? array_values($_POST['variantes_prix']) : [];
            $variantes_prix_promo = isset($_POST['variantes_prix_promo']) && is_array($_POST['variantes_prix_promo']) ? array_values($_POST['variantes_prix_promo']) : [];
            $variantes_files = (isset($_FILES['variantes_image']) && is_array($_FILES['variantes_image']['name'])) ? $_FILES['variantes_image'] : null;
            $nb_variantes = count($variantes_nom);
            for ($i = 0; $i < $nb_variantes; $i++) {
                $vn = trim($variantes_nom[$i] ?? '');
                $vp = isset($variantes_prix[$i]) && is_numeric($variantes_prix[$i]) ? (float)$variantes_prix[$i] : 0;
                if ($vn !== '' && $vp > 0) {
                    $vimg = null;
                    if ($variantes_files && isset($variantes_files['name'][$i]) && (int)($variantes_files['error'][$i] ?? 4) === UPLOAD_ERR_OK) {
                        $f = [
                            'name' => $variantes_files['name'][$i],
                            'type' => $variantes_files['type'][$i] ?? '',
                            'tmp_name' => $variantes_files['tmp_name'][$i] ?? '',
                            'error' => $variantes_files['error'][$i] ?? 4,
                            'size' => $variantes_files['size'][$i] ?? 0
                        ];
                        $fake = ['image' => $f];
                        $vimg = upload_produit_image($fake, 'image');
                    }
                    $vpromo = isset($variantes_prix_promo[$i]) && is_numeric($variantes_prix_promo[$i]) && (float)$variantes_prix_promo[$i] > 0 ? (float)$variantes_prix_promo[$i] : null;
                    if ($vpromo !== null && $vpromo >= $vp) $vpromo = null;
                    create_variante([
                        'produit_id' => $produit_id,
                        'nom' => $vn,
                        'prix' => $vp,
                        'prix_promotion' => $vpromo,
                        'image' => $vimg ?: null,
                        'ordre' => $i
                    ]);
                }
            }
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'ajout du produit.';
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la modification d'un produit
 * @param int $produit_id L'ID du produit à modifier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_produit($produit_id) {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Vérifier que le produit existe
    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }
    
    // Récupération et validation des données (stock géré via produits.stock)
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $prix = isset($_POST['prix']) ? trim($_POST['prix']) : '';
    $prix_promotion = isset($_POST['prix_promotion']) && !empty($_POST['prix_promotion']) ? trim($_POST['prix_promotion']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : 'unité';
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    $couleurs = null;
    if (isset($_POST['couleurs']) && trim($_POST['couleurs']) !== '') {
        $raw = trim($_POST['couleurs']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $valid = array_values(array_unique(array_filter($decoded, function($c) {
                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
            })));
            $couleurs = !empty($valid) ? json_encode($valid) : null;
        } else {
            $couleurs = $raw;
        }
    }
    $poids = null;
    $taille = null;
    if (isset($_POST['poids']) && trim($_POST['poids']) !== '') {
        $raw = trim($_POST['poids']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $poids = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$poids && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $poids = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    if (isset($_POST['taille']) && trim($_POST['taille']) !== '') {
        $raw = trim($_POST['taille']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $taille = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$taille && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $taille = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    
    // Validation (identique à l'ajout)
    if (empty($nom)) {
        $errors[] = 'Le nom du produit est obligatoire.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }
    
    if (empty($prix) || !is_numeric($prix) || $prix <= 0) {
        $errors[] = 'Le prix doit être un nombre positif.';
    }
    
    if ($prix_promotion !== null && (!is_numeric($prix_promotion) || $prix_promotion <= 0 || $prix_promotion >= $prix)) {
        $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }
    
    if ($categorie_id <= 0) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }
    
    // Récupérer les images existantes
    $all_images = [];
    if (!empty($produit['images'])) {
        $decoded = json_decode($produit['images'], true);
        if (is_array($decoded)) {
            $all_images = $decoded;
        }
    }
    if (empty($all_images) && !empty($produit['image_principale'])) {
        $all_images = [$produit['image_principale']];
    }
    
    // Images à conserver (envoyées par le formulaire - celles non supprimées par l'utilisateur)
    $images_to_keep = [];
    if (isset($_POST['images_to_keep']) && is_array($_POST['images_to_keep'])) {
        $images_to_keep = array_values(array_filter(array_map('trim', $_POST['images_to_keep'])));
    }
    
    // Upload des images supplémentaires (nouvelles)
    $images_supp = [];
    if (isset($_FILES['images_supplementaires']) && is_array($_FILES['images_supplementaires']['name'])) {
        $images_supp = upload_produit_images_multiples($_FILES, 'images_supplementaires');
    }
    
    // Construire le tableau final : images conservées + nouvelles
    $final_images = array_merge($images_to_keep, $images_supp);
    $final_images = array_values(array_unique($final_images));
    
    // Validation : au moins une image obligatoire
    if (empty($final_images)) {
        $errors[] = 'Au moins une image est obligatoire. Veuillez conserver ou ajouter au moins une image.';
    }
    
    $image_principale = !empty($final_images) ? $final_images[0] : $produit['image_principale'];
    $images_json = !empty($final_images) ? json_encode($final_images) : null;
    $removed_images = array_diff($all_images, $images_to_keep);
    
    // Si aucune erreur, mettre à jour le produit
    if (empty($errors)) {
        $etage = isset($_POST['etage']) ? trim($_POST['etage']) : '';
        $numero_rayon = isset($_POST['numero_rayon']) ? trim($_POST['numero_rayon']) : '';
        $data = [
            'nom' => $nom,
            'description' => $description,
            'prix' => $prix,
            'prix_promotion' => $prix_promotion,
            'stock' => $stock,
            'categorie_id' => $categorie_id,
            'image_principale' => $image_principale,
            'images' => $images_json,
            'poids' => $poids,
            'unite' => $unite,
            'couleurs' => $couleurs,
            'taille' => $taille,
            'statut' => $stock > 0 ? $statut : 'rupture_stock',
            'stock_article_id' => null,
            'etage' => $etage !== '' ? $etage : null,
            'numero_rayon' => $numero_rayon !== '' ? $numero_rayon : null
        ];

        if (update_produit($produit_id, $data)) {
            $success = true;
            $message = 'Produit modifié avec succès !';
            // Supprimer du disque les images retirées par l'utilisateur
            foreach ($removed_images as $old_path) {
                $full_path = __DIR__ . '/../upload/' . $old_path;
                if ($old_path && file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
            // Gestion des variantes
            $variantes_nom = isset($_POST['variantes_nom']) && is_array($_POST['variantes_nom']) ? $_POST['variantes_nom'] : [];
            $variantes_prix = isset($_POST['variantes_prix']) && is_array($_POST['variantes_prix']) ? $_POST['variantes_prix'] : [];
            $variantes_prix_promo = isset($_POST['variantes_prix_promo']) && is_array($_POST['variantes_prix_promo']) ? $_POST['variantes_prix_promo'] : [];
            $variantes_id = isset($_POST['variantes_id']) && is_array($_POST['variantes_id']) ? $_POST['variantes_id'] : [];
            $existing_ids = [];
            foreach (get_variantes_by_produit($produit_id) as $v) {
                $existing_ids[] = (int) $v['id'];
            }
            $kept_ids = [];
            for ($i = 0; $i < count($variantes_nom); $i++) {
                $vn = isset($variantes_nom[$i]) ? trim($variantes_nom[$i]) : '';
                $vp = isset($variantes_prix[$i]) && is_numeric($variantes_prix[$i]) ? (float) $variantes_prix[$i] : 0;
                if (empty($vn) || $vp <= 0) continue;
                $vid = isset($variantes_id[$i]) ? (int) $variantes_id[$i] : 0;
                $vpromo = isset($variantes_prix_promo[$i]) && is_numeric($variantes_prix_promo[$i]) && $variantes_prix_promo[$i] > 0 ? (float) $variantes_prix_promo[$i] : null;
                if ($vpromo && $vp > 0 && $vpromo >= $vp) $vpromo = null;
                $vimg = null;
                if (isset($_FILES['variantes_image']) && is_array($_FILES['variantes_image']['name']) && isset($_FILES['variantes_image']['name'][$i]) && $_FILES['variantes_image']['error'][$i] === UPLOAD_ERR_OK) {
                    $fake = [
                        'image' => [
                            'name' => $_FILES['variantes_image']['name'][$i],
                            'type' => $_FILES['variantes_image']['type'][$i],
                            'tmp_name' => $_FILES['variantes_image']['tmp_name'][$i],
                            'error' => $_FILES['variantes_image']['error'][$i],
                            'size' => $_FILES['variantes_image']['size'][$i]
                        ]
                    ];
                    $vimg = upload_produit_image($fake, 'image');
                }
                if ($vid > 0 && in_array($vid, $existing_ids)) {
                    $old = get_variante_by_id($vid);
                    $img_final = $vimg ?: ($old ? $old['image'] : null);
                    update_variante($vid, [
                        'nom' => $vn,
                        'prix' => $vp,
                        'prix_promotion' => $vpromo,
                        'image' => $img_final,
                        'ordre' => $i
                    ]);
                    $kept_ids[] = $vid;
                } else {
                    create_variante([
                        'produit_id' => $produit_id,
                        'nom' => $vn,
                        'prix' => $vp,
                        'prix_promotion' => $vpromo,
                        'image' => $vimg,
                        'ordre' => $i
                    ]);
                }
            }
            foreach ($existing_ids as $eid) {
                if (!in_array($eid, $kept_ids)) {
                    delete_variante($eid);
                }
            }
        } else {
            $errors[] = 'Une erreur est survenue lors de la modification du produit.';
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la suppression d'un produit
 * @param int $produit_id L'ID du produit à supprimer
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_produit($produit_id) {
    // Vérifier que le produit existe
    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }
    
    // Supprimer l'image si elle existe
    if ($produit['image_principale'] && file_exists(__DIR__ . '/../upload/' . $produit['image_principale'])) {
        @unlink(__DIR__ . '/../upload/' . $produit['image_principale']);
    }
    
    // Supprimer le produit
    if (delete_produit($produit_id)) {
        return ['success' => true, 'message' => 'Produit supprimé avec succès !'];
    } else {
        return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'];
    }
}

/**
 * Traite l'ajustement du stock d'un produit
 * @param int $produit_id ID du produit
 * @return array ['success' => bool, 'message' => string]
 */
function process_ajuster_stock_produit($produit_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajuster_stock'])) {
        return ['success' => false, 'message' => ''];
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }

    $nouveau_stock = isset($_POST['nouveau_stock']) ? (int) $_POST['nouveau_stock'] : -1;
    if ($nouveau_stock < 0) {
        return ['success' => false, 'message' => 'La quantité doit être un nombre positif ou zéro.'];
    }

    $quantite_avant = (int) ($produit['stock'] ?? 0);
    if ($nouveau_stock === $quantite_avant) {
        return ['success' => false, 'message' => 'La nouvelle quantité est identique au stock actuel.'];
    }

    $statut = $produit['statut'];
    if ($nouveau_stock <= 0) {
        $statut = 'rupture_stock';
    } elseif ($produit['statut'] === 'rupture_stock') {
        $statut = 'actif';
    }
    $data_update = array_merge($produit, ['stock' => $nouveau_stock, 'statut' => $statut]);
    if (update_produit($produit_id, $data_update)) {
        $quantite_diff = $nouveau_stock - $quantite_avant;
        create_stock_mouvement([
            'type' => 'inventaire',
            'stock_article_id' => null,
            'produit_id' => $produit_id,
            'quantite' => abs($quantite_diff),
            'quantite_avant' => $quantite_avant,
            'quantite_apres' => $nouveau_stock,
            'reference_type' => 'ajustement',
            'reference_id' => null,
            'reference_numero' => null,
            'notes' => 'Ajustement manuel : ' . ($quantite_diff >= 0 ? '+' : '') . $quantite_diff
        ]);
        return ['success' => true, 'message' => 'Stock ajusté avec succès.'];
    }

    return ['success' => false, 'message' => 'Erreur lors de la mise à jour du stock.'];
}

?>

