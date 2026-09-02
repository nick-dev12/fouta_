<?php
/**
 * LES COLONNES DU CATALOGUE DE PIÈCES (02/09/2026) — une source unique.
 *
 * La direction veut choisir, à l'écran, quelles colonnes le tableau des
 * pièces affiche — dont des données qui n'apparaissaient que dans la fiche
 * (les prix). Plutôt que deux tableaux (le paginé et la recherche en direct)
 * aux colonnes figées, ce fichier décrit LA liste des colonnes une seule
 * fois : les deux en-têtes et la ligne s'y conforment, et un sélecteur à
 * cases les montre ou les cache.
 *
 * RÈGLE DES DROITS : une colonne réservée (les prix, le fournisseur) n'est
 * même PAS ÉMISE dans le HTML pour un rôle qui n'y a pas droit — comme la
 * garde des exports. Cocher une colonne absente est donc impossible : rien à
 * cacher côté client, rien qui fuie côté serveur.
 *
 * Les colonnes de structure (vignette, Pièce, Actions) ne sont pas dans cette
 * liste : elles encadrent le tableau et ne se cachent pas.
 *
 * Programmation procédurale uniquement.
 */

require_once __DIR__ . '/produit_formulaire_champs.php';

/**
 * La liste ordonnée des colonnes proposables, filtrée par les droits du rôle.
 *
 * @return array<string, array{label:string, defaut:bool, num:bool}>
 */
function catalogue_colonnes_disponibles()
{
    $voit_fournisseur = !function_exists('pf_champ_visible') || pf_champ_visible('fournisseur_id');
    $voit_prix = !function_exists('pf_champ_visible') || pf_champ_visible('prix');
    $voit_promo = !function_exists('pf_champ_visible') || pf_champ_visible('prix_promotion');
    $voit_grossiste = !function_exists('pf_champ_visible') || pf_champ_visible('prix_achat');

    // Par défaut, on retrouve EXACTEMENT le tableau d'avant : Marque, puis
    // Fournisseur (ou Catégorie pour le rayonniste), Référence, Stock.
    $cols = [];
    $cols['marque'] = ['label' => 'Marque', 'defaut' => true, 'num' => false];
    if ($voit_fournisseur) {
        $cols['fournisseur'] = ['label' => 'Fournisseur', 'defaut' => true, 'num' => false];
        $cols['categorie'] = ['label' => 'Catégorie', 'defaut' => false, 'num' => false];
    } else {
        // Le rayonniste ne voit pas le fournisseur : la catégorie prend la
        // place par défaut, comme le faisait la colonne unique.
        $cols['categorie'] = ['label' => 'Catégorie', 'defaut' => true, 'num' => false];
    }
    $cols['reference'] = ['label' => 'Référence', 'defaut' => true, 'num' => false];
    $cols['stock'] = ['label' => 'Stock', 'defaut' => true, 'num' => true];
    if ($voit_prix) {
        // Affiché PAR DÉFAUT (02/09) : la direction attend les prix dans la
        // liste, pas seulement dans la fiche. Les autres prix restent à cocher.
        $cols['prix'] = ['label' => 'Prix de vente', 'defaut' => true, 'num' => true];
    }
    if ($voit_promo) {
        $cols['prix_promotion'] = ['label' => 'Prix promo', 'defaut' => false, 'num' => true];
    }
    if ($voit_prix) {
        // Le prix entreprise suit la règle du prix de vente (un tarif de vente
        // négocié) — même décision que la fusion et l'export.
        $cols['prix_entreprise'] = ['label' => 'Prix entreprise', 'defaut' => false, 'num' => true];
    }
    if ($voit_grossiste) {
        $cols['prix_achat'] = ['label' => 'Prix grossiste', 'defaut' => false, 'num' => true];
    }
    $cols['statut'] = ['label' => 'Statut', 'defaut' => false, 'num' => false];
    $cols['modele'] = ['label' => 'Modèle', 'defaut' => false, 'num' => false];
    $cols['date_creation'] = ['label' => 'Ajoutée le', 'defaut' => false, 'num' => false];

    return $cols;
}

/**
 * Les clés des colonnes affichées par défaut — l'état d'origine du sélecteur.
 *
 * @return array<int, string>
 */
function catalogue_colonnes_defaut()
{
    $out = [];
    foreach (catalogue_colonnes_disponibles() as $cle => $def) {
        if ($def['defaut']) {
            $out[] = $cle;
        }
    }

    return $out;
}

/**
 * Le contenu HTML d'une cellule pour une pièce et une colonne données.
 * Réutilise les mêmes formats que la fiche et l'export (fpl_montant, statut…).
 *
 * @param string $cle
 * @param array<string, mixed> $produit
 * @param array<int, string> $modeles_noms  id de modèle → nom
 * @return string HTML prêt à poser dans un <td>
 */
function catalogue_colonne_cellule_html($cle, array $produit, array $modeles_noms = [])
{
    $muet = '<span class="muted">—</span>';

    switch ($cle) {
        case 'marque':
            $v = trim((string) ($produit['marque_libelle_catalogue'] ?? ''));
            return $v !== '' ? '<span class="cell-title" style="font-weight:550">' . fpl_e($v) . '</span>' : $muet;

        case 'fournisseur':
            $v = function_exists('produits_fournisseur_nom_affichage')
                ? trim((string) produits_fournisseur_nom_affichage($produit))
                : trim((string) ($produit['nom_fournisseur'] ?? $produit['fournisseur_table_nom'] ?? ''));
            return $v !== '' ? fpl_e($v) : $muet;

        case 'categorie':
            $v = trim((string) ($produit['categorie_nom'] ?? ''));
            return $v !== '' ? fpl_e($v) : $muet;

        case 'reference':
            $oem = trim((string) ($produit['reference_oem'] ?? ''));
            $ref_f = trim((string) ($produit['reference_fournisseur'] ?? ''));
            $ref = $oem !== '' ? $oem : $ref_f;
            if ($ref === '') {
                return '—';
            }
            $out = fpl_e($ref);
            if ($oem === '') {
                $out .= ' <span class="muted" style="font-size:11px">fourn.</span>';
            }
            return $out;

        case 'stock':
            $stock = (int) ($produit['stock'] ?? 0);
            $seuil = null;
            if (function_exists('stock_alerte_seuil_effectif')) {
                $seuil = stock_alerte_seuil_effectif($produit)['seuil'];
            }
            $manque = $stock <= 0 || ($seuil !== null && $stock <= (int) $seuil);
            $out = '<strong>' . $stock . '</strong>';
            if ($manque && $seuil !== null) {
                $out .= ' <span class="fpl-cell-stock__seuil">/ ' . (int) $seuil . '</span>';
            }
            return $out;

        case 'prix':
        case 'prix_promotion':
        case 'prix_entreprise':
        case 'prix_achat':
            $v = $produit[$cle] ?? null;
            if ($v === null || $v === '' || (float) $v <= 0) {
                return $muet;
            }
            return function_exists('fpl_montant')
                ? fpl_e(fpl_montant((float) $v) . ' FCFA')
                : number_format((float) $v, 0, ',', ' ') . ' FCFA';

        case 'statut':
            $s = (string) ($produit['statut'] ?? '');
            if ($s === '') {
                return $muet;
            }
            return function_exists('fpl_statut_piece_libelle') ? fpl_e(fpl_statut_piece_libelle($s)) : fpl_e($s);

        case 'modele':
            $mid = (int) ($produit['modele_id'] ?? 0);
            $v = ($mid > 0 && isset($modeles_noms[$mid])) ? $modeles_noms[$mid] : '';
            return $v !== '' ? fpl_e($v) : $muet;

        case 'date_creation':
            $d = (string) ($produit['date_creation'] ?? '');
            return $d !== '' ? date('d/m/Y', strtotime($d)) : $muet;
    }

    return $muet;
}
