<!DOCTYPE html>
<!-- Cette page utilise /assets/data/*.json via db.js (simulation Supabase) - 100% dynamique -->
<html lang="fr">

<head>
    <?php 
    $title = 'Accueil';
    $description = 'Mes Orchidées : Le monde fascinant des orchidées. Encyclopédie botanique, gestion de collections personnelles et conseils de culture pour orchidophiles.';
    $keywords = 'orchidées, encyclopédie orchidées, collection orchidées, conseils culture orchidées, botanique';
    require_once 'includes/head.php'; 
    ?>
</head>

<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <div class="loader" id="loader"></div>

    <div id="latin-bg-layer" class="latin-bg" aria-hidden="true"></div>

    <main class="main-content" id="main-content">
        <section class="hero" aria-label="Présentation du projet">
            <div class="title-group">
                <h1 class="main-title">MES ORCHIDÉES</h1>
                <p class="sub-title">Le monde fascinant des Orchidées</p>
            </div>

            <div class="orchid-focus">
                <img src="./assets/images/site/orchidee_hero.webp" alt="Cattleya hybride blanc pur" id="hero-orchid"
                    width="380" height="380" fetchpriority="high">
            </div>

            <div class="search-container">
                <form id="landing-search-form" class="search-box" role="search" onsubmit="event.preventDefault();">
                    <input type="hidden" name="csrf_token" id="csrf-token" value="">
                    <label for="search-input" class="sr-only">Rechercher une espèce d'orchidée par son nom</label>
                    <input type="text" id="search-input" name="search" placeholder="Ex : Acacalis Cyanea..."
                        autocomplete="on" required>
                    <button type="submit" class="search-btn">Rechercher</button>
                </form>
            </div>
        </section>

    </main>

    <?php require_once 'includes/search-results-modal.php'; ?>
    <?php require_once 'includes/orchid-modal.php'; ?>
    <?php require_once 'includes/footer.php'; ?>