/**
 * @file pwa.js
 * @description J'enregistre le Service Worker pour que le site marche hors-ligne (PWA).
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : pwa.js — Enregistrement du Service Worker (PWA hors-ligne)
// ===========================================================================
// J'ai isolé l'enregistrement du Service Worker dans ce module pour clarifier
// mon intention : rendre l'application installable et consultable hors-ligne.
// Pourquoi un Service Worker ? Parce que c'est la brique technique qui permet
// la conformité PWA : il intercepte les requêtes réseau et sert les fichiers
// depuis le cache, même sans connexion.

/**
 * J'enregistre le Service Worker qui met en cache les ressources.
 * @returns {void}
 * @example
 * initPWA(); // a appeler au demarrage dans app.js
 */
export function initPWA() {
    if ('serviceWorker' in navigator) {
        // J'enregistre le fichier sw.js situé à la racine du projet. C'est lui
        // qui contient la logique de cache (CACHE_NAME, CACHE_ASSETS).
        // Pourquoi à la racine ? Pour que son scope couvre tout le site.
        navigator.serviceWorker.register('sw.js')
            .then(registration => {
                // Succès de l'enregistrement du Service Worker
            })
            .catch(error => {
                // Si l'enregistrement échoue (ex: sw.js introuvable ou erreur de
                // syntaxe), je loggue l'erreur pour pouvoir la corriger rapidement.
                console.error('Échec de l\'enregistrement du Service Worker :', error);
            });
    }
}
