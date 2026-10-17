<?php
/**
 * Contrôleur des pages de contenu statiques (mentions, confidentialité, accessibilité).
 *
 * Ces pages n'ont pas de logique métier : elles rendent une vue statique. Elles
 * illustrent le motif "page publique" sans dépendance à la base de données.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

class PageController extends AbstractController
{
    /**
     * GET /confidentialite — Politique de confidentialité (RGPD).
     *
     * @return string HTML rendu.
     */
    public function confidentialite(): string
    {
        return $this->render('pages/confidentialite', [], 'Confidentialité');
    }

    /**
     * GET /mentions — Mentions légales.
     *
     * @return string HTML rendu.
     */
    public function mentions(): string
    {
        return $this->render('pages/mentions', [], 'Mentions légales');
    }

    /**
     * GET /accessibilite — Déclaration d'accessibilité.
     *
     * @return string HTML rendu.
     */
    public function accessibilite(): string
    {
        return $this->render('pages/accessibilite', [], 'Accessibilité');
    }
}
