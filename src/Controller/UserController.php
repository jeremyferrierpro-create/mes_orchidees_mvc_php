<?php
/**
 * Contrôleur de gestion des utilisateurs (rôle admin) — Phase 03, incrément 5.
 *
 * Back-office réservé admin : liste, consultation/fiche, création et édition
 * (rôle inclus) et suppression. La CONTRACT de routes ne déclare pas de POST
 * dédié à la création : le formulaire de création ET de modification POSTENT tous
 * deux vers `/update/user` ; la méthode distingue les deux selon la présence d'un
 * `id` (vide = création).
 *
 * Le mot de passe n'est jamais exposé ni relu ; à la création il est haché avec
 * password_hash(PASSWORD_DEFAULT) (convention projet). À l'édition, il n'est pas
 * modifié par ce contrôleur.
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver).
 *
 * @package App\Controller
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Repository\UserRepository;

class UserController extends AbstractController
{
    /** Rôles admissibles (ENUM de la table users). */
    private const ROLES = ['user', 'admin', 'moderateur'];

    private UserRepository $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserRepository();
    }

    /**
     * GET /users — Liste des utilisateurs (grant : admin).
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        return $this->render('pages/admin/users', [
            'users' => $this->users->findAll(),
            'csrf'  => $this->getCsrfToken(),
        ], 'Gestion des utilisateurs');
    }

    /**
     * GET /user/new — Formulaire de création d'un utilisateur (grant : admin).
     *
     * @return string HTML rendu.
     */
    public function newForm(): string
    {
        return $this->render('pages/admin/user_form', [
            'user'   => ['id' => '', 'nom' => '', 'prenom' => '', 'email' => '', 'role' => 'user'],
            'mode'   => 'create',
            'action' => url('/update/user'),
            'csrf'   => $this->getCsrfToken(),
        ], 'Nouvel utilisateur');
    }

    /**
     * GET /user/{id} — Fiche / formulaire d'édition d'un utilisateur (grant : admin).
     *
     * @param string $id UUID de l'utilisateur.
     * @return string HTML rendu.
     */
    public function show(string $id): string
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Utilisateur introuvable.',
            ], 'Utilisateur introuvable');
        }

        return $this->render('pages/admin/user_form', [
            'user'   => $user,
            'mode'   => 'update',
            'action' => url('/update/user'),
            'csrf'   => $this->getCsrfToken(),
        ], 'Utilisateur — ' . trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')));
    }

    /**
     * POST /update/user — Crée (id vide) ou met à jour (id présent) un utilisateur.
     *
     * @return void (redirection).
     */
    public function update(): void
    {
        $this->assertCsrf($_POST);

        $id     = trim((string) ($_POST['id'] ?? ''));
        $nom    = trim((string) ($_POST['nom'] ?? ''));
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $email  = trim((string) ($_POST['email'] ?? ''));
        $role   = in_array((string) ($_POST['role'] ?? ''), self::ROLES, true)
                    ? (string) $_POST['role'] : 'user';

        if ($nom === '' || $prenom === '' || $email === '') {
            $this->addFlash('Nom, prénom et email sont obligatoires.', 'error');
            $this->redirectTo($id === '' ? '/user/new' : '/user/' . rawurlencode($id));
        }

        // -------- Mode création (aucun id) --------
        if ($id === '') {
            $password = (string) ($_POST['password'] ?? '');
            if (strlen($password) < 4) {
                $this->addFlash('Un mot de passe (min. 4 caractères) est requis pour la création.', 'error');
                $this->redirectTo('/user/new');
            }
            if ($this->users->emailExists($email)) {
                $this->addFlash('Cet email est déjà utilisé.', 'error');
                $this->redirectTo('/user/new');
            }

            $newId = $this->users->create([
                'nom'           => $nom,
                'prenom'        => $prenom,
                'email'         => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role'          => $role,
            ]);

            $this->addFlash('Utilisateur créé.', 'success');
            $this->redirectTo('/user/' . rawurlencode($newId));
        }

        // -------- Mode édition (id présent) --------
        $current = $this->users->findById($id);
        if ($current === null) {
            $this->addFlash('Utilisateur introuvable.', 'error');
            $this->redirectTo('/users');
        }

        // Le super-admin ne peut pas se rétrograder lui-même (garantie d'accès).
        $selfId = (string) (current_user()['id'] ?? '');
        if ($id === $selfId && $role !== 'admin') {
            $this->addFlash('Vous ne pouvez pas retirer votre propre rôle d\'administrateur.', 'error');
            $this->redirectTo('/user/' . rawurlencode($id));
        }

        if ($this->users->emailExists($email, $id)) {
            $this->addFlash('Cet email est déjà utilisé par un autre compte.', 'error');
            $this->redirectTo('/user/' . rawurlencode($id));
        }

        $this->users->update(['id' => $id, 'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'role' => $role]);
        $this->addFlash('Utilisateur mis à jour.', 'success');
        $this->redirectTo('/users');
    }

    /**
     * POST /user/delete/{id} — Supprime un utilisateur (grant : admin).
     *
     * Interdit de se supprimer soi-même (évite de verrouiller le compte courant).
     *
     * @param string $id UUID de l'utilisateur.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);

        $selfId = (string) (current_user()['id'] ?? '');
        if ($id === $selfId) {
            $this->addFlash('Vous ne pouvez pas supprimer votre propre compte.', 'error');
            $this->redirectTo('/users');
        }

        if ($this->users->delete($id)) {
            $this->addFlash('Utilisateur supprimé (et ses données associées).', 'success');
        } else {
            $this->addFlash('Suppression impossible : utilisateur introuvable.', 'error');
        }
        $this->redirectTo('/users');
    }
}
