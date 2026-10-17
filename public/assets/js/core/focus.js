/**
 * @file core/focus.js
 * @description Je piege le focus clavier dans la modale pour que l'utilisateur ne s'echappe pas avec Tab (accessibilite RGAA).
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/focus.js — Mécanisme de Focus Trap (piégeage de focus)
// ===========================================================================
// J'ai créé ce module pour répondre à une exigence d'accessibilité fondamentale
// du RGAA (critères 7.1 et 12.9) : quand une modale est ouverte, l'utilisateur
// qui navigue au clavier ou avec un lecteur d'écran ne doit JAMAIS pouvoir
// tabuler vers l'arrière-plan. Sans ce piégeage, il se perdrait derrière la
// modale, ce qui est très désorientant pour une personne non-voyante.

// J'exporte une seule fonction trapFocus qui sera appelée à chaque pression de Tab
// depuis le gestionnaire modal.js. Pourquoi une fonction séparée ? Pour isoler
// cette logique complexe et la tester indépendamment.
/**
 * Je piege le focus dans la modale : Tab reboucle du dernier au premier element.
 * @param {Object} modal - L'element de la modale ouverte.
 * @param {Object} event - L'evenement clavier (keydown Tab).
 * @returns {void}
 * @example
 * trapFocus(maModale, event);
 */
export function trapFocus(modal, event) {
    // Je définis le sélecteur CSS qui cible TOUS les éléments focalisables par
    // le clavier : liens, boutons non désactivés, champs de formulaire, éléments
    // avec tabindex="0" ou contenteditable. C'est la liste officielle WAI-ARIA.
    const focusableElementsString = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex="0"], [contenteditable]';
    let focusableElements = modal.querySelectorAll(focusableElementsString);
    
    // Je filtre pour ne garder que les éléments réellement visibles à l'écran.
    // Pourquoi ce filtre ? Parce que certains éléments peuvent être présents dans
    // le DOM mais cachés via display:none. Ils ne doivent pas être comptés.
    // J'autorise aussi document.activeElement même s'il est temporairement invisible.
    focusableElements = Array.from(focusableElements).filter(el => {
        return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
    });

    // Si la modale ne contient aucun élément focalisable, je n'ai rien à piéger.
    if (focusableElements.length === 0) return;

    // J'identifie le premier et le dernier élément focalisable de la modale.
    // Ce sont mes deux bornes : c'est entre elles que le focus doit reboucler.
    // Pourquoi c'est important ? Parce que le piège consiste à faire un cercle.
    const firstTabStop = focusableElements[0];
    const lastTabStop = focusableElements[focusableElements.length - 1];

    // Cas 1 : l'utilisateur fait Shift + Tab (navigation à reculons) et il est
    // déjà sur le PREMIER élément. S'il continue, il sortirait de la modale.
    // J'intercepte donc l'événement, j'annule son comportement par défaut avec
    // preventDefault(), et je téléporte le focus sur le DERNIER élément. Ainsi,
    // la navigation reboucle en arrière.
    if (event.shiftKey) {
        if (document.activeElement === firstTabStop) {
            event.preventDefault();
            lastTabStop.focus();
        }
    } else {
        // Cas 2 : l'utilisateur fait Tab (navigation en avant) et il est sur le
        // DERNIER élément. Même logique : j'empêche la sortie et je renvoie au
        // PREMIER élément. La boucle est bouclée, l'utilisateur reste piégé
        // de manière bienveillante à l'intérieur de la modale jusqu'à ce qu'il
        // appuie sur Échap.
        if (document.activeElement === lastTabStop) {
            event.preventDefault();
            firstTabStop.focus();
        }
    }
}
