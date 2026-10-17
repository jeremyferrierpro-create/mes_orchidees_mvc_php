/**
 * @file core/storage.js
 * @description Je centralise tout le localStorage : je lis, j'ecris et je supprime avec des cles uniques.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/storage.js — Persistance locale centralisée et sécurisée
// ===========================================================================
// J'ai centralisé ici toute la gestion du localStorage. Pourquoi ?
// Pour éviter d'éparpiller des chaînes comme "mo_user_session" dans tout le code.
// Si je dois renommer une clé demain, je ne la change qu'ici, et tout le projet
// suit. C'est un principe de maintenabilité et de robustesse.

// Je déclare un objet unique STORAGE_KEYS qui liste toutes mes clés de stockage.
// Je le fige avec Object.freeze() : ainsi, aucun module ne pourra accidentellement
// le modifier à l'exécution (ex: STORAGE_KEYS.session = "autre"). C'est une
// protection contre les erreurs d'écriture et cela garantit la cohérence des
// données persistées.
/**
 * Je liste toutes les cles localStorage utilisees (figees pour eviter les fautes).
 * @type {Object}
 */
export const STORAGE_KEYS = Object.freeze({
    session: 'mo_user_session',
    userCollection: 'mo_user_collection',
    users: 'mo_users_db',
    orchids: 'mo_orchids',
    conseils: 'mo_conseils',
    notifications: 'mo_notifications',
    pendingOrchid: 'pendingOrchidToAdd'
});

/**
 * Je lis une valeur JSON depuis le localStorage.
 * @param {string} key - La cle a lire (ex: STORAGE_KEYS.session).
 * @param {*} [fallback=null] - La valeur par defaut si la cle n'existe pas ou est cassee.
 * @returns {*} L'objet/liste parse ou le fallback.
 * @example
 * const session = readJson(STORAGE_KEYS.session);
 */
export function readJson(key, fallback = null) {
    const raw = localStorage.getItem(key);
    if (raw === null) return fallback;
    try {
        return JSON.parse(raw);
    } catch (error) {
        console.error(`Invalid JSON in localStorage key: ${key}`, error);
        return fallback;
    }
}

/**
 * J'ecris une valeur (objet/tableau) en JSON dans le localStorage.
 * @param {string} key - La cle ou ecrire.
 * @param {*} value - La valeur a transformer en JSON.
 * @returns {void}
 * @example
 * writeJson(STORAGE_KEYS.session, { email: "a@b.fr" });
 */
export function writeJson(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
}

/**
 * Je supprime une cle du localStorage.
 * @param {string} key - La cle a supprimer.
 * @returns {void}
 */
export function remove(key) {
    localStorage.removeItem(key);
}

/**
 * Je lis une simple chaine de caracteres (non JSON).
 * @param {string} key - La cle a lire.
 * @param {string|null} [fallback=null] - La valeur si absente.
 * @returns {string|null} La chaine ou le fallback.
 */
export function readString(key, fallback = null) {
    const raw = localStorage.getItem(key);
    if (raw === null) return fallback;
    return raw;
}

/**
 * J'ecris une simple chaine.
 * @param {string} key - La cle ou ecrire.
 * @param {string} value - La chaine a stocker.
 * @returns {void}
 */
export function writeString(key, value) {
    localStorage.setItem(key, value);
}
