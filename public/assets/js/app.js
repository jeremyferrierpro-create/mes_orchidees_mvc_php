/**
 * @file app.js
 * @description Je suis le point d'entree unique de l'application : je lance la navigation, l'animation, la recherche et j'ouvre la bonne page selon l'URL.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : app.js â€” Point d'entrÃ©e unique de mon application (Router principal)
// ===========================================================================
// J'ai choisi de centraliser tout le dÃ©marrage de mon application dans ce seul
// fichier. Pourquoi ? Pour appliquer le pattern du "point d'entrÃ©e unique" :
// au lieu d'avoir un <script> diffÃ©rent par page, j'ai un seul app.js qui
// dÃ©cide quoi lancer selon la page courante. C'est plus maintenable et
// cela m'oblige Ã  structurer mon code en modules ES6+ (import/export).
// J'ai volontairement travaillÃ© en Vanilla JS moderne (ES6+) sans framework
// lourd comme React ou Vue.

// J'importe ici la fonction qui installe le mode hors-ligne PWA (Progressive Web App).
// Pourquoi je l'importe dÃ¨s le dÃ©but ? Parce que le Service Worker doit Ãªtre
// enregistrÃ© au plus tÃ´t pour mettre en cache les ressources et permettre
// une consultation hors-ligne, critÃ¨re important pour la robustesse de l'app.
import { initPWA } from './pwa.js';

// J'importe toutes les fonctions d'initialisation de chaque fonctionnalitÃ©.
// J'ai dÃ©coupÃ© mon code en "features" pour isoler chaque page/fonctionnalitÃ© :
// c'est l'application du principe de sÃ©paration des responsabilitÃ©s.
import { initNavigation } from './features/navigation.js';
import { initBackgroundAnimation } from './features/background-animation.js';
import { initSearch } from './features/search.js';
import { initAddButton } from './features/add-button.js';
import { initCollection } from './features/collection.js';
import { initAdministration } from './features/administration.js';
import { initConseils } from './features/conseils.js';
import { initAuthForm } from './features/authentication.js';
import { initRappelsToast } from './features/rappels-toast.js';
import { initRappelsModal } from './features/rappels-modal.js';

// J'importe mon petit routeur maison. Il analyse l'URL courante et me renvoie
// un identifiant simple ("home", "collection", "administration"...).
// Pourquoi un routeur maison plutÃ´t qu'une librairie ? Parce qu'en Vanilla JS
// je peux le coder en quelques lignes et je garde le contrÃ´le total.
import { getCurrentPage } from './core/router.js';

// Je crÃ©e ici une table de correspondance (objet littÃ©ral) qui associe chaque
// identifiant de page Ã  sa fonction d'initialisation. C'est mon routeur dÃ©claratif.
// Si la page n'a pas besoin de JS spÃ©cifique, je mets une fonction vide () => {}.
const featureInitializers = {
    home: () => {},
    encyclopedia: () => {},

    // Routes MVC reelles : ce sont des vues rendues cote serveur. Les anciens
    // modules (initCollection / initAdministration) ciblent le DOM du site
    // statique d'origine et ne doivent surtout pas tourner ici (ils dereferencent
    // des elements absents, voire deplacent l'utilisateur). Je les branche donc
    // sur les initialiseurs MVC dedies — ou du no-op quand la page n'a rien de
    // specifique — en attendant les modules des prochaines phases.
    // Phase D : la modale « Gerer mes rappels » vit sur le dashboard et la
    // fiche plante ; son module se met en no-op tout seul si le squelette
    // #rappels-modal est absent de la page courante.
    'collection-dashboard': initRappelsModal,
    'collection-detail': initRappelsModal,
    rappels: () => {},
    'admin-dashboard': () => {},

    // Conseils / authentication : initialiseurs historiques deja proteges (ils
    // sortent en no-op si leur DOM n'est pas present sur la page serveur).
    conseils: initConseils,
    authentication: initAuthForm,

    // Compatibilite avec les anciennes pages statiques *.php (non servies par
    // l'app MVC, mais reconnues par le routeur si presentes).
    collection: initCollection,
    administration: initAdministration
};

// J'Ã©coute l'Ã©vÃ©nement DOMContentLoaded. Pourquoi cet Ã©vÃ©nement prÃ©cisÃ©ment ?
// Parce qu'il garantit que l'arbre DOM est totalement construit et parsÃ© par le
// navigateur avant que je n'exÃ©cute le moindre querySelector ou addEventListener.
// Si je lanÃ§ais mes scripts avant, je risquerais de cibler des Ã©lÃ©ments qui
// n'existent pas encore et de provoquer des erreurs "null is not an object".
document.addEventListener('DOMContentLoaded', () => {
    // J'initialise d'abord tout ce qui doit fonctionner sur TOUTES les pages,
    // quel que soit le contexte : la navigation et les Ã©lÃ©ments transverses.
    initNavigation();
    initBackgroundAnimation();
    initSearch();
    initAddButton();

    // Toast persistant des rappels de soin dus (Phase C1) : transverse, il tourne
    // sur TOUTES les pages — mais ne fait de réseau que si le footer a rendu
    // l'amorce #rappels-toast-bootstrap (utilisateur authentifié). async : je
    // n'attends pas et le module ne lève jamais (try/catch interne).
    initRappelsToast();
    
    // Ensuite, je ne lance que le module qui correspond Ã  la page actuelle.
    // Pourquoi ce dÃ©coupage ? Pour Ã©viter de charger inutilement le code de la
    // page "Ma Collection" quand je suis sur l'accueil : j'optimise les performances.
    const page = getCurrentPage();
    const initializer = featureInitializers[page];
    if (typeof initializer === 'function') {
        // Un module qui echoue ne doit jamais empecher le reste de l'app (ni
        // l'enregistrement du Service Worker) de demarrer : je cloisonne.
        try {
            initializer();
        } catch (error) {
            console.error('Initialisation de la page « ' + page + ' » impossible :', error);
        }
    }
    
    // Enfin, j'enregistre le Service Worker pour la conformitÃ© PWA.
    // Je le fais en dernier car il met en cache les ressources : je veux d'abord
    // que l'interface soit interactive avant de lancer le travail en arriÃ¨re-plan.
    initPWA();
});

