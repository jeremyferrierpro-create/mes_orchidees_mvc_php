<?php
/**
 * Gabarit global (layout) de l'application — design d'origine (sidebar + header + footer).
 *
 * Injecté par AbstractController::render(). Variables attendues :
 *  - $pageTitle : titre de page (fallback $title)
 *  - $content   : HTML de la vue déjà rendue
 *  - $flashes   : messages flash (array de ['type','message'])
 *  - $pageData  : meta SEO éventuels (meta_description, meta_keywords…)
 *
 * Les composants (head, sidebar, header, footer) sont dans templates/components/.
 *
 * @package App\Templates
 */
$pageTitle = $pageTitle ?? ($title ?? 'Mes Orchidées');
$flashes   = $flashes   ?? [];
$pageData  = $pageData  ?? [];
$comp      = __DIR__ . '/components';

// Le voile de noms latins est la signature poétique de l'ACCUEIL uniquement
// (cf. site statique d'origine : #latin-bg-layer n'existe que dans index.html).
// On le rend donc conditionnellement, sinon les mots flotteraient sur toutes
// les pages de travail (encyclopédie, collection, administration).
$uri        = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base       = base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$isHome = ('/' . ltrim($uri, '/')) === '/';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
<?php include $comp . '/head.php'; ?>
</head>

<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

<?php include $comp . '/sidebar.php'; ?>
<?php include $comp . '/header.php'; ?>

    <div class="loader" id="loader" aria-hidden="true"></div>
    <?php if ($isHome): ?>
        <div id="latin-bg-layer" class="latin-bg" aria-hidden="true"></div>
    <?php endif; ?>

    <main class="main-content" id="main-content" role="main">
        <?php if ($flashes !== []): ?>
            <div class="flash-messages" aria-live="polite">
                <?php foreach ($flashes as $flash): ?>
                    <div class="flash flash--<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message'] ?? '') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

<?php include $comp . '/footer.php'; ?>

</body>
</html>
