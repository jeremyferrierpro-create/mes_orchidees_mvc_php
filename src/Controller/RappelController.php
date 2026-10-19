<?php
/**
 * Contrôleur des rappels de soins — cycle de vie RG3 étendu (Phase 09).
 *
 * Expose la vue « /rappels » (prochains rappels planifiés + rappels dus) et les
 * actions unitaires de cycle de vie : traiter, ignorer, reporter, supprimer.
 * Le destinataire est TOUJOURS l'utilisateur de la session (RG1) : les méthodes
 * du repository filtrent par `user_id`, aucune notification d'un tiers n'est
 * atteignable par devinette d'UUID.
 *
 * Phase C0 (arbitrage propriétaire) : l'action « Effectuer le soin » (/fait)
 * n'est plus un simple changement de statut — elle consigne un soin RÉEL (date
 * du jour + type porté par le rappel), clôture le rappel, puis replanifie le
 * suivant si la plante a opt-in (`collections.rappel_actif`, source unique).
 * La programmation est déléguée au `RappelService` partagé avec SoinController.
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver), dépendance composée ici.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.1.0
 * @since   2026-10-18
 */

namespace App\Controller;

use App\Entity\Soin;
use App\Repository\CollectionRepository;
use App\Repository\NotificationRepository;
use App\Repository\SoinRepository;
use App\Repository\TypeSoinRepository;
use App\Service\RappelService;

class RappelController extends AbstractController
{
    private NotificationRepository $notifications;
    private SoinRepository $soins;
    private CollectionRepository $collections;
    private TypeSoinRepository $typesSoin;
    private RappelService $rappels;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationRepository();
        $this->soins         = new SoinRepository();
        $this->collections   = new CollectionRepository();
        $this->typesSoin     = new TypeSoinRepository();
        $this->rappels       = new RappelService();
    }

    /** Identifiant de la session courante (RG1). */
    private function currentUserId(): string
    {
        return (string) (current_user()['id'] ?? '');
    }

    /**
     * Collecte et classe les rappels OUVERTS de l'utilisateur (source unique de
     * vérité des sections, partagée par /rappels et le fragment de modale D).
     *
     * Active d'abord les rappels `planifie` devenus dus (mécanisme C0,
     * idempotent, ne crée rien), puis ventile : `actif` dépassé → « en retard »,
     * `actif` du jour → « aujourd'hui », reste → « à venir » ; l'historique
     * (`traite`/`ignore`) est une requête distincte.
     *
     * @param string $uid Identifiant de session (RG1).
     * @return array{aujourdhui: array, enRetard: array, aVenir: array, historique: array}
     */
    private function listsRappels(string $uid): array
    {
        // Activation différée : les rappels `planifie` devenus dus passent `actif`.
        $this->notifications->activateDueReminders($uid);

        $aujourdhui = [];
        $enRetard   = [];
        $aVenir     = [];
        foreach ($this->notifications->findRappelsForUser($uid, ['planifie', 'actif']) as $r) {
            if ($r->statut === 'actif' && $r->isLate()) {
                $enRetard[] = $r;
            } elseif ($r->statut === 'actif' && $r->isDueToday()) {
                $aujourdhui[] = $r;
            } else {
                // `planifie` à échéance future (par exception : un `actif` dont la
                // date est postérieure à aujourd'hui — reste traité comme « à venir »).
                $aVenir[] = $r;
            }
        }
        $historique = $this->notifications->findRappelsForUser($uid, ['traite', 'ignore']);

        return compact('aujourdhui', 'enRetard', 'aVenir', 'historique');
    }

    /**
     * GET /rappels — Rappels classés en sections calculées sur données réelles (C0) :
     * « À faire aujourd'hui » (actif dû ce jour), « En retard » (actif dépassé),
     * « À venir » (planifié, échéance future) et « Historique » (traité/ignoré).
     * Les compteurs d'en-tête découlent des mêmes listes — aucune valeur bidon.
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        $uid  = $this->currentUserId();
        $listes = $this->listsRappels($uid);
        extract($listes); // [$aujourdhui, $enRetard, $aVenir, $historique]
        $user = current_user();

        return $this->render('pages/rappels/index', [
            'aujourdhui'       => $aujourdhui,
            'enRetard'         => $enRetard,
            'aVenir'           => $aVenir,
            'historique'       => $historique,
            'nbAVenir'         => count($aujourdhui) + count($enRetard) + count($aVenir),
            'user'             => $user,
            'csrf'             => $this->getCsrfToken(),
            'meta_description' => 'Vos prochains rappels de soins pour les orchidées de votre collection.',
        ], 'Mes rappels de soins');
    }

    /**
     * GET /rappels/modal — Fragment HTML de la modale « Gérer mes rappels »
     * (Phase D). Mêmes listes, mêmes cartes et mêmes formulaires POST que la
     * page /rappels : rendu via `renderPartial()` (pas de layout), réutilisant
     * le partial `pages/rappels/_liste.php`. Aucune logique métier dupliquée.
     *
     * Contrat (front `features/rappels-modal.js`) :
     *   - authentifié (GRANT_AUTH) : anonyme → 403 générique du front-controller,
     *     corps HTML ne révélant rien des données de l'utilisateur ;
     *   - session expirée → même 403 (le front JS affiche un état d'erreur,
     *     aucune action n'est rejouée automatiquement) ;
     *   - GET pur : ne mute jamais l'état (l'activation `planifie`→`actif`
     *     idempotente de C0 mise à part) et ne crée aucun rappel.
     *
     * Le jeton CSRF voyage dans les formulaires du fragment (session
     * authentifiée), comme sur la page autonome — il n'est jamais exposé aux
     * visiteurs anonymes.
     *
     * @return string Fragment HTML (200), ou message minimal 403 si la session
     *                est invalide (défense en profondeur, inatteignable sous
     *                routage normal grâce à GRANT_AUTH).
     */
    public function modal(): string
    {
        $uid = $this->currentUserId();
        if ($uid === '') {
            http_response_code(403);
            return '<p class="rappels-modal__erreur">Session expirée. Rechargez la page pour vous reconnecter.</p>';
        }

        $listes = $this->listsRappels($uid);
        extract($listes);

        return $this->renderPartial('pages/rappels/modal', [
            'aujourdhui' => $aujourdhui,
            'enRetard'   => $enRetard,
            'aVenir'     => $aVenir,
            'historique' => $historique,
            'user'       => current_user(),
            'csrf'       => $this->getCsrfToken(),
        ]);
    }

    /**
     * GET /api/rappels/dus — Flux JSON des rappels de soin devenus dus et non lus.
     *
     * Consommé par le toast persistant du jour J (Phase C1). Le route est
     * GRANT_AUTH : le propriétaire est lu UNIQUEMENT en session (RG1), jamais
     * depuis la requête. L'activation réutilise `activateDueReminders()` (même
     * mécanisme que /rappels et le dashboard — aucune logique dupliquée, aucun
     * nouveau rappel créé à chaque appel : simple transition `planifie` → `actif`).
     *
     * Éligibilité : statut `actif`, échéance atteinte (`isLate()` ou
     * `isDueToday()`, mêmes helpers qu'en C0) ET non lu (`isUnread()`). Un rappel
     * ignoré/traité (statut hors `actif`) ou à échéance future ne sort jamais ici.
     *
     * Réponse stable (front `features/rappels-toast.js`) :
     *   { status:'ok', count:int, consultUrl:string, readUrlBase:string,
     *     csrf:string, rappels:[ Notification::toArray() + { late:bool } ] }
     * `consultUrl`/`readUrlBase` sont générés côté serveur via `url()` (préfixe
     * d'installation respecté) ; le jeton CSRF est transmis uniquement dans cette
     * réponse authentifiée, pour le POST de marquage « lu ».
     *
     * @return string Corps JSON (200). En cas d'erreur inattendue, le
     * front-controller renvoie déjà une 500 générique sans détails sensibles.
     */
    public function dus(): string
    {
        $uid = $this->currentUserId();
        if ($uid === '') {
            // Défense en profondeur : le grant bloque déjà, mais on ne expose
            // jamais un flux vide « au cas où » sous une session invalide.
            return $this->json(['status' => 'ko', 'count' => 0, 'rappels' => []], 403);
        }

        // Mécanisme d'activation existant (idempotent, ne crée rien).
        $this->notifications->activateDueReminders($uid);

        $dus = [];
        foreach ($this->notifications->findRappelsForUser($uid, ['actif']) as $r) {
            if ($r->isUnread() && ($r->isLate() || $r->isDueToday())) {
                $item          = $r->toArray();
                $item['late']  = $r->isLate();
                $dus[]         = $item;
            }
        }

        return $this->json([
            'status'      => 'ok',
            'count'       => count($dus),
            'consultUrl'  => url('/rappels'),
            'readUrlBase' => url('/notification'),
            'csrf'        => $this->getCsrfToken(),
            'rappels'     => $dus,
        ]);
    }

    /**
     * POST /rappel/{id}/fait — « Effectuer le soin » (C0, arbitrage propriétaire).
     *
     * Séquence : consigner le soin réel (date du jour, type porté par le rappel,
     * repli sur le type par défaut pour les rappels historiques sans type),
     * clôturer le rappel (`traite`), puis replanifier le prochain rappel si la
     * plante est en opt-in (`rappel_actif`). Un rappel rattaché à une plante
     * supprimée ou global se contente de clôturer (aucun soin orphelin).
     *
     * RG1 : relecture de la propriété AVANT toute écriture (rappel via user_id,
     * plante via collectionOwnedBy) — un rappel d'un tiers ne peut pas créer de
     * soin sur une plante d'un tiers. Anti-double-frappe : un rappel déjà clos
     * (`traite`/`ignore`) ne consigne rien de plus.
     *
     * @param string $id UUID de la notification.
     * @return string|null Corps JSON si le client le demande (modale D), sinon
     *                      rien : la redirection 302 met fin à la requête.
     */
    public function fait(string $id): ?string
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $rappel = $this->notifications->findByIdForUser($id, $uid);
        if ($rappel === null) {
            return $this->reply('Rappel introuvable.', 'error', ['resultat' => 'introuvable']);
        }
        if (in_array($rappel->statut, ['traite', 'ignore'], true)) {
            // Double-frappe ou rappel déjà écarté : rien n'est consigné deux fois.
            return $this->reply('Ce rappel est déjà clôturé.', 'info', ['resultat' => 'deja_cloture', 'statut' => $rappel->statut]);
        }

        // 1) Consigner le soin si le rappel vise une plante possédée (RG1).
        $soinConsigne = false;
        $plante       = null;
        $soin         = null;
        if ($rappel->collectionId !== null && $this->soins->collectionOwnedBy($rappel->collectionId, $uid)) {
            $plante = $this->collections->findByIdForUser($rappel->collectionId, $uid);

            $typeId = $rappel->idTypeSoin ?? $this->typesSoin->defaultId();
            $soin   = new Soin();
            $soin->collectionId = (string) $rappel->collectionId;
            $soin->soinDate     = date('Y-m-d');
            $soin->idTypeSoin   = $typeId;
            $soin->typeLabel    = $rappel->typeSoinLabel ?? $this->typeLabelById($typeId);
            $soin->notes        = 'Soin consigné depuis le rappel.';
            $this->soins->create($soin);
            $soinConsigne = true;
        }

        // 2) Clôturer le rappel.
        $this->notifications->updateStatutForUser($id, $uid, 'traite');

        // 3) Reprogrammer le rappel suivant (dédupliqué) si la plante a opt-in.
        //    Source unique de vérité : `collections.rappel_actif` (étape A).
        if ($soinConsigne && $plante !== null && $soin !== null && $plante->rappelActif) {
            $this->rappels->generer($uid, (string) $rappel->collectionId, $soin);
            return $this->reply('Soin consigné. Prochain rappel programmé.', 'success', [
                'resultat'        => 'soin_consigne',
                'prochain_rappel' => true,
                'statut'          => 'traite',
            ]);
        } elseif ($soinConsigne) {
            return $this->reply('Soin consigné. Les rappels de cette plante sont désactivés.', 'success', [
                'resultat'        => 'soin_consigne',
                'prochain_rappel' => false,
                'statut'          => 'traite',
            ]);
        }
        return $this->reply('Rappel marqué comme traité.', 'success', [
            'resultat'        => 'traite',
            'prochain_rappel' => false,
            'statut'          => 'traite',
        ]);
    }

    /**
     * POST /rappel/{id}/ignorer — Le propriétaire écarte explicitement ce rappel.
     *
     * @param string $id UUID.
     * @return string|null Corps JSON si demandé, sinon rien (redirection).
     */
    public function ignorer(string $id): ?string
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();
        if ($this->notifications->updateStatutForUser($id, $uid, 'ignore')) {
            return $this->reply('Rappel ignoré.', 'success', ['resultat' => 'ignore', 'statut' => 'ignore']);
        }
        return $this->reply('Rappel introuvable.', 'error', ['resultat' => 'introuvable']);
    }

    /**
     * POST /rappel/{id}/report/{jours} — Repousse l'échéance de `jours` jours.
     *
     * Borné à [1 ; 365] : un report aberrant ou une valeur non numérique renvoie
     * une erreur 400 sans toucher la base.
     *
     * @param string $id    UUID.
     * @param string $jours Nombre de jours (paramètre de route, donc string).
     * @return string|null Corps JSON si demandé, sinon rien (redirection).
     */
    public function report(string $id, string $jours): ?string
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $n = $this->notifications->findByIdForUser($id, $uid);
        if ($n === null) {
            return $this->reply('Rappel introuvable.', 'error', ['resultat' => 'introuvable']);
        }

        $delta = (int) $jours;
        if ($delta < 1 || $delta > 365) {
            return $this->reply('Report invalide : le délai doit être compris entre 1 et 365 jours.', 'error', ['resultat' => 'report_invalide']);
        }

        // Date de départ = échéance actuelle (si future) sinon aujourd'hui.
        $base = $n->notifDate !== null && strtotime($n->notifDate) !== false
              ? max(time(), strtotime($n->notifDate))
              : time();
        $newDate = date('Y-m-d H:i:s', $base + $delta * 86400);

        if ($this->notifications->rescheduleForUser($id, $uid, $newDate)) {
            return $this->reply(sprintf('Rappel reporté de %d jour(s).', $delta), 'success', [
                'resultat'  => 'reporte',
                'nouvelleDate' => date('d/m/Y', strtotime($newDate)),
            ]);
        }
        return $this->reply('Report impossible.', 'error', ['resultat' => 'report_impossible']);
    }

    /**
     * POST /rappel/delete/{id} — Supprime définitivement un rappel (RG1).
     *
     * @param string $id UUID.
     * @return string|null Corps JSON si demandé, sinon rien (redirection).
     */
    public function delete(string $id): ?string
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();
        if ($this->notifications->deleteForUser($id, $uid)) {
            return $this->reply('Rappel supprimé.', 'success', ['resultat' => 'supprime']);
        }
        return $this->reply('Suppression impossible : rappel introuvable.', 'error', ['resultat' => 'introuvable']);
    }

    /**
     * Terminal de réponse d'une action du cycle de vie d'un rappel.
     *
     * Deux rendu possibles pour UNE seule et même logique métier (aucune
     * duplication) :
     *   - JSON (Phase D, modale) : {status, message, resultat, …} en 200 — le
     *     client n'affiche jamais un succès sans avoir lu `status: ok` ;
     *   - HTML (C0, page autonome et repli sans JS) : message flash + 302 vers
     *     `returnUrl()`, comportement strictement inchangé.
     *
     * @param string $message Texte court, prêt à afficher (sans détail technique).
     * @param string $type    'success' | 'error' | 'info'.
     * @param array  $extra   Champs complémentaires du contrat JSON.
     * @return string|null Corps JSON, ou null après la redirection (script tué).
     */
    private function reply(string $message, string $type, array $extra = []): ?string
    {
        if ($this->prefersJson()) {
            return $this->json(array_merge([
                'status'  => $type === 'error' ? 'ko' : 'ok',
                'type'    => $type,
                'message' => $message,
            ], $extra));
        }
        $this->addFlash($message, $type);
        $this->redirectTo($this->returnUrl());
        return null;
    }

    /**
     * Résout le libellé d'un type de soin depuis le vocabulaire fermé.
     * Repli défensif sur « arrosage » (premier type) si l'id est inconnu —
     * cas des rappels historiques sans type structuré.
     *
     * @param int $typeId
     * @return string
     */
    private function typeLabelById(int $typeId): string
    {
        foreach ($this->typesSoin->findAll() as $t) {
            if ($t->id === $typeId) {
                return $t->label;
            }
        }
        return 'arrosage';
    }

    /**
     * URL de retour après action : la referer interne si elle mène à /rappels ou
     * /collection/*, sinon /rappels. Évite d'envoyer l'utilisateur chez un tiers.
     *
     * @return string
     */
    private function returnUrl(): string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $path = parse_url($ref, PHP_URL_PATH);
            if (is_string($path) && (str_starts_with($path, '/rappels') || str_starts_with($path, '/collection/'))) {
                return $path;
            }
        }
        return '/rappels';
    }
}
