<?php
/**
 * Vue : boîte de réception des notifications (/notifications, connecté uniquement).
 *
 * Rendue par NotificationController::index(). Variables :
 *  - $notifications : App\Entity\Notification[] (celles de l'utilisateur — isolation user_id)
 *  - $nbNonLues     : int   (compteur de notifications non lues)
 *  - $total         : int
 *  - $csrf          : string (jeton pour les formulaires de marquage/suppression)
 *
 * Chaque notification offre deux actions (POST, protégées CSRF) : marquer comme lue
 * et supprimer. L'isolation par utilisateur est appliquée côté repository.
 *
 * @package App\Templates\Pages\Notifications
 * @var \App\Entity\Notification[] $notifications
 * @var int $nbNonLues
 * @var int $total
 * @var string $csrf
 */
?>
<section class="notifications-page">
    <h1 class="page-title">MES NOTIFICATIONS</h1>
    <p class="collection-intro">
        <?= $total ?> notification<?= $total > 1 ? 's' : '' ?>
        <?php if ($nbNonLues > 0): ?>(<strong><?= $nbNonLues ?></strong> non lue<?= $nbNonLues > 1 ? 's' : '' ?>)<?php endif; ?>.
    </p>

    <?php if ($notifications === []): ?>
        <p class="no-results">Aucune notification pour le moment.</p>
    <?php else: ?>
        <ul class="notification-list">
            <?php foreach ($notifications as $n): ?>
                <li class="notification-item<?= $n->isUnread() ? ' notification-item--unread' : '' ?>">
                    <div class="notification-main">
                        <span class="notification-type"><?= e($n->typeLabel()) ?></span>
                        <?php if ($n->orchidName): ?>
                            <span class="notification-orchid"><?= e($n->orchidName) ?></span>
                        <?php endif; ?>
                        <p class="notification-message"><?= e($n->message) ?></p>
                        <span class="notification-date"><?= e($n->dateFr()) ?></span>
                    </div>
                    <div class="notification-actions">
                        <a class="btn-secondary btn-mini" href="<?= e(url('/notification/' . rawurlencode($n->id))) ?>">Voir</a>
                        <?php if ($n->isUnread()): ?>
                            <form method="post" action="<?= e(url('/notification/' . rawurlencode($n->id) . '/read')) ?>" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-secondary btn-mini">Marquer lu</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/notification/delete/' . rawurlencode($n->id))) ?>"
                              class="inline-form js-confirm-delete" data-confirm="Supprimer cette notification ?">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <button type="submit" class="btn-danger btn-mini">✕</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
