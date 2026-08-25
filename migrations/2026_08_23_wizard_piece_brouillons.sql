-- =====================================================================
--  LE WIZARD « NOUVELLE PIÈCE » DE FPL NATIF ARRIVE DANS CE DÉPÔT
--  23/08/2026 (soir)
--
--  La page d'ajout de pièce reprend désormais le squelette EXACT de
--  fpl_natif/admin/piece-nouvelle.php (4 étapes : Véhicule → La pièce →
--  Stock → Finaliser). Trois choses que ce squelette attend n'existaient
--  pas ici :
--
--   * la table `brouillons` — la sauvegarde au fil de l'eau : chaque champ
--     saisi part en base quelques centaines de millisecondes après la
--     frappe (js/fpl-draft.js + admin/produits/ajax_brouillon.php). Quitter
--     la page et revenir ne fait rien perdre. Même structure que chez FPL
--     natif (clé unique par utilisateur et par formulaire).
--
--   * `produits.nom_wolof` — le nom sous lequel la pièce se demande au
--     comptoir (FPL natif l'a depuis le 14/08 ; il titre l'étiquette).
--
--   * `produits.prix_entreprise` — le tarif des clients professionnels,
--     sous le prix public (FPL natif le saisit dès la création).
--
--  AUCUNE DONNÉE N'EST TOUCHÉE : une table neuve et deux colonnes nullables
--  ajoutées. Tout le code qui lit `produits` continue de fonctionner, et
--  les deux colonnes sont vérifiées par produits_has_column() avant d'être
--  écrites : l'application marche avant comme après cette migration.
--
--  À rejouer ailleurs : mysql -u root jomas_fouta3 < ce_fichier.sql
--  (les ALTER échouent proprement si les colonnes existent déjà — MySQL n'a
--  pas de IF NOT EXISTS pour ADD COLUMN ; migrations/run_wizard_piece.php
--  fait la même chose en vérifiant d'abord.)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `brouillons` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `admin_id` INT NOT NULL,
    `cle` VARCHAR(120) NOT NULL COMMENT 'Formulaire + cible, ex. produit.nouveau.27',
    `contenu` JSON NULL DEFAULT NULL,
    `date_creation` DATETIME NULL DEFAULT NULL,
    `date_modification` DATETIME NULL DEFAULT NULL,
    `sync_uuid` CHAR(36) NULL DEFAULT NULL,
    `sync_updated_at` DATETIME NULL DEFAULT NULL,
    `sync_deleted_at` DATETIME NULL DEFAULT NULL,
    `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_brouillons` (`admin_id`, `cle`),
    UNIQUE KEY `sync_uuid` (`sync_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `produits`
    ADD COLUMN `nom_wolof` VARCHAR(190) NULL DEFAULT NULL
        COMMENT 'Le nom demandé au comptoir — titre de l''étiquette'
        AFTER `nom`;

ALTER TABLE `produits`
    ADD COLUMN `prix_entreprise` DECIMAL(12,2) NULL DEFAULT NULL
        COMMENT 'Tarif des clients professionnels, sous le prix public'
        AFTER `prix_promotion`;
