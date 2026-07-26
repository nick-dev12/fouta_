<?php
/**
 * Compression et variantes WebP des images uploadées (extension GD).
 *
 * À l'upload : redimensionnement + encodage WebP + variantes _md (800px) et _sm (400px).
 * À l'affichage : upload_image_url() pour servir la variante adaptée.
 */

require_once __DIR__ . '/fouta_upload_limits.php';

if (!defined('IMAGE_OPTIMIZER_MAX_WIDTH')) {
    define('IMAGE_OPTIMIZER_MAX_WIDTH', 1920);
}
if (!defined('IMAGE_OPTIMIZER_MD_WIDTH')) {
    define('IMAGE_OPTIMIZER_MD_WIDTH', 800);
}
if (!defined('IMAGE_OPTIMIZER_SM_WIDTH')) {
    define('IMAGE_OPTIMIZER_SM_WIDTH', 400);
}
if (!defined('IMAGE_OPTIMIZER_WEBP_QUALITY')) {
    define('IMAGE_OPTIMIZER_WEBP_QUALITY', 82);
}

/**
 * @return list<string>
 */
function image_optimizer_allowed_mimes() {
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
}

function image_optimizer_gd_available() {
    return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

function image_optimizer_webp_available() {
    return image_optimizer_gd_available() && function_exists('imagewebp');
}

/**
 * Cache is_file() sur la durée de la requête (évite des centaines d'appels disque).
 */
function image_optimizer_file_exists($abs_path) {
    static $cache = [];
    $key = (string) $abs_path;
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = is_file($key);
    }

    return $cache[$key];
}

/**
 * Indique si une image sous upload/ est déjà optimisée (WebP + variantes _md/_sm).
 *
 * @param string $relative_path Chemin relatif sous upload/
 */
function image_optimizer_is_fully_optimized($relative_path) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return false;
    }
    $upload_root = dirname(__DIR__) . '/upload/';
    $resolved = image_optimizer_resolve_relative_path($relative_path);
    if ($resolved === '') {
        return false;
    }
    $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
    if ($ext !== 'webp') {
        $dir = dirname($resolved);
        $stem = pathinfo($resolved, PATHINFO_FILENAME);
        if ($stem === '') {
            return false;
        }
        $webp_rel = ($dir === '.' || $dir === '') ? $stem . '.webp' : $dir . '/' . $stem . '.webp';
        if (!image_optimizer_file_exists($upload_root . $webp_rel)) {
            return false;
        }
        $resolved = $webp_rel;
    }
    foreach (['', '_md', '_sm'] as $suffix) {
        $dir = dirname($resolved);
        $stem = pathinfo($resolved, PATHINFO_FILENAME);
        if ($stem === '') {
            return false;
        }
        $variant = ($dir === '.' || $dir === '') ? $stem . $suffix . '.webp' : $dir . '/' . $stem . $suffix . '.webp';
        if ($suffix === '' && $variant === $resolved) {
            if (!image_optimizer_file_exists($upload_root . $variant)) {
                return false;
            }
            continue;
        }
        if (!image_optimizer_file_exists($upload_root . $variant)) {
            return false;
        }
    }

    return true;
}

/**
 * @return string
 */
function image_optimizer_detect_mime($path) {
    if (!is_file($path)) {
        return '';
    }
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $path);
            finfo_close($finfo);
            if ($mime !== '') {
                return $mime;
            }
        }
    }
    $info = @getimagesize($path);
    if (is_array($info) && !empty($info['mime'])) {
        return (string) $info['mime'];
    }

    return '';
}

/**
 * @return \GdImage|false
 */
function image_optimizer_load($path) {
    if (!image_optimizer_gd_available() || !is_file($path)) {
        return false;
    }
    $blob = @file_get_contents($path);
    if ($blob === false || $blob === '') {
        return false;
    }
    $img = @imagecreatefromstring($blob);
    if ($img === false) {
        return false;
    }
    if (function_exists('imageresolution')) {
        @imageresolution($img, 72, 72);
    }

    return $img;
}

/**
 * @param \GdImage $src
 * @return \GdImage|false
 */
function image_optimizer_resize($src, $max_width) {
    $src_w = imagesx($src);
    $src_h = imagesy($src);
    if ($src_w <= 0 || $src_h <= 0) {
        return false;
    }
    $max_width = max(1, (int) $max_width);
    if ($src_w <= $max_width) {
        $dst_w = $src_w;
        $dst_h = $src_h;
    } else {
        $dst_w = $max_width;
        $dst_h = (int) round($src_h * ($max_width / $src_w));
    }
    $dst = imagecreatetruecolor($dst_w, $dst_h);
    if ($dst === false) {
        return false;
    }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    if ($transparent !== false) {
        imagefill($dst, 0, 0, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);

    return $dst;
}

/**
 * @param \GdImage $img
 */
function image_optimizer_save_webp($img, $dest_path, $quality = IMAGE_OPTIMIZER_WEBP_QUALITY) {
    if (!image_optimizer_webp_available()) {
        return false;
    }
    $dir = dirname($dest_path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    return imagewebp($img, $dest_path, max(1, min(100, (int) $quality)));
}

/**
 * Résout un chemin BDD vers le fichier réel (ex. foo.png → foo.webp si converti).
 */
function image_optimizer_resolve_relative_path($relative_path) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '';
    }
    $upload_root = dirname(__DIR__) . '/upload/';
    if (image_optimizer_file_exists($upload_root . $relative_path)) {
        return $relative_path;
    }
    $dir = dirname($relative_path);
    $stem = pathinfo($relative_path, PATHINFO_FILENAME);
    if ($stem === '') {
        return $relative_path;
    }
    $webp_rel = ($dir === '.' || $dir === '') ? $stem . '.webp' : $dir . '/' . $stem . '.webp';
    if (image_optimizer_file_exists($upload_root . $webp_rel)) {
        return $webp_rel;
    }

    return $relative_path;
}

/**
 * Si un .webp existe pour le même nom de base, retourne le chemin .webp.
 */
function image_optimizer_normalize_db_path($relative_path) {
    $resolved = image_optimizer_resolve_relative_path($relative_path);
    $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
    if ($ext === 'webp') {
        return $resolved;
    }
    $upload_root = dirname(__DIR__) . '/upload/';
    $dir = dirname($resolved);
    $stem = pathinfo($resolved, PATHINFO_FILENAME);
    if ($stem === '') {
        return $resolved;
    }
    $webp_rel = ($dir === '.' || $dir === '') ? $stem . '.webp' : $dir . '/' . $stem . '.webp';
    if (image_optimizer_file_exists($upload_root . $webp_rel)) {
        return $webp_rel;
    }

    return $resolved;
}

/**
 * Traite un fichier temporaire : original WebP + variantes _md et _sm.
 *
 * @return array{success:bool, relative_path?:string, filename?:string, message?:string, bytes_before?:int, bytes_after?:int}
 */
function image_optimizer_process_tmp($tmp_path, $dest_dir, $relative_subdir, $name_prefix, $fixed_stem = null) {
    if (!is_uploaded_file($tmp_path) && !is_file($tmp_path)) {
        return ['success' => false, 'message' => 'Fichier source introuvable.'];
    }

    $bytes_before = (int) (@filesize($tmp_path) ?: 0);
    if ($bytes_before <= 0) {
        return ['success' => false, 'message' => 'Fichier vide.'];
    }
    if ($bytes_before > FOUTA_UPLOAD_IMAGE_MAX_BYTES) {
        return ['success' => false, 'message' => 'Image trop volumineuse.'];
    }

    $mime = image_optimizer_detect_mime($tmp_path);
    if ($mime === '' || !in_array($mime, image_optimizer_allowed_mimes(), true)) {
        return ['success' => false, 'message' => 'Format d\'image non supporté.'];
    }

    if (!is_dir($dest_dir) && !mkdir($dest_dir, 0755, true) && !is_dir($dest_dir)) {
        return ['success' => false, 'message' => 'Impossible de créer le dossier d\'upload.'];
    }

    if (is_string($fixed_stem) && $fixed_stem !== '') {
        $safe_stem = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fixed_stem);
        $base_name = ($safe_stem !== '') ? $safe_stem : ($name_prefix . bin2hex(random_bytes(8)));
    } else {
        $base_name = $name_prefix . bin2hex(random_bytes(8));
    }
    $filename = $base_name . '.webp';
    $dest_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    $relative_subdir_norm = trim(str_replace('\\', '/', $relative_subdir), '/');
    $existing_rel = ($relative_subdir_norm !== '' ? $relative_subdir_norm . '/' : '') . $filename;
    if (image_optimizer_is_fully_optimized($existing_rel)) {
        $existing_abs = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $bytes_after = (int) (@filesize($existing_abs) ?: 0);

        return [
            'success' => true,
            'relative_path' => $existing_rel,
            'filename' => $filename,
            'message' => '',
            'bytes_before' => $bytes_before,
            'bytes_after' => $bytes_after,
            'skipped_existing' => true,
        ];
    }

    if (!image_optimizer_webp_available()) {
        return image_optimizer_fallback_move($tmp_path, $dest_dir, $relative_subdir, $mime, $name_prefix, $bytes_before, $fixed_stem);
    }

    $src = image_optimizer_load($tmp_path);
    if ($src === false) {
        return image_optimizer_fallback_move($tmp_path, $dest_dir, $relative_subdir, $mime, $name_prefix, $bytes_before, $fixed_stem);
    }

    $variants = [
        ['suffix' => '', 'width' => IMAGE_OPTIMIZER_MAX_WIDTH],
        ['suffix' => '_md', 'width' => IMAGE_OPTIMIZER_MD_WIDTH],
        ['suffix' => '_sm', 'width' => IMAGE_OPTIMIZER_SM_WIDTH],
    ];

    $saved = [];
    foreach ($variants as $variant) {
        $resized = image_optimizer_resize($src, (int) $variant['width']);
        if ($resized === false) {
            continue;
        }
        $variant_file = $base_name . $variant['suffix'] . '.webp';
        $variant_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $variant_file;
        if (image_optimizer_save_webp($resized, $variant_path)) {
            $saved[] = $variant_file;
        }
        imagedestroy($resized);
    }
    imagedestroy($src);

    if ($saved === []) {
        return ['success' => false, 'message' => 'Échec de la compression WebP.'];
    }

    $bytes_after = 0;
    foreach ($saved as $saved_file) {
        $bytes_after += (int) (@filesize(rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $saved_file) ?: 0);
    }

    $relative_subdir = trim(str_replace('\\', '/', $relative_subdir), '/');
    $relative_path = ($relative_subdir !== '' ? $relative_subdir . '/' : '') . $filename;

    return [
        'success' => true,
        'relative_path' => $relative_path,
        'filename' => $filename,
        'message' => '',
        'bytes_before' => $bytes_before,
        'bytes_after' => $bytes_after,
    ];
}

/**
 * Secours si GD/WebP indisponible : enregistre le fichier original.
 *
 * @return array{success:bool, relative_path?:string, filename?:string, message?:string, bytes_before?:int, bytes_after?:int}
 */
function image_optimizer_fallback_move($tmp_path, $dest_dir, $relative_subdir, $mime, $name_prefix, $bytes_before, $fixed_stem = null) {
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $ext_map[$mime] ?? 'jpg';
    if (is_string($fixed_stem) && $fixed_stem !== '') {
        $safe_stem = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fixed_stem);
        $filename = ($safe_stem !== '' ? $safe_stem : ($name_prefix . bin2hex(random_bytes(8)))) . '.' . $ext;
    } else {
        $filename = $name_prefix . bin2hex(random_bytes(8)) . '.' . $ext;
    }
    $dest_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    $moved = is_uploaded_file($tmp_path)
        ? move_uploaded_file($tmp_path, $dest_path)
        : @rename($tmp_path, $dest_path);

    if (!$moved) {
        return ['success' => false, 'message' => 'Impossible d\'enregistrer l\'image.'];
    }

    $relative_subdir = trim(str_replace('\\', '/', $relative_subdir), '/');

    return [
        'success' => true,
        'relative_path' => ($relative_subdir !== '' ? $relative_subdir . '/' : '') . $filename,
        'filename' => $filename,
        'message' => '',
        'bytes_before' => $bytes_before,
        'bytes_after' => (int) (@filesize($dest_path) ?: $bytes_before),
    ];
}

/**
 * Enregistre et optimise un fichier issu de $_FILES (champ unitaire).
 *
 * @param array<string,mixed> $file_info
 * @return array{success:bool, relative_path?:string, filename?:string, message?:string}
 */
function upload_optimize_image_file(array $file_info, $dest_dir, $relative_subdir, $name_prefix) {
    if (!isset($file_info['error']) || (int) $file_info['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload invalide.'];
    }
    $tmp = (string) ($file_info['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['success' => false, 'message' => 'Fichier temporaire manquant.'];
    }

    return image_optimizer_process_tmp($tmp, $dest_dir, $relative_subdir, $name_prefix);
}

/**
 * URL publique d'une image uploadée avec variante (md catalogue, sm vignette, original fiche).
 *
 * @param string $relative_path Chemin relatif sous upload/ (ex. produits/xxx.webp)
 * @param string $variant original|md|sm
 */
function upload_image_url_from_src($src, $variant = 'md') {
    $src = trim(str_replace('\\', '/', (string) $src), '/');
    if ($src === '') {
        return upload_image_url('', $variant);
    }
    if (strpos($src, 'upload/') === 0) {
        $src = substr($src, 7);
    }

    return upload_image_url($src, $variant);
}

function upload_image_url($relative_path, $variant = 'md') {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '/image/produit1.jpg';
    }

    $relative_path = image_optimizer_resolve_relative_path($relative_path);
    $upload_root = dirname(__DIR__) . '/upload/';

    $variant = strtolower(trim((string) $variant));
    if ($variant === '' || $variant === 'original') {
        return '/upload/' . $relative_path;
    }
    if (!in_array($variant, ['md', 'sm'], true)) {
        $variant = 'md';
    }

    $variant_rel = image_optimizer_variant_relative_path($relative_path, $variant);
    if (image_optimizer_file_exists($upload_root . $variant_rel)) {
        return '/upload/' . $variant_rel;
    }
    if (image_optimizer_file_exists($upload_root . $relative_path)) {
        return '/upload/' . $relative_path;
    }

    return '/upload/' . $relative_path;
}

/**
 * @return string
 */
function image_optimizer_variant_relative_path($relative_path, $variant) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '';
    }
    $dir = dirname($relative_path);
    $base = pathinfo($relative_path, PATHINFO_FILENAME);
    $variant_name = $base . '_' . $variant . '.webp';
    if ($dir === '.' || $dir === '') {
        return $variant_name;
    }

    return $dir . '/' . $variant_name;
}

/**
 * Supprime une image et ses variantes _md / _sm.
 */
function image_optimizer_delete_with_variants($relative_path) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return;
    }
    $upload_root = dirname(__DIR__) . '/upload/';
    $paths = [$relative_path];
    foreach (['md', 'sm'] as $variant) {
        $variant_rel = image_optimizer_variant_relative_path($relative_path, $variant);
        if ($variant_rel !== '') {
            $paths[] = $variant_rel;
        }
    }
    $ext = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));
    if ($ext !== 'webp') {
        $dir = dirname($relative_path);
        $stem = pathinfo($relative_path, PATHINFO_FILENAME);
        if ($stem !== '') {
            foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $old_ext) {
                $legacy = ($dir === '.' || $dir === '') ? $stem . '.' . $old_ext : $dir . '/' . $stem . '.' . $old_ext;
                $paths[] = $legacy;
            }
        }
    }
    foreach (array_unique($paths) as $rel) {
        $abs = $upload_root . $rel;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}

/**
 * Supprime une image slider (nom de fichier seul en BDD).
 */
function image_optimizer_delete_slider_file($filename) {
    $filename = basename(trim(str_replace('\\', '/', (string) $filename)));
    if ($filename === '') {
        return;
    }
    image_optimizer_delete_with_variants('slider/' . $filename);
}
