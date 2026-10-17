<?php
/**
 * Contrôleur « Ma Collection » (espace privé de l'utilisateur connecté).
 *
 * Phase 02 : coquille de page (tableau de bord). Le CRUD collection + soins +
 * notifications et l'isolation stricte par propriétaire (RG1, filtre user_id au
 * Repository) seront implémentés en Phase 03.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

class CollectionController extends AbstractController
{
    /**
     * GET /macollection — Tableau de bord de la collection (grant : authentifié).
     *
     * @return string HTML rendu.
     */
    public function dashboard(): string
    {
        return $this->render('pages/placeholder', [
            'entite' => 'Ma Collection',
            'note'   => 'Tableau de bord (CollectionRepository, isolation RG1) — Phase 03.',
        ], 'Ma Collection');
    }
}
