-- Jours de présence de référence (commun à tous les salariés pour un mois type)
-- Exécution : php migrations/run_alter_bulletin_jours_presence_defaut.php

ALTER TABLE `bulletin_paie_parametres`
  ADD COLUMN `jours_presence_defaut` SMALLINT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Nb jours de travail/presence de reference (tous employes, a afficher sur bulletin apres retrait absences retenues)'
  AFTER `retenues_taux_json`;
