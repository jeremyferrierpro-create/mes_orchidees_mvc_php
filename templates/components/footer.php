<?php
/**
 * Composant : pied de page + toasts + script applicatif (repris de includes/footer.php).
 *
 * Les liens pointent désormais vers les routes MVC. Le script app.js gère l'UI
 * (menu, focus, toasts) ; son peuplement de données sera réorienté vers les routes
 * API MVC (JSON) au fil des incréments.
 *
 * @package App\Templates\Components
 */
?>
    <footer id="main-footer">
        <div class="footer-links-group">
            <a href="<?= e(url('/mentions')) ?>" class="footer-link">Mentions légales</a>
            <a href="<?= e(url('/accessibilite')) ?>" class="footer-link">Accessibilité</a>
        </div>
        <div class="footer-center">
            <img src="<?= e(asset('assets/images/site/logotransparent.webp')) ?>" alt="Logo de Mes Orchidées" width="30" height="30">
            <p>&copy; Mes Orchidées - Projet Fil Rouge DWWM - Jeremy Ferrier - 2026/2027</p>
        </div>
        <a href="<?= e(url('/confidentialite')) ?>" class="footer-link">Politiques de confidentialité</a>
    </footer>

    <!-- Conteneur global pour les notifications "Toasts" -->
    <div id="toast-container" class="toast-container" aria-live="polite"></div>

    <!-- Script principal de l'application -->
    <script type="module" src="<?= e(asset('assets/js/app.js')) ?>"></script>
