-- Script SQL pour changer le statut "en_cours_expedition" en "livraison_en_cours"
-- À exécuter dans votre base de données

-- Étape 1: Mettre à jour les données existantes
UPDATE `commandes` 
SET `statut` = 'livraison_en_cours' 
WHERE `statut` = 'en_cours_expedition';

-- Étape 2: Modifier la colonne statut pour remplacer 'en_cours_expedition' par 'livraison_en_cours'
ALTER TABLE `commandes` 
MODIFY COLUMN `statut` ENUM(
    'en_attente', 
    'confirmee', 
    'prise_en_charge',
    'en_preparation', 
    'livraison_en_cours',
    'expediee', 
    'livree', 
    'annulee'
) NOT NULL DEFAULT 'en_attente';

