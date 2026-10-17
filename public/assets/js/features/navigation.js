/**
 * @file features/navigation.js
 * @description Je gere le menu hamburger lateral avec accessibilite clavier et ARIA.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : features/navigation.js — Menu hamburger accessible
// ===========================================================================
// J'ai développé ce module pour gérer le menu latéral (sidebar) qui s'ouvre
// avec le bouton hamburger. Pourquoi un module dédié ? Parce que la navigation
// est transversale à toutes les pages et doit respecter scrupuleusement les
// critères RGAA liés aux attributs ARIA et à la navigation au clavier.

// J'importe mon helper DOM pour chercher les éléments de manière sécurisée.
import { getElement } from '../core/dom.js';

/**
 * J'initialise toute la navigation (hamburger, Escape, clic dehors).
 * @returns {void}
 * @example
 * initNavigation();
 */
export function initNavigation() {
    const sidebar = getElement('#main-sidebar');
    const toggleBtn = getElement('#menu-toggle-btn');

    // Si la page n'a pas de sidebar (ex: page d'erreur), je sors proprement.
    if (!sidebar || !toggleBtn) {
        return;
    }

    /**
     * Je bascule le menu ouvert/ferme et je mets a jour aria-expanded.
     * @returns {void}
     */
    function toggleSidebar() {
        sidebar.classList.toggle('sidebar-open');
        const isOpen = sidebar.classList.contains('sidebar-open');
        // J'annonce l'état aux lecteurs d'écran via aria-expanded sur le bouton :
        // true = menu déployé, false = menu replié. C'est exigé par WAI-ARIA.
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        // Je synchronise aussi aria-hidden sur la sidebar elle-même pour que les
        // lecteurs d'écran sachent s'ils doivent l'ignorer ou non.
        sidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }

    // J'écoute le clic sur le hamburger : c'est l'interaction principale à la souris.
    toggleBtn.addEventListener('click', toggleSidebar);

    // J'écoute aussi les touches Espace et Entrée sur le bouton.
    // Pourquoi ? Parce qu'un bouton doit être activable au clavier, pas seulement
    // à la souris. J'empêche le scroll avec preventDefault() sur Espace.
    toggleBtn.addEventListener('keydown', function (event) {
        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            toggleSidebar();
        }
    });

    // J'écoute la touche Échap au niveau document. Pourquoi global ?
    // Pour permettre une fermeture rapide sans devoir retabuler jusqu'au bouton.
    // Je restitue le focus sur le bouton pour que l'utilisateur ne perde pas sa position.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('sidebar-open')) {
            sidebar.classList.remove('sidebar-open');
            toggleBtn.setAttribute('aria-expanded', 'false');
            sidebar.setAttribute('aria-hidden', 'true');
            toggleBtn.focus();
        }
    });

    // Je ferme le menu si on clique en dehors. Pourquoi ? Pour l'ergonomie
    // mobile : on s'attend à ce que le menu se referme quand on touche l'overlay.
    document.addEventListener('click', function (event) {
        if (sidebar.classList.contains('sidebar-open')) {
            const clickInsideSidebar = sidebar.contains(event.target);
            const clickOnButton = toggleBtn.contains(event.target);

            if (!clickInsideSidebar && !clickOnButton) {
                sidebar.classList.remove('sidebar-open');
                toggleBtn.setAttribute('aria-expanded', 'false');
                sidebar.setAttribute('aria-hidden', 'true');
            }
        }
    });
}
