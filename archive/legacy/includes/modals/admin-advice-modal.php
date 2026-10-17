<?php
/**
 * Modale d'ajout de conseil (administration)
 * 
 * @package MesOrchidées\Includes\Modals
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */
?>
<!-- MODALE AJOUT CONSEIL -->
<div id="modal-add-advice" class="modal-overlay" role="dialog" aria-modal="true"
    aria-labelledby="modal-advice-title" aria-hidden="true">
    <div class="modal-container admin-form-modal">
        <button type="button" class="modal-close" id="modal-advice-close"
            aria-label="Fermer la modale">&times;</button>
        <h2 id="modal-advice-title" class="admin-modal-title">AJOUTER UN CONSEIL</h2>

        <form id="admin-advice-form" class="admin-elegant-form">
            <div class="form-row">
                <label for="adv-name">NOM / TITRE</label>
                <input type="text" id="adv-name" class="pill-input full-width" required>
            </div>
            <div class="form-row">
                <label for="adv-cat">CATÉGORIE</label>
                <select id="adv-cat" class="pill-input full-width" required>
                    <option value="Entretien & Soins">Entretien & Soins</option>
                    <option value="Floraison">Floraison</option>
                    <option value="Maladies">Maladies</option>
                </select>
            </div>
            <div class="form-row" style="flex-direction:column; align-items:flex-start;">
                <label for="adv-content" style="margin-bottom:0.5rem;">CONTENU</label>
                <textarea id="adv-content" class="pill-input full-width" rows="5" required
                    style="border-radius:15px; padding:15px;"></textarea>
            </div>
            <div class="admin-modal-actions">
                <button type="submit" class="btn-tan">VALIDER</button>
                <button type="button" class="btn-tan btn-cancel" id="btn-cancel-advice">ANNULER</button>
            </div>
        </form>
    </div>
</div>
