<?php
/**
 * Préparation + fragment HTML carte RH (aperçu fiche + impression).
 */
require_once __DIR__ . '/../models/model_employes.php';
require_once __DIR__ . '/../models/model_employe_matricules.php';
require_once __DIR__ . '/employe_qrcode.php';
require_once __DIR__ . '/site_url.php';

/**
 * Agrège données pour carte + QR + photo pour un employé.
 *
 * @return array<string,mixed>|false
 */
function employes_carte_rh_preparer_variables($employe_id) {
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }

    $upload_disk_root = __DIR__ . '/../upload/';
    $upload_public = rtrim(get_request_origin_base_url(), '/') . '/upload/';
    $f = get_employe_by_id($employe_id);
    if (!$f) {
        return false;
    }

    $matricule = employe_matricule_assigner_si_absent($employe_id);
    if ($matricule === false) {
        $matricule = '—';
    }

    $qr_rel = $f['qr_chemin'] ?? '';
    if ($qr_rel !== '' && !is_file($upload_disk_root . str_replace('/', DIRECTORY_SEPARATOR, $qr_rel))) {
        employe_generer_et_sauver_qrcode($employe_id);
        $f = get_employe_by_id($employe_id);
        $qr_rel = $f['qr_chemin'] ?? '';
    } elseif ($qr_rel === '') {
        employe_generer_et_sauver_qrcode($employe_id);
        $f = get_employe_by_id($employe_id);
        $qr_rel = $f['qr_chemin'] ?? '';
    }

    $photo_rel = trim((string) ($f['photo_chemin'] ?? ''));
    $photo_disk_ok = $photo_rel !== '' && strpos($photo_rel, '..') === false
        && is_file($upload_disk_root . str_replace('/', DIRECTORY_SEPARATOR, $photo_rel));

    $carte_logo_url = get_site_logo_url_for_current_request();

    return [
        'employe_id'      => $employe_id,
        'f'               => $f,
        'matricule'       => $matricule,
        'upload_public'   => $upload_public,
        'upload_disk'     => $upload_disk_root,
        'photo_rel'       => $photo_rel,
        'photo_disk_ok'   => $photo_disk_ok,
        'qr_rel'          => $qr_rel,
        'qr_disk_ok'      => $qr_rel !== '' && strpos($qr_rel, '..') === false
            && is_file($upload_disk_root . str_replace('/', DIRECTORY_SEPARATOR, $qr_rel)),
        'carte_logo_url' => $carte_logo_url,
    ];
}

/**
 * @param array<string,mixed> $v Résultat de employes_carte_rh_preparer_variables()
 * @param string $modifier_classes classes additionnelles sur .er-carte-rh (facultatif, ex. variante imprimée)
 */
function employes_carte_rh_rendre_html(array $v, $modifier_classes = '') {
    $f = $v['f'] ?? [];
    $employe_id = (int) ($v['employe_id'] ?? 0);
    if (!is_array($f) || $employe_id <= 0) {
        return '';
    }
    $matricule = (string) ($v['matricule'] ?? '—');
    $upload_public = (string) ($v['upload_public'] ?? '');
    $photo_rel = (string) ($v['photo_rel'] ?? '');
    $photo_disk_ok = !empty($v['photo_disk_ok']);
    $qr_rel = (string) ($v['qr_rel'] ?? '');
    $qr_disk_ok = !empty($v['qr_disk_ok']);
    $carte_logo_url = (string) ($v['carte_logo_url'] ?? '');
    $extra = trim((string) $modifier_classes) !== ''
        ? ' ' . htmlspecialchars(trim($modifier_classes), ENT_QUOTES, 'UTF-8') : '';
    ob_start();
    ?>
    <div class="er-carte-rh<?php echo $extra; ?>">
        <header class="er-carte-rh__head">
            <div class="er-carte-rh__mark" title="Logo">
                <?php if (!empty($carte_logo_url)): ?>
                    <img src="<?php echo htmlspecialchars((string) $carte_logo_url); ?>" alt="Logo FOUTA" width="90" height="90" decoding="async">
                <?php else: ?>
                    <i class="fas fa-building" aria-hidden="true"></i>
                <?php endif; ?>
            </div>
            <div class="er-carte-rh__head-center">
                <p class="er-carte-rh__brand">FOUTA POIDS LOURDS</p>
                <p class="er-carte-rh__tagline">the solution</p>
                <p class="er-carte-rh__type">Carte d’identité employé</p>
            </div>
            <div class="er-carte-rh__mark er-carte-rh__mark--dashed" aria-hidden="true">
                <i class="fas fa-id-card-clip"></i>
            </div>
        </header>
        <div class="er-carte-rh__body">
            <div class="er-carte-rh__photo-col">
                <div class="er-carte-rh__photo-box">
                    <?php if (!empty($photo_disk_ok)): ?>
                        <img src="<?php echo htmlspecialchars((string) $upload_public . (string) $photo_rel); ?>" alt="" decoding="async">
                    <?php else: ?>
                        <span class="er-carte-rh__photo-placeholder"><i class="fas fa-user" aria-hidden="true"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="er-carte-rh__fields">
                <div class="er-carte-rh__field-line">
                    <div class="er-carte-rh__col">
                        <div class="er-carte-rh__row">
                            <span class="er-carte-rh__k">Nom :</span>
                            <span class="er-carte-rh__v"><?php echo htmlspecialchars((string) ($f['nom'] ?? '—')); ?></span>
                        </div>
                        <div class="er-carte-rh__row">
                            <span class="er-carte-rh__k">Prénom :</span>
                            <span class="er-carte-rh__v"><?php echo htmlspecialchars((string) ($f['prenom'] ?? '—')); ?></span>
                        </div>
                    </div>
                    <div class="er-carte-rh__col">
                        <div class="er-carte-rh__row">
                            <span class="er-carte-rh__k">Téléphone :</span>
                            <span class="er-carte-rh__v"><?php echo !empty($f['telephone']) ? htmlspecialchars((string) $f['telephone']) : '—'; ?></span>
                        </div>
                        <div class="er-carte-rh__row">
                            <span class="er-carte-rh__k">Fonction :</span>
                            <span class="er-carte-rh__v"><?php echo !empty($f['poste']) ? htmlspecialchars((string) $f['poste']) : '—'; ?></span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($f['date_embauche'])): ?>
                <div class="er-carte-rh__field-line er-carte-rh__field-line--full">
                    <div class="er-carte-rh__row">
                        <span class="er-carte-rh__k">Embauche :</span>
                        <span class="er-carte-rh__v"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $f['date_embauche']))); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="er-carte-rh__qr-col">
                <div class="er-carte-rh__qr-box">
                    <?php if (!empty($qr_disk_ok)): ?>
                        <img src="<?php echo htmlspecialchars((string) $upload_public . (string) $qr_rel); ?>" alt="" class="er-carte-rh__qr-img" decoding="async">
                    <?php else: ?>
                        <span class="er-carte-rh__qr-miss"><i class="fas fa-qrcode" aria-hidden="true"></i><span>QR indispo.</span></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <footer class="er-carte-rh__foot">
            <span><strong>MAT. : <?php echo htmlspecialchars((string) $matricule); ?></strong></span>
            <span class="er-carte-rh__foot-by">Imprimé par / FPL</span>
        </footer>
    </div>
    <?php
    return trim((string) ob_get_clean());
}
