/**
 * @file features/conseils.js
 * @description Je montre les conseils, je filtre avec la recherche et j'ouvre la modale de detail.
 * @author Jeremy Ferrier
 * @version 1.0
 */

import { replaceChildren } from '../core/dom.js';
import { getAllConseils, getConseilById, searchConseils } from '../services/conseil-service.js';
import * as modalManager from '../core/modal.js';

// ===========================================================================
// FICHIER : features/conseils.js — Page Conseils (cartes + recherche + modale)
// ===========================================================================
// J'ai structuré ce module autour de trois responsabilités : afficher les 6
// grosses cartes catégories, filtrer via la barre de recherche, et ouvrir une
// modale de détail accessible. Pourquoi un seul fichier ? Parce que ces trois
// éléments partagent le même état (les conseils) et les mêmes helpers.

/**
 * J'initialise la page Conseils : cartes, recherche et modale.
 * @returns {void}
 * @example
 * initConseils();
 */
export function initConseils() {
    // Je récupère tous les éléments statiques de la page. Pourquoi dès le début ?
    // Pour éviter de refaire des querySelector à chaque interaction et pour
    // sortir proprement si je ne suis pas sur la page conseils.
    const searchForm = document.getElementById('conseil-search-form');
    const searchInput = document.getElementById('conseil-search-input');
    const searchHelp = document.getElementById('conseil-search-help');
    const resultsContainer = document.getElementById('advice-results');
    const conseilCards = document.querySelectorAll('.conseil-card');

    const modal = document.getElementById('conseil-modal');
    const closeButton = document.getElementById('conseil-modal-close');
    const modalImage = document.getElementById('conseil-modal-img');
    const modalTitle = document.getElementById('conseil-modal-title');
    const modalMeta = document.getElementById('conseil-modal-meta');
    const modalText = document.getElementById('conseil-modal-text');

    // Je récupère les 6 cases de la grille "careCards" (température, arrosage...).
    // Pourquoi un objet ? Pour pouvoir boucler dessus avec for...in plus tard.
    const careElements = {
        temperature: document.getElementById('care-temperature'),
        arrosage: document.getElementById('care-arrosage'),
        hygrometrie: document.getElementById('care-hygrometrie'),
        rempotage: document.getElementById('care-rempotage'),
        engrais: document.getElementById('care-engrais'),
        substrats: document.getElementById('care-substrats')
    };

    // Garde-fou : si les éléments clés de la modale manquent, je ne suis pas sur
    // la bonne page, j'arrête tout pour éviter des erreurs null.
    if (!modal || !modalTitle || !modalMeta || !modalText) {
        return;
    }

    /**
     * J'ecris du texte securise dans un element (anti-XSS).
     * @param {Object|null} element - L'element ou ecrire.
     * @param {string} text - Le texte brut.
     * @returns {void}
     */
    function setText(element, text) {
        if (element) {
            element.textContent = text;
        }
    }

    /**
     * J'ouvre la modale avec les details d'un conseil.
     * @param {Object} conseil - L'objet conseil a afficher.
     * @param {Object|null} [triggerElement=null] - Le bouton/carte qui a ouvert (pour rendre le focus).
     * @returns {void}
     */
    function openConseilModal(conseil, triggerElement = null) {
        if (!conseil) {
            return;
        }

        setText(modalTitle, conseil.name || 'Conseil de culture');
        // J'adapte la ligne de métadonnée selon que c'est une fiche espèce ou
        // une rubrique catégorie : c'est plus parlant pour l'utilisateur.
        setText(
            modalMeta,
            conseil.type === 'species'
                ? `Fiche de culture — ${conseil.category || 'Orchidée'}`
                : 'Rubrique de conseils'
        );
        setText(modalText, conseil.content || conseil.description || 'Aucun conseil disponible.');

        // Je gère l'image avec précaution : si elle existe je l'affiche, sinon
        // je la cache avec hidden pour ne pas avoir une icône d'image cassée.
        if (modalImage) {
            if (conseil.img) {
                modalImage.src = conseil.img;
                modalImage.alt = `Illustration de ${conseil.name}`;
                modalImage.hidden = false;
            } else {
                modalImage.removeAttribute('src');
                modalImage.alt = '';
                modalImage.hidden = true;
            }
        }

        // Je remplis les 6 cases du bas avec les données careCards ou "-" par défaut.
        const careCards = conseil.careCards || {};
        for (const key in careElements) {
            setText(careElements[key], careCards[key] || '-');
        }

        // J'ouvre via le gestionnaire centralisé en lui donnant l'élément déclencheur
        // pour qu'il puisse restituer le focus à la fermeture (RGAA).
        modalManager.open(modal, triggerElement || document.activeElement);
    }

    /**
     * Je ferme la modale de conseil.
     * @returns {void}
     */
    function closeConseilModal() {
        modalManager.close(modal);
    }

    /**
     * Je cree une carte cliquable pour un resultat de recherche.
     * @param {Object} conseil - L'objet conseil a afficher.
     * @returns {Object} L'element article pret a inserer.
     */
    function createResultCard(conseil) {
        const card = document.createElement('article');
        card.className = 'advice-result-card';
        card.tabIndex = 0;
        // J'ajoute role="button" et aria-controls pour que les lecteurs d'écran
        // comprennent que cette carte ouvre une modale, même si c'est un <article>.
        card.setAttribute('role', 'button');
        card.setAttribute('aria-controls', 'conseil-modal');
        card.setAttribute('aria-label', `Ouvrir la fiche ${conseil.name}`);

        const title = document.createElement('h3');
        title.textContent = conseil.name;
        card.appendChild(title);

        const meta = document.createElement('p');
        meta.className = 'advice-result-meta';
        meta.textContent = conseil.type === 'species'
            ? `Fiche de culture — ${conseil.category || 'Orchidée'}`
            : 'Rubrique de conseils';
        card.appendChild(meta);

        if (conseil.img) {
            const image = document.createElement('img');
            image.src = conseil.img;
            image.alt = '';
            image.className = 'advice-result-thumb';
            image.loading = 'lazy';
            card.appendChild(image);
        }

        card.addEventListener('click', () => openConseilModal(conseil, card));
        // J'ajoute aussi l'activation au clavier (Entrée / Espace) pour les
        // utilisateurs qui naviguent sans souris. Sans cela, la carte serait
        // inaccessible au clavier.
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openConseilModal(conseil, card);
            }
        });

        return card;
    }

    /**
     * J'affiche la liste des resultats sous la barre de recherche.
     * @param {Array} results - Le tableau des conseils trouves.
     * @param {string} query - Le mot cherche par l'utilisateur.
     * @returns {void}
     */
    function renderResults(results, query) {
        if (!resultsContainer) {
            return;
        }

        replaceChildren(resultsContainer);

        if (results.length === 0) {
            const message = document.createElement('p');
            message.className = 'advice-no-results';
            // J'utilise textContent pour éviter le XSS même dans ce message d'erreur.
            message.textContent = `Aucune fiche ne correspond à « ${query} ».`;
            resultsContainer.appendChild(message);
            if (searchHelp) searchHelp.textContent = `0 résultat pour « ${query} »`;
            return;
        }

        const fragment = document.createDocumentFragment();
        for (const conseil of results) {
            fragment.appendChild(createResultCard(conseil));
        }
        resultsContainer.appendChild(fragment);
        if (searchHelp) searchHelp.textContent = `${results.length} fiche(s) trouvée(s) pour « ${query} »`;
    }

    /**
     * Je filtre les conseils selon la saisie (minimum 3 lettres) puis j'affiche.
     * @returns {Array} Le tableau des resultats trouves.
     */
    function filterAndRender() {
        if (!searchInput || !resultsContainer) {
            return [];
        }

        const query = searchInput.value.trim();

        if (query.length === 0) {
            replaceChildren(resultsContainer);
            if (searchHelp) searchHelp.textContent = '';
            return [];
        }

        if (query.length < 3) {
            replaceChildren(resultsContainer);
            const message = document.createElement('p');
            message.className = 'advice-search-help';
            message.textContent = 'Veuillez saisir au moins 3 caractères pour lancer la recherche.';
            resultsContainer.appendChild(message);
            if (searchHelp) searchHelp.textContent = 'Tape au moins 3 lettres...';
            return [];
        }

        const results = searchConseils(query);
        renderResults(results, query);
        return results;
    }

    /**
     * J'ouvre la fiche liee a une des grosses cartes categorie.
     * @param {Object} card - L'element HTML de la carte cliquee.
     * @returns {void}
     */
    function openCardAdvice(card) {
        const conseilId = card.dataset.conseilId;
        let conseil = conseilId ? getConseilById(conseilId) : null;
        if (!conseil) {
            conseil = getAllConseils().find((item) => item.type === 'category' && item.name === card.dataset.category);
        }

        if (conseil) {
            openConseilModal(conseil, card);
        }
    }

    // Je rends les 6 grosses cartes cliquables à la souris ET au clavier.
    for (const card of conseilCards) {
        card.addEventListener('click', () => openCardAdvice(card));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openCardAdvice(card);
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterAndRender);
    }

    // J'écoute aussi la soumission du formulaire pour gérer la touche Entrée.
    // Pourquoi preventDefault ici aussi ? Pour éviter le rechargement de page.
    if (searchForm) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            filterAndRender();
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', closeConseilModal);
    }

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeConseilModal();
        }
    });

    // Si l'image de la modale ne charge pas (404), je la cache pour garder
    // une interface propre sans icône d'image cassée.
    if (modalImage) {
        modalImage.addEventListener('error', () => {
            modalImage.hidden = true;
            modalImage.alt = '';
        });
    }
}
