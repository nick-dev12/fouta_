-- Migration: Ajouter le statut 'paye' à la table commandes
-- Le stock est décrémenté uniquement lorsque le statut passe à 'paye'

ALTER TABLE `commandes`
MODIFY COLUMN `statut` ENUM(
    'en_attente',
    'confirmee',
    'prise_en_charge',
    'en_preparation',
    'livraison_en_cours',
    'expediee',
    'livree',
    'paye',
    'annulee'
) NOT NULL DEFAULT 'en_attente';
