-- Script SQL pour ajouter les nouveaux statuts à la table commandes
-- À exécuter dans votre base de données

-- Modifier la colonne statut pour inclure les nouveaux statuts
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

