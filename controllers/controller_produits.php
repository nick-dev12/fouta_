<?php
/* VOIR N'EST PAS MODIFIER (31/08/2026) — les quatre champs d'argent et de
 * fournisseur (prix, prix promotionnel, prix d'achat, fournisseur) ne sont
 * plus écrits sur la simple foi de « le rôle voit le champ ». Ils demandent
 * produit_formulaire_champ_modifiable() : celui qui vend LIT le prix, seul
 * le responsable stock le FIXE. Un prix envoyé par quelqu'un qui n'a que le
 * droit de voir est ignoré — la valeur en base est conservée, exactement
 * comme pour un champ masqué. Cacher un champ n'a jamais protégé personne :
 * c'est ici, à l'écriture, que la règle tient. */
/**
 * Contrôleur pour la gestion des produits
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_marques.php';
require_once __DIR__ . '/../models/model_sous_categories.php';
require_once __DIR__ . '/../includes/barcode_fpl.php';
require_once __DIR__ . '/../includes/fouta_upload_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/produit_emplacement_entrepot.php';

/**
 * Valide un prix entreprise saisi (tarif professionnel) : nombre >= 0 et sous
 * le prix de vente. Règle UNIQUE pour la création et la modification. Pousse le
 * message dans $errors et renvoie la valeur validée, ou null (vide ou refusée).
 *
 * @param string $pe_raw la saisie déjà trim
 * @param mixed $prix le prix de vente (plafond), ou non défini
 * @param array<int, string> $errors
 * @return float|null
 */
function produits_valider_prix_entreprise($pe_raw, $prix, array &$errors)
{
    if ($pe_raw === '') {
        return null;
    }
    if (!is_numeric($pe_raw) || (float) $pe_raw < 0) {
        $errors[] = 'Le prix entreprise doit être un nombre valide (0 ou plus).';
        return null;
    }
    if (isset($prix) && (float) $prix > 0 && (float) $pe_raw > (float) $prix) {
        $errors[] = 'Le prix entreprise doit rester sous le prix de vente.';
        return null;
    }

    return (float) $pe_raw;
}

/**
 * Génère et sauvegarde le QR code d'un produit (pointant vers stock-info.php)
 * @param int $produit_id ID du produit
 * @return bool True si succès
 */
function generer_qrcode_produit($produit_id)
{
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../includes/site_url.php';
    $produit_id = (int) $produit_id;
    $produit = get_produit_by_id($produit_id);
    $url = produit_emplacement_stock_info_url($produit_id, $produit ?: []);
    $dir = __DIR__ . '/../upload/qrcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'produit_' . (int) $produit_id . '.png';
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale' => 10,
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
require_once __DIR__ . '/../includes/stock_alertes_notifications.php';
require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';
require_once __DIR__ . '/../includes/produit_formulaire_champs.php';

/**
 * Upload une image de produit
 * @param array $file Le fichier $_FILES
 * @param string $field_name Le nom du champ
 * @return string|false Le nom du fichier ou False en cas d'erreur
 */
function upload_produit_image($file, $field_name = 'image', &$out_error = null)
{
    if ($out_error !== null) {
        $out_error = null;
    }

    if (!isset($file[$field_name]) || !is_array($file[$field_name])) {
        return false;
    }

    $file_info = $file[$field_name];
    $code = isset($file_info['error']) ? (int) $file_info['error'] : UPLOAD_ERR_NO_FILE;

    if ($code === UPLOAD_ERR_NO_FILE) {
        return false;
    }

    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        if ($out_error !== null) {
            $out_error = fouta_upload_image_err_ini_ou_limite();
        }
        return false;
    }

    if ($code !== UPLOAD_ERR_OK) {
        if ($out_error !== null) {
            $out_error = 'Échec du téléversement de l’image.';
        }
        return false;
    }

    $size = isset($file_info['size']) ? (int) $file_info['size'] : 0;
    if ($size <= 0) {
        if ($out_error !== null) {
            $out_error = 'Fichier image invalide.';
        }
        return false;
    }

    if ($size > FOUTA_UPLOAD_IMAGE_MAX_BYTES) {
        if ($out_error !== null) {
            $out_error = 'Image trop volumineuse (max. ' . fouta_upload_image_max_mo_int() . ' Mo).';
        }
        return false;
    }

    $upload_dir = __DIR__ . '/../upload/produits/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file_info['type'], $allowed_types, true)) {
        if ($out_error !== null) {
            $out_error = 'Format non autorisé (JPG, PNG, GIF, WEBP).';
        }
        return false;
    }

    $result = upload_optimize_image_file($file_info, $upload_dir, 'produits', 'produit_');
    if (empty($result['success'])) {
        if ($out_error !== null) {
            $out_error = !empty($result['message']) ? (string) $result['message'] : 'Impossible d’enregistrer l’image sur le serveur.';
        }
        return false;
    }

    return (string) ($result['relative_path'] ?? '');
}

/**
 * Upload plusieurs images supplémentaires
 * @param array $files $_FILES avec name en tableau (ex: images_supplementaires[])
 * @param string $field_name Le nom du champ (ex: images_supplementaires)
 * @return array Tableau des chemins des images uploadées
 */
function upload_produit_images_multiples($files, $field_name = 'images_supplementaires', &$first_error = null)
{
    $uploaded = [];
    $collect_err = func_num_args() > 2;
    if ($collect_err) {
        $first_error = null;
    }
    if (!isset($files[$field_name]) || !is_array($files[$field_name]['name'])) {
        return $uploaded;
    }

    $count = count($files[$field_name]['name']);
    for ($i = 0; $i < $count; $i++) {
        $one_err = isset($files[$field_name]['error'][$i]) ? (int) $files[$field_name]['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($one_err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $file = [
            'name' => $files[$field_name]['name'][$i],
            'type' => $files[$field_name]['type'][$i],
            'tmp_name' => $files[$field_name]['tmp_name'][$i],
            'error' => $files[$field_name]['error'][$i],
            'size' => $files[$field_name]['size'][$i]
        ];
        $fake_files = [$field_name => $file];
        $row_err = null;
        $path = upload_produit_image($fake_files, $field_name, $row_err);
        if ($path) {
            $uploaded[] = $path;
        } elseif ($row_err !== null && $row_err !== '') {
            $uploaded = [];
            if ($collect_err) {
                $first_error = $row_err;
            }
            break;
        }
    }
    return $uploaded;
}

/**
 * Nom du fournisseur depuis POST — optionnel, tronqué à 255 caractères UTF-8 (ancien champ texte).
 *
 * @return string|null Valeur conservée ou null si vide après trim.
 */
function produits_normalize_nom_fournisseur_from_post(array $post)
{
    $s = isset($post['nom_fournisseur']) ? trim((string) $post['nom_fournisseur']) : '';
    if ($s === '') {
        return null;
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($s, 'UTF-8') > 255) {
            $s = mb_substr($s, 0, 255, 'UTF-8');
        }
    } elseif (strlen($s) > 255) {
        $s = substr($s, 0, 255);
    }
    return $s;
}

/**
 * Résout fournisseur_id + nom dénormalisé depuis le formulaire (liste déroulante).
 *
 * @return array{fournisseur_id: int|null, nom_fournisseur: string|null}
 */
function produits_resolve_fournisseur_from_post(array $post)
{
    if (!function_exists('produits_has_column') || !produits_has_column('fournisseur_id')) {
        $nom = produits_normalize_nom_fournisseur_from_post($post);
        return ['fournisseur_id' => null, 'nom_fournisseur' => $nom];
    }
    require_once __DIR__ . '/../models/model_fournisseurs.php';
    if (!isset($post['fournisseur_id']) || $post['fournisseur_id'] === '' || (int) $post['fournisseur_id'] <= 0) {
        /* AUCUN FOURNISSEUR CHOISI DANS LA LISTE. Le formulaire propose alors un
         * champ libre, « Si absent de la liste ci-dessus » — et ce nom était
         * jeté sans rien dire : on le tapait, il disparaissait. On le garde.
         * La liste reste prioritaire : ce champ ne sert que quand elle est vide. */
        return [
            'fournisseur_id' => null,
            'nom_fournisseur' => produits_normalize_nom_fournisseur_from_post($post),
        ];
    }
    $fid = (int) $post['fournisseur_id'];
    $f = get_fournisseur_by_id($fid);
    if (!$f) {
        return ['fournisseur_id' => null, 'nom_fournisseur' => null];
    }
    return ['fournisseur_id' => $fid, 'nom_fournisseur' => trim((string) $f['nom'])];
}

/**
 * Traite l'ajout d'un nouveau produit
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_produit()
{
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    // Récupération et validation des données (stock géré via produits.stock)
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $fournisseur_res = produits_resolve_fournisseur_from_post($_POST);
    $prix_raw = isset($_POST['prix']) ? trim((string) $_POST['prix']) : '';
    $prix_promotion = null;
    if (isset($_POST['prix_promotion']) && trim((string) ($_POST['prix_promotion'] ?? '')) !== '') {
        $prix_promotion = trim((string) $_POST['prix_promotion']);
    }
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $statut = 'actif';
    if (isset($_POST['statut']) && in_array((string) $_POST['statut'], ['actif', 'inactif', 'rupture_stock'], true)) {
        $statut = (string) $_POST['statut'];
    }
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : 'unité';
    $couleurs = null;
    if (isset($_POST['couleurs']) && trim($_POST['couleurs']) !== '') {
        $raw = trim($_POST['couleurs']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $valid = array_values(array_unique(array_filter($decoded, function ($c) {
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
                $arr = array_map(function ($x) {
                    return ['v' => trim($x), 's' => 0];
                }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function ($x) {
                    return !empty($x['v']) && $x['v'] !== '[]';
                });
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
                $arr = array_map(function ($x) {
                    return ['v' => trim($x), 's' => 0];
                }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function ($x) {
                    return !empty($x['v']) && $x['v'] !== '[]';
                });
                $taille = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }

    // Validation
    if (produit_formulaire_champ_visible('nom') && empty($nom)) {
        $errors[] = 'Le nom de la pièce est obligatoire.';
    }

    if (produit_formulaire_champ_modifiable('prix')) {
        if ($prix_raw === '') {
            $prix = 0.0;
        } elseif (!is_numeric($prix_raw)) {
            $errors[] = 'Le prix doit être un nombre valide (0 ou plus).';
        } elseif ((float) $prix_raw < 0) {
            $errors[] = 'Le prix ne peut pas être négatif.';
        } else {
            $prix = (float) $prix_raw;
        }
    } else {
        $prix = 0.0;
    }

    if (produit_formulaire_champ_modifiable('prix_promotion') && $prix_promotion !== null) {
        if (!is_numeric($prix_promotion)) {
            $errors[] = 'Le prix promotionnel doit être un nombre valide.';
        } elseif ((float) $prix_promotion <= 0) {
            $errors[] = 'Le prix promotionnel doit être strictement positif.';
        } elseif ((float) $prix <= 0) {
            $errors[] = 'Un prix promotionnel nécessite un prix normal strictement positif.';
        } elseif ((float) $prix_promotion >= (float) $prix) {
            $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
        }
    }

    if (produit_formulaire_champ_visible('stock') && $stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }

    if (produit_formulaire_champ_visible('categorie_id') && $categorie_id <= 0) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }

    // Vérifier que la catégorie existe
    if (produit_formulaire_champ_visible('categorie_id') && $categorie_id > 0 && !get_categorie_by_id($categorie_id)) {
        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
    }

    produit_formulaire_valider_custom($_POST, $errors);

    $fid_sent = isset($_POST['fournisseur_id']) ? trim((string) $_POST['fournisseur_id']) : '';
    if (
        produit_formulaire_champ_modifiable('fournisseur_id')
        && $fid_sent !== ''
        && (int) $fid_sent > 0
        && produits_has_column('fournisseur_id')
        && $fournisseur_res['fournisseur_id'] === null
    ) {
        $errors[] = 'Le fournisseur sélectionné est invalide.';
    }

    $prix_achat = null;
    if (produit_formulaire_champ_modifiable('prix_achat') && produits_has_column('prix_achat')) {
        $pa_raw = isset($_POST['prix_achat']) ? trim((string) $_POST['prix_achat']) : '';
        if ($pa_raw !== '') {
            if (!is_numeric($pa_raw) || (float) $pa_raw < 0) {
                $errors[] = 'Le prix d\'achat doit être un nombre valide (0 ou plus).';
            } else {
                $prix_achat = (float) $pa_raw;
            }
        }
    }

    $sous_categorie_id = null;
    if (produit_formulaire_champ_visible('sous_categorie_id') && produits_has_column('sous_categorie_id') && function_exists('sous_categories_table_ok') && sous_categories_table_ok()) {
        $sc_post = isset($_POST['sous_categorie_id']) ? (int) $_POST['sous_categorie_id'] : 0;
        if ($sc_post > 0) {
            $row_sc = get_sous_categorie_by_id($sc_post);
            if (!$row_sc || (int) $row_sc['categorie_id'] !== $categorie_id) {
                $errors[] = 'La sous-catégorie choisie ne correspond pas à la catégorie de la pièce.';
            } else {
                $sous_categorie_id = $sc_post;
            }
        }
    }

    $marque_id_resolved = null;
    if (produit_formulaire_champ_visible('marque_id') && produits_has_column('marque_id')) {
        $mid_post = isset($_POST['marque_id']) ? (int) $_POST['marque_id'] : 0;
        if ($mid_post > 0) {
            if (!marques_table_ok() || !get_marque_by_id($mid_post)) {
                $errors[] = 'La marque sélectionnée est invalide.';
            } else {
                $marque_id_resolved = $mid_post;
            }
        }
    }

    $reference_fournisseur_val = null;
    if (produit_formulaire_champ_visible('reference_fournisseur') && produits_has_column('reference_fournisseur')) {
        $rf_raw = isset($_POST['reference_fournisseur']) ? trim((string) $_POST['reference_fournisseur']) : '';
        if ($rf_raw !== '') {
            $reference_fournisseur_val = function_exists('mb_substr')
                ? mb_substr($rf_raw, 0, 120, 'UTF-8')
                : substr($rf_raw, 0, 120);
        }
    }

    /* LE VÉHICULE DU WIZARD FPL (23/08) — les modèles compatibles (plusieurs
     * possibles, cases `modeles[]`) et la génération. Le modèle principal est
     * le premier de la liste ALPHABÉTIQUE des cochés ; la génération n'a de
     * sens qu'à modèle unique, et au sien. Sans marque, rien n'est retenu. */
    $modeles_retenus = [];
    $generation_retenue = null;
    if ($marque_id_resolved !== null && function_exists('produit_modeles_retenus')) {
        $modeles_post = isset($_POST['modeles']) && is_array($_POST['modeles'])
            ? array_slice($_POST['modeles'], 0, 30) : [];
        $modeles_retenus = produit_modeles_retenus($modeles_post, $marque_id_resolved);
        $generation_retenue = produit_generation_retenue(
            !empty($_POST['generation_id']) ? (int) $_POST['generation_id'] : null,
            count($modeles_retenus) === 1 ? $modeles_retenus[0] : null
        );
    }

    /* LA DESCRIPTION SORT AUTOMATIQUEMENT (règle FPL natif) : quand rien n'est
     * saisi, elle est composée MARQUE — MODÈLE — RÉF. OEM. Le wizard la
     * compose déjà à l'écran ; ici c'est le filet quand le JavaScript manque. */
    if ($description === '') {
        global $db;
        $morceaux = [];
        if ($marque_id_resolved !== null) {
            $mq = get_marque_by_id($marque_id_resolved);
            if ($mq && trim((string) $mq['nom']) !== '') {
                $morceaux[] = trim((string) $mq['nom']);
            }
        }
        if (isset($modeles_retenus[0])) {
            try {
                $st = $db->prepare("SELECT nom FROM vehicule_modeles WHERE id = :id");
                $st->execute(['id' => (int) $modeles_retenus[0]]);
                $mn = $st->fetchColumn();
                if ($mn !== false && trim((string) $mn) !== '') {
                    $morceaux[] = trim((string) $mn);
                }
            } catch (PDOException $e) {
                // sans modèle nommé, la description se passe de lui
            }
        }
        $oem_desc = isset($_POST['reference_oem']) ? trim((string) $_POST['reference_oem']) : '';
        if ($oem_desc !== '') {
            $morceaux[] = 'OEM ' . $oem_desc;
        }
        if ($morceaux !== []) {
            $description = implode(' — ', $morceaux);
        }
    }

    // Le nom en wolof — celui qu'on demande au comptoir ; il titre l'étiquette.
    $nom_wolof_val = null;
    if (produits_has_column('nom_wolof')) {
        $nw_raw = isset($_POST['nom_wolof']) ? trim((string) $_POST['nom_wolof']) : '';
        if ($nw_raw !== '') {
            if (mb_strlen($nw_raw) > 190) {
                $errors[] = 'Le nom en wolof est trop long (190 caractères au plus).';
            } else {
                $nom_wolof_val = $nw_raw;
            }
        }
    }

    // Le prix entreprise — le tarif des professionnels, sous le prix public.
    // Même visibilité que le prix de vente : qui ne tarife pas ne le pose pas.
    $prix_entreprise_val = null;
    if (produits_has_column('prix_entreprise') && produit_formulaire_champ_modifiable('prix')) {
        $pe_raw = isset($_POST['prix_entreprise']) ? trim((string) $_POST['prix_entreprise']) : '';
        $prix_entreprise_val = produits_valider_prix_entreprise($pe_raw, $prix ?? null, $errors);
    }

    $identifiant_attribue = null;
    if (produit_formulaire_champ_visible('identifiant_interne') && produits_has_column('identifiant_interne')) {
        $identifiant_attribue = produits_allouer_identifiant_fpl_9_auto(0);
        if (!$identifiant_attribue) {
            $errors[] = 'Impossible d\'attribuer une référence interne unique. Réessayez.';
        }
    }

    // Upload des images : images_produit[] (1ère = principale, reste = galerie)
    // Si lié à un article en stock, on utilise son image si pas d'upload
    $image_principale = null;
    $images_supp = [];
    if (
        produit_formulaire_champ_visible('images_produit')
        && isset($_FILES['images_produit']) && is_array($_FILES['images_produit']['name'])
    ) {
        $img_err = null;
        $uploaded = upload_produit_images_multiples($_FILES, 'images_produit', $img_err);
        if ($img_err !== null && $img_err !== '') {
            $errors[] = $img_err;
        }
        if (!empty($uploaded)) {
            $image_principale = $uploaded[0];
            $images_supp = array_slice($uploaded, 1);
        }
    }

    // Construire le tableau images (principale + supplémentaires) en JSON
    $images_json = null;
    if ($image_principale) {
        $all_images = array_merge([$image_principale], $images_supp);
        $images_json = json_encode($all_images);
    }

    $image_etiquette_fpl = null;
    if (
        produit_formulaire_champ_visible('image_etiquette_fpl')
        && produits_has_column('image_etiquette_fpl')
        && isset($_FILES['image_etiquette_fpl'])
        && (int) ($_FILES['image_etiquette_fpl']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ) {
        $up_et_err = null;
        $up_et = upload_produit_image($_FILES, 'image_etiquette_fpl', $up_et_err);
        if ($up_et !== false) {
            $image_etiquette_fpl = $up_et;
        } else {
            $errors[] = ($up_et_err !== null && $up_et_err !== '')
                ? ('Image étiquette FPL : ' . $up_et_err)
                : 'Image étiquette FPL : fichier invalide ou format non autorisé (JPG, PNG, GIF, WEBP).';
        }
    }

    // Si aucune erreur, créer le produit
    if (empty($errors)) {
        $emplacement = produit_emplacement_from_source($_POST);
        $admin_session_id = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
        $data = [
            'nom' => $nom,
            'description' => $description,
            'fournisseur_id' => $fournisseur_res['fournisseur_id'],
            'nom_fournisseur' => $fournisseur_res['nom_fournisseur'],
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
        ];
        foreach ($emplacement as $col => $val) {
            $data[$col] = $val;
        }
        if (produits_has_column('prix_achat')) {
            $data['prix_achat'] = $prix_achat;
        }
        // Identité technique des pièces poids lourds (reprise de FPL natif)
        if (produits_has_column('reference_oem')) {
            $ref_oem = isset($_POST['reference_oem']) ? trim((string) $_POST['reference_oem']) : '';
            $data['reference_oem'] = $ref_oem !== '' ? $ref_oem : null;
        }
        if (produits_has_column('position_montage')) {
            $pos = isset($_POST['position_montage']) ? (string) $_POST['position_montage'] : '';
            $data['position_montage'] = in_array($pos, ['gauche', 'droite'], true) ? $pos : null;
        }
        if (produits_has_column('sous_categorie_id')) {
            $data['sous_categorie_id'] = $sous_categorie_id;
        }
        if ($identifiant_attribue !== null && produits_has_column('identifiant_interne')) {
            $data['identifiant_interne'] = $identifiant_attribue;
        }
        if (produits_has_column('image_etiquette_fpl')) {
            $data['image_etiquette_fpl'] = $image_etiquette_fpl;
        }
        if (produits_has_column('marque_id')) {
            $data['marque_id'] = $marque_id_resolved;
        }
        if (produits_has_column('reference_fournisseur')) {
            $data['reference_fournisseur'] = $reference_fournisseur_val;
        }
        // Le véhicule du wizard FPL : modèle principal + génération (colonnes
        // déjà présentes), le nom wolof et le prix entreprise (migration 23/08)
        if (produits_has_column('modele_id')) {
            $data['modele_id'] = isset($modeles_retenus[0]) ? (int) $modeles_retenus[0] : null;
        }
        if (produits_has_column('generation_id')) {
            $data['generation_id'] = $generation_retenue;
        }
        if (produits_has_column('nom_wolof')) {
            $data['nom_wolof'] = $nom_wolof_val;
        }
        if (produits_has_column('prix_entreprise')) {
            $data['prix_entreprise'] = $prix_entreprise_val;
        }
        if ($admin_session_id > 0) {
            $data['admin_createur_id'] = $admin_session_id;
        }

        $produit_id = create_produit($data);

        if ($produit_id) {
            $success = true;
            $message = 'Pièce ajoutée avec succès !';
            if ($identifiant_attribue !== null && $identifiant_attribue !== '') {
                $message .= ' Référence : ' . $identifiant_attribue . '.';
            }
            // Toutes les compatibilités véhicule — le modèle principal compris
            if ($modeles_retenus !== [] && function_exists('produit_modeles_poser')) {
                produit_modeles_poser((int) $produit_id, $modeles_retenus);
            }
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
                $vp = isset($variantes_prix[$i]) && is_numeric($variantes_prix[$i]) ? (float) $variantes_prix[$i] : 0;
                if ($vn !== '' && $vp > 0) {
                    $vimg = null;
                    if ($variantes_files && isset($variantes_files['name'][$i]) && (int) ($variantes_files['error'][$i] ?? 4) === UPLOAD_ERR_OK) {
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
                    $vpromo = isset($variantes_prix_promo[$i]) && is_numeric($variantes_prix_promo[$i]) && (float) $variantes_prix_promo[$i] > 0 ? (float) $variantes_prix_promo[$i] : null;
                    if ($vpromo !== null && $vpromo >= $vp)
                        $vpromo = null;
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
            stock_alertes_notifier_baisse_stock((int) $produit_id, PHP_INT_MAX, (int) $stock);
            produit_formulaire_enregistrer_valeurs_custom((int) $produit_id, $_POST);
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'ajout du produit.';
        }
    }

    if ($success) {
        // L'identifiant de la pièce créée : la page d'ajout mène droit à sa
        // fiche (parcours FPL natif), au lieu de renvoyer à une liste.
        return [
            'success' => true,
            'message' => $message,
            'produit_id' => isset($produit_id) ? (int) $produit_id : 0,
            'identifiant' => $identifiant_attribue,
        ];
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
function process_update_produit($produit_id)
{
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    // Vérifier que le produit existe (sans filtre d’accès : conserver les champs masqués)
    $produit = get_produit_by_id_sans_filtre_acces($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Pièce introuvable.'];
    }

    // Récupération et validation des données (stock géré via produits.stock)
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    /* LA DESCRIPTION EST GÉNÉRÉE, PLUS SAISIE (24/08) : l'écran Modifier
     * envoie un champ caché recomposé par la page. Vide — JavaScript coupé,
     * ancien formulaire — on GARDE la description déjà en base : une mise à
     * jour ne doit jamais effacer en silence ce que la fiche portait. */
    if ($description === '') {
        $description = trim((string) ($produit['description'] ?? ''));
    }
    $fournisseur_res = produit_formulaire_champ_modifiable('fournisseur_id')
        ? produits_resolve_fournisseur_from_post($_POST)
        : [
            'fournisseur_id' => $produit['fournisseur_id'] ?? null,
            'nom_fournisseur' => $produit['nom_fournisseur'] ?? null,
        ];
    $prix_raw = isset($_POST['prix']) ? trim((string) $_POST['prix']) : '';
    $prix_promotion = null;
    if (isset($_POST['prix_promotion']) && trim((string) ($_POST['prix_promotion'] ?? '')) !== '') {
        $prix_promotion = trim((string) $_POST['prix_promotion']);
    }
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : 'unité';
    $statut = 'actif';
    if (isset($_POST['statut']) && in_array((string) $_POST['statut'], ['actif', 'inactif', 'rupture_stock'], true)) {
        $statut = (string) $_POST['statut'];
    }
    $couleurs = null;
    if (isset($_POST['couleurs']) && trim($_POST['couleurs']) !== '') {
        $raw = trim($_POST['couleurs']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $valid = array_values(array_unique(array_filter($decoded, function ($c) {
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
                $arr = array_map(function ($x) {
                    return ['v' => trim($x), 's' => 0];
                }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function ($x) {
                    return !empty($x['v']) && $x['v'] !== '[]';
                });
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
                $arr = array_map(function ($x) {
                    return ['v' => trim($x), 's' => 0];
                }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function ($x) {
                    return !empty($x['v']) && $x['v'] !== '[]';
                });
                $taille = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }

    // Validation (identique à l'ajout)
    if (produit_formulaire_champ_visible('nom') && empty($nom)) {
        $errors[] = 'Le nom de la pièce est obligatoire.';
    }

    if (produit_formulaire_champ_modifiable('prix')) {
        if ($prix_raw === '') {
            $prix = 0.0;
        } elseif (!is_numeric($prix_raw)) {
            $errors[] = 'Le prix doit être un nombre valide (0 ou plus).';
        } elseif ((float) $prix_raw < 0) {
            $errors[] = 'Le prix ne peut pas être négatif.';
        } else {
            $prix = (float) $prix_raw;
        }
    } else {
        $prix = isset($produit['prix']) ? (float) $produit['prix'] : 0.0;
    }

    if (produit_formulaire_champ_modifiable('prix_promotion') && $prix_promotion !== null) {
        if (!is_numeric($prix_promotion)) {
            $errors[] = 'Le prix promotionnel doit être un nombre valide.';
        } elseif ((float) $prix_promotion <= 0) {
            $errors[] = 'Le prix promotionnel doit être strictement positif.';
        } elseif ((float) $prix <= 0) {
            $errors[] = 'Un prix promotionnel nécessite un prix normal strictement positif.';
        } elseif ((float) $prix_promotion >= (float) $prix) {
            $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
        }
    }

    if (produit_formulaire_champ_visible('stock') && $stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }

    if (produit_formulaire_champ_visible('categorie_id') && $categorie_id <= 0) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }

    if (produit_formulaire_champ_visible('categorie_id') && $categorie_id > 0 && !get_categorie_by_id($categorie_id)) {
        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
    }

    produit_formulaire_valider_custom($_POST, $errors);

    $fid_sent_upd = isset($_POST['fournisseur_id']) ? trim((string) $_POST['fournisseur_id']) : '';
    if (
        produit_formulaire_champ_modifiable('fournisseur_id')
        && $fid_sent_upd !== ''
        && (int) $fid_sent_upd > 0
        && produits_has_column('fournisseur_id')
        && $fournisseur_res['fournisseur_id'] === null
    ) {
        $errors[] = 'Le fournisseur sélectionné est invalide.';
    }

    $prix_achat = null;
    if (produit_formulaire_champ_modifiable('prix_achat') && produits_has_column('prix_achat')) {
        $pa_raw = isset($_POST['prix_achat']) ? trim((string) $_POST['prix_achat']) : '';
        if ($pa_raw !== '') {
            if (!is_numeric($pa_raw) || (float) $pa_raw < 0) {
                $errors[] = 'Le prix d\'achat doit être un nombre valide (0 ou plus).';
            } else {
                $prix_achat = (float) $pa_raw;
            }
        }
    }

    $sous_categorie_id = null;
    if (produit_formulaire_champ_visible('sous_categorie_id') && produits_has_column('sous_categorie_id') && function_exists('sous_categories_table_ok') && sous_categories_table_ok()) {
        $sc_post = isset($_POST['sous_categorie_id']) ? (int) $_POST['sous_categorie_id'] : 0;
        if ($sc_post > 0) {
            $row_sc = get_sous_categorie_by_id($sc_post);
            if (!$row_sc || (int) $row_sc['categorie_id'] !== $categorie_id) {
                $errors[] = 'La sous-catégorie choisie ne correspond pas à la catégorie de la pièce.';
            } else {
                $sous_categorie_id = $sc_post;
            }
        }
    }

    $marque_id_resolved = null;
    if (produit_formulaire_champ_visible('marque_id') && produits_has_column('marque_id')) {
        $mid_post = isset($_POST['marque_id']) ? (int) $_POST['marque_id'] : 0;
        if ($mid_post > 0) {
            if (!marques_table_ok() || !get_marque_by_id($mid_post)) {
                $errors[] = 'La marque sélectionnée est invalide.';
            } else {
                $marque_id_resolved = $mid_post;
            }
        }
    }

    $reference_fournisseur_val = null;
    if (produit_formulaire_champ_visible('reference_fournisseur') && produits_has_column('reference_fournisseur')) {
        $rf_raw = isset($_POST['reference_fournisseur']) ? trim((string) $_POST['reference_fournisseur']) : '';
        if ($rf_raw !== '') {
            $reference_fournisseur_val = function_exists('mb_substr')
                ? mb_substr($rf_raw, 0, 120, 'UTF-8')
                : substr($rf_raw, 0, 120);
        }
    }

    /* LE VÉHICULE (fiche FPL, 23/08) : les modèles cochés et la génération ne
     * se posent que si le formulaire les a envoyés — un écran qui ne les
     * montre pas ne les efface pas. `modeles_envoyes` est le témoin. */
    $modeles_maj = null;          // null = on ne touche à rien
    $generation_maj = null;
    if (array_key_exists('modeles_envoyes', $_POST) && function_exists('produit_modeles_retenus')) {
        $marque_pour_modeles = $marque_id_resolved !== null
            ? $marque_id_resolved
            : (produit_formulaire_champ_visible('marque_id') ? null : (int) ($produit['marque_id'] ?? 0));
        $modeles_post = isset($_POST['modeles']) && is_array($_POST['modeles'])
            ? array_slice($_POST['modeles'], 0, 30) : [];
        $modeles_maj = $marque_pour_modeles ? produit_modeles_retenus($modeles_post, $marque_pour_modeles) : [];
        $generation_maj = produit_generation_retenue(
            !empty($_POST['generation_id']) ? (int) $_POST['generation_id'] : null,
            count($modeles_maj) === 1 ? $modeles_maj[0] : null
        );
    }

    // Le nom en wolof : présent et vide = on efface ; absent = on ne touche à rien.
    $nom_wolof_maj = null;
    $nom_wolof_envoye = produits_has_column('nom_wolof') && array_key_exists('nom_wolof', $_POST);
    if ($nom_wolof_envoye) {
        $nom_wolof_maj = trim((string) $_POST['nom_wolof']);
        if (mb_strlen($nom_wolof_maj) > 190) {
            $errors[] = 'Le nom en wolof est trop long (190 caractères au plus).';
        }
    }

    // Le prix entreprise : même visibilité que le prix de vente.
    $prix_entreprise_maj = null;
    $prix_entreprise_envoye = produits_has_column('prix_entreprise')
        && produit_formulaire_champ_modifiable('prix') && array_key_exists('prix_entreprise', $_POST);
    if ($prix_entreprise_envoye) {
        $pe_raw = trim((string) $_POST['prix_entreprise']);
        $prix_entreprise_maj = produits_valider_prix_entreprise($pe_raw, $prix ?? null, $errors);
    }

    $nouvel_identifiant = null;
    if (
        produit_formulaire_champ_visible('identifiant_interne')
        && produits_has_column('identifiant_interne')
        && isset($_POST['reference_suffix6'])
    ) {
        $ref6 = preg_replace('/\D/', '', (string) $_POST['reference_suffix6']);
        if (strlen($ref6) !== 6) {
            $errors[] = 'Indiquez exactement 6 chiffres pour la fin de la référence produit.';
        } else {
            $cur = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
            if (preg_match('/^FPL(\d{3})(\d{6})$/', $cur, $m)) {
                $nouvel_identifiant = 'FPL' . $m[1] . $ref6;
            } else {
                $nouvel_identifiant = produits_allouer_identifiant_fpl_9($ref6, $produit_id);
            }
            if (!$nouvel_identifiant) {
                $errors[] = 'Impossible de déterminer la référence interne.';
            } elseif (produits_identifiant_interne_existe($nouvel_identifiant, $produit_id)) {
                $errors[] = 'Cette référence interne est déjà utilisée par un autre produit.';
            }
        }
    }

    $image_etiquette_fpl_courant = '';
    if (produits_has_column('image_etiquette_fpl')) {
        $image_etiquette_fpl_courant = trim((string) ($produit['image_etiquette_fpl'] ?? ''));
    }
    if (
        produit_formulaire_champ_visible('image_etiquette_fpl')
        && produits_has_column('image_etiquette_fpl')
        && isset($_FILES['image_etiquette_fpl'])
        && (int) ($_FILES['image_etiquette_fpl']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ) {
        $up_et_err = null;
        $up_et = upload_produit_image($_FILES, 'image_etiquette_fpl', $up_et_err);
        if ($up_et !== false) {
            if ($image_etiquette_fpl_courant !== '') {
                image_optimizer_delete_with_variants($image_etiquette_fpl_courant);
            }
            $image_etiquette_fpl_courant = $up_et;
        } else {
            $errors[] = ($up_et_err !== null && $up_et_err !== '')
                ? ('Image étiquette FPL : ' . $up_et_err)
                : 'Image étiquette FPL : fichier invalide ou format non autorisé (JPG, PNG, GIF, WEBP).';
        }
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
        $sup_err = null;
        $images_supp = upload_produit_images_multiples($_FILES, 'images_supplementaires', $sup_err);
        if ($sup_err !== null && $sup_err !== '') {
            $errors[] = $sup_err;
        }
    }

    // Construire le tableau final : images conservées + nouvelles
    $final_images = array_merge($images_to_keep, $images_supp);
    $final_images = array_values(array_unique($final_images));

    $image_principale = !empty($final_images) ? $final_images[0] : null;
    $images_json = !empty($final_images) ? json_encode($final_images) : null;
    $removed_images = array_diff($all_images, $images_to_keep);

    if (!produit_formulaire_champ_visible('images_produit')) {
        $image_principale = $produit['image_principale'] ?? null;
        $images_json = $produit['images'] ?? null;
        $removed_images = [];
    }

    // Si aucune erreur, mettre à jour le produit
    if (empty($errors)) {
        if (!produit_formulaire_champ_visible('nom')) {
            $nom = (string) ($produit['nom'] ?? '');
        }
        if (!produit_formulaire_champ_visible('description')) {
            $description = (string) ($produit['description'] ?? '');
        }
        if (!produit_formulaire_champ_visible('stock')) {
            $stock = (int) ($produit['stock'] ?? 0);
        }
        if (!produit_formulaire_champ_visible('categorie_id')) {
            $categorie_id = (int) ($produit['categorie_id'] ?? 0);
        }
        if (!produit_formulaire_champ_modifiable('prix_promotion')) {
            $prix_promotion = isset($produit['prix_promotion']) && $produit['prix_promotion'] !== '' && $produit['prix_promotion'] !== null
                ? (string) $produit['prix_promotion'] : null;
        }
        if (!produit_formulaire_champ_visible('poids')) {
            $poids = $produit['poids'] ?? null;
        }
        if (!produit_formulaire_champ_visible('couleurs')) {
            $couleurs = $produit['couleurs'] ?? null;
        }
        if (!produit_formulaire_champ_visible('taille')) {
            $taille = $produit['taille'] ?? null;
        }
        if (!produit_formulaire_champ_visible('statut')) {
            $statut = (string) ($produit['statut'] ?? 'actif');
        }
        $emplacement = produit_formulaire_champ_visible('emplacement')
            ? produit_emplacement_from_source($_POST)
            : produit_emplacement_from_produit($produit);
        $admin_session_id = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
        $data = [
            'nom' => $nom,
            'description' => $description,
            'fournisseur_id' => $fournisseur_res['fournisseur_id'],
            'nom_fournisseur' => $fournisseur_res['nom_fournisseur'],
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
        ];
        foreach ($emplacement as $col => $val) {
            $data[$col] = $val;
        }
        if (produit_formulaire_champ_modifiable('prix_achat') && produits_has_column('prix_achat')) {
            $data['prix_achat'] = $prix_achat;
        }
        /* LE SEUIL DE LA PIÈCE (31/08) — écrit seulement par qui en a le droit,
         * et posé « à la main » : le calcul des suggestions ne l'écrasera pas.
         * Champ vide = on retire le seuil ; le formulaire qui ne l'envoie pas
         * (profil sans le droit) ne touche à rien. */
        if (produit_formulaire_champ_modifiable('seuil_alerte')
            && produits_has_column('seuil_alerte')
            && array_key_exists('seuil_alerte', $_POST)) {
            $seuil_saisi = trim((string) $_POST['seuil_alerte']);
            $data['seuil_alerte'] = ($seuil_saisi === '' || !is_numeric($seuil_saisi) || (int) $seuil_saisi < 0)
                ? null : (int) $seuil_saisi;
            if (produits_has_column('seuil_alerte_source')) {
                $data['seuil_alerte_source'] = $data['seuil_alerte'] === null ? null : 'manuel';
            }
        }
        /* LA RÉFÉRENCE D'ORIGINE ET LE CÔTÉ DE MONTAGE, corrigeables enfin.
         * On ne pose la clé QUE si le formulaire a envoyé le champ : un écran
         * qui ne l'affiche pas ne doit pas remettre la valeur à zéro — c'est
         * exactement ce qui effaçait la taille à chaque enregistrement. */
        if (produits_has_column('reference_oem') && array_key_exists('reference_oem', $_POST)) {
            $ref_oem_maj = trim((string) $_POST['reference_oem']);
            $data['reference_oem'] = $ref_oem_maj !== '' ? $ref_oem_maj : null;
        }
        if (produits_has_column('position_montage') && array_key_exists('position_montage', $_POST)) {
            $pos_maj = (string) $_POST['position_montage'];
            $data['position_montage'] = in_array($pos_maj, ['gauche', 'droite'], true) ? $pos_maj : null;
        }
        if (produit_formulaire_champ_visible('sous_categorie_id') && produits_has_column('sous_categorie_id')) {
            $data['sous_categorie_id'] = $sous_categorie_id;
        }
        if ($nouvel_identifiant !== null && produits_has_column('identifiant_interne')) {
            $data['identifiant_interne'] = $nouvel_identifiant;
        }
        if (produit_formulaire_champ_visible('image_etiquette_fpl') && produits_has_column('image_etiquette_fpl')) {
            $data['image_etiquette_fpl'] = $image_etiquette_fpl_courant !== '' ? $image_etiquette_fpl_courant : null;
        }
        if (produit_formulaire_champ_visible('marque_id') && produits_has_column('marque_id')) {
            $data['marque_id'] = $marque_id_resolved;
        }
        if (produit_formulaire_champ_visible('reference_fournisseur') && produits_has_column('reference_fournisseur')) {
            $data['reference_fournisseur'] = $reference_fournisseur_val;
        }
        if (!produit_formulaire_champ_modifiable('fournisseur_id')) {
            unset($data['fournisseur_id'], $data['nom_fournisseur']);
        }
        // Le véhicule, le nom wolof et le prix entreprise — clés posées
        // SEULEMENT si le formulaire les a envoyés (voir plus haut)
        if ($modeles_maj !== null) {
            $data['modele_id'] = isset($modeles_maj[0]) ? (int) $modeles_maj[0] : null;
            $data['generation_id'] = $generation_maj;
        }
        if ($nom_wolof_envoye) {
            $data['nom_wolof'] = $nom_wolof_maj;
        }
        if ($prix_entreprise_envoye) {
            $data['prix_entreprise'] = $prix_entreprise_maj;
        }
        if ($admin_session_id > 0) {
            $data['admin_dernier_modificateur_id'] = $admin_session_id;
        }

        if (update_produit($produit_id, $data)) {
            $success = true;
            $message = 'Pièce modifiée avec succès !';
            if ($modeles_maj !== null && function_exists('produit_modeles_poser')) {
                produit_modeles_poser((int) $produit_id, $modeles_maj);
            }
            produit_formulaire_enregistrer_valeurs_custom((int) $produit_id, $_POST);
            $stock_old = (int) ($produit['stock'] ?? 0);
            $stock_new = (int) $stock;
            stock_alertes_notifier_baisse_stock($produit_id, $stock_old, $stock_new);
            // Supprimer du disque les images retirées par l'utilisateur
            foreach ($removed_images as $old_path) {
                if ($old_path !== '') {
                    image_optimizer_delete_with_variants((string) $old_path);
                }
            }
            // Gestion des variantes (ne pas les vider si le champ est masqué pour ce profil)
            if (produit_formulaire_champ_visible('variantes')) {
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
                if (empty($vn) || $vp <= 0)
                    continue;
                $vid = isset($variantes_id[$i]) ? (int) $variantes_id[$i] : 0;
                $vpromo = isset($variantes_prix_promo[$i]) && is_numeric($variantes_prix_promo[$i]) && $variantes_prix_promo[$i] > 0 ? (float) $variantes_prix_promo[$i] : null;
                if ($vpromo && $vp > 0 && $vpromo >= $vp)
                    $vpromo = null;
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
            }
            if ($nouvel_identifiant !== null && produits_has_column('identifiant_interne')) {
                $old_id = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
                $new_id = strtoupper(trim((string) $nouvel_identifiant));
                if ($old_id !== $new_id) {
                    generer_barcode_produit_fpl($produit_id);
                    generer_qrcode_produit($produit_id);
                }
            }
            $old_emplacement = produit_emplacement_from_produit($produit);
            $emplacement_modifie = false;
            if (produit_emplacement_use_referentiel()) {
                $ancien_pid = isset($old_emplacement['entrepot_position_id']) ? (string) $old_emplacement['entrepot_position_id'] : '';
                $nouveau_pid = isset($emplacement['entrepot_position_id']) ? (string) $emplacement['entrepot_position_id'] : '';
                $ancien_nid = isset($old_emplacement['entrepot_noeud_id']) ? (string) $old_emplacement['entrepot_noeud_id'] : '';
                $nouveau_nid = isset($emplacement['entrepot_noeud_id']) ? (string) $emplacement['entrepot_noeud_id'] : '';
                $emplacement_modifie = ($ancien_pid !== $nouveau_pid) || ($ancien_nid !== $nouveau_nid);
            }
            if (!$emplacement_modifie) {
            foreach ($emplacement as $col => $val) {
                if ($col === 'entrepot_position_id') {
                    continue;
                }
                $ancien = isset($old_emplacement[$col]) ? (string) $old_emplacement[$col] : '';
                $nouveau = $val !== null ? (string) $val : '';
                if ($ancien !== $nouveau) {
                    $emplacement_modifie = true;
                    break;
                }
            }
            }
            if ($emplacement_modifie) {
                generer_barcode_produit_fpl($produit_id);
                generer_qrcode_produit($produit_id);
            }
        } else {
            $errors[] = 'Une erreur est survenue lors de la modification de la pièce.';
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
function process_delete_produit($produit_id)
{
    $produit = get_produit_by_id_sans_filtre_acces($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Pièce introuvable.'];
    }

    if (!delete_produit($produit_id)) {
        return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'];
    }

    if (!empty($produit['image_principale'])) {
        image_optimizer_delete_with_variants((string) $produit['image_principale']);
    }
    if (!empty($produit['images'])) {
        $imgs = json_decode((string) $produit['images'], true);
        if (is_array($imgs)) {
            foreach ($imgs as $img_path) {
                if (trim((string) $img_path) !== '') {
                    image_optimizer_delete_with_variants((string) $img_path);
                }
            }
        }
    }
    if (!empty($produit['image_etiquette_fpl'])) {
        image_optimizer_delete_with_variants((string) $produit['image_etiquette_fpl']);
    }

    $qr = dirname(__DIR__) . '/upload/qrcodes/produit_' . (int) $produit_id . '.png';
    if (is_file($qr)) {
        @unlink($qr);
    }

    return ['success' => true, 'message' => 'Pièce supprimée avec succès !'];
}

/**
 * Traite l'ajustement du stock d'un produit (ajout cumulatif : stock après = stock actuel + quantité saisie).
 * Pour fixer une quantité absolue ou diminuer le stock sans ajout positif : modifier la fiche produit.
 *
 * @param int $produit_id ID du produit
 * @return array ['success' => bool, 'message' => string]
 */
function process_ajuster_stock_produit($produit_id)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajuster_stock'])) {
        return ['success' => false, 'message' => ''];
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Pièce introuvable.'];
    }

    $quantite_avant = (int) ($produit['stock'] ?? 0);
    $ajout = isset($_POST['quantite_ajout']) ? (int) $_POST['quantite_ajout'] : -1;
    if ($ajout < 1) {
        return ['success' => false, 'message' => 'Indiquez un nombre strictement positif : quantité à ajouter au stock actuel.'];
    }

    $nouveau_stock = $quantite_avant + $ajout;
    if ($nouveau_stock > 2147483647) {
        return ['success' => false, 'message' => 'Quantité trop élevée.'];
    }

    $statut = $produit['statut'];
    if ($nouveau_stock <= 0) {
        $statut = 'rupture_stock';
    } elseif ($produit['statut'] === 'rupture_stock') {
        $statut = 'actif';
    }
    $data_update = array_merge($produit, ['stock' => $nouveau_stock, 'statut' => $statut]);
    $ajust_admin = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    if ($ajust_admin > 0) {
        $data_update['admin_dernier_modificateur_id'] = $ajust_admin;
    }
    if (update_produit($produit_id, $data_update)) {
        $mv = [
            'type' => 'inventaire',
            'stock_article_id' => null,
            'produit_id' => $produit_id,
            'quantite' => $ajout,
            'quantite_avant' => $quantite_avant,
            'quantite_apres' => $nouveau_stock,
            'reference_type' => 'ajustement',
            'reference_id' => null,
            'reference_numero' => null,
            'notes' => 'Ajout au stock +' . $ajout . ' (' . $quantite_avant . ' → ' . $nouveau_stock . ')',
        ];
        if ($ajust_admin > 0) {
            $mv['admin_id'] = $ajust_admin;
        }
        create_stock_mouvement($mv);
        stock_alertes_notifier_baisse_stock($produit_id, $quantite_avant, $nouveau_stock);
        return ['success' => true, 'message' => 'Stock mis à jour : +' . $ajout . ' unité(s). Nouveau stock : ' . $nouveau_stock . '.'];
    }

    return ['success' => false, 'message' => 'Erreur lors de la mise à jour du stock.'];
}

