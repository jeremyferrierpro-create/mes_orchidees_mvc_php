<?php
/**
 * Vue : fiche détaillée d'une orchidée (page durable /orchid/{id}).
 *
 * Reprend la structure de la modale d'origine (orchid-modal.php) en page autonome,
 * indexable et accessible. Actions admin (modifier / supprimer) affichées si
 * l'utilisateur a le rôle admin ou modérateur.
 *
 * @package App\Templates\Pages\Orchids
 * @var \App\Entity\Orchid $orchid
 */
$imgUrl = $orchid->imageUrl();
$specs = [
    'Ordre'          => $orchid->botanicalOrder,
    'Espèce'         => $orchid->species,
    'Genre'          => $orchid->genre,
    'Famille'        => $orchid->family,
    'Sous-Famille'   => $orchid->subfamily,
    'Tribu'          => $orchid->tribu,
    'Sous-Tribu'     => $orchid->subtribu,
    'Comportement'   => $orchid->behavior,
    'Découverte par' => $orchid->discovered,
];
?>
<article class="orchid-detail">
    <nav class="detail-breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?= e(url('/encyclopedie')) ?>">← Retour à l'Encyclopédie</a>
    </nav>

    <header class="detail-header-block">
        <?php if ($imgUrl !== null): ?>
            <img class="detail-orchid-img" src="<?= e($imgUrl) ?>" alt="Photographie de <?= e($orchid->name) ?>">
        <?php endif; ?>
        <div class="detail-title-group">
            <h1 class="page-title"><?= e($orchid->name) ?></h1>
            <p class="detail-scientific"><?= e($orchid->scientificName()) ?></p>
            <?php if ($orchid->vernacular): ?>
                <p class="detail-vernacular"><?= e($orchid->vernacular) ?></p>
            <?php endif; ?>
            <?php if (!$orchid->isPublished): ?>
                <p class="badge badge--pending">En attente de modération</p>
            <?php endif; ?>
            <?php if ($orchid->shortDesc): ?>
                <h4 class="detail-label-desc">DESCRIPTION COURTE :</h4>
                <p class="detail-short-text"><?= e($orchid->shortDesc) ?></p>
            <?php endif; ?>
        </div>
    </header>

    <section class="detail-specs-grid" aria-label="Caractéristiques botaniques">
        <?php foreach ($specs as $label => $value): if ($value === null || $value === '') { continue; } ?>
            <div class="spec-item">
                <span class="spec-label"><?= e($label) ?></span>
                <div class="spec-value"><?= e($value) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if ($orchid->origin): ?>
            <div class="spec-item spec-item--full">
                <span class="spec-label">Origines</span>
                <div class="spec-value"><?= e($orchid->origin) ?></div>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($orchid->longDesc): ?>
        <section class="detail-description-block">
            <h2>Description &amp; Caractéristiques</h2>
            <p class="detail-long-desc"><?= e($orchid->longDesc) ?></p>
        </section>
    <?php endif; ?>

    <footer class="detail-actions">
        <a class="btn-primary" href="<?= e(url('/macollection')) ?>">+ Ajouter à ma collection</a>

        <?php if (has_role(['admin', 'moderateur'])): ?>
            <a class="btn-secondary" href="<?= e(url('/update/orchid/' . rawurlencode($orchid->id))) ?>">Modifier</a>
            <form method="post" action="<?= e(url('/orchid/delete/' . rawurlencode($orchid->id))) ?>"
                  class="inline-form js-confirm-delete"
                  data-confirm="Supprimer définitivement cette orchidée ?">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="btn-danger">Supprimer</button>
            </form>
        <?php endif; ?>
    </footer>
</article>
