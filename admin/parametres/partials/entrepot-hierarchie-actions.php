<?php
/**
 * Boutons modifier / supprimer pour les tableaux hiérarchie entrepôt.
 */
if (!function_exists('ee_hierarchie_render_actions')) {
    /**
     * @param string $csrf
     * @param int $eid
     * @param int $uid
     * @param string $type zone|rayon|etagere|barre|position
     * @param string $table entrepot_zone|…
     * @param int $id
     * @param int $numero
     * @param string $nom_raw
     */
    function ee_hierarchie_render_actions($csrf, $eid, $uid, $type, $table, $id, $numero, $nom_raw) {
        require_once __DIR__ . '/../../../models/model_entrepot_hierarchie.php';
        $id = (int) $id;
        $numero = (int) $numero;
        $eid = (int) $eid;
        $uid = (int) $uid;
        $nom_attr = htmlspecialchars((string) $nom_raw, ENT_QUOTES, 'UTF-8');
        $impact = entrepot_hierarchie_impact_suppression_entite($table, $id, $eid, (string) $nom_raw, $numero);
        $impact_json = htmlspecialchars(
            json_encode($impact ?: [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            ENT_QUOTES,
            'UTF-8'
        );
        ?>
        <div class="ee-h-actions">
            <button type="button"
                class="ee-h-edit__btn"
                title="Modifier"
                data-ee-edit-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                data-ee-edit-table="<?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?>"
                data-ee-edit-id="<?php echo $id; ?>"
                data-ee-edit-numero="<?php echo $numero; ?>"
                data-ee-edit-nom="<?php echo $nom_attr; ?>"
                data-ee-edit-etage="<?php echo $eid; ?>"
                data-ee-edit-niveau="<?php echo $uid; ?>"
                onclick="event.stopPropagation(); eeOpenEditHierarchie(this);">
                <i class="fas fa-pen" aria-hidden="true"></i>
                <span class="visually-hidden">Modifier</span>
            </button>
            <button type="button"
                class="ee-h-delete__btn"
                title="Supprimer"
                data-ee-delete-impact="<?php echo $impact_json; ?>"
                data-ee-delete-csrf="<?php echo $csrf; ?>"
                data-ee-delete-table="<?php echo htmlspecialchars($table, ENT_QUOTES, 'UTF-8'); ?>"
                data-ee-delete-id="<?php echo $id; ?>"
                data-ee-delete-etage="<?php echo $eid; ?>"
                data-ee-delete-niveau="<?php echo $uid; ?>"
                onclick="event.stopPropagation(); eeOpenDeleteHierarchie(this);">
                <i class="fas fa-trash-can" aria-hidden="true"></i>
                <span class="visually-hidden">Supprimer</span>
            </button>
        </div>
        <?php
    }
}
