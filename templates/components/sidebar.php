<?php
/**
 * Composant : barre latérale de navigation (repris de includes/sidebar.php, adapté MVC).
 *
 * Liens vers les routes du front-controller ; bloc admin réservé admin/modérateur,
 * bloc session selon l'état de connexion. Relief de la page courante via aria-current.
 *
 * @package App\Templates\Components
 */
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$current = '/' . ltrim($uri, '/');
$nav = function (string $path) use ($current): string {
    return $path === $current ? ' aria-current="page"' : '';
};
?>
    <nav id="main-sidebar" class="sidebar-container" aria-label="Menu principal">
        <div class="sidebar-brand">
            <a href="<?= e(url('/')) ?>">
                <img src="<?= e(asset('assets/images/site/logotransparent.webp')) ?>" alt="Logo de Mes Orchidées" width="60" height="60">
                <h2>Mes Orchidées</h2>
            </a>
        </div>
        <div class="sidebar-menu">
            <ul class="menu-group-main">
                <li><a href="<?= e(url('/')) ?>"<?= $nav('/') ?>"><i class="fa-solid fa-home" aria-hidden="true"></i> Accueil</a></li>
                <li><a href="<?= e(url('/encyclopedie')) ?>"<?= $nav('/encyclopedie') ?>"><i class="fa-solid fa-book" aria-hidden="true"></i> Encyclopédie</a></li>
                <li><a href="<?= e(url('/macollection')) ?>"<?= $nav('/macollection') ?>"><i class="fa-solid fa-seedling" aria-hidden="true"></i> Ma collection</a></li>
                <li><a href="<?= e(url('/conseils')) ?>"<?= $nav('/conseils') ?>"><i class="fa-solid fa-heart" aria-hidden="true"></i> Conseils</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="<?= e(url('/notifications')) ?>"<?= $nav('/notifications') ?>"><i class="fa-solid fa-bell" aria-hidden="true"></i> Notifications</a></li>
                <?php endif; ?>
            </ul>
            <ul class="menu-group-user">
                <?php if (has_role('admin')): ?>
                    <li><a href="<?= e(url('/administration')) ?>"<?= $nav('/administration') ?>"><i class="fa-solid fa-gear" aria-hidden="true"></i> Administration</a></li>
                <?php endif; ?>
                <?php if (has_role(['admin', 'moderateur'])): ?>
                    <li><a href="<?= e(url('/moderation')) ?>"<?= $nav('/moderation') ?>"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Modération</a></li>
                <?php endif; ?>
                <?php if (is_logged_in()): ?>
                    <li>
                        <form method="post" action="<?= e(url('/logout')) ?>" class="logout-form">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="link-as-button"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Déconnexion</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="<?= e(url('/login')) ?>"<?= $nav('/login') ?>"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Authentification</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
