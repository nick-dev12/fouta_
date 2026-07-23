/**
 * Paramètres — emplacement entrepôt (hiérarchie CRUD par onglets).
 */
(function () {
    'use strict';

    function lockBody(open) {
        document.body.style.overflow = open ? 'hidden' : '';
    }

    function openModal(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        lockBody(true);
    }

    function closeModal(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        var anyOpen = document.querySelector('.ee-modal.is-open');
        lockBody(!!anyOpen);
    }

    window.openModal = openModal;
    window.closeModal = closeModal;

    window.openModalEntrepotEmplacement = function () { openModal('modalNiveau'); };
    window.closeModalEntrepotEmplacement = function () { closeModal('modalNiveau'); };
    window.openModalAjouterChamp = function () { openModal('modalAjouterChamp'); };
    window.closeModalAjouterChamp = function () { closeModal('modalAjouterChamp'); };
    window.openModalSupprimerChamp = function () { openModal('modalSupprimerChamp'); };
    window.closeModalSupprimerChamp = function () { closeModal('modalSupprimerChamp'); };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.ee-modal.is-open').forEach(function (m) {
                closeModal(m);
            });
        }
    });

    /**
     * Soumission cascade GET : vide les niveaux enfants lors d’un changement parent.
     */
    window.eeCascadeResetAndSubmit = function (form, mode, from) {
        if (!form) {
            return;
        }
        var zone = form.querySelector('[name="c_zone"]');
        var rayon = form.querySelector('[name="c_rayon"]');
        var etagere = form.querySelector('[name="c_etagere"]');
        if (from === 'zone') {
            if (rayon) {
                rayon.removeAttribute('name');
            }
            if (etagere) {
                etagere.removeAttribute('name');
            }
        } else if (from === 'rayon') {
            if (etagere) {
                etagere.removeAttribute('name');
            }
        } else {
            if (zone) {
                zone.removeAttribute('name');
            }
            if (rayon) {
                rayon.removeAttribute('name');
            }
            if (etagere) {
                etagere.removeAttribute('name');
            }
        }
        form.submit();
    };

    function eeInitSupprimerChampModal() {
        var form = document.getElementById('ee_form_supprimer_champ');
        var select = document.getElementById('champ_id');
        var impactBox = document.getElementById('ee_champ_impact');
        var impactTitle = document.getElementById('ee_champ_impact_title');
        var impactIntro = document.getElementById('ee_champ_impact_intro');
        var impactStats = document.getElementById('ee_champ_impact_stats');
        var impactWarnings = document.getElementById('ee_champ_impact_warnings');
        var impactCheck = document.getElementById('ee_champ_impact_check');
        var confirmField = document.getElementById('ee_confirm_suppression_champ');
        var submitBtn = document.getElementById('ee_btn_supprimer_champ');
        var impactData = window.EE_CHAMPS_IMPACT || {};

        if (!form || !select || !impactBox) {
            return;
        }

        function resetImpact() {
            impactBox.hidden = true;
            if (impactCheck) {
                impactCheck.checked = false;
            }
            if (confirmField) {
                confirmField.value = '';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            if (impactStats) {
                impactStats.innerHTML = '';
            }
            if (impactWarnings) {
                impactWarnings.innerHTML = '';
            }
        }

        function renderImpact(id) {
            resetImpact();
            if (!id || !impactData[id]) {
                return;
            }
            var data = impactData[id];
            impactBox.hidden = false;
            if (impactTitle) {
                impactTitle.textContent = 'Impact — « ' + (data.label || '') + ' »';
            }
            if (impactIntro) {
                var intro = 'Vous êtes sur le point de supprimer le champ « ' + (data.label || '') + ' »';
                if (data.colonne_db) {
                    intro += ' (colonne ' + data.colonne_db + ')';
                }
                intro += '.';
                impactIntro.textContent = intro;
            }
            if (impactStats) {
                var cards = [];
                if (data.niveau_label) {
                    cards.push('<div class="ee-champ-impact__stat"><span>Niveau hiérarchique</span><strong>' + data.niveau_label + '</strong></div>');
                }
                (data.entites || []).forEach(function (ent) {
                    cards.push('<div class="ee-champ-impact__stat"><span>' + ent.label + '</span><strong>' + ent.count + '</strong></div>');
                });
                if ((data.elements_champ || 0) > 0) {
                    cards.push('<div class="ee-champ-impact__stat"><span>Éléments nommés</span><strong>' + data.elements_champ + '</strong></div>');
                }
                if ((data.barres_liees || 0) > 0) {
                    cards.push('<div class="ee-champ-impact__stat"><span>Barres liées</span><strong>' + data.barres_liees + '</strong></div>');
                }
                cards.push('<div class="ee-champ-impact__stat ee-champ-impact__stat--warn"><span>Produits avec emplacement</span><strong>' + (data.produits_lies || 0) + '</strong></div>');
                impactStats.innerHTML = cards.join('');
            }
            if (impactWarnings) {
                impactWarnings.innerHTML = '';
                (data.avertissements || []).forEach(function (msg) {
                    var li = document.createElement('li');
                    li.textContent = msg;
                    impactWarnings.appendChild(li);
                });
            }
        }

        select.addEventListener('change', function () {
            renderImpact(select.value);
        });

        if (impactCheck) {
            impactCheck.addEventListener('change', function () {
                var ok = impactCheck.checked && select.value !== '';
                if (submitBtn) {
                    submitBtn.disabled = !ok;
                }
                if (confirmField) {
                    confirmField.value = ok ? '1' : '';
                }
            });
        }

        form.addEventListener('submit', function (e) {
            if (!select.value || !impactCheck || !impactCheck.checked) {
                e.preventDefault();
                window.alert('Veuillez sélectionner un champ, lire l’impact et cocher la case de confirmation.');
                return;
            }
            var data = impactData[select.value] || {};
            var msg = 'Confirmer la suppression du champ « ' + (data.label || '') + ' » ?';
            if ((data.produits_lies || 0) > 0) {
                msg += '\n\n' + data.produits_lies + ' produit(s) perdront leur emplacement assigné.';
            }
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', eeInitSupprimerChampModal);

    function parseJsonEl(id) {
        var el = document.getElementById(id);
        if (!el || !el.textContent) {
            return null;
        }
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    window.eeMoveDef = function (btn, dir) {
        var item = btn && btn.closest ? btn.closest('.ee-config-hierarchie-item') : null;
        var list = document.getElementById('eeConfigHierarchieList');
        if (!item || !list) {
            return;
        }
        if (dir < 0 && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (dir > 0 && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }
    };

    window.eeRefreshNoeudParent = function () {
        if (typeof window.eeBuildNoeudCascade === 'function') {
            window.eeBuildNoeudCascade();
        }
    };

    window.eeBuildNoeudCascade = function () {
        var wrap = document.getElementById('ee_noeud_cascade');
        var title = document.getElementById('ee_noeud_modal_title');
        var etageHidden = document.getElementById('noeud_etage_id');
        var niveauHidden = document.getElementById('noeud_niveau_id_hidden');
        var parentHidden = document.getElementById('noeud_parent_id_hidden');
        var defs = parseJsonEl('ee-hierarchie-defs') || [];
        var byNiveau = parseJsonEl('ee-noeuds-par-niveau') || {};
        var etages = parseJsonEl('ee-etages-cascade') || [];
        var etageActif = parseJsonEl('ee-etage-actif') || {};
        var targetId = parseInt(window.EE_NOEUD_TARGET_NIVEAU || 0, 10);

        if (!wrap || !niveauHidden) {
            return;
        }

        var targetIdx = -1;
        var targetDef = null;
        defs.forEach(function (d, i) {
            if (parseInt(d.id, 10) === targetId) {
                targetIdx = i;
                targetDef = d;
            }
        });

        if (title) {
            title.textContent = targetDef && targetDef.label
                ? ('Ajouter — ' + targetDef.label)
                : 'Ajouter un élément';
        }

        wrap.innerHTML = '';
        if (targetIdx < 0 || (targetDef && parseInt(targetDef.is_etage, 10) === 1)) {
            return;
        }

        niveauHidden.value = String(targetId);

        // Ancêtres : defs[0] .. defs[targetIdx-1]
        var ancestors = defs.slice(0, targetIdx);
        var state = {
            etageId: parseInt(etageActif.id, 10) || 0,
            selections: {}
        };

        function fillOptions(sel, items, placeholder, valueKey, labelFn) {
            sel.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = placeholder || '— Choisir —';
            sel.appendChild(empty);
            items.forEach(function (it) {
                var opt = document.createElement('option');
                opt.value = String(it[valueKey]);
                opt.textContent = labelFn(it);
                sel.appendChild(opt);
            });
        }

        function syncHidden() {
            var lastParent = 0;
            var etageId = state.etageId;
            ancestors.forEach(function (anc, i) {
                var val = state.selections[i] || 0;
                if (parseInt(anc.is_etage, 10) === 1) {
                    etageId = val;
                } else if (val > 0) {
                    lastParent = val;
                }
            });
            if (etageHidden) {
                etageHidden.value = String(etageId || 0);
            }
            if (parentHidden) {
                parentHidden.value = String(lastParent || '');
            }
        }

        function rebuildFrom(level) {
            // Rebuild selects from `level` to end
            for (var i = level; i < ancestors.length; i++) {
                var field = wrap.querySelector('[data-cascade-level="' + i + '"]');
                if (!field) {
                    continue;
                }
                var sel = field.querySelector('select');
                if (!sel) {
                    continue;
                }
                var anc = ancestors[i];
                var isEtage = parseInt(anc.is_etage, 10) === 1;
                if (isEtage) {
                    fillOptions(sel, etages, '— Choisir ' + (anc.label || 'Niveau') + ' —', 'id', function (et) {
                        var code = et.code_abrege ? (' (' + et.code_abrege + ')') : '';
                        return (et.nom || ('#' + et.numero_etage)) + code;
                    });
                    if (state.etageId > 0) {
                        sel.value = String(state.etageId);
                        state.selections[i] = state.etageId;
                    }
                } else {
                    var parentVal = 0;
                    var etageFilter = state.etageId;
                    var cascadeOk = true;
                    for (var j = 0; j < i; j++) {
                        if (!(state.selections[j] > 0)) {
                            cascadeOk = false;
                            break;
                        }
                        if (parseInt(ancestors[j].is_etage, 10) === 1) {
                            etageFilter = state.selections[j];
                        }
                    }
                    if (!cascadeOk) {
                        parentVal = -1;
                    } else if (i > 0) {
                        var imm = ancestors[i - 1];
                        if (parseInt(imm.is_etage, 10) === 1) {
                            parentVal = 0;
                            etageFilter = state.selections[i - 1] || etageFilter;
                        } else {
                            parentVal = state.selections[i - 1] || 0;
                        }
                    }
                    var list = byNiveau[anc.id] || byNiveau[String(anc.id)] || [];
                    var filtered = list.filter(function (n) {
                        if (parentVal < 0) {
                            return false;
                        }
                        if (etageFilter > 0 && parseInt(n.etage_id, 10) !== etageFilter) {
                            return false;
                        }
                        return (parseInt(n.parent_id, 10) || 0) === parentVal;
                    });
                    fillOptions(
                        sel,
                        filtered,
                        parentVal < 0
                            ? '— Choisissez d’abord le niveau précédent —'
                            : ('— Choisir ' + (anc.label || 'parent') + ' —'),
                        'id',
                        function (n) {
                            return (n.nom || ('#' + n.numero));
                        }
                    );
                    state.selections[i] = 0;
                }
            }
            syncHidden();
        }

        ancestors.forEach(function (anc, i) {
            var field = document.createElement('div');
            field.className = 'ee-field';
            field.setAttribute('data-cascade-level', String(i));
            var lab = document.createElement('label');
            lab.textContent = (anc.label || 'Niveau') + ' *';
            lab.setAttribute('for', 'ee_cascade_' + i);
            var sel = document.createElement('select');
            sel.id = 'ee_cascade_' + i;
            sel.required = true;
            sel.setAttribute('data-cascade-level', String(i));
            sel.addEventListener('change', function () {
                var v = parseInt(sel.value, 10) || 0;
                state.selections[i] = v;
                if (parseInt(anc.is_etage, 10) === 1) {
                    state.etageId = v;
                }
                // clear downstream
                for (var k = i + 1; k < ancestors.length; k++) {
                    state.selections[k] = 0;
                }
                rebuildFrom(i + 1);
            });
            field.appendChild(lab);
            field.appendChild(sel);
            wrap.appendChild(field);
        });

        rebuildFrom(0);
        syncHidden();
    };

    window.eeOpenAjouterNoeud = function (niveauId) {
        window.EE_NOEUD_TARGET_NIVEAU = niveauId || 0;
        var form = document.getElementById('formAjouterNoeud');
        if (form) {
            form.reset();
        }
        if (typeof window.eeBuildNoeudCascade === 'function') {
            window.eeBuildNoeudCascade();
        }
        openModal('modalAjouterNoeud');
    };

    window.eeOpenModifierNoeud = function (id, nom, numero) {
        var idEl = document.getElementById('mod_noeud_id');
        var nomEl = document.getElementById('mod_noeud_nom');
        var numEl = document.getElementById('mod_noeud_numero');
        if (idEl) {
            idEl.value = String(id || '');
        }
        if (nomEl) {
            nomEl.value = nom || '';
        }
        if (numEl) {
            numEl.value = String(numero || 1);
        }
        openModal('modalModifierNoeud');
    };

    window.eeOpenDeleteNoeudLibre = function (btn) {
        if (!btn) {
            return;
        }
        var id = btn.getAttribute('data-ee-noeud-id') || '';
        var nom = btn.getAttribute('data-ee-noeud-nom') || '';
        var idEl = document.getElementById('del_noeud_id');
        var msg = document.getElementById('del_noeud_msg');
        if (idEl) {
            idEl.value = id;
        }
        if (msg) {
            msg.textContent = 'Supprimer « ' + nom + ' » et tous ses enfants ? Les produits liés seront détachés.';
        }
        openModal('modalSupprimerNoeud');
    };

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('formAjouterNoeud');
        if (form) {
            form.addEventListener('submit', function (e) {
                var etageHidden = document.getElementById('noeud_etage_id');
                var niveauHidden = document.getElementById('noeud_niveau_id_hidden');
                if (!niveauHidden || !niveauHidden.value) {
                    e.preventDefault();
                    alert('Niveau hiérarchique invalide.');
                    return;
                }
                if (etageHidden && (!etageHidden.value || etageHidden.value === '0')) {
                    e.preventDefault();
                    alert('Sélectionnez d’abord le niveau parent (étage).');
                }
            });
        }
    });
})();
