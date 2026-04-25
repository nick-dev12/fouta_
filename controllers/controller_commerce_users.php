<?php
/**
 * Contrôleur pour les commerçants (ancien système)
 * Ce fichier est conservé pour la compatibilité avec l'ancien code
 * Programmation procédurale uniquement
 */

// Initialiser la variable $commercant si le commerçant est connecté
$commercant = null;

if (isset($_SESSION['commercant_id'])) {
    // Si vous avez besoin de récupérer les données du commerçant,
    // vous pouvez ajouter une requête à la base de données ici
    // Pour l'instant, on initialise juste la variable pour éviter les erreurs
    $commercant = [
        'nom' => 'Commerçant',
        'images' => 'default.png'
    ];
}

?>

