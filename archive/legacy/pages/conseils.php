<!DOCTYPE html>
<!-- Page branchee sur /assets/js/data/*.json via db.js (simulation Supabase) - 100% dynamique -->
<html lang="fr">
<head>
    <?php 
    $title = 'Conseils';
    $description = 'Conseils de culture pour orchidées : épiphytes, terrestres, hémi-épiphytes, sortie de flask, après achat. Guide complet pour réussir votre culture.';
    $keywords = 'conseils orchidées, culture orchidées, épiphytes, terrestres, soins, arrosage';
    require_once 'includes/head.php'; 
    ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content" id="main-content" role="main">
        <section class="advice-header" aria-label="Conseils de culture">
            <h1 class="page-title">CONSEILS</h1>
        </section>

        <section class="conseils-search" aria-label="Rechercher une fiche de culture">
            <form class="search-box" id="conseil-search-form" action="#" method="GET" role="search" novalidate>
                <label for="conseil-search-input" class="sr-only">Saisir le nom d'une orchidée ou d'une rubrique</label>
                <input type="text" id="conseil-search-input" name="search" placeholder="Saisir le nom d'une orchidée ..." autocomplete="off">
                <button type="submit" class="search-btn" id="conseil-search-btn">RECHERCHER</button>
            </form>
            <p id="conseil-search-help" class="conseil-search-help" aria-live="polite"></p>
        </section>

        <section class="conseils-grid" aria-label="Rubriques de conseils, cliquez pour consulter">
            <!-- Chaque carte a un data-conseil-id qui correspond à l'id dans conseils-data.js -->
            <article class="conseil-card" data-conseil-id="conseils-base" data-category="Conseils de base" tabindex="0" role="button" aria-label="Consulter les conseils de base">
                <h2>Conseils de base</h2>
                <p>Débutez votre collection d'Orchidées avec ces conseils de base. Selon vos affinités, vous évoluerez vers les épiphytes, terrestres ou hémi-épiphytes. Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-epiphytes" data-category="Pour les épiphytes" tabindex="0" role="button" aria-label="Consulter les conseils pour les épiphytes">
                <h2>Pour les épiphytes</h2>
                <p>Vous aimez les Orchidées épiphytes. Cette rubrique est faîtes pour vous. Vous y trouverez tous les conseils de culture pour réussir vos épiphytes, comme un professionnel ! Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-terrestres" data-category="Pour les terrestres" tabindex="0" role="button" aria-label="Consulter les conseils pour les terrestres">
                <h2>Pour les terrestres</h2>
                <p>Vous aimez les Orchidées terrestres. Cette rubrique est faîtes pour vous. Vous y trouverez tous les conseils de culture pour réussir vos terrestres, comme un professionnel ! Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-hemi-epiphytes" data-category="Pour les hémi-épiphytes" tabindex="0" role="button" aria-label="Consulter les conseils pour les hémi-épiphytes">
                <h2>Pour les hémi-épiphytes</h2>
                <p>Vous aimez les Orchidées hémi-épiphytes. Cette rubrique est faîtes pour vous. Vous y trouverez tous les conseils de culture pour réussir vos hémi-épiphytes, comme un professionnel ! Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-flask" data-category="Sortie de flask" tabindex="0" role="button" aria-label="Consulter les conseils sur la sortie de flask">
                <h2>Sortie de flask</h2>
                <p>Vous êtes un amateur orchidophile. Vous souhaitez cultiver des Orchidées sorties de flask ? Ici, vous trouverez tous nos conseils de pro pour réussir vos sorties de flask.</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-apres-achat" data-category="Après achat" tabindex="0" role="button" aria-label="Consulter les conseils d'après achat">
                <h2>Après achat</h2>
                <p>Vous venez d'acheter vos premières Orchidées ? Découvrez nos conseils d'après achat. Nous vous conseillons de consulter les conseils de base, par la suite, pour vous familiariser avec vos Orchidées.</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>
        </section>

        <section class="advice-catalog" id="advice-catalog" aria-label="Résultats de recherche">
            <h2 class="section-title">Fiches trouvées</h2>
            <div id="advice-results" class="advice-results" aria-live="polite" aria-label="Fiches de culture correspondantes"></div>
        </section>

        <?php require_once 'includes/footer.php'; ?>

        <?php require_once 'includes/modals/conseil-modal.php'; ?>
    </main>
</body>
</html>
