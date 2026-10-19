/**
 * @file features/rappels-toast.js
 * @description Je fais apparaitre les rappels de soins devenus dus (jour J) et non
 *              lus dans des toasts persistants en haut à droite. Le rappel reste
 *              affiché jusqu'à ce que l'utilisateur le consulte puis confirme sa
 *              lecture ; le fermer (×) ne change AUCUN état côté serveur.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : features/rappels-toast.js — Toast persistant du jour J (Phase C1)
// ===========================================================================
// Je m'appuie sur trois contrats réels vérifiés avant de coder :
//  1. core/notifications.js : showToast() accepte duration = 0 (persistant) mais
//     ne sait pas porter d'actions ; je réutilise donc le conteneur #toast-container
//     (aria-live="polite", rendu par le footer) et ses classes visuelles, sans
//     toucher au module partagé — les toasts flash existants sont préservés.
//  2. GET /api/rappels/dus : réservé aux utilisateurs authentifiés (GRANT_AUTH),
//     active les rappels échus via activateDueReminders() (aucun doublon créé) et
//     renvoie { status, count, consultUrl, readUrlBase, csrf, rappels[] }.
//  3. POST /notification/{id}/read : CSRF + isolation RG1 ; négocié en JSON grâce
//     à l'en-tête Accept. Un échec (400/404/réseau) ne doit JAMAIS faire croire
//     côté client que le rappel est lu : je ne retire le toast qu'après un 200 ok.
//
// Distinction des actions (exigence produit) :
//  - « Consulter » : lien simple vers /rappels (GET sans effet de bord ; je ne
//    cible pas /notification/{id} dont la lecture HTML marque lue en GET — hors
//    charte pour mon flux).
//  - « Marquer comme lu » : POST explicite, seule action qui change l'état lu.
//  - « × » : retire le toast du DOM uniquement ; le rappel reste non lu et
//    réapparaîtra à la prochaine ouverture de l'application.
//  - « Effectuer le soin » : n'existe que sur la page /rappels (C0), pas ici.

import { showToast } from '../core/notifications.js';

// Je limite à trois toasts simultanés : au-delà, la page serait monopolisée ;
// le lien « Consulter » renvoie vers /rappels où tout l'échéancier est visible.
const MAX_TOASTS = 3;

// Durée de l'animation de sortie (identique à core/notifications.js : 400 ms).
const LEAVE_MS = 400;

// Je mémorise si l'initialisation a déjà tourné sur ce chargement de page :
// un seul appel réseau, jamais de rafraîchissement périodique inutile.
let dejaLance = false;

/**
 * Je fabrique le bouton de fermeture purement visuelle (×).
 * @param {Object} toastElement - Le toast à fermer.
 * @returns {Object} Le bouton prêt à insérer.
 */
function makeCloseButton(toastElement) {
    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Fermer la notification (ne pas marquer comme lue)');

    const closeIcon = document.createElement('i');
    closeIcon.className = 'fa-solid fa-times';
    closeIcon.setAttribute('aria-hidden', 'true');
    closeBtn.appendChild(closeIcon);

    closeBtn.addEventListener('click', () => removeToastElement(toastElement));
    return closeBtn;
}

/**
 * Je retire un élément du DOM avec l'animation de sortie du design system.
 * @param {Object} toastElement - L'élément à retirer.
 * @returns {void}
 */
function removeToastElement(toastElement) {
    if (toastElement.classList.contains('toast-leaving')) return;
    toastElement.classList.add('toast-leaving');
    setTimeout(() => {
        if (toastElement.parentNode) {
            toastElement.parentNode.removeChild(toastElement);
        }
    }, LEAVE_MS);
}

/**
 * Je construis un span de métadonnée (type de soin, date) — texte simple, XSS-safe.
 * @param {string} className - Classe CSS de la métadonnée.
 * @param {string} text - Contenu textuel.
 * @returns {Object} L'élément construit.
 */
function makeMeta(className, text) {
    const el = document.createElement('span');
    el.className = className;
    el.textContent = text; // textContent, jamais innerHTML : protection XSS
    return el;
}

/**
 * Je fabrique le toast persistant d'un rappel de soin dû (jour J).
 * @param {Object} rappel - Ligne du flux /api/rappels/dus (Notification::toArray + late).
 * @param {Object} flux   - { consultUrl, readUrlBase, csrf } renvoyés par le serveur.
 * @returns {Object} L'élément toast inséré dans #toast-container.
 */
function buildRappelToast(rappel, flux) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'toast toast-rappel' + (rappel.late ? ' toast-rappel--retard' : '');
    // L'urgence réelle est informationnelle (le rappel attend, il ne coupe rien) :
    // je m'appuie sur l'aria-live="polite" du conteneur, pas sur role="alert".
    toast.setAttribute('aria-label', 'Rappel de soin à effectuer');

    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-seedling toast-rappel__icon';
    icon.setAttribute('aria-hidden', 'true');
    toast.appendChild(icon);

    const body = document.createElement('div');
    body.className = 'toast-rappel__body';

    const titre = document.createElement('strong');
    titre.className = 'toast-rappel__plant';
    titre.textContent = rappel.orchidName || 'Un soin vous attend';
    body.appendChild(titre);

    if (rappel.typeSoinLabel) {
        body.appendChild(makeMeta('toast-rappel__type', rappel.typeSoinLabel));
    }
    body.appendChild(makeMeta('toast-rappel__date', rappel.late
        ? 'En retard — échéance ' + rappel.dateFr
        : 'À faire aujourd\'hui — ' + rappel.dateFr));
    body.appendChild(makeMeta('toast-rappel__message', rappel.message));

    // « Consulter » : navigation GET sans effet de bord vers la page des rappels.
    const consultLink = document.createElement('a');
    consultLink.className = 'toast-rappel__action';
    consultLink.href = flux.consultUrl;
    consultLink.textContent = 'Consulter mes rappels';
    body.appendChild(consultLink);

    toast.appendChild(body);

    // « Marquer comme lu » : la SEULE action qui change l'état côté serveur.
    const readBtn = document.createElement('button');
    readBtn.className = 'toast-rappel__read';
    readBtn.type = 'button';
    readBtn.textContent = 'Marquer comme lu';
    readBtn.addEventListener('click', async () => {
        readBtn.disabled = true; // pas de double-frappe pendant l'appel
        try {
            const res = await fetch(flux.readUrlBase + '/' + encodeURIComponent(rappel.id) + '/read', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'csrf_token=' + encodeURIComponent(flux.csrf)
            });
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            const json = await res.json();
            if (json.status !== 'ok') {
                throw new Error(json.status || 'erreur');
            }
            // Succès prouvé : seulement maintenant je retire le toast.
            removeToastElement(toast);
            showToast('Rappel marqué comme lu.', 'info');
        } catch (error) {
            // Échec réseau/HTTP : le rappel RESTE non lu et le toast persiste.
            console.warn('Marquage « lu » impossible :', error);
            showToast('Marquage impossible. Le rappel reste non lu.', 'error');
            readBtn.disabled = false;
        }
    });
    toast.appendChild(readBtn);

    // « × » distinct de la lecture : fermeture visuelle seule.
    toast.appendChild(makeCloseButton(toast));

    container.appendChild(toast);
    return toast;
}

/**
 * J'initialise le toast persistant des rappels dus.
 * Appelé sur toutes les pages par app.js ; je ne fais de réseau que si le
 * footer a rendu l'amorce de session (utilisateur authentifié).
 * @returns {Promise<void>}
 */
export async function initRappelsToast() {
    if (dejaLance) return;
    const bootstrap = document.getElementById('rappels-toast-bootstrap');
    if (!bootstrap) return; // visiteur anonyme : aucune requête, aucun toast
    dejaLance = true;

    const endpoint = bootstrap.dataset.endpoint;
    if (!endpoint) return;

    let flux;
    try {
        const res = await fetch(endpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        // 403 (session perdue), 500 ou réseau coupé : j'abandonne SILENCIEUSEMENT.
        // Un rappel non lu n'est jamais détruit ni marqué lu ici.
        if (!res.ok) return;
        flux = await res.json();
    } catch (error) {
        console.warn('Chargement des rappels dus impossible :', error);
        return;
    }

    if (!flux || flux.status !== 'ok' || !Array.isArray(flux.rappels)) return;

    flux.rappels.slice(0, MAX_TOASTS).forEach((rappel) => {
        buildRappelToast(rappel, {
            consultUrl: flux.consultUrl,
            readUrlBase: flux.readUrlBase,
            csrf: flux.csrf
        });
    });
}
