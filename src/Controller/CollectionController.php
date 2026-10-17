<?php
/**
 * Contrôleur « Ma Collection » — espace privé de l'utilisateur connecté (Phase 03, incrément 3).
 *
 * Gère le tableau de bord (/macollection), la liste JSON (/collections), la fiche
 * d'une plante (/collection/{id}) et l'ajout / modification / suppression d'une entrée.
 *
 * ISOLATION RG1 : le propriétaire est TOUJOURS dérivé de la session
 * (current_user()['id']) et JAMAIS d'une donnée soumise par le client. Toutes les
 * requêtes passent par des méthodes *ForUser du repository qui filtrent sur user_id.
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver), dépendances composées ici.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Entity\Collection;
use App\Repository\CollectionRepository;
use App\Repository\OrchidRepository;
use App\Repository\SoinRepository;

class CollectionController extends AbstractController
{
    private CollectionRepository $collections;
    private OrchidRepository $orchids;
    private SoinRepository $soins;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new CollectionRepository();
        $this->orchids     = new OrchidRepository();
        $this->soins       = new SoinRepository();
    }

    /**
     * Identifiant de l'utilisateur connecté (rg1). Le grant 'authenticated' de la
     * route garantit la présence de la session ; on retourne '' si absent (défense).
     *
     * @return string
     */
    private function currentUserId(): string
    {
        return (string) (current_user()['id'] ?? '');
    }

    // ---------------------------------------------------------------------
    // TABLEAU DE BORD (HTML)
    // ---------------------------------------------------------------------

    /**
     * GET /macollection — Tableau de bord de la collection (grant : authentifié).
     *
     * @return string HTML rendu.
     */
    public function dashboard(): string
    {
        $uid        = $this->currentUserId();
        $collection = $this->collections->findByUser($uid);
        $user = current_user();

        return $this->render('pages/collection/dashboard', [
            'collection'       => $collection,
            'orchids'          => $this->orchids->findAllPublished(),
            'nbPlantes'        => count($collection),
            'user'             => $user,
            'csrf'             => $this->getCsrfToken(),
            'meta_description' => 'Votre collection personnelle d\'orchidées : plantes, conditions de culture et historique de soins.',
        ], 'Ma Collection');
    }

    /**
     * GET /collections — Liste JSON des plantes de l'utilisateur (grant : authentifié).
     *
     * @return string JSON.
     */
    public function index(): string
    {
        $uid  = $this->currentUserId();
        $list = array_map(fn(Collection $c) => $c->toArray(), $this->collections->findByUser($uid));
        return $this->json($list);
    }

    /**
     * GET /collection/{id} — Fiche d'une plante + historique de soins (grant : authentifié).
     *
     * RG1 : findByIdForUser() renvoie null si la plante appartient à un autre utilisateur.
     *
     * @param string $id UUID de l'entrée.
     * @return string HTML rendu.
     */
    public function show(string $id): string
    {
        $uid  = $this->currentUserId();
        $item = $this->collections->findByIdForUser($id, $uid);
        if ($item === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Cette plante est introuvable dans votre collection.',
            ], 'Plante introuvable');
        }

        return $this->render('pages/collection/show', [
            'item'  => $item,
            'soins' => $this->soins->findByCollectionForUser($item->id, $uid),
            'csrf'  => $this->getCsrfToken(),
        ], $item->displayName());
    }

    // ---------------------------------------------------------------------
    // AJOUT
    // ---------------------------------------------------------------------

    /**
     * POST /collection/new — Ajoute une espèce à la collection de l'utilisateur.
     *
     * @return void (redirection).
     */
    public function create(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $orchidId = trim((string) ($_POST['orchid_id'] ?? ''));
        if ($orchidId === '' || $this->orchids->findById($orchidId, true) === null) {
            $this->addFlash('Choisissez une espèce valide et publiée.', 'error');
            $this->redirectTo('/macollection');
        }
        if ($this->collections->existsForUser($uid, $orchidId)) {
            $this->addFlash('Cette espèce est déjà dans votre collection.', 'error');
            $this->redirectTo('/macollection');
        }

        $c = new Collection();
        $c->userId     = $uid;          // TOUJOURS l'utilisateur de la session (jamais le POST) — RG1.
        $c->orchidId   = $orchidId;
        $this->fillFields($c);

        $newId = $this->collections->create($c);
        $this->addFlash('Plante ajoutée à votre collection.', 'success');
        $this->redirectTo('/collection/' . rawurlencode($newId));
    }

    // ---------------------------------------------------------------------
    // MODIFICATION
    // ---------------------------------------------------------------------

    /**
     * POST /update/collection — Met à jour une entrée de la collection (RG1).
     *
     * @return void (redirection).
     */
    public function update(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $id     = trim((string) ($_POST['id'] ?? ''));
        $current = $this->collections->findByIdForUser($id, $uid);
        if ($current === null) {
            $this->addFlash('Modification impossible : plante introuvable dans votre collection.', 'error');
            $this->redirectTo('/macollection');
        }

        $orchidId = trim((string) ($_POST['orchid_id'] ?? ''));
        if ($orchidId !== '' && $this->orchids->findById($orchidId, true) !== null) {
            $current->orchidId = $orchidId;
        }
        $this->fillFields($current);

        $this->collections->updateForUser($current, $uid);
        $this->addFlash('Plante mise à jour.', 'success');
        $this->redirectTo('/collection/' . rawurlencode($current->id));
    }

    // ---------------------------------------------------------------------
    // SUPPRESSION
    // ---------------------------------------------------------------------

    /**
     * POST /collection/delete/{id} — Retire une plante de la collection (RG1).
     *
     * @param string $id UUID.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        if ($this->collections->deleteForUser($id, $uid)) {
            $this->addFlash('Plante retirée de votre collection.', 'success');
        } else {
            $this->addFlash('Suppression impossible : plante introuvable.', 'error');
        }
        $this->redirectTo('/macollection');
    }

    // ---------------------------------------------------------------------
    // AIDE PRIVÉE : hydrate les champs libres depuis $_POST
    // ---------------------------------------------------------------------

    /**
     * Renseigne les champs contextuels d'une entrée depuis le formulaire.
     *
     * @param Collection $c
     * @return void
     */
    private function fillFields(Collection $c): void
    {
        $c->location    = $this->strOrNull($_POST['location'] ?? null);
        $c->notes       = $this->strOrNull($_POST['notes'] ?? null);
        $c->temp        = $this->strOrNull($_POST['temp'] ?? null);
        $c->hygro       = $this->strOrNull($_POST['hygro'] ?? null);
        $c->light       = $this->strOrNull($_POST['light'] ?? null);
        $c->ventilation = $this->strOrNull($_POST['ventilation'] ?? null);
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
}
