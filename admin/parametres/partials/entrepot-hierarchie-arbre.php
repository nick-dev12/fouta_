<?php
/**
 * Hiérarchie entrepôt — onglets Zone → Rayon + tableaux Étagère → Barre → Position.
 *
 * @var array<string, mixed>|null $arbre
 * @var int $numero_niveau
 * @var int $etage_id
 */
if (empty($arbre) || !is_array($arbre)) {
    echo '<div class="ee-empty"><p>Aucune zone sur ce niveau. Ajoutez une zone pour commencer.</p></div>';
    return;
}

require_once __DIR__ . '/../../../includes/entrepot_barcode_service.php';
require_once __DIR__ . '/../../../includes/site_url.php';
require_once __DIR__ . '/../../../includes/asset_version.php';
require_once __DIR__ . '/entrepot-hierarchie-actions.php';

$origin_et = get_request_origin_base_url();
$barre_etiq_css = '/css/entrepot-barre-etiquette.css' . asset_version_query();

$zones_noeuds = is_array($arbre['zones'] ?? null) ? $arbre['zones'] : [];
$rayons_list = is_array($arbre['rayons'] ?? null) ? $arbre['rayons'] : [];
$etageres_list = is_array($arbre['etageres'] ?? null) ? $arbre['etageres'] : [];
$barres_list = is_array($arbre['barres'] ?? null) ? $arbre['barres'] : [];
$etage_ctx = is_array($arbre['etage'] ?? null) ? $arbre['etage'] : [];

$zones_list = [];
foreach ($zones_noeuds as $z) {
    $zid = (int) ($z['id'] ?? 0);
    if ($zid <= 0) {
        continue;
    }
    $zones_list[] = $z;
}

$csrf = htmlspecialchars((string) ($_SESSION['admin_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');
$uid = (int) $numero_niveau;
$eid = (int) $etage_id;

$rayon_zone_map = [];
$rayons_par_zone_count = [];
foreach ($rayons_list as $r) {
    $rid = (int) ($r['id'] ?? 0);
    if ($rid <= 0) {
        continue;
    }
    $zid = (int) ($r['zone_id'] ?? 0);
    $rayon_zone_map[$rid] = $zid;
    if (!isset($rayons_par_zone_count[$zid])) {
        $rayons_par_zone_count[$zid] = 0;
    }
    $rayons_par_zone_count[$zid]++;
}

if (!isset($hierarchie_actifs)) {
    require_once __DIR__ . '/../../../models/model_entrepot_structure_champs.php';
    $hierarchie_actifs = entrepot_hierarchie_niveaux_actifs();
}
$ee_show_zone = isset($hierarchie_actifs['zone']);
$ee_show_rayon = isset($hierarchie_actifs['rayon']);
$ee_show_etagere = isset($hierarchie_actifs['etagere']);
$ee_show_barre = isset($hierarchie_actifs['barre']);
$ee_show_position = isset($hierarchie_actifs['position']);
$ee_niveaux_actifs_json = htmlspecialchars(json_encode(array_keys($hierarchie_actifs)), ENT_QUOTES, 'UTF-8');

$ee_empty_hint = 'Zone';
if ($ee_show_zone) {
    $ee_empty_hint = (string) ($hierarchie_actifs['zone']['label'] ?? 'Zone');
} elseif ($ee_show_rayon) {
    $ee_empty_hint = (string) ($hierarchie_actifs['rayon']['label'] ?? 'Rayon');
} elseif ($ee_show_etagere) {
    $ee_empty_hint = (string) ($hierarchie_actifs['etagere']['label'] ?? 'Étagère');
} elseif ($ee_show_barre) {
    $ee_empty_hint = (string) ($hierarchie_actifs['barre']['label'] ?? 'Barre');
} elseif ($ee_show_position) {
    $ee_empty_hint = (string) ($hierarchie_actifs['position']['label'] ?? 'Position');
}

$ee_show_empty = false;
if ($hierarchie_actifs === []) {
    $ee_show_empty = true;
} elseif ($ee_show_zone && $zones_list === []) {
    $ee_show_empty = true;
} elseif (!$ee_show_zone && $ee_show_rayon && $rayons_list === []) {
    $ee_show_empty = true;
}
?>

<div class="ee-hierarchie<?php echo $ee_show_zone ? ' ee-hierarchie--drill-modal' : ''; ?>" id="ee-hierarchie-<?php echo $uid; ?>" data-ee-hierarchie-root data-ee-niveaux-actifs="<?php echo $ee_niveaux_actifs_json; ?>">
    <?php if ($hierarchie_actifs === []): ?>
    <div class="ee-h-empty-block">
        <p class="ee-h-empty">Aucun niveau hiérarchique actif.</p>
        <p class="ee-h-empty ee-h-empty--hint">Ajoutez un champ avec niveau hiérarchique (Zone, Rayon, etc.) pour afficher la cartographie.</p>
    </div>
    <?php elseif ($ee_show_empty): ?>
    <div class="ee-h-empty-block">
        <p class="ee-h-empty">Aucun élément sur ce niveau.</p>
        <p class="ee-h-empty ee-h-empty--hint">Utilisez le bouton <strong><?php echo htmlspecialchars($ee_empty_hint, ENT_QUOTES, 'UTF-8'); ?></strong> pour en ajouter un.</p>
    </div>
    <?php else: ?>

    <div class="ee-h-nav-stack">
        <?php if ($ee_show_zone): ?>
        <section class="ee-h-table-section" aria-label="Zones du niveau">
            <header class="ee-h-drill-panel__head">
                <h3><i class="fas fa-map-marker-alt"></i> Zones</h3>
            </header>
            <div class="ee-table-scroll">
                <table class="ee-table ee-table--hierarchie ee-h-pick-table">
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Nom</th>
                            <th scope="col">Rayons</th>
                            <th scope="col" class="ee-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones_list as $zone):
                            $zid = (int) ($zone['id'] ?? 0);
                            $znom = htmlspecialchars((string) ($zone['nom'] ?? 'Zone'), ENT_QUOTES, 'UTF-8');
                            $znum = (int) ($zone['numero'] ?? 0);
                            $nb_rayons = (int) ($rayons_par_zone_count[$zid] ?? 0);
                        ?>
                        <tr class="ee-h-pick-row ee-h-pick-row--zone"
                            data-ee-item="zone"
                            data-ee-id="<?php echo $zid; ?>"
                            tabindex="0"
                            role="button"
                            aria-selected="false">
                            <td class="ee-table-etage-num">#<?php echo $znum; ?></td>
                            <td><span class="ee-entity-name"><?php echo $znom; ?></span></td>
                            <td>
                                <span class="ee-badge ee-badge--muted"><?php echo $nb_rayons; ?> rayon<?php echo $nb_rayons > 1 ? 's' : ''; ?></span>
                            </td>
                            <td class="ee-table__actions">
                                <?php ee_hierarchie_render_actions(
                                    $csrf,
                                    $eid,
                                    $uid,
                                    'zone',
                                    'entrepot_zone',
                                    $zid,
                                    $znum,
                                    (string) ($zone['nom'] ?? 'Zone')
                                ); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($ee_show_rayon): ?>
        <section class="ee-h-table-section" data-ee-rayons-section aria-label="Rayons de la zone"<?php echo $ee_show_zone ? ' hidden' : ''; ?>>
            <header class="ee-h-drill-panel__head">
                <h3><i class="fas fa-th-large"></i> Rayons</h3>
            </header>
            <div class="ee-table-scroll">
                <table class="ee-table ee-table--hierarchie ee-h-pick-table">
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Nom</th>
                            <th scope="col" class="ee-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rayons_list as $r):
                            $rid = (int) ($r['id'] ?? 0);
                            if ($rid <= 0) {
                                continue;
                            }
                            $zid = (int) ($r['zone_id'] ?? 0);
                            $rnom = htmlspecialchars((string) ($r['nom'] ?? 'Rayon'), ENT_QUOTES, 'UTF-8');
                            $rnum = (int) ($r['numero'] ?? 0);
                        ?>
                        <tr class="ee-h-pick-row ee-h-pick-row--rayon"
                            data-ee-item="rayon"
                            data-ee-id="<?php echo $rid; ?>"
                            data-ee-zone="<?php echo $zid; ?>"
                            <?php if ($ee_show_zone): ?>hidden<?php endif; ?>
                            tabindex="0"
                            role="button"
                            aria-selected="false">
                            <td class="ee-table-etage-num">#<?php echo $rnum; ?></td>
                            <td><span class="ee-entity-name"><?php echo $rnom; ?></span></td>
                            <td class="ee-table__actions">
                                <?php ee_hierarchie_render_actions(
                                    $csrf,
                                    $eid,
                                    $uid,
                                    'rayon',
                                    'entrepot_rayon',
                                    $rid,
                                    $rnum,
                                    (string) ($r['nom'] ?? 'Rayon')
                                ); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="ee-h-empty ee-h-empty--sm" data-ee-rayons-empty hidden>Aucun rayon<?php echo $ee_show_zone ? ' dans cette zone' : ' sur ce niveau'; ?>.</p>
        </section>
        <?php endif; ?>
    </div>

    <?php if ($ee_show_etagere || $ee_show_barre || $ee_show_position): ?>
    <div class="ee-h-drill" data-ee-drill hidden>
        <nav class="ee-h-crumb" aria-label="Fil d’Ariane" data-ee-crumb>
            <button type="button" class="ee-h-crumb__back" data-ee-crumb-back hidden aria-label="Retour">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
            </button>
            <span class="ee-h-crumb__trail" data-ee-crumb-trail></span>
        </nav>

        <p class="ee-h-drill-hint" data-ee-drill-hint>Sélectionnez un rayon pour afficher les éléments suivants.</p>

        <?php if ($ee_show_etagere): ?>
        <section class="ee-h-drill-panel" data-ee-level="etageres" aria-label="Étagères" hidden>
            <header class="ee-h-drill-panel__head">
                <h3><i class="fas fa-bars-staggered"></i> Étagères</h3>
            </header>
            <?php if ($etageres_list === []): ?>
            <p class="ee-h-empty ee-h-empty--sm">Aucune étagère sur ce niveau.</p>
            <?php else: ?>
            <div class="ee-table-scroll">
                <table class="ee-table ee-table--hierarchie ee-h-pick-table">
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Nom</th>
                            <th scope="col" class="ee-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etageres_list as $etagere):
                            $etid = (int) ($etagere['id'] ?? 0);
                            $rid = (int) ($etagere['rayon_id'] ?? 0);
                            $zid_et = (int) ($etagere['zone_id'] ?? 0);
                            if ($zid_et <= 0) {
                                $zid_et = (int) ($rayon_zone_map[$rid] ?? 0);
                            }
                            $enom = htmlspecialchars((string) ($etagere['nom'] ?? 'Étagère'), ENT_QUOTES, 'UTF-8');
                            $enum = (int) ($etagere['numero'] ?? 0);
                        ?>
                        <tr class="ee-h-pick-row"
                            data-ee-item="etagere"
                            data-ee-id="<?php echo $etid; ?>"
                            data-ee-zone="<?php echo $zid_et; ?>"
                            data-ee-rayon="<?php echo $rid; ?>"
                            hidden
                            tabindex="0"
                            role="button">
                            <td class="ee-table-etage-num">#<?php echo $enum; ?></td>
                            <td><span class="ee-entity-name"><?php echo $enom; ?></span></td>
                            <td class="ee-table__actions">
                                <?php ee_hierarchie_render_actions(
                                    $csrf,
                                    $eid,
                                    $uid,
                                    'etagere',
                                    'entrepot_etagere',
                                    $etid,
                                    $enum,
                                    (string) ($etagere['nom'] ?? 'Étagère')
                                ); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="ee-h-empty ee-h-empty--sm" data-ee-etageres-empty hidden>Aucune étagère pour ce rayon.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($ee_show_barre): ?>
        <section class="ee-h-drill-panel" data-ee-level="barres" aria-label="Barres" hidden>
            <header class="ee-h-drill-panel__head">
                <h3><i class="fas fa-grip-lines"></i> Barres</h3>
            </header>
            <div class="ee-table-scroll">
                <table class="ee-table ee-table--hierarchie ee-h-pick-table">
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Nom</th>
                            <th scope="col">Code scan</th>
                            <th scope="col" class="ee-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barres_list as $b):
                            $bid = (int) ($b['id'] ?? 0);
                            $etid = (int) ($b['etagere_id'] ?? 0);
                            $rid = (int) ($b['rayon_id'] ?? 0);
                            $zid_b = (int) ($b['zone_id'] ?? 0);
                            if ($zid_b <= 0) {
                                $zid_b = (int) ($rayon_zone_map[$rid] ?? 0);
                            }
                            $bnom = entrepot_barre_nom_valeur_formulaire($b['nom'] ?? '', (int) ($b['numero'] ?? 0));
                            $bnum = (int) ($b['numero'] ?? 0);
                            $blabel = htmlspecialchars($bnom !== '' ? $bnom : ('Barre ' . $bnum), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="ee-h-pick-row"
                            data-ee-item="barre"
                            data-ee-id="<?php echo $bid; ?>"
                            data-ee-zone="<?php echo $zid_b; ?>"
                            data-ee-rayon="<?php echo $rid; ?>"
                            data-ee-etagere="<?php echo $etid; ?>"
                            hidden
                            tabindex="0"
                            role="button">
                            <td class="ee-table-etage-num">#<?php echo $bnum; ?></td>
                            <td><span class="ee-entity-name"><?php echo $blabel; ?></span></td>
                            <td><?php if (!empty($b['code_scan'])): ?><code class="ee-h-barre__scan"><?php echo htmlspecialchars((string) $b['code_scan'], ENT_QUOTES, 'UTF-8'); ?></code><?php else: ?>—<?php endif; ?></td>
                            <td class="ee-table__actions">
                                <?php ee_hierarchie_render_actions(
                                    $csrf,
                                    $eid,
                                    $uid,
                                    'barre',
                                    'entrepot_barre',
                                    $bid,
                                    $bnum,
                                    $bnom !== '' ? $bnom : ('Barre ' . $bnum)
                                ); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="ee-h-empty ee-h-empty--sm" data-ee-barres-empty hidden>Aucune barre pour cette étagère.</p>
        </section>
        <?php endif; ?>

        <?php if ($ee_show_position): ?>
        <section class="ee-h-drill-panel" data-ee-level="positions" aria-label="Positions" hidden>
            <header class="ee-h-drill-panel__head">
                <h3><i class="fas fa-crosshairs"></i> Positions</h3>
            </header>
            <?php foreach ($barres_list as $b):
                $bid = (int) ($b['id'] ?? 0);
                $rid = (int) ($b['rayon_id'] ?? 0);
                $etid = (int) ($b['etagere_id'] ?? 0);
                $zid_b = (int) ($b['zone_id'] ?? 0);
                if ($zid_b <= 0) {
                    $zid_b = (int) ($rayon_zone_map[$rid] ?? 0);
                }
                $rayon_ctx = null;
                foreach ($rayons_list as $r) {
                    if ((int) ($r['id'] ?? 0) === $rid) {
                        $rayon_ctx = $r;
                        break;
                    }
                }
            ?>
            <div class="ee-h-barre-detail"
                data-ee-barre-detail="<?php echo $bid; ?>"
                data-ee-zone="<?php echo $zid_b; ?>"
                data-ee-rayon="<?php echo $rid; ?>"
                data-ee-etagere="<?php echo $etid; ?>"
                hidden>
                <?php include __DIR__ . '/entrepot-barre-hierarchie-block.php'; ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
