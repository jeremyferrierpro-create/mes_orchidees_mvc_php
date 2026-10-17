<?php
/**
 * Composant : carte d'orchidée pour la grille de l'encyclopédie.
 *
 * Reprend la structure HTML/CSS d'origine (app.js → .orchid-card) mais rendue côté
 * serveur. Le bouton "SÉLECTIONNER" pointe désormais vers la fiche durable
 * /orchid/{id} (rendu serveur) au lieu d'ouvrir la modale JS.
 *
 * Variable attendue : $orchid (App\Entity\Orchid).
 *
 * @package App\Templates\Components
 * @var \App\Entity\Orchid $orchid
 */
$imgUrl = $orchid->imageUrl();
?>
<article class="orchid-card" data-orchid-name="<?= e($orchid->name) ?>">
    <?php if ($imgUrl !== null): ?>
        <img class="card-img" src="<?= e($imgUrl) ?>" alt="Photographie de <?= e($orchid->name) ?>" loading="lazy">
    <?php else: ?>
        <span class="card-img card-img--placeholder" aria-hidden="true"></span>
    <?php endif; ?>

    <div class="card-info">
        <h3><?= e($orchid->name) ?></h3>
        <p class="vernacular-name"><?= e($orchid->vernacular ?? '') ?></p>
        <p class="short-desc"><?= e($orchid->shortDesc ?? '') ?></p>
        <a class="card-btn" href="<?= e(url('/orchid/' . rawurlencode($orchid->id))) ?>">
            SÉLECTIONNER<span class="sr-only"> : <?= e($orchid->scientificName()) ?></span>
        </a>
    </div>
</article>
