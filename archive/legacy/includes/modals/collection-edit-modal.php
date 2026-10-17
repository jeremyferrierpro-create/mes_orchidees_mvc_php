<?php
/**
 * Modale d'édition de fiche orchidée dans la collection
 * 
 * @package MesOrchidées\Includes\Modals
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */
?>
<!-- MODALE : FICHE DE L'ORCHIDÉE (ÉDITION / AFFICHAGE) -->
<section class="modal-overlay" id="edit-collection-modal" aria-hidden="true" role="dialog" aria-modal="true"
    aria-labelledby="edit-modal-title">
    <div class="modal-container collection-modal" role="document">
        <button type="button" class="modal-close" id="edit-modal-close" aria-label="Fermer la fiche">×</button>

        <div class="collection-modal-header">
            <img id="edit-modal-img" src="" alt="" aria-hidden="true" class="modal-orchid-img">
            <div class="collection-modal-titles">
                <h2 id="edit-modal-title" class="collection-modal-title"></h2>
                <p id="edit-modal-short" class="collection-modal-short"></p>
            </div>
        </div>

        <div class="collection-modal-fields" id="edit-modal-fields"></div>

        <div class="collection-modal-desc">
            <h4>DESCRIPTION &amp; CARACTÉRISTIQUES</h4>
            <p id="edit-modal-long"></p>
        </div>

        <div class="collection-modal-personal">
            <h4>INFORMATIONS PERSONNELLES</h4>
            <div class="personal-fields">
                <div class="form-group">
                    <label for="edit-location">Site de culture (Emplacement)</label>
                    <input type="text" id="edit-location" class="edit-input" placeholder="Ex : Salon, Véranda...">
                </div>
                <div class="form-group">
                    <label for="edit-temp">Température du site</label>
                    <input type="text" id="edit-temp" class="edit-input" placeholder="Ex : 20-25°C">
                </div>
                <div class="form-group">
                    <label for="edit-hygro">Hygrométrie du site</label>
                    <input type="text" id="edit-hygro" class="edit-input" placeholder="Ex : 60-70%">
                </div>
                <div class="form-group">
                    <label for="edit-light">Luminosité du site</label>
                    <input type="text" id="edit-light" class="edit-input"
                        placeholder="Ex : Vive sans soleil direct">
                </div>
                <div class="form-group">
                    <label for="edit-ventilation">Ventilation du site</label>
                    <input type="text" id="edit-ventilation" class="edit-input"
                        placeholder="Ex : Bonne ventilation">
                </div>
                <div class="form-group">
                    <label for="edit-notes">Notes complémentaires</label>
                    <textarea id="edit-notes" class="edit-textarea" rows="3"
                        placeholder="Notes de culture..."></textarea>
                </div>
            </div>
        </div>

        <div class="collection-modal-actions">
            <button type="button" class="btn-validate" id="edit-modal-save">VALIDER</button>
            <button type="button" class="btn-cancel" id="edit-modal-cancel">ANNULER</button>
        </div>
    </div>
</section>
