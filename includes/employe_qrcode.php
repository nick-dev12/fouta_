<?php
/**
 * Génération du QR badge employé (chillerlan/php-qrcode — même pattern que controller_produits).
 */

/**
 * Payload stable pour scan (sans secret — identification interne + nom affiché).
 */
function employe_build_qr_payload(array $employe_row) {
    $id = (int) ($employe_row['id'] ?? 0);
    $prenom = trim((string) ($employe_row['prenom'] ?? ''));
    $nom = trim((string) ($employe_row['nom'] ?? ''));
    $matricule = '';
    if ($id > 0) {
        require_once __DIR__ . '/../models/model_employe_matricules.php';
        $m = employe_matricule_par_employe_id($id);
        if ($m !== false) {
            $matricule = (string) $m;
        }
    }
    return json_encode([
        'app'      => 'fouta-pl-rh',
        'kind'     => 'employee',
        'id'       => $id,
        'matricule'=> $matricule,
        'fullname' => trim($prenom . ' ' . $nom),
        'tel'      => (isset($employe_row['telephone']) && $employe_row['telephone'] !== null && $employe_row['telephone'] !== '')
            ? (string) $employe_row['telephone'] : '',
        'role'     => $employe_row['poste'] !== null ? (string) $employe_row['poste'] : '',
        'generated_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Génère PNG + met à jour employes.qr_chemin / qr_payload
 * @return bool
 */
function employe_generer_et_sauver_qrcode($employe_id) {
    $employe_id = (int) $employe_id;
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }
    require_once $autoload;
    require_once __DIR__ . '/../models/model_employes.php';

    $emp = get_employe_by_id($employe_id);
    if (!$emp) {
        return false;
    }

    $payload = employe_build_qr_payload($emp);
    $dir = __DIR__ . '/../upload/employes_qr/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $filename = 'employe_' . $employe_id . '.png';
    $basename = $dir . $filename;
    $relative = 'employes_qr/' . $filename;

    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale'        => 8,
            'outputBase64' => false,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $qr->render($payload, $basename);
    } catch (Throwable $e) {
        return false;
    }

    if (!file_exists($basename)) {
        return false;
    }

    return employe_update_qr_fields($employe_id, $relative, $payload);
}
