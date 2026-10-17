<?php
/**
 * Modale de modération d'orchidée (administration)
 * 
 * @package MesOrchidées\Includes\Modals
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */
?>
<!-- MODALE MODÉRATION ORCHIDÉE -->
<div id="modal-moderate-orchid" class="modal-overlay" role="dialog" aria-modal="true"
    aria-labelledby="modal-mod-title" aria-hidden="true">
    <div class="modal-container">
        <button type="button" class="modal-close" id="modal-mod-close"
            aria-label="Fermer la fenêtre">&times;</button>

        <div class="orchid-modal-view">
            <div class="modal-header-block">
                <img id="mod-orchid-img" src="" alt="" aria-hidden="true" class="modal-orchid-img">
                <div class="modal-title-group">
                    <h2 id="modal-mod-title">TITRE</h2>
                    <p id="mod-orchid-scientific" class="modal-scientific">SCIENTIFIQUE</p>
                    <p id="mod-orchid-vernacular" class="modal-subtitle">VERNACULAIRE</p>
                    <h2 class="modal-label-desc">DESCRIPTION COURTE :</h2>
                    <p id="mod-orchid-short" class="modal-short-text">...</p>
                </div>
            </div>

            <div class="modal-specs-grid">
                <div class="spec-item"><span class="spec-label">Ordre</span>
                    <div id="mod-spec-ordre" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Espèce</span>
                    <div id="mod-spec-espece" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Genre</span>
                    <div id="mod-spec-genre" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Famille</span>
                    <div id="mod-spec-famille" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Sous-Famille</span>
                    <div id="mod-spec-subfamily" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Tribu</span>
                    <div id="mod-spec-tribu" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Sous-Tribu</span>
                    <div id="mod-spec-subtribu" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Comportement</span>
                    <div id="mod-spec-behavior" class="spec-value">...</div>
                </div>
                <div class="spec-item"><span class="spec-label">Découverte par</span>
                    <div id="mod-spec-discovered" class="spec-value">...</div>
                </div>
                <div class="spec-item spec-item--full"><span class="spec-label">Origines</span>
                    <div id="mod-spec-origin" class="spec-value">...</div>
                </div>
            </div>

            <div class="modal-description-block">
                <h2>Description &amp; Caractéristiques</h2>
                <p id="mod-orchid-long" class="modal-long-desc">...</p>
            </div>

            <div class="modal-footer-action admin-mod-actions">
                <button type="button" class="btn-tan" id="btn-approve-orchid">APPROUVER</button>
                <button type="button" class="btn-tan" id="btn-reject-orchid">REFUSER</button>
            </div>
        </div>
    </div>
</div>
