<!DOCTYPE html>
<html lang="fr">
<head>
    <?php 
    $title = 'Mentions légales';
    $description = 'Mentions légales du site Mes Orchidées : éditeur, hébergement, crédits et informations juridiques.';
    $keywords = 'mentions légales, juridique, éditeur, hébergement, crédits';
    require_once 'includes/head.php'; 
    ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content text-content" id="main-content">
        <section class="legal-page" aria-label="Mentions légales">
            <h1 class="page-title">MENTIONS LÉGALES</h1>

            <article class="legal-section">
                <h2>Édition du site</h2>
                <p>Le site <strong>Mes Orchidées</strong> est un projet fil rouge réalisé dans le cadre de la formation DWWM (Développeur Web et Web Mobile).</p>
                <p>Responsable de la publication : Jérémy Ferrier.</p>
            </article>

            <article class="legal-section">
                <h2>Hébergement</h2>
                <p>Hébergement en phase de test. L'application est prévue pour être déployée sur un VPS ou une solution cloud telle que Supabase / Vercel / OVH.</p>
            </article>

            <article class="legal-section">
                <h2>Crédits</h2>
                <p>Les photographies et les descriptions botaniques sont utilisées à des fins pédagogiques et illustratives.</p>
            </article>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>

</body>
</html>

