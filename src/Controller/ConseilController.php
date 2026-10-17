<?php
/**
 * Contrôleur de la page Conseils (lecture publique).
 *
 * Phase 02 : coquille de page. Le contenu (conseils de catégorie / d'espèce,
 * care cards JSON, recherche FULLTEXT) est un incrément de la Phase 03.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

class ConseilController extends AbstractController
{
    /**
     * GET /conseils — Liste publique des conseils.
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        return $this->render('pages/placeholder', [
            'entite' => 'Conseils',
            'note'   => 'Conseils d\'entretien (ConseilRepository) — Phase 03.',
        ], 'Conseils');
    }
}
