<?php
/**
 * Contrôleur de modération — workflow de validation des propositions (Phase 03, incrément 5).
 *
 * Réservé aux rôles `admin` ET `moderateur` (grant GRANT_MODO). Liste les orchidées
 * PROPOSÉES PAR LES UTILISATEURS et non encore publiées (`is_published = 0`), puis
 * permet de les approuver ou de les refuser.
 *
 * RG5 — notification de l'auteur : chaque décision (validation ou refus) envoie une
 * notification à l'utilisateur à l'origine de la proposition (`orchids.proposed_by`).
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver).
 *
 * @package App\Controller
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Repository\OrchidRepository;

class ModerationController extends AbstractController
{
    private OrchidRepository $orchids;
    private NotificationRepository $notifications;

    public function __construct()
    {
        parent::__construct();
        $this->orchids       = new OrchidRepository();
        $this->notifications = new NotificationRepository();
    }

    /**
     * GET /moderation — Liste des propositions en attente (grant : admin/modérateur).
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        return $this->render('pages/moderation/index', [
            'pending' => $this->orchids->findUnpublished(),
            'nbEnAttente' => $this->orchids->countUnpublished(),
            'csrf'    => $this->getCsrfToken(),
        ], 'Modération');
    }

    /**
     * POST /moderation/{id}/approve — Publie la proposition et notifie l'auteur (RG5).
     *
     * @param string $id Slug de l'orchidée proposée.
     * @return void (redirection).
     */
    public function approve(string $id): void
    {
        $this->assertCsrf($_POST);

        $orchid = $this->orchids->findById($id, false);
        if ($orchid === null) {
            $this->addFlash('Proposition introuvable.', 'error');
            $this->redirectTo('/moderation');
        }

        $this->orchids->setModerationStatus($orchid->id, 'approved');

        // RG5 : confirmation de validation envoyée à l'auteur de la proposition.
        $this->notifierAuteur(
            $orchid->proposedBy,
            'validation',
            sprintf('Votre proposition « %s » a été validée et publiée dans l\'encyclopédie. Merci !', $orchid->scientificName())
        );

        $this->addFlash('Proposition approuvée et auteur notifié.', 'success');
        $this->redirectTo('/moderation');
    }

    /**
     * POST /moderation/{id}/reject — Refuse la proposition et notifie l'auteur (RG5).
     *
     * REFUS SOFT : la fiche n'est PAS supprimée. Elle reste acquise au propriétaire
     * qui l'a ajoutée à sa collection (la contrainte RESTRICT de collections.orchid_id
     * l'exige tout autant que le cahier des charges). On la marque « rejected » et on
     * la dépublie : elle ne rejoint jamais l'encyclopédie, et quitte la file de modération.
     *
     * @param string $id Slug de l'orchidée proposée.
     * @return void (redirection).
     */
    public function reject(string $id): void
    {
        $this->assertCsrf($_POST);

        $orchid = $this->orchids->findById($id, false);
        if ($orchid === null) {
            $this->addFlash('Proposition introuvable.', 'error');
            $this->redirectTo('/moderation');
        }

        $auteur = $orchid->proposedBy;
        $nom    = $orchid->scientificName();

        // Rejet soft : on conserve la plante (statut 'rejected', non publiée).
        $this->orchids->setModerationStatus($orchid->id, 'rejected');

        // RG5 : notification de refus à l'auteur.
        $this->notifierAuteur(
            $auteur,
            'rejet',
            sprintf('Votre proposition « %s » n\'a pas été retenue lors de la modération. Elle reste néanmoins dans votre collection.', $nom)
        );

        $this->addFlash('Proposition refusée (plante conservée) et auteur notifié.', 'success');
        $this->redirectTo('/moderation');
    }

    // ---------------------------------------------------------------------
    // AIDE PRIVÉE — RG5
    // ---------------------------------------------------------------------

    /**
     * Envoie une notification de décision à l'auteur d'une proposition (RG5).
     *
     * Silencieusement ignorée si l'auteur est inconnu (ex : fiche seed sans `proposed_by`).
     *
     * @param string|null $userId  Destinataire (auteur de la proposition).
     * @param string      $type    'validation' | 'rejet'.
     * @param string      $message Corps de la notification.
     * @return void
     */
    private function notifierAuteur(?string $userId, string $type, string $message): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $n = new Notification();
        $n->userId       = $userId;
        $n->collectionId = null;      // notification globale (non rattachée à une plante)
        $n->type         = $type;
        $n->message      = $message;
        $n->isRead       = false;
        $n->notifDate    = date('Y-m-d H:i:s');

        $this->notifications->create($n);
    }
}
