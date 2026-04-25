-- Référence courte (5 chiffres) pour retrouver un ticket en attente à la caisse.
-- Supprimée automatiquement à l’encaissement (application).

ALTER TABLE `caisse_ventes`
  ADD COLUMN `reference` VARCHAR(5) NULL DEFAULT NULL COMMENT 'Recherche caisse (ticket non payé uniquement)' AFTER `numero_ticket`,
  ADD UNIQUE KEY `uk_caisse_ventes_reference` (`reference`);
