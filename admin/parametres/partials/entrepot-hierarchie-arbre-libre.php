<?php
/**
 * Hiérarchie libre — tableau racine cliquable + drill modal (cascade).
 *
 * @var array<string, mixed>|null $arbre
 * @var int $numero_niveau
 * @var int $etage_id
 * @var array<int, array<string, mixed>> $hierarchie_defs
 */
if (empty($arbre) || !is_array($arbre) || ($arbre['mode'] ?? '') !== 'libre') {
    echo '<div class="ee-empty"><p>Configurez la hiérarchie puis ajoutez des éléments.</p></div>';
    return;
}

$csrf = htmlspecialchars((string) ($_SESSION['admin_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');
$uid = (int) $numero_niveau;
$eid = (int) $etage_id;
$defs = is_array($arbre['defs'] ?? null) ? $arbre['defs'] : ($hierarchie_defs ?? []);
$racines = is_array($arbre['racines'] ?? null) ? $arbre['racines'] : [];
$def_by_id = [];
foreach ($defs as $d) {
    $def_by_id[(int) ($d['id'] ?? 0)] = $d;
}

/**
 * @param array<int, array<string, mixed>> $defs
 * @param int $niveau_id
 * @return array<string, mixed>|null
 */
if (!function_exists('ee_libre_next_def')) {
    function ee_libre_next_def(array $defs, $niveau_id) {
        $niveau_id = (int) $niveau_id;
        $found = false;
        foreach ($defs as $d) {
            $id = (int) ($d['id'] ?? 0);
            if ($found) {
                return $d;
            }
            if ($id === $niveau_id) {
                $found = true;
            }
        }

        return null;
    }
}

$root_def = null;
$root_label = 'Éléments';
$root_icon = 'fa-cube';
$child_def = null;
$child_label = 'Sous-éléments';
if ($racines !== []) {
    $first_nid = (int) ($racines[0]['niveau_id'] ?? 0);
    $root_def = $def_by_id[$first_nid] ?? null;
    if ($root_def) {
        $root_label = (string) ($root_def['label'] ?? $root_label);
        $root_icon = (string) ($root_def['icon'] ?? $root_icon);
    }
    $child_def = ee_libre_next_def($defs, $first_nid);
    if ($child_def) {
        $child_label = (string) ($child_def['label'] ?? $child_label);
    }
}

$json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$payload = [
    'etage_id' => $eid,
    'numero_etage' => $uid,
    'etiquette_niveau_id' => (int) ($arbre['etiquette_niveau_id'] ?? 0),
    'defs' => array_values(array_map(static function ($d) {
        return [
            'id' => (int) ($d['id'] ?? 0),
            'label' => (string) ($d['label'] ?? ''),
            'icon' => (string) ($d['icon'] ?? 'fa-cube'),
            'slug' => (string) ($d['slug'] ?? ''),
            'est_etiquette_qr' => (int) ($d['est_etiquette_qr'] ?? 0),
        ];
    }, $defs)),
    'racines' => $racines,
];
$payload_json = json_encode($payload, $json_flags);
if ($payload_json === false) {
    $payload_json = '{"defs":[],"racines":[]}';
}
?>

<div class="ee-hierarchie ee-hierarchie--libre ee-hierarchie--drill-modal"
     id="ee-hierarchie-<?php echo $uid; ?>"
     data-ee-hierarchie-libre>
    <script type="application/json" id="ee-libre-tree-<?php echo $uid; ?>"><?php echo $payload_json; ?></script>

    <?php if ($defs === []): ?>
    <div class="ee-h-empty-block">
        <p class="ee-h-empty">Aucun niveau hiérarchique actif.</p>
        <p class="ee-h-empty ee-h-empty--hint">Ouvrez <a href="hierarchie-entrepot.php"><strong>Configurer la hiérarchie</strong></a> pour ajouter des niveaux.</p>
    </div>
    <?php elseif ($racines === []): ?>
    <div class="ee-h-empty-block">
        <p class="ee-h-empty">Aucun élément sur cet étage.</p>
        <p class="ee-h-empty ee-h-empty--hint">Utilisez les boutons de la barre d’outils pour ajouter le premier niveau.</p>
    </div>
    <?php else: ?>
    <section class="ee-h-table-section ee-h-table-section--libre" aria-label="<?php echo htmlspecialchars($root_label, ENT_QUOTES, 'UTF-8'); ?>">
        <header class="ee-h-drill-panel__head">
            <h3><i class="fas <?php echo htmlspecialchars($root_icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($root_label, ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="ee-h-drill-panel__hint">Cliquez une ligne pour ouvrir le niveau suivant.</p>
        </header>
        <div class="ee-table-scroll">
            <table class="ee-table ee-table--hierarchie ee-h-pick-table" data-ee-libre-root-table>
                <thead>
                    <tr>
                        <th scope="col">N°</th>
                        <th scope="col">Nom</th>
                        <th scope="col"><?php echo htmlspecialchars($child_def ? $child_label : 'Contenu', ENT_QUOTES, 'UTF-8'); ?></th>
                        <th scope="col" class="ee-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($racines as $node):
                        $id = (int) ($node['id'] ?? 0);
                        $nom = (string) ($node['nom'] ?? '');
                        $numero = (int) ($node['numero'] ?? 0);
                        $niveau_id = (int) ($node['niveau_id'] ?? 0);
                        $enfants = is_array($node['enfants'] ?? null) ? $node['enfants'] : [];
                        $nb = count($enfants);
                        $next = ee_libre_next_def($defs, $niveau_id);
                        $can_drill = $next !== null;
                        $nom_esc = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
                        $nom_js = htmlspecialchars(json_encode($nom, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="ee-h-pick-row ee-h-pick-row--libre<?php echo $can_drill ? ' is-drillable' : ''; ?>"
                        data-ee-libre-node="<?php echo $id; ?>"
                        data-ee-niveau-id="<?php echo $niveau_id; ?>"
                        data-ee-can-drill="<?php echo $can_drill ? '1' : '0'; ?>"
                        <?php if ($can_drill): ?>tabindex="0" role="button" aria-selected="false"<?php endif; ?>>
                        <td class="ee-table-etage-num">#<?php echo $numero; ?></td>
                        <td><span class="ee-entity-name"><?php echo $nom_esc; ?></span></td>
                        <td>
                            <?php if ($can_drill): ?>
                            <span class="ee-h-count-cell">
                                <span class="ee-badge ee-badge--muted"><?php echo $nb; ?>&nbsp;<?php echo htmlspecialchars((string) ($next['label'] ?? 'élément'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <i class="fas fa-chevron-right ee-h-pick-chevron" aria-hidden="true"></i>
                            </span>
                            <?php else: ?>
                            <span class="ee-badge ee-badge--leaf">Feuille</span>
                            <?php endif; ?>
                        </td>
                        <td class="ee-table__actions">
                            <div class="ee-libre-node__actions">
                                <button type="button" class="ee-btn-icon" title="Modifier"
                                    onclick="eeOpenModifierNoeud(<?php echo $id; ?>, <?php echo $nom_js; ?>, <?php echo $numero; ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="ee-btn-icon ee-btn-icon--danger" title="Supprimer"
                                    data-ee-noeud-id="<?php echo $id; ?>"
                                    data-ee-noeud-nom="<?php echo $nom_esc; ?>"
                                    onclick="eeOpenDeleteNoeudLibre(this)">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>
