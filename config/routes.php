<?php
/**
 * Table de routes de l'application (CONTRAT — cf. Phase 01 §4).
 *
 * Déclare TOUTES les routes validées sur le Mithridatem Router. Les contrôleurs
 * d'entités (Orchid/User/Soin/Notification/Moderation et les actions CRUD) sont
 * référencés ici en tant que contrat mais SERONT IMPLÉMENTÉS en Phase 03 ; le
 * front-controller répond alors proprement ("Phase 03") tant que la classe n'existe
 * pas. Les contrôleurs Home / Page / Auth / (pages de contenu) existent déjà.
 *
 * Sémantique des grants (voir App\Security\SessionGrantChecker) :
 *  - []                      → public
 *  - ['authenticated']       → utilisateur connecté (n'importe quel rôle)
 *  - ['admin']               → rôle admin
 *  - ['admin','moderateur']  → admin OU modérateur
 *
 * Nomenclature : liste au pluriel, fiche/création/suppression au singulier,
 * mise à jour via /update/{entite}. Ordre important : les routes statiques
 * (/orchid/new) sont déclarées AVANT les routes dynamiques (/orchid/{id}).
 *
 * @package App\Config
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

use Mithridatem\Routing\Router;

// Raccourcis de grants pour lire le contrat facilement.
const GRANT_PUBLIC = [];
const GRANT_AUTH   = ['authenticated'];
const GRANT_ADMIN  = ['admin'];
const GRANT_MODO   = ['admin', 'moderateur'];

/**
 * Enregistre toutes les routes sur le routeur fourni.
 *
 * @param Router $router Instance Routeur déjà configurée (grant checker, etc.).
 * @return void
 */
function register_routes(Router $router): void
{
    // --------- 4.1 Authentification / session ---------
    $router->mapController('GET',    '/login',     \App\Controller\AuthController::class, 'loginForm',  GRANT_PUBLIC);
    $router->mapController('POST',   '/login',     \App\Controller\AuthController::class, 'login',      GRANT_PUBLIC);
    $router->mapController('GET',    '/register',  \App\Controller\AuthController::class, 'registerForm', GRANT_PUBLIC);
    $router->mapController('POST',   '/register',  \App\Controller\AuthController::class, 'register',   GRANT_PUBLIC);
    $router->mapController('POST',   '/logout',    \App\Controller\AuthController::class, 'logout',     GRANT_AUTH);
    $router->mapController('GET',    '/me',        \App\Controller\AuthController::class, 'me',         GRANT_AUTH);

    // --------- 4.2 Pages de contenu (vues) ---------
    $router->mapController('GET', '/',               \App\Controller\HomeController::class,          'index',     GRANT_PUBLIC);
    $router->mapController('GET', '/encyclopedie',   \App\Controller\EncyclopedieController::class,  'index',     GRANT_PUBLIC);
    $router->mapController('GET', '/macollection',   \App\Controller\CollectionController::class,    'dashboard', GRANT_AUTH);
    $router->mapController('GET', '/conseils',       \App\Controller\ConseilController::class,       'index',     GRANT_PUBLIC);
    $router->mapController('GET', '/administration', \App\Controller\AdminController::class,         'dashboard', GRANT_ADMIN);
    $router->mapController('GET', '/confidentialite',\App\Controller\PageController::class,          'confidentialite', GRANT_PUBLIC);
    $router->mapController('GET', '/mentions',       \App\Controller\PageController::class,          'mentions',  GRANT_PUBLIC);
    $router->mapController('GET', '/accessibilite',  \App\Controller\PageController::class,          'accessibilite', GRANT_PUBLIC);

    // --------- 4.3 CRUD Orchidées (lecture publique, écriture admin, proposition user) ---------
    $router->mapController('GET',  '/orchids',            \App\Controller\OrchidController::class, 'index',    GRANT_PUBLIC);
    $router->mapController('GET',  '/orchids/search',     \App\Controller\OrchidController::class, 'search',   GRANT_PUBLIC);
    $router->mapController('GET',  '/orchid/new',         \App\Controller\OrchidController::class, 'newForm',  GRANT_ADMIN);
    $router->mapController('POST', '/orchid/new',         \App\Controller\OrchidController::class, 'create',   GRANT_ADMIN);
    $router->mapController('POST', '/orchid/propose',     \App\Controller\OrchidController::class, 'propose',  GRANT_AUTH);
    $router->mapController('POST', '/orchid/delete/{id}', \App\Controller\OrchidController::class, 'delete',   GRANT_ADMIN);
    $router->mapController('GET',  '/update/orchid/{id}', \App\Controller\OrchidController::class, 'editForm', GRANT_ADMIN);
    $router->mapController('POST', '/update/orchid',      \App\Controller\OrchidController::class, 'update',   GRANT_ADMIN);
    // Route dynamique AFTER les statiques :
    $router->mapController('GET',  '/orchid/{id}',        \App\Controller\OrchidController::class, 'show',     GRANT_PUBLIC);

    // --------- 4.3 CRUD Conseils (CRUD admin, lecture publique) ---------
    $router->mapController('GET',  '/conseil/new',         \App\Controller\ConseilController::class, 'newForm',  GRANT_ADMIN);
    $router->mapController('POST', '/conseil/new',         \App\Controller\ConseilController::class, 'create',   GRANT_ADMIN);
    $router->mapController('POST', '/conseil/delete/{id}', \App\Controller\ConseilController::class, 'delete',   GRANT_ADMIN);
    $router->mapController('GET',  '/update/conseil/{id}', \App\Controller\ConseilController::class, 'editForm', GRANT_ADMIN);
    $router->mapController('POST', '/update/conseil',      \App\Controller\ConseilController::class, 'update',   GRANT_ADMIN);
    $router->mapController('GET',  '/conseil/{id}',        \App\Controller\ConseilController::class, 'show',     GRANT_PUBLIC);

    // --------- 4.3 Collections (propriétaire uniquement — RG1) ---------
    $router->mapController('GET',  '/collections',           \App\Controller\CollectionController::class, 'index',   GRANT_AUTH);
    $router->mapController('POST', '/collection/new',        \App\Controller\CollectionController::class, 'create',  GRANT_AUTH);
    $router->mapController('POST', '/update/collection',     \App\Controller\CollectionController::class, 'update',  GRANT_AUTH);
    $router->mapController('POST', '/collection/delete/{id}',\App\Controller\CollectionController::class, 'delete',  GRANT_AUTH);
    $router->mapController('GET',  '/collection/{id}',       \App\Controller\CollectionController::class, 'show',    GRANT_AUTH);

    // --------- 4.3 Soins (rattachés à une collection de l'owner) ---------
    $router->mapController('GET',  '/soins',             \App\Controller\SoinController::class, 'index',   GRANT_AUTH);
    $router->mapController('POST', '/soin/new',          \App\Controller\SoinController::class, 'create',  GRANT_AUTH);
    $router->mapController('POST', '/update/soin',       \App\Controller\SoinController::class, 'update',  GRANT_AUTH);
    $router->mapController('POST', '/soin/delete/{id}',  \App\Controller\SoinController::class, 'delete',  GRANT_AUTH);

    // --------- 4.3 Notifications (owner ; lecture + marquage lu) ---------
    $router->mapController('GET',  '/notifications',            \App\Controller\NotificationController::class, 'index',    GRANT_AUTH);
    $router->mapController('POST', '/notification/{id}/read',   \App\Controller\NotificationController::class, 'markRead', GRANT_AUTH);
    $router->mapController('POST', '/notification/delete/{id}', \App\Controller\NotificationController::class, 'delete',   GRANT_AUTH);
    $router->mapController('GET',  '/notification/{id}',        \App\Controller\NotificationController::class, 'show',     GRANT_AUTH);

    // --------- 4.3 bis Rappels de soins (RG3 étendu — cycle de vie) ---------
    $router->mapController('GET',  '/rappels',                       \App\Controller\RappelController::class, 'index',   GRANT_AUTH);
    // Flux JSON « rappels de soin devenus dus et non lus » — consommé par le toast
    // persistant du jour J (Phase C1). GRANT_AUTH : le 403 du routeur protège la route.
    $router->mapController('GET',  '/api/rappels/dus',                \App\Controller\RappelController::class, 'dus',      GRANT_AUTH);
    // Phase D : fragment HTML de la modale « Gérer mes rappels » (mêmes listes que
    // /rappels, rendu sans layout via renderPartial). GRANT_AUTH : un visiteur
    // anonyme reçoit la 403 générique du front-controller, jamais un contenu tiers.
    $router->mapController('GET',  '/rappels/modal',                \App\Controller\RappelController::class, 'modal',    GRANT_AUTH);
    $router->mapController('POST', '/rappel/{id}/fait',              \App\Controller\RappelController::class, 'fait',    GRANT_AUTH);
    $router->mapController('POST', '/rappel/{id}/ignorer',           \App\Controller\RappelController::class, 'ignorer', GRANT_AUTH);
    $router->mapController('POST', '/rappel/{id}/report/{jours}',    \App\Controller\RappelController::class, 'report',  GRANT_AUTH);
    $router->mapController('POST', '/rappel/delete/{id}',            \App\Controller\RappelController::class, 'delete',  GRANT_AUTH);

    // --------- 4.3 Utilisateurs (admin) ---------
    $router->mapController('GET',  '/users',            \App\Controller\UserController::class, 'index',   GRANT_ADMIN);
    $router->mapController('GET',  '/user/new',         \App\Controller\UserController::class, 'newForm', GRANT_ADMIN);
    $router->mapController('POST', '/update/user',      \App\Controller\UserController::class, 'update',  GRANT_ADMIN);
    $router->mapController('POST', '/user/delete/{id}', \App\Controller\UserController::class, 'delete',  GRANT_ADMIN);
    $router->mapController('GET',  '/user/{id}',        \App\Controller\UserController::class, 'show',    GRANT_ADMIN);

    // --------- 4.3 Modération / workflow (admin + modérateur) ---------
    $router->mapController('GET',  '/moderation',                \App\Controller\ModerationController::class, 'index',   GRANT_MODO);
    $router->mapController('POST', '/moderation/{id}/approve',   \App\Controller\ModerationController::class, 'approve', GRANT_MODO);
    $router->mapController('POST', '/moderation/{id}/reject',    \App\Controller\ModerationController::class, 'reject',  GRANT_MODO);
}
