<?php
/**
 * Vue : page Encyclopédie (grille serveur des orchidées publiées / résultats).
 *
 * Rendue par EncyclopedieController. Variables :
 *  - $orchids : App\Entity\Orchid[] (catalogue ou résultats de recherche)
 *  - $query   : string terme de recherche courant ('' si aucun)
 *  - $csrf    : jeton (réservé, la recherche est un GET simple)
 *
 * La recherche est un formulaire GET classique (reload serveur) : le conteneur
 * "orchid-grid-server" est volontairement distinct de "#orchid-grid-container" pour
 * que le script legacy app.js (peuplement JS) n'écrase pas le rendu serveur.
 *
 * @package App\Templates\Pages
 * @var \App\Entity\Orchid[] $orchids
 * @var string $query
 */
$cardComp = dirname(__DIR__) . '/components/orchid_card.php';
?>
<section class="encyclopedia-header" aria-label="Recherche dans l'Encyclopédie">
    <h1 class="page-title">ENCYCLOPÉDIE</h1>

    <div class="search-container">
        <form action="<?= e(url('/encyclopedie')) ?>" method="GET" class="search-box" role="search">
            <label for="search-input" class="sr-only">Rechercher une orchidée</label>
            <input type="search" id="search-input" name="search"
                   value="<?= e($query) ?>" placeholder="Rechercher une orchidée..." autocomplete="off">
            <button type="submit" class="search-btn">RECHERCHER</button>
        </form>
    </div>

    <?php if ($query !== ''): ?>
        <p class="search-summary" aria-live="polite">
            <?= count($orchids) ?> résultat<?= count($orchids) > 1 ? 's' : '' ?>
            pour « <?= e($query) ?> » —
            <a href="<?= e(url('/encyclopedie')) ?>">voir tout le catalogue</a>
        </p>
    <?php endif; ?>
</section>

<section class="encyclopedia-grid-container" aria-label="Résultats de la recherche">
    <?php if ($orchids === []): ?>
        <p class="no-results">Aucune orchidée ne correspond à votre recherche.</p>
    <?php else: ?>
        <div class="orchid-grid" id="orchid-grid-server">
            <?php foreach ($orchids as $orchid): ?>
                <?php include $cardComp; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
