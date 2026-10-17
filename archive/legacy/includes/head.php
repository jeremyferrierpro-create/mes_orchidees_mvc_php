<?php
/**
 * Composant Head dynamique avec SEO complet
 * 
 * @package MesOrchidées\Includes
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 * 
 * @param string $title Titre de la page
 * @param string $description Description SEO spécifique à la page
 * @param string $keywords Mots-clés (optionnel)
 * @param string $ogImage Image Open Graph (optionnel)
 * @param string $canonical URL canonique (optionnel)
 */

// Variables par défaut
$pageTitle = $title ?? 'Mes Orchidées';
$pageDescription = $description ?? 'Mes Orchidées : Encyclopédie et gestion de collections pour orchidophiles.';
$pageKeywords = $keywords ?? 'orchidées, orchidées collection, encyclopédie orchidées, culture orchidées';
$pageOgImage = $ogImage ?? './assets/images/site/orchidee_hero.webp';
$pageCanonical = $canonical ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<!-- SEO Basics -->
<title><?= htmlspecialchars($pageTitle) ?> - Mes Orchidées</title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
<meta name="keywords" content="<?= htmlspecialchars($pageKeywords) ?>">
<meta name="author" content="Jérémy Ferrier">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= htmlspecialchars($pageCanonical) ?>">

<!-- Open Graph -->
<meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
<meta property="og:image" content="<?= htmlspecialchars($pageOgImage) ?>">
<meta property="og:url" content="<?= htmlspecialchars($pageCanonical) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($pageOgImage) ?>">

<!-- Security & PWA -->
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self'; connect-src 'self'; worker-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests">
<meta name="theme-color" content="#0e2018">

<!-- Icons & Manifest -->
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/png" href="./assets/images/site/favicon.png">
<link rel="apple-touch-icon" href="./assets/images/site/logotransparent.webp">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

<!-- Styles -->
<link rel="stylesheet" href="./assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Structured Data (JSON-LD) - Organization -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "Mes Orchidées",
    "url": "https://mes-orchidees.fr",
    "description": "Encyclopédie et gestion de collections pour orchidophiles",
    "author": {
        "@type": "Person",
        "name": "Jérémy Ferrier"
    }
}
</script>
