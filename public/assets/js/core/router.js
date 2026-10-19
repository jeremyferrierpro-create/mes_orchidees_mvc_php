/**
 * @file core/router.js
 * @description Je lis l'URL du navigateur et je dis sur quelle page on se trouve
 *              (accueil, collection, rappels, etc.) pour que app.js lance le bon
 *              initialisateur.
 * @author Jeremy Ferrier
 * @version 2.0
 */

// ===========================================================================
// FICHIER : core/router.js — Mon routeur ultra-leger maison
// ===========================================================================
// J'ai choisi de coder moi-meme ce routeur plutot que d'importer une librairie
// externe. Mon besoin est simple : savoir sur quelle page je me trouve pour
// lancer le bon module. En Vanilla JS, quelques verifications sur
// window.location.pathname suffisent et m'evitent une dependance inutile.
//
// Version 2.0 — Raccordement aux vraies routes MVC : l'application ne sert plus
// des fichiers statiques (macollection.php, conseils.php…) mais des routes MVC
// (/macollection, /collection/{id}, /rappels…). Je reconnais donc ces routes.
// Je raisonne sur les DERNIERS segments du chemin : ainsi un eventuel prefixe
// d'installation (base_path / APP_BASE_PATH) est ignore sans que j'aie besoin de
// le connaitre cote client — je ne code en dur ni domaine, ni port, ni prefixe.

/**
 * J'analyse l'URL et je renvoie l'identifiant de la page courante.
 * @returns {string} Un identifiant stable : "home", "encyclopedia",
 *   "collection-dashboard", "collection-detail", "rappels", "conseils",
 *   "admin-dashboard", "authentication", les identifiants historiques
 *   ("collection", "administration") pour les anciennes pages *.php, ou "other".
 * @example
 * getCurrentPage(); // "collection-dashboard" sur /macollection
 */
export function getCurrentPage() {
    // Je recupere le chemin sans la query ni le hash (pathname ne les contient pas).
    let path = window.location.pathname || '/';

    // Normalisation previsible : je retire le ou les slashes finaux (sauf la racine).
    if (path.length > 1) {
        path = path.replace(/\/+$/, '');
    }

    // --- 1) Compatibilite historique : anciennes pages statiques *.php ---
    // Elles ne sont plus servies par l'app MVC, mais je les reconnais toujours si
    // elles sont presentes, sans modifier leur comportement d'origine.
    if (path === '/' || path.endsWith('index.php')) return 'home';
    if (path.endsWith('encyclopedie.php')) return 'encyclopedia';
    if (path.endsWith('macollection.php')) return 'collection';
    if (path.endsWith('conseils.php')) return 'conseils';
    if (path.endsWith('administration.php')) return 'administration';
    if (path.endsWith('authentification.php')) return 'authentication';

    // --- 2) Routes MVC reelles ---
    // Je decoupe en segments (minuscules) et je raisonne sur les derniers, ce qui
    // rend la detection independante d'un prefixe d'installation.
    const segments = path.split('/').filter(Boolean).map((s) => s.toLowerCase());
    if (segments.length === 0) return 'home';

    const last = segments[segments.length - 1];
    const prev = segments[segments.length - 2];

    // Tableau de bord « Ma Collection » (liste des plantes du proprietaire).
    if (last === 'macollection') return 'collection-dashboard';

    // Fiche detaillee d'une plante : /collection/{id}. Je la distingue bien de la
    // simple liste et de la creation (POST /collection/new, jamais en GET page).
    if (prev === 'collection' && last && last !== 'new') return 'collection-detail';

    // Vue dediee « Mes rappels » de soins.
    if (last === 'rappels') return 'rappels';

    // Page publique « Encyclopedie ».
    if (last === 'encyclopedie') return 'encyclopedia';

    // Page publique « Conseils » (l'initialiseur historique est protece : no-op
    // sur une vue serveur qui n'a pas sa modale).
    if (last === 'conseils') return 'conseils';

    // Back-office admin MVC. J'utilise un identifiant DISTINCT de l'ancien
    // "administration" pour ne pas declencher l'ancien module admin (qui repose
    // sur une authentification localStorage et deplacerait l'utilisateur).
    if (last === 'administration') return 'admin-dashboard';

    // Authentification (login / register). Initialiseur historique protece.
    if (last === 'login' || last === 'register') return 'authentication';

    // --- 3) Repli : page inconnue → aucun initialisateur (comportement d'origine). ---
    return 'other';
}
