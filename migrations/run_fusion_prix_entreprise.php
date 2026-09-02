<?php
/**
 * FUSION DU PRIX ENTREPRISE (02/09/2026) — le champ en double disparaît.
 *
 * L'HISTOIRE : l'équipe a créé en production (12/08) un CHAMP PERSONNALISÉ
 * « Prix Entreprise » (nom machine amputé « rix_ntreprise » par le vieux bug
 * des majuscules), et y a posé des prix sur 73 pièces. La refonte a ensuite
 * branché la VRAIE colonne produits.prix_entreprise — restée vide en prod.
 * Résultat : DEUX champs au même libellé sur les formulaires ; on tape 15000
 * dans l'un, l'écran raffiche l'autre — « le prix change en 1500 »
 * (constat direction du 02/09).
 *
 * LE GESTE :
 *   1. chaque valeur du champ personnalisé est recopiée dans la vraie colonne
 *      SI celle-ci est vide — une vraie valeur déjà posée n'est JAMAIS
 *      écrasée (l'écart est signalé) ;
 *   2. le champ personnalisé est DÉSACTIVÉ (actif = 0, valeurs conservées en
 *      trace dans produit_champ_valeur) : il ne reste qu'un « Prix
 *      entreprise » sur tous les écrans.
 *
 * Idempotent : rejouée, elle recopie 0 valeur et constate le champ inactif.
 *   php migrations/run_fusion_prix_entreprise.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

/* le champ en double — par son nom machine amputé, sinon par son libellé
   (champ NON système, sans colonne réelle : jamais le vrai champ) */
$champ = $db->query("SELECT id, slug, label, actif FROM produit_formulaire_champ
                     WHERE (slug = 'rix_ntreprise' OR (label = 'Prix Entreprise' AND est_systeme = 0 AND colonne_db IS NULL))
                     ORDER BY (slug = 'rix_ntreprise') DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$champ) {
    echo "aucun champ personnalisé « Prix Entreprise » — rien à faire\n";
    exit(0);
}
printf("champ en double : #%d « %s » (slug %s, %s)\n", $champ['id'], $champ['label'], $champ['slug'],
    ((int) $champ['actif'] === 1 ? 'encore actif' : 'déjà inactif'));

/* 1. la recopie, pièce par pièce */
$valeurs = $db->prepare('SELECT v.produit_id, v.valeur, p.prix_entreprise, p.nom
                         FROM produit_champ_valeur v
                         JOIN produits p ON p.id = v.produit_id
                         WHERE v.champ_id = ? AND v.valeur IS NOT NULL AND TRIM(v.valeur) <> \'\'');
$valeurs->execute([(int) $champ['id']]);
$poser = $db->prepare('UPDATE produits SET prix_entreprise = ? WHERE id = ?');

$copies = 0;
$deja = 0;
$conflits = 0;
$invalides = 0;
foreach ($valeurs->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $brut = str_replace([' ', "\xc2\xa0", "\xe2\x80\xaf", ','], ['', '', '', '.'], trim((string) $v['valeur']));
    if (!is_numeric($brut) || (float) $brut < 0) {
        $invalides++;
        printf("  INVALIDE  pièce #%-5d « %s » : « %s » laissé de côté\n", $v['produit_id'], mb_substr($v['nom'], 0, 34), $v['valeur']);
        continue;
    }
    $montant = (float) $brut;
    if ($v['prix_entreprise'] !== null) {
        if (abs((float) $v['prix_entreprise'] - $montant) < 0.005) {
            $deja++;
        } else {
            $conflits++;
            printf("  CONFLIT   pièce #%-5d « %s » : colonne %.2f ≠ champ %.2f — la colonne GARDE sa valeur\n",
                $v['produit_id'], mb_substr($v['nom'], 0, 34), (float) $v['prix_entreprise'], $montant);
        }
        continue;
    }
    $poser->execute([$montant, $v['produit_id']]);
    $copies++;
    printf("  copié     pièce #%-5d « %-34s » : %s\n", $v['produit_id'], mb_substr($v['nom'], 0, 34), rtrim(rtrim(number_format($montant, 2, '.', ''), '0'), '.'));
}
printf("RECOPIE : %d copiée(s), %d déjà à l'identique, %d conflit(s) préservé(s), %d invalide(s)\n",
    $copies, $deja, $conflits, $invalides);

/* 2. la désactivation douce */
if ((int) $champ['actif'] === 1) {
    $db->prepare('UPDATE produit_formulaire_champ SET actif = 0, sync_updated_at = NOW() WHERE id = ?')
       ->execute([(int) $champ['id']]);
    echo "champ personnalisé DÉSACTIVÉ (valeurs conservées en trace)\n";
} else {
    echo "champ personnalisé déjà inactif\n";
}

/* relecture */
echo 'relecture : ',
    $db->query('SELECT COUNT(*) FROM produits WHERE prix_entreprise IS NOT NULL')->fetchColumn(),
    ' pièce(s) avec un prix entreprise dans la vraie colonne ; champ actif = ',
    $db->query('SELECT actif FROM produit_formulaire_champ WHERE id = ' . (int) $champ['id'])->fetchColumn(), "\n";
