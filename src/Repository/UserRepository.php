<?php
/**
 * Implémentation PDO/MySQL du repository des utilisateurs.
 *
 * Phase 02 : fournit UNIQUEMENT le sous-ensemble d'accès requis par
 * l'authentification (findById, findByEmail, create). Le CRUD complet
 * (liste, mise à jour, suppression, modération) sera ajouté aux incréments
 * de la Phase 03.
 *
 * Toutes les requêtes utilisent des requêtes préparées (aucune concaténation
 * de valeurs utilisateur) — protection contre les injections SQL.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

use PDO;

class UserRepository implements UserRepositoryInterface
{
    /** Connexion injectée ou résolue paresseusement au premier usage. */
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        // Injection de la connexion ; si absente, on ne la résout QU'AU PREMIER USAGE
        // (lazy) : afficher un formulaire de connexion ne doit pas ouvrir la BDD.
        $this->db = $db;
    }

    /**
     * Retourne la connexion PDO, en l'ouvrant à la demande (lazy).
     *
     * @return PDO
     */
    private function pdo(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }
        // Fabrique globale définie dans config/db.php.
        $this->db = getDb();
        return $this->db;
    }

    /**
     * {@inheritDoc}
     */
    public function findById(string $id): ?array
    {
        $sql  = 'SELECT id, nom, prenom, email, role, created_at, updated_at
                   FROM users WHERE id = :id LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * {@inheritDoc}
     *
     * Retourne la ligne COMPLÈTE (y compris password_hash) pour la vérification.
     */
    public function findByEmail(string $email): ?array
    {
        $sql  = 'SELECT id, nom, prenom, email, password_hash, role
                   FROM users WHERE email = :email LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * {@inheritDoc}
     *
     * L'id est un UUID généré côté PHP pour rester indépendant du SGBD.
     */
    public function create(array $user): string
    {
        $id = $this->uuid4();

        $sql = 'INSERT INTO users (id, nom, prenom, email, password_hash, role)
                  VALUES (:id, :nom, :prenom, :email, :password_hash, :role)';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':id'            => $id,
            ':nom'           => $user['nom'],
            ':prenom'        => $user['prenom'],
            ':email'         => $user['email'],
            ':password_hash' => $user['password_hash'],
            ':role'          => $user['role'] ?? 'user',
        ]);

        return $id;
    }

    /**
     * Liste complète des utilisateurs (back-office admin), tri par nom puis prénom.
     *
     * N'expose JAMAIS le hash de mot de passe.
     *
     * @return array[]
     */
    public function findAll(): array
    {
        $sql = 'SELECT id, nom, prenom, email, role, created_at, updated_at
                  FROM users ORDER BY nom ASC, prenom ASC';
        return $this->pdo()->query($sql)->fetchAll();
    }

    /**
     * Met à jour un utilisateur (nom, prénom, email, rôle) par id.
     *
     * Le mot de passe n'est jamais touché ici (réinitialisation gérée ailleurs).
     *
     * @param array $user Champs : id + (nom, prenom, email, role).
     * @return bool True si une ligne a été modifiée.
     */
    public function update(array $user): bool
    {
        $sql = 'UPDATE users
                   SET nom = :nom, prenom = :prenom, email = :email, role = :role
                 WHERE id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':nom'    => $user['nom'],
            ':prenom' => $user['prenom'],
            ':email'  => $user['email'],
            ':role'   => $user['role'] ?? 'user',
            ':id'     => $user['id'],
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime un utilisateur par id (CASCADE : collections/notifications associées).
     *
     * @param string $id UUID de l'utilisateur.
     * @return bool True si une ligne a été supprimée.
     */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Un email est-il déjà pris ? (contrôle d'unicité à la création/édition admin).
     *
     * @param string $email Email à tester.
     * @param string|null $excludeId Id à ignorer (édition de soi-même).
     * @return bool
     */
    public function emailExists(string $email, ?string $excludeId = null): bool
    {
        // La condition d'exclusion doit précéder LIMIT 1 : concaténée APRÈS le LIMIT,
        // elle produisait une requête invalide (erreur 1064) en mode édition.
        $sql    = 'SELECT 1 FROM users WHERE email = :email';
        $params = [':email' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :eid';
            $params[':eid'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Identifiants (UUID) des utilisateurs portant l'un des rôles demandés.
     *
     * Sert à notifier les modérateurs/administrateurs lors d'une nouvelle
     * proposition d'orchidée. Les rôles sont validés par une liste blanche
     * (aucune valeur client injectée).
     *
     * @param string[] $roles Rôles ciblés (ex : ['admin', 'moderateur']).
     * @return string[] Liste d'UUID (vide si aucun destinataire).
     */
    public function findIdsByRole(array $roles): array
    {
        $allowed = ['user', 'admin', 'moderateur'];
        $roles   = array_values(array_intersect($roles, $allowed));
        if ($roles === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql    = "SELECT id FROM users WHERE role IN ($placeholders)";
        $stmt   = $this->pdo()->prepare($sql);
        $stmt->execute($roles);
        return array_map(static fn($r) => (string) $r, $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Génère un UUID v4 (122 bits aléatoires + versions/variante).
     *
     * @return string UUID au format 8-4-4-4-12.
     */
    private function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); // version 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80); // variante
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
