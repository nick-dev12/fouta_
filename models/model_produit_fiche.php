<?php
/**
 * LES FAITS D'UNE PIÈCE — ce que la fiche dit d'elle, en toutes lettres.
 * Programmation procédurale uniquement.
 *
 * Reprise de la grille « fiche-facts » de la fiche pièce de FPL natif
 * (admin/piece.php). Là-bas chaque fait est écrit à la main dans le HTML ;
 * ici on les rassemble en une liste ordonnée, pour que la page n'ait plus
 * qu'à la parcourir — et pour qu'on puisse la vérifier sans passer par HTTP.
 *
 * RÈGLE : un fait vide ne descend pas dans la liste. La fiche d'une pièce
 * bien remplie est dense, celle d'une pièce nue reste courte — jamais une
 * colonne de tirets.
 */

require_once __DIR__ . '/../includes/fpl_ui.php';
require_once __DIR__ . '/../includes/fpl_texte.php';
require_once __DIR__ . '/model_produits.php';
require_once __DIR__ . '/../includes/produit_formulaire_champs.php';

/**
 * Une table existe-t-elle ? On le demande à la base, on ne l'attrape pas
 * dans un catch : une table absente se distingue ainsi d'un nom mal écrit.
 *
 * @param string $table
 * @return bool
 */
function produit_fiche_table_ok($table)
{
    global $db;
    static $connues = [];
    if (isset($connues[$table])) {
        return $connues[$table];
    }
    if (!$db) {
        return false;
    }
    $trouvee = $db->query('SHOW TABLES LIKE ' . $db->quote($table))->fetchColumn();
    $connues[$table] = ($trouvee !== false && $trouvee !== null);

    return $connues[$table];
}

/**
 * Les valeurs d'un champ à choix multiples : le catalogue les enregistre en
 * JSON (« ["Rouge","Noir"] »), mais les plus anciennes lignes portent encore
 * du texte libre. On rend les deux de la même façon.
 *
 * @param mixed $valeur
 * @return array<int, string>
 */
function produit_fiche_valeurs_liste($valeur)
{
    $brut = trim((string) $valeur);
    if ($brut === '' || $brut === '[]' || $brut === 'null') {
        return [];
    }

    if ($brut[0] === '[' || $brut[0] === '{') {
        $decode = json_decode($brut, true);
        if (is_array($decode)) {
            $sorties = [];
            foreach ($decode as $item) {
                $item = trim((string) (is_array($item) ? implode(' ', $item) : $item));
                if ($item !== '') {
                    $sorties[] = $item;
                }
            }

            return $sorties;
        }
    }

    $sorties = [];
    foreach (preg_split('/\s*[,;]\s*/', $brut) as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $sorties[] = $item;
        }
    }

    return $sorties;
}

/**
 * Le nom d'un administrateur, pour dire QUI a ajouté ou modifié la pièce.
 *
 * @param mixed $admin_id
 * @return string
 */
function produit_fiche_admin_nom($admin_id)
{
    global $db;
    static $noms = [];
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !$db) {
        return '';
    }
    if (isset($noms[$admin_id])) {
        return $noms[$admin_id];
    }

    $stmt = $db->prepare('SELECT prenom, nom FROM admin WHERE id = :id');
    $stmt->execute([':id' => $admin_id]);
    $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
    $noms[$admin_id] = $ligne
        ? trim((string) ($ligne['prenom'] ?? '') . ' ' . (string) ($ligne['nom'] ?? ''))
        : '';

    return $noms[$admin_id];
}

/**
 * Le nom de la sous-catégorie — le rayon précis où la pièce est classée.
 *
 * @param mixed $sous_categorie_id
 * @return string
 */
function produit_fiche_sous_categorie_nom($sous_categorie_id)
{
    global $db;
    $sous_categorie_id = (int) $sous_categorie_id;
    if ($sous_categorie_id <= 0 || !$db || !produit_fiche_table_ok('sous_categories')) {
        return '';
    }

    $stmt = $db->prepare('SELECT nom FROM sous_categories WHERE id = :id');
    $stmt->execute([':id' => $sous_categorie_id]);

    return (string) ($stmt->fetchColumn() ?: '');
}

/**
 * Les modèles de camion compatibles : la table pivot d'abord (une pièce peut
 * aller sur plusieurs modèles), la colonne modele_id de la pièce à défaut.
 *
 * @param array<string, mixed> $produit
 * @return array<int, string>
 */
function produit_fiche_modeles_noms(array $produit)
{
    global $db;
    if (!$db || !produit_fiche_table_ok('vehicule_modeles')) {
        return [];
    }

    $ids = [];
    if (produit_fiche_table_ok('produit_modeles')) {
        $stmt = $db->prepare('SELECT modele_id FROM produit_modeles WHERE produit_id = :p');
        $stmt->execute([':p' => (int) ($produit['id'] ?? 0)]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[] = (int) $id;
        }
    }
    if ($ids === [] && (int) ($produit['modele_id'] ?? 0) > 0) {
        $ids[] = (int) $produit['modele_id'];
    }
    if ($ids === []) {
        return [];
    }

    $liste = implode(',', array_map('intval', $ids));
    $noms = $db->query('SELECT nom FROM vehicule_modeles WHERE id IN (' . $liste . ') ORDER BY nom')
        ->fetchAll(PDO::FETCH_COLUMN);

    return array_map('strval', $noms);
}

/**
 * La génération du véhicule et les années qu'elle couvre.
 *
 * @param mixed $generation_id
 * @return array{nom: string, periode: string}
 */
function produit_fiche_generation($generation_id)
{
    global $db;
    $generation_id = (int) $generation_id;
    if ($generation_id <= 0 || !$db || !produit_fiche_table_ok('vehicule_generations')) {
        return ['nom' => '', 'periode' => ''];
    }

    $stmt = $db->prepare('SELECT nom, annee_debut, annee_fin FROM vehicule_generations WHERE id = :id');
    $stmt->execute([':id' => $generation_id]);
    $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ligne) {
        return ['nom' => '', 'periode' => ''];
    }

    $debut = (int) ($ligne['annee_debut'] ?? 0);
    $fin = (int) ($ligne['annee_fin'] ?? 0);
    if ($debut > 0 && $fin > 0) {
        $periode = $debut . ' – ' . $fin;
    } elseif ($debut > 0) {
        $periode = 'depuis ' . $debut;
    } elseif ($fin > 0) {
        $periode = 'jusqu\'en ' . $fin;
    } else {
        $periode = '';
    }

    return ['nom' => (string) ($ligne['nom'] ?? ''), 'periode' => $periode];
}

/**
 * LA GRILLE DES FAITS. Chaque entrée est ['k' => intitulé, 'v' => HTML déjà
 * échappé]. L'ordre est celui de FPL natif : ce qui identifie la pièce en
 * rayon d'abord, ce qui la situe sur le camion ensuite, l'argent après, et
 * les mains qui l'ont touchée en dernier.
 *
 * @param array<string, mixed> $produit Une ligne de produits, filtre d'accès déjà appliqué
 * @return array<int, array{k: string, v: string}>
 */
function produit_fiche_faits(array $produit)
{
    $faits = [];

    // --- Le rayon : c'est là qu'on va la chercher ---
    if (pf_champ_visible('categorie_id')) {
        $rayon = trim((string) ($produit['categorie_nom'] ?? ''));
        $sous = pf_champ_visible('sous_categorie_id')
            ? produit_fiche_sous_categorie_nom($produit['sous_categorie_id'] ?? 0)
            : '';
        if ($rayon !== '' || $sous !== '') {
            $texte = fpl_e($rayon);
            if ($sous !== '') {
                $texte = ($texte !== '' ? $texte . ' <span class="fpl-fait__sep">·</span> ' : '') . fpl_e($sous);
            }
            $faits[] = ['k' => 'Rayon', 'v' => $texte];
        }
    }

    // --- Les références gravées sur la pièce ---
    $oem = trim((string) ($produit['reference_oem'] ?? ''));
    if ($oem !== '') {
        $faits[] = ['k' => 'Réf. OEM', 'v' => '<span class="fpl-fait__mono">' . fpl_e($oem) . '</span>'];
    }

    if (pf_champ_visible('reference_fournisseur')) {
        $ref_fournisseur = trim((string) ($produit['reference_fournisseur'] ?? ''));
        if ($ref_fournisseur !== '') {
            $faits[] = [
                'k' => 'Réf. fournisseur',
                'v' => '<span class="fpl-fait__mono">' . fpl_e($ref_fournisseur) . '</span>',
            ];
        }
    }

    // --- Sur quel camion elle va ---
    $marque = pf_champ_visible('marque_id') ? trim((string) produits_marque_libelle_from_row($produit)) : '';
    $modeles = produit_fiche_modeles_noms($produit);
    if ($marque !== '' || $modeles !== []) {
        $faits[] = ['k' => 'Véhicule', 'v' => fpl_e(trim($marque . ' ' . implode(', ', $modeles)))];
    }

    $generation = produit_fiche_generation($produit['generation_id'] ?? 0);
    if ($generation['nom'] !== '') {
        $faits[] = ['k' => 'Génération', 'v' => fpl_e($generation['nom'])];
    }
    if ($generation['periode'] !== '') {
        $faits[] = ['k' => 'Années', 'v' => fpl_e($generation['periode'])];
    }

    $position = trim((string) ($produit['position_montage'] ?? ''));
    if ($position !== '') {
        $faits[] = ['k' => 'Position', 'v' => fpl_e(ucfirst($position))];
    }

    // --- Ce qui la distingue d'une pièce voisine ---
    if (pf_champ_visible('taille')) {
        $tailles = produit_fiche_valeurs_liste($produit['taille'] ?? '');
        if ($tailles !== []) {
            $faits[] = [
                'k' => count($tailles) > 1 ? 'Tailles' : 'Taille',
                'v' => fpl_e(implode(', ', $tailles)),
            ];
        }
    }

    if (pf_champ_visible('couleurs')) {
        $couleurs = produit_fiche_valeurs_liste($produit['couleurs'] ?? '');
        if ($couleurs !== []) {
            $morceaux = [];
            foreach ($couleurs as $couleur) {
                $hex = fpl_couleur_hex($couleur);
                $morceaux[] = ($hex !== null
                    ? '<span class="fpl-fait__pastille" style="background:' . e($hex) . '"></span>'
                    : '') . fpl_e($couleur);
            }
            $faits[] = [
                'k' => count($couleurs) > 1 ? 'Couleurs' : 'Couleur',
                'v' => '<span class="fpl-fait__couleurs">'
                    . implode(' <span class="fpl-fait__sep">·</span> ', $morceaux) . '</span>',
            ];
        }
    }

    if (pf_champ_visible('poids')) {
        $poids = produit_fiche_valeurs_liste($produit['poids'] ?? '');
        if ($poids !== []) {
            $faits[] = [
                'k' => count($poids) > 1 ? 'Poids disponibles' : 'Poids',
                'v' => fpl_e(implode(', ', $poids)),
            ];
        }
    }

    $unite = trim((string) ($produit['unite'] ?? ''));
    if ($unite !== '') {
        $faits[] = ['k' => 'Unité de vente', 'v' => fpl_e($unite)];
    }

    /* LE SEUIL D'ALERTE DE CETTE PIÈCE (31/08) : on dit le chiffre ET d'où il
     * vient — son propre réglage, la règle de sa sous-catégorie, celle de sa
     * catégorie, ou rien du tout. Sans cette précision, deux pièces « sous le
     * seuil » se ressemblent alors qu'elles n'ont pas le même seuil. */
    if (pf_champ_visible('seuil_alerte')) {
        require_once __DIR__ . '/model_stock_alertes.php';
        if (function_exists('stock_alerte_seuil_effectif')) {
            $eff = stock_alerte_seuil_effectif($produit);
            if ($eff['seuil'] === null) {
                $faits[] = ['k' => 'Seuil d\'alerte', 'v' => '<span class="muted">aucun</span>'];
            } else {
                $stock_actuel_fiche = (int) ($produit['stock'] ?? 0);
                $etat = $stock_actuel_fiche <= (int) $eff['seuil']
                    ? ' — <strong>atteint</strong>'
                    : '';
                $faits[] = [
                    'k' => 'Seuil d\'alerte',
                    'v' => (int) $eff['seuil'] . $etat
                        . '<div class="cell-sub">' . fpl_e($eff['libelle']) . '</div>',
                ];
            }
        }
    }

    // --- Qui la fournit, et à quel prix elle entre ---
    if (pf_champ_visible('fournisseur_id')) {
        $fournisseur = trim((string) produits_fournisseur_nom_affichage($produit));
        if ($fournisseur !== '') {
            $faits[] = ['k' => 'Fournisseur', 'v' => fpl_e($fournisseur)];
        }
    }

    if (pf_champ_visible('prix_achat')) {
        $prix_achat = $produit['prix_achat'] ?? null;
        if ($prix_achat !== null && $prix_achat !== '' && (float) $prix_achat > 0) {
            $faits[] = ['k' => 'Prix grossiste', 'v' => fpl_montant((float) $prix_achat) . ' FCFA'];
        }
    }

    /* LE STATUT, et sa conséquence — fait de la fiche de FPL natif. On ne le
     * montre que s'il sort de l'ordinaire : dire « Active » sur les 3 186
     * pièces actives n'apprendrait rien, alors que « retirée de la vente »
     * change ce qu'on répond au client. */
    $statut = trim((string) ($produit['statut'] ?? ''));
    if ($statut !== '' && $statut !== 'actif' && function_exists('fpl_statut_piece_libelle')) {
        $faits[] = ['k' => 'Statut', 'v' => fpl_e(fpl_statut_piece_libelle($statut))];
    }

    // --- Les champs que la maison s'est ajoutés ---
    $valeurs_custom = null;
    foreach (produit_formulaire_champs_custom_actifs() as $champ) {
        $slug = (string) ($champ['slug'] ?? '');
        if ($slug === '' || !pf_champ_visible($slug)) {
            continue;
        }
        if ($valeurs_custom === null) {
            $valeurs_custom = produit_formulaire_valeurs_custom((int) ($produit['id'] ?? 0));
        }
        $valeur = trim((string) ($valeurs_custom[$slug] ?? ''));
        if ($valeur !== '') {
            $faits[] = ['k' => (string) ($champ['label'] ?? $slug), 'v' => fpl_e($valeur)];
        }
    }

    // --- Les mains qui l'ont touchée ---
    $ajoutee = trim((string) ($produit['date_creation'] ?? ''));
    if ($ajoutee !== '') {
        $createur = produit_fiche_admin_nom($produit['admin_createur_id'] ?? 0);
        $faits[] = [
            'k' => 'Ajoutée',
            'v' => date('d/m/Y', strtotime($ajoutee)) . ($createur !== '' ? ' par ' . fpl_e($createur) : ''),
        ];
    }

    $modifiee = trim((string) ($produit['date_modification'] ?? ''));
    $modificateur = produit_fiche_admin_nom($produit['admin_dernier_modificateur_id'] ?? 0);
    if ($modifiee !== '' && $modifiee !== $ajoutee) {
        $faits[] = [
            'k' => 'Modifiée',
            'v' => date('d/m/Y', strtotime($modifiee)) . ($modificateur !== '' ? ' par ' . fpl_e($modificateur) : ''),
        ];
    }

    // --- Le statut ne se dit que lorsqu'il sort de l'ordinaire ---
    if (pf_champ_visible('statut')) {
        $statut = trim((string) ($produit['statut'] ?? ''));
        if ($statut !== '' && $statut !== 'actif') {
            $faits[] = [
                'k' => 'Statut',
                'v' => $statut === 'inactif' ? 'Inactive — retirée de la vente' : fpl_e(ucfirst($statut)),
            ];
        }
    }

    return $faits;
}
