<!DOCTYPE html>
<html lang="fr">
<head>
    <?php 
    $title = 'Politique de confidentialité';
    $description = 'Politique de confidentialité de Mes Orchidées : collecte des données, protection RGPD, droit à l\'effacement et gestion des cookies.';
    $keywords = 'confidentialité, RGPD, protection données, cookies, droit effacement';
    require_once 'includes/head.php'; 
    ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content text-content" id="main-content">
        <section class="legal-page" aria-label="Politique de confidentialité">
            <h1 class="page-title">POLITIQUE DE CONFIDENTIALITÉ</h1>

            <article class="legal-section">
                <h2>Données collectées</h2>
                <p>Lors de l'inscription, nous collectons votre nom, prénom, adresse email et un mot de passe haché. Ces données sont nécessaires à la gestion de votre collection personnelle.</p>
            </article>

            <article class="legal-section">
                <h2>Protection des données</h2>
                <p>Conformément au RGPD, vos données ne sont pas revendues. Le mot de passe est haché avec Bcrypt et les échanges seront chiffrés via HTTPS à terme.</p>
            </article>

            <article class="legal-section">
                <h2>Droit à l'effacement</h2>
                <p>Vous pouvez demander la suppression de votre compte et de l'ensemble des données qui y sont associées (collection, soins, sites de culture, rappels).</p>
            </article>

            <article class="legal-section">
                <h2>Cookies et localStorage</h2>
                <p>Ce site utilise le <strong>localStorage</strong> du navigateur pour mémoriser temporairement votre collection locale. Aucun cookie publicitaire n'est déposé.</p>
            </article>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>

</body>
</html>

