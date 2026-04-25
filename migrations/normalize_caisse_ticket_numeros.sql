-- Optionnel : recalcule les numéros au format TKT + AAAAMMJJ + id (6 chiffres)
-- à exécuter une fois si des tickets anciens utilisent encore l’ancien format aléatoire.
-- Vérifiez les exports / références externes avant d’appliquer en production.

UPDATE `caisse_ventes`
SET `numero_ticket` = CONCAT('TKT', DATE_FORMAT(`date_vente`, '%Y%m%d'), LPAD(`id`, 6, '0'));
