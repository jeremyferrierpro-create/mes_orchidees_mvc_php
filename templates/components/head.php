<?php
/**
 * Composant : <head> avec SEO complet (repris de includes/head.php, adapté MVC).
 *
 * Variables lues : $pageTitle, $pageData['meta_description'], $pageData['meta_keywords'].
 *
 * @package App\Templates\Components
 */
$pageTitle    = $pageTitle    ?? 'Mes Orchidées';
$metaDesc     = $pageData['meta_description'] ?? 'Mes Orchidées : Encyclopédie et gestion de collections pour orchidophiles.';
$metaKeywords = $pageData['meta_keywords']    ?? 'orchidées, collection orchidées, encyclopédie orchidées, culture orchidées';
$ogImage      = $pageData['meta_og_image']    ?? asset('assets/images/site/orchidee_hero.webp');
$canonical    = (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . url($_SERVER['REQUEST_URI'] ?? '/');
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- SEO Basics -->
    <title><?= e($pageTitle) ?> - Mes Orchidées</title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="keywords" content="<?= e($metaKeywords) ?>">
    <meta name="author" content="Jérémy Ferrier">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= e($canonical) ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDesc) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <!-- Security & PWA -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self'; connect-src 'self'; worker-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests">
    <meta name="theme-color" content="#0e2018">

    <!-- Icons & Manifest -->
    <link rel="manifest" href="<?= e(asset('manifest.json')) ?>">
    <link rel="icon" type="image/png" href="<?= e(asset('assets/images/site/favicon.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('assets/images/site/logotransparent.webp')) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
