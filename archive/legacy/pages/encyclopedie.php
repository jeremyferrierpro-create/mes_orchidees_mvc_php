<!DOCTYPE html>
<!-- Page branchee sur /assets/js/data/*.json via db.js (simulation Supabase) - 100% dynamique -->
<html lang="fr">
<head>
    <?php 
    $title = 'Encyclopédie';
    $description = 'Encyclopédie botanique des orchidées : recherchez des espèces, découvrez leurs caractéristiques, origines et conseils de culture spécifiques.';
    $keywords = 'encyclopédie orchidées, botanique, espèces orchidées, recherche orchidées, caractéristiques';
    require_once 'includes/head.php'; 
    ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content" id="main-content">
        <section class="encyclopedia-header" aria-label="Recherche dans l'Encyclopédie">
            <h1 class="page-title">ENCYCLOPÉDIE</h1>
            <div class="search-container">
                <form id="encyclopedia-search-form" action="encyclopedie.php" method="GET" class="search-box" role="search">
                    <input type="hidden" name="csrf_token" id="csrf-token" value="">
                    <label for="search-input" class="sr-only">Rechercher une orchidée</label>
                    <input type="text" id="search-input" name="search" placeholder="Rechercher une orchidée..." autocomplete="off">
                    <button type="submit" class="search-btn">RECHERCHER</button>
                </form>
            </div>
        </section>

        <section class="encyclopedia-grid-container" aria-label="Résultats de la recherche">
            <div class="orchid-grid" id="orchid-grid-container"></div>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>

    <?php require_once 'includes/orchid-modal.php'; ?>

</body>
</html>



