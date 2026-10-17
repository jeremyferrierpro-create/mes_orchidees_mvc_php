<?php
/**
 * Vue : page Conseils (grille serveur des fiches de culture).
 *
 * Rendue par ConseilController::index(). Variables :
 *  - $conseils : App\Entity\Conseil[] (liste complète ou résultats de recherche)
 *  - $query    : string terme de recherche courant ('' si aucun)
 *
 * La recherche est un formulaire GET classique (reload serveur). Les conseils
 * généraux (type 'category') et les fiches par espèce (type 'species') partagent
 * la même carte ; le lien pointe vers la fiche durable /conseil/{id}.
 *
 * @package App\Templates\Pages\Conseils
 * @var \App\Entity\Conseil[] $conseils
 * @var string $query
 */
?>
<section class="conseils-header" aria-label="Recherche dans les conseils">
    <h1 class="page-title">CONSEILS DE CULTURE</h1>

    <div class="search-container">
        <form action="<?= e(url('/conseils')) ?>" method="GET" class="search-box" role="search">
            <label for="conseil-search" class="sr-only">Rechercher un conseil</label>
            <input type="search" id="conseil-search" name="search"
                   value="<?= e($query) ?>" placeholder="Rechercher un conseil (arrosage, épiphyte…)" autocomplete="off">
            <button type="submit" class="search-btn">RECHERCHER</button>
        </form>
    </div>

    <?php if ($query !== ''): ?>
        <p class="search-summary" aria-live="polite">
            <?= count($conseils) ?> résultat<?= count($conseils) > 1 ? 's' : '' ?>
            pour « <?= e($query) ?> » —
            <a href="<?= e(url('/conseils')) ?>">voir tous les conseils</a>
        </p>
    <?php endif; ?>

    <?php if (has_role(['admin'])): ?>
        <p class="conseils-admin-bar">
            <a class="btn-primary" href="<?= e(url('/conseil/new')) ?>">+ Nouveau conseil</a>
        </p>
    <?php endif; ?>
</section>

<section class="conseils-grid-container" aria-label="Liste des conseils">
    <?php if ($conseils === []): ?>
        <p class="no-results">Aucun conseil ne correspond à votre recherche.</p>
    <?php else: ?>
        <div class="orchid-grid" id="conseils-grid">
            <?php foreach ($conseils as $conseil): ?>
                <?php $imgUrl = $conseil->imageUrl(); ?>
                <article class="orchid-card" data-conseil-name="<?= e($conseil->name) ?>">
                    <?php if ($imgUrl !== null): ?>
                        <img class="card-img" src="<?= e($imgUrl) ?>" alt="Illustration : <?= e($conseil->name) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="card-img card-img--placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                    <div class="card-info">
                        <h3><?= e($conseil->name) ?></h3>
                        <p class="vernacular-name">
                            <?= $conseil->isSpecies() ? 'Fiche espèce' : e($conseil->category ?? 'Conseil général') ?>
                        </p>
                        <p class="short-desc"><?= e($conseil->category ?? '') ?></p>
                        <a class="card-btn" href="<?= e(url('/conseil/' . rawurlencode($conseil->id))) ?>">
                            LIRE<span class="sr-only"> : <?= e($conseil->name) ?></span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
