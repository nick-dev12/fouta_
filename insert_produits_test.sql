-- Script SQL pour insérer 20 produits de test pour chaque catégorie
-- À exécuter dans votre base de données après avoir créé les catégories

-- IMPORTANT: Ce script utilise les IDs des catégories. 
-- Assurez-vous que les catégories existent avant d'exécuter ce script.

-- Fonction pour obtenir l'ID d'une catégorie (à utiliser dans les requêtes)
-- Pour chaque catégorie, nous allons insérer 20 produits

-- ============================================
-- PRODUITS POUR "Les Noix" (categorie_id = 1)
-- ============================================
INSERT INTO `produits` (`nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `image_principale`, `poids`, `unite`, `statut`) VALUES
('Noix de Cajou', 'Noix de cajou naturelles de qualité supérieure', 8500, NULL, 45, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Noix de Coco', 'Noix de coco fraîche et naturelle', 6000, NULL, 60, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Amandes', 'Amandes naturelles non salées', 12000, 10000, 35, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Pistaches', 'Pistaches décortiquées de qualité premium', 15000, NULL, 25, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '250g', 'kg', 'actif'),
('Noisettes', 'Noisettes entières naturelles', 11000, 9500, 40, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Noix de Macadamia', 'Noix de macadamia premium', 18000, NULL, 20, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '250g', 'kg', 'actif'),
('Noix de Pécan', 'Noix de pécan naturelles', 14000, 12000, 30, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Noix du Brésil', 'Noix du Brésil entières', 16000, NULL, 28, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Noix de Grenoble', 'Noix de Grenoble décortiquées', 10000, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Pignons de pin', 'Pignons de pin naturels', 20000, 18000, 15, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '250g', 'kg', 'actif'),
('Châtaignes', 'Châtaignes séchées', 7000, NULL, 55, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Marrons', 'Marrons entiers', 8000, 7000, 42, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Arachides', 'Arachides grillées naturelles', 5000, NULL, 70, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Graines de sésame', 'Graines de sésame naturelles', 6000, NULL, 65, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Graines de tournesol', 'Graines de tournesol décortiquées', 4500, NULL, 80, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Graines de courge', 'Graines de courge naturelles', 5500, NULL, 58, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Graines de pavot', 'Graines de pavot', 8000, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '250g', 'kg', 'actif'),
('Graines de lin', 'Graines de lin brunes', 5000, NULL, 60, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Graines de chanvre', 'Graines de chanvre décortiquées', 9000, 8000, 40, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Graines de chia', 'Graines de chia noires', 7500, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Noix' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif');

-- ============================================
-- PRODUITS POUR "Les Feuilles" (categorie_id = 2)
-- ============================================
INSERT INTO `produits` (`nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `image_principale`, `poids`, `unite`, `statut`) VALUES
('Feuilles de Moringa', 'Feuilles de moringa séchées, riches en nutriments', 4000, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Basilic frais', 'Basilic frais et aromatique', 2500, NULL, 65, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Menthe verte', 'Menthe verte fraîche', 2000, NULL, 70, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Thym séché', 'Thym séché de qualité', 3000, 2500, 45, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Romarin', 'Romarin séché', 3500, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Sauge', 'Feuilles de sauge séchées', 4000, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Origan', 'Origan séché', 3000, NULL, 55, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Coriandre fraîche', 'Coriandre fraîche', 2000, NULL, 60, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Persil frais', 'Persil frais', 1800, NULL, 75, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Feuilles de laurier', 'Feuilles de laurier séchées', 2500, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Feuilles de céleri', 'Feuilles de céleri fraîches', 2200, NULL, 45, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Aneth', 'Aneth frais', 2300, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Estragon', 'Estragon séché', 3200, NULL, 38, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Ciboulette', 'Ciboulette fraîche', 2000, NULL, 55, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Citronnelle', 'Citronnelle fraîche', 2500, NULL, 42, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Verveine', 'Feuilles de verveine séchées', 3500, 3000, 30, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Mélisse', 'Feuilles de mélisse séchées', 3000, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Feuilles de curry', 'Feuilles de curry fraîches', 2800, NULL, 48, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '100g', 'g', 'actif'),
('Fenouil', 'Feuilles de fenouil', 2600, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif'),
('Sarriette', 'Sarriette séchée', 3200, NULL, 33, (SELECT id FROM categories WHERE nom = 'Les Feuilles' LIMIT 1), 'produit1.jpg', '250g', 'g', 'actif');

-- ============================================
-- PRODUITS POUR "Les Fruits" (categorie_id = 3)
-- ============================================
INSERT INTO `produits` (`nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `image_principale`, `poids`, `unite`, `statut`) VALUES
('Mangues', 'Mangues fraîches et sucrées', 3500, NULL, 80, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Bananes', 'Bananes mûres et naturelles', 2500, NULL, 100, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Ananas', 'Ananas frais et juteux', 4500, 4000, 60, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Papayes', 'Papayes mûres', 3000, NULL, 70, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Avocats', 'Avocats Hass', 5000, NULL, 45, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Oranges', 'Oranges fraîches', 2000, NULL, 90, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Citrons', 'Citrons verts', 2500, NULL, 75, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Pamplemousses', 'Pamplemousses roses', 3500, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Goyaves', 'Goyaves fraîches', 2800, NULL, 65, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Fruits de la passion', 'Fruits de la passion mûrs', 4000, 3500, 40, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Grenadilles', 'Grenadilles fraîches', 4500, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Corossols', 'Corossols mûrs', 5000, NULL, 30, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Jujubes', 'Jujubes frais', 3000, NULL, 55, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Tamarins', 'Tamarins frais', 3500, NULL, 42, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Fruits de baobab', 'Fruits de baobab séchés', 6000, 5500, 25, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Kakis', 'Kakis mûrs', 4000, NULL, 38, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Figues', 'Figues fraîches', 4500, NULL, 33, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Dattes', 'Dattes Medjool', 8000, 7000, 28, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Raisins', 'Raisins noirs', 3500, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Pastèques', 'Pastèques fraîches', 3000, NULL, 45, (SELECT id FROM categories WHERE nom = 'Les Fruits' LIMIT 1), 'produit1.jpg', '5kg', 'kg', 'actif');

-- ============================================
-- PRODUITS POUR "Les Huiles" (categorie_id = 4)
-- ============================================
INSERT INTO `produits` (`nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `image_principale`, `poids`, `unite`, `statut`) VALUES
('Huile de Palme', 'Huile de palme rouge naturelle', 5000, NULL, 30, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '1L', 'L', 'actif'),
('Huile de Coco', 'Huile de coco vierge pressée à froid', 8000, 7000, 25, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile d\'Arachide', 'Huile d\'arachide raffinée', 4500, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '1L', 'L', 'actif'),
('Huile de Sésame', 'Huile de sésame vierge', 10000, NULL, 20, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile de Tournesol', 'Huile de tournesol', 4000, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '1L', 'L', 'actif'),
('Huile d\'Olive', 'Huile d\'olive extra vierge', 12000, 10000, 22, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile de Colza', 'Huile de colza', 3500, NULL, 38, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '1L', 'L', 'actif'),
('Huile de Soja', 'Huile de soja', 3800, NULL, 32, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '1L', 'L', 'actif'),
('Huile de Carthame', 'Huile de carthame', 6000, NULL, 18, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile de Lin', 'Huile de lin', 7000, 6500, 15, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile de Chanvre', 'Huile de chanvre', 9000, NULL, 12, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '250ml', 'L', 'actif'),
('Huile d\'Avocat', 'Huile d\'avocat vierge', 11000, NULL, 20, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile d\'Argan', 'Huile d\'argan cosmétique', 15000, 13000, 10, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '250ml', 'L', 'actif'),
('Huile de Jojoba', 'Huile de jojoba pure', 12000, NULL, 15, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '250ml', 'L', 'actif'),
('Huile d\'Amande', 'Huile d\'amande douce', 10000, NULL, 18, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile de Noisette', 'Huile de noisette', 11000, NULL, 14, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '250ml', 'L', 'actif'),
('Huile de Noix', 'Huile de noix', 13000, 12000, 12, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '250ml', 'L', 'actif'),
('Huile de Pépin de raisin', 'Huile de pépin de raisin', 8000, NULL, 16, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif'),
('Huile de Germe de blé', 'Huile de germe de blé', 9000, NULL, 13, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '250ml', 'L', 'actif'),
('Huile de Ricin', 'Huile de ricin', 5000, NULL, 25, (SELECT id FROM categories WHERE nom = 'Les Huiles' LIMIT 1), 'produit1.jpg', '500ml', 'L', 'actif');

-- ============================================
-- PRODUITS POUR "Les Céréales" (categorie_id = 5)
-- ============================================
INSERT INTO `produits` (`nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `image_principale`, `poids`, `unite`, `statut`) VALUES
('Riz blanc', 'Riz blanc de qualité', 3000, NULL, 100, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '5kg', 'kg', 'actif'),
('Mil', 'Mil naturel', 2500, NULL, 80, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Maïs', 'Maïs séché', 2000, NULL, 90, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Blé', 'Blé entier', 3500, NULL, 70, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Sorgho', 'Sorgho naturel', 2800, NULL, 65, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Avoine', 'Flocons d\'avoine', 4000, 3500, 55, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Orge', 'Orge perlé', 3200, NULL, 60, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Seigle', 'Seigle entier', 3800, NULL, 45, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Sarrasin', 'Sarrasin décortiqué', 5000, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Quinoa', 'Quinoa blanc', 8000, 7000, 30, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Amarante', 'Graines d\'amarante', 6000, NULL, 25, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Fonio', 'Fonio blanc', 4500, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Teff', 'Teff brun', 5500, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Épeautre', 'Épeautre complet', 4200, NULL, 42, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Kamut', 'Kamut ancien', 4800, NULL, 38, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Triticale', 'Triticale', 3600, NULL, 33, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Riz sauvage', 'Riz sauvage', 10000, 9000, 20, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Riz complet', 'Riz complet bio', 4500, NULL, 48, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Riz basmati', 'Riz basmati premium', 5000, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Riz jasmin', 'Riz jasmin parfumé', 4800, NULL, 43, (SELECT id FROM categories WHERE nom = 'Les Céréales' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif');

-- ============================================
-- PRODUITS POUR "Les Racines" (categorie_id = 6)
-- ============================================
INSERT INTO `produits` (`nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `image_principale`, `poids`, `unite`, `statut`) VALUES
('Gingembre', 'Gingembre frais', 4000, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Curcuma', 'Curcuma frais', 3500, NULL, 45, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Carottes', 'Carottes fraîches', 2000, NULL, 80, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Patates douces', 'Patates douces', 2500, NULL, 70, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Ignames', 'Ignames fraîches', 3000, NULL, 60, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Manioc', 'Manioc frais', 2000, NULL, 75, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Taro', 'Taro frais', 2800, NULL, 55, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '2kg', 'kg', 'actif'),
('Radis', 'Radis rouges', 2500, NULL, 65, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Betteraves', 'Betteraves rouges', 3000, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Navets', 'Navets frais', 2200, NULL, 58, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Rutabagas', 'Rutabagas', 2400, NULL, 48, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Panais', 'Panais frais', 2600, NULL, 42, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Céleri-rave', 'Céleri-rave', 3500, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '1kg', 'kg', 'actif'),
('Raifort', 'Raifort frais', 4000, NULL, 30, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Wasabi', 'Wasabi frais', 6000, 5500, 20, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Ginseng', 'Racine de ginseng', 15000, NULL, 15, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Réglisse', 'Racine de réglisse', 5000, NULL, 25, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Valériane', 'Racine de valériane', 4500, NULL, 28, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Bardane', 'Racine de bardane', 3800, NULL, 32, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif'),
('Pissenlit', 'Racine de pissenlit', 3000, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Racines' LIMIT 1), 'produit1.jpg', '500g', 'kg', 'actif');

-- ============================================
-- PRODUITS POUR "Les Cosmétiques" (categorie_id = 7)
-- ============================================
INSERT INTO `produits` (`nom`, `description`, `prix`, `prix_promotion`, `stock`, `categorie_id`, `image_principale`, `poids`, `unite`, `statut`) VALUES
('Savon au karité', 'Savon naturel au beurre de karité', 3000, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Savon au miel', 'Savon artisanal au miel', 3500, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Savon à l\'argile', 'Savon à l\'argile verte', 3200, NULL, 38, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Crème hydratante', 'Crème hydratante au karité', 8000, 7000, 25, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Baume à lèvres', 'Baume à lèvres naturel', 2500, NULL, 50, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '100g', 'unité', 'actif'),
('Huile de massage', 'Huile de massage relaxante', 6000, NULL, 30, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Masque visage', 'Masque visage à l\'argile', 5000, NULL, 28, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Gommage naturel', 'Gommage corps naturel', 4500, NULL, 32, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Shampoing naturel', 'Shampoing naturel', 4000, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Après-shampoing', 'Après-shampoing naturel', 4200, NULL, 33, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Déodorant naturel', 'Déodorant naturel', 3500, NULL, 40, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '100g', 'unité', 'actif'),
('Dentifrice naturel', 'Dentifrice naturel', 3000, NULL, 45, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '100g', 'unité', 'actif'),
('Lotion tonique', 'Lotion tonique visage', 5500, NULL, 30, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Sérum visage', 'Sérum anti-âge naturel', 12000, 10000, 20, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Beurre de karité', 'Beurre de karité pur', 7000, NULL, 28, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '500g', 'unité', 'actif'),
('Beurre de cacao', 'Beurre de cacao pur', 8000, NULL, 25, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '500g', 'unité', 'actif'),
('Huile de coco cosmétique', 'Huile de coco pour le corps', 6000, NULL, 30, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '500g', 'unité', 'actif'),
('Pommade cicatrisante', 'Pommade cicatrisante naturelle', 4500, NULL, 35, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Gel douche', 'Gel douche naturel', 3800, NULL, 38, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif'),
('Bombe de bain', 'Bombe de bain relaxante', 4000, NULL, 32, (SELECT id FROM categories WHERE nom = 'Les Cosmétiques' LIMIT 1), 'produit1.jpg', '200g', 'unité', 'actif');
