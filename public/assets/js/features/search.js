/**
 * @file features/search.js
 * @description Je fais la recherche d'orchidees, j'affiche la grille et j'ouvre la modale de detail.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// Moteur de recherche et affichage des fiches
// ===========================================================================

import { getElement, createElement, replaceChildren } from '../core/dom.js';
import { getAllOrchids, searchOrchids, getOrchidById } from '../services/orchid-service.js';
import * as modalManager from '../core/modal.js';
import * as notifications from '../core/notifications.js';

/**
 * Dèfinition du type d'objet Orchid (reprèsente une orchidèe dans la base de donnèes).
 * @typedef {Object} Orchid
 * @property {string} id - L'identifiant unique de l'orchidèe (ex: "acacalis_cyanea").
 * @property {string} name - Le nom scientifique/botanique (ex: "ACACALIS CYANEA").
 * @property {string} vernacular - Le nom commun usuel (ex: "AGANISIA BLEUE").
 * @property {string} order - L'ordre botanique (ex: "Asparagales").
 * @property {string} species - L'espÃ¨ce exacte (ex: "Cyanea").
 * @property {string} genre - Le genre botanique (ex: "Acacalis").
 * @property {string} family - La famille botanique (ex: "Orchidaceae").
 * @property {string} subfamily - La sous-famille (ex: "Epidendroideae").
 * @property {string} tribu - La tribu taxonomique (ex: "Cymbidieae").
 * @property {string} subtribu - La sous-tribu (ex: "Zygopetalinae").
 * @property {string} behavior - Le type de comportement vègètal (ex: "épiphyte", "Terrestre").
 * @property {string} discovered - Nom du botaniste dècouvreur et date (ex: "John Lindley (1839)").
 * @property {string} origin - La règion gèographique d'origine (ex: "Amèrique du Sud").
 * @property {string} img - Le chemin relatif vers la photo (ex: "./assets/images/orchids/...").
 * @property {string} shortDesc - Rèsumè court pour les cartes de la grille.
 * @property {string} longDesc - Description dètaillèe pour la modale.
 */

/** @type {HTMLElement|null} Conteneur de la grille qui reÃ§oit les cartes */
let gridContainer;

/** @type {HTMLInputElement|null} Champ texte de saisie de recherche */
let searchInput;

/** @type {HTMLFormElement|null} Formulaire contenant la barre de recherche */
let searchForm;

/** @type {HTMLElement|null} élèment global de la fenêtre modale */
let modal;

/** @type {HTMLElement|null} Bouton en forme de croix servant Ã  fermer la modale */
let closeModalBtn;

/** @type {HTMLElement|null} Modale de resultats (landing page) */
let searchResultsModal;

/** @type {HTMLElement|null} Grille de resultats dans la modale */
let searchResultsGrid;

/** @type {HTMLElement|null} Bouton fermeture modale de resultats */
let closeSearchResultsBtn;

/**
 * Je demarre la recherche : j'affiche le catalogue et j'ecoute la saisie.
 * @returns {void}
 */
export async function initSearch() {
    gridContainer = getElement('#orchid-grid-container');
    searchInput = getElement('#search-input');
    searchForm = getElement('#encyclopedia-search-form') || getElement('#landing-search-form');
    modal = getElement('#orchid-modal');
    closeModalBtn = modal ? getElement('.modal-close', modal) : null;
    searchResultsModal = getElement('#search-results-modal');
    searchResultsGrid = getElement('#search-modal-grid');
    closeSearchResultsBtn = searchResultsModal ? getElement('.modal-close', searchResultsModal) : null;

    if (gridContainer) {
        const urlParams = new URLSearchParams(window.location.search);
        const hasSearch = urlParams.get('search');
        const onEncyclopedie = window.location.pathname.toLowerCase().includes('encyclopedie');
        if (onEncyclopedie && !hasSearch) {
            renderOrchidGrid(await getAllOrchids());
        }
    }

    setupRealtimeSearch();
    setupEvents();
}

/**
 * Je vide la grille et j'y mets les cartes d'orchidees (ou un message si vide).
 * @param {Array} list - Le tableau d'orchidees a afficher.
 * @param {HTMLElement} targetContainer - Le conteneur cible (par defaut gridContainer).
 * @returns {void}
 */
function renderOrchidGrid(list, targetContainer = gridContainer) {
    if (!targetContainer) return;

    replaceChildren(targetContainer);

    if (list.length === 0) {
        const noResult = createElement('p', {
            className: 'no-results',
            text: 'Aucune orchidèe ne correspond Ã  votre recherche.'
        });
        noResult.style.gridColumn = '1 / -1';
        noResult.style.textAlign = 'center';
        targetContainer.appendChild(noResult);
        return;
    }

    const fragment = document.createDocumentFragment();
    for (const orchid of list) {
        fragment.appendChild(createOrchidCard(orchid));
    }
    targetContainer.appendChild(fragment);
}

/**
 * Je cree une carte HTML pour une orchidee.
 * @param {Object} orchid - L'objet orchidee avec name, vernacular, img.
 * @returns {Object} L'element article de la carte.
 */
function createOrchidCard(orchid) {
    const article = createElement('article', {
        className: 'orchid-card',
        attributes: { 'data-orchid-name': orchid.name }
    });

    const img = createElement('img', {
        className: 'card-img',
        attributes: { src: orchid.img, alt: 'Photographie de ' + orchid.name }
    });
    article.appendChild(img);

    const infoDiv = createElement('div', { className: 'card-info' });

    infoDiv.appendChild(createElement('h3', { text: orchid.name }));
    infoDiv.appendChild(createElement('p', { className: 'vernacular-name', text: orchid.vernacular }));
    infoDiv.appendChild(createElement('p', { className: 'short-desc', text: orchid.shortDesc }));

    const btn = createElement('button', {
        className: 'card-btn',
        text: 'SéLECTIONNER',
        attributes: { type: 'button', 'data-orchid-name': orchid.name }
    });
    infoDiv.appendChild(btn);

    article.appendChild(infoDiv);
    return article;
}

/**
 * Je filtre la grille selon le texte tape (minimum 3 lettres).
 * @param {string} query - Le texte tape par l'utilisateur.
 * @param {HTMLElement} targetContainer - Le conteneur cible.
 * @returns {void}
 */
async function filterOrchids(query, targetContainer = gridContainer) {
    const cleanQuery = query.toLowerCase().trim();

    if (cleanQuery.length < 3) {
        if (targetContainer) replaceChildren(targetContainer);
        return;
    }

    const filtered = await searchOrchids(cleanQuery);
    renderOrchidGrid(filtered, targetContainer);
}

/**
 * J'ecoute la saisie en temps reel et le parametre d'URL ?search=...
 * @returns {void}
 */
function setupRealtimeSearch() {
    if (!searchInput) return;

    searchInput.addEventListener('input', (event) => {
        if (searchForm && searchForm.id === 'landing-search-form') {
            const query = event.target.value.toLowerCase().trim();
            if (query.length >= 3) {
                if (searchResultsModal && !searchResultsModal.classList.contains('modal-open')) {
                    modalManager.open(searchResultsModal);
                }
                filterOrchids(query, searchResultsGrid);
            } else if (searchResultsModal) {
                 modalManager.close(searchResultsModal);
            }
        } else if (gridContainer) {
            filterOrchids(event.target.value, gridContainer);
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    const searchFromUrl = urlParams.get('search');
    if (searchFromUrl) {
        searchInput.value = searchFromUrl;
        filterOrchids(searchFromUrl);
    }
}

/**
 * Je cherche une orchidee par son nom exact et j'ouvre sa modale.
 * @param {string} orchidName - Le nom complet de l'orchidee (ex: "ACACALIS CYANEA").
 * @returns {void}
 */
async function selectOrchidByName(orchidName) {
    const matchedOrchid = (await getAllOrchids()).find(orchid => orchid.name.toLowerCase() === orchidName.toLowerCase());
    if (matchedOrchid) {
        injectModalData(matchedOrchid);
        openModal();
    }
}

/**
 * Je remplis la modale avec les donnees d'une orchidee (anti-XSS avec textContent).
 * @param {Object} orchid - L'objet orchidee a afficher.
 * @returns {void}
 */
function injectModalData(orchid) {
    /**
     * J'insere du texte securise dans un element par son id.
     * @param {string} id - L'id HTML sans #.
     * @param {string} text - Le texte brut.
     * @returns {void}
     */
    const setText = (id, text) => {
        const el = getElement('#' + id);
        if (el) el.textContent = text;
    };

    setText('modal-orchid-title', orchid.name);
    setText('modal-orchid-scientific', orchid.name);
    setText('modal-orchid-vernacular', orchid.vernacular);
    setText('modal-orchid-short', orchid.shortDesc);
    setText('modal-orchid-long', orchid.longDesc);
    setText('spec-ordre', orchid.order);
    setText('spec-espece', orchid.species);
    setText('spec-genre', orchid.genre);
    setText('spec-famille', orchid.family);
    setText('spec-subfamily', orchid.subfamily);
    setText('spec-tribu', orchid.tribu);
    setText('spec-subtribu', orchid.subtribu);
    setText('spec-behavior', orchid.behavior);
    setText('spec-discovered', orchid.discovered);
    setText('spec-origin', orchid.origin);

    const modalImg = getElement('#modal-orchid-img');
    if (modalImg) {
        modalImg.src = orchid.img;
        modalImg.alt = 'Photographie de ' + orchid.name;
    }
}

/**
 * J'ouvre la modale via le gestionnaire central.
 * @returns {void}
 */
function openModal() {
    if (!modal) return;
    modal.dispatchEvent(new CustomEvent('orchidModalOpened'));
    modalManager.open(modal);
}

/**
 * Je ferme la modale.
 * @returns {void}
 */
function closeModal() {
    modalManager.close(modal);
}

/**
 * J'attache tous les clics et soumissions du module recherche.
 * @returns {void}
 */
function setupEvents() {
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
    }

    if (gridContainer) {
        gridContainer.addEventListener('click', (event) => {
            const button = event.target.closest('[data-orchid-name]');
            if (button) {
                selectOrchidByName(button.getAttribute('data-orchid-name'));
            }
        });
    }

    if (closeSearchResultsBtn) {
        closeSearchResultsBtn.addEventListener('click', () => modalManager.close(searchResultsModal));
    }

    if (searchResultsModal) {
        searchResultsModal.addEventListener('click', (event) => {
            if (event.target === searchResultsModal) modalManager.close(searchResultsModal);
        });
    }

    if (searchResultsGrid) {
        searchResultsGrid.addEventListener('click', (event) => {
            const button = event.target.closest('[data-orchid-name]');
            if (button) {
                if (searchResultsModal) modalManager.close(searchResultsModal);
                selectOrchidByName(button.getAttribute('data-orchid-name'));
            }
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (searchInput && searchForm.id === 'landing-search-form') {
                const query = searchInput.value.toLowerCase().trim();
                if (query.length >= 3) {
                    if (searchResultsModal && !searchResultsModal.classList.contains('modal-open')) {
                        modalManager.open(searchResultsModal);
                    }
                    filterOrchids(query, searchResultsGrid);
                }
            } else if (searchInput && gridContainer) {
                filterOrchids(searchInput.value, gridContainer);
            }
        });
    }
}

