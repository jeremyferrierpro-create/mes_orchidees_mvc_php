/**
 * @file tests/js/rappels-modal.test.mjs
 * @description Test exécutable du module de la modale « Gérer mes rappels »
 *              (Phase D) : contrat d'initialisation sans DOM + vérification
 *              statique des règles de sécurité appliquées au client.
 * @author Jeremy Ferrier
 * @version 1.0
 *
 * Lancement : node tests/js/rappels-modal.test.mjs
 *
 * Pourquoi ce découpage ? Le dépôt n'a pas de package.json et aucune dépendance
 * DOM (jsdom) n'a été autorisée. J'importe donc le VRAI fichier de production
 * par file:// (ses imports relatifs se résolvent seuls) après avoir posé les
 * deux globales touchées au niveau module (window) et pendant l'exécution
 * (document). Ce test prouve le contrat d'initialisation et les invariants
 * lisibles dans le source ; il NE PREUVE PAS le comportement dans un navigateur
 * — la partie clavier/focus/réseau est vérifiée séparément (rapport phase-d/04).
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const modalPath = resolve(here, '../../public/assets/js/features/rappels-modal.js');
const appPath = resolve(here, '../../public/assets/js/app.js');

let pass = 0;
let fail = 0;

function checkTrue(label, ok, detail = '') {
    if (ok) {
        pass++;
        console.log(`  PASS | ${label}`);
    } else {
        fail++;
        console.log(`  FAIL | ${label}${detail ? ' → ' + detail : ''}`);
    }
}

function check(label, actual, expected) {
    checkTrue(`${label} (${String(actual)} attendu : ${String(expected)})`, actual === expected);
}

// ---------------------------------------------------------------------------
// Bouchon minimal : uniquement ce que le module utilise réellement.
// ---------------------------------------------------------------------------
function elementStub(options = {}) {
    const listeners = [];
    return {
        dataset: { ...options.dataset },
        classList: { contains: () => false, add() {}, remove() {} },
        children: options.children || {},
        listeners,
        addEventListener(type, handler) {
            listeners.push({ type, handler });
        },
        querySelector(selector) {
            return this.children[selector] || null;
        },
        querySelectorAll() {
            return [];
        },
        setAttribute() {},
        removeAttribute() {},
        prepend() {},
        appendChild() {},
        focus() {}
    };
}

function installDom({ modale = null }) {
    const docListeners = [];
    globalThis.window = { confirm: () => true, location: { pathname: '/macollection' } };
    globalThis.document = {
        docListeners,
        getElementById: (id) => (id === 'rappels-modal' ? modale : null),
        addEventListener(type, handler) {
            docListeners.push({ type, handler });
        },
        createElement: () => elementStub()
    };
    return docListeners;
}

// Import du module de production (le top-level de core/modal.js touche window).
installDom({ modale: null });
const { initRappelsModal } = await import(pathToFileURL(modalPath).href);
const src = readFileSync(modalPath, 'utf8');
const appSrc = readFileSync(appPath, 'utf8');

console.log('\n--- R1 · Contrat d\'initialisation (module réel, DOM bouchonné) ---');
checkTrue('le module s\'importe et exporte initRappelsModal', typeof initRappelsModal === 'function');

// R1-1 — Squelette absent (toutes les autres pages du site) : no-op silencieux.
let docListeners = installDom({ modale: null });
let threw = false;
try {
    initRappelsModal();
} catch (e) {
    threw = true;
}
checkTrue('no-op sans exception quand #rappels-modal est absent', !threw);
check('aucun écouteur posé hors page concernée', docListeners.length, 0);

// R1-2 — Squelette présent mais sans endpoint : rien ne doit être branché.
const corpsSansEndpoint = elementStub({ dataset: {} });
let modale = elementStub({ children: { '.rappels-modal__body': corpsSansEndpoint } });
docListeners = installDom({ modale });
threw = false;
try {
    initRappelsModal();
} catch (e) {
    threw = true;
}
checkTrue('no-op quand le corps ne porte pas data-endpoint', !threw && docListeners.length === 0);

// R1-3 — Garde anti-double-lier : le module ne doit pas superposer deux liens.
const corpsLie = elementStub({ dataset: { endpoint: '/rappels/modal', liee: '1' } });
modale = elementStub({ dataset: { liee: '1' }, children: { '.rappels-modal__body': corpsLie } });
docListeners = installDom({ modale });
initRappelsModal();
check('aucun écouteur document en double-bind', docListeners.length, 0);
check('aucune soumission branchée en double-bind', modale.listeners.length, 0);

// R1-4 — Cas nominal : un seul écouteur de clic (délégation) + une soumission.
const corpsOk = elementStub({ dataset: { endpoint: '/rappels/modal' } });
const boutonFermer = elementStub();
modale = elementStub({
    children: {
        '.rappels-modal__body': corpsOk,
        '[data-rappels-modal-close]': boutonFermer
    }
});
docListeners = installDom({ modale });
initRappelsModal();
check('un écouteur click délégué sur document',
    docListeners.filter((l) => l.type === 'click').length, 1);
check('un écouteur submit sur la modale',
    modale.listeners.filter((l) => l.type === 'submit').length, 1);
check('le bouton fermer est branché une fois',
    boutonFermer.listeners.filter((l) => l.type === 'click').length, 1);
check('marqueur de liaison posé sur la modale', modale.dataset.liee, '1');

console.log('\n--- R2 · Invariants de sécurité (lecture du source réel) ---');

// Deux fetch : le chargement du fragment (lecture) et l\'action (écriture POST).
const fetchCount = (src.match(/\bfetch\(/g) || []).length;
check('deux appels fetch (fragment + action)', fetchCount, 2);
checkTrue('l\'écriture passe exclusivement par method: POST',
    /method:\s*'POST'/.test(src) && !/method:\s*'GET'/.test(src));
checkTrue('aucune action de soin envoyée en GET',
    !/fetch\([^)]*\/(fait|ignorer|report|delete)/.test(src));
checkTrue('négociation JSON de la réponse d\'action',
    /'Accept':\s*'application\/json'/.test(src));
checkTrue('succès conditionné par la confirmation du serveur (status ok)',
    /status === 'ok'/.test(src));
checkTrue('aucune redirection suivie (source du faux message en navigateur)',
    !/redirected/.test(src));
checkTrue('les messages d\'action passent par textContent (jamais innerHTML)',
    /message\.textContent = texte/.test(src));
checkTrue('le contenu injecté vient du fragment serveur, jamais d\'une donnée client',
    /corps\.innerHTML = await res\.text\(\)/.test(src)
        && !/corps\.innerHTML = [^\n]*\${/.test(src)
        && !/insertAdjacentHTML/.test(src));
checkTrue('aucun code évalué dynamiquement (eval / new Function / document.write)',
    !/\beval\(/.test(src) && !/new Function\(/.test(src) && !/document\.write\(/.test(src));
checkTrue('aucune URL de fragment durcie (endpoint lu depuis data-endpoint)',
    !/'\/rappels\/modal'/.test(src) && /corps\.dataset\.endpoint/.test(src));
checkTrue('repli explicite vers la page autonome quand le fragment échoue',
    /rappels-modal__erreur/.test(src) && /réessayer depuis la page complète/.test(src));

// Un seul point d'entrée : app.js importe le module une fois, jamais le gabarit.
check('app.js importe le module exactement une fois',
    (appSrc.match(/features\/rappels-modal\.js/g) || []).length, 1);
const layoutPath = resolve(here, '../../templates/layout.php');
checkTrue('layout.php ne charge pas le module en balise <script> séparée',
    !/rappels-modal\.js/.test(readFileSync(layoutPath, 'utf8')));

console.log('\n=== Bilan modale rappels JS ===');
console.log(`PASS ${pass} · FAIL ${fail}`);
process.exit(fail === 0 ? 0 : 1);
