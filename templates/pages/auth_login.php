<?php
/**
 * Vue : formulaire de connexion.
 *
 * @var string $csrf Jeton CSRF injecté par le contrôleur.
 */
?>
<section class="card" style="max-width:420px;margin:0 auto;">
    <h1>Connexion</h1>
    <form method="post" action="/login">
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
    <p>Pas encore de compte ? <a href="/register">Inscrivez-vous</a>.</p>
</section>
