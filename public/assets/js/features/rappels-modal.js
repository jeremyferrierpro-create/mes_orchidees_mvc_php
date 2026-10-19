/**
 * @file features/rappels-modal.js
 * @description Je pilote la modale « Gérer mes rappels de soins » (Phase D) :
 *              j'ouvre le fragment authentifié, j'intercepte les actions POST
 *              et je recharge l'état persisté. Je réutilise ModalManager
 *              (core/modal.js) sans le réécrire : Échap, piège de focus,
 *              verrou du scroll et retour du focus sont ses responsabilités.
 * @author Jeremy Ferrier
 * @version 1.0
 *
 * Points de contrat (vérifiés dans le dépôt avant écriture) :
 *   - le squelette est rendu serveur par components/rappels_modal.php ; son
 *     corps .rappels-modal__body porte data-endpoint = URL du fragment
 *     générée par url() (préfixe d'installation respecté, aucun chemin dur) ;
 *   - le contenu arrive par GET /rappels/modal (HTML déjà échappé côté
 *     serveur, vues e()) — je ne reconstruis aucune carte en JavaScript ;
 *   - chaque action reste un POST des routes C0 (CSRF embarqué dans le
 *     formulaire, contrôle RG1 côté contrôleur) ; je ne crée aucune route ;
 *   - le POST est négocié en JSON (même logique métier, même contrôle CSRF et
 *     RG1 ; le parcours formulaire HTML de /rappels reste 302 + flash). Je ne
 *     suis donc AUCUNE redirection : la réponse du serveur est lue directement
 *     (un suivi de 302 échouerait notamment derrière `upgrade-insecure-requests`
 *     en HTTP local, vérifié en navigateur) ;
 *   - succès = `status: "ok"` renvoyé par le serveur. Une 400 (CSRF), une 403
 *     (session) ou une erreur réseau ne sont JAMAIS présentées comme réussies ;
 *   - « fermer la modale » ne modifie aucune donnée — distinct de « marquer
 *     lu » (toast C1), « ignorer un rappel » et « effectuer le soin ».
 */

import { open as ouvrirModale, close as fermerModale } from '../core/modal.js';

// Anti-double-exécution : une seule écriture à la fois dans la modale.
// Le bouton est désactivé pendante la requête et le contenu n'est rechargé
// qu'APRES confirmation du serveur — aucune action n'est rejouée si la page
// est rechargée ou si le fragment est actualise entre-temps.
let actionEnCours = false;

/**
 * Je charge (ou recharge) le fragment dans le corps de la modale.
 * @param {Object} corps - L'element .rappels-modal__body (porte data-endpoint).
 * @returns {Promise<boolean>} true si le contenu a ete rafraichi.
 */
async function chargerContenu(corps) {
    corps.setAttribute('aria-busy', 'true');
    try {
        const res = await fetch(corps.dataset.endpoint, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        // Fragment de NOTRE serveur, rendu par les vues qui échappent tout via
        // e() (mêmes gabarits que la page /rappels). Aucun données brutes.
        corps.innerHTML = await res.text();
        return true;
    } catch (error) {
        // Erreur réseau ou session expirée : etat d'erreur explicite, jamais
        // un contenu vide fait passer le message « aucun rappel ».
        corps.innerHTML = '';

        const message = document.createElement('p');
        message.className = 'rappels-modal__erreur';
        message.textContent = 'Le chargement de vos rappels a échoué (réseau ou session expirée). Aucune donnée n\'a été modifiée. Vous pouvez ';

        const repli = document.createElement('a');
        // Le point d'entree de la modale est l\'URL du fragment sans son suffixe
        // « /modal » : route jumeau /rappels, generee cote serveur, pas durcie.
        repli.href = (corps.dataset.endpoint || '').replace(/\/modal\/?$/, '') || '/';
        repli.textContent = 'réessayer depuis la page complète.';

        message.appendChild(repli);
        corps.appendChild(message);
        return false;
    } finally {
        corps.removeAttribute('aria-busy');
    }
}

/**
 * J'affiche un message d'état en tête du corps de la modale (succès, refus ou
 * échec d'action). Le corps porte `aria-live="polite"` : le message est annoncé
 * aux lecteurs d'écran sans détourner le focus de l'utilisateur.
 *
 * @param {Object} corps  - Le corps de la modale.
 * @param {string} texte  - Message lisible, sans détail technique.
 * @param {string} genre  - 'ok' | 'info' | 'ko' (pilote la couleur, jamais seule information).
 * @returns {void}
 */
function signaler(corps, texte, genre) {
    const code = genre === 'ok' || genre === 'info' ? genre : 'ko';
    const message = document.createElement('p');
    message.className = 'rappels-modal__statut rappels-modal__statut--' + code;
    message.setAttribute('data-genre', code);
    message.textContent = texte;
    corps.prepend(message);
}

/**
 * J'initialise la modale de gestion des rappels (pages dashboard + fiche
 * plante). No-op si le squelette n'est pas present sur la page courante.
 * @returns {void}
 */
export function initRappelsModal() {
    const modale = document.getElementById('rappels-modal');
    if (!modale) {
        return;
    }

    const corps = modale.querySelector('.rappels-modal__body');
    if (!corps || !corps.dataset.endpoint) {
        return;
    }

    // Garde anti-double-lier : le routeur d'app.js peut rappeler l'initialiseur
    // (navigation pas-a-pas) sans rechargement complet ; je ne lie qu'une fois.
    if (modale.dataset.liee === '1') {
        return;
    }
    modale.dataset.liee = '1';

    // --- Ouverture : je detourne le lien « Gérer mes rappels » (délégation,
    // car les declencheurs sont dans le contenu serveur). Sans JavaScript, le
    // lien reste un href vers /rappels : la page autonome fait le travail.
    document.addEventListener('click', function (event) {
        const declencheur = event.target.closest('[data-rappels-modal-trigger]');
        if (!declencheur) {
            return;
        }
        // Clic modifié (Ctrl/Maj/shift clic milieu) = navigation volontaire de
        // l'utilisateur (nouvel onglet…) : je ne l'intercepte pas.
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();

        // ModalManager se charge du focus initial (.modal-close), du piegeage,
        // du verrou de scroll, d'Échap et du retour du focus sur ce lien.
        ouvrirModale(modale, declencheur);
        chargerContenu(corps);
    });

    // --- Fermeture par le bouton visible. Échap et le piegeage de focus sont
    // deja couverts par core/modal.js ; je n'ajoute pas de fermeture au clic
    // sur le voile (pertes d'état évitées, non exigée par la mission).
    const boutonFermer = modale.querySelector('[data-rappels-modal-close]');
    if (boutonFermer) {
        boutonFermer.addEventListener('click', function () {
            fermerModale(modale);
        });
    }

    // --- Actions : j'intercepte la soumission des formulaires C0 presents dans
    // le fragment (Effectuer le soin, reporter, ignorer, supprimer). Je délègue
    // sur la modale elle-meme (element stable) et non sur le corps (remplacé à
    // chaque rafraîchissement). stopPropagation empeche le gestionnaire
    // document de js-confirm-delete (navigation.js) de confirmer une deuxieme
    // fois ou de laisser partir la soumission native.
    modale.addEventListener('submit', async function (event) {
        const forme = event.target.closest('form');
        if (!forme) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();

        if (forme.classList.contains('js-confirm-delete')) {
            const question = forme.dataset.confirm || 'Confirmer cette action irréversible ?';
            if (!window.confirm(question)) {
                return; // Annulation : rien n'est envoyé, le contenu reste intact.
            }
        }

        if (actionEnCours) {
            return; // Double-frappe ignorée tant qu'une écriture est en vol.
        }
        actionEnCours = true;

        const bouton = forme.querySelector('button');
        if (bouton) {
            bouton.disabled = true;
        }

        let succes = false;
        let genre  = 'ko';
        let messageServeur = '';
        try {
            const res = await fetch(forme.action, {
                method: 'POST',
                body: new FormData(forme),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) {
                // 400 (CSRF), 403 (session) ou 5xx : le front-controller renvoie
                // une page d'erreur HTML générique — aucune écriture confirmée.
                messageServeur = res.status === 400
                    ? 'Jeton de protection expiré : l\'action n\'a pas été envoyée. Rechargez l\'état et réessayez.'
                    : (res.status === 403
                        ? 'Session fermée : reconnectez-vous pour gérer vos rappels.'
                        : 'Le serveur a refusé l\'action (HTTP ' + res.status + '). Aucune donnée confirmée.');
            } else {
                const donnees = await res.json();
                succes = donnees.status === 'ok';
                // 'success' → succès vert, 'info' → neutre doré (ex. rappel déjà
                // clôturé : rien n'a été écrit une seconde fois), sinon refus.
                genre = donnees.type === 'success' ? 'ok' : (donnees.type === 'info' ? 'info' : 'ko');
                messageServeur = String(donnees.message || '');
            }
        } catch (error) {
            // Réseau coupé, réponse non JSON ou lecture impossible : le serveur
            // n'a rien confirmé — le rappel reste dans son état précédent.
            succes = false;
            messageServeur = 'Réseau indisponible : le serveur n\'a confirmé aucune écriture. Vérifiez l\'état affiché avant de réessayer.';
        } finally {
            actionEnCours = false;
        }

        // Dans tous les cas je relis l'état côté serveur : la modale ne montre
        // que des données persistées, jamais un état optimiste local.
        await chargerContenu(corps);
        signaler(corps, succes
            ? (messageServeur || 'Action enregistrée.')
            : (messageServeur || 'L\'action n\'a pas été confirmée par le serveur.'), succes ? genre : 'ko');

        // Le bouton cliqué a ete detruit par le rafraichissement : je repose le
        // focus sur le titre de la fenetre (atteignable, annonce aux TA) plutot
        // que de laisser le navigateur retomber sur le body.
        const titre = document.getElementById('rappels-modal-title');
        if (titre && typeof titre.focus === 'function') {
            titre.focus();
        }
    });
}
