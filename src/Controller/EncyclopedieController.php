<?php
/**
 * Contrôleur de la page Encyclopédie (lecture publique) — Phase 03, incrément 1.
 *
 * Rend côté serveur la grille des orchidées publiées via OrchidRepository (MySQL).
 * Si un terme de recherche est présent (?search= ou ?q=), on filtre avec l'index
 * FULLTEXT + repli LIKE du repository. Le rendu HTML remplace le peuplement JS
 * (app.js) d'origine : la page est désormais servie dynamiquement.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Repository\OrchidRepository;

class EncyclopedieController extends AbstractController
{
    private OrchidRepository $orchids;

    public function __construct()
    {
        parent::__construct();
        $this->orchids = new OrchidRepository();
    }

    /**
     * GET /encyclopedie — Liste publique (et recherche) des orchidées.
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        $query = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));

        // Sans terme : tout le catalogue publié ; avec terme : résultats filtrés.
        $orchids = $query === ''
            ? $this->orchids->findAllPublished()
            : $this->orchids->search($query);

        return $this->render('pages/encyclopedie', [
            'orchids'           => $orchids,
            'query'             => $query,
            'csrf'              => $this->getCsrfToken(),
            'meta_description'  => 'Encyclopédie botanique des orchidées : recherchez des espèces, découvrez leurs caractéristiques, origines et conseils de culture.',
            'meta_keywords'     => 'encyclopédie orchidées, botanique, espèces orchidées, recherche orchidées, caractéristiques',
        ], 'Encyclopédie');
    }
}
