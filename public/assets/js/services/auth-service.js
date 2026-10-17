/**
 * @file services/auth-service.js
 * @description Je gere la connexion via l'API PHP (Neon/PostgreSQL).
 * @author Jeremy Ferrier
 * @version 2.0 (Migration PHP)
 */

import { STORAGE_KEYS, readJson, writeJson, remove } from '../core/storage.js';

/**
 * Je verifie si un utilisateur est connecte d'apres le state local.
 */
export function isAuthenticated() {
  return readJson(STORAGE_KEYS.session) !== null;
}

/**
 * Je recupere l'utilisateur actuellement connecte (cache UI).
 */
export function getCurrentUser() {
  return readJson(STORAGE_KEYS.session);
}

/**
 * J'authentifie l'utilisateur via l'API.
 */
export async function login(email, password) {
    const res = await fetch('api/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
    });
    const data = await res.json();
    
    if (!res.ok) {
        throw new Error(data.error || 'Identifiants incorrects');
    }
    
    // Le cookie JWT securise est envoye par le serveur.
    // On garde juste les infos user pour l'affichage UI (sans mdp).
    writeJson(STORAGE_KEYS.session, data.user);
    return data.user;
}

/**
 * J'inscris un nouvel utilisateur via l'API.
 */
export async function register(email, password, nom, prenom) {
    const res = await fetch('api/auth/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, nom, prenom })
    });
    const data = await res.json();
    
    if (!res.ok) {
        throw new Error(data.error || "Erreur lors de l'inscription");
    }
    
    return data.user;
}

/**
 * Je deconnecte l'utilisateur en detruisant la session cote serveur et client.
 */
export async function logout() {
    await fetch('api/auth/logout.php', { method: 'POST' });
    remove(STORAGE_KEYS.session);
}

