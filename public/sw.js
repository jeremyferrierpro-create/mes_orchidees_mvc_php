// sw.js — Service Worker de l'application
// =========================================
// Ce fichier permet à l'application de fonctionner hors-ligne
// en mettant en cache les ressources principales.

// Nom du cache : on l'incrémente à chaque mise à jour majeure
// v7 : le projet est passé en MVC (front controller public/index.php + routes
// sans extension .php). L'ancienne liste pointait vers ./encyclopedie.php etc.,
// qui répondent 404 aujourd'hui : cache.addAll() rejetait donc EN BLOC, le
// cache initial restait vide et le mode hors-ligne ne fonctionnait plus.
// v8 (E11) : correction F2 — les 9 ressources manquantes du graphe d'imports
// réel de app.js sont ajoutées (core/db.js, features/rappels-modal.js,
// features/rappels-toast.js et les 6 data/*.json importés statiquement par
// db.js) + la page de repli ./offline.html (F3). Sans elles, un module JS non
// précaché recevait en hors-ligne un faux « 200 text/plain » et l'import ES
// échouait (MIME), tuant toute la chaîne app.js, modale des rappels comprise.
// v9 (E12-A) : retrait de ./login et ./register du précache décidé par le
// propriétaire. Ces pages étaient téléchargées avec les cookies du navigateur
// à l'install : une copie faite en session authentifiée aurait pu cacher une
// page post-connectée ou un token CSRF périmé dans le Cache Storage, qui est
// partagé par l'origine et survit aux déconnexions. Hors ligne, ces routes
// désormais non précachées dégénèrent vers ./offline.html (504) au lieu
// d'afficher un formulaire périmé. Le bump de version purge le cache v8 et
// ses entrées ./login et ./register chez tous les clients déjà installés.
const CACHE_NAME = 'mes-orchidees-v9';

// Liste des fichiers à mettre en cache pour le mode hors-ligne.
// Je ne mets EN CACHE AUCUNE page privée (macollection, administration,
// modération, notifications) : elles dépendent de la session et leurs données
// personnelles n'ont rien à faire dans un cache partagé par le navigateur.
const CACHE_ASSETS = [
    './',
    './encyclopedie',
    './conseils',
    './mentions',
    './confidentialite',
    './accessibilite',
    './manifest.json',
    './offline.html',
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
    './assets/js/core/db.js',
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
    './assets/js/features/rappels-toast.js',
    './assets/js/features/rappels-modal.js',
    // POLITIQUE (E12-A) : les 4 JSON métier ci-dessous (users, notifications,
    // collections, soins) ne sont précachés que parce que core/db.js les
    // IMPORTE STATIQUEMENT : sans eux, la chaîne de modules ES meurt hors
    // ligne. Ils DOIVENT rester des fichiers STATIQUES et VIDES ([]). Si l'un
    // d'eux devenait dynamique ou contenait la moindre donnée personnelle,
    // le RETIRER de CACHE_ASSETS et réévaluer sa stratégie : le cache du
    // Service Worker est partagé par l'origine et ne s'efface pas à la
    // déconnexion.
    './assets/js/data/users.json',
    './assets/js/data/orchids.json',
    './assets/js/data/conseils.json',
    './assets/js/data/collections.json',
    './assets/js/data/soins.json',
    './assets/js/data/notifications.json',
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
                    // On ne supprime QUE les anciennes versions du cache de
                    // cette application (préfixe « mes-orchidees- ») : un cache
                    // étranger au projet, éventuellement créé par un autre
                    // service sur la même origine, doit rester intact.
                    if (cacheName !== CACHE_NAME && cacheName.indexOf('mes-orchidees-') === 0) {
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
            .catch(function () {
                // Échec réseau : cache d'abord, puis repli HONNÊTE (F3).
                return caches.match(event.request).then(function (cached) {
                    if (cached) return cached;

                    // Navigation non satisfaisable : page hors ligne dédiée,
                    // en échec HTTP explicite (504) — plus jamais l'accueil
                    // renvoyé silencieusement comme un faux succès.
                    if (event.request.mode === 'navigate') {
                        return caches.match('./offline.html').then(function (fallback) {
                            if (fallback) {
                                return new Response(fallback.body, {
                                    status: 504,
                                    statusText: 'Hors ligne',
                                    headers: { 'Content-Type': 'text/html; charset=utf-8' }
                                });
                            }
                            return new Response('Connexion indisponible.', {
                                status: 504,
                                statusText: 'Hors ligne',
                                headers: { 'Content-Type': 'text/plain; charset=utf-8' }
                            });
                        });
                    }

                    // Ressource (JS, CSS, image, JSON…) non précachée : échec
                    // explicite 504 et corps vide. On ne fabrique aucun contenu
                    // et aucun faux « 200 text/plain » (défaut F2 de E9).
                    return new Response('', { status: 504, statusText: 'Hors ligne' });
                });
            })
    );
});
