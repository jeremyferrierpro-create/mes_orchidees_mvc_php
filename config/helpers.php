<?php
/**
 * Helpers de vue (chemins, échappement, session).
 *
 * Fonctions globales légères utilisées par les gabarits (layout, composants, vues)
 * pour générer des URLs et des chemins d'assets corrects, que l'application soit
 * servie à la racine du domaine ou sous un sous-répertoire (APP_BASE_PATH sur Render).
 *
 * @package App\Config
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

if (!function_exists('base_path')) {
    /**
     * Préfixe de base de l'app ('' si servie à la racine, sinon '/sous-repertoire').
     *
     * @return string
     */
    function base_path(): string
    {
        $base = trim((string) ($_ENV['APP_BASE_PATH'] ?? ''));
        return $base === '' ? '' : '/' . trim($base, '/');
    }

    /**
     * Construit une URL interne à partir d'un chemin relatif à l'app.
     *
     * Ex : url('/encyclopedie') => '/encyclopedie' (racine) ou '/mes-orchidees/encyclopedie'.
     *
     * @param string $path Chemin commençant par '/' (ou vide pour l'accueil).
     * @return string
     */
    function url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        return (base_path() . $path) ?: '/';
    }

    /**
     * Chemin vers un fichier de l'application dans /public (assets, manifest…).
     *
     * @param string $path Chemin relatif sans '/' initial (ex : 'assets/css/style.css').
     * @return string
     */
    function asset(string $path): string
    {
        return base_path() . '/' . ltrim($path, '/');
    }

    /**
     * Échappement HTML sûr (anti-XSS). À utiliser pour TOUTES les données dynamiques.
     *
     * @param mixed $value Valeur à afficher.
     * @return string Échappé (guillemets inclus).
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * L'utilisateur courant est-il connecté ?
     *
     * @return bool
     */
    function is_logged_in(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    /**
     * Utilisateur courant (id, nom, prenom, role) ou null.
     *
     * @return array|null
     */
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Le rôle courant correspond-il à l'un des rôles demandés ?
     *
     * @param string|array $roles Un ou plusieurs rôles.
     * @return bool
     */
    function has_role(string|array $roles): bool
    {
        $roles = (array) $roles;
        return in_array($_SESSION['user']['role'] ?? null, $roles, true);
    }
}
