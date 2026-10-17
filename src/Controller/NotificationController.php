<?php
/**
 * Contrôleur des notifications — boîte de réception de l'utilisateur (Phase 03, incrément 4).
 *
 * Gère la consultation, le marquage « lu » et la suppression des notifications du
 * SEUL utilisateur connecté. L'isolation est stricte (même discipline que RG1) :
 * toutes les requêtes du repository filtrent par `user_id`, et l'identifiant du
 * destinataire est lu UNIQUEMENT en session — jamais depuis la requête.
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver : `new ClassName()`),
 * dépendance composée ici.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Repository\NotificationRepository;

class NotificationController extends AbstractController
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationRepository();
    }

    /**
     * Identifiant de l'utilisateur connecté (isolation).
     *
     * @return string
     */
    private function currentUserId(): string
    {
        return (string) (current_user()['id'] ?? '');
    }

    /**
     * GET /notifications — Liste HTML des notifications de l'utilisateur.
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        $uid   = $this->currentUserId();
        $liste = $this->notifications->findByUser($uid);

        return $this->render('pages/notifications/index', [
            'notifications' => $liste,
            'nbNonLues'     => $this->notifications->unreadCount($uid),
            'total'         => count($liste),
            'csrf'          => $this->getCsrfToken(),
        ], 'Mes notifications');
    }

    /**
     * GET /notification/{id} — Détail d'une notification (isolation : WHERE user_id).
     *
     * @param string $id UUID de la notification.
     * @return string HTML rendu.
     */
    public function show(string $id): string
    {
        $uid  = $this->currentUserId();
        $notif = $this->notifications->findByIdForUser($id, $uid);
        if ($notif === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Cette notification est introuvable.',
            ], 'Notification introuvable');
        }

        // Affuser la notification fait aussi passer son état à « lue ».
        $this->notifications->markReadForUser($notif->id, $uid);

        return $this->render('pages/notifications/show', [
            'notification' => $notif,
            'csrf'         => $this->getCsrfToken(),
        ], 'Notification');
    }

    /**
     * POST /notification/{id}/read — Marque la notification comme lue (isolation).
     *
     * @param string $id UUID de la notification.
     * @return void (redirection).
     */
    public function markRead(string $id): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        if ($this->notifications->markReadForUser($id, $uid)) {
            $this->addFlash('Notification marquée comme lue.', 'success');
        } else {
            $this->addFlash('Notification introuvable.', 'error');
        }
        $this->redirectTo('/notifications');
    }

    /**
     * POST /notification/delete/{id} — Supprime la notification (isolation).
     *
     * @param string $id UUID de la notification.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        if ($this->notifications->deleteForUser($id, $uid)) {
            $this->addFlash('Notification supprimée.', 'success');
        } else {
            $this->addFlash('Suppression impossible : notification introuvable.', 'error');
        }
        $this->redirectTo('/notifications');
    }
}
