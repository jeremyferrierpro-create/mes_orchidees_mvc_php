<?php
/**
 * Modale de gestion des soins d'orchidée
 * 
 * @package MesOrchidées\Includes\Modals
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */
?>
<!-- MODALE : GESTION DES SOINS -->
<section class="modal-overlay" id="care-modal" aria-hidden="true" role="dialog" aria-modal="true"
    aria-labelledby="care-modal-title">
    <div class="modal-container care-modal" role="document">
        <button type="button" class="modal-close" id="care-modal-close" aria-label="Fermer le formulaire">×</button>
        <h2 id="care-modal-title" class="care-modal-title">NOUVEAU SOIN</h2>

        <form id="care-form" class="care-form">
            <input type="hidden" name="csrf_token" id="csrf-token" value="">
            <div class="care-form-grid">
                <div class="form-group">
                    <label for="care-orchid">Orchidée</label>
                    <select id="care-orchid" class="edit-select" required></select>
                </div>
                <div class="form-group">
                    <label for="care-date">Date</label>
                    <input type="date" id="care-date" class="edit-input" required>
                </div>
                <fieldset class="form-group care-checks">
                    <legend>Soins effectués</legend>
                    <label><input type="checkbox" name="careType" value="arrosage"> Arrosage</label>
                    <label><input type="checkbox" name="careType" value="rempotage"> Rempotage</label>
                    <label><input type="checkbox" name="careType" value="traitement"> Traitement</label>
                    <label><input type="checkbox" name="careType" value="nutrition"> Nutrition</label>
                </fieldset>
                <div class="form-group">
                    <label for="care-engrais">Type d'engrais</label>
                    <input type="text" id="care-engrais" class="edit-input" placeholder="Ex : 20-20-20">
                </div>
                <div class="form-group">
                    <label for="care-substrat">Type de substrat</label>
                    <input type="text" id="care-substrat" class="edit-input" placeholder="Ex : Écorces de pin">
                </div>
                <div class="form-group">
                    <label for="care-ravageurs">Ravageurs</label>
                    <input type="text" id="care-ravageurs" class="edit-input" placeholder="Aucun">
                </div>
                <fieldset class="form-group care-cycles">
                    <legend>Cycle de la plante</legend>
                    <label><input type="checkbox" name="careCycle" value="repos"> Repos</label>
                    <label><input type="checkbox" name="careCycle" value="croissance"> Croissance</label>
                    <label><input type="checkbox" name="careCycle" value="floraison"> Floraison</label>
                </fieldset>
            </div>

            <div class="care-history-section">
                <h3>HISTORIQUE DES SOINS</h3>
                <div id="care-modal-history" class="care-history-table"></div>
            </div>

            <div class="collection-modal-actions">
                <button type="submit" class="btn-validate">VALIDER</button>
                <button type="button" class="btn-cancel" id="care-modal-cancel">ANNULER</button>
            </div>
        </form>
    </div>
</section>
