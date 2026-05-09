<?php
/**
 * Limite unique pour les uploads d’images (formulaires MAX_FILE_SIZE + validations PHP).
 * Aligner php.ini : upload_max_filesize et post_max_size ≥ 30M si besoin.
 */
declare(strict_types=1);

if (!defined('FOUTA_UPLOAD_IMAGE_MAX_BYTES')) {
    define('FOUTA_UPLOAD_IMAGE_MAX_BYTES', 30 * 1024 * 1024);
}
