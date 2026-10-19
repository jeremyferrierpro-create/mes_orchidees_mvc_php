<?php
/**
 * Contrôleur des orchidées — CRUD de l'encyclopédie (Phase 03, incrément 1).
 *
 * Gère la lecture publique (liste, recherche, fiche) et l'écriture :
 *  - admin : création / modification / suppression de fiches publiées ;
 *  - utilisateur connecté : proposition d'une nouvelle espèce (non publiée, en attente
 *    de modération).
 *
 * Les endpoints /orchids et /orchids/search renvoient du JSON (API consommée par le
 * front) ; les pages HTML (fiche, formulaires) sont rendues côté serveur via
 * OrchidRepository (MySQL/PDO).
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver : `new ClassName()`), les
 * dépendances sont composées ici (style débutant, cf. AuthController).
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Entity\Orchid;
use App\Repository\OrchidRepository;

class OrchidController extends AbstractController
{
    private OrchidRepository $orchids;

    public function __construct()
    {
        parent::__construct();
        $this->orchids = new OrchidRepository();
    }

    // ---------------------------------------------------------------------
    // LECTURE PUBLIQUE
    // ---------------------------------------------------------------------

    /**
     * GET /orchids — Liste JSON de toutes les orchidées publiées.
     *
     * @return string JSON (tableau d'orchidées).
     */
    public function index(): string
    {
        $list = array_map(fn(Orchid $o) => $o->toArray(), $this->orchids->findAllPublished());
        return $this->json($list);
    }

    /**
     * GET /orchids/search — Recherche JSON (?q= ou ?search=).
     *
     * @return string JSON (tableau de résultats, min. 2 caractères).
     */
    public function search(): string
    {
        $query = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
        $list  = array_map(fn(Orchid $o) => $o->toArray(), $this->orchids->search($query));
        return $this->json($list);
    }

    /**
     * GET /orchid/{id} — Fiche détaillée HTML (publiée uniquement).
     *
     * @param string $id Slug de l'espèce.
     * @return string HTML rendu.
     */
    public function show(string $id): string
    {
        $orchid = $this->orchids->findById($id, true);
        if ($orchid === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Cette orchidée est introuvable ou non publiée.',
            ], 'Orchidée introuvable');
        }

        return $this->render('pages/orchids/show', [
            'orchid'            => $orchid,
            'meta_description'  => $orchid->shortDesc ?? ('Fiche de ' . $orchid->scientificName()),
        ], $orchid->scientificName());
    }

    // ---------------------------------------------------------------------
    // CRÉATION (admin) + PROPOSITION (utilisateur)
    // ---------------------------------------------------------------------

    /**
     * GET /orchid/new — Formulaire de création (admin).
     *
     * @return string HTML rendu.
     */
    public function newForm(): string
    {
        return $this->render('pages/orchids/form', [
            'orchid' => new Orchid(),
            'mode'   => 'create',
            'action' => url('/orchid/new'),
            'csrf'   => $this->getCsrfToken(),
        ], 'Nouvelle orchidée');
    }

    /**
     * POST /orchid/new — Enregistre une fiche PUBLIÉE (admin).
     *
     * @return void (redirection).
     */
    public function create(): void
    {
        $this->assertCsrf($_POST);

        $orchid = $this->fromRequest(new Orchid());
        $orchid->isPublished      = true;              // l'admin publie directement
        $orchid->moderationStatus = 'approved';        // fiche validée d'office
        $orchid->proposedBy  = current_user()['id'] ?? null;

        if ($this->orchids->exists($orchid->id)) {
            $this->addFlash('Une orchidée porte déjà cet identifiant.', 'error');
            $this->redirectTo('/orchid/new');
        }

        $this->orchids->create($orchid);
        $this->addFlash('Orchidée créée.', 'success');
        $this->redirectTo('/orchid/' . rawurlencode($orchid->id));
    }

    /**
     * POST /orchid/propose — Proposition d'une espèce NON PUBLIÉE (utilisateur connecté).
     *
     * La fiche part en attente de modération (is_published = 0), cf. workflow Phase 03+.
     *
     * @return void (redirection).
     */
    public function propose(): void
    {
        $this->assertCsrf($_POST);

        $orchid = $this->fromRequest(new Orchid());
        $orchid->isPublished      = false;             // en attente de validation
        $orchid->moderationStatus = 'pending';         // placée dans la file de modération
        $orchid->proposedBy  = current_user()['id'] ?? null;

        if ($this->orchids->exists($orchid->id)) {
            $this->addFlash('Cette espèce existe déjà dans l\'encyclopédie.', 'error');
            $this->redirectTo('/encyclopedie');
        }

        $this->orchids->create($orchid);
        $this->addFlash('Merci ! Votre proposition sera vérifiée par un modérateur.', 'success');
        $this->redirectTo('/encyclopedie');
    }

    // ---------------------------------------------------------------------
    // MODIFICATION (admin)
    // ---------------------------------------------------------------------

    /**
     * GET /update/orchid/{id} — Formulaire de modification pré-rempli (admin).
     *
     * @param string $id Slug.
     * @return string HTML rendu.
     */
    public function editForm(string $id): string
    {
        // En édition admin, on admet aussi une fiche non publiée (modération).
        $orchid = $this->orchids->findById($id, false);
        if ($orchid === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Orchidée introuvable.',
            ], 'Orchidée introuvable');
        }

        return $this->render('pages/orchids/form', [
            'orchid' => $orchid,
            'mode'   => 'update',
            'action' => url('/update/orchid'),
            'csrf'   => $this->getCsrfToken(),
        ], 'Modifier ' . $orchid->scientificName());
    }

    /**
     * POST /update/orchid — Applique la modification (admin).
     *
     * @return string|null HTML 404 si l'orchidée a disparu sinon redirection (sortie).
     */
    public function update(): ?string
    {
        $this->assertCsrf($_POST);

        $id     = trim((string) ($_POST['id'] ?? ''));
        $current = $this->orchids->findById($id, false);
        if ($current === null) {
            http_response_code(404);
            return $this->render('pages/error', ['status' => 404, 'message' => 'Orchidée introuvable.'], 'Orchidée introuvable');
        }

        $orchid = $this->fromRequest($current);
        // L'admin peut basculer l'état publié via la case "is_published".
        $published = isset($_POST['is_published']);
        $orchid->isPublished      = $published;
        // On synchronise l'état de modération avec la publication (une fiche publiée
        // admin est « approved » ; dépublier la renvoie en « pending »).
        $orchid->moderationStatus = $published ? 'approved' : 'pending';

        $this->orchids->update($orchid);
        $this->addFlash('Orchidée mise à jour.', 'success');
        $this->redirectTo('/orchid/' . rawurlencode($orchid->id));
    }

    // ---------------------------------------------------------------------
    // SUPPRESSION (admin)
    // ---------------------------------------------------------------------

    /**
     * POST /orchid/delete/{id} — Supprime une orchidée (admin).
     *
     * @param string $id Slug.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);

        if ($this->orchids->delete($id)) {
            $this->addFlash('Orchidée supprimée.', 'success');
        } else {
            $this->addFlash('Suppression impossible : orchidée introuvable.', 'error');
        }
        $this->redirectTo('/encyclopedie');
    }

    // ---------------------------------------------------------------------
    // AIDE PRIVÉE : hydrate une entité depuis $_POST
    // ---------------------------------------------------------------------

    /**
     * Remplit une entité Orchid à partir des champs du formulaire.
     *
     * @param Orchid $o   Entité à peupler (nouvelle ou existante).
     * @return Orchid
     */
    private function fromRequest(Orchid $o): Orchid
    {
        $name = trim((string) ($_POST['name'] ?? ''));

        $o->name           = $name !== '' ? mb_strtoupper($name) : $o->name;
        $o->vernacular     = $this->strOrNull($_POST['vernacular'] ?? null);
        $o->botanicalOrder = $this->strOrNull($_POST['botanical_order'] ?? null);
        $o->species        = $this->strOrNull($_POST['species'] ?? null);
        $o->genre          = trim((string) ($_POST['genre'] ?? '')) ?: $o->genre;
        $o->family         = trim((string) ($_POST['family'] ?? '')) ?: 'Orchidaceae';
        $o->subfamily      = $this->strOrNull($_POST['subfamily'] ?? null);
        $o->tribu          = $this->strOrNull($_POST['tribu'] ?? null);
        $o->subtribu       = $this->strOrNull($_POST['subtribu'] ?? null);
        $o->behavior       = $this->strOrNull($_POST['behavior'] ?? null);
        $o->discovered     = $this->strOrNull($_POST['discovered'] ?? null);
        $o->origin         = $this->strOrNull($_POST['origin'] ?? null);
        $o->img            = $this->strOrNull($_POST['img'] ?? null);
        $o->shortDesc      = $this->strOrNull($_POST['short_desc'] ?? null);
        $o->longDesc       = $this->strOrNull($_POST['long_desc'] ?? null);

        // L'id (slug) est dérivé du nom ; en édition on le conserve sinon.
        if ($o->id === '') {
            $o->id = $this->slugify((string) ($_POST['id'] ?? '') !== '' ? $_POST['id'] : $o->name);
        }

        return $o;
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
     * Fabrique un slug d'identifiant à partir d'un nom (minuscules, tiret bas).
     *
     * Ex : "Acacalis Cyanea" → "acacalis_cyanea" (convention héritée du JSON).
     *
     * @param string $text
     * @return string
     */
    private function slugify(string $text): string
    {
        // Translittération simple des accents, puis nettoyage.
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
