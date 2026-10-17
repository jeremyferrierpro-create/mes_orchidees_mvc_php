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

use App\Entity\Notification;
use App\Entity\Soin;
use App\Repository\CollectionRepository;
use App\Repository\NotificationRepository;
use App\Repository\SoinRepository;

class SoinController extends AbstractController
{
    /** Types de soin reconnus (sinon repli sur 'autre'). */
    private const TYPES = ['arrosage', 'engrais', 'rempotage', 'substrat', 'ravageurs'];

    /** Décalage (jours) du rappel automatique RG3 après un soin enregistré. */
    private const RAPPORT_JOURS_RAPPEL = 7;

    private SoinRepository $soins;
    private NotificationRepository $notifications;
    private CollectionRepository $collections;

    public function __construct()
    {
        parent::__construct();
        $this->soins        = new SoinRepository();
        $this->notifications = new NotificationRepository();
        $this->collections  = new CollectionRepository();
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

        // RG3 — rappel automatique : à chaque soin enregistré, on programme une
        // notification de rappel pour le prochain soin à J+7, destinée au propriétaire.
        $this->genererRappelSoin($uid, $collectionId, $soin);

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

    /**
     * RG3 — génère la notification de rappel du prochain soin (J+7) pour le propriétaire.
     *
     * Le destinataire est l'utilisateur connecté (RG1 : on ne notifie jamais un tiers) ;
     * la notification est rattachée à l'entrée de collection concernée. Toute erreur de
     * jointure (plante introuvable) est tolérée : le soin est déjà enregistré.
     *
     * @param string $uid          Propriétaire (session).
     * @param string $collectionId Entrée de collection.
     * @param Soin   $soin         Le soin vient d'être enregistré.
     * @return void
     */
    private function genererRappelSoin(string $uid, string $collectionId, Soin $soin): void
    {
        $plante   = $this->collections->findByIdForUser($collectionId, $uid);
        $nomPlante = $plante?->displayName() ?? 'votre plante';

        $dateSoin = $soin->soinDate !== '' ? $soin->soinDate : date('Y-m-d');
        $nextDate = date('d/m/Y', strtotime($dateSoin . ' +' . self::RAPPORT_JOURS_RAPPEL . ' days'));

        $message = sprintf(
            'Rappel automatique : prochain soin (%s) prévu le %s pour %s.',
            $soin->typeLabel(),
            $nextDate,
            $nomPlante
        );

        $notif = new Notification();
        $notif->userId       = $uid;
        $notif->collectionId = $collectionId;
        $notif->type         = 'rappel_soin';
        $notif->message      = $message;
        $notif->isRead       = false;
        $notif->notifDate    = date('Y-m-d H:i:s');

        $this->notifications->create($notif);
    }
}
