/**
 * @file core/modal.js
 * @description Je gere les modales de facon accessible : j'ouvre, je ferme, je bloque le scroll et je piege le focus.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/modal.js — Gestionnaire centralisé de fenêtres modales
// ===========================================================================
// J'ai centralisé toute la logique des modales ici pour garantir une
// accessibilité irréprochable (RGAA / WAI-ARIA) et éviter de dupliquer le même
// code dans chaque feature. Pourquoi un module dédié ? Parce que l'ouverture
// d'une modale implique 4 responsabilités critiques : l'ARIA, le scroll, le
// focus et le clavier, que je ne veux gérer qu'une seule fois.

// J'importe ma fonction de piégeage de focus. Elle m'est indispensable pour
// respecter le critère RGAA qui impose de ne pas laisser le focus s'échapper
// de la modale.
import { trapFocus } from './focus.js';

// Je garde en mémoire l'état global de la modale : une seule modale peut être
// ouverte à la fois, c'est un choix d'UX pour éviter la confusion.
let activeModal = null;
let lastFocusedElement = null;
let previousBodyOverflow = '';

/**
 * Je gere les touches Escape et Tab quand une modale est ouverte.
 * @param {Object} event - L'evenement clavier (keydown).
 * @returns {void}
 */
function handleKeyDown(event) {
    if (!activeModal) {
        return;
    }

    // J'écoute la touche Échap (Escape). Pourquoi ? Pour garantir une sortie
    // rapide sans souris, exigence RGAA/WCAG 2.1 : l'utilisateur au clavier doit
    // pouvoir fermer la modale en une seule frappe, sans avoir à tabuler jusqu'au ×.
    if (event.key === 'Escape') {
        close(activeModal);
    }

    // J'intercepte la touche Tab et je délègue à mon Focus Trap.
    // Sans cela, l'utilisateur pourrait tabuler vers l'arrière-plan et se perdre.
    if (event.key === 'Tab') {
        trapFocus(activeModal, event);
    }
}

/**
 * J'ouvre une modale et je mets en place l'accessibilite et le blocage du scroll.
 * @param {Object} modalElement - L'element HTML de la modale a ouvrir.
 * @param {Object|null} [triggerElement=null] - Le bouton qui a declenche l'ouverture (pour rendre le focus apres).
 * @returns {void}
 * @example
 * open(document.getElementById("orchid-modal"), monBouton);
 */
export function open(modalElement, triggerElement = null) {
    if (!modalElement) {
        return;
    }

    // Si une autre modale était déjà ouverte, je la ferme proprement avant.
    // Pourquoi ? Pour éviter deux fonds sombres superposés et deux Focus Trap actifs.
    if (activeModal && activeModal !== modalElement) {
        close(activeModal);
    }

    activeModal = modalElement;
    // Je sauvegarde l'élément qui avait le focus avant l'ouverture.
    // Pourquoi ? Pour pouvoir restituer automatiquement le curseur clavier sur le
    // bouton d'origine à la fermeture, comme l'exige le RGAA 12.9. Sans cela,
    // l'utilisateur non-voyant perdrait sa position dans la page.
    lastFocusedElement = triggerElement || document.activeElement;
    previousBodyOverflow = document.body.style.overflow;

    modalElement.classList.add('active');
    // Je bascule aria-hidden de "true" à "false" pour avertir immédiatement les
    // technologies d'assistance (lecteurs d'écran NVDA, JAWS, VoiceOver) que ce
    // contenu devient visible. J'ai aussi prévu en HTML les attributs
    // role="dialog" et aria-modal="true" sur la modale pour indiquer que c'est
    // une fenêtre de dialogue qui bloque l'interaction avec l'arrière-plan.
    modalElement.setAttribute('aria-hidden', 'false');
    // Je bloque le défilement de l'arrière-plan avec overflow = 'hidden'.
    // Pourquoi ? Pour éviter le "scroll bleed" : quand on scrolle dans une modale
    // longue, on ne veut pas que la page derrière bouge aussi, ce qui désoriente.
    document.body.style.overflow = 'hidden';

    // Je m'assure de ne pas accumuler plusieurs écouteurs keydown. J'enlève
    // l'ancien avant d'ajouter le nouveau : c'est une bonne pratique pour éviter
    // les fuites mémoire et les doubles fermetures.
    document.removeEventListener('keydown', handleKeyDown);
    document.addEventListener('keydown', handleKeyDown);

    // Après un court délai de 50ms (le temps que la transition CSS se lance),
    // je place le focus sur le bouton de fermeture. Pourquoi ? Pour que
    // l'utilisateur au clavier soit immédiatement à l'intérieur de la modale et
    // n'ait pas à tabuler depuis le fond de page.
    window.setTimeout(() => {
        const closeButton = modalElement.querySelector('.modal-close, .close-modal');

        if (closeButton) {
            closeButton.focus();
        } else if (typeof modalElement.focus === 'function') {
            modalElement.focus();
        }
    }, 50);
}

/**
 * Je ferme la modale et je restaure le scroll et le focus d'avant.
 * @param {Object} modalElement - L'element de la modale a fermer.
 * @returns {void}
 * @example
 * close(document.getElementById("orchid-modal"));
 */
export function close(modalElement) {
    if (!modalElement || modalElement !== activeModal) {
        return;
    }

    modalElement.classList.remove('active');
    // Je repasse aria-hidden à "true" pour que les lecteurs d'écran ignorent à
    // nouveau ce contenu caché. C'est le pendant indispensable de l'ouverture.
    modalElement.setAttribute('aria-hidden', 'true');
    // Je restaure la valeur d'overflow que j'avais sauvegardée pour rendre
    // le scroll à la page. Sans cela, la page resterait bloquée après fermeture.
    document.body.style.overflow = previousBodyOverflow;

    document.removeEventListener('keydown', handleKeyDown);
    activeModal = null;

    // Je restitue le focus sur l'élément qui avait ouvert la modale.
    // C'est crucial pour la continuité de navigation au clavier : l'utilisateur
    // retrouve exactement sa position, il n'est pas renvoyé en haut de page.
    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
    }

    lastFocusedElement = null;
}

// J'expose aussi une API globale window.ModalManager pour la compatibilité avec
// d'anciens fichiers qui l'appelaient encore directement. C'est une transition douce.
window.ModalManager = { open, close };
