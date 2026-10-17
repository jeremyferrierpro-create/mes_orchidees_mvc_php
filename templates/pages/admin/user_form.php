<?php
/**
 * Vue : formulaire de création / édition d'un utilisateur (admin).
 *
 * Rendue par UserController::newForm() et ::show(). Variables :
 *  - $user   : array (id, nom, prenom, email, role)
 *  - $mode   : 'create' | 'update'
 *  - $action : string (URL du POST, /update/user)
 *  - $csrf   : string
 *
 * Le même endpoint /update/user gère la création (id vide) et la mise à jour (id présent).
 * Le mot de passe n'est demandé qu'à la création (jamais relu en édition).
 *
 * @package App\Templates\Pages\Admin
 * @var array $user
 * @var string $mode
 * @var string $action
 * @var string $csrf
 */
$roles = ['user', 'moderateur', 'admin'];
?>
<section class="admin-user-form">
    <h1 class="page-title"><?= $mode === 'create' ? 'NOUVEL UTILISATEUR' : 'MODIFIER L\'UTILISATEUR' ?></h1>
    <p><a class="btn-secondary" href="<?= e(url('/users')) ?>">← Retour à la liste</a></p>

    <form method="post" action="<?= e($action) ?>" class="collection-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= e($user['id'] ?? '') ?>">

        <div class="form-field">
            <label for="prenom">Prénom *</label>
            <input type="text" id="prenom" name="prenom" value="<?= e($user['prenom'] ?? '') ?>" required>
        </div>
        <div class="form-field">
            <label for="nom">Nom *</label>
            <input type="text" id="nom" name="nom" value="<?= e($user['nom'] ?? '') ?>" required>
        </div>
        <div class="form-field">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
        </div>

        <?php if ($mode === 'create'): ?>
            <div class="form-field">
                <label for="password">Mot de passe *</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
                <small>Haché automatiquement (password_hash) — jamais stocké en clair.</small>
            </div>
        <?php endif; ?>

        <div class="form-field">
            <label for="role">Rôle *</label>
            <select id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r) ?>" <?= ($user['role'] ?? 'user') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $mode === 'create' ? 'Créer l\'utilisateur' : 'Enregistrer' ?></button>
        </div>
    </form>
</section>
