<?php
/**
 * Vue : formulaire de connexion.
 *
 * @var string $csrf Jeton CSRF injecté par le contrôleur.
 */
?>
<section class="card card--narrow" aria-label="Formulaire de connexion">
    <h1>Connexion</h1>
    <form method="post" action="<?= e(url('/login')) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="field">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" required autocomplete="email">
        </div>
        <div class="field">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit">Se connecter</button>
    </form>
    <p>Pas encore de compte ? <a href="<?= e(url('/register')) ?>">Inscrivez-vous</a>.</p>
</section>
