<?php
/**
 * Contrôleur du back-office d'administration (rôle admin) — Phase 03, incrément 5.
 *
 * Tableau de bord de la plateforme : statistiques agrégées (StatsRepository), accès
 * aux zones de gestion (utilisateurs via /users, modération via /moderation, CRUD
 * orchidées/conseils). L'accès est gardé côté serveur par le grant ['admin'].
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver), dépendances composées ici.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Repository\OrchidRepository;
use App\Repository\StatsRepository;

class AdminController extends AbstractController
{
    private StatsRepository $stats;
    private OrchidRepository $orchids;

    public function __construct()
    {
        parent::__construct();
        $this->stats   = new StatsRepository();
        $this->orchids = new OrchidRepository();
    }

    /**
     * GET /administration — Tableau de bord admin (grant : admin).
     *
     * @return string HTML rendu.
     */
    public function dashboard(): string
    {
        return $this->render('pages/admin/dashboard', [
            'stats'    => $this->stats->platformStats(),
            'parRole'  => $this->stats->usersByRole(),
            'pending'  => $this->orchids->findUnpublished(),
            'user'     => current_user(),
            'csrf'     => $this->getCsrfToken(),
        ], 'Administration');
    }
}
