<?php
/**
 * Contrôleur des soins — historique de culture d'une plante (Phase 03, incrément 3).
 *
 * Un soin est rattaché à une ENTRÉE DE COLLECTION. L'accès (lecture / écriture /
 * suppression) est donc conditionné à la propriété de cette entrée : on vérifie
 * systématiquement que la collection appartient à l'utilisateur connecté (RG1),
 * directement dans le repository (jointure `collections.user_id = :uid`).
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver), dépendance composée ici.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Entity\Soin;
use App\Repository\SoinRepository;

class SoinController extends AbstractController
{
    /** Types de soin reconnus (sinon repli sur 'autre'). */
    private const TYPES = ['arrosage', 'engrais', 'rempotage', 'substrat', 'ravageurs'];

    private SoinRepository $soins;

    public function __construct()
    {
        parent::__construct();
        $this->soins = new SoinRepository();
    }

    /**
     * Identifiant de l'utilisateur connecté (RG1).
     *
     * @return string
     */
    private function currentUserId(): string
    {
        return (string) (current_user()['id'] ?? '');
    }

    /**
     * GET /soins — Historique JSON des soins de l'utilisateur (grant : authentifié).
     *
     * @return string JSON.
     */
    public function index(): string
    {
        $uid  = $this->currentUserId();
        $list = array_map(fn(Soin $s) => $s->toArray(), $this->soins->findByUser($uid));
        return $this->json($list);
    }

    /**
     * POST /soin/new — Enregistre un soin pour une plante de l'utilisateur (RG1).
     *
     * @return void (redirection).
     */
    public function create(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $collectionId = trim((string) ($_POST['collection_id'] ?? ''));
        if ($collectionId === '' || !$this->soins->collectionOwnedBy($collectionId, $uid)) {
            $this->addFlash('Ajout impossible : cette plante n\'appartient pas à votre collection.', 'error');
            $this->redirectTo('/macollection');
        }

        $soin = $this->fromRequest(new Soin());
        $soin->collectionId = $collectionId;

        $this->soins->create($soin);
        $this->addFlash('Soin enregistré.', 'success');
        $this->redirectTo('/collection/' . rawurlencode($collectionId));
    }

    /**
     * POST /update/soin — Met à jour un soin (RG1 : via propriété de la collection).
     *
     * @return void (redirection).
     */
    public function update(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $id     = trim((string) ($_POST['id'] ?? ''));
        $current = $this->soins->findByIdForUser($id, $uid);
        if ($current === null) {
            $this->addFlash('Modification impossible : soin introuvable.', 'error');
            $this->redirectTo('/macollection');
        }

        $soin = $this->fromRequest($current);
        $soin->collectionId = $current->collectionId; // inchangé (propriété déjà vérifiée).

        $this->soins->updateForUser($soin, $uid);
        $this->addFlash('Soin mis à jour.', 'success');
        $this->redirectTo('/collection/' . rawurlencode($current->collectionId));
    }

    /**
     * POST /soin/delete/{id} — Supprime un soin (RG1 : via propriété de la collection).
     *
     * @param string $id UUID du soin.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        // Le contrôleur relit l'appartenance avant suppression (double garde, RG1).
        $current = $this->soins->findByIdForUser($id, $uid);
        if ($current !== null && $this->soins->deleteForUser($id, $uid)) {
            $this->addFlash('Soin supprimé.', 'success');
            $this->redirectTo('/collection/' . rawurlencode($current->collectionId));
        }

        $this->addFlash('Suppression impossible : soin introuvable.', 'error');
        $this->redirectTo('/macollection');
    }

    // ---------------------------------------------------------------------
    // AIDE PRIVÉE
    // ---------------------------------------------------------------------

    /**
     * Remplit une entité Soin à partir des champs du formulaire de soin.
     *
     * @param Soin $s Entité à peupler (nouvelle ou existante).
     * @return Soin
     */
    private function fromRequest(Soin $s): Soin
    {
        $type = trim((string) ($_POST['type'] ?? 'arrosage'));
        $s->type = in_array($type, self::TYPES, true) ? $type : 'arrosage';

        $date = trim((string) ($_POST['soin_date'] ?? ''));
        // Format ISO accepté ; sinon date du jour (colonne NOT NULL).
        $s->soinDate = $date !== '' ? $date : date('Y-m-d');

        $s->notes = $this->strOrNull($_POST['notes'] ?? null);

        $s->engrais   = isset($_POST['engrais']);
        $s->substrat  = isset($_POST['substrat']);
        $s->ravageurs = isset($_POST['ravageurs']);

        return $s;
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
