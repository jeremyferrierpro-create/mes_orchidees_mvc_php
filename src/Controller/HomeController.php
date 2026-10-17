<?php
/**
 * Contrôleur de la page d'accueil.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

class HomeController extends AbstractController
{
    /**
     * GET / — Page d'accueil publique.
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        return $this->render('pages/home', [], 'MES ORCHIDEES');
    }
}
