<?php
require_once __DIR__ . '/api/middleware/auth.php';

$admin = getCurrentUser();
if (!$admin || ($admin['role'] ?? null) !== 'admin') {
    header('Cache-Control: no-store, private');
    header('Location: authentification.php', true, 302);
    exit;
}
?>
<!DOCTYPE html>
<!-- Page reservee aux administrateurs verifies cote serveur. -->
<html lang="fr">

<head>
    <?php 
    $title = 'Administration';
    $description = 'Panneau d\'administration : gestion des utilisateurs, modération de l\'encyclopédie, statistiques et notifications du site Mes Orchidées.';
    $keywords = 'administration, modération, gestion utilisateurs, statistiques, back-office';
    require_once 'includes/head.php'; 
    ?>
</head>

<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content" id="main-content">
        <section class="admin-header text-center" aria-label="Administration">
            <!-- J'ai enlevé le style en dur, il est maintenant dans _admin.scss -->
            <h1 class="page-title">ADMINISTRATION</h1>
        </section>

        <!-- DASHBOARD -->
        <section class="admin-dashboard-section" aria-label="Tableau de bord">
            <div class="admin-dash-cards">
                <!-- Carte Utilisateurs -->
                <div class="admin-dash-card">
                    <h3>UTILISATEURS</h3>
                    <div class="admin-dash-grid">
                        <div class="dash-stat"><span>TOTAL</span><strong id="stat-users-total">0</strong></div>
                        <div class="dash-stat"><span>MENSUEL</span><strong id="stat-users-monthly">0</strong></div>
                        <div class="dash-stat"><span>SEMAINE</span><strong id="stat-users-weekly">0</strong></div>
                        <div class="dash-stat"><span>LE + DE PLANTES</span><strong id="stat-users-active">0</strong></div>
                    </div>
                </div>

                <!-- Carte Plantes -->
                <div class="admin-dash-card">
                    <h3>PLANTES</h3>
                    <div class="admin-dash-grid">
                        <div class="dash-stat"><span>TOTAL</span><strong id="stat-plants-total">0</strong></div>
                        <div class="dash-stat"><span>PHARE</span><strong id="stat-plants-phare">-</strong></div>
                        <div class="dash-stat"><span>DÉTENUES</span><strong id="stat-plants-owned">0</strong></div>
                        <div class="dash-stat"><span>LA + RARE</span><strong id="stat-plants-rare">-</strong></div>
                    </div>
                </div>

                <!-- Carte Activités -->
                <div class="admin-dash-card">
                    <h3>ACTIVITÉS</h3>
                    <div class="admin-dash-grid">
                        <div class="dash-stat"><span>EN ATTENTE</span><strong id="stat-act-pending">0</strong></div>
                        <div class="dash-stat"><span>CONSEILS</span><strong id="stat-act-advices">0</strong></div>
                        <div class="dash-stat"><span>VALIDÉE</span><strong id="stat-act-approved">0</strong></div>
                        <div class="dash-stat"><span>REFUSÉE</span><strong id="stat-act-rejected">0</strong></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- GESTION ENCYCLOPÉDIE -->
        <section class="admin-encyclopedia-section" aria-label="Gestion de l'Encyclopédie">
            <h2 class="admin-section-title">GESTION DE L'ENCYCLOPÉDIE</h2>
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <div class="col-nom">NOM</div>
                    <div class="col-etat">ÉTAT</div>
                    <div class="col-origines">ORIGINES</div>
                    <div class="col-decouverte">DÉCOUVERTE PAR</div>
                    <div class="col-actions">ACTIONS</div>
                </div>
                <div class="admin-table-body" id="admin-encyclopedia-list">
                    <!-- Les lignes seront injectées en JS -->
                </div>
            </div>
        </section>

        <!-- ACTIONS & NOTIFICATIONS -->
        <section class="admin-bottom-section">
            <div class="admin-big-actions">
                <button type="button" class="admin-big-btn btn-tan" id="btn-moderate-orchid">
                    MODÉRER UN<br>AJOUT<br>D'ORCHIDÉE
                </button>
                <button type="button" class="admin-big-btn btn-green" id="btn-add-advice">
                    AJOUTER UN<br>NOUVEAU<br>CONSEIL
                </button>
                <!-- Bouton demandé : je le garde, il est maintenant bien intégré au design du mockup -->
                <button type="button" class="admin-big-btn btn-outline" id="btn-manage-users">
                    GÉRER LES<br>UTILISATEURS
                </button>
            </div>

            <div class="admin-notifications-container">
                <h2 class="admin-section-title">NOTIFICATIONS</h2>
                <div class="admin-table-container">
                    <div class="admin-table-header">
                        <div class="col-date">DATE</div>
                        <div class="col-notif">NOTIFICATION</div>
                        <div class="col-actions">ACTIONS</div>
                    </div>
                    <div class="admin-table-body" id="admin-notifications-list">
                        <!-- Notifications injectées en JS -->
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>

    <?php require_once 'includes/modals/admin-user-modal.php'; ?>
    <?php require_once 'includes/modals/admin-moderate-modal.php'; ?>
    <?php require_once 'includes/modals/admin-advice-modal.php'; ?>

</body>

</html>
