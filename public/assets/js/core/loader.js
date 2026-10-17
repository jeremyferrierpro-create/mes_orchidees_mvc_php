/**
 * @file core/loader.js
 * @description Je montre et cache les loaders (voile plein ecran et petit spinner) pour dire a l'utilisateur que ca charge.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/loader.js — Gestion des états de chargement (UX + Accessibilité)
// ===========================================================================
// J'ai créé ce module pour gérer les deux types de chargement que j'utilise :
// le voile plein écran (global) et le petit spinner inline. Pourquoi ? Pour
// donner un feedback visuel immédiat à l'utilisateur et respecter l'accessibilité
// (aria-live, role="dialog") pendant que les données se chargent.

// J'importe mon helper de manipulation DOM pour vider proprement les conteneurs.
import { replaceChildren } from './dom.js';

// Je garde en mémoire l'état des loaders actifs pour éviter d'en ouvrir deux
// superposés. C'est une précaution contre les doubles appels intempestifs.
const activeLoaders = {
    overlay: null,
    inline: new Map()
};

/**
 * Je fabrique le HTML du spinner avec son texte accessible.
 * @param {string} [text=""] - Le texte a afficher sous le spinner.
 * @param {boolean} [isSmall=false] - Vrai pour un petit spinner.
 * @returns {Object} L'element div contenant le spinner.
 */
function createSpinnerDOM(text = '', isSmall = false) {
    const group = document.createElement('div');
    group.className = 'loader-group';

    const spinner = document.createElement('div');
    spinner.className = 'loader-spinner';
    if (isSmall) {
        spinner.classList.add('loader-spinner--sm');
    }
    group.appendChild(spinner);

    if (text) {
        const textEl = document.createElement('div');
        textEl.className = 'loader-text';
        // J'ajoute aria-live="polite" pour que les lecteurs d'écran annoncent
        // le changement de texte sans interrompre brutalement l'utilisateur.
        textEl.setAttribute('aria-live', 'polite');
        textEl.textContent = text;
        group.appendChild(textEl);
    }

    return group;
}

/**
 * J'affiche le voile global qui bloque toute interaction.
 * @param {string} [text="Chargement..."] - Le texte a afficher dans le voile.
 * @returns {void}
 * @example
 * showGlobalLoader("Patientez...");
 */
export function showGlobalLoader(text = 'Chargement...') {
    // Si un loader global est déjà visible, je mets simplement à jour son texte
    // au lieu d'en créer un second. C'est plus propre.
    if (activeLoaders.overlay) {
        const textEl = activeLoaders.overlay.querySelector('.loader-text');
        if (textEl && text) {
            textEl.textContent = text;
        }
        return;
    }

    const overlay = document.createElement('div');
    overlay.className = 'loader-overlay';
    overlay.id = 'global-loader';
    // J'annonce ce voile comme une boîte de dialogue modale pour les lecteurs
    // d'écran : ils comprendront que le reste de la page est temporairement
    // inaccessible, comme pour une modale.
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Veuillez patienter');

    const spinnerDom = createSpinnerDOM(text);
    overlay.appendChild(spinnerDom);

    document.body.appendChild(overlay);

    void overlay.offsetWidth;
    
    overlay.classList.add('loader-active');
    
    // Je bloque le scroll de l'arrière-plan exactement comme pour les modales,
    // pour la même raison d'UX : on ne doit pas scroller derrière un voile.
    document.body.style.overflow = 'hidden';

    activeLoaders.overlay = overlay;
}

/**
 * Je cache le voile global avec une petite animation.
 * @returns {void}
 */
export function hideGlobalLoader() {
    const overlay = activeLoaders.overlay;
    if (!overlay) return;

    overlay.classList.remove('loader-active');
    document.body.style.overflow = '';

    // Je laisse 300ms pour l'animation CSS de disparition avant de retirer le
    // nœud du DOM. Sans ce délai, la transition serait coupée brutalement.
    setTimeout(() => {
        if (overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
    }, 300);

    activeLoaders.overlay = null;
}

/**
 * J'affiche un petit spinner dans une zone precise de la page.
 * @param {string|Object} target - Le selecteur CSS ou l'element conteneur.
 * @param {string} [text=""] - Le texte optionnel.
 * @param {boolean} [clearTarget=true] - Vrai pour vider le conteneur avant.
 * @returns {void}
 * @example
 * showInlineLoader("#collection-grid", "Chargement...");
 */
export function showInlineLoader(target, text = '', clearTarget = true) {
    const container = typeof target === 'string' ? document.querySelector(target) : target;
    if (!container) return;

    if (activeLoaders.inline.has(container)) return;

    if (clearTarget) {
        replaceChildren(container);
    }

    const loaderWrapper = document.createElement('div');
    loaderWrapper.className = 'loader-inline';
    loaderWrapper.appendChild(createSpinnerDOM(text));

    container.appendChild(loaderWrapper);
    activeLoaders.inline.set(container, loaderWrapper);
}

/**
 * Je retire le petit spinner d'une zone.
 * @param {string|Object} target - Le selecteur CSS ou l'element conteneur.
 * @returns {void}
 */
export function hideInlineLoader(target) {
    const container = typeof target === 'string' ? document.querySelector(target) : target;
    if (!container) return;

    const loaderWrapper = activeLoaders.inline.get(container);
    if (loaderWrapper && loaderWrapper.parentNode === container) {
        container.removeChild(loaderWrapper);
        activeLoaders.inline.delete(container);
    }
}

// J'expose une API globale pour la compatibilité avec d'anciens scripts qui
// utilisaient window.AppLoader. C'est une transition douce avant migration complète.
window.AppLoader = {
    show: showGlobalLoader,
    hide: hideGlobalLoader,
    showInline: showInlineLoader,
    hideInline: hideInlineLoader
};
