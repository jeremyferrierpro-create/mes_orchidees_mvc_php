<?php
/**
 * Vue : formulaire d'inscription.
 *
 * @var string $csrf Jeton CSRF injecté par le contrôleur.
 */
?>
<section class="card card--narrow" aria-label="Formulaire d'inscription">
    <h1>Créer mon compte</h1>
    <form method="post" action="<?= e(url('/register')) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="field">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" required maxlength="255" autocomplete="given-name">
        </div>
        <div class="field">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" required maxlength="255" autocomplete="family-name">
        </div>
        <div class="field">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" required autocomplete="email">
        </div>
        <div class="field">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit">S'inscrire</button>
    </form>
    <p>Déjà un compte ? <a href="<?= e(url('/login')) ?>">Connectez-vous</a>.</p>
</section>
