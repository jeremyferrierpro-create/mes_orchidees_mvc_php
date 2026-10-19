<?php
/**
 * Vue : page d'accueil (hero), rendue dans le layout.
 *
 * La barre de recherche pointe vers /encyclopedie?search= (rendu serveur), en lieu
 * et place de la recherche JS + modale d'origine.
 *
 * @package App\Templates\Pages
 */
?>
<section class="hero" aria-label="Présentation du projet">
    <div class="title-group">
        <h1 class="main-title">MES ORCHIDÉES</h1>
        <p class="sub-title">Le monde fascinant des Orchidées</p>
    </div>

    <div class="orchid-focus">
        <img src="<?= e(asset('assets/images/site/orchidee_hero.webp')) ?>"
             alt="Cattleya hybride blanc pur" id="hero-orchid" width="380" height="380" fetchpriority="high">
    </div>

    <div class="search-container">
        <form action="<?= e(url('/encyclopedie')) ?>" method="GET" class="search-box" role="search">
            <label for="search-input" class="sr-only">Rechercher une espèce d'orchidée par son nom</label>
            <input type="search" id="search-input" name="search" placeholder="Ex : Acacalis Cyanea..."
                   autocomplete="on" required>
            <button type="submit" class="search-btn">Rechercher</button>
        </form>
    </div>
</section>
