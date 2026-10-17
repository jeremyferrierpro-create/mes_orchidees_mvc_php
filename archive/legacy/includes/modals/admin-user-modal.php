<?php
/**
 * Modale de gestion des utilisateurs (administration)
 * 
 * @package MesOrchidées\Includes\Modals
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */
?>
<!-- MODALE FICHE UTILISATEUR -->
<div id="modal-user-form" class="modal-overlay" role="dialog" aria-modal="true"
    aria-labelledby="modal-user-title" aria-hidden="true">
    <div class="modal-container admin-form-modal">
        <button type="button" class="modal-close" id="modal-user-close"
            aria-label="Fermer la modale">&times;</button>
        <h2 id="modal-user-title" class="admin-modal-title">FICHE UTILISATEUR</h2>

        <form id="admin-user-form" class="admin-elegant-form">
            <div class="form-row">
                <label for="user-nom">NOM</label>
                <input type="text" id="user-nom" class="pill-input" required>
                <label for="user-prenom" style="margin-left:1rem;">PRÉNOM</label>
                <input type="text" id="user-prenom" class="pill-input" required>
            </div>
            <div class="form-row">
                <label for="user-email">EMAIL</label>
                <input type="email" id="user-email" class="pill-input full-width" required>
            </div>
            <div class="form-row">
                <label for="user-role">RÔLE</label>
                <select id="user-role" class="pill-input full-width">
                    <option value="user">Utilisateur</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-footer-dates">
                <span id="user-created-date">Créé le : --/--/----</span>
                <span id="user-modified-date">Modifié le : --/--/----</span>
            </div>

            <div class="admin-modal-actions">
                <button type="submit" class="btn-tan">VALIDER</button>
                <button type="button" class="btn-tan btn-cancel" id="btn-cancel-user">ANNULER</button>
            </div>
        </form>
    </div>
</div>
