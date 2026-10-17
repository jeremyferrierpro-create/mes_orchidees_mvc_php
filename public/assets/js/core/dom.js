/**
 * @file core/dom.js
 * @description Je simplifie le DOM : je cherche des elements, j'en cree et je remplace les enfants proprement.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/dom.js â€” Ma boÃ®te Ã  outils DOM sÃ©curisÃ©e
// ===========================================================================
// J'ai crÃ©Ã© ce petit module utilitaire pour simplifier et sÃ©curiser toutes mes
// manipulations du DOM (Document Object Model). Pourquoi des wrappers ?
// Parce qu'Ã©crire document.querySelector partout est rÃ©pÃ©titif et source
// d'erreurs. En centralisant ici, je garantis une Ã©criture propre et je
// pourrai faire Ã©voluer la logique Ã  un seul endroit.

/**
 * Je cherche un seul element dans la page.
 * @param {string} selector - Le selecteur CSS (ex: "#id", ".classe").
 * @param {Object} [root=document] - L'element parent ou chercher (par defaut tout le document).
 * @returns {Object|null} L'element trouve ou null.
 * @example
 * const btn = getElement("#menu-toggle-btn");
 */
export function getElement(selector, root = document) {
    return root.querySelector(selector);
}

/**
 * Je cherche plusieurs elements et je les renvoie en vrai tableau.
 * @param {string} selector - Le selecteur CSS (ex: ".card").
 * @param {Object} [root=document] - L'element parent ou chercher.
 * @returns {Array} Un tableau d'elements (meme si vide).
 * @example
 * const cartes = getAllElements(".orchid-card");
 */
export function getAllElements(selector, root = document) {
    return Array.from(root.querySelectorAll(selector));
}

/**
 * Je cree un element HTML proprement sans risque XSS.
 * @param {string} tagName - Le nom de la balise (ex: "div", "button").
 * @param {Object} [options={}] - Les options de creation.
 * @param {string} [options.className=""] - Les classes CSS a ajouter.
 * @param {string} [options.text=""] - Le texte brut securise (textContent).
 * @param {string} [options.php=""] - Le HTML interne si besoin d'icone.
 * @param {Object} [options.attributes={}] - Les attributs supplementaires (src, alt...).
 * @returns {Object} L'element cree pret a etre ajoute au DOM.
 * @example
 * const titre = createElement("h3", { text: "Mon titre", className: "title" });
 */
export function createElement(tagName, {
    className = '',
    text = '',
    html = '',
    attributes = {}
} = {}) {
    const element = document.createElement(tagName);

    if (className) element.className = className;
    // J'utilise textContent pour le texte brut : c'est sÃ©curisÃ© contre le XSS,
    // car le navigateur n'interprÃ©tera jamais le contenu comme du HTML.
    if (text) element.textContent = text;
    // J'utilise innerHTML uniquement quand je dois insÃ©rer une icÃ´ne dÃ©jÃ 
    // validÃ©e par mes soins. C'est un cas d'usage maÃ®trisÃ© et volontaire.
    if (html) element.innerHTML = html;

    // Je pose tous les attributs supplÃ©mentaires (src, alt, aria-*, data-*) en
    // bouclant sur l'objet attributes. C'est plus propre qu'une longue liste de setAttribute.
    for (const [name, value] of Object.entries(attributes)) {
        element.setAttribute(name, value);
    }

    return element;
}

/**
 * Je vide un conteneur et j'y mets les nouveaux enfants.
 * @param {Object} container - L'element parent a vider.
 * @param {...Object} nodes - Les nouveaux enfants a ajouter.
 * @returns {void}
 * @example
 * replaceChildren(grid, carte1, carte2);
 */
export function replaceChildren(container, ...nodes) {
    if (container.replaceChildren) {
        container.replaceChildren(...nodes);
    } else {
        container.innerHTML = '';
        container.append(...nodes);
    }
}

