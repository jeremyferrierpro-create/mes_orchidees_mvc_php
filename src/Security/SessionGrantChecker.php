<?php
/**
 * Contrôle d'accès basé sur la session PHP.
 *
 * Implémente l'interface GrantCheckerInterface du Mithridatem Router : le routeur
 * appelle isGranted() pour chaque route en lui passant la liste des « grants »
 * (rôles autorisés) déclarés. L'utilisateur courant est lu dans $_SESSION['user'].
 *
 * Sémantique des grants :
 *  - [] (vide)                 → route publique, tout le monde passe.
 *  - ['authenticated', ...]    → accessible à tout utilisateur connecté (n'importe quel rôle).
 *  - ['admin']                 → réservé au rôle 'admin'.
 *  - ['admin', 'moderateur']   → rôle 'admin' OU 'moderateur'.
 *
 * @package App\Security
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Security;

use Mithridatem\Routing\Auth\GrantCheckerInterface;

class SessionGrantChecker implements GrantCheckerInterface
{
    /**
     * Détermine si l'utilisateur de la session a le droit d'accéder à la route.
     *
     * @param array $requiredGrants Rôles autorisés pour la route ([] = public).
     * @return bool Vrai si l'accès est accordé.
     */
    public function isGranted(array $requiredGrants): bool
    {
        // Route publique : aucun grant requis.
        if ($requiredGrants === []) {
            return true;
        }

        // Utilisateur connecté ? (mis en place par AuthService dans $_SESSION['user'])
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || empty($user['role'])) {
            return false; // non authentifié → accès refusé
        }

        // Grant spécial "authenticated" : tout utilisateur connecté est accepté.
        if (in_array('authenticated', $requiredGrants, true)) {
            return true;
        }

        // Sinon, le rôle de l'utilisateur doit figurer dans la liste des grants requis.
        return in_array($user['role'], $requiredGrants, true);
    }
}
