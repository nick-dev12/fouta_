<?php
/**
 * LA SAISIE QU'ON N'ABANDONNE PAS — ReprendreSaisie (01/09/2026).
 * Programmation procédurale uniquement
 *
 * PORTAGE du second intercepteur de fpl_natif/includes/navigation.php :
 * quitter le formulaire d'ajout d'une pièce puis revenir sur le catalogue
 * y ramène DIRECTEMENT — là où le brouillon (js/fpl-draft.js) attend déjà.
 * C'était le dernier des trois middlewares de FPL relevés par l'audit de
 * fidélité : seul le brouillon avait été porté, pas le rappel vers lui.
 *
 * On en sort par trois portes, comme chez FPL natif :
 *   - « Annuler » sur le formulaire (il porte ?liste=1) ;
 *   - un enregistrement qui aboutit ;
 *   - toute adresse du catalogue portant ?liste=1 — le retour volontaire.
 *
 * Le premier intercepteur de natif (la pile « Retour » qui revient étape
 * par étape) n'est PAS porté : le Retour de ce dépôt est une adresse fixe
 * posée page par page ($fpl_retour_page), et en changer le comportement
 * toucherait tout l'admin — hors du périmètre de ce portage.
 */

/** L'adresse de la page courante, sans le signal de sortie `liste`. */
function saisie_url_courante()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $chemin = parse_url($uri, PHP_URL_PATH);
    $requete = parse_url($uri, PHP_URL_QUERY);
    if ($requete === null || $requete === '') {
        return $chemin;
    }

    parse_str($requete, $params);
    unset($params['liste']);

    return $chemin . ($params !== [] ? '?' . http_build_query($params) : '');
}

/** Les formulaires suivis → les listes qui doivent y ramener. */
function saisie_formulaires_suivis()
{
    return ['produits/ajouter.php' => ['produits/index.php']];
}

/**
 * Le formulaire d'ajout est affiché : on retient où l'utilisateur en est —
 * l'adresse complète, rayon compris, pour que le brouillon retrouve sa clé.
 * À appeler APRÈS tous les gardes qui redirigent : une page qui renvoie
 * ailleurs (rayon manquant, profil restreint) ne doit pas être retenue,
 * ce serait une boucle sans fin.
 */
function saisie_encours_retenir($ecran)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['admin_id'])) {
        return;
    }
    $_SESSION['saisie_encours'] = ['ecran' => (string) $ecran, 'url' => saisie_url_courante()];
}

/** L'enregistrement a abouti (ou l'utilisateur est sorti) : on oublie. */
function saisie_encours_oublier()
{
    unset($_SESSION['saisie_encours']);
}

/**
 * Page de liste dont un formulaire est resté ouvert : on y ramène.
 * `?liste=1` = retour volontaire, la reprise s'arrête là.
 * À appeler AVANT tout affichage (elle redirige et sort).
 */
function saisie_encours_rediriger($ecran_liste)
{
    if (!empty($_GET['liste'])) {
        saisie_encours_oublier();

        return;
    }

    $encours = isset($_SESSION['saisie_encours']) ? $_SESSION['saisie_encours'] : null;
    if (!$encours || !isset($encours['ecran'], $encours['url'])) {
        return;
    }

    $listes = saisie_formulaires_suivis();
    $ramene = isset($listes[$encours['ecran']]) ? $listes[$encours['ecran']] : [];
    if ($encours['ecran'] !== $ecran_liste && in_array($ecran_liste, $ramene, true)) {
        header('Location: ' . $encours['url']);
        exit;
    }
}
