<?php
/**
 * Vue : tableau de bord d'administration (/administration, rôle admin).
 *
 * Rendue par AdminController::dashboard(). Variables :
 *  - $stats   : array  (compteurs de plateforme — StatsRepository::platformStats())
 *  - $parRole : array  (répartition des utilisateurs par rôle)
 *  - $pending : \App\Entity\Orchid[] (propositions en attente de modération)
 *  - $user    : array|null (session courante)
 *  - $csrf    : string
 *
 * @package App\Templates\Pages\Admin
 * @var array $stats
 * @var array $parRole
 * @var \App\Entity\Orchid[] $pending
 * @var array|null $user
 * @var string $csrf
 */
$cartes = [
    ['Utilisateurs', $stats['users'], url('/users')],
    ['Orchidées publiées', $stats['orchids_published'], url('/encyclopedie')],
    ['En attente de modération', $stats['orchids_pending'], url('/moderation')],
    ['Conseils', $stats['conseils'], url('/conseils')],
    ['Plantes en collection', $stats['collections'], url('/macollection')],
    ['Soins enregistrés', $stats['soins'], url('/soins')],
    ['Notifications', $stats['notifications'], url('/notifications')],
    ['Notifications non lues', $stats['notifications_unread'], url('/notifications')],
];
?>
<section class="admin-dashboard">
    <h1 class="page-title">ADMINISTRATION</h1>
    <p class="collection-intro">
        Bonjour <?= e($user['prenom'] ?? '') ?> — vue d'ensemble de la plateforme.
    </p>

    <!-- Statistiques -->
    <div class="stats-grid">
        <?php foreach ($cartes as [$label, $valeur, $lien]): ?>
            <a class="stat-card" href="<?= e($lien) ?>">
                <span class="stat-value"><?= (int) $valeur ?></span>
                <span class="stat-label"><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Répartition par rôle -->
    <section class="admin-section" aria-label="Utilisateurs par rôle">
        <h2 class="section-title">Comptes par rôle</h2>
        <ul class="role-list">
            <li>Utilisateurs : <strong><?= (int) ($parRole['user'] ?? 0) ?></strong></li>
            <li>Modérateurs : <strong><?= (int) ($parRole['moderateur'] ?? 0) ?></strong></li>
            <li>Administrateurs : <strong><?= (int) ($parRole['admin'] ?? 0) ?></strong></li>
        </ul>
    </section>

    <!-- Actions rapides -->
    <section class="admin-section" aria-label="Actions rapides">
        <h2 class="section-title">Gestion</h2>
        <div class="form-actions">
            <a class="btn-primary" href="<?= e(url('/users')) ?>">Gérer les utilisateurs</a>
            <a class="btn-primary" href="<?= e(url('/user/new')) ?>">Nouvel utilisateur</a>
            <a class="btn-secondary" href="<?= e(url('/moderation')) ?>">Modération (<?= $stats['orchids_pending'] ?>)</a>
            <a class="btn-secondary" href="<?= e(url('/orchid/new')) ?>">Nouvelle orchidée</a>
            <a class="btn-secondary" href="<?= e(url('/conseil/new')) ?>">Nouveau conseil</a>
        </div>
    </section>

    <!-- file d'attente de modération (aperçu) -->
    <section class="admin-section" aria-label="Propositions en attente">
        <h2 class="section-title">Propositions en attente</h2>
        <?php if ($pending === []): ?>
            <p class="no-results">Aucune proposition en attente.</p>
        <?php else: ?>
            <ul class="care-list">
                <?php foreach ($pending as $o): ?>
                    <li class="care-item">
                        <span class="care-type">Nouvelle espèce</span>
                        <span class="care-notes"><?= e($o->scientificName()) ?></span>
                        <a class="btn-secondary btn-mini" href="<?= e(url('/update/orchid/' . rawurlencode($o->id))) ?>">Éditer</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a class="btn-primary" href="<?= e(url('/moderation')) ?>">Ouvrir la modération</a></p>
    </section>
</section>
