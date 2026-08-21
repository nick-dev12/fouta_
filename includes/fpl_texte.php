<?php
/**
 * Réparation d'affichage des textes mal encodés.
 *
 * Certaines lignes du catalogue portent un texte enregistré DEUX FOIS en UTF-8 :
 * le « É » a été relu comme du latin puis ré-enregistré, ce qui donne « Ã‰ ».
 * Relevé le 20/08/2026 sur la base du siège : 2 catégories (« Échappement » et
 * « Électricité et éclairage ») et 4 pièces sont dans ce cas.
 *
 * Ce fichier ne corrige RIEN en base : il répare seulement ce qui s'affiche.
 * La donnée reste telle quelle jusqu'à ce que l'équipe décide d'une migration.
 */

if (!function_exists('fpl_texte')) {

    /**
     * Rend un texte lisible même s'il a été doublement encodé.
     *
     * On ne tente la conversion que sur les chaînes qui portent la marque du
     * double encodage (la séquence C3 83, soit « Ã »), et seulement si le
     * résultat est de l'UTF-8 valide. Un texte sain ressort donc intact.
     */
    function fpl_texte($valeur)
    {
        $s = (string) $valeur;
        if ($s === '') {
            return $s;
        }

        // « Ã » en octets : la signature d'un texte encodé deux fois.
        if (strpos($s, "\xC3\x83") === false && strpos($s, "\xC3\x82") === false) {
            return $s;
        }

        // CP1252 et non ISO-8859-1 : c'est lui qui rend l'octet 0x89 sous la
        // forme « ‰ », signature du double encodage rencontré ici.
        $repare = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        if ($repare === false || $repare === '') {
            return $s;
        }

        // On n'accepte la réparation que si elle produit de l'UTF-8 valide
        // ET qu'elle fait disparaître la signature du double encodage.
        if (!mb_check_encoding($repare, 'UTF-8')) {
            return $s;
        }
        if (strpos($repare, "\xC3\x83") !== false) {
            return $s;
        }

        return $repare;
    }
}

if (!function_exists('fpl_e')) {

    /**
     * Échappe pour le HTML, après réparation de l'encodage.
     * À utiliser partout où l'on affiche un nom venu du catalogue.
     */
    function fpl_e($valeur)
    {
        return htmlspecialchars(fpl_texte($valeur), ENT_QUOTES, 'UTF-8');
    }
}
