<?php
/**
 * LA VITRINE CLIENT D'UNE PIÈCE — le circuit du QR de l'étiquette (04/09/2026).
 *
 * Décision de la direction : sur l'étiquette de pièce, le QR et le code-barres
 * portent LE MÊME contenu — le numéro EAN-13 de la pièce (200 + les 9 chiffres
 * de l'identifiant FPL + la clé). Le code-barres le donne nu à la douchette ;
 * le QR le place dans le lien /p/{ean13} pour que le téléphone du client
 * ouvre la page vitrine. Ce contenu est exclusivement destiné aux clients :
 * rien d'interne (ni stock, ni emplacement, ni prix d'achat) n'y transite.
 *
 * Ici vivent les trois gestes partagés par l'étiquette, la page p.php et la
 * redirection des anciens QR (stock-info.php) :
 *   - fpl_vitrine_base_url()          : l'adresse PUBLIQUE gravée dans les QR ;
 *   - fpl_vitrine_ean13_pour_produit(): le numéro commun aux deux codes ;
 *   - produit_vitrine_url()           : l'URL complète encodée dans le QR.
 */

require_once __DIR__ . '/site_url.php';

/**
 * L'adresse de base gravée dans les QR imprimés. Une étiquette collée est un
 * engagement : cette adresse doit être joignable par le téléphone d'un client,
 * donc jamais une IP de réseau local. Priorité à la clé 'vitrine_url' de
 * config/site.php (le domaine public), sinon site_url, sinon l'hôte courant.
 *
 * @return string URL sans slash final
 */
function fpl_vitrine_base_url() {
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $url = '';
    if (file_exists(__DIR__ . '/../config/site.php')) {
        $config = require __DIR__ . '/../config/site.php';
        $url = trim((string) ($config['vitrine_url'] ?? ''));
    }
    if ($url === '') {
        $url = get_site_base_url();
    }

    $base = rtrim($url, '/');
    return $base;
}

/**
 * Les 13 chiffres communs au code-barres et au QR : la composition vit dans le
 * moteur d'étiquette (etiquette70_ean12_pour_identifiant + la clé de
 * etiquette70_ean13) — on la réutilise, jamais on ne la recopie.
 *
 * @param array<string, mixed> $produit Doit porter identifiant_interne (et id en repli)
 * @return string 13 chiffres
 */
function fpl_vitrine_ean13_pour_produit(array $produit) {
    if (!function_exists('etiquette70_ean12_pour_identifiant')) {
        require_once __DIR__ . '/etiquette_fpl70.php';
    }
    $identifiant = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
    $douze = etiquette70_ean12_pour_identifiant($identifiant, (int) ($produit['id'] ?? 0));
    $ean = etiquette70_ean13($douze);

    return (string) $ean['chiffres'];
}

/**
 * L'URL encodée dans le QR de l'étiquette de pièce.
 *
 * @param array<string, mixed> $produit
 * @return string
 */
function produit_vitrine_url(array $produit) {
    return fpl_vitrine_base_url() . '/p/' . fpl_vitrine_ean13_pour_produit($produit);
}

/**
 * Résout ce que porte l'URL /p/{code} vers un identifiant FPL cherchable :
 * accepte les 13 chiffres du code-barres (clé vérifiée), l'identifiant FPL
 * lui-même, avec espaces et casse indifférentes — le client tape ce qu'il voit.
 *
 * @param string $brut
 * @return string Identifiant FPL (FPL + 6 ou 9 chiffres), ou '' si inconnu
 */
function fpl_vitrine_code_vers_identifiant($brut) {
    require_once __DIR__ . '/produit_emplacement_entrepot.php';
    $code = strtoupper(str_replace(' ', '', trim((string) $brut)));
    if ($code === '') {
        return '';
    }
    /* L'EAN-13 du code-barres (200 + 9 chiffres de l'identifiant + clé) —
       décodé ICI directement, sans dépendre de produit_emplacement_extraire_
       fpl_du_scan : la vitrine reste autonome (utile là où ce décodage n'a pas
       encore été déployé). Clé vérifiée pour ne pas confondre avec un vrai EAN
       du commerce. C'est CE numéro que le QR encode dans /p/{ean13}. */
    if (preg_match('/^200(\d{9})(\d)$/', $code, $m)) {
        $somme = 0;
        for ($i = 0; $i < 12; $i++) {
            $somme += ((int) $code[$i]) * ($i % 2 === 0 ? 1 : 3);
        }
        if ((int) $m[2] === (10 - $somme % 10) % 10) {
            return 'FPL' . $m[1];
        }
    }
    $code = produit_emplacement_extraire_fpl_du_scan($code);
    if (preg_match('/^FPL\d{6}(\d{3})?$/', $code)) {
        return $code;
    }
    /* Les 9 chiffres nus (l'identifiant sans son préfixe) : on les habille. */
    if (preg_match('/^\d{9}$/', $code)) {
        return 'FPL' . $code;
    }

    return '';
}

/**
 * LES PHOTOS D'UNE PIÈCE POUR LA VITRINE (11/09/2026) : la principale, puis la
 * galerie, sinon la photo de l'étiquette. Chemins relatifs à upload/, fichiers
 * présents seulement, sans doublon. /p/{code} et /p-photo.php lisent la MÊME
 * liste : le rang d'une photo désigne le même fichier des deux côtés.
 *
 * @param array<string, mixed> $piece colonnes image_principale, images, image_etiquette_fpl
 * @return array<int, string>
 */
function fpl_vitrine_photos_chemins(array $piece, $dossier_upload)
{
    $bruts = [];
    if (!empty($piece['image_principale'])) {
        $bruts[] = (string) $piece['image_principale'];
    }
    $galerie = json_decode((string) ($piece['images'] ?? ''), true);
    if (is_array($galerie)) {
        foreach ($galerie as $g) {
            if (is_string($g) && $g !== '') {
                $bruts[] = $g;
            }
        }
    }
    if ($bruts === [] && !empty($piece['image_etiquette_fpl'])) {
        $bruts[] = (string) $piece['image_etiquette_fpl'];
    }
    $chemins = [];
    foreach ($bruts as $chemin) {
        $chemin = ltrim(str_replace(chr(92), '/', $chemin), '/');
        if ($chemin === '' || strpos($chemin, '..') !== false || in_array($chemin, $chemins, true)) {
            continue;
        }
        if (is_file(rtrim((string) $dossier_upload, '/') . '/' . $chemin)) {
            $chemins[] = $chemin;
        }
    }
    return $chemins;
}

/**
 * LA PIÈCE SANS SON FOND, POUR LE TÉLÉPHONE (11/09/2026). Même détourage que
 * l'étiquette (etiquette70_photo_detouree, donc son cache), recadré sur la
 * matière, réduit à $taille px et gardé en WebP avec transparence à côté du
 * cache du détourage. null quand le détourage refuse la photo (fond chargé) :
 * la vitrine montre alors la photo d'origine.
 *
 * @return string|null chemin du fichier WebP
 */
function fpl_vitrine_photo_detouree_fichier($source, $taille)
{
    if (!is_file($source) || !function_exists('imagewebp')) {
        return null;
    }
    $taille = max(64, (int) $taille);
    $dossier = __DIR__ . '/../upload/detour_cache/vitrine';
    $cle = md5(realpath($source) . '|' . filemtime($source) . '|v14|' . $taille);
    $fichier = $dossier . '/' . $cle . '.webp';
    $refus = $dossier . '/' . $cle . '.non';
    if (is_file($fichier)) {
        return $fichier;
    }
    if (is_file($refus)) {
        return null;
    }
    if (!is_dir($dossier)) {
        @mkdir($dossier, 0775, true);
    }
    require_once __DIR__ . '/etiquette_fpl70.php';
    $photo = etiquette70_photo_detouree($source);
    if ($photo === null || empty($photo['detouree'])) {
        if ($photo !== null) {
            imagedestroy($photo['img']);
        }
        @file_put_contents($refus, '1');
        return null;
    }
    $src = $photo['img'];
    $sx = 0;
    $sy = 0;
    $sw = imagesx($src);
    $sh = imagesy($src);
    $vis = etiquette70_boite_visible($src);
    if ($vis !== null) {
        $sx = $vis['x0'];
        $sy = $vis['y0'];
        $sw = $vis['x1'] - $vis['x0'] + 1;
        $sh = $vis['y1'] - $vis['y0'] + 1;
    }
    $echelle = min(1.0, $taille / max($sw, $sh));
    $dw = max(1, (int) round($sw * $echelle));
    $dh = max(1, (int) round($sh * $echelle));
    $marge = (int) round(max($dw, $dh) * 0.04);
    $dst = imagecreatetruecolor($dw + 2 * $marge, $dh + 2 * $marge);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefilledrectangle($dst, 0, 0, $dw + 2 * $marge, $dh + 2 * $marge, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    imagecopyresampled($dst, $src, $marge, $marge, $sx, $sy, $dw, $dh, $sw, $sh);
    imagedestroy($src);
    $provisoire = $fichier . '.' . uniqid('', true) . '.tmp';
    $ok = @imagewebp($dst, $provisoire, 86);
    imagedestroy($dst);
    if (!$ok || !@rename($provisoire, $fichier)) {
        @unlink($provisoire);
        return is_file($fichier) ? $fichier : null;
    }
    return $fichier;
}
