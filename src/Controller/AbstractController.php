<?php
/**
 * Contrôleur de base de l'application (MVC).
 *
 * Tous les contrôleurs d'app héritent de cette classe pour réutiliser :
 *  - le rendu de vues (avec gabarit global / layout),
 *  - les réponses JSON,
 *  - les messages flash (une seule requête),
 *  - les jetons CSRF (protection des formulaires),
 *  - les redirections HTTP.
 *
 * Le constructeur reste sans argument : le Mithridatem Router (DefaultControllerResolver)
 * instancie les contrôleurs via `new ClassName()`.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

abstract class AbstractController
{
    /** Racine du dossier des vues (templates/), calculée depuis src/Controller/. */
    protected string $templatesDir;

    public function __construct()
    {
        // src/Controller -> deux niveaux au-dessus = racine projet.
        $this->templatesDir = dirname(__DIR__, 2) . '/templates';
    }

    // ---------------------------------------------------------------------
    // RENDU DE VUE
    // ---------------------------------------------------------------------

    /**
     * Rend une vue et renvoie le HTML.
     *
     * La vue est incluse dans le gabarit global `templates/layout.php` via la
     * variable `$content`. Les données sont extraites en variables locales.
     *
     * @param string      $template Nom du fichier vue sans extension (ex : "home").
     * @param array       $data     Données disponibles dans la vue.
     * @param string|null $title    Titre de la page (optionnel).
     * @return string   HTML rendu.
     */
    protected function render(string $template, array $data = [], ?string $title = null): string
    {
        // On rend d'abord la vue dans une zone de tampon pour récupérer son HTML.
        $content = $this->renderView($template, $data);

        // Variables consommées par le gabarit global (layout.php) :
        //   $pageTitle (title de page), $content (HTML de la vue), $flashes, $pageData (meta SEO).
        $pageTitle = $title;
        $flashes   = $this->getFlashes();
        $pageData  = $data;

        ob_start();
        include $this->templatesDir . '/layout.php';
        return ob_get_clean();
    }

    /**
     * Rend une vue seule (sans layout), utile pour les fragments / modales.
     *
     * @param string $template Nom du fichier vue sans extension.
     * @param array  $data     Données à exposer dans la vue.
     * @return string HTML de la vue.
     */
    protected function renderPartial(string $template, array $data = []): string
    {
        return $this->renderView($template, $data);
    }

    /**
     * Méthode interne : inclut le fichier de vue avec ses variables locales.
     *
     * @param string $template Nom du fichier vue sans extension.
     * @param array  $data     Données à extraire en variables.
     * @return string HTML produit par la vue.
     */
    private function renderView(string $template, array $data): string
    {
        $file = $this->templatesDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vue introuvable : {$template}.php");
        }

        // Rend isolé : $data devient des variables locales dans la vue.
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    // ---------------------------------------------------------------------
    // REPONSE JSON
    // ---------------------------------------------------------------------

    /**
     * Envoie une réponse JSON et retourne le corps (pour le front-controller).
     *
     * @param mixed $data   Données à encoder.
     * @param int   $status Code HTTP (200 par défaut).
     * @return string Corps JSON encodé.
     */
    protected function json(mixed $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ---------------------------------------------------------------------
    // REDIRECTION
    // ---------------------------------------------------------------------

    /**
     * Redirige (302) vers une URL et stoppe l'exécution.
     *
     * @param string $url URL cible (relative à la base de l'app ou absolue).
     * @return void
     */
    protected function redirectTo(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    // ---------------------------------------------------------------------
    // MESSAGES FLASH (stockés en session, consommés une seule fois)
    // ---------------------------------------------------------------------

    /**
     * Ajoute un message flash à afficher à la prochaine requête.
     *
     * @param string $message Texte à afficher.
     * @param string $type    Niveau : 'success' | 'error' | 'info' (défaut).
     * @return void
     */
    protected function addFlash(string $message, string $type = 'info'): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Récupère puis vide les messages flash de la session.
     *
     * @return array Liste de messages ['type' => ..., 'message' => ...].
     */
    protected function getFlashes(): array
    {
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']); // consommation unique
        return $flashes;
    }

    // ---------------------------------------------------------------------
    // CSRF (protection des formulaires)
    // ---------------------------------------------------------------------

    /**
     * Retourne le jeton CSRF de la session (créé s'il manque).
     *
     * @return string Jeton hexadécimal.
     */
    protected function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valide le jeton CSRF soumis (comparaison constante, insensible au timing).
     *
     * @param array $post Données du formulaire ($_POST).
     * @return bool Vrai si le jeton est présent et valide.
     */
    protected function isCsrfTokenValid(array $post): bool
    {
        $token        = (string) ($post['csrf_token'] ?? '');
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

        return $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    /**
     * Bloque la requête si le CSRF est invalide (400).
     *
     * @param array $post Données du formulaire.
     * @return void
     */
    protected function assertCsrf(array $post): void
    {
        if (!$this->isCsrfTokenValid($post)) {
            http_response_code(400);
            throw new \RuntimeException('Jeton CSRF invalide.');
        }
    }
}
