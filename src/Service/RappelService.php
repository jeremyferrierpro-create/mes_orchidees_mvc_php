<?php
/**
 * Service des rappels de soin — cycle de vie RG3 (génération / annulation).
 *
 * Extrait de SoinController (phase C0) pour que la programmation et l'annulation
 * des rappels partagent UNE SEULE implémentation entre :
 *  - SoinController  : enregistrement d'un soin depuis la fiche plante ;
 *  - RappelController : action « Effectuer le soin » depuis la carte du rappel,
 *    qui consigne le soin puis replanifie le rappel suivant.
 *
 * Politique (inchangée depuis RG3) :
 *  - la fréquence est personnalisable (`collections.rappel_frequence_jours`),
 *    repli sur la constante de service si la valeur est aberrante ;
 *  - le rappel préexistant (`planifie` ou `actif`) sur la même plante est mis à
 *    jour plutôt qu'empilé (sinon l'utilisateur accumulerait un rappel par soin,
 *    saturant la cloche) ;
 *  - le nouveau rappel est écrit en statut `planifie` : il ne devient `actif`
 *    que lorsque notif_date est atteinte (vue par `activateDueReminders()`) ;
 *  - C0 : le rappel hérite du type du soin qui le programme
 *    (`notifications.id_type_soin`), pour affichage et actions typées.
 *
 * Constructeur SANS ARGUMENT imposé (DefaultControllerResolver) : les dépendances
 * sont composées ici, injectables pour les tests.
 *
 * @package App\Service
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 1.0.0
 * @since   2026-10-18 (phase C0)
 */

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Soin;
use App\Repository\CollectionRepository;
use App\Repository\NotificationRepository;

class RappelService
{
    /** Décalage (jours) par défaut du rappel automatique RG3 après un soin enregistré.
     *  Sert de repli si la collection n'expose pas de fréquence valide. */
    public const RAPPORT_JOURS_RAPPEL = 7;

    /** Bornes de la fréquence personnalisable (jour) — cohérent avec la validation du formulaire. */
    public const RAPPEL_MIN = 1;
    public const RAPPEL_MAX = 365;

    private NotificationRepository $notifications;
    private CollectionRepository $collections;

    public function __construct(
        ?NotificationRepository $notifications = null,
        ?CollectionRepository $collections = null
    ) {
        $this->notifications = $notifications ?? new NotificationRepository();
        $this->collections   = $collections ?? new CollectionRepository();
    }

    /**
     * Annule le rappel en cours d'une plante quand l'utilisateur désactive les
     * rappels (opt-in). Un rappel `planifie` ou `actif` est basculé en `ignore` :
     * il cesse d'être proposé sans être détruit (préservation de l'historique).
     * Les rappels déjà `traite`/`ignore` sont laissés tels quels.
     *
     * RG1 : updateStatutForUser filtre sur user_id.
     *
     * @param string $uid          Propriétaire (session).
     * @param string $collectionId Entrée de collection.
     * @return void
     */
    public function annuler(string $uid, string $collectionId): void
    {
        $existant = $this->notifications->findLatestRappelForCollection($collectionId, $uid);
        if ($existant !== null && in_array($existant->statut, ['planifie', 'actif'], true)) {
            $this->notifications->updateStatutForUser($existant->id, $uid, 'ignore');
        }
    }

    /**
     * Génère ou replanifie la notification de rappel du prochain soin (RG3).
     *
     * @param string $uid          Propriétaire (session).
     * @param string $collectionId Entrée de collection.
     * @param Soin   $soin         Le soin vient d'être enregistré (type + date peuplés).
     * @return void
     */
    public function generer(string $uid, string $collectionId, Soin $soin): void
    {
        $plante     = $this->collections->findByIdForUser($collectionId, $uid);
        $nomPlante  = $plante?->displayName() ?? 'votre plante';
        $frequence  = $plante !== null && $plante->rappelFrequenceJours >= self::RAPPEL_MIN
                    ? $plante->rappelFrequenceJours
                    : self::RAPPORT_JOURS_RAPPEL;

        $dateSoin = $soin->soinDate !== '' ? $soin->soinDate : date('Y-m-d');
        $echeanceTs = strtotime($dateSoin . ' +' . (int) $frequence . ' days');
        if ($echeanceTs === false) {
            $echeanceTs = time() + self::RAPPORT_JOURS_RAPPEL * 86400;
        }
        $nextDateIso  = date('Y-m-d H:i:s', $echeanceTs);
        $nextDateFr   = date('d/m/Y', $echeanceTs);

        $message = sprintf(
            'Rappel : prochain soin (%s) prévu le %s pour %s (fréquence %d j).',
            $soin->typeLabel(),
            $nextDateFr,
            $nomPlante,
            $frequence
        );

        // Reprise du dernier rappel actif/planifié pour éviter l'empilement.
        $existant = $this->notifications->findLatestRappelForCollection($collectionId, $uid);
        if ($existant !== null && in_array($existant->statut, ['planifie', 'actif'], true)) {
            $existant->message    = $message;
            $existant->notifDate  = $nextDateIso;
            $existant->statut     = 'planifie';
            $existant->idTypeSoin = $soin->idTypeSoin; // C0 : le rappel suit le type du soin.
            $this->notifications->updateRappelForUser($existant, $uid);
            return;
        }

        $notif = new Notification();
        $notif->userId       = $uid;
        $notif->collectionId = $collectionId;
        $notif->type         = 'rappel_soin';
        $notif->message      = $message;
        $notif->isRead       = false;
        $notif->statut       = 'planifie';
        $notif->idTypeSoin   = $soin->idTypeSoin; // C0 : type structuré porté par le rappel.
        $notif->notifDate    = $nextDateIso;

        $this->notifications->create($notif);
    }
}
