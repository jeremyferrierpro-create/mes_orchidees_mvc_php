/**
 * @file features/add-button.js
 * @description Je gere le bouton "+ COLLECTION" de la modale : je verifie si tu es connecte et j'ajoute l'orchidee.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : features/add-button.js â€” Bouton "+ COLLECTION" de la modale
// ===========================================================================
// J'ai isolé ce bouton dans son propre module pour deux raisons : d'abord pour
// vérifier l'authentification avant tout ajout, ensuite pour gérer le cas du
// "pendingOrchid" (orchidée en attente quand l'utilisateur n'est pas connecté).
// C'est un excellent exemple de parcours utilisateur complet a  expliquer au jury.

import { getElement } from '../core/dom.js';
import * as authService from '../services/auth-service.js';
import * as collectionService from '../services/collection-service.js';
import { getAllOrchids } from '../services/orchid-service.js';
import * as notifications from '../core/notifications.js';
import { STORAGE_KEYS, writeString } from '../core/storage.js';

/**
 * J'initialise le bouton d'ajout present dans la modale d'orchidee.
 * @returns {void}
 * @example
 * initAddButton(); // a appeler dans app.js au chargement
 */
export function initAddButton() {
    // Je récupa¨re le titre affiché dans la modale : c'est ma source de vérité
    // pour savoir quelle orchidée l'utilisateur veut ajouter.
    const modalTitle = getElement('#modal-orchid-title');
    const modal = getElement('#orchid-modal');
    const addButton = getElement('.btn-add-collection');

    if (!addButton) return;

    /**
     * Je masque ou j'affiche le bouton selon que l'utilisateur est connecte.
     * @returns {void}
     */
    function updateCollectionButtonVisibility() {
        addButton.hidden = !authService.isAuthenticated();
    }

    // J'écoute l'événement custom 'orchidModalOpened' tiré depuis search.js.
    // Pourquoi un événement custom ? Pour découpler les modules : search.js n'a
    // pas besoin de connaa®tre add-button.js, il se contente d'annoncer l'ouverture.
    if (modal) {
        modal.addEventListener('orchidModalOpened', updateCollectionButtonVisibility);
    }
    updateCollectionButtonVisibility();

    /**
     * J'ajoute vraiment l'orchidee a la collection perso (ou je demande connexion).
     * @param {string} orchidName - Le nom affiche dans la modale (ex: "ACACALIS CYANEA").
     * @returns {void}
     */
    async function ajouterAMaCollection(orchidName) {
        if (!orchidName || orchidName === '...') return;

        // Si l'utilisateur n'est pas connecté, je ne l'ajoute pas brutalement.
        // Je lui propose de se connecter et je mémorise son intention via
        // pendingOrchid : apra¨s connexion, je pourrai le rediriger et finaliser l'ajout.
        if (!authService.isAuthenticated()) {
            const choix = confirm(
                'Vous devez être connecté pour ajouter une orchidée a  votre collection.\n\n' +
                'Souhaitez-vous vous connecter ou créer un compte dès maintenant ?'
            );
            if (choix) {
                writeString(STORAGE_KEYS.pendingOrchid, orchidName);
                window.location.href = 'authentification.php';
            }
            return;
        }

        // Je retrouve l'objet orchidée complet a  partir de son nom affiché.
        // Pourquoi une recherche par nom ? Parce que la modale ne stocke que le
        // texte, pas l'id. Je normalise en minuscules pour éviter les erreurs de casse.
        const orchids = await getAllOrchids();
        const orchid = orchids.find(o => o.name.toLowerCase() === orchidName.toLowerCase());

        if (!orchid) {
            notifications.error('Impossible d\'ajouter cette orchidée : elle n\'est pas référencée.');
            return;
        }

        const maCollection = collectionService.getCollection();
        const dejaPresente = maCollection.some(item => item.orchidId === orchid.id);

        if (dejaPresente) {
            notifications.warning('L\'orchidée "' + orchid.name + '" est déja  présente dans votre collection.');
            return;
        }

        // J'ajoute l'orchidée avec un collectionId unique basé sur le timestamp
        // pour distinguer deux exemplaires de la même espèce.
        collectionService.addOrchid({
            collectionId: 'col-' + Date.now(),
            orchidId: orchid.id,
            addedAt: new Date().toISOString(),
            location: '',
            notes: '',
            careHistory: []
        });

        notifications.success('L\'orchidée "' + orchid.name + '" a été ajoutée a  votre collection.');
    }

    // Au clic sur le bouton "+", je lis le titre actuel de la modale et je lance l'ajout.
    addButton.addEventListener('click', function () {
        const orchidName = modalTitle ? modalTitle.textContent.trim() : null;
        if (orchidName) {
            ajouterAMaCollection(orchidName);
        }
    });
}

