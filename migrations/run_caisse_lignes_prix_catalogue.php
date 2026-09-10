<?php
/**
 * LE PRIX TAPÉ À LA MAIN SE VOIT (10/09/2026).
 *
 * À la caisse, le vendeur peut changer le prix d'une ligne, et 49 lignes de
 * ticket sur 57 portent sur une pièce sans prix au catalogue : le prix y est
 * entièrement saisi. La caisse le savait déjà au moment de la vente
 * (caisse_build_cart_from_payload marque la ligne « prix_manuel »), mais
 * n'enregistrait rien : la pièce n°2, au catalogue à 1 999 FCFA, a été vendue
 * 1 500, 15 000, 24 000 et 45 000 FCFA sans aucune trace.
 *
 * Ajoute à caisse_vente_lignes :
 *   - prix_catalogue : le prix du catalogue au moment de la vente (NULL si la
 *     pièce n'en avait pas, ou pour les lignes enregistrées avant cette date) ;
 *   - prix_saisi : 1 si le prix de la ligne a été tapé à la main.
 *
 * Les lignes déjà enregistrées gardent prix_saisi = 0 et prix_catalogue NULL :
 * on ne connaît pas le prix du catalogue de ces jours-là.
 *
 * Idempotente. À JOUER SUR CHAQUE SERVEUR (foutasvr ET VPS) : la table est
 * synchronisée. Le code n'écrit ces colonnes que si elles existent.
 *
 * Usage : php migrations/run_caisse_lignes_prix_catalogue.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';

$colonnes = [
    'prix_catalogue' => 'DECIMAL(12,2) NULL DEFAULT NULL',
    'prix_saisi' => 'TINYINT(1) NOT NULL DEFAULT 0',
];

try {
    /** @var PDO $db */
    foreach ($colonnes as $nom => $definition) {
        $existe = $db->query('SHOW COLUMNS FROM `caisse_vente_lignes` LIKE ' . $db->quote($nom))->fetch();
        if ($existe) {
            echo "Colonne caisse_vente_lignes.$nom : déjà présente.\n";
            continue;
        }
        $db->exec("ALTER TABLE `caisse_vente_lignes` ADD COLUMN `$nom` $definition");
        echo "Colonne caisse_vente_lignes.$nom : ajoutée.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'caisse_vente_lignes : ' . $e->getMessage() . "\n");
    exit(1);
}
