<?php
/**
 * Contrôleur du back-office d'administration (rôle admin).
 *
 * Phase 02 : coquille de page. CRUD orchidées/conseils, gestion des utilisateurs
 * et statistiques relèvent des incréments Phase 03. L'accès est déjà gardé côté
 * serveur par le grant ['admin'] (GrantCheckerInterface).
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

class AdminController extends AbstractController
{
    /**
     * GET /administration — Tableau de bord admin (grant : admin).
     *
     * @return string HTML rendu.
     */
    public function dashboard(): string
    {
        return $this->render('pages/placeholder', [
            'entite' => 'Administration',
            'note'   => 'Back-office (CRUD, utilisateurs, stats) — Phase 03.',
        ], 'Administration');
    }
}
