<?php
/**
 * L'ÉDITEUR PHOTO — les calculs PURS partagés par l'écran (admin/produits/
 * photo-editer.php) et son point d'enregistrement (ajax_photo_enregistrer.php).
 *
 * 08/09/2026 — la direction a constaté que « les images ajoutées auparavant
 * s'affichent même après avoir changé les photos ». Deux des causes vivaient
 * dans l'éditeur lui-même :
 *
 *  1. La galerie finale mettait les NOUVELLES photos à la FIN : l'ancienne
 *     restait principale (étiquette, page du QR, catalogue) tant que
 *     l'infographiste ne cliquait pas « Principale » puis Enregistrer une
 *     seconde fois — et l'écran ne propose pas « Principale » sur une photo
 *     encore en attente. Désormais les nouvelles passent EN PREMIER, sauf si
 *     l'utilisateur a lui-même désigné une principale parmi les gardées.
 *
 *  2. Deux enregistrements pouvaient se croiser (autre poste, autre onglet,
 *     « pièce suivante ») : le second remettait l'ancien ordre et EFFAÇAIT du
 *     disque les photos ajoutées entre-temps. L'écran emporte donc une
 *     EMPREINTE de la galerie qu'il a chargée ; l'enregistrement la recalcule
 *     sur la ligne actuelle et refuse tout si elle a bougé.
 *
 * Ni base ni fichier ici : ces règles se prouvent telles quelles dans
 * tests/test_editeur_photo_nouvelles_photos.php.
 */

if (!function_exists('photo_editeur_chemin_normaliser')) {

    /** Un chemin de photo, lu de la même façon partout (barres obliques, sans blancs). */
    function photo_editeur_chemin_normaliser($rel)
    {
        return trim(str_replace('\\', '/', (string) $rel));
    }
}

if (!function_exists('photo_editeur_composer_galerie')) {

    /**
     * Compose la galerie finale d'une pièce.
     *
     * @param string[]    $gardees            chemins gardés, dans l'ordre voulu à l'écran
     * @param string[]    $nouvelles          chemins qui viennent d'arriver, dans l'ordre d'arrivée
     * @param string|null $principale_choisie chemin désigné « Principale » par l'utilisateur
     *                                        parmi les gardées ; null s'il n'a rien désigné
     * @return string[] la galerie ; la 1re EST la principale ; sans doublon ni chemin vide
     */
    function photo_editeur_composer_galerie(array $gardees, array $nouvelles, $principale_choisie = null)
    {
        /* Les deux fonctions de ce fichier lisent les chemins de la MÊME façon
           (antislashs de Windows ramenés en barres obliques, blancs coupés) :
           sans cela le chemin rendu par photo_editeur_principale_choisie(),
           normalisé, n'était pas reconnu parmi des gardées qui ne l'étaient
           pas — et le choix de l'utilisateur passait à la trappe (08/09/2026). */
        $nettoyer = function (array $liste) {
            $propre = [];
            foreach ($liste as $rel) {
                $rel = photo_editeur_chemin_normaliser($rel);
                if ($rel !== '' && !in_array($rel, $propre, true)) {
                    $propre[] = $rel;
                }
            }
            return $propre;
        };
        $gardees = $nettoyer($gardees);
        $nouvelles = $nettoyer($nouvelles);

        $principale_choisie = $principale_choisie === null ? null : photo_editeur_chemin_normaliser($principale_choisie);
        if ($principale_choisie !== null && $principale_choisie !== '' && in_array($principale_choisie, $gardees, true)) {
            /* Choix explicite : la principale désignée d'abord, les autres gardées
               dans leur ordre, puis les nouvelles (autres faces) à la suite. */
            $reste = array_values(array_diff($gardees, [$principale_choisie]));
            $finale = array_merge([$principale_choisie], $reste, $nouvelles);
        } else {
            /* Pas de choix : ce qu'on vient d'ajouter remplace l'ancienne en tête. */
            $finale = array_merge($nouvelles, $gardees);
        }

        return $nettoyer($finale);
    }

    /**
     * L'ordre envoyé exprime-t-il un choix explicite de principale ?
     *
     * Oui quand l'ordre envoyé n'est plus celui de la base : l'utilisateur a
     * mis autre chose devant (bouton « Principale » ou glisser-déposer). On
     * compare la tête des gardées au PREMIER de la galerie en base qui est
     * encore gardé — pas seulement à l'ancienne principale : celle-ci peut
     * avoir été retirée dans la même session, et son clic « Principale » sur
     * une autre photo serait alors passé inaperçu (08/09/2026, contre-lecture).
     * Sans galerie en base, on retombe sur l'ancienne principale seule.
     *
     * @param string[]    $gardees             chemins gardés, dans l'ordre envoyé
     * @param string|null $ancienne_principale image_principale telle qu'en base
     * @param string[]    $actuelles           la galerie telle qu'en base, dans son ordre
     * @return string|null le chemin désigné, ou null
     */
    function photo_editeur_principale_choisie(array $gardees, $ancienne_principale, array $actuelles = [])
    {
        $propre = function (array $liste) {
            $out = [];
            foreach ($liste as $rel) {
                $rel = photo_editeur_chemin_normaliser($rel);
                if ($rel !== '' && !in_array($rel, $out, true)) {
                    $out[] = $rel;
                }
            }
            return $out;
        };
        $gardees = $propre($gardees);
        if ($gardees === []) {
            return null;
        }

        /* la référence : le premier de la galerie en base encore gardé */
        $reference = null;
        foreach ($propre($actuelles) as $rel) {
            if (in_array($rel, $gardees, true)) {
                $reference = $rel;
                break;
            }
        }
        if ($reference === null) {
            $ancienne = photo_editeur_chemin_normaliser($ancienne_principale);
            if ($ancienne === '' || !in_array($ancienne, $gardees, true)) {
                return null; // rien de connu n'a survécu : une nouvelle passera devant
            }
            $reference = $ancienne;
        }

        return $gardees[0] === $reference ? null : $gardees[0];
    }

    /**
     * L'empreinte de la galerie TELLE QU'EN BASE (image_principale + JSON images,
     * chaînes brutes, NULL compté comme vide). L'écran l'emporte au chargement,
     * l'enregistrement la recalcule : si elle diffère, quelqu'un a enregistré
     * entre-temps.
     *
     * @param string|null $image_principale
     * @param string|null $images_json
     * @return string md5 (32 caractères hexadécimaux)
     */
    function photo_editeur_empreinte($image_principale, $images_json)
    {
        return md5((string) $image_principale . '|' . (string) $images_json);
    }
}
