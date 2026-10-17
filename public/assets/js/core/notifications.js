/**
 * @file core/notifications.js
 * @description Je fais apparaitre des toasts (petites notifications) accessibles pour dire si une action a reussi ou echoue.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/notifications.js — Système de toasts accessibles
// ===========================================================================
// J'ai conçu ce module pour afficher des notifications non bloquantes (toasts)
// en haut à droite. Pourquoi des toasts ? Pour informer l'utilisateur d'une
// action réussie ou d'une erreur sans l'obliger à fermer une modale. J'ai
// veillé à respecter l'accessibilité : role="alert" et aria-live pour les
// lecteurs d'écran.

// Je définis une durée d'affichage par défaut de 4 secondes. Pourquoi 4s ?
// C'est un compromis : assez long pour être lu, assez court pour ne pas polluer.
const DEFAULT_DURATION = 4000;

// Je prépare une table de correspondance type -> icône Font Awesome.
// Pourquoi centraliser ici ? Pour garantir une cohérence visuelle : succès =
// coche verte, erreur = point d'exclamation rouge, etc.
const TOAST_ICONS = {
    success: 'fa-check-circle',
    error: 'fa-exclamation-circle',
    warning: 'fa-exclamation-triangle',
    info: 'fa-info-circle'
};

/**
 * Je trouve le conteneur de toasts ou je le cree s'il n'existe pas.
 * @returns {Object} L'element conteneur des toasts.
 */
function getOrCreateToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
}

/**
 * J'affiche un toast (message flottant) a l'ecran.
 * @param {string} message - Le texte a afficher.
 * @param {string} [type="info"] - Le type : "success", "error", "warning" ou "info".
 * @param {number} [duration=4000] - La duree en millisecondes (0 = reste affiche).
 * @returns {void}
 * @example
 * showToast("Orchidee ajoutee !", "success");
 */
export function showToast(message, type = 'info', duration = DEFAULT_DURATION) {
    if (!TOAST_ICONS[type]) {
        type = 'info';
    }

    const container = getOrCreateToastContainer();

    const toastElement = document.createElement('div');
    toastElement.className = `toast toast-${type}`;
    // J'ajoute role="alert" et aria-live="assertive" pour que les lecteurs
    // d'écran annoncent immédiatement ce message, même si l'utilisateur est en
    // train de naviguer ailleurs. C'est vital pour les notifications d'erreur.
    toastElement.setAttribute('role', 'alert');
    toastElement.setAttribute('aria-live', 'assertive');

    const iconElement = document.createElement('i');
    iconElement.className = `fa-solid ${TOAST_ICONS[type]} toast-icon`;
    // L'icône est purement décorative, je la cache aux lecteurs d'écran.
    iconElement.setAttribute('aria-hidden', 'true');
    toastElement.appendChild(iconElement);

    const messageElement = document.createElement('span');
    messageElement.className = 'toast-message';
    // J'utilise textContent (et non innerHTML) pour me protéger du XSS :
    // même si le message contenait du HTML malveillant, il serait affiché tel quel.
    messageElement.textContent = message;
    toastElement.appendChild(messageElement);

    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Fermer la notification');
    
    const closeIcon = document.createElement('i');
    closeIcon.className = 'fa-solid fa-times';
    closeIcon.setAttribute('aria-hidden', 'true');
    
    closeBtn.appendChild(closeIcon);
    toastElement.appendChild(closeBtn);

    container.appendChild(toastElement);

    // Je définis la fonction de fermeture avec animation. Pourquoi une fonction
    // interne ? Pour pouvoir l'appeler à la fois au clic sur × et automatiquement
    // après le timeout, sans dupliquer le code.
    const removeToast = () => {
        if (toastElement.classList.contains('toast-leaving')) return;
        
        toastElement.classList.add('toast-leaving');
        
        setTimeout(() => {
            if (toastElement.parentNode) {
                toastElement.parentNode.removeChild(toastElement);
            }
        }, 400); 
    };

    closeBtn.addEventListener('click', removeToast);

    if (duration > 0) {
        setTimeout(removeToast, duration);
    }
}

/**
 * Je montre un toast vert de succes.
 * @param {string} msg - Le message a afficher.
 * @param {number} [dur] - La duree en ms.
 * @returns {void}
 */
export const success = (msg, dur) => showToast(msg, 'success', dur);
/**
 * Je montre un toast rouge d'erreur.
 * @param {string} msg - Le message a afficher.
 * @param {number} [dur] - La duree en ms.
 * @returns {void}
 */
export const error = (msg, dur) => showToast(msg, 'error', dur);
/**
 * Je montre un toast orange d'avertissement.
 * @param {string} msg - Le message a afficher.
 * @param {number} [dur] - La duree en ms.
 * @returns {void}
 */
export const warning = (msg, dur) => showToast(msg, 'warning', dur);
/**
 * Je montre un toast bleu d'information.
 * @param {string} msg - Le message a afficher.
 * @param {number} [dur] - La duree en ms.
 * @returns {void}
 */
export const info = (msg, dur) => showToast(msg, 'info', dur);

// J'expose aussi une API globale window.AppToast pour les anciens scripts.
window.AppToast = {
    show: showToast,
    success,
    error,
    warning,
    info
};
