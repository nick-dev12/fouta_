<?php
/**
 * VOIR N'EST PAS MODIFIER (31/08/2026) — le droit sur un champ de la fiche
 * pièce prend un NIVEAU.
 *
 * Jusqu'ici, la table `produit_formulaire_champ_role` disait une seule chose :
 * ce rôle a droit à ce champ. Et « avoir droit » voulait dire voir ET écrire.
 * Or celui qui vend doit lire le prix sans pouvoir le changer : c'est le
 * responsable stock qui tarifie. La colonne `niveau` porte cette distinction :
 *
 *   'voir'     → le champ s'affiche, en lecture seule ; le contrôleur refuse
 *                toute valeur envoyée pour lui et conserve celle en base
 *   'modifier' → comme avant : on voit et on écrit
 *
 * Les lignes déjà en place passent toutes à 'modifier' : rien ne change pour
 * elles, et la migration est sans effet tant que personne ne pose un 'voir'.
 *
 * Puis la MATRICE DES PRIX est semée (décision de la direction, 31/08) :
 *
 *   Prix de vente, Prix promotionnel
 *       voir     : commercial, commercial général, caissier, comptabilité
 *       modifier : responsable stock (+ administrateur, informaticien, développeur)
 *   Prix d'achat
 *       voir     : comptabilité
 *       modifier : responsable stock (+ administrateur, informaticien, développeur)
 *   Fournisseur
 *       modifier : responsable stock (+ administrateur, informaticien, développeur)
 *       — ni la vente ni la caisse : le fournisseur est une affaire d'achat.
 *         Conséquence assumée : le comptoir perd son filtre « par fournisseur ».
 *   Le rayonniste (gestion_stock) n'est sur aucune de ces lignes : il ne voit
 *   ni les prix ni le fournisseur, comme avant.
 *
 * Idempotent, se rejoue sans risque :
 *   php migrations/run_champ_role_niveau.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

/* ------------------------------------------------------------------ 1. LA COLONNE */
$table_ok = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                              WHERE TABLE_SCHEMA = DATABASE()
                                AND TABLE_NAME = 'produit_formulaire_champ_role'")->fetchColumn();
if ($table_ok === 0) {
    echo "produit_formulaire_champ_role : table absente — lancez d'abord la migration des champs produit.\n";
    exit(1);
}

$col = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'produit_formulaire_champ_role'
                           AND COLUMN_NAME = 'niveau'")->fetchColumn();
if ($col === 0) {
    $db->exec("ALTER TABLE `produit_formulaire_champ_role`
               ADD COLUMN `niveau` ENUM('voir','modifier') NOT NULL DEFAULT 'modifier' AFTER `role`");
    echo "colonne `niveau` ajoutée (tout le monde à 'modifier' : aucun changement de comportement)\n";
} else {
    echo "colonne `niveau` : déjà là\n";
}

/* ------------------------------------------------------------------ 2. LA MATRICE */
/* « developpeur » ne se sème plus (31/08 au soir) : le profil technique
 * contourne de toute façon ces règles — produit_formulaire_acces_bypass_role()
 * couvre informaticien ET developpeur — et le rôle n'est plus proposé. */
$technique = ['admin', 'informaticien'];

$matrice = [
    'prix' => [
        'voir'     => ['commercial', 'commercial_general', 'caissier', 'comptabilite'],
        'modifier' => array_merge(['gestion_stock_general'], $technique),
    ],
    'prix_promotion' => [
        'voir'     => ['commercial', 'commercial_general', 'caissier', 'comptabilite'],
        'modifier' => array_merge(['gestion_stock_general'], $technique),
    ],
    'prix_achat' => [
        'voir'     => ['comptabilite'],
        'modifier' => array_merge(['gestion_stock_general'], $technique),
    ],
    'fournisseur_id' => [
        'voir'     => [],
        'modifier' => array_merge(['gestion_stock_general'], $technique),
    ],
];

$champ_id = $db->prepare('SELECT id FROM produit_formulaire_champ WHERE slug = :s LIMIT 1');
$vider    = $db->prepare('DELETE FROM produit_formulaire_champ_role WHERE champ_id = :c');
$poser    = $db->prepare('INSERT INTO produit_formulaire_champ_role (champ_id, role, niveau, date_modification)
                          VALUES (:c, :r, :n, NOW())');

foreach ($matrice as $slug => $niveaux) {
    $champ_id->execute([':s' => $slug]);
    $cid = (int) $champ_id->fetchColumn();
    if ($cid <= 0) {
        echo "  $slug : champ introuvable — ignoré\n";
        continue;
    }
    $db->beginTransaction();
    $vider->execute([':c' => $cid]);
    $compte = ['voir' => 0, 'modifier' => 0];
    foreach ($niveaux as $niveau => $roles) {
        foreach ($roles as $role) {
            $poser->execute([':c' => $cid, ':r' => $role, ':n' => $niveau]);
            $compte[$niveau]++;
        }
    }
    $db->commit();
    printf("  %-16s voir: %-2d  modifier: %d\n", $slug, $compte['voir'], $compte['modifier']);
}

/* ------------------------------------------------------------------ 3. RELECTURE */
echo "\nCe que la base dit maintenant :\n";
$lecture = $db->query("SELECT c.slug, r.niveau, GROUP_CONCAT(r.role ORDER BY r.role SEPARATOR ', ') AS roles
                       FROM produit_formulaire_champ c
                       JOIN produit_formulaire_champ_role r ON r.champ_id = c.id
                       WHERE c.slug IN ('prix', 'prix_promotion', 'prix_achat', 'fournisseur_id')
                       GROUP BY c.slug, r.niveau
                       ORDER BY c.slug, r.niveau");
foreach ($lecture as $row) {
    printf("  %-16s %-9s %s\n", $row['slug'], $row['niveau'], $row['roles']);
}
echo "\nTerminé.\n";
