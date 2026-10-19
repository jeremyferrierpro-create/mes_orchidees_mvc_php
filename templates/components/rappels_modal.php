<?php
/**
 * Composant : squelette de la modale « Gérer mes rappels de soins » (Phase D).
 *
 * Inclus par `pages/collection/dashboard.php` et `pages/collection/show.php`
 * (pages connectées uniquement). Le squelette est rendu serveur et reste vide
 * tant que le JavaScript ne l'a pas ouvert : `features/rappels-modal.js`
 * charge le fragment authentifié GET /rappels/modal dans
 * `.rappels-modal__body` au moment de l'ouverture, puis pilote les actions.
 *
 * Contrat respecté (réutilisé, pas réécrit) :
 *   - markup convention du projet : section.modal-overlay[role=dialog]
 *     .modal-container > .modal-close (voir components/_modals.scss et les
 *     modales du site statique) ;
 *   - ModalManager (core/modal.js) bascule la classe `active` et l'attribut
 *     `aria-hidden`, gère Échap, piège de focus, verrou du scroll et retour du
 *     focus sur le bouton d'ouverture (declencheur passé à open()).
 *
 * Sans JavaScript : le déclencheur est un lien href="/rappels" et la page
 * autonome continue de tout gérer via ses formulaires POST — ce squelette
 * simplement invisible ne sert à rien, et c'est voulu (pas de régression).
 *
 * @package App\Templates\Components
 */
?>
<section class="modal-overlay rappels-modal" id="rappels-modal"
         aria-hidden="true" role="dialog" aria-modal="true"
         aria-labelledby="rappels-modal-title">
    <div class="modal-container">
        <button type="button" class="modal-close" data-rappels-modal-close
                aria-label="Fermer la fenêtre (aucune donnée modifiée)">&times;</button>
        <h2 id="rappels-modal-title" class="rappels-modal__titre" tabindex="-1">
            Gérer mes rappels de soins
        </h2>
        <div class="rappels-modal__body"
             data-endpoint="<?= e(url('/rappels/modal')) ?>"
             aria-live="polite"></div>
    </div>
</section>
