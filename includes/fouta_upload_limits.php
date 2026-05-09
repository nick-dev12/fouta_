<?php
/**
 * Limite unique pour les uploads d’images (MAX_FILE_SIZE + validations PHP).
 * 30 Mo pour tout le projet (produits, catégories, slider, logos, absences, employés…).
 * Aligner php.ini : upload_max_filesize et post_max_size ≥ 30M.
 */
declare(strict_types=1);

if (!defined('FOUTA_UPLOAD_IMAGE_MAX_BYTES')) {
    define('FOUTA_UPLOAD_IMAGE_MAX_BYTES', 30 * 1024 * 1024);
}

/**
 * Taille max images (Mo, entier) pour les libellés d’aide et messages d’erreur.
 */
function fouta_upload_image_max_mo_int(): int
{
    return (int) (FOUTA_UPLOAD_IMAGE_MAX_BYTES / (1024 * 1024));
}

/**
 * Message standard : dépassement côté PHP (ini) ou formulaire.
 */
function fouta_upload_image_err_ini_ou_limite(): string
{
    $m = fouta_upload_image_max_mo_int();
    return 'Fichier trop volumineux (max. ' . $m . ' Mo). Vérifiez upload_max_filesize et post_max_size dans php.ini, puis redémarrez Apache.';
}
