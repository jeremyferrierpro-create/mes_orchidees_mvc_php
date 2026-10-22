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
 * Phase C0 : la génération/annulation des rappels est extraite dans
 * `App\Service\RappelService`, partagé avec RappelController (action
 * « Effectuer le soin » qui consigne le soin puis replanifie).
 *
 * @package App\Controller
 * @author  Jérémy Ferrier
 * @version 3.1.0
 * @since   2026-10-18
 */

namespace App\Controller;

use App\Entity\Soin;
use App\Repository\CollectionRepository;
use App\Repository\SoinRepository;
use App\Repository\TypeSoinRepository;
use App\Service\RappelService;

class SoinController extends AbstractController
{
    private SoinRepository $soins;
    private RappelService $rappels;
    private CollectionRepository $collections;
    private TypeSoinRepository $typesSoin;

    public function __construct()
    {
        parent::__construct();
        $this->soins       = new SoinRepository();
        $this->rappels     = new RappelService();
        $this->collections = new CollectionRepository();
        $this->typesSoin   = new TypeSoinRepository();
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

        // RG3 étendu (opt-in) : à l'enregistrement du soin, l'utilisateur choisit
        // explicitement si le prochain rappel doit être programmé. Le choix est
        // persisté sur la plante (collections.rappel_actif).
        // - absent du POST  → activé (rétro-compatibilité : comportement historique)
        // - « 0 » (non coché) → désactivé
        // - toute autre valeur (ex. « 1 » coché) → activé
        $optIn = ((string) ($_POST['rappel_actif'] ?? '1')) !== '0';
        $this->collections->setRappelActifForUser($collectionId, $uid, $optIn);

        if ($optIn) {
            // Rappel automatique : on programme/replanifie le prochain soin (dédupliqué).
            $this->rappels->generer($uid, $collectionId, $soin);
            $this->addFlash('Soin enregistré. Prochain rappel programmé.', 'success');
        } else {
            // Désactivation : aucun nouveau rappel ; un rappel déjà planifié est annulé
            // (statut « ignore ») — la ligne historique est CONSERVÉE, jamais supprimée.
            $this->rappels->annuler($uid, $collectionId);
            $this->addFlash('Soin enregistré. Rappels désactivés pour cette plante.', 'success');
        }
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
        // Le type est soumis sous forme de label (select HTML). On le résout via
        // le vocabulaire fermé `types_soin` ; tout label inconnu replie sur le
        // premier type (« arrosage »), préservant la comportement historique.
        $label   = trim((string) ($_POST['type'] ?? 'arrosage'));
        $typeId  = $this->typesSoin->findIdByLabel($label) ?? $this->typesSoin->defaultId();
        $s->idTypeSoin = $typeId;
        $s->typeLabel  = $label;

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
