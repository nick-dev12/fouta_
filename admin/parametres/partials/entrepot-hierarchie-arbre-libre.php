<?php
/**
 * Arbre hiérarchie libre — nœuds génériques récursifs.
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
 * @param array<string, mixed> $node
 * @param int $depth
 */
function ee_render_noeud_libre(array $node, $depth, array $def_by_id, $csrf, $uid, $eid) {
    $id = (int) ($node['id'] ?? 0);
    $nom = htmlspecialchars((string) ($node['nom'] ?? ''), ENT_QUOTES, 'UTF-8');
    $numero = (int) ($node['numero'] ?? 0);
    $niveau_id = (int) ($node['niveau_id'] ?? 0);
    $def = $def_by_id[$niveau_id] ?? null;
    $label_niv = htmlspecialchars((string) ($def['label'] ?? 'Élément'), ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars((string) ($def['icon'] ?? 'fa-cube'), ENT_QUOTES, 'UTF-8');
    $enfants = is_array($node['enfants'] ?? null) ? $node['enfants'] : [];
    ?>
    <li class="ee-libre-node" data-ee-noeud="<?php echo $id; ?>" data-ee-niveau-id="<?php echo $niveau_id; ?>">
        <div class="ee-libre-node__row" style="--ee-depth: <?php echo (int) $depth; ?>">
            <span class="ee-libre-node__badge"><i class="fas <?php echo $icon; ?>"></i> <?php echo $label_niv; ?></span>
            <strong class="ee-libre-node__name"><?php echo $nom; ?></strong>
            <span class="ee-libre-node__num">#<?php echo $numero; ?></span>
            <div class="ee-libre-node__actions">
                <button type="button" class="ee-btn-icon" title="Modifier"
                    onclick="eeOpenModifierNoeud(<?php echo $id; ?>, <?php echo htmlspecialchars(json_encode((string) ($node['nom'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $numero; ?>)">
                    <i class="fas fa-pen"></i>
                </button>
                <button type="button" class="ee-btn-icon ee-btn-icon--danger" title="Supprimer"
                    data-ee-noeud-id="<?php echo $id; ?>"
                    data-ee-noeud-nom="<?php echo $nom; ?>"
                    onclick="eeOpenDeleteNoeudLibre(this)">
                    <i class="fas fa-trash-can"></i>
                </button>
            </div>
        </div>
        <?php if ($enfants !== []): ?>
        <ul class="ee-libre-tree">
            <?php foreach ($enfants as $child) {
                ee_render_noeud_libre($child, $depth + 1, $def_by_id, $csrf, $uid, $eid);
            } ?>
        </ul>
        <?php endif; ?>
    </li>
    <?php
}
?>

<div class="ee-hierarchie ee-hierarchie--libre" id="ee-hierarchie-<?php echo $uid; ?>" data-ee-hierarchie-libre>
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
    <ul class="ee-libre-tree ee-libre-tree--root">
        <?php foreach ($racines as $node) {
            ee_render_noeud_libre($node, 0, $def_by_id, $csrf, $uid, $eid);
        } ?>
    </ul>
    <?php endif; ?>
</div>
