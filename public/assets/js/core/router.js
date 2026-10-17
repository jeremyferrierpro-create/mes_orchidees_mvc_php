/**
 * @file core/router.js
 * @description Je lis l'URL du navigateur et je dis sur quelle page on est (home, collection, etc.).
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/router.js â€” Mon routeur ultra-lÃ©ger maison
// ===========================================================================
// J'ai choisi de coder moi-mÃªme ce routeur plutÃ´t que d'importer une librairie
// externe. Pourquoi ? Parce que mon besoin est trÃ¨s simple : savoir sur quelle
// page je me trouve pour lancer le bon module. En Vanilla JS, quelques
// vÃ©rifications sur window.location.pathname suffisent et m'Ã©vitent une
// dÃ©pendance inutile. C'est aussi une preuve de maÃ®trise des fondamentaux.

/**
 * J'analyse l'URL et je renvoie l'identifiant de la page courante.
 * @returns {string} L'identifiant : "home", "encyclopedia", "collection", "conseils", "administration", "authentication" ou "other".
 * @example
 * const page = getCurrentPage(); // "collection" si on est sur macollection.php
 */
export function getCurrentPage() {
    // Je rÃ©cupÃ¨re le chemin complet (ex: "/mon_orchidee/encyclopedie.php" ou "/").
    const path = window.location.pathname;
    if (path.endsWith('index.php') || path === '/' || path.endsWith('/mon_orchidee/')) return 'home';
    if (path.endsWith('encyclopedie.php')) return 'encyclopedia';
    if (path.endsWith('macollection.php')) return 'collection';
    if (path.endsWith('conseils.php')) return 'conseils';
    if (path.endsWith('administration.php')) return 'administration';
    if (path.endsWith('authentification.php')) return 'authentication';
    return 'other';
}

