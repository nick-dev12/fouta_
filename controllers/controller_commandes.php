<?php
/**
 * Contrôleur pour la gestion des commandes
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_panier.php';
require_once __DIR__ . '/../models/model_zones_livraison.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Traite la création d'une commande
 * @return array Tableau avec 'success', 'message', et éventuellement 'commande_id' et 'numero_commande'
 */
function process_create_commande() {
    if (!isset($_SESSION['user_id'])) {
        return [
            'success' => false,
            'message' => 'Vous devez être connecté pour passer une commande.'
        ];
    }
    
    $user_id = $_SESSION['user_id'];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'success' => false,
            'message' => 'Méthode non autorisée.'
        ];
    }
    
    $zone_livraison_id = isset($_POST['zone_livraison_id']) ? (int) $_POST['zone_livraison_id'] : 0;
    $telephone_livraison = trim($_POST['telephone_livraison'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($telephone_livraison)) {
        return [
            'success' => false,
            'message' => 'Le téléphone de livraison est obligatoire.'
        ];
    }
    
    if (!preg_match('/^[0-9+\s\-()]+$/', $telephone_livraison)) {
        return [
            'success' => false,
            'message' => 'Le format du téléphone n\'est pas valide.'
        ];
    }
    
    $adresse_livraison = 'À définir';
    $frais_livraison = 0;
    
    if ($zone_livraison_id > 0) {
        $zone = get_zone_livraison_by_id($zone_livraison_id);
        if (!$zone || $zone['statut'] !== 'actif') {
            return [
                'success' => false,
                'message' => 'La zone de livraison sélectionnée n\'est pas valide.'
            ];
        }
        $adresse_livraison = $zone['ville'] . ' - ' . $zone['quartier'];
        $frais_livraison = (float) $zone['prix_livraison'];
    } else {
        return [
            'success' => false,
            'message' => 'Veuillez sélectionner une zone de livraison.'
        ];
    }
    
    $panier_items = get_panier_by_user($user_id);
    
    if (empty($panier_items)) {
        return [
            'success' => false,
            'message' => 'Votre panier est vide. Ajoutez des produits avant de passer une commande.'
        ];
    }
    
    foreach ($panier_items as $item) {
        if ($item['stock'] < $item['quantite']) {
            return [
                'success' => false,
                'message' => 'Le stock disponible pour "' . htmlspecialchars($item['nom']) . '" est insuffisant. Stock disponible: ' . $item['stock']
            ];
        }
    }
    
    // Récupérer les choix couleur, poids, taille par panier_id (priorité: POST, sinon options du panier)
    $choix = [];
    foreach ($panier_items as $item) {
        $panier_id = isset($item['panier_id']) ? (int) $item['panier_id'] : 0;
        if ($panier_id <= 0) continue;

        $couleur = '';
        $poids = '';
        $taille = '';

        if (isset($_POST['choix'][$panier_id]) && is_array($_POST['choix'][$panier_id])) {
            $c = $_POST['choix'][$panier_id];
            $couleur = isset($c['couleur']) ? trim($c['couleur']) : '';
            $poids = isset($c['poids']) ? trim($c['poids']) : '';
            $taille = isset($c['taille']) ? trim($c['taille']) : '';
        }
        // Fallback: utiliser les options du panier (sélectionnées sur la page produit)
        if ($couleur === '' && !empty(trim($item['panier_couleur'] ?? ''))) {
            $couleur = trim($item['panier_couleur']);
        }
        if ($poids === '' && !empty(trim($item['panier_poids'] ?? ''))) {
            $poids = trim($item['panier_poids']);
        }
        if ($taille === '' && !empty(trim($item['panier_taille'] ?? ''))) {
            $taille = trim($item['panier_taille']);
        }

        $choix[$panier_id] = ['couleur' => $couleur, 'poids' => $poids, 'taille' => $taille];
    }
    
    $result = create_commande(
        $user_id,
        $panier_items,
        $adresse_livraison,
        $telephone_livraison,
        $notes ?: null,
        $zone_livraison_id,
        $frais_livraison,
        $choix
    );
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la création de la commande. Veuillez réessayer.'
        ];
    }
    
    if ($result['success']) {
        // Vider le panier après création de la commande
        clear_panier($user_id);

        // Préparer les données pour la notification/email (envoi asynchrone dans commande.php)
        $sous_total = 0;
        $nombre_articles = 0;
        $produits_email = [];
        foreach ($panier_items as $item) {
            $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                ? (float) $item['panier_prix_unitaire']
                : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
            $prix_total_ligne = $prix_unitaire * $item['quantite'];
            $sous_total += $prix_total_ligne;
            $nombre_articles += $item['quantite'];
            $panier_id = isset($item['panier_id']) ? (int) $item['panier_id'] : 0;
            $c = isset($choix[$panier_id]) ? $choix[$panier_id] : [];
            $nom_affichage = $item['nom'];
            if (!empty($item['panier_variante_nom'])) {
                $nom_affichage .= ' → ' . $item['panier_variante_nom'];
            }
            $produits_email[] = [
                'nom' => $nom_affichage,
                'quantite' => $item['quantite'],
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total_ligne,
                'variante_nom' => $item['panier_variante_nom'] ?? '',
                'couleur' => isset($c['couleur']) ? $c['couleur'] : ($item['panier_couleur'] ?? ''),
                'poids' => isset($c['poids']) ? $c['poids'] : ($item['panier_poids'] ?? ''),
                'taille' => isset($c['taille']) ? $c['taille'] : ($item['panier_taille'] ?? ''),
                'surcout_poids' => isset($item['panier_surcout_poids']) ? (float) $item['panier_surcout_poids'] : 0,
                'surcout_taille' => isset($item['panier_surcout_taille']) ? (float) $item['panier_surcout_taille'] : 0
            ];
        }
        $montant_total = $sous_total + $frais_livraison;

        return [
            'success' => true,
            'message' => 'Votre commande a été créée avec succès ! Numéro de commande: ' . $result['numero_commande'],
            'commande_id' => $result['commande_id'],
            'numero_commande' => $result['numero_commande'],
            'email_data' => [
                'numero_commande' => $result['numero_commande'],
                'montant_total' => $montant_total,
                'nombre_articles' => $nombre_articles,
                'telephone_livraison' => $telephone_livraison,
                'adresse_livraison' => $adresse_livraison,
                'produits' => $produits_email
            ]
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Une erreur est survenue lors de la création de la commande.'
    ];
}

?>

