<?php
/**
 * Registre FK statique (fallback si information_schema vide).
 * Généré depuis jomas_fouta_fixed.sql
 */

if (!function_exists('sync_registry_static_foreign_keys')) {
    function sync_registry_static_foreign_keys($table) {
        static $map = array (
  'admin_export_catalogue_colonnes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'bl_lignes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'bl_id',
      'REFERENCED_TABLE_NAME' => 'bons_livraison',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'bons_livraison' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'client_b2b_id',
      'REFERENCED_TABLE_NAME' => 'clients_b2b',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'bons_retour' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'bl_id',
      'REFERENCED_TABLE_NAME' => 'bons_livraison',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'bons_retour_lignes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'bl_ligne_id',
      'REFERENCED_TABLE_NAME' => 'bl_lignes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'bon_retour_id',
      'REFERENCED_TABLE_NAME' => 'bons_retour',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'caisse_ventes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'caissier_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'caisse_vente_lignes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'vente_id',
      'REFERENCED_TABLE_NAME' => 'caisse_ventes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'categories' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'admin_dernier_modificateur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'clients_b2b' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'commandes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'admin_dernier_traitement_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'user_id',
      'REFERENCED_TABLE_NAME' => 'users',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'commandes_personnalisees' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'user_id',
      'REFERENCED_TABLE_NAME' => 'users',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'zone_livraison_id',
      'REFERENCED_TABLE_NAME' => 'zones_livraison',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'commandes_retours' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'commande_id',
      'REFERENCED_TABLE_NAME' => 'commandes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'commandes_retours_lignes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'commande_produit_id',
      'REFERENCED_TABLE_NAME' => 'commande_produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'retour_commande_id',
      'REFERENCED_TABLE_NAME' => 'commandes_retours',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'commande_produits' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'commande_id',
      'REFERENCED_TABLE_NAME' => 'commandes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'depenses' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'categorie_id',
      'REFERENCED_TABLE_NAME' => 'categories_depenses',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'devis' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'user_id',
      'REFERENCED_TABLE_NAME' => 'users',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'zone_livraison_id',
      'REFERENCED_TABLE_NAME' => 'zones_livraison',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'devis_produits' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'devis_id',
      'REFERENCED_TABLE_NAME' => 'devis',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employes_matricules' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_absences' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'created_by_admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'penalite_deduite_bulletin_id',
      'REFERENCED_TABLE_NAME' => 'employe_bulletins_paie',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    3 => 
    array (
      'COLUMN_NAME' => 'subject_admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_absence_justificatifs' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'absence_id',
      'REFERENCED_TABLE_NAME' => 'employe_absences',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'created_by_admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_autorisations_absence' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_bulletins_paie' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_conges' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_documents' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_prets' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_pret_remboursements' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'pret_id',
      'REFERENCED_TABLE_NAME' => 'employe_prets',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_prime_transport_retraits' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'employe_sanctions' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'employe_id',
      'REFERENCED_TABLE_NAME' => 'employes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_allee' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'etage_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etage',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_barre' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'allee_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_allee',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'champ_element_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_champ_element',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'etage_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etage',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    3 => 
    array (
      'COLUMN_NAME' => 'etagere_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etagere',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    4 => 
    array (
      'COLUMN_NAME' => 'rayon_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_rayon',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    5 => 
    array (
      'COLUMN_NAME' => 'zone_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_zone',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_champ_element' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'champ_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_structure_champ',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'etage_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etage',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_etagere' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'etage_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etage',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'rayon_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_rayon',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'zone_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_zone',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_hierarchie_noeud' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'etage_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etage',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'niveau_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_hierarchie_niveau',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'parent_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_hierarchie_noeud',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_position' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'barre_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_barre',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_rayon' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'etage_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etage',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'zone_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_zone',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'entrepot_zone' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'etage_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_etage',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'rayon_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_rayon',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'factures' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'commande_id',
      'REFERENCED_TABLE_NAME' => 'commandes',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'factures_devis' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'devis_id',
      'REFERENCED_TABLE_NAME' => 'devis',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'factures_mensuelles' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'client_b2b_id',
      'REFERENCED_TABLE_NAME' => 'clients_b2b',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'factures_personnalisees' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'commande_personnalisee_id',
      'REFERENCED_TABLE_NAME' => 'commandes_personnalisees',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'facture_mensuelle_bl' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'bl_id',
      'REFERENCED_TABLE_NAME' => 'bons_livraison',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'facture_mensuelle_id',
      'REFERENCED_TABLE_NAME' => 'factures_mensuelles',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'favoris' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'user_id',
      'REFERENCED_TABLE_NAME' => 'users',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'panier' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'user_id',
      'REFERENCED_TABLE_NAME' => 'users',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'produits' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_createur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'admin_dernier_modificateur_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'categorie_id',
      'REFERENCED_TABLE_NAME' => 'categories',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    3 => 
    array (
      'COLUMN_NAME' => 'entrepot_position_id',
      'REFERENCED_TABLE_NAME' => 'entrepot_position',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    4 => 
    array (
      'COLUMN_NAME' => 'fournisseur_id',
      'REFERENCED_TABLE_NAME' => 'fournisseurs',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    5 => 
    array (
      'COLUMN_NAME' => 'sous_categorie_id',
      'REFERENCED_TABLE_NAME' => 'sous_categories',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    6 => 
    array (
      'COLUMN_NAME' => 'stock_article_id',
      'REFERENCED_TABLE_NAME' => 'stock_articles',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'produits_variantes' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'produits_visites' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'user_id',
      'REFERENCED_TABLE_NAME' => 'users',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'produit_champ_valeur' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'champ_id',
      'REFERENCED_TABLE_NAME' => 'produit_formulaire_champ',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'produit_formulaire_champ_droit' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'produit_formulaire_champ_role' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'champ_id',
      'REFERENCED_TABLE_NAME' => 'produit_formulaire_champ',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'sous_categories' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'categorie_id',
      'REFERENCED_TABLE_NAME' => 'categories',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'stock_alertes_regles_categories' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'categorie_id',
      'REFERENCED_TABLE_NAME' => 'categories',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'regle_id',
      'REFERENCED_TABLE_NAME' => 'stock_alertes_regles',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'stock_alertes_regles_sous_categories' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'regle_id',
      'REFERENCED_TABLE_NAME' => 'stock_alertes_regles',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'sous_categorie_id',
      'REFERENCED_TABLE_NAME' => 'sous_categories',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'stock_articles' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'categorie_id',
      'REFERENCED_TABLE_NAME' => 'categories',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
  'stock_mouvements' => 
  array (
    0 => 
    array (
      'COLUMN_NAME' => 'produit_id',
      'REFERENCED_TABLE_NAME' => 'produits',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    1 => 
    array (
      'COLUMN_NAME' => 'stock_article_id',
      'REFERENCED_TABLE_NAME' => 'stock_articles',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
    2 => 
    array (
      'COLUMN_NAME' => 'admin_id',
      'REFERENCED_TABLE_NAME' => 'admin',
      'REFERENCED_COLUMN_NAME' => 'id',
    ),
  ),
);
        return $map[$table] ?? [];
    }
}
