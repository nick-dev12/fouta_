<?php
/**
 * Bloc barre dans l’arbre hiérarchique (étiquette 90×30, QR, positions).
 *
 * @var array $b
 * @var array $etage_ctx
 * @var array $rayon_ctx
 * @var string $origin_et
 * @var string $barre_etiq_css
 */
if (empty($b) || !is_array($b)) {
    return;
}
$bid = (int) ($b['id'] ?? 0);
if ($bid <= 0) {
    return;
}
$etage = $etage_ctx ?? [];
$rayon = $rayon_ctx ?? null;
$origin_et = isset($origin_et) ? (string) $origin_et : '';
$barre_etiq_css = isset($barre_etiq_css) ? (string) $barre_etiq_css : '/css/entrepot-barre-etiquette.css';

$qc = get_qrcode_barre_web_path($bid);
if ($qc === '' && !empty($b['code_scan'])) {
    entrepot_generer_codes_barre($bid);
    $qc = get_qrcode_barre_web_path($bid);
}
$etiq_lib = entrepot_barre_etiquette_libelle($b, $etage, $rayon);
$nom_form = entrepot_barre_nom_valeur_formulaire($b['nom'] ?? '', (int) ($b['numero'] ?? 0));
$csrf_barre = htmlspecialchars((string) ($_SESSION['admin_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');
$eid_barre = (int) ($etage['id'] ?? 0);
$uid_barre = (int) ($etage['numero_etage'] ?? 0);
require_once __DIR__ . '/entrepot-hierarchie-actions.php';
require_once __DIR__ . '/../../../models/model_entrepot_hierarchie.php';
$barre_delete_impact = entrepot_hierarchie_impact_suppression_entite(
    'entrepot_barre',
    $bid,
    $eid_barre,
    $nom_form !== '' ? $nom_form : ('Barre ' . (int) ($b['numero'] ?? 0)),
    (int) ($b['numero'] ?? 0)
);
$barre_delete_impact_json = htmlspecialchars(
    json_encode($barre_delete_impact ?: [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ENT_QUOTES,
    'UTF-8'
);
?>
<div class="ee-h-barre" id="barre-h-<?php echo $bid; ?>">
    <div class="ee-h-barre__head">
        <strong>Barre #<?php echo (int) ($b['numero'] ?? 0); ?></strong>
        <?php if ($nom_form !== '' && $nom_form !== ('Barre ' . (int) ($b['numero'] ?? 0))): ?>
        <span class="ee-h-barre__nom-inline"><?php echo htmlspecialchars($nom_form, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <?php if (!empty($b['code_scan'])): ?>
        <code class="ee-h-barre__scan"><?php echo htmlspecialchars((string) $b['code_scan'], ENT_QUOTES, 'UTF-8'); ?></code>
        <?php endif; ?>
        <div class="ee-h-barre__head-actions">
            <button type="button"
                class="ee-h-edit__btn"
                title="Modifier la barre"
                data-ee-edit-type="barre"
                data-ee-edit-table="entrepot_barre"
                data-ee-edit-id="<?php echo $bid; ?>"
                data-ee-edit-numero="<?php echo (int) ($b['numero'] ?? 0); ?>"
                data-ee-edit-nom="<?php echo htmlspecialchars($nom_form !== '' ? $nom_form : ('Barre ' . (int) ($b['numero'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>"
                data-ee-edit-etage="<?php echo $eid_barre; ?>"
                data-ee-edit-niveau="<?php echo $uid_barre; ?>"
                onclick="eeOpenEditHierarchie(this);">
                <i class="fas fa-pen" aria-hidden="true"></i>
            </button>
            <button type="button"
                class="ee-h-delete__btn"
                title="Supprimer la barre"
                data-ee-delete-impact="<?php echo $barre_delete_impact_json; ?>"
                data-ee-delete-csrf="<?php echo $csrf_barre; ?>"
                data-ee-delete-table="entrepot_barre"
                data-ee-delete-id="<?php echo $bid; ?>"
                data-ee-delete-etage="<?php echo $eid_barre; ?>"
                data-ee-delete-niveau="<?php echo $uid_barre; ?>"
                onclick="eeOpenDeleteHierarchie(this);">
                <i class="fas fa-trash-can" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="ee-h-barre__grid">
        <div class="ee-barre-etiq-block" id="ee-barre-etiq-root-<?php echo $bid; ?>" data-css-url="<?php echo htmlspecialchars($origin_et . $barre_etiq_css, ENT_QUOTES, 'UTF-8'); ?>">
            <p class="ee-barre-etiq-block__label">Étiquette 90×30 mm</p>
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
                    <a href="emplacement-barre-etiquette.php?id=<?php echo $bid; ?>" class="ee-barre-etiq-pdf-btn" target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf" aria-hidden="true"></i> PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="ee-h-barre__positions">
        <h4 class="ee-h-barre__positions-title"><i class="fas fa-crosshairs"></i> Positions</h4>
        <?php if (!empty($b['positions'])): ?>
        <div class="ee-table-scroll">
            <table class="ee-table ee-table--hierarchie ee-table--compact">
                <thead>
                    <tr>
                        <th scope="col">N°</th>
                        <th scope="col">Nom</th>
                        <th scope="col" class="ee-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($b['positions'] as $p):
                        $pid = (int) ($p['id'] ?? 0);
                        if ($pid <= 0) {
                            continue;
                        }
                        $pnum = (int) ($p['numero'] ?? 0);
                        $pnom = htmlspecialchars((string) ($p['nom'] ?? 'Position'), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td class="ee-table-etage-num">#<?php echo $pnum; ?></td>
                        <td><span class="ee-entity-name"><?php echo $pnom; ?></span></td>
                        <td class="ee-table__actions">
                            <?php ee_hierarchie_render_actions(
                                $csrf_barre,
                                $eid_barre,
                                $uid_barre,
                                'position',
                                'entrepot_position',
                                $pid,
                                $pnum,
                                (string) ($p['nom'] ?? 'Position')
                            ); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="ee-h-empty ee-h-empty--sm">Aucune position sur cette barre.</p>
        <?php endif; ?>
    </div>
</div>
