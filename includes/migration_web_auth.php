<?php
/**
 * Accès aux scripts migrations/outils via navigateur (hébergement mutualisé).
 *
 * Autorisé si :
 * - session admin active, OU
 * - paramètre ?token= identique à MIGRATION_WEB_TOKEN (définir dans conn/conn.php).
 *
 * Exemple conn.php :
 *   define('MIGRATION_WEB_TOKEN', 'votre-cle-secrete-longue');
 */

if (!function_exists('migration_web_is_authorized')) {
    function migration_web_is_authorized() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!empty($_SESSION['admin_id']) && !empty($_SESSION['admin_email'])) {
            return true;
        }

        $expected = '';
        if (defined('MIGRATION_WEB_TOKEN')) {
            $expected = (string) MIGRATION_WEB_TOKEN;
        }

        if ($expected !== '' && isset($_GET['token'])) {
            return hash_equals($expected, (string) $_GET['token']);
        }

        return false;
    }
}

if (!function_exists('migration_web_require_auth')) {
    function migration_web_require_auth() {
        if (migration_web_is_authorized()) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Accès refusé</title></head><body>';
        echo '<h1>Accès refusé</h1>';
        echo '<p>Connectez-vous à l’administration ou ajoutez <code>?token=…</code> si <code>MIGRATION_WEB_TOKEN</code> est défini dans <code>conn/conn.php</code>.</p>';
        echo '</body></html>';
        exit;
    }
}

if (!function_exists('migration_web_preserve_query')) {
    /**
     * @param array<string, scalar|null> $overrides
     */
    function migration_web_preserve_query($overrides = []) {
        $params = $_GET;
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }

        return http_build_query($params);
    }
}

if (!function_exists('migration_web_render_page')) {
    /**
     * @param string $title
     * @param string $body_html
     */
    function migration_web_render_page($title, $body_html, $head_extra = '') {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="fr"><head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        if ($head_extra !== '') {
            echo $head_extra;
        }
        echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>
            body{font-family:system-ui,sans-serif;max-width:52rem;margin:2rem auto;padding:0 1rem;line-height:1.5;color:#0d0d0d}
            h1{font-size:1.35rem;margin-bottom:.5rem}
            .meta{color:#737373;font-size:.95rem;margin-bottom:1.25rem}
            pre{background:#f5f5f5;border:1px solid rgba(0,0,0,.08);padding:1rem;overflow:auto;font-size:.85rem;border-radius:6px}
            .actions{margin:1.25rem 0;display:flex;flex-wrap:wrap;gap:.5rem}
            .actions a{display:inline-block;padding:.45rem .85rem;background:#3564a6;color:#fff;text-decoration:none;border-radius:6px;font-size:.9rem}
            .actions a.secondary{background:#fff;color:#3564a6;border:1px solid rgba(53,100,166,.35)}
            .ok{color:#2d5690}.err{color:#e85a2a}.warn{color:#737373}
            ul.links{list-style:none;padding:0;margin:1rem 0}
            ul.links li{margin:.35rem 0}
            ul.links a{color:#3564a6}
            .stats ul{list-style:none;padding:0;margin:0 0 1rem}
            .stats li{margin:.25rem 0}
        </style></head><body>';
        echo $body_html;
        echo '</body></html>';
    }
}
