<?php
/**
 * Vue : liste des utilisateurs (/users, rôle admin).
 *
 * Rendue par UserController::index(). Variables :
 *  - $users : array[] (lignes users, sans hash)
 *  - $csrf  : string
 *
 * @package App\Templates\Pages\Admin
 * @var array[] $users
 * @var string $csrf
 */
$selfId = (string) (current_user()['id'] ?? '');
?>
<section class="admin-users">
    <h1 class="page-title">GESTION DES UTILISATEURS</h1>
    <p class="form-actions">
        <a class="btn-primary" href="<?= e(url('/user/new')) ?>">Nouvel utilisateur</a>
        <a class="btn-secondary" href="<?= e(url('/administration')) ?>">← Retour à l'administration</a>
    </p>

    <?php if ($users === []): ?>
        <p class="no-results">Aucun utilisateur.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscription</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?><?= $u['id'] === $selfId ? ' <span class="badge">vous</span>' : '' ?></td>
                    <td><?= e($u['email'] ?? '') ?></td>
                    <td><span class="badge badge--<?= e($u['role'] ?? 'user') ?>"><?= e($u['role'] ?? '') ?></span></td>
                    <td><?= e(substr((string) ($u['created_at'] ?? ''), 0, 10)) ?></td>
                    <td class="table-actions">
                        <a class="btn-secondary btn-mini" href="<?= e(url('/user/' . rawurlencode($u['id']))) ?>">Éditer</a>
                        <?php if ($u['id'] !== $selfId): ?>
                            <form method="post" action="<?= e(url('/user/delete/' . rawurlencode($u['id']))) ?>"
                                  class="inline-form js-confirm-delete" data-confirm="Supprimer définitivement cet utilisateur et toutes ses données ?">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-danger btn-mini">Supprimer</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
