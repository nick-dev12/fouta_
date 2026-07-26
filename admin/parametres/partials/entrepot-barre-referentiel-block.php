<?php
/**
 * Bloc édition barre (QR, nom, positions) — partial référentiel étage.
 *
 * Variables attendues :
 * - $b (array barre), $etage (array), $rayon (array|null)
 * - $origin_et, $barre_etiq_css
 * - $lie_barre_champ (array|null), $lie_barre_elements (array)
 * - $champ_element_fixe (int|null) ID fixe ou null pour select / aucun
 * - $afficher_lie_select (bool) afficher le select d’affectation
 */
if (!isset($b) || !is_array($b)) {
    return;
}

$bid = (int) ($b['id'] ?? 0);
if ($bid <= 0) {
    return;
}

$rid = (int) ($b['rayon_id'] ?? 0);
$qc = get_qrcode_barre_web_path($bid);
if ($qc === '' && !empty($b['code_scan'])) {
    entrepot_generer_codes_barre($bid);
    $qc = get_qrcode_barre_web_path($bid);
}
$etiq_lib = entrepot_barre_etiquette_libelle($b, $etage, $rayon);
$nom_form = entrepot_barre_nom_valeur_formulaire($b['nom'] ?? '', (int) ($b['numero'] ?? 0));
$lie_barre_elements = $lie_barre_elements ?? [];
$champ_element_fixe = isset($champ_element_fixe) ? (int) $champ_element_fixe : null;
$afficher_lie_select = !empty($afficher_lie_select);
$lie_barre_champ = $lie_barre_champ ?? null;
if (!isset($ee_etiq_dims) || !is_array($ee_etiq_dims)) {
    require_once __DIR__ . '/../../../models/model_entrepot_etiquette_parametres.php';
    $ee_etiq_dims = entrepot_etiquette_dims();
}
$ee_etiq_label = (string) ($ee_etiq_dims['label'] ?? 'Étiquette 90×40 mm');
$ee_etiq_data = entrepot_etiquette_dims_data_attrs($ee_etiq_dims);
?>
<div class="ee-barre-in-rayon" id="barre-edit-<?php echo $bid; ?>" data-barre-id="<?php echo $bid; ?>">
    <div class="ee-barre-in-rayon__head">
        <strong class="ee-barre-in-rayon__title">Barre #<?php echo (int) ($b['numero'] ?? 0); ?></strong>
        <?php if (is_array($rayon) && !empty($rayon['nom'])): ?>
        <span class="ee-barre-in-rayon__rayon"><?php echo htmlspecialchars((string) $rayon['nom'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <?php if (!empty($b['code_scan'])): ?>
        <code class="ee-barre-card__code"><?php echo htmlspecialchars((string) $b['code_scan'], ENT_QUOTES, 'UTF-8'); ?></code>
        <?php endif; ?>
    </div>
    <?php if ($rid > 0): ?>
    <input type="hidden" name="barres[<?php echo $bid; ?>][rayon_id]" value="<?php echo $rid; ?>">
    <?php endif; ?>
    <div class="ee-barre-meta">
        <p class="ee-barre-meta__kicker">
            <i class="fas fa-tag" aria-hidden="true"></i>
            Configuration barre
        </p>
        <div class="ee-barre-meta__grid">
            <div class="ee-barre-meta__field">
                <label for="barre-nom-<?php echo $bid; ?>">
                    <i class="fas fa-tag" aria-hidden="true"></i>
                    Nom de la barre
                    <span class="ee-barre-meta__optional">facultatif</span>
                </label>
                <input type="text" id="barre-nom-<?php echo $bid; ?>" name="barres[<?php echo $bid; ?>][nom]" value="<?php echo htmlspecialchars($nom_form, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex. B01-01" autocomplete="off">
            </div>
            <?php if ($champ_element_fixe !== null && $champ_element_fixe > 0): ?>
            <input type="hidden" name="barres[<?php echo $bid; ?>][champ_element_id]" value="<?php echo $champ_element_fixe; ?>">
            <?php elseif ($afficher_lie_select && $lie_barre_champ !== null && $lie_barre_elements !== []): ?>
            <div class="ee-barre-meta__field">
                <label for="barre-champ-<?php echo $bid; ?>">
                    <i class="fas <?php echo htmlspecialchars($lie_barre_champ['icon'] ?? 'fa-cube', ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($lie_barre_champ['label'] ?? 'Lien', ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <select id="barre-champ-<?php echo $bid; ?>" name="barres[<?php echo $bid; ?>][champ_element_id]">
                    <option value=""<?php echo empty($b['champ_element_id']) ? ' selected' : ''; ?>>— Non assignée —</option>
                    <?php foreach ($lie_barre_elements as $el): ?>
                    <option value="<?php echo (int) $el['id']; ?>" <?php echo (int) ($b['champ_element_id'] ?? 0) === (int) $el['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($el['nom'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="ee-barre-etiq-block" id="ee-barre-etiq-root-<?php echo $bid; ?>" data-css-url="<?php echo htmlspecialchars($origin_et . $barre_etiq_css, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $ee_etiq_data; ?>>
        <p class="ee-barre-etiq-block__label"><?php echo htmlspecialchars($ee_etiq_label, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="ee-barre-etiq-row">
            <div class="ee-barre-etiq-preview-wrap">
                <div class="ee-barre-etiq-preview-scale">
                    <article class="ee-barre-etiq" data-barre-etiq>
                        <span class="ee-barre-etiq__text"><?php echo htmlspecialchars($etiq_lib, ENT_QUOTES, 'UTF-8'); ?></span>
                        <div class="ee-barre-etiq__qr-box">
                            <?php if ($qc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($qc, ENT_QUOTES, 'UTF-8'); ?>" width="96" height="96" alt="QR barre" class="ee-barre-etiq__qr">
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            </div>
            <div class="ee-barre-etiq-actions">
                <button type="button" class="ee-barre-etiq-print-btn" data-barre-print="<?php echo $bid; ?>">
                    <i class="fas fa-print" aria-hidden="true"></i> Imprimer l’étiquette
                </button>
                <a href="emplacement-barre-etiquette.php?id=<?php echo $bid; ?>" class="ee-btn-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> PDF</a>
            </div>
        </div>
    </div>
    <h4 class="ee-barre-positions-title">Positions sur cette barre</h4>
    <div class="ee-naming-list ee-naming-list--compact">
        <?php foreach ($b['positions'] ?? [] as $p): ?>
        <div class="ee-naming-row">
            <span class="ee-naming-row__num">#<?php echo (int) $p['numero']; ?></span>
            <input type="text" name="barres[<?php echo $bid; ?>][positions][<?php echo (int) $p['id']; ?>][nom]" value="<?php echo htmlspecialchars($p['nom']); ?>" required aria-label="Position <?php echo (int) $p['numero']; ?> barre <?php echo (int) ($b['numero'] ?? 0); ?>">
        </div>
        <?php endforeach; ?>
    </div>
</div>
