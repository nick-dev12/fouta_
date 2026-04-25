-- Migration: ajout de la colonne zone_livraison_id sur commandes_personnalisees
-- Exécuter via php migrations/run_add_zone_livraison_commandes_personnalisees.php

ALTER TABLE commandes_personnalisees
ADD COLUMN zone_livraison_id INT(11) NULL DEFAULT NULL AFTER date_souhaitee,
ADD KEY idx_zone_livraison (zone_livraison_id);

ALTER TABLE commandes_personnalisees
ADD CONSTRAINT fk_cp_zone_livraison
FOREIGN KEY (zone_livraison_id) REFERENCES zones_livraison(id) ON DELETE SET NULL ON UPDATE CASCADE;
