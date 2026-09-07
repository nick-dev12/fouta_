<?php
/**
 * LA BOX SE RANGE ENTRE LA BARRE ET LA POSITION (07/09/2026) — décision de la
 * direction, deuxième passe.
 *
 * On avait rendu la box « facultative » (run_niveau_facultatif.php) sans regarder
 * OÙ elle se trouve dans la chaîne. Or :
 *   • sur le serveur de l'entreprise et en production, l'ordre est
 *     Barre › Position › Box — la box est en QUEUE, donc on ne peut rien sauter ;
 *   • sur le poste de dev, il n'y a AUCUN niveau actif après la box.
 * Dans les deux cas, la promesse « on peut sauter la box pour aller directement
 * à la position » ne pouvait pas se tenir : le constat de la direction, « les
 * box ne sont pas optionnelles, ou bien c'est mal fait », est exact.
 *
 * Le rangement attendu, celui de l'entrepôt réel :
 *
 *      … › Étagère › BARRE (étiquette QR) › BOX (facultative) › POSITION
 *
 * La box est un contenant de la barre (Box 1, Box 2… empilables) ; la position
 * est le point de rangement. Une pièce va dans une box de la barre, OU
 * directement à une position de la barre quand il n'y a pas de box.
 *
 * Ce que fait la migration, sans rien détruire :
 *   1. elle place la box JUSTE APRÈS la barre, devant la position ;
 *   2. elle garde le reste de la chaîne dans son ordre actuel ;
 *   3. elle rappelle que la box doit être facultative (drapeau posé par
 *      run_niveau_facultatif.php) ;
 *   4. elle ne touche à AUCUN nœud existant : les box déjà créées sous des
 *      barres, et les positions déjà créées, gardent leur parent. L'ordre des
 *      niveaux ne gouverne que ce qu'on PROPOSE à la création.
 *
 * Idempotente :  php migrations/run_ordre_box_avant_position.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$niveaux = $db->query('SELECT id, slug, label, ordre, actif, facultatif
                         FROM entrepot_hierarchie_niveau
                        WHERE sync_deleted_at IS NULL
                     ORDER BY ordre, id')->fetchAll(PDO::FETCH_ASSOC);

$trouver = static function (array $niveaux, array $slugs, $actif_seulement = true) {
    foreach ($niveaux as $n) {
        if (in_array((string) $n['slug'], $slugs, true) && (!$actif_seulement || (int) $n['actif'] === 1)) {
            return $n;
        }
    }

    return null;
};

$barre = $trouver($niveaux, ['barre']);
$box = $trouver($niveaux, ['box']);
/* « osition » est la faute de frappe historique du même niveau : les deux
   comptent comme « la position ». */
$position = $trouver($niveaux, ['position', 'osition']);

if ($barre === null || $box === null) {
    echo "Pas de niveau « barre » ou « box » actif : rien à ranger.\n";
    exit(0);
}

echo 'barre    : #', $barre['id'], ' « ', $barre['label'], ' » (ordre ', $barre['ordre'], ")\n";
echo 'box      : #', $box['id'], ' « ', $box['label'], ' » (ordre ', $box['ordre'],
     (int) $box['facultatif'] === 1 ? ', facultative' : ', OBLIGATOIRE', ")\n";
echo 'position : ', $position === null ? "AUCUNE ACTIVE\n"
    : '#' . $position['id'] . ' « ' . $position['label'] . ' » (ordre ' . $position['ordre'] . ")\n";

if ($position === null) {
    echo "\nATTENTION : aucun niveau « position » actif. La box est alors le dernier\n",
         "niveau, donc rien à sauter — activez « Positions » dans « Gérer les niveaux »,\n",
         "puis rejouez cette migration.\n";
    exit(0);
}

if ((int) $box['ordre'] < (int) $position['ordre'] && (int) $barre['ordre'] < (int) $box['ordre']) {
    echo "\nLa chaîne est déjà dans le bon ordre : barre › box › position.\n";
} else {
    /* On pose la box entre la barre et la position, sans toucher aux autres.
       Si la place manque entre les deux (ordres collés), on décale la position
       et tout ce qui la suit de 10, ce qui ne change AUCUN parent. */
    $db->beginTransaction();
    try {
        $ordre_box = (int) $barre['ordre'] + 5;
        if ($ordre_box >= (int) $position['ordre']) {
            /* ON NE POUSSE QUE CE QUI VIENT À PARTIR DE LA POSITION — surtout
               pas la barre. Deux niveaux peuvent porter le MÊME numéro d'ordre
               (sur le poste de dev, barre et position étaient tous deux à 60) ;
               l'écran les départage par leur id (ORDER BY ordre, id). Un simple
               « ordre >= » emportait donc la barre avec, et rangeait la box
               AVANT elle — constaté au premier essai, corrigé ici. */
            $decalage = $db->prepare('UPDATE entrepot_hierarchie_niveau
                                         SET ordre = ordre + 10, sync_updated_at = NOW()
                                       WHERE (ordre > :o OR (ordre = :o AND id >= :pid))
                                         AND id <> :box AND sync_deleted_at IS NULL');
            $decalage->execute([
                ':o' => (int) $position['ordre'],
                ':pid' => (int) $position['id'],
                ':box' => (int) $box['id'],
            ]);
            echo 'décalage : ', $decalage->rowCount(), " niveau(x) repoussé(s) de 10 pour faire la place\n";
        }
        $db->prepare('UPDATE entrepot_hierarchie_niveau SET ordre = :o, sync_updated_at = NOW() WHERE id = :id')
           ->execute([':o' => $ordre_box, ':id' => (int) $box['id']]);
        $db->commit();
        echo 'box rangée à l\'ordre ', $ordre_box, " — juste après la barre, devant la position.\n";
    } catch (PDOException $e) {
        $db->rollBack();
        echo 'ÉCHEC, rien n\'a bougé : ', $e->getMessage(), "\n";
        exit(1);
    }
}

if ((int) $box['facultatif'] !== 1) {
    echo "\nRAPPEL : la box n'est pas marquée facultative — jouez\n",
         "php migrations/run_niveau_facultatif.php, sinon elle reste un passage obligé.\n";
}

echo "\nla chaîne, telle qu'elle est maintenant :\n";
foreach ($db->query('SELECT id, slug, label, ordre, actif, facultatif
                       FROM entrepot_hierarchie_niveau
                      WHERE sync_deleted_at IS NULL AND actif = 1
                   ORDER BY ordre, id') as $r) {
    printf("  %-4s %-10s %-14s ordre %-5s %s\n", $r['id'], $r['slug'], $r['label'], $r['ordre'],
        (int) $r['facultatif'] === 1 ? 'FACULTATIF — on peut le sauter' : '');
}
