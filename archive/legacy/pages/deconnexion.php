<!DOCTYPE html>
<html lang="fr">

<head>
    <?php 
    $title = 'Déconnexion';
    $description = 'Page de déconnexion de Mes Orchidées. Vous avez été déconnecté de votre session.';
    $keywords = 'déconnexion, logout, session, fin session';
    require_once 'includes/head.php'; 
    ?>
</head>

<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content" id="main-content">
        <section class="logout-page" aria-label="D�connexion">
            <h1 class="page-title">DÉCONNEXION</h1>
            <p class="page-intro">Vous avez été déconnecté de l'application.</p>
            <p class="logout-note">Vos données locales restent disponibles sur cet appareil. Pour les supprimer
                définitivement, contactez l'administrateur.</p>
            <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>
    
    <script type="module">
        // Déconnexion propre : on vide la session locale, puis on revient à l'accueil
        import('./assets/js/services/auth-service.js').then(mod => {
            mod.logout();
            // Petite pause pour que la notification de succès soit visible, puis retour à l'accueil
            setTimeout(() => { window.location.href = 'index.php'; }, 1200);
        });
    </script>

</body>

</html>