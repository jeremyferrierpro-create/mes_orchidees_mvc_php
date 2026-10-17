<!DOCTYPE html>
<!-- Page branchee sur /assets/js/data/*.json via db.js (simulation Supabase) - 100% dynamique -->
<html lang="fr">
<head>
    <?php 
    $title = 'Authentification';
    $description = 'Connectez-vous ou inscrivez-vous pour accéder à votre collection personnelle d\'orchidées sur Mes Orchidées.';
    $keywords = 'authentification, connexion, inscription, compte utilisateur, session';
    require_once 'includes/head.php'; 
    ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content" id="main-content">
        <section class="auth-header" aria-label="Authentification">
            <h1 class="page-title">AUTHENTIFICATION</h1>
            <p class="page-intro">Connectez-vous pour accéder à votre collection personnelle.</p>
        </section>

        <section class="auth-form-container" aria-label="Formulaire d'authentification">
            <div class="auth-toggle-group">
                <button type="button" id="btn-show-login" class="btn btn-primary" aria-pressed="true">Connexion</button>
                <button type="button" id="btn-show-register" class="btn btn-outline" aria-pressed="false">Inscription</button>
            </div>

            <!-- Formulaire de Connexion : 100% local en JavaScript (pas de PHP pour l'instant) -->
            <!-- J'ai retiré le PHP car tu n'as pas encore vu les bases de données. Tout reste dans le navigateur avec localStorage -->
            <form id="login-form" action="#" method="GET" class="auth-form" novalidate>
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" required autocomplete="email" placeholder="exemple@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="current-password" placeholder="Votre mot de passe">
                </div>

                <div class="form-group form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        Se souvenir de moi
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Se connecter</button>

                <div id="login-message" class="auth-message" role="status" aria-live="polite"></div>
            </form>

            <!-- Formulaire d'Inscription : 100% local aussi, pas de PHP -->
            <form id="register-form" action="#" method="GET" class="auth-form" novalidate hidden>
                <div class="form-group">
                    <label for="reg-email">Adresse email</label>
                    <input type="email" id="reg-email" name="email" required autocomplete="email" placeholder="exemple@email.com">
                </div>

                <div class="form-group">
                    <label for="reg-password">Mot de passe</label>
                    <input type="password" id="reg-password" name="password" required minlength="8" autocomplete="new-password" placeholder="Votre mot de passe">
                </div>
                
                <div class="form-group">
                    <label for="reg-password-confirm">Confirmer le mot de passe</label>
                    <input type="password" id="reg-password-confirm" name="password-confirm" required minlength="8" autocomplete="new-password" placeholder="Confirmez votre mot de passe">
                </div>

                <button type="submit" class="btn btn-primary">S'inscrire</button>

                <div id="register-message" class="auth-message" role="status" aria-live="polite"></div>
            </form>

            <p class="auth-note">
                Authentification locale (sans base de données).<br>
                Tes identifiants sont gardés dans ton navigateur (localStorage).<br>
                Plus tard, on remplacera par PHP + Supabase.
            </p>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>

</body>
</html>


