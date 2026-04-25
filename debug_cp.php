<?php
session_start();
require_once __DIR__ . '/conn/conn.php';
global $db;
if (!$db) { echo "DB null!\n"; exit; }
$stmt = $db->query('SELECT id, nom, prenom, statut, image FROM commandes_personnalisees ORDER BY id DESC LIMIT 5');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "ID: " . $row['id'] . " | " . $row['prenom'] . " " . $row['nom'] . " | image: " . var_export($row['image'], true) . "\n";
}
$stmt2 = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'image'");
$col = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "\nColonne image: ";
print_r($col);
