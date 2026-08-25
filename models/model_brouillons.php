<?php
/**
 * LES BROUILLONS — la sauvegarde au fil de l'eau : chaque champ saisi part
 * en base quelques centaines de millisecondes après la frappe. Quitter la
 * page et revenir ne fait rien perdre — même sans avoir cliqué
 * « Enregistrer ». Le brouillon s'efface quand le vrai enregistrement aboutit.
 * Programmation procédurale uniquement
 *
 * Portage de fpl_natif/models/model_brouillons.php (table `brouillons`,
 * créée par migrations/2026_08_23_wizard_piece_brouillons.sql). Les trois
 * fonctions sont TOLÉRANTES : sans la table, elles ne font rien et ne
 * cassent pas la page.
 */

require_once __DIR__ . '/../conn/conn.php';

/** La table est-elle là ? (vérifié une fois par requête) */
function brouillons_table_ok()
{
    static $ok = null;
    global $db;

    if ($ok === null) {
        $ok = false;
        try {
            $s = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brouillons'");
            $ok = (int) $s->fetchColumn() > 0;
        } catch (PDOException $e) {
            $ok = false;
        }
    }

    return $ok;
}

/** Le brouillon d'un utilisateur pour un formulaire donné, ou null. */
function brouillon_lire($admin_id, $cle)
{
    global $db;

    if (!brouillons_table_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare("SELECT contenu, date_modification FROM brouillons
                              WHERE admin_id = :admin AND cle = :cle LIMIT 1");
        $stmt->execute(['admin' => (int) $admin_id, 'cle' => (string) $cle]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ligne) {
            return null;
        }

        return [
            'payload' => $ligne['contenu'] !== null ? json_decode($ligne['contenu'], true) : null,
            'depuis' => $ligne['date_modification'],
        ];
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Écrit (ou met à jour) le brouillon. Jamais de secret dans un brouillon :
 * les jetons et mots de passe sont écartés.
 */
function brouillon_sauver($admin_id, $cle, $payload)
{
    global $db;

    if (!brouillons_table_ok()) {
        return false;
    }
    unset($payload['_token'], $payload['_jeton'], $payload['csrf_token'],
          $payload['password'], $payload['password_confirmation']);

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare("INSERT INTO brouillons (admin_id, cle, contenu, date_creation, date_modification)
                          VALUES (:admin, :cle, :contenu, NOW(), NOW())
                          ON DUPLICATE KEY UPDATE contenu = :contenu2, date_modification = NOW()");

    return $stmt->execute([
        'admin' => (int) $admin_id, 'cle' => (string) $cle,
        'contenu' => $json, 'contenu2' => $json,
    ]);
}

/** L'enregistrement réel a abouti (ou l'utilisateur abandonne) : purge. */
function brouillon_purger($admin_id, $cle)
{
    global $db;

    if (!$admin_id || $cle === null || $cle === '' || !brouillons_table_ok()) {
        return;
    }

    $stmt = $db->prepare("DELETE FROM brouillons WHERE admin_id = :admin AND cle = :cle");
    $stmt->execute(['admin' => (int) $admin_id, 'cle' => (string) $cle]);
}
