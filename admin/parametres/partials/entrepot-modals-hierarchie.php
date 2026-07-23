<?php
/**
 * Modals CRUD hiérarchie entrepôt.
 *
 * Variables : $niveaux, $etage_id_actif, $numero_niveau_actif, $cascade_lists,
 * $all_niveaux_select, $structure_champs_tous, $niveaux_hierarchie_options,
 * $prochain_numero_niveau, $ee_form_niveau_numero, $ee_form_nom_niveau, $ee_form_code_abrege,
 * $numeros_niveaux_occupes, $cascade_zone, $cascade_rayon, $cascade_etagere
 */
$csrf = htmlspecialchars($_SESSION['admin_csrf']);
$num_actif = (int) $numero_niveau_actif;
$eid_actif = (int) $etage_id_actif;
if (!isset($ee_form_niveau_numero)) {
    $ee_form_niveau_numero = '';
}
$ee_form_nom_niveau = isset($ee_form_nom_niveau) ? (string) $ee_form_nom_niveau : '';
$ee_form_code_abrege = isset($ee_form_code_abrege) ? (string) $ee_form_code_abrege : '';
$numeros_niveaux_occupes = isset($numeros_niveaux_occupes) && is_array($numeros_niveaux_occupes) ? $numeros_niveaux_occupes : [];
$prochain_numero_niveau = isset($prochain_numero_niveau) ? (int) $prochain_numero_niveau : 1;

function ee_cascade_url($modal, $num, $eid = 0, $z = 0, $r = 0, $et = 0) {
    $q = ['niveau' => $num, 'modal' => $modal];
    if ($eid > 0) {
        $q['c_etage'] = $eid;
    }
    if ($z > 0) {
        $q['c_zone'] = $z;
    }
    if ($r > 0) {
        $q['c_rayon'] = $r;
    }
    if ($et > 0) {
        $q['c_etagere'] = $et;
    }

    return 'emplacement-entrepot.php?' . http_build_query($q);
}
?>
<div class="ee-modal" id="modalNiveau" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalNiveau')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title"><i class="fas fa-layer-group"></i> Ajouter — <?php echo htmlspecialchars(isset($label_hierarchie_etage) ? (string) $label_hierarchie_etage : 'Niveau', ENT_QUOTES, 'UTF-8'); ?></h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalNiveau')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_niveau" value="1">
            <div class="ee-modal__body">
                <div class="ee-field">
                    <label for="ee_numero_niveau">Numéro du niveau *</label>
                    <input type="number" id="ee_numero_niveau" name="numero_etage" min="1" max="<?php echo (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX; ?>" step="1" required
                        value="<?php echo $ee_form_niveau_numero !== '' && $ee_form_niveau_numero !== null ? (int) $ee_form_niveau_numero : ''; ?>"
                        placeholder="Ex. <?php echo (int) $prochain_numero_niveau; ?>"
                        inputmode="numeric"
                        autocomplete="off">
                    <span class="ee-field__hint">
                        Saisie manuelle (1 à <?php echo (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX; ?>) —
                        doublon contrôlé <strong>uniquement entre les niveaux</strong> (pas avec zones, rayons, etc.).
                        <?php if ($numeros_niveaux_occupes !== []): ?>
                        Déjà pris par un niveau&nbsp;: <strong><?php echo htmlspecialchars(implode(', ', $numeros_niveaux_occupes), ENT_QUOTES, 'UTF-8'); ?></strong>.
                        <?php else: ?>
                        Aucun numéro de niveau pris pour l’instant.
                        <?php endif; ?>
                    </span>
                </div>
                <div class="ee-field">
                    <label for="ee_nom_niveau">Nom du niveau *</label>
                    <input type="text" id="ee_nom_niveau" name="nom_niveau" maxlength="100" required placeholder="Ex. Rez-de-chaussée"
                        value="<?php echo htmlspecialchars($ee_form_nom_niveau, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="ee-field">
                    <label for="ee_code_abrege">Code abrégé (étiquettes barres) *</label>
                    <input type="text" id="ee_code_abrege" name="code_abrege" maxlength="10" required placeholder="Ex. RDC, B01"
                        value="<?php echo htmlspecialchars($ee_form_code_abrege, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="ee-field__hint">Affiché sur les étiquettes barres (max 10 caractères alphanumériques).</span>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalNiveau')">Annuler</button>
                <button type="submit" class="ee-modal__submit"><i class="fas fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalZone" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalZone')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head"><h2 class="ee-modal__title"><i class="fas fa-map-marker-alt"></i> Ajouter une zone</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalZone')"><i class="fas fa-xmark"></i></button></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_zone" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <div class="ee-field">
                    <label for="zone_etage_id">Niveau</label>
                    <select id="zone_etage_id" name="etage_id" required>
                        <?php foreach ($niveaux as $nv): ?>
                        <option value="<?php echo (int) $nv['id']; ?>" <?php echo (int) $nv['id'] === $eid_actif ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $nv['nom'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ee-field"><label for="zone_numero">Numéro</label><input type="number" id="zone_numero" name="numero" min="1" value="1" required></div>
                <div class="ee-field"><label for="zone_nom">Nom</label><input type="text" id="zone_nom" name="nom" maxlength="100" placeholder="Ex. Zone A"></div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalZone')">Annuler</button>
                <button type="submit" class="ee-modal__submit">Ajouter la zone</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalRayon" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalRayon')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head"><h2 class="ee-modal__title"><i class="fas fa-th-large"></i> Ajouter un rayon</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalRayon')"><i class="fas fa-xmark"></i></button></div>
        <form method="get" class="ee-cascade-form">
            <input type="hidden" name="niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="modal" value="modalRayon">
            <div class="ee-modal__body">
                <div class="ee-field">
                    <label>Niveau (filtre zones)</label>
                    <select name="c_etage" onchange="this.form.submit()">
                        <?php foreach ($all_niveaux_select as $nv): ?>
                        <option value="<?php echo (int) $nv['id']; ?>" <?php echo (int) $nv['id'] === ($cascade_etage > 0 ? $cascade_etage : $eid_actif) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $nv['nom'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_rayon" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="etage_id" value="<?php echo $cascade_etage > 0 ? $cascade_etage : $eid_actif; ?>">
            <div class="ee-modal__body">
                <div class="ee-field">
                    <label for="rayon_zone_id">Zone</label>
                    <select id="rayon_zone_id" name="zone_id" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($cascade_lists['zones'] as $z): ?>
                        <option value="<?php echo (int) $z['id']; ?>" <?php echo (int) $z['id'] === $cascade_zone ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $z['nom'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ee-field"><label for="rayon_numero">Numéro</label><input type="number" id="rayon_numero" name="numero" min="1" value="1" required></div>
                <div class="ee-field"><label for="rayon_nom">Nom</label><input type="text" id="rayon_nom" name="nom" maxlength="100"></div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalRayon')">Annuler</button>
                <button type="submit" class="ee-modal__submit">Ajouter le rayon</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalEtagere" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalEtagere')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title"><i class="fas fa-bars-staggered"></i> Ajouter une étagère</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalEtagere')"><i class="fas fa-xmark"></i></button>
        </div>
        <?php
        $etagere_eid = $cascade_etage_effectif;
        $etagere_zones = $etagere_eid > 0
            ? entrepot_hierarchie_liste_pour_cascade($etagere_eid)['zones']
            : [];
        $etagere_rayons = ($etagere_eid > 0 && $cascade_zone > 0)
            ? entrepot_hierarchie_liste_pour_cascade($etagere_eid, $cascade_zone)['rayons']
            : [];
        $etagere_pret = $etagere_eid > 0 && $cascade_zone > 0 && $cascade_rayon > 0;
        ?>
        <form method="get" class="ee-cascade-form" id="formEtagereCascade">
            <input type="hidden" name="niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="modal" value="modalEtagere">
            <div class="ee-modal__body">
                <p class="ee-modal__subtitle">Sélectionnez le niveau, la zone puis le rayon. Les champs nom et numéro apparaissent ensuite.</p>
                <ol class="ee-cascade-steps" aria-label="Étapes de sélection">
                    <li class="ee-cascade-steps__item<?php echo $etagere_eid > 0 ? ' is-done' : ' is-active'; ?>">
                        <span class="ee-cascade-steps__num">1</span> Niveau
                    </li>
                    <li class="ee-cascade-steps__item<?php echo $cascade_zone > 0 ? ' is-done' : ($etagere_eid > 0 ? ' is-active' : ''); ?>">
                        <span class="ee-cascade-steps__num">2</span> Zone
                    </li>
                    <li class="ee-cascade-steps__item<?php echo $cascade_rayon > 0 ? ' is-done' : ($cascade_zone > 0 ? ' is-active' : ''); ?>">
                        <span class="ee-cascade-steps__num">3</span> Rayon
                    </li>
                    <li class="ee-cascade-steps__item<?php echo $etagere_pret ? ' is-active' : ''; ?>">
                        <span class="ee-cascade-steps__num">4</span> Détails
                    </li>
                </ol>
                <div class="ee-field">
                    <label for="etagere_c_etage"><i class="fas fa-layer-group"></i> Niveau</label>
                    <select id="etagere_c_etage" name="c_etage" required onchange="eeCascadeResetAndSubmit(this.form, 'etagere')">
                        <option value="">— Choisir un niveau —</option>
                        <?php foreach ($all_niveaux_select as $nv): ?>
                        <option value="<?php echo (int) $nv['id']; ?>" <?php echo (int) $nv['id'] === $etagere_eid ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $nv['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo htmlspecialchars((string) ($nv['code_abrege'] ?? 'E' . ($nv['numero_etage'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($etagere_eid > 0): ?>
                <div class="ee-field">
                    <label for="etagere_c_zone"><i class="fas fa-map-marker-alt"></i> Zone</label>
                    <select id="etagere_c_zone" name="c_zone" required onchange="eeCascadeResetAndSubmit(this.form, 'etagere', 'zone')">
                        <option value="">— Choisir une zone —</option>
                        <?php foreach ($etagere_zones as $z): ?>
                        <option value="<?php echo (int) $z['id']; ?>" <?php echo (int) $z['id'] === $cascade_zone ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $z['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            (#<?php echo (int) ($z['numero'] ?? 0); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($etagere_zones === []): ?>
                    <span class="ee-field__hint ee-field__hint--warn">Aucune zone sur ce niveau — créez-en une d’abord.</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($etagere_eid > 0 && $cascade_zone > 0): ?>
                <div class="ee-field">
                    <label for="etagere_c_rayon"><i class="fas fa-th-large"></i> Rayon</label>
                    <select id="etagere_c_rayon" name="c_rayon" required onchange="this.form.submit()">
                        <option value="">— Choisir un rayon —</option>
                        <?php foreach ($etagere_rayons as $r): ?>
                        <option value="<?php echo (int) $r['id']; ?>" <?php echo (int) $r['id'] === $cascade_rayon ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $r['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            (#<?php echo (int) ($r['numero'] ?? 0); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($etagere_rayons === []): ?>
                    <span class="ee-field__hint ee-field__hint--warn">Aucun rayon dans cette zone — créez-en un d’abord.</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($etagere_pret): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_etagere" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="etage_id" value="<?php echo $etagere_eid; ?>">
            <input type="hidden" name="rayon_id" value="<?php echo (int) $cascade_rayon; ?>">
            <div class="ee-modal__body ee-modal__body--details">
                <p class="ee-modal__level-kicker"><i class="fas fa-check-circle"></i> Emplacement cible sélectionné — renseignez l’étagère.</p>
                <div class="ee-field">
                    <label for="etagere_numero"><i class="fas fa-hashtag"></i> Numéro</label>
                    <input type="number" id="etagere_numero" name="numero" min="1" value="1" required>
                </div>
                <div class="ee-field">
                    <label for="etagere_nom"><i class="fas fa-tag"></i> Nom</label>
                    <input type="text" id="etagere_nom" name="nom" maxlength="100" placeholder="Ex. Étagère A">
                    <span class="ee-field__hint">Facultatif — un nom par défaut sera généré si vide.</span>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalEtagere')">Annuler</button>
                <button type="submit" class="ee-modal__submit"><i class="fas fa-check"></i> Ajouter l’étagère</button>
            </div>
        </form>
        <?php else: ?>
        <div class="ee-modal__footer">
            <button type="button" class="ee-modal__cancel" onclick="closeModal('modalEtagere')">Fermer</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ee-modal" id="modalBarre" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalBarre')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title"><i class="fas fa-grip-lines"></i> Ajouter une barre</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalBarre')"><i class="fas fa-xmark"></i></button>
        </div>
        <?php
        $barre_eid = $cascade_etage_effectif;
        $barre_zones = $barre_eid > 0
            ? entrepot_hierarchie_liste_pour_cascade($barre_eid)['zones']
            : [];
        $barre_rayons = ($barre_eid > 0 && $cascade_zone > 0)
            ? entrepot_hierarchie_liste_pour_cascade($barre_eid, $cascade_zone)['rayons']
            : [];
        $barre_etageres = ($barre_eid > 0 && $cascade_zone > 0 && $cascade_rayon > 0)
            ? entrepot_hierarchie_liste_pour_cascade($barre_eid, $cascade_zone, $cascade_rayon)['etageres']
            : [];
        $barre_pret = $barre_eid > 0 && $cascade_zone > 0 && $cascade_rayon > 0 && $cascade_etagere > 0;
        ?>
        <form method="get" class="ee-cascade-form" id="formBarreCascade">
            <input type="hidden" name="niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="modal" value="modalBarre">
            <div class="ee-modal__body">
                <p class="ee-modal__subtitle">Sélectionnez le niveau, la zone, le rayon puis l’étagère. Le numéro et le nom apparaissent ensuite.</p>
                <ol class="ee-cascade-steps" aria-label="Étapes de sélection">
                    <li class="ee-cascade-steps__item<?php echo $barre_eid > 0 ? ' is-done' : ' is-active'; ?>">
                        <span class="ee-cascade-steps__num">1</span> Niveau
                    </li>
                    <li class="ee-cascade-steps__item<?php echo $cascade_zone > 0 ? ' is-done' : ($barre_eid > 0 ? ' is-active' : ''); ?>">
                        <span class="ee-cascade-steps__num">2</span> Zone
                    </li>
                    <li class="ee-cascade-steps__item<?php echo $cascade_rayon > 0 ? ' is-done' : ($cascade_zone > 0 ? ' is-active' : ''); ?>">
                        <span class="ee-cascade-steps__num">3</span> Rayon
                    </li>
                    <li class="ee-cascade-steps__item<?php echo $cascade_etagere > 0 ? ' is-done' : ($cascade_rayon > 0 ? ' is-active' : ''); ?>">
                        <span class="ee-cascade-steps__num">4</span> Étagère
                    </li>
                    <li class="ee-cascade-steps__item<?php echo $barre_pret ? ' is-active' : ''; ?>">
                        <span class="ee-cascade-steps__num">5</span> Détails
                    </li>
                </ol>
                <div class="ee-field">
                    <label for="barre_c_etage"><i class="fas fa-layer-group"></i> Niveau</label>
                    <select id="barre_c_etage" name="c_etage" required onchange="eeCascadeResetAndSubmit(this.form)">
                        <option value="">— Choisir un niveau —</option>
                        <?php foreach ($all_niveaux_select as $nv): ?>
                        <option value="<?php echo (int) $nv['id']; ?>" <?php echo (int) $nv['id'] === $barre_eid ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $nv['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo htmlspecialchars((string) ($nv['code_abrege'] ?? 'E' . ($nv['numero_etage'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($barre_eid > 0): ?>
                <div class="ee-field">
                    <label for="barre_c_zone"><i class="fas fa-map-marker-alt"></i> Zone</label>
                    <select id="barre_c_zone" name="c_zone" required onchange="eeCascadeResetAndSubmit(this.form, 'barre', 'zone')">
                        <option value="">— Choisir une zone —</option>
                        <?php foreach ($barre_zones as $z): ?>
                        <option value="<?php echo (int) $z['id']; ?>" <?php echo (int) $z['id'] === $cascade_zone ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $z['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            (#<?php echo (int) ($z['numero'] ?? 0); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($barre_zones === []): ?>
                    <span class="ee-field__hint ee-field__hint--warn">Aucune zone sur ce niveau — créez-en une d’abord.</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($barre_eid > 0 && $cascade_zone > 0): ?>
                <div class="ee-field">
                    <label for="barre_c_rayon"><i class="fas fa-th-large"></i> Rayon</label>
                    <select id="barre_c_rayon" name="c_rayon" required onchange="eeCascadeResetAndSubmit(this.form, 'barre', 'rayon')">
                        <option value="">— Choisir un rayon —</option>
                        <?php foreach ($barre_rayons as $r): ?>
                        <option value="<?php echo (int) $r['id']; ?>" <?php echo (int) $r['id'] === $cascade_rayon ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $r['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            (#<?php echo (int) ($r['numero'] ?? 0); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($barre_rayons === []): ?>
                    <span class="ee-field__hint ee-field__hint--warn">Aucun rayon dans cette zone — créez-en un d’abord.</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($barre_eid > 0 && $cascade_zone > 0 && $cascade_rayon > 0): ?>
                <div class="ee-field">
                    <label for="barre_c_etagere"><i class="fas fa-bars-staggered"></i> Étagère</label>
                    <select id="barre_c_etagere" name="c_etagere" required onchange="this.form.submit()">
                        <option value="">— Choisir une étagère —</option>
                        <?php foreach ($barre_etageres as $et): ?>
                        <option value="<?php echo (int) $et['id']; ?>" <?php echo (int) $et['id'] === $cascade_etagere ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $et['nom'], ENT_QUOTES, 'UTF-8'); ?>
                            (#<?php echo (int) ($et['numero'] ?? 0); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($barre_etageres === []): ?>
                    <span class="ee-field__hint ee-field__hint--warn">Aucune étagère sur ce rayon — créez-en une d’abord.</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($barre_pret): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_barre" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="etage_id" value="<?php echo $barre_eid; ?>">
            <input type="hidden" name="rayon_id" value="<?php echo (int) $cascade_rayon; ?>">
            <input type="hidden" name="etagere_id" value="<?php echo (int) $cascade_etagere; ?>">
            <div class="ee-modal__body ee-modal__body--details">
                <p class="ee-modal__level-kicker"><i class="fas fa-check-circle"></i> Emplacement cible sélectionné — renseignez la barre (QR généré automatiquement).</p>
                <div class="ee-field">
                    <label for="barre_numero"><i class="fas fa-hashtag"></i> Numéro</label>
                    <input type="number" id="barre_numero" name="numero" min="1" value="1" required>
                </div>
                <div class="ee-field">
                    <label for="barre_nom"><i class="fas fa-tag"></i> Nom</label>
                    <input type="text" id="barre_nom" name="nom" maxlength="100" placeholder="Ex. Barre B01">
                    <span class="ee-field__hint">Facultatif — utilisé sur les étiquettes et le scan.</span>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalBarre')">Annuler</button>
                <button type="submit" class="ee-modal__submit"><i class="fas fa-check"></i> Ajouter la barre</button>
            </div>
        </form>
        <?php else: ?>
        <div class="ee-modal__footer">
            <button type="button" class="ee-modal__cancel" onclick="closeModal('modalBarre')">Fermer</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ee-modal" id="modalPosition" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalPosition')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head"><h2 class="ee-modal__title"><i class="fas fa-crosshairs"></i> Ajouter une position</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalPosition')"><i class="fas fa-xmark"></i></button></div>
        <form method="get" class="ee-cascade-form">
            <input type="hidden" name="niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="modal" value="modalPosition">
            <input type="hidden" name="c_etage" value="<?php echo $cascade_etage > 0 ? $cascade_etage : $eid_actif; ?>">
            <input type="hidden" name="c_zone" value="<?php echo $cascade_zone; ?>">
            <input type="hidden" name="c_rayon" value="<?php echo $cascade_rayon; ?>">
            <div class="ee-modal__body ee-modal__body--cascade">
                <?php if ($cascade_rayon > 0): ?>
                <div class="ee-field">
                    <label>Étagère</label>
                    <select name="c_etagere" onchange="this.form.submit()">
                        <option value="">— Choisir —</option>
                        <?php
                        $ec2 = entrepot_hierarchie_liste_pour_cascade($cascade_etage > 0 ? $cascade_etage : $eid_actif, $cascade_zone, $cascade_rayon);
                        foreach ($ec2['etageres'] as $et):
                        ?>
                        <option value="<?php echo (int) $et['id']; ?>" <?php echo (int) $et['id'] === $cascade_etagere ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $et['nom'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </form>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_position" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <div class="ee-field">
                    <label for="position_barre_id">Barre</label>
                    <select id="position_barre_id" name="barre_id" required>
                        <option value="">— Choisir —</option>
                        <?php
                        $barres_pos = $cascade_etagere > 0
                            ? entrepot_hierarchie_liste_pour_cascade($cascade_etage > 0 ? $cascade_etage : $eid_actif, $cascade_zone, $cascade_rayon, $cascade_etagere)['barres']
                            : ($arbre_actif['barres'] ?? []);
                        foreach ($barres_pos as $b):
                        ?>
                        <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars((string) ($b['nom'] ?? 'Barre'), ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int) ($b['numero'] ?? 0); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ee-field"><label for="position_numero">Numéro</label><input type="number" id="position_numero" name="numero" min="1" value="1" required></div>
                <div class="ee-field"><label for="position_nom">Nom</label><input type="text" id="position_nom" name="nom" maxlength="80"></div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalPosition')">Annuler</button>
                <button type="submit" class="ee-modal__submit">Ajouter la position</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalAjouterChamp" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalAjouterChamp')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head"><h2 class="ee-modal__title"><i class="fas fa-plus-circle"></i> Ajouter un champ</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalAjouterChamp')"><i class="fas fa-xmark"></i></button></div>
        <?php if (!empty($mode_hierarchie_libre)): ?>
        <form method="post" id="eeFormAjouterChampLibre">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="hierarchie_def_ajouter" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <p class="ee-field__hint" style="margin-top:0;">Le nouveau champ (niveau) est ajouté en fin de chaîne. Réordonnez-le ensuite via <strong>Configurer la hiérarchie</strong> si besoin.</p>
                <div class="ee-field"><label for="def_label_new">Nom du champ *</label><input type="text" id="def_label_new" name="def_label" maxlength="100" required placeholder="Ex. Secteur, Allée, Casier…"></div>
                <div class="ee-field"><label for="def_icon_new">Icône Font Awesome</label><input type="text" id="def_icon_new" name="def_icon" maxlength="40" value="fa-cube" placeholder="fa-cube"></div>
                <div class="ee-field">
                    <label for="est_etiquette_qr_ee">Configurer étiquette / QR *</label>
                    <select id="est_etiquette_qr_ee" name="est_etiquette_qr">
                        <option value="0" selected>Non</option>
                        <option value="1">Oui — ce niveau porte l’étiquette et le QR</option>
                    </select>
                    <span class="ee-field__hint">Le code abrégé vient du Niveau (étage), pas de ce formulaire.</span>
                </div>
                <div class="ee-field" id="eeLieWrapChamp" hidden>
                    <label for="etiquette_lie_cible_ee">Hiérarchie liée *</label>
                    <select id="etiquette_lie_cible_ee" name="etiquette_lie_cible">
                        <option value="etage" selected>Niveau (code abrégé des étiquettes)</option>
                        <?php foreach ($hierarchie_defs_all as $d):
                            $oid = (int) ($d['id'] ?? 0);
                            if ($oid <= 0 || entrepot_hierarchie_def_est_etage($d)) {
                                continue;
                            }
                        ?>
                        <option value="niveau:<?php echo $oid; ?>"><?php echo htmlspecialchars((string) ($d['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (numéro uniquement)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ee-field__hint">Par défaut&nbsp;: Niveau. Les autres niveaux n’affichent que leur numéro sur l’étiquette.</span>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalAjouterChamp')">Annuler</button>
                <button type="submit" class="ee-modal__submit">Créer le champ</button>
            </div>
        </form>
        <script>
        (function () {
            var sel = document.getElementById('est_etiquette_qr_ee');
            var wrap = document.getElementById('eeLieWrapChamp');
            if (!sel || !wrap) return;
            function sync() { wrap.hidden = sel.value !== '1'; }
            sel.addEventListener('change', sync);
            sync();
        })();
        </script>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_champ_structure" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <div class="ee-field"><label for="label_champ">Nom du champ</label><input type="text" id="label_champ" name="label_champ" maxlength="100" required placeholder="Ex : Zones, Rayons…"></div>
                <div class="ee-field">
                    <label for="niveau_hierarchie">Niveau hiérarchique</label>
                    <select id="niveau_hierarchie" name="niveau_hierarchie" required>
                        <?php if ($niveaux_hierarchie_disponibles === []): ?>
                        <option value="">— Tous les niveaux sont déjà configurés —</option>
                        <?php else: ?>
                        <?php foreach ($niveaux_hierarchie_disponibles as $val => $lab): ?>
                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small class="form-hint">Les éléments de ce niveau s’ajoutent ensuite manuellement (comme Zones, Rayons, etc.).</small>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalAjouterChamp')">Annuler</button>
                <button type="submit" class="ee-modal__submit" <?php echo empty($niveaux_hierarchie_disponibles) ? 'disabled' : ''; ?>>Créer le champ</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="ee-modal" id="modalSupprimerChamp" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalSupprimerChamp')"></div>
    <div class="ee-modal__dialog ee-modal__dialog--wide" role="dialog">
        <div class="ee-modal__head"><h2 class="ee-modal__title"><i class="fas fa-minus-circle"></i> Supprimer un champ</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalSupprimerChamp')"><i class="fas fa-xmark"></i></button></div>
        <form method="post" id="ee_form_supprimer_champ">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <?php if (!empty($mode_hierarchie_libre)): ?>
            <input type="hidden" name="hierarchie_def_supprimer" value="1">
            <?php else: ?>
            <input type="hidden" name="supprimer_champ_structure" value="1">
            <?php endif; ?>
            <input type="hidden" name="confirm_suppression_champ" id="ee_confirm_suppression_champ" value="">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <div class="ee-field">
                    <label for="champ_id">Champ à supprimer</label>
                    <select id="champ_id" name="<?php echo !empty($mode_hierarchie_libre) ? 'def_id' : 'champ_id'; ?>" required>
                        <option value="">— Choisir —</option>
                        <?php if (!empty($mode_hierarchie_libre)): ?>
                        <?php foreach ($hierarchie_defs_all as $sc):
                            if (entrepot_hierarchie_def_est_etage($sc)) {
                                continue;
                            }
                        ?>
                        <option value="<?php echo (int) ($sc['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($sc['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <?php foreach ($structure_champs_tous as $sc): ?>
                        <option value="<?php echo (int) $sc['id']; ?>"><?php echo htmlspecialchars((string) $sc['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="ee-champ-impact" id="ee_champ_impact" hidden>
                    <div class="ee-champ-impact__alert" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <div>
                            <strong id="ee_champ_impact_title">Impact de la suppression</strong>
                            <p id="ee_champ_impact_intro" class="ee-champ-impact__intro"></p>
                        </div>
                    </div>
                    <div class="ee-champ-impact__grid" id="ee_champ_impact_stats"></div>
                    <ul class="ee-champ-impact__list" id="ee_champ_impact_warnings"></ul>
                    <label class="ee-champ-impact__confirm">
                        <input type="checkbox" id="ee_champ_impact_check">
                        <span>Je comprends les conséquences et souhaite supprimer définitivement ce champ.</span>
                    </label>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalSupprimerChamp')">Annuler</button>
                <button type="submit" class="ee-modal__submit ee-modal__submit--danger" id="ee_btn_supprimer_champ" disabled>Supprimer</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalEditHierarchie" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalEditHierarchie')"></div>
    <div class="ee-modal__dialog" role="dialog" aria-labelledby="ee_edit_title">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title" id="ee_edit_title"><i class="fas fa-pen" id="ee_edit_icon"></i> <span id="ee_edit_title_text">Modifier</span></h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalEditHierarchie')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post" id="ee_form_edit_hierarchie">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="modifier_entite" value="1">
            <input type="hidden" name="entite_table" id="ee_edit_table" value="">
            <input type="hidden" name="entite_id" id="ee_edit_id" value="">
            <input type="hidden" name="etage_id" id="ee_edit_etage_id" value="">
            <input type="hidden" name="numero_niveau" id="ee_edit_numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <p class="ee-field__hint" id="ee_edit_hint"></p>
                <div class="ee-field">
                    <label for="ee_edit_numero">Numéro</label>
                    <input type="number" id="ee_edit_numero" name="numero" min="1" max="9999" required>
                </div>
                <div class="ee-field">
                    <label for="ee_edit_nom">Nom</label>
                    <input type="text" id="ee_edit_nom" name="nom" maxlength="120" required placeholder="Nom affiché">
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalEditHierarchie')">Annuler</button>
                <button type="submit" class="ee-modal__submit"><i class="fas fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalSupprimerHierarchie" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalSupprimerHierarchie')"></div>
    <div class="ee-modal__dialog ee-modal__dialog--wide" role="dialog" aria-labelledby="ee_delete_h_title">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title" id="ee_delete_h_title"><i class="fas fa-trash-can"></i> <span id="ee_delete_h_title_text">Confirmer la suppression</span></h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalSupprimerHierarchie')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post" id="ee_form_delete_hierarchie">
            <input type="hidden" name="csrf_token" id="ee_delete_h_csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="confirm_suppression_hierarchie" id="ee_delete_h_confirm" value="">
            <input type="hidden" name="numero_niveau" id="ee_delete_h_numero_niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="supprimer_entite" id="ee_delete_h_supprimer_entite" value="1">
            <input type="hidden" name="supprimer_niveau" id="ee_delete_h_supprimer_niveau" value="">
            <input type="hidden" name="entite_table" id="ee_delete_h_table" value="">
            <input type="hidden" name="entite_id" id="ee_delete_h_id" value="">
            <input type="hidden" name="etage_id" id="ee_delete_h_etage_id" value="">
            <input type="hidden" name="numero_etage" id="ee_delete_h_numero_etage" value="">
            <div class="ee-modal__body">
                <div class="ee-champ-impact" id="ee_delete_h_impact">
                    <div class="ee-champ-impact__alert" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <div>
                            <strong id="ee_delete_h_impact_title">Impact de la suppression</strong>
                            <p id="ee_delete_h_impact_intro" class="ee-champ-impact__intro"></p>
                        </div>
                    </div>
                    <div class="ee-champ-impact__grid" id="ee_delete_h_impact_stats"></div>
                    <ul class="ee-champ-impact__list" id="ee_delete_h_impact_warnings"></ul>
                    <label class="ee-champ-impact__confirm">
                        <input type="checkbox" id="ee_delete_h_impact_check">
                        <span>Je comprends les conséquences et souhaite supprimer définitivement.</span>
                    </label>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalSupprimerHierarchie')">Annuler</button>
                <button type="submit" class="ee-modal__submit ee-modal__submit--danger" id="ee_delete_h_submit" disabled>Supprimer</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal ee-modal--drill" id="modalEeDrillNav" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalEeDrillNav')"></div>
    <div class="ee-modal__dialog ee-modal__dialog--wide" role="dialog" aria-labelledby="ee_drill_modal_title">
        <div class="ee-modal__head">
            <div class="ee-modal__head-top ee-drill-modal__head-top">
                <button type="button" class="ee-h-crumb__back ee-drill-modal__back" data-ee-drill-modal-back hidden aria-label="Retour">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </button>
                <div class="ee-drill-modal__titles">
                    <h2 class="ee-modal__title" id="ee_drill_modal_title">
                        <i class="fas fa-sitemap" data-ee-drill-modal-icon aria-hidden="true"></i>
                        <span data-ee-drill-modal-title-text></span>
                    </h2>
                    <p class="ee-modal__subtitle" data-ee-drill-modal-subtitle hidden></p>
                </div>
            </div>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalEeDrillNav')" aria-label="Fermer"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="ee-modal__body ee-modal__body--drill" data-ee-drill-modal-body></div>
    </div>
</div>

<?php
$hierarchie_defs_all = isset($hierarchie_defs_all) && is_array($hierarchie_defs_all) ? $hierarchie_defs_all : [];
$hierarchie_defs = isset($hierarchie_defs) && is_array($hierarchie_defs) ? $hierarchie_defs : [];
$noeuds_par_niveau = isset($noeuds_par_niveau) && is_array($noeuds_par_niveau) ? $noeuds_par_niveau : [];
$defs_impact_suppression = isset($defs_impact_suppression) && is_array($defs_impact_suppression) ? $defs_impact_suppression : [];
$mode_hierarchie_libre = !empty($mode_hierarchie_libre);
?>
<?php if ($mode_hierarchie_libre): ?>
<div class="ee-modal" id="modalAjouterNoeud" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalAjouterNoeud')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title"><i class="fas fa-plus-circle"></i> <span id="ee_noeud_modal_title">Ajouter un élément</span></h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalAjouterNoeud')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post" id="formAjouterNoeud">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_noeud" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="etage_id" id="noeud_etage_id" value="<?php echo $eid_actif; ?>">
            <input type="hidden" name="niveau_id" id="noeud_niveau_id_hidden" value="">
            <input type="hidden" name="parent_id" id="noeud_parent_id_hidden" value="">
            <div class="ee-modal__body">
                <p class="ee-field__hint" id="ee_noeud_cascade_hint" style="margin-top:0;">Sélectionnez d’abord les niveaux parents selon l’ordre de la hiérarchie, puis renseignez cet élément.</p>
                <div id="ee_noeud_cascade" class="ee-noeud-cascade" aria-live="polite"></div>
                <div class="ee-field"><label for="noeud_nom">Nom *</label><input type="text" id="noeud_nom" name="nom" maxlength="100" required></div>
                <div class="ee-field">
                    <label for="noeud_numero">Numéro (optionnel)</label>
                    <input type="number" id="noeud_numero" name="numero" min="1" step="1" placeholder="Auto">
                    <span class="ee-field__hint">Doublon contrôlé uniquement parmi les éléments du même type sous le même parent.</span>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalAjouterNoeud')">Annuler</button>
                <button type="submit" class="ee-modal__submit">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalModifierNoeud" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalModifierNoeud')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title"><i class="fas fa-pen"></i> Modifier l’élément</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalModifierNoeud')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="modifier_noeud" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="noeud_id" id="mod_noeud_id" value="">
            <div class="ee-modal__body">
                <div class="ee-field"><label for="mod_noeud_nom">Nom *</label><input type="text" id="mod_noeud_nom" name="nom" maxlength="100" required></div>
                <div class="ee-field">
                    <label for="mod_noeud_numero">Numéro *</label>
                    <input type="number" id="mod_noeud_numero" name="numero" min="1" required>
                    <span class="ee-field__hint">Doublon contrôlé uniquement parmi les éléments du même type sous le même parent.</span>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalModifierNoeud')">Annuler</button>
                <button type="submit" class="ee-modal__submit">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalSupprimerNoeud" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalSupprimerNoeud')"></div>
    <div class="ee-modal__dialog" role="dialog">
        <div class="ee-modal__head">
            <h2 class="ee-modal__title"><i class="fas fa-trash-can"></i> Supprimer l’élément</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalSupprimerNoeud')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post" id="formSupprimerNoeud">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="supprimer_noeud" value="1">
            <input type="hidden" name="confirm_suppression_hierarchie" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <input type="hidden" name="noeud_id" id="del_noeud_id" value="">
            <div class="ee-modal__body">
                <p id="del_noeud_msg">Cet élément et ses enfants seront supprimés. Les produits liés seront détachés.</p>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalSupprimerNoeud')">Annuler</button>
                <button type="submit" class="ee-modal__submit ee-modal__submit--danger">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script type="application/json" id="ee-noeuds-par-niveau"><?php
echo json_encode($noeuds_par_niveau, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="ee-hierarchie-defs"><?php
$defs_json = [];
foreach ($hierarchie_defs as $d) {
    $defs_json[] = [
        'id' => (int) ($d['id'] ?? 0),
        'slug' => (string) ($d['slug'] ?? ''),
        'label' => (string) ($d['label'] ?? ''),
        'icon' => (string) ($d['icon'] ?? 'fa-cube'),
        'is_etage' => entrepot_hierarchie_def_est_etage($d) ? 1 : 0,
    ];
}
echo json_encode($defs_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="ee-etages-cascade"><?php
echo json_encode(isset($ee_etages_cascade) ? $ee_etages_cascade : [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="ee-etage-actif"><?php
echo json_encode([
    'id' => (int) $eid_actif,
    'numero' => (int) $num_actif,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<?php endif; ?>


