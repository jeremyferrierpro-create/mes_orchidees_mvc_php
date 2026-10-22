<?php
/**
 * Service d'authentification basé sur les sessions PHP natives.
 *
 * Gère : la tentative de connexion (vérification du mot de passe), l'inscription
 * (hachage), la destruction de session (déconnexion) et la lecture de l'utilisateur
 * courant. Il dépend du contrat UserRepositoryInterface (et non d'une classe concrète),
 * ce qui le rend testable et découplé de la persistance.
 *
 * Hachage : password_hash(..., PASSWORD_DEFAULT) et password_verify(), sur
 * recommandation du formateur (l'algorithme suit les mises à jour de PHP ;
 * pas de PASSWORD_BCRYPT épinglé en dur).
 *
 * @package App\Service
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Service;

use App\Repository\UserRepositoryInterface;

class AuthService
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /**
     * Tente une connexion : vérifie le mot de passe puis ouvre la session.
     *
     * @param string $email    Email saisi.
     * @param string $password Mot de passe en clair saisi.
     * @return bool Vrai si la connexion réussit.
     */
    public function attempt(string $email, string $password): bool
    {
        // 1) On récupère l'utilisateur par email (le hash est stocké côté serveur).
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return false;
        }

        // 2) On compare le mot de passe saisi au hash stocké (timing-safe).
        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        // 3) Succès : on ouvre la session.
        $this->loginUser($user);
        return true;
    }

    /**
     * Inscrit un nouvel utilisateur (mot de passe haché automatiquement).
     *
     * @param array $data Champs : nom, prenom, email, password, role (optionnel 'user').
     * @return array Utilisateur créé (sans le mot de passe en clair).
     * @throws \InvalidArgumentException Si un champ obligatoire manque.
     */
    public function register(array $data): array
    {
        foreach (['nom', 'prenom', 'email', 'password'] as $champ) {
            if (empty($data[$champ])) {
                throw new \InvalidArgumentException("Champ manquant : {$champ}.");
            }
        }

        // Hachage avec PASSWORD_DEFAULT (jamais de mot de passe en clair stocké).
        $hash = password_hash((string) $data['password'], PASSWORD_DEFAULT);

        $id = $this->users->create([
            'nom'           => $data['nom'],
            'prenom'        => $data['prenom'],
            'email'         => $data['email'],
            'password_hash' => $hash,
            'role'          => $data['role'] ?? 'user',
        ]);

        return ['id' => $id, 'nom' => $data['nom'], 'prenom' => $data['prenom'], 'email' => $data['email']];
    }

    /**
     * Ouvre la session pour un utilisateur (après vérification du mot de passe).
     *
     * @param array $user Ligne utilisateur (id, nom, prenom, role...).
     * @return void
     */
    public function loginUser(array $user): void
    {
        // Régénération de l'ID : empêche la fixation de session (session fixation).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // On ne stocke QUE le strict nécessaire (jamais le hash).
        $_SESSION['user'] = [
            'id'     => $user['id'],
            'nom'    => $user['nom']    ?? null,
            'prenom' => $user['prenom'] ?? null,
            'role'   => $user['role']   ?? 'user',
        ];
    }

    /**
     * Déconnecte l'utilisateur (vide puis détruit la session).
     *
     * @return void
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            // Invalide le cookie de session côté navigateur.
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
        }
    }

    /**
     * Retourne l'utilisateur connecté, ou null.
     *
     * @return array|null
     */
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Un utilisateur est-il connecté ?
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    /**
     * L'utilisateur connecté possède-t-il tel rôle ?
     *
     * @param string $role Rôle recherché ('user', 'admin', 'moderateur').
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return (($_SESSION['user']['role'] ?? null) === $role);
    }
}
