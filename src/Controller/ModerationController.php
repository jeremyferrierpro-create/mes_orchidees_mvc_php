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
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
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

        $this->orchids->setPublished($orchid->id, true);

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
     * POST /moderation/{id}/reject — Refuse (supprime) la proposition et notifie l'auteur (RG5).
     *
     * La proposition refusée est retirée du catalogue (elle n'a jamais été publiée) ;
     * l'auteur en est informé par notification.
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

        // Rejet = suppression de la proposition non publiée.
        $this->orchids->delete($orchid->id);

        // RG5 : notification de refus à l'auteur.
        $this->notifierAuteur(
            $auteur,
            'rejet',
            sprintf('Votre proposition « %s » n\'a pas été retenue lors de la modération.', $nom)
        );

        $this->addFlash('Proposition refusée et auteur notifié.', 'success');
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
