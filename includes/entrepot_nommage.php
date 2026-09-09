<?php
/**
 * LE NOM ET LE NUMÉRO D'UN EMPLACEMENT — les calculs PURS de l'écran Structure.
 *
 * 08/09/2026 — constat de la direction : « À l'étage 2 on s'était arrêté à B4.
 * Quand j'ajoute une autre barre, ça devrait afficher B5, mais ça recommence
 * 1, 2, 3, 4, 5. » Puis, devant le code d'étiquette : « quand on clique sur
 * l'étiquette on voit 01 » alors que la barre s'appelle B5.
 *
 * DEUX COMPTEURS VIVAIENT SÉPARÉMENT :
 *   — le NOM, fabriqué à l'écran, repartait toujours de 1 (nom . ($i + 1)) ;
 *   — le NUMÉRO en base, lui, comptait les frères sous l'étagère.
 * Ils divergeaient donc dès qu'une barre naissait ailleurs que dans l'ordre,
 * et le libellé de l'étiquette — qui n'affiche QUE le numéro — ne disait plus
 * le nom de la barre.
 *
 * LA RÈGLE, LUE DANS LES ÉTIQUETTES DÉJÀ IMPRIMÉES (consigne de la direction :
 * « on doit suivre la même logique que les étiquettes déjà imprimées ») : le
 * numéro d'une barre est son rang DANS SON RAYON, en une seule suite continue
 * à travers toutes les étagères. Mesuré sur les données : 15A va de 1 à 21,
 * 21A de 1 à 33, sans trou ni répétition.
 *
 * D'où le principe tenu ici : LE NUMÉRO DÉCIDE, LE NOM SUIT. La couche base
 * attribue le numéro dans la bonne portée ; ces fonctions composent le nom
 * avec CE numéro. Nom et étiquette ne peuvent plus diverger.
 *
 * Ni base ni session ici : tout se prouve dans tests/test_structure_numerotation_barres.php.
 */

if (!function_exists('entrepot_nom_decomposer')) {

    /**
     * Un nom qui finit par un nombre se lit « préfixe + numéro » : B5 → (B, 5),
     * B05 → (B, 5, sur 2 chiffres), 12 → ('', 12). Un nom qui ne finit pas par
     * un nombre n'en est pas un : « Zone A » et « 15A » rendent null — ce sont
     * des noms entiers, que l'on ne renumérote pas.
     *
     * @param string $nom
     * @return array{prefixe: string, numero: int, largeur: int}|null
     */
    function entrepot_nom_decomposer($nom)
    {
        $nom = trim((string) $nom);
        if ($nom === '' || !preg_match('/^(.*?)(\d+)$/u', $nom, $m)) {
            return null;
        }

        return [
            'prefixe' => $m[1],
            'numero' => (int) $m[2],
            'largeur' => strlen($m[2]), // « B05 » garde ses deux chiffres
        ];
    }

    /**
     * Le nom d'un emplacement portant ce numéro : ('B', 5, 2) → « B05 ».
     *
     * @param string $prefixe
     * @param int    $numero
     * @param int    $largeur nombre de chiffres minimum (zéros de tête)
     * @return string
     */
    function entrepot_nom_composer($prefixe, $numero, $largeur = 1)
    {
        $numero = max(0, (int) $numero);
        $largeur = max(1, (int) $largeur);

        return (string) $prefixe . str_pad((string) $numero, $largeur, '0', STR_PAD_LEFT);
    }

    /**
     * Deux noms d'emplacement se comparent sans casse ni blancs superflus.
     *
     * @param string $a
     * @param string $b
     * @return bool
     */
    function entrepot_nom_meme($a, $b)
    {
        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    /**
     * Combien de chiffres portent les frères d'un même préfixe ? On reprend la
     * largeur du plus grand d'entre eux : après B01…B04 vient B05, pas B5.
     *
     * @param string   $prefixe
     * @param string[] $noms_freres
     * @return int
     */
    function entrepot_nom_largeur_freres($prefixe, array $noms_freres)
    {
        $largeur = 1;
        $plus_grand = -1;
        foreach ($noms_freres as $nom) {
            $d = entrepot_nom_decomposer($nom);
            if ($d === null || !entrepot_nom_meme($d['prefixe'], $prefixe)) {
                continue;
            }
            if ($d['numero'] > $plus_grand) {
                $plus_grand = $d['numero'];
                $largeur = $d['largeur'];
            }
        }

        return $largeur;
    }

    /**
     * Les noms à créer.
     *
     * @param string   $saisi          ce que la direction a tapé (« B », « B9 », « Zone A »)
     * @param int      $combien        1 ou plus
     * @param int      $numero_depart  le numéro que la base attribuera au premier
     *                                 (rang dans la portée : le rayon pour une barre)
     * @param string[] $noms_freres    les noms déjà pris dans cette portée
     * @return array{noms: string[], numeros: int[], suit_le_numero: bool}
     *         suit_le_numero = false quand le nom est posé tel quel (« Zone A ») :
     *         la base garde alors la main sur le numéro.
     */
    function entrepot_noms_serie($saisi, $combien, $numero_depart, array $noms_freres = [])
    {
        $saisi = trim((string) $saisi);
        $combien = max(1, (int) $combien);
        $numero_depart = max(1, (int) $numero_depart);

        $decompose = entrepot_nom_decomposer($saisi);
        if ($decompose !== null) {
            /* NUMÉRO ÉCRIT À LA MAIN : « B9 » veut dire B9, et une série de 3
               veut dire B9, B10, B11. On honore la saisie ; la base refusera
               clairement si l'un de ces numéros est déjà pris. */
            $prefixe = $decompose['prefixe'];
            $depart = $decompose['numero'];
            $largeur = $decompose['largeur'];
        } else {
            $prefixe = $saisi;
            $largeur = entrepot_nom_largeur_freres($prefixe, $noms_freres);
            $a_des_freres = false;
            foreach ($noms_freres as $nom) {
                $d = entrepot_nom_decomposer($nom);
                if ($d !== null && entrepot_nom_meme($d['prefixe'], $prefixe)) {
                    $a_des_freres = true;
                    break;
                }
            }
            if (!$a_des_freres && $combien === 1) {
                /* « Zone A » tout seul reste « Zone A » : on n'invente pas un
                   suffixe à un nom qui n'en a jamais eu. */
                return ['noms' => [$saisi], 'numeros' => [$numero_depart], 'suit_le_numero' => false];
            }
            /* LES NOMS ONT PU COURIR DEVANT LES NUMÉROS (09/09/2026, mesuré sur
               le serveur de l'entreprise) : dans 25 rayons, les barres sont
               nommées à la suite d'une étagère à l'autre (B1…B66) alors que
               leurs numéros repartaient de 1 à chaque étagère. Repartir du seul
               plus grand numéro (13) aurait proposé « B14 » — déjà pris — et
               bloqué toute création. On repart donc après le plus grand des
               deux : le numéro attribué par la base ET le plus grand nom du
               même préfixe. Le nom et le numéro restent égaux. */
            $plus_grand_nom = 0;
            foreach ($noms_freres as $nom) {
                $d = entrepot_nom_decomposer($nom);
                if ($d !== null && entrepot_nom_meme($d['prefixe'], $prefixe) && $d['numero'] > $plus_grand_nom) {
                    $plus_grand_nom = $d['numero'];
                }
            }
            $depart = max($numero_depart, $plus_grand_nom + 1);
        }

        $noms = [];
        $numeros = [];
        for ($i = 0; $i < $combien; $i++) {
            $n = $depart + $i;
            $noms[] = entrepot_nom_composer($prefixe, $n, $largeur);
            $numeros[] = $n;
        }

        return ['noms' => $noms, 'numeros' => $numeros, 'suit_le_numero' => true];
    }
}
