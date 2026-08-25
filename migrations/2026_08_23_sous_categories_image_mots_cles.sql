-- =====================================================================
--  LES RAYONS GAGNENT UNE IMAGE ET DES MOTS-CLÉS
--  23/08/2026
--
--  Deux colonnes que FPL natif a sur `sous_categories` et que ce dépôt
--  n'avait pas :
--
--   * `image`      — la vignette du rayon. Sans elle, les cartes du bandeau
--                    du catalogue restent muettes dès qu'on ouvre une
--                    catégorie, alors que les catégories, elles, ont la leur.
--
--   * `mots_cles`  — les mots sous lesquels on CHERCHE un rayon sans
--                    connaître son nom. C'est la première des trois
--                    recherches de l'écran « Où ranger cette pièce ? » :
--                    taper « filtre » doit trouver le rayon même s'il
--                    s'appelle « Système d'admission d'air ».
--
--  AUCUNE DONNÉE N'EST TOUCHÉE : deux colonnes nullables ajoutées à la fin
--  de la table. Les lignes existantes reçoivent NULL, et tout le code qui
--  lit `sous_categories` continue de fonctionner sans être modifié.
--
--  Les deux colonnes sont vérifiées avant d'être interrogées par le code
--  (voir sous_categories_a_colonne() dans models/model_produits.php), donc
--  l'application marche avant comme après cette migration.
-- =====================================================================

ALTER TABLE `sous_categories`
    ADD COLUMN `image` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Vignette du rayon, relative à upload/'
        AFTER `description`,
    ADD COLUMN `mots_cles` VARCHAR(500) NULL DEFAULT NULL
        COMMENT 'Mots sous lesquels on cherche ce rayon (séparés par des virgules)'
        AFTER `image`;

-- Un index sur les mots-clés : la recherche de rangement les interroge à
-- chaque frappe, sur les 102 rayons.
ALTER TABLE `sous_categories`
    ADD INDEX `idx_sous_categories_mots_cles` (`mots_cles`(191));
