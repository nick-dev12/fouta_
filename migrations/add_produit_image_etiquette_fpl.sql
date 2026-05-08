-- Image optionnelle affichée sur l'étiquette FPL (ajuster-stock, impression)
ALTER TABLE `produits`
ADD COLUMN `image_etiquette_fpl` VARCHAR(255) NULL DEFAULT NULL
COMMENT 'Chemin relatif upload/ ex. produits/xxx.jpg pour etiquette FPL'
AFTER `image_principale`;
