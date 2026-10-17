/**
 * @file features/background-animation.js
 * @description Je fais flotter des noms latins d'orchidees en fond d'accueil pour une ambiance poetique.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : features/background-animation.js — Animation poétique de l'accueil
// ===========================================================================
// J'ai voulu donner une identité visuelle forte à la page d'accueil avec des
// noms latins qui flottent en arrière-plan. Pourquoi un module séparé ?
// Pour que cette animation purement décorative ne pollue pas le code métier et
// puisse être désactivée facilement sur les autres pages.

import { getElement, createElement } from '../core/dom.js';

// Ma liste de 38 noms latins d'orchidées. Je les ai choisis pour leur
// musicalité et pour rappeler la richesse botanique que le site met en valeur.
const orchidNames = [
    'Phalaenopsis amabilis', 'Cattleya labiata', 'Dendrobium nobile',
    'Vanda coerulea', 'Oncidium flexuosum', 'Paphiopedilum insigne',
    'Odontoglossum crispum', 'Cymbidium eberhardtii', 'Laelia purpurata',
    'Miltonia spectabilis', 'Masdevallia coccinea', 'Lycaste deppei',
    'Stanhopea tigrina', 'Brassia verrucosa', 'Acacalis cyanea',
    'Acineta superba', 'Aerangis citrata', 'Angraecum sesquipedale',
    'Barkeria spectabilis', 'Bletilla striata', 'Coelogyne cristata',
    'Encyclia cochleata', 'Epidendrum radicans', 'Gongora galeata',
    'Zygopetalum maculatum', 'Maxillaria tenui-folia', 'Phragmipedium besseae',
    'Psychopsis papilio', 'Rhynchostylis gigantea', 'Sobralia macrantha',
    'Vanilla planifolia', 'Bulbophyllum fletcherianum', 'Catasetum pileatum',
    'Chysis laevis', 'Dracula simia', 'Eria coronaria', 'Ludisia discolor',
    'Renanthera coccinea', 'Sophronitis coccinea', 'Thunia alba'
];

// Je découpe l'écran en 9 zones (grille 3x3) pour répartir les mots.
// Pourquoi 9 zones ? Pour éviter que deux mots ne se chevauchent et pour
// garantir une couverture homogène de l'écran, même sur grand écran.
const gridZones = [
    { xMin: 5,  xMax: 25, yMin: 5,  yMax: 25 },
    { xMin: 35, xMax: 55, yMin: 5,  yMax: 25 },
    { xMin: 65, xMax: 85, yMin: 5,  yMax: 25 },
    { xMin: 5,  xMax: 25, yMin: 35, yMax: 55 },
    { xMin: 35, xMax: 55, yMin: 35, yMax: 55 },
    { xMin: 65, xMax: 85, yMin: 35, yMax: 55 },
    { xMin: 5,  xMax: 25, yMin: 65, yMax: 85 },
    { xMin: 35, xMax: 55, yMin: 65, yMax: 85 },
    { xMin: 65, xMax: 85, yMin: 65, yMax: 85 }
];

let zoneIndex = 0;
let animationInterval = null;

/**
 * Je lance l'animation des mots latins flottants sur l'accueil.
 * @returns {void}
 * @example
 * initBackgroundAnimation();
 */
export function initBackgroundAnimation() {
    const container = getElement('#latin-bg-layer');
    if (!container) return;

    /**
     * Je cree un mot flottant a une position aleatoire dans la zone suivante.
     * @returns {void}
     */
    function createFloatingWord() {
        // Si le conteneur a disparu (changement de page sans rechargement),
        // j'arrête l'intervalle pour ne pas fuir en mémoire. C'est une bonne
        // pratique de nettoyage.
        if (!document.body.contains(container)) {
            clearInterval(animationInterval);
            return;
        }

        const randomIndex = Math.floor(Math.random() * orchidNames.length);
        const zone = gridZones[zoneIndex];
        zoneIndex = (zoneIndex + 1) % gridZones.length;

        const randomX = Math.floor(Math.random() * (zone.xMax - zone.xMin)) + zone.xMin;
        const randomY = Math.floor(Math.random() * (zone.yMax - zone.yMin)) + zone.yMin;

        const span = createElement('span', {
            className: 'latin-word',
            text: orchidNames[randomIndex]
        });

        span.style.left = randomX + '%';
        span.style.top = randomY + '%';

        container.appendChild(span);

        // Je déclenche l'apparition avec un léger délai pour laisser le temps au
        // navigateur d'appliquer la transition CSS (opacity 0 -> 1).
        setTimeout(() => {
            span.classList.add('word-visible');
        }, 50);

        // Après 6 secondes d'affichage, je lance la disparition puis je supprime
        // le nœud du DOM pour ne pas accumuler des dizaines de span invisibles.
        setTimeout(() => {
            span.classList.remove('word-visible');
            setTimeout(() => {
                if (span.parentNode) {
                    span.parentNode.removeChild(span);
                }
            }, 2500);
        }, 6000);
    }

    // Je lance la création toutes les 1,5s et j'en crée 3 immédiatement pour
    // que l'accueil ne soit pas vide au premier affichage.
    animationInterval = setInterval(createFloatingWord, 1500);
    createFloatingWord();
    setTimeout(createFloatingWord, 500);
    setTimeout(createFloatingWord, 1000);
}
