<?php
/**
 * Interface admin — synchronisation des données.
 * Accès : informaticien / développeur / admin (via liste blanche routes).
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/sync_functions.php';

global $db;

$message = '';
$error = '';
$last_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['sync_action'] ?? '';
    try {
        if (!$db instanceof PDO) {
            throw new RuntimeException('Connexion base indisponible.');
        }
        ini_set('max_execution_time', '600');
        $config = sync_load_config();

        if ($action === 'pull' && !sync_direction_allows_pull($config)) {
            throw new RuntimeException('Pull désactivé : mode sync local → VPS uniquement (push_only).');
        }
        if ($action === 'push' && !sync_direction_allows_push($config)) {
            throw new RuntimeException('Push désactivé : mode sync VPS → local uniquement (pull_only).');
        }

        switch ($action) {
            case 'pull':
                $last_result = sync_pull($db, $config, false);
                $message = 'Pull terminé : ' . (int) ($last_result['records'] ?? 0) . ' enregistrement(s), '
                    . (int) ($last_result['conflicts'] ?? 0) . ' conflit(s).';
                break;
            case 'push':
                $last_result = sync_push($db, $config, false);
                $message = 'Push terminé : ' . (int) ($last_result['records'] ?? 0) . ' enregistrement(s), '
                    . (int) ($last_result['conflicts'] ?? 0) . ' conflit(s).';
                break;
            case 'run':
                $last_result = sync_local_to_vps($db, $config, false);
                $push_n = (int) ($last_result['push']['records'] ?? 0);
                $skip_n = (int) ($last_result['push']['skipped'] ?? 0);
                $files_n = (int) ($last_result['files']['files_pushed'] ?? 0);
                $files_skip = (int) ($last_result['files']['files_skipped'] ?? 0);
                $message = "Sync terminée : $push_n enregistrement(s) envoyé(s), $skip_n déjà à jour en BDD, "
                    . "$files_n fichier(s) envoyé(s), $files_skip fichier(s) déjà sur le VPS.";
                break;
            case 'files':
                $last_result = sync_files_push($db, $config, false);
                $message = 'Fichiers : ' . (int) ($last_result['files_pushed'] ?? 0) . ' envoyé(s), '
                    . (int) ($last_result['files_skipped'] ?? 0) . ' déjà à jour.';
                break;
            case 'ping':
                $last_result = sync_remote_request('ping', [], $config);
                $message = 'Connexion OK — nœud distant : ' . ($last_result['node_id'] ?? 'inconnu');
                break;
            default:
                throw new InvalidArgumentException('Action inconnue.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$logs = [];
$config_info = [];
$sync_allows_pull = false;
$sync_allows_push = true;
try {
    if ($db instanceof PDO) {
        sync_ensure_infrastructure($db);
        $logs = sync_get_recent_logs($db, 30);
        $config = sync_load_config();
        $config_info = [
            'node_id' => $config['node_id'] ?? '',
            'remote_url' => $config['remote_url'] ?? '',
            'sync_direction' => sync_direction_label($config),
            'last_pull' => sync_get_state($db, 'last_pull_since', '—'),
            'last_push' => sync_get_state($db, 'last_push_since', '—'),
            'tables' => count(sync_registry_sort_tables($db, $config)),
        ];
        $sync_allows_pull = sync_direction_allows_pull($config);
        $sync_allows_push = sync_direction_allows_push($config);
    }
} catch (Throwable $e) {
    $error = $error ?: $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Synchronisation - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <style>
        .sync-card { background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 20px rgba(53,100,166,.12); }
        .sync-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin: 1rem 0; }
        .sync-actions button { padding: .65rem 1rem; border: none; border-radius: 8px; cursor: pointer; background: #3564a6; color: #fff; }
        .sync-actions button.secondary { background: #FF6B35; }
        .sync-meta { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 1rem; }
        .sync-meta dt { font-weight: 700; color: #3564a6; }
        .sync-log-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .sync-log-table th, .sync-log-table td { padding: .5rem; border-bottom: 1px solid #eee; text-align: left; }
        .message.success { background: rgba(53,100,166,.12); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .message.error { background: rgba(255,107,53,.12); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        pre.result { background: #f5f5f5; padding: 1rem; border-radius: 8px; overflow: auto; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/nav.php'; ?>

<section class="produits-section" style="padding: 2rem;">
    <h1><i class="fas fa-sync-alt"></i> Synchronisation des données</h1>
    <p>Envoi des données locales vers le serveur distant (VPS) — mode configurable dans <code>config/sync.php</code>.</p>

    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="sync-card">
        <h2>Configuration actuelle</h2>
        <?php if ($config_info): ?>
            <dl class="sync-meta">
                <div><dt>Direction</dt><dd><?php echo htmlspecialchars($config_info['sync_direction']); ?></dd></div>
                <div><dt>Nœud local</dt><dd><?php echo htmlspecialchars($config_info['node_id']); ?></dd></div>
                <div><dt>URL distante</dt><dd><?php echo htmlspecialchars($config_info['remote_url']); ?></dd></div>
                <?php if (!empty($sync_allows_pull)): ?>
                <div><dt>Dernier pull</dt><dd><?php echo htmlspecialchars($config_info['last_pull']); ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($sync_allows_push)): ?>
                <div><dt>Dernier push</dt><dd><?php echo htmlspecialchars($config_info['last_push']); ?></dd></div>
                <?php endif; ?>
                <div><dt>Tables sync</dt><dd><?php echo (int) $config_info['tables']; ?></dd></div>
            </dl>
        <?php else: ?>
            <p>Copiez <code>config/sync.example.php</code> vers <code>config/sync.php</code> et configurez le token.</p>
        <?php endif; ?>

        <form method="post" class="sync-actions">
            <button type="submit" name="sync_action" value="ping">Tester connexion</button>
            <?php if (!empty($sync_allows_pull)): ?>
            <button type="submit" name="sync_action" value="pull">Pull (distant → local)</button>
            <?php endif; ?>
            <?php if (!empty($sync_allows_push)): ?>
            <button type="submit" name="sync_action" value="push">Push BDD uniquement</button>
            <button type="submit" name="sync_action" value="run">Sync complète (BDD + images)</button>
            <button type="submit" name="sync_action" value="files" class="secondary">Images / fichiers uniquement</button>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($last_result): ?>
        <div class="sync-card">
            <h2>Dernier résultat</h2>
            <pre class="result"><?php echo htmlspecialchars(json_encode($last_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
        </div>
    <?php endif; ?>

    <div class="sync-card">
        <h2>Journal récent</h2>
        <?php if ($logs): ?>
            <table class="sync-log-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Direction</th>
                        <th>Table</th>
                        <th>Enreg.</th>
                        <th>Conflits</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($log['direction']); ?></td>
                            <td><?php echo htmlspecialchars($log['table_name'] ?? '—'); ?></td>
                            <td><?php echo (int) $log['records_count']; ?></td>
                            <td><?php echo (int) $log['conflicts_count']; ?></td>
                            <td><?php echo htmlspecialchars($log['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun journal pour le moment.</p>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
