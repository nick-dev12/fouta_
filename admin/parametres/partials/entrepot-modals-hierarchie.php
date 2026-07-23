<?php
/**
 * Modals CRUD hiérarchie entrepôt.
 *
 * Variables : $niveaux, $etage_id_actif, $numero_niveau_actif, $cascade_lists,
 * $all_niveaux_select, $structure_champs_tous, $niveaux_hierarchie_options,
 * $prochain_numero_niveau, $cascade_zone, $cascade_rayon, $cascade_etagere
 */
$csrf = htmlspecialchars($_SESSION['admin_csrf']);
$num_actif = (int) $numero_niveau_actif;
$eid_actif = (int) $etage_id_actif;

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
            <h2 class="ee-modal__title"><i class="fas fa-layer-group"></i> Ajouter un niveau</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalNiveau')"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_niveau" value="1">
            <div class="ee-modal__body">
                <p class="ee-modal__level-kicker">Sera enregistré comme <strong>Niveau <?php echo (int) $prochain_numero_niveau; ?></strong></p>
                <div class="ee-field">
                    <label for="ee_nom_niveau">Nom du niveau</label>
                    <input type="text" id="ee_nom_niveau" name="nom_niveau" maxlength="100" required placeholder="Ex. Rez-de-chaussée">
                </div>
                <div class="ee-field">
                    <label for="ee_code_abrege">Code abrégé (étiquettes barres)</label>
                    <input type="text" id="ee_code_abrege" name="code_abrege" maxlength="10" required placeholder="Ex. RDC, B01">
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
        <div class="ee-modal__head"><h2 class="ee-modal__title"><i class="fas fa-plus-circle"></i> Ajouter un champ structurel</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalAjouterChamp')"><i class="fas fa-xmark"></i></button></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ajouter_champ_structure" value="1">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <div class="ee-field"><label for="label_champ">Nom du champ</label><input type="text" id="label_champ" name="label_champ" maxlength="100" required></div>
                <div class="ee-field">
                    <label for="niveau_hierarchie">Niveau hiérarchique</label>
                    <select id="niveau_hierarchie" name="niveau_hierarchie" required>
                        <?php foreach ($niveaux_hierarchie_options as $val => $lab): ?>
                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ee-field"><label for="max_champ">Maximum</label><input type="number" id="max_champ" name="max_champ" min="1" max="500" value="50" required></div>
                <div class="ee-field ee-field--checkbox">
                    <label class="ee-checkbox-label">
                        <input type="checkbox" name="lie_barre" value="1">
                        <span>Lier aux barres (remplace étagère système pour ce champ)</span>
                    </label>
                </div>
            </div>
            <div class="ee-modal__footer">
                <button type="button" class="ee-modal__cancel" onclick="closeModal('modalAjouterChamp')">Annuler</button>
                <button type="submit" class="ee-modal__submit">Créer le champ</button>
            </div>
        </form>
    </div>
</div>

<div class="ee-modal" id="modalSupprimerChamp" aria-hidden="true">
    <div class="ee-modal__backdrop" onclick="closeModal('modalSupprimerChamp')"></div>
    <div class="ee-modal__dialog ee-modal__dialog--wide" role="dialog">
        <div class="ee-modal__head"><h2 class="ee-modal__title"><i class="fas fa-minus-circle"></i> Supprimer un champ</h2>
            <button type="button" class="ee-modal__close" onclick="closeModal('modalSupprimerChamp')"><i class="fas fa-xmark"></i></button></div>
        <form method="post" id="ee_form_supprimer_champ">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="supprimer_champ_structure" value="1">
            <input type="hidden" name="confirm_suppression_champ" id="ee_confirm_suppression_champ" value="">
            <input type="hidden" name="numero_niveau" value="<?php echo $num_actif; ?>">
            <div class="ee-modal__body">
                <div class="ee-field">
                    <label for="champ_id">Champ à supprimer</label>
                    <select id="champ_id" name="champ_id" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($structure_champs_tous as $sc): ?>
                        <option value="<?php echo (int) $sc['id']; ?>"><?php echo htmlspecialchars((string) $sc['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
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
