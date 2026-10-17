/**
 * @file D:/FORMATION/PROJET FIL ROUGE/mon_orchidee/assets/js/features/administration.js
 * @description Je gere le panneau admin : dashboard, tableaux, modales et droits admin.
 * @author Jeremy Ferrier
 * @version 1.1
 */

import { replaceChildren } from '../core/dom.js';
import * as authService from '../services/auth-service.js';
import { getAllOrchids, deleteOrchid as deleteOrchidService } from '../services/orchid-service.js';
import { getAllConseils, saveConseil } from '../services/conseil-service.js';
import * as modalManager from '../core/modal.js';
import * as notifications from '../core/notifications.js';
import { readJson, writeJson, STORAGE_KEYS } from '../core/storage.js';
import { db } from '../core/db.js'; // je passe par la fausse BDD Supabase (assets/data/*.json)

// ===========================================================================
// FICHIER : features/administration.js – Panneau d'administration sécurisé
// ===========================================================================
// J'ai développé cette page comme un back-office complet réservé aux admins.
// Pourquoi un module dédié ? Parce qu'elle concentre 3 responsabilités critiques
// que je voulais isoler : 1) le tableau de bord chiffré (utilisateurs, plantes,
// activités), 2) les deux tableaux de gestion (encyclopédie + notifications),
// 3) les trois actions à fort impact (modérer une orchidée, ajouter un conseil,
// gérer les utilisateurs). Toutes les données proviennent de ma couche db.js
// (qui lit /assets/js/data/*.json) : ainsi, je valide déjà le circuit de données
// qui deviendra Supabase en Phase 3 sans toucher à l'interface.

/**
 * J'initialise le panneau d'administration (verrou admin + dashboard + tableaux + modales).
 * @returns {void}
 * @example
 * initAdministration();
 */
export function initAdministration() {
    // --- VERROU ADMIN : je bloque l'accès si pas admin ---
    // Je regarde qui est connecté (ou rien si personne)
    const currentUser = authService.getCurrentUser();
    // Je vérifie : est-ce que la personne est bien connectée et a le rôle "admin" ?
    if (!authService.isAuthenticated() || !currentUser || currentUser.role !== 'admin') {
        // Je prépare un message clair pour la personne
        const isConnected = authService.isAuthenticated();
        const message = isConnected
            ? "Accès refusé : ton compte (" + (currentUser ? currentUser.email : "inconnu") + ") n'a pas le rôle administrateur."
            : "Accès refusé : tu dois te connecter avec un compte administrateur.";
        // J'affiche une petite notification rouge en haut à droite
        notifications.error(message);
        // Je cache le contenu admin pour ne rien montrer (sécurité visuelle)
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            mainContent.innerHTML = '<section class="admin-header text-center" style="padding:60px 20px;"><h1 class="page-title">ACCÈS REFUSÉ</h1><p style="margin-top:20px; color:#fff; font-size:1.1rem;">' + message + '</p><p style="margin-top:10px;"><a href="authentification.php" class="btn btn-primary">Se connecter</a> <a href="index.php" class="btn btn-outline" style="margin-left:10px;">Retour accueil</a></p></section>';
        }
        // Après 2,5 secondes je renvoie vers la page de connexion (ou accueil si déjà connecté mais pas admin)
        setTimeout(() => {
            window.location.href = isConnected ? 'index.php' : 'authentification.php';
        }, 2500);
        return; // j'arrête tout, je ne charge PAS le tableau de bord
    }

    // --- BADGE ADMIN DANS L'EN-TÊTE ---
    // On récupère qui est connecté
    const session = authService.getCurrentUser();
    // On cherche ses vraies infos dans users.json (comme Supabase : SELECT * FROM users WHERE email = ...)
    const res = db.from('users').select().eq('email', session.email).single();
    const user = (!res.error && res.data) ? res.data : session;
    // On crée le badge dans le header
    const badgeEl = document.getElementById('admin-badge');
    if (badgeEl) {
        badgeEl.textContent = `Bonjour ${user.prenom} ${user.nom}, vous êtes connecté en tant qu'admin`;
        badgeEl.style.color = '#c4a47c';
        badgeEl.style.fontWeight = '600';
    }

    // Si on arrive ici, c'est que c'est bien un admin : je continue normalement
    // Je récupère les vraies listes : orchidées, utilisateurs, conseils
    const orchids = getAllOrchids();
    const users = authService.checkUsersDb();
    const conseils = getAllConseils();

    // Je récupère les notifications depuis la table notifications (via db, comme Supabase)
    // C'est comme faire SELECT * FROM notifications
    let notifRes = db.from('notifications').select().execute();
    let notificationsDb = notifRes.data;
    // Si la table est vide au premier lancement, db.js l'a déjà remplie avec notifications.json, rien à faire

    // Je remplis le haut de page (les 3 cartes chiffrées)
    populateDashboard(users, orchids, conseils, notificationsDb);

    // Je remplis les 2 tableaux
    populateEncyclopediaTable(orchids);
    populateNotificationsTable(notificationsDb);

    // J'active les 3 petites fenêtres (modales) + les boutons
    setupModals(users);
}

/**
 * Je remplis les 3 cartes chiffrees du haut (utilisateurs, plantes, activites).
 * @param {Array} users - La liste des utilisateurs.
 * @param {Array} orchids - La liste des orchidees.
 * @param {Array} conseils - La liste des conseils.
 * @param {Array} notifsList - La liste des notifications.
 * @returns {void}
 */
function populateDashboard(users, orchids, conseils, notifsList) {
    // --- Carte UTILISATEURS ---
    // Je compte les vrais utilisateurs
    document.getElementById('stat-users-total').textContent = users.length;
    // Pour la démo, je mets des chiffres réalistes mais calculés simplement
    document.getElementById('stat-users-monthly').textContent = Math.min(users.length, 1);
    document.getElementById('stat-users-weekly').textContent = "0";
    // Je compte combien ont le rôle admin = "le + de plantes" simulé
    const adminCount = users.filter(u => u.role === 'admin').length;
    document.getElementById('stat-users-active').textContent = adminCount || users.length;

    // --- Carte PLANTES ---
    document.getElementById('stat-plants-total').textContent = orchids.length;
    // Je prends la première orchidée comme "phare" au lieu de mettre ACACALIS en dur
    const phare = orchids[0] ? orchids[0].genre.toUpperCase() : "-";
    document.getElementById('stat-plants-phare').textContent = phare;
    // Je compte les plantes dans les collections (table collections via db, comme Supabase)
    const collection = db.from('collections').select().execute().data || [];
    document.getElementById('stat-plants-owned').textContent = collection.length;
    // Je prends la dernière comme "la plus rare" au lieu de BARLIA qui n'existe pas
    const rare = orchids[orchids.length - 1] ? orchids[orchids.length - 1].genre.toUpperCase() : "-";
    document.getElementById('stat-plants-rare').textContent = rare;

    // --- Carte ACTIVITES ---
    document.getElementById('stat-act-pending').textContent = notifsList.length;
    document.getElementById('stat-act-advices').textContent = conseils.length;
    // Je mets des chiffres cohérents au lieu de 15 et 5 en dur
    document.getElementById('stat-act-approved').textContent = orchids.length;
    document.getElementById('stat-act-rejected').textContent = "0";
}

/**
 * Je remplis le tableau de l'encyclopedie avec les orchidees.
 * @param {Array} orchids - Le tableau des orchidees a afficher.
 * @returns {void}
 */
function populateEncyclopediaTable(orchids) {
    const container = document.getElementById('admin-encyclopedia-list');
    if (!container) return;

    // Je vide le tableau avant de le remplir
    replaceChildren(container);

    // Pour chaque orchidée, je crée une ligne
    orchids.forEach(function (orchid) {
        const row = document.createElement('div');
        row.className = 'admin-table-row';

        const colNom = document.createElement('div');
        colNom.className = 'col-nom';
        colNom.title = orchid.name;
        colNom.textContent = orchid.name;

        const colEtat = document.createElement('div');
        colEtat.className = 'col-etat';
        colEtat.textContent = orchid.behavior || 'N/A';

        const colOrigines = document.createElement('div');
        colOrigines.className = 'col-origines';
        colOrigines.title = orchid.origin || '';
        colOrigines.textContent = orchid.origin || 'N/A';

        const colDecouverte = document.createElement('div');
        colDecouverte.className = 'col-decouverte';
        colDecouverte.title = orchid.discovered || '';
        colDecouverte.textContent = orchid.discovered || 'N/A';

        const colActions = document.createElement('div');
        colActions.className = 'col-actions';

        const viewBtn = document.createElement('button');
        viewBtn.type = 'button';
        viewBtn.className = 'action-btn view-btn';
        viewBtn.setAttribute('aria-label', 'Voir ' + orchid.name);
        const viewIcon = document.createElement('i');
        viewIcon.className = 'fa-solid fa-eye';
        viewBtn.appendChild(viewIcon);

        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'action-btn edit-btn';
        editBtn.setAttribute('aria-label', 'Editer ' + orchid.name);
        const editIcon = document.createElement('i');
        editIcon.className = 'fa-solid fa-pen-to-square';
        editBtn.appendChild(editIcon);

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'action-btn delete-btn';
        deleteBtn.setAttribute('aria-label', 'Supprimer ' + orchid.name);
        const deleteIcon = document.createElement('i');
        deleteIcon.className = 'fa-solid fa-trash';
        deleteBtn.appendChild(deleteIcon);

        colActions.appendChild(viewBtn);
        colActions.appendChild(editBtn);
        colActions.appendChild(deleteBtn);

        row.appendChild(colNom);
        row.appendChild(colEtat);
        row.appendChild(colOrigines);
        row.appendChild(colDecouverte);
        row.appendChild(colActions);

        container.appendChild(row);

        // Quand on clique sur l'œil, j'ouvre la fiche en lecture seule
        viewBtn.addEventListener('click', function () {
            openModerateModal(orchid, 'view');
        });
        // Quand on clique sur le crayon, j'ouvre la même fiche mais avec les boutons Approuver/Refuser
        editBtn.addEventListener('click', function () {
            openModerateModal(orchid, 'moderate');
        });
        deleteBtn.addEventListener('click', function () {
            if (confirm("Confirmez-vous la suppression de " + orchid.name + " ?")) {
                // Je supprime vraiment de la base locale /data/orchids-data.js via le service
                deleteOrchidService(orchid.id);
                row.remove();
                notifications.success("Orchidée " + orchid.name + " supprimée de la base locale.");
                // Je mets à jour le compteur du dashboard
                const totalEl = document.getElementById('stat-plants-total');
                if (totalEl) totalEl.textContent = getAllOrchids().length;
            }
        });
    });
}

/**
 * Je remplis le tableau des notifications.
 * BUG CORRIGE : le parametre s'appelait "notifications", ce qui masquait
 * l'import "import * as notifications" du module.
 * Dans les callbacks (deleteBtn), notifications.success() appelait donc
 * le tableau Array au lieu du module, ce qui provoquait une erreur silencieuse.
 * Renomme en "notifsList" pour lever le conflit.
 * @param {Array} notifsList - Le tableau des notifications.
 * @returns {void}
 */
function populateNotificationsTable(notifsList) {
    const container = document.getElementById('admin-notifications-list');
    if (!container) return;

    replaceChildren(container);

    notifsList.forEach(function (notif) {
        const row = document.createElement('div');
        row.className = 'admin-table-row';

        const colDate = document.createElement('div');
        colDate.className = 'col-date';
        colDate.textContent = notif.date;

        const colNotif = document.createElement('div');
        colNotif.className = 'col-notif';
        // Je gère les 2 noms possibles : message (vrai) ou text (ancien)
        const notifText = notif.message || notif.text || '';
        colNotif.title = notifText;
        colNotif.textContent = notifText;

        const colActions = document.createElement('div');
        colActions.className = 'col-actions';

        const viewBtn = document.createElement('button');
        viewBtn.type = 'button';
        viewBtn.className = 'action-btn view-btn';
        viewBtn.setAttribute('aria-label', 'Voir');
        const viewIcon = document.createElement('i');
        viewIcon.className = 'fa-solid fa-eye';
        viewBtn.appendChild(viewIcon);

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'action-btn delete-btn';
        deleteBtn.setAttribute('aria-label', 'Supprimer');
        const deleteIcon = document.createElement('i');
        deleteIcon.className = 'fa-solid fa-trash';
        deleteBtn.appendChild(deleteIcon);

        colActions.appendChild(viewBtn);
        colActions.appendChild(deleteBtn);

        row.appendChild(colDate);
        row.appendChild(colNotif);
        row.appendChild(colActions);
        container.appendChild(row);

        // Bouton poubelle : je supprime vraiment de la table notifications (via db, comme DELETE FROM notifications WHERE id=...)
        deleteBtn.addEventListener('click', function () {
            // Je fais DELETE FROM notifications WHERE id = notif.id
            db.from('notifications').delete().eq('id', notif.id).execute();
            let notifs = db.from('notifications').select().execute().data || [];
            row.remove();
            notifications.success("Notification supprimée de la base locale.");
            const pendEl = document.getElementById('stat-act-pending');
            if (pendEl) pendEl.textContent = notifs.length;
        });
        viewBtn.addEventListener('click', function () {
            notifications.info(notifText);
        });
    });
}

/**
 * Je prepare les 3 modales et leurs boutons (utilisateurs, moderation, conseil).
 * @param {Array} allUsers - La liste de tous les utilisateurs.
 * @returns {void}
 */
function setupModals(allUsers) {
    // --- 1. BOUTON GERER LES UTILISATEURS ---
    const modalUser = document.getElementById('modal-user-form');
    const btnManageUsers = document.getElementById('btn-manage-users');
    const btnCancelUser = document.getElementById('btn-cancel-user');
    const userForm = document.getElementById('admin-user-form');

    if (btnManageUsers) {
        btnManageUsers.addEventListener('click', function () {
            // J'ouvre la fiche du premier utilisateur de la vraie base
            if (allUsers.length > 0) openUserModal(allUsers[0]);
            else notifications.warning("Aucun utilisateur trouvé.");
        });
    }

    if (btnCancelUser) btnCancelUser.addEventListener('click', () => closeModal(modalUser));
    if (modalUser) {
        modalUser.querySelector('.modal-close').addEventListener('click', () => closeModal(modalUser));
        modalUser.addEventListener('click', (e) => { if (e.target === modalUser) closeModal(modalUser); });
        if (userForm) userForm.addEventListener('submit', function (e) {
            e.preventDefault();
            // Je lis ce qui est dans les champs
            const email = document.getElementById('user-email').value.trim();
            const original = allUsers.find(u => u.email === email) || {};
            // Je construis la fiche mise à jour (je garde id et créé d'origine)
            const updatedUser = {
                id: original.id || Date.now(),
                nom: document.getElementById('user-nom').value.trim(),
                prenom: document.getElementById('user-prenom').value.trim(),
                email: email,
                password: original.password || "demouser",
                role: document.getElementById('user-role').value,
                created: original.created || new Date().toLocaleDateString('fr-FR'),
                modified: new Date().toLocaleDateString('fr-FR')
            };
            if (!updatedUser.nom || !updatedUser.prenom || !updatedUser.email) {
                notifications.error("Nom, prénom et email obligatoires.");
                return;
            }
            // J'enregistre vraiment dans /data/users-data.js via localStorage
            authService.saveUser(updatedUser);
            closeModal(modalUser);
            notifications.success("Utilisateur " + updatedUser.email + " mis à jour dans la base locale.");
            // Je rafraîchis les chiffres du dashboard depuis les vraies tables via db
            const freshUsers = authService.checkUsersDb();
            const freshConseils = getAllConseils();
            const freshNotifs = db.from('notifications').select().execute().data || [];
            populateDashboard(freshUsers, getAllOrchids(), freshConseils, freshNotifs);
        });
    }

    // --- 2. BOUTON MODERER UN AJOUT ---
    const modalMod = document.getElementById('modal-moderate-orchid');
    const btnModerate = document.getElementById('btn-moderate-orchid');
    if (btnModerate) {
        btnModerate.addEventListener('click', function () {
            // Je prends une vraie orchidée de la base au lieu d'inventer Vanilla
            const orchids = getAllOrchids();
            const dummy = orchids.find(o => o.id.includes('vanilla')) || orchids[0];
            openModerateModal(dummy, 'moderate');
        });
    }
    if (modalMod) {
        modalMod.querySelector('.modal-close').addEventListener('click', () => closeModal(modalMod));
        modalMod.addEventListener('click', (e) => { if (e.target === modalMod) closeModal(modalMod); });
        const btnApprove = document.getElementById('btn-approve-orchid');
        const btnReject = document.getElementById('btn-reject-orchid');
        if (btnApprove) btnApprove.addEventListener('click', () => {
            closeModal(modalMod);
            notifications.success("Fiche approuvée !");
        });
        if (btnReject) btnReject.addEventListener('click', () => {
            closeModal(modalMod);
            notifications.warning("Fiche rejetée.");
        });
    }

    // --- 3. BOUTON AJOUTER UN CONSEIL ---
    const modalAdvice = document.getElementById('modal-add-advice');
    const btnAddAdvice = document.getElementById('btn-add-advice');
    const adviceForm = document.getElementById('admin-advice-form');
    const btnCancelAdvice = document.getElementById('btn-cancel-advice');

    if (btnAddAdvice) {
        btnAddAdvice.addEventListener('click', () => openAdviceModal());
    }
    if (btnCancelAdvice) btnCancelAdvice.addEventListener('click', () => closeModal(modalAdvice));
    if (modalAdvice) {
        modalAdvice.querySelector('.modal-close').addEventListener('click', () => closeModal(modalAdvice));
        modalAdvice.addEventListener('click', (e) => { if (e.target === modalAdvice) closeModal(modalAdvice); });
        if (adviceForm) adviceForm.addEventListener('submit', function (e) {
            e.preventDefault();
            // Je lis le formulaire
            const name = document.getElementById('adv-name').value.trim();
            const cat = document.getElementById('adv-cat').value;
            const content = document.getElementById('adv-content').value.trim();
            if (!name || !content) {
                notifications.error("Nom et contenu obligatoires.");
                return;
            }
            // Je crée une vraie fiche comme dans /data/conseils-data.js
            const newConseil = {
                id: "conseils-" + Date.now(),
                type: "category",
                name: name,
                category: cat,
                content: content,
                img: "./assets/images/site/base.jpg",
                careCards: { temperature: "20 °C", arrosage: "régulier", hygrometrie: "65 %", rempotage: "2 ans", engrais: "1 arrosage sur 2", substrats: "selon type" }
            };
            // J'enregistre vraiment dans la base locale
            saveConseil(newConseil);
            closeModal(modalAdvice);
            notifications.success("Conseil '" + newConseil.name + "' ajouté dans la base locale.");
            adviceForm.reset();
            // Je mets à jour le compteur
            const advEl = document.getElementById('stat-act-advices');
            if (advEl) advEl.textContent = getAllConseils().length;
        });
    }
}

// Je stocke l'élément qui avait le focus avant l'ouverture d'une modale,
// pour pouvoir y revenir quand elle se ferme (accessibilité clavier).
let lastFocusedElement = null;

/**
 * J'ouvre la fiche utilisateur dans une modale.
 * @param {Object} user - L'objet utilisateur a editer.
 * @returns {void}
 */
function openUserModal(user) {
    const modal = document.getElementById('modal-user-form');
    if (!modal) return;
    lastFocusedElement = document.activeElement;

    document.getElementById('user-nom').value = user.nom || '';
    document.getElementById('user-prenom').value = user.prenom || '';
    document.getElementById('user-email').value = user.email || '';
    document.getElementById('user-role').value = user.role || 'user';
    document.getElementById('user-created-date').textContent = "Créé le : " + (user.created || '--/--/----');
    document.getElementById('user-modified-date').textContent = "Modifié le : " + (user.modified || '--/--/----');

    openModal(modal);
}

/**
 * J'ouvre la fiche orchidee en lecture ou moderation.
 * @param {Object} orchid - L'objet orchidee a afficher.
 * @param {string} mode - "view" pour voir, "moderate" pour moderer.
 * @returns {void}
 */
function openModerateModal(orchid, mode) {
    const modal = document.getElementById('modal-moderate-orchid');
    if (!modal) return;
    lastFocusedElement = document.activeElement;

    document.getElementById('modal-mod-title').textContent = orchid.name;
    document.getElementById('mod-orchid-scientific').textContent = orchid.name;
    document.getElementById('mod-orchid-vernacular').textContent = orchid.vernacular || '';
    document.getElementById('mod-orchid-short').textContent = orchid.shortDesc || '';
    document.getElementById('mod-orchid-long').textContent = orchid.longDesc || '';
    const imgEl = document.getElementById('mod-orchid-img');
    if (imgEl) imgEl.src = orchid.img || '';

    document.getElementById('mod-spec-ordre').textContent = orchid.order || '-';
    document.getElementById('mod-spec-espece').textContent = orchid.species || '-';
    document.getElementById('mod-spec-genre').textContent = orchid.genre || '-';
    document.getElementById('mod-spec-famille').textContent = orchid.family || '-';
    document.getElementById('mod-spec-subfamily').textContent = orchid.subfamily || '-';
    document.getElementById('mod-spec-tribu').textContent = orchid.tribu || '-';
    document.getElementById('mod-spec-subtribu').textContent = orchid.subtribu || '-';
    document.getElementById('mod-spec-behavior').textContent = orchid.behavior || '-';
    document.getElementById('mod-spec-discovered').textContent = orchid.discovered || '-';
    document.getElementById('mod-spec-origin').textContent = orchid.origin || '-';

    const actionsContainer = modal.querySelector('.admin-mod-actions');
    if (actionsContainer) {
        if (mode === 'view') {
            actionsContainer.style.display = 'none';
        } else {
            actionsContainer.style.display = 'flex';
        }
    }

    openModal(modal);
}

/**
 * J'ouvre la modale de creation de conseil.
 * @returns {void}
 */
function openAdviceModal() {
    const modal = document.getElementById('modal-add-advice');
    if (!modal) return;
    lastFocusedElement = document.activeElement;
    const form = document.getElementById('admin-advice-form');
    if (form) form.reset();
    openModal(modal);
}

/**
 * J'ouvre une modale via le gestionnaire central.
 * @param {HTMLElement} modal - L'element de la modale.
 * @returns {void}
 */
function openModal(modal) {
    modalManager.open(modal, lastFocusedElement);
}

/**
 * Je ferme une modale via le gestionnaire central.
 * @param {HTMLElement} modal - L'element de la modale.
 * @returns {void}
 */
function closeModal(modal) {
    modalManager.close(modal);
}