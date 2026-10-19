// sw.js — Service Worker de l'application
// =========================================
// Ce fichier permet à l'application de fonctionner hors-ligne
// en mettant en cache les ressources principales.

// Nom du cache : on l'incrémente à chaque mise à jour majeure
// v7 : le projet est passé en MVC (front controller public/index.php + routes
// sans extension .php). L'ancienne liste pointait vers ./encyclopedie.php etc.,
// qui répondent 404 aujourd'hui : cache.addAll() rejetait donc EN BLOC, le
// cache initial restait vide et le mode hors-ligne ne fonctionnait plus.
const CACHE_NAME = 'mes-orchidees-v7';

// Liste des fichiers à mettre en cache pour le mode hors-ligne.
// Je ne mets EN CACHE AUCUNE page privée (macollection, administration,
// modération, notifications) : elles dépendent de la session et leurs données
// personnelles n'ont rien à faire dans un cache partagé par le navigateur.
const CACHE_ASSETS = [
    './',
    './encyclopedie',
    './conseils',
    './login',
    './register',
    './mentions',
    './confidentialite',
    './accessibilite',
    './manifest.json',
    './assets/css/style.css',
    './assets/js/pwa.js',
    './assets/js/app.js',
    './assets/js/core/storage.js',
    './assets/js/core/security.js',
    './assets/js/core/dom.js',
    './assets/js/core/focus.js',
    './assets/js/core/modal.js',
    './assets/js/core/notifications.js',
    './assets/js/core/router.js',
    './assets/js/core/loader.js',
    './assets/js/services/auth-service.js',
    './assets/js/services/collection-service.js',
    './assets/js/services/orchid-service.js',
    './assets/js/services/conseil-service.js',
    './assets/js/features/navigation.js',
    './assets/js/features/background-animation.js',
    './assets/js/features/search.js',
    './assets/js/features/add-button.js',
    './assets/js/features/collection.js',
    './assets/js/features/administration.js',
    './assets/js/features/conseils.js',
    './assets/js/features/authentication.js',
    './assets/images/site/favicon.png',
    './assets/images/site/logotransparent.webp',
    './assets/images/site/background.webp',
    './assets/images/site/orchidee_hero.webp'
];

// Événement "install" : on met en cache les ressources
self.addEventListener('install', function (event) {
    // J'ajoute fichier par fichier au lieu de cache.addAll() : add() n'est pas
    // atomique, un seul 404 ne doit plus vider tout le cache initial.
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function (cache) {
                return Promise.all(
                    CACHE_ASSETS.map(function (url) {
                        return cache.add(url).catch(function (error) {
                            console.warn('Ressource non mise en cache (' + url + ') :', error);
                        });
                    })
                );
            })
    );
    // On active immédiatement le nouveau Service Worker
    self.skipWaiting();
});

// Événement "activate" : on nettoie les anciennes versions du cache
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cacheNames) {
            // On parcourt tous les caches existants
            return Promise.all(
                cacheNames.map(function (cacheName) {
                    // Si le cache n'est pas celui actuel, on le supprime
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    // On prend le contrôle de toutes les pages immédiatement
    self.clients.claim();
});

// Événement "fetch" : on sert les fichiers depuis le cache si possible
self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request)
            .catch(function () { return caches.match(event.request); })
            .then(function (response) {
                if (response) return response;
                if (event.request.mode === 'navigate') return caches.match('./');
                return new Response('Ressource non disponible hors-ligne.', {
                    headers: { 'Content-Type': 'text/plain' }
                });
            })
    );
});
