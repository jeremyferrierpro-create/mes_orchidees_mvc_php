<?php
/**
 * Vue : page « en construction » (Phase 02 → contenu métier en Phase 03).
 *
 * @var string $entite Nom de l'entité / page concernée.
 * @var string $note   Description de l'incrément à venir.
 */
?>
<section class="card">
    <h1><?= htmlspecialchars($entite ?? 'Page', ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Cette page est une <strong>coquille</strong> produite par le squelette MVC (Phase 02).</p>
    <p><?= htmlspecialchars($note ?? 'Implémentation du contenu prévue en Phase 03.', ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="/"><button>Retour à l'accueil</button></a></p>
</section>
