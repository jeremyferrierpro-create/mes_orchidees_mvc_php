<?php
/**
 * Contrôleur d'authentification (connexion, inscription, déconnexion, session).
 *
 * Délègue la logique à App\Service\AuthService (sessions natives + PASSWORD_DEFAULT).
 * Le constructeur reste SANS ARGUMENT : le DefaultControllerResolver du router
 * instancie les contrôleurs via `new ClassName()`. Les dépendances sont donc
 * construites ici (composition simple, style débutant). Un conteneur à injection
 * de dépendances pourra remplacer cette construction en Phase 03.
 *
 * Protection des formulaires : jeton CSRF (voir AbstractController) vérifié sur
 * chaque POST.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\AuthService;

class AuthController extends AbstractController
{
    private AuthService $auth;

    public function __construct()
    {
        parent::__construct();
        // Composition manuelle (pas de conteneur en Phase 02).
        $this->auth = new AuthService(new UserRepository());
    }

    /**
     * GET /login — Formulaire de connexion.
     *
     * @return string HTML rendu.
     */
    public function loginForm(): string
    {
        if ($this->auth->isAuthenticated()) {
            $this->redirectTo('/macollection');
        }
        return $this->render('pages/auth_login', [
            'csrf' => $this->getCsrfToken(),
        ], 'Connexion');
    }

    /**
     * POST /login — Traite la connexion (redirection, ne revient jamais).
     *
     * @return void
     */
    public function login(): void
    {
        $this->assertCsrf($_POST);

        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($this->auth->attempt($email, $password)) {
            $this->addFlash('Bienvenue, ' . ($_SESSION['user']['prenom'] ?? '') . ' !', 'success');
            $this->redirectTo('/macollection');
        }

        // Échec : on recharge le formulaire avec un message (compte non trouvé / mdp).
        $this->addFlash('Identifiants incorrects.', 'error');
        $this->redirectTo('/login');
    }

    /**
     * GET /register — Formulaire d'inscription.
     *
     * @return string HTML rendu.
     */
    public function registerForm(): string
    {
        if ($this->auth->isAuthenticated()) {
            $this->redirectTo('/macollection');
        }
        return $this->render('pages/auth_register', [
            'csrf' => $this->getCsrfToken(),
        ], 'Inscription');
    }

    /**
     * POST /register — Traite l'inscription (redirection, ne revient jamais).
     *
     * @return void
     */
    public function register(): void
    {
        $this->assertCsrf($_POST);

        try {
            $this->auth->register([
                'nom'      => trim((string) ($_POST['nom'] ?? '')),
                'prenom'   => trim((string) ($_POST['prenom'] ?? '')),
                'email'    => trim((string) ($_POST['email'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
                // Le rôle reste 'user' : un compte admin ne se crée jamais via le public (RG6).
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('Inscription impossible : ' . $e->getMessage(), 'error');
            $this->redirectTo('/register');
        }

        $this->addFlash('Compte créé, vous pouvez vous connecter.', 'success');
        $this->redirectTo('/login');
    }

    /**
     * POST /logout — Déconnexion (grant : authentifié).
     *
     * @return void
     */
    public function logout(): void
    {
        $this->assertCsrf($_POST);
        $this->auth->logout();
        $this->redirectTo('/login');
    }

    /**
     * GET /me — Session courante (API JSON, grant : authentifié).
     *
     * @return string JSON {id, nom, prenom, role} ou message.
     */
    public function me(): string
    {
        return $this->json($this->auth->user() ?? ['message' => 'Non authentifié'], 200);
    }
}
