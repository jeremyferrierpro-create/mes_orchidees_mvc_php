<?php
/**
 * Contrat du repository des utilisateurs.
 *
 * Définit le « port » d'accès aux données de la table `users`. L'implémentation
 * concrète (PDO/MySQL) sera fournie lors des incréments CRUD (Phase 03). Le
 * service d'authentification dépend de ce contrat, pas d'une classe concrète.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

interface UserRepositoryInterface
{
    /**
     * Retourne un utilisateur par son identifiant, ou null.
     *
     * @param string $id Identifiant (UUID).
     * @return array|null Ligne utilisateur ou null.
     */
    public function findById(string $id): ?array;

    /**
     * Retourne un utilisateur par son email, ou null.
     *
     * @param string $email Adresse email.
     * @return array|null Ligne utilisateur (avec password_hash) ou null.
     */
    public function findByEmail(string $email): ?array;

    /**
     * Insère un utilisateur (mot de passe DÉJÀ haché) et retourne son id.
     *
     * @param array $user Champs : nom, prenom, email, password_hash, role.
     * @return string Identifiant généré/stocké.
     */
    public function create(array $user): string;
}
