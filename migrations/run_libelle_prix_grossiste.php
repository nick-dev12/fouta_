<?php
/**
 * « PRIX D'ACHAT » DEVIENT « PRIX GROSSISTE » (01/09/2026) — le libellé.
 *
 * Décision de la direction : le champ que le code appelle `prix_achat`
 * s'affiche partout sous le nom « Prix grossiste ». Le code est renommé
 * dans le même commit ; ce fichier règle LA DONNÉE — le libellé enregistré
 * dans le registre des champs de la fiche pièce, qui vit dans chaque base
 * (locale, serveur d'entreprise, production).
 *
 * Idempotent : ne touche la ligne que si elle porte encore l'ancien nom.
 *   php migrations/run_libelle_prix_grossiste.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$existe = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'produit_formulaire_champ'")->fetchColumn();
if ($existe === 0) {
    echo "registre des champs absent — rien à faire (le semis naîtra avec le bon nom)\n";
    exit(0);
}

$maj = $db->prepare("UPDATE produit_formulaire_champ
                     SET label = 'Prix grossiste'
                     WHERE slug = 'prix_achat' AND label IN ('Prix d''achat', 'Prix d\\'achat')");
$maj->execute();
echo 'libellé mis à jour : ' . $maj->rowCount() . " ligne(s)\n";

$lu = $db->query("SELECT label FROM produit_formulaire_champ WHERE slug = 'prix_achat'")->fetchColumn();
echo "relecture : le champ s'appelle « " . $lu . " »\n";
