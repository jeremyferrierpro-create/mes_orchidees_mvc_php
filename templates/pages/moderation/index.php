<?php
/**
 * Vue : file d'attente de modération (/moderation, admin + modérateur).
 *
 * Rendue par ModerationController::index(). Variables :
 *  - $pending     : \App\Entity\Orchid[] (propositions is_published = 0)
 *  - $nbEnAttente : int
 *  - $csrf        : string
 *
 * Chaque proposition offre : édition complète, APPROUVER (publie + RG5 notifie l'auteur)
 * et REFUSER (supprime + RG5 notifie l'auteur).
 *
 * @package App\Templates\Pages\Moderation
 * @var \App\Entity\Orchid[] $pending
 * @var int $nbEnAttente
 * @var string $csrf
 */
?>
<section class="moderation-page">
    <h1 class="page-title">MODÉRATION</h1>
    <p class="collection-intro"><?= $nbEnAttente ?> proposition(s) en attente de validation.</p>
    <p><a class="btn-secondary" href="<?= e(url('/administration')) ?>">← Retour à l'administration</a></p>

    <?php if ($pending === []): ?>
        <p class="no-results">Aucune proposition à modérer. Tout est à jour.</p>
    <?php else: ?>
        <div class="orchid-grid">
            <?php foreach ($pending as $o): $img = $o->imageUrl(); ?>
                <article class="orchid-card moderation-card">
                    <?php if ($img !== null): ?>
                        <img class="card-img" src="<?= e($img) ?>" alt="<?= e($o->scientificName()) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="card-img card-img--placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                    <div class="card-info">
                        <h3><?= e($o->scientificName()) ?></h3>
                        <?php if ($o->vernacular): ?><p class="vernacular-name"><?= e($o->vernacular) ?></p><?php endif; ?>
                        <p class="short-desc"><?= e($o->shortDesc ?? '—') ?></p>

                        <div class="form-actions moderation-actions">
                            <a class="btn-secondary btn-mini" href="<?= e(url('/update/orchid/' . rawurlencode($o->id))) ?>">Éditer</a>
                            <form method="post" action="<?= e(url('/moderation/' . rawurlencode($o->id) . '/approve')) ?>" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-primary btn-mini">Approuver</button>
                            </form>
                            <form method="post" action="<?= e(url('/moderation/' . rawurlencode($o->id) . '/reject')) ?>"
                                  class="inline-form js-confirm-delete" data-confirm="Refuser et supprimer cette proposition ?">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-danger btn-mini">Refuser</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
