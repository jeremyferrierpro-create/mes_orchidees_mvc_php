<?php
/**
 * Vue : détail d'une notification (/notification/{id}).
 *
 * Rendue par NotificationController::show(). Variables :
 *  - $notification : App\Entity\Notification (appartient à l'utilisateur connecté)
 *  - $csrf         : string
 *
 * @package App\Templates\Pages\Notifications
 * @var \App\Entity\Notification $notification
 * @var string $csrf
 */
?>
<article class="orchid-detail notification-detail">
    <nav class="detail-breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?= e(url('/notifications')) ?>">← Retour aux notifications</a>
    </nav>

    <header class="detail-header-block">
        <div class="detail-title-group">
            <h1 class="page-title"><?= e($notification->typeLabel()) ?></h1>
            <?php if ($notification->orchidName): ?>
                <p class="detail-vernacular"><?= e($notification->orchidName) ?></p>
            <?php endif; ?>
            <p class="detail-scientific">Le <?= e($notification->dateFr()) ?></p>
        </div>
    </header>

    <section class="notification-body">
        <p><?= e($notification->message) ?></p>
        <?php if ($notification->collectionId !== null): ?>
            <p>
                <a class="card-btn" href="<?= e(url('/collection/' . rawurlencode((string) $notification->collectionId))) ?>">
                    Ouvrir la plante concernée
                </a>
            </p>
        <?php endif; ?>
    </section>

    <footer class="detail-actions">
        <form method="post" action="<?= e(url('/notification/delete/' . rawurlencode($notification->id))) ?>"
              class="inline-form js-confirm-delete" data-confirm="Supprimer cette notification ?">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button type="submit" class="btn-danger">Supprimer la notification</button>
        </form>
    </footer>
</article>
