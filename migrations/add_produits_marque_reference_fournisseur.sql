-- Marque (référentiel paramètres) et référence article chez le fournisseur
ALTER TABLE produits ADD COLUMN marque_id INT UNSIGNED NULL DEFAULT NULL AFTER categorie_id;
ALTER TABLE produits ADD COLUMN reference_fournisseur VARCHAR(120) NULL DEFAULT NULL AFTER marque_id;
