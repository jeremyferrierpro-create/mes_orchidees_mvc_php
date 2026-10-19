<?php
/**
 * Vue : contenu du fragment « modale rappels » (GET /rappels/modal, Phase D).
 *
 * Rendue par RappelController::modal() via renderPartial() — injectée dans
 * `.rappels-modal__body` par `features/rappels-modal.js`. Le squelette de la
 * fenêtre (voile, titre, bouton de fermeture) est rendu serveur par le
 * composant `components/rappels_modal.php` ; ce fichier ne fournit que le
 * corps, réutilisant intégralement le partial `_liste.php` (cartes glass,
 * sections, compteurs, historique) de la page autonome /rappels.
 *
 * Échappement : produit par les mêmes vues serveur que /rappels (`e()`),
 * jamais par concaténation côté client.
 *
 * @package App\Templates\Pages\Rappels
 * @var \App\Entity\Notification[] $aujourdhui
 * @var \App\Entity\Notification[] $enRetard
 * @var \App\Entity\Notification[] $aVenir
 * @var \App\Entity\Notification[] $historique
 * @var array|null $user
 * @var string $csrf
 */
?>
<p class="rappels-modal__bilan">
    <?php
    $nbOuverts = count($aujourdhui) + count($enRetard) + count($aVenir);
    if ($nbOuverts === 0) {
        echo 'Aucun rappel ouvert pour le moment.';
    } else {
        $parties = [];
        if ($aujourdhui !== []) { $parties[] = count($aujourdhui) . ' à faire aujourd\'hui'; }
        if ($enRetard !== [])   { $parties[] = count($enRetard) . ' en retard'; }
        if ($aVenir !== [])     { $parties[] = count($aVenir) . ' à venir'; }
        echo 'Vous avez ' . e(implode(' · ', $parties)) . '.';
    }
    ?>
</p>

<?php include __DIR__ . '/_liste.php'; ?>

<p class="rappels-modal__lien">
    <a href="<?= e(url('/rappels')) ?>">Ouvrir la page complète de gestion des rappels →</a>
</p>
