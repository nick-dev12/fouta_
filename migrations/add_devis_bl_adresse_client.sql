-- Adresse du client (facultatif), distincte de l'adresse / zone de livraison
ALTER TABLE `devis` ADD COLUMN `adresse_client` TEXT NULL DEFAULT NULL COMMENT 'Adresse postale ou siège du client (optionnel)' AFTER `client_email`;
ALTER TABLE `bons_livraison` ADD COLUMN `adresse_client` TEXT NULL DEFAULT NULL COMMENT 'Adresse client affichée sur facture BL (optionnel)' AFTER `notes`;
