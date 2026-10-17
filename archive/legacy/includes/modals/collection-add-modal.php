<?php
/**
 * Modale d'ajout d'orchidée à la collection
 * 
 * @package MesOrchidées\Includes\Modals
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */
?>
<!-- MODALE : AJOUTER UNE ORCHIDÉE À LA COLLECTION -->
<section class="modal-overlay" id="add-collection-modal" aria-hidden="true" role="dialog" aria-modal="true"
    aria-labelledby="add-modal-title">
    <div class="modal-container collection-modal" role="document">
        <button type="button" class="modal-close" id="add-modal-close" aria-label="Fermer la fenêtre">×</button>

        <div class="collection-modal-header">
            <div class="collection-modal-titles">
                <h2 id="add-modal-title" class="collection-modal-title">AJOUTER À LA COLLECTION</h2>
                <p class="collection-modal-short">Recherchez une orchidée ou proposez-en une nouvelle.</p>
            </div>
        </div>

        <div class="collection-modal-personal" style="border-top: none; padding-top: 10px;">
            <form id="add-collection-form">
                <!-- Zone Recherche / Autocomplete -->
                <div class="form-group" style="position: relative;">
                    <label for="add-orchid-name">Nom de l'orchidée *</label>
                    <input type="text" id="add-orchid-name" class="edit-input" placeholder="Ex: Phalaenopsis..."
                        autocomplete="off" required>
                    <div id="add-orchid-suggestions" class="autocomplete-suggestions glassmorphism"
                        style="display: none; background: rgba(14, 32, 24, 0.95); border: 1px solid var(--color-gold);">
                    </div>
                </div>

                <div class="form-group">
                    <label for="add-orchid-behavior">Comportement *</label>
                    <select id="add-orchid-behavior" class="edit-input" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="Épiphyte">Épiphyte</option>
                        <option value="Terrestre">Terrestre</option>
                        <option value="Lithophyte">Lithophyte</option>
                        <option value="Hémi-épiphyte">Hémi-épiphyte</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="add-orchid-origin">Origine(s)</label>
                    <input type="text" id="add-orchid-origin" class="edit-input"
                        placeholder="Ex: Amérique du Sud...">
                </div>

                <!-- Informations Botaniques (Optionnelles mais auto-remplies si connue) -->
                <h4 style="margin-top: 20px;">TAXONOMIE (OPTIONNEL)</h4>
                <div class="personal-fields">
                    <div class="form-group">
                        <label for="add-orchid-order">Ordre</label>
                        <input type="text" id="add-orchid-order" class="edit-input">
                    </div>
                    <div class="form-group">
                        <label for="add-orchid-family">Famille</label>
                        <input type="text" id="add-orchid-family" class="edit-input">
                    </div>
                    <div class="form-group">
                        <label for="add-orchid-genre">Genre</label>
                        <input type="text" id="add-orchid-genre" class="edit-input">
                    </div>
                    <div class="form-group">
                        <label for="add-orchid-species">Espèce</label>
                        <input type="text" id="add-orchid-species" class="edit-input">
                    </div>
                </div>

                <!-- Checkbox pour proposer à l'Encyclopédie (Masquée si la plante est connue) -->
                <div class="form-group" id="add-propose-container"
                    style="display: flex; align-items: center; gap: 10px; margin-top: 20px;">
                    <input type="checkbox" id="add-propose-checkbox" style="width: auto;">
                    <label for="add-propose-checkbox"
                        style="color: var(--color-tan); font-style: italic; font-size: 0.9rem;">
                        Je souhaite proposer cette orchidée à l'Encyclopédie.
                    </label>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" id="add-modal-cancel"
                        style="padding: 10px 20px; border-radius: 20px;">Annuler</button>
                    <button type="submit" class="btn-primary" id="add-modal-save"
                        style="padding: 10px 20px; border-radius: 20px;">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</section>
