<?php
/**
 * Ajoute employes.montant_trimf_mensuel et retire la clé trimf des taux globaux (JSON).
 * php migrations/run_add_employes_montant_trimf_mensuel.php
 */
require_once __DIR__ . '/../conn/conn.php';

try {
    $db->exec('ALTER TABLE `employes`
      ADD COLUMN `montant_trimf_mensuel` DECIMAL(14,2) NULL DEFAULT NULL COMMENT \'TRIMF mensuel FCFA (fiche employé)\' AFTER `montant_irpp_mensuel`');
    echo "+ employes.montant_trimf_mensuel\n";
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'Duplicate column') !== false) {
        echo "— employes.montant_trimf_mensuel existe déjà\n";
    } else {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

try {
    $st = $db->query('SELECT retenues_taux_json FROM bulletin_paie_parametres WHERE id = 1 LIMIT 1');
    $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
    if ($row && !empty($row['retenues_taux_json'])) {
        $j = json_decode((string) $row['retenues_taux_json'], true);
        if (is_array($j) && array_key_exists('trimf', $j)) {
            unset($j['trimf']);
            $up = $db->prepare('UPDATE bulletin_paie_parametres SET retenues_taux_json = :j WHERE id = 1');
            $up->execute(['j' => json_encode($j, JSON_UNESCAPED_UNICODE)]);
            echo "~ bulletin_paie_parametres : clé trimf retirée du JSON taux\n";
        } else {
            echo "— pas de clé trimf dans retenues_taux_json\n";
        }
    }
} catch (PDOException $e) {
    echo "— nettoyage JSON taux ignoré : " . $e->getMessage() . "\n";
}

echo "\nMigration TRIMF employé terminée.\n";
