<?php
/**
 * Envoi fiable de PDF binaires (admin) — évite sorties parasites et boucles de téléchargement.
 */

if (!function_exists('admin_pdf_request_begin')) {

    /**
     * À appeler en tout début de script PDF (avant session et includes).
     */
    function admin_pdf_request_begin() {
        if (ob_get_level() === 0) {
            ob_start();
        }
        if (function_exists('ini_set')) {
            @ini_set('display_errors', '0');
            @ini_set('zlib.output_compression', '0');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
    }

    /**
     * Vide tous les tampons de sortie (warnings, espaces, HTML accidentel).
     */
    function admin_pdf_clean_output_buffers() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * @param string $binary
     * @param string $filename_base Nom sans chemin ( .pdf ajouté si absent )
     * @return bool false si en-têtes déjà envoyés
     */
    function admin_pdf_send_binary($binary, $filename_base) {
        admin_pdf_clean_output_buffers();

        if (headers_sent()) {
            return false;
        }

        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) $filename_base);
        $safe = trim((string) $safe, '-');
        if ($safe === '') {
            $safe = 'document';
        }
        if (strtolower(substr($safe, -4)) !== '.pdf') {
            $safe .= '.pdf';
        }

        $len = strlen($binary);
        if ($len <= 0) {
            return false;
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Content-Length: ' . $len);
        header('Cache-Control: private, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        echo $binary;
        exit;
    }

    /**
     * @param string $title
     * @param string $message
     * @param string $back_href
     * @param string $back_label
     */
    function admin_pdf_send_error_html($title, $message, $back_href, $back_label = 'Retour') {
        admin_pdf_clean_output_buffers();
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>';
        echo '<body style="font-family:sans-serif;padding:24px;max-width:640px;margin:auto">';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($back_href !== '') {
            echo '<p><a href="' . htmlspecialchars($back_href, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($back_label, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }
        echo '</body></html>';
        exit;
    }
}
