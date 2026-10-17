<?php
/**
 * Vue : fiche détaillée d'un conseil (/conseil/{id}).
 *
 * Rendue par ConseilController::show(). Variables :
 *  - $conseil : App\Entity\Conseil
 *
 * Reprend la structure de la fiche orchidée (detail-*) : en-tête + image, contenu
 * rédactionnel, carte de soins (care_cards) en grille. Actions admin (modifier /
 * supprimer) affichées si l'utilisateur a le rôle admin.
 *
 * @package App\Templates\Pages\Conseils
 * @var \App\Entity\Conseil $conseil
 */
$imgUrl  = $conseil->imageUrl();
$careKeys = [
    'temperature' => 'Température',
    'arrosage'    => 'Arrosage',
    'hygrometrie' => 'Hygrométrie',
    'rempotage'   => 'Rempotage',
    'engrais'     => 'Engrais',
    'substrats'   => 'Substrat',
];
?>
<article class="orchid-detail conseil-detail">
    <nav class="detail-breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?= e(url('/conseils')) ?>">← Retour aux Conseils</a>
    </nav>

    <header class="detail-header-block">
        <?php if ($imgUrl !== null): ?>
            <img class="detail-orchid-img" src="<?= e($imgUrl) ?>" alt="Illustration : <?= e($conseil->name) ?>">
        <?php endif; ?>
        <div class="detail-title-group">
            <h1 class="page-title"><?= e($conseil->name) ?></h1>
            <p class="detail-vernacular">
                <?= $conseil->isSpecies() ? 'Fiche de culture par espèce' : e($conseil->category ?? 'Conseil général') ?>
            </p>
            <?php if ($conseil->isSpecies() && $conseil->orchidId): ?>
                <p class="detail-scientific">
                    Espèce concernée :
                    <a href="<?= e(url('/orchid/' . rawurlencode($conseil->orchidId))) ?>"><?= e($conseil->orchidId) ?></a>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($conseil->content): ?>
        <section class="detail-description-block">
            <h2>Conseils</h2>
            <p class="detail-long-desc"><?= nl2br(e($conseil->content)) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($conseil->careCards !== []): ?>
        <section class="detail-specs-grid" aria-label="Carte de soins">
            <?php foreach ($careKeys as $key => $label): $val = $conseil->careCards[$key] ?? null; if ($val === null || $val === '') { continue; } ?>
                <div class="spec-item">
                    <span class="spec-label"><?= e($label) ?></span>
                    <div class="spec-value"><?= e($val) ?></div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <footer class="detail-actions">
        <?php if (has_role(['admin'])): ?>
            <a class="btn-secondary" href="<?= e(url('/update/conseil/' . rawurlencode($conseil->id))) ?>">Modifier</a>
            <form method="post" action="<?= e(url('/conseil/delete/' . rawurlencode($conseil->id))) ?>"
                  class="inline-form js-confirm-delete"
                  data-confirm="Supprimer définitivement ce conseil ?">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="btn-danger">Supprimer</button>
            </form>
        <?php endif; ?>
    </footer>
</article>
