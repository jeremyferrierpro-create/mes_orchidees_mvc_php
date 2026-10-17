<?php
/**
 * Modale d'affichage des conseils de culture
 * 
 * @package MesOrchidées\Includes\Modals
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */
?>
<!-- MODALE FICHE CONSEILS -->
<section class="modal-overlay" id="conseil-modal" aria-hidden="true" role="dialog" aria-modal="true"
    aria-labelledby="conseil-modal-title">
    <div class="modal-container conseil-modal" role="document">
        <button type="button" class="modal-close" id="conseil-modal-close" aria-label="Fermer la fiche">
            <span aria-hidden="true">&times;</span>
        </button>

        <div class="conseil-modal-header">
            <img id="conseil-modal-img" class="conseil-modal-img" src="../assets/images/site/orchidee_hero.webp" alt="Conseils">
            <div class="conseil-modal-titles">
                <h2 class="conseil-modal-suptitle">FICHE CONSEILS</h2>
                <h3 id="conseil-modal-title" class="conseil-modal-title"></h3>
                <p id="conseil-modal-meta" class="conseil-modal-meta"></p>
            </div>
        </div>

        <div class="conseil-modal-body">
            <p id="conseil-modal-text" class="conseil-modal-text"></p>
        </div>

        <div class="conseil-care-grid" aria-label="Conseils de culture">
            <div class="care-card">
                <i class="fa-solid fa-thermometer-half" aria-hidden="true"></i>
                <span>Températures</span>
                <strong id="care-temperature"></strong>
            </div>
            <div class="care-card">
                <i class="fa-solid fa-droplet" aria-hidden="true"></i>
                <span>Arrosage</span>
                <strong id="care-arrosage"></strong>
            </div>
            <div class="care-card">
                <i class="fa-solid fa-percent" aria-hidden="true"></i>
                <span>Hygrométrie</span>
                <strong id="care-hygrometrie"></strong>
            </div>
            <div class="care-card">
                <i class="fa-solid fa-seedling" aria-hidden="true"></i>
                <span>Rempotage</span>
                <strong id="care-rempotage"></strong>
            </div>
            <div class="care-card">
                <i class="fa-solid fa-flask" aria-hidden="true"></i>
                <span>Engrais</span>
                <strong id="care-engrais"></strong>
            </div>
            <div class="care-card">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <span>Substrats</span>
                <strong id="care-substrats"></strong>
            </div>
        </div>
    </div>
</section>
