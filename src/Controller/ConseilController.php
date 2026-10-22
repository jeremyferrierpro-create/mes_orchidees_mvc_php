<?php
/**
 * Contrôleur des conseils de culture — CRUD (Phase 03, incrément 2).
 *
 * Gère la lecture publique (liste /conseils, fiche /conseil/{id}, recherche) et
 * l'écriture réservée à l'admin : création, modification, suppression de fiches
 * conseils (catégories générales ou fiches par espèce).
 *
 * Les pages HTML sont rendues côté serveur via ConseilRepository (MySQL/PDO).
 * Constructeur SANS ARGUMENT (DefaultControllerResolver : `new ClassName()`), les
 * dépendances sont composées ici (style débutant, cf. OrchidController).
 *
 * @package App\Controller
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Entity\Conseil;
use App\Repository\CategorieConseilRepository;
use App\Repository\ConseilRepository;

class ConseilController extends AbstractController
{
    /** Clés de la carte de soins (care_cards) attendues du formulaire. */
    private const CARE_KEYS = ['temperature', 'arrosage', 'hygrometrie', 'rempotage', 'engrais', 'substrats'];

    private ConseilRepository $conseils;
    private CategorieConseilRepository $categories;

    public function __construct()
    {
        parent::__construct();
        $this->conseils   = new ConseilRepository();
        $this->categories = new CategorieConseilRepository();
    }

    // ---------------------------------------------------------------------
    // LECTURE PUBLIQUE
    // ---------------------------------------------------------------------

    /**
     * GET /conseils — Liste publique des conseils (et recherche ?search=).
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        $query = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));

        $conseils = $query === ''
            ? $this->conseils->findAll()
            : $this->conseils->search($query);

        return $this->render('pages/conseils/index', [
            'conseils'         => $conseils,
            'query'            => $query,
            'meta_description' => 'Conseils de culture des orchidées : arrosage, lumière, substrat, rempotage et fiches par espèce.',
        ], 'Conseils');
    }
    /**
     * GET /conseil/{id} — Fiche conseil détaillée (publique).
     *
     * @param string $id Slug du conseil.
     * @return string HTML rendu.
     */
    public function show(string $id): string
    {
        $conseil = $this->conseils->findById($id);
        if ($conseil === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Ce conseil est introuvable.',
            ], 'Conseil introuvable');
        }

        return $this->render('pages/conseils/show', [
            'conseil'           => $conseil,
            'meta_description'  => $conseil->category ?: $conseil->name,
        ], $conseil->name);
    }

    // ---------------------------------------------------------------------
    // CRÉATION (admin)
    // ---------------------------------------------------------------------

    /**
     * GET /conseil/new — Formulaire de création (admin).
     *
     * @return string HTML rendu.
     */
    public function newForm(): string
    {
        return $this->render('pages/conseils/form', [
            'conseil'    => new Conseil(),
            'mode'       => 'create',
            'action'     => url('/conseil/new'),
            'csrf'       => $this->getCsrfToken(),
            'categories' => $this->categories->findAll(),
        ], 'Nouveau conseil');
    }

    /**
     * POST /conseil/new — Enregistre un conseil (admin).
     *
     * @return void (redirection).
     */
    public function create(): void
    {
        $this->assertCsrf($_POST);

        $conseil = $this->fromRequest(new Conseil());

        if ($this->conseils->exists($conseil->id)) {
            $this->addFlash('Un conseil porte déjà cet identifiant.', 'error');
            $this->redirectTo('/conseil/new');
        }

        $this->conseils->create($conseil);
        $this->addFlash('Conseil créé.', 'success');
        $this->redirectTo('/conseil/' . rawurlencode($conseil->id));
    }

    // ---------------------------------------------------------------------
    // MODIFICATION (admin)
    // ---------------------------------------------------------------------

    /**
     * GET /update/conseil/{id} — Formulaire de modification pré-rempli (admin).
     *
     * @param string $id Slug.
     * @return string HTML rendu.
     */
    public function editForm(string $id): string
    {
        $conseil = $this->conseils->findById($id);
        if ($conseil === null) {
            http_response_code(404);
            return $this->render('pages/error', ['status' => 404, 'message' => 'Conseil introuvable.'], 'Conseil introuvable');
        }

        return $this->render('pages/conseils/form', [
            'conseil'    => $conseil,
            'mode'       => 'update',
            'action'     => url('/update/conseil'),
            'csrf'       => $this->getCsrfToken(),
            'categories' => $this->categories->findAll(),
        ], 'Modifier ' . $conseil->name);
    }

    /**
     * POST /update/conseil — Applique la modification (admin).
     *
     * @return string|null HTML 404 si le conseil a disparu sinon redirection (sortie).
     */
    public function update(): ?string
    {
        $this->assertCsrf($_POST);

        $id      = trim((string) ($_POST['id'] ?? ''));
        $current = $this->conseils->findById($id);
        if ($current === null) {
            http_response_code(404);
            return $this->render('pages/error', ['status' => 404, 'message' => 'Conseil introuvable.'], 'Conseil introuvable');
        }

        $this->conseils->update($this->fromRequest($current));
        $this->addFlash('Conseil mis à jour.', 'success');
        $this->redirectTo('/conseil/' . rawurlencode($current->id));

        return null; // redirectTo() termine le script (exit) ; retour pour la cohérence du type.
    }

    // ---------------------------------------------------------------------
    // SUPPRESSION (admin)
    // ---------------------------------------------------------------------

    /**
     * POST /conseil/delete/{id} — Supprime un conseil (admin).
     *
     * @param string $id Slug.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);

        if ($this->conseils->delete($id)) {
            $this->addFlash('Conseil supprimé.', 'success');
        } else {
            $this->addFlash('Suppression impossible : conseil introuvable.', 'error');
        }
        $this->redirectTo('/conseils');
    }

    // ---------------------------------------------------------------------
    // AIDE PRIVÉE : hydrate une entité depuis $_POST
    // ---------------------------------------------------------------------

    /**
     * Remplit une entité Conseil à partir des champs du formulaire.
     *
     * @param Conseil $c Entité à peupler (nouvelle ou existante).
     * @return Conseil
     */
    private function fromRequest(Conseil $c): Conseil
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $type = (($_POST['type'] ?? 'category') === 'species') ? 'species' : 'category';

        $c->type     = $type;
        $c->name     = $name !== '' ? $name : $c->name;
        // La catégorie est soumise sous forme de label (select HTML + champ texte
        // optionnel pour un nouveau libellé). Vocabulaire ouvert : on trouve ou on
        // crée la référence dans `categories_conseil`.
        $labelCategorie = $this->strOrNull($_POST['category'] ?? null);
        if ($labelCategorie !== null) {
            $c->idCategorie = $this->categories->findOrCreateLabel($labelCategorie);
            $c->category     = $labelCategorie;
        } else {
            $c->idCategorie = null;
            $c->category     = null;
        }
        $c->img      = $this->strOrNull($_POST['img'] ?? null);
        $c->content  = $this->strOrNull($_POST['content'] ?? null);

        // Fiche liée à une espèce : orchid_id optionnel (sinon null pour 'category').
        $c->orchidId = $type === 'species' ? $this->strOrNull($_POST['orchid_id'] ?? null) : null;

        // Carte de soins : on ne conserve que les clés renseignées.
        $cards = [];
        foreach (self::CARE_KEYS as $key) {
            $val = $this->strOrNull($_POST['care_' . $key] ?? null);
            if ($val !== null) {
                $cards[$key] = $val;
            }
        }
        $c->careCards = $cards;

        // L'id (slug) : conservé en édition, sinon dérivé du formulaire / du nom.
        if ($c->id === '') {
            $posted = trim((string) ($_POST['id'] ?? ''));
            $c->id  = $this->slugify($posted !== '' ? $posted : ($type === 'species' && $c->orchidId ? 'fiche-' . $c->orchidId : $c->name));
        }

        return $c;
    }

    /**
     * Retourne la valeur trimée, ou null si chaîne vide.
     *
     * @param mixed $value
     * @return string|null
     */
    private function strOrNull(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        return $value === '' ? null : $value;
    }

    /**
     * Fabrique un slug d'identifiant à partir d'un texte (minuscules, tiret bas).
     *
     * @param string $text
     * @return string
     */
    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = strtr($slug, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'ñ' => 'n', 'ÿ' => 'y',
        ]);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        return trim((string) $slug, '_');
    }
}
