<?php
/**
 * Vue : « Mes rappels de soins » (/rappels, connecté uniquement — RG3 étendu, refonte C0).
 *
 * Rendue par RappelController::index(). Variables :
 *  - $aujourdhui : \App\Entity\Notification[] rappels `actif` dus ce jour.
 *  - $enRetard   : \App\Entity\Notification[] rappels `actif` dont l'échéance est passée.
 *  - $aVenir     : \App\Entity\Notification[] rappels `planifie` (échéance future).
 *  - $historique : \App\Entity\Notification[] rappels `traite` ou `ignore`.
 *  - $nbAVenir   : int total des rappels ouverts (somme des trois premières listes).
 *  - $user       : array|null (session courante).
 *  - $csrf       : string (jeton pour les formulaires d'action).
 *
 * Le cycle de vie est piloté par 4 routes POST, toutes protégées CSRF + RG1 :
 *   /rappel/{id}/fait       → « Effectuer le soin » (C0) : consigne le soin réel,
 *                             clôture le rappel (`traite`) et replanifie le suivant.
 *   /rappel/{id}/ignorer    → statut 'ignore'
 *   /rappel/{id}/report/{d} → notif_date + d jours, statut 'planifie'
 *   /rappel/delete/{id}     → suppression définitive
 *
 * Phase D : le rendu des listes (cartes glass, sections, historique) est extrait
 * dans le partial `_liste.php`, partagé avec le fragment de la modale
 * (`pages/rappels/modal.php`). Cette page reste la solution de repli sans
 * JavaScript et la vue autonome de référence.
 *
 * @package App\Templates\Pages\Rappels
 * @var \App\Entity\Notification[] $aujourdhui
 * @var \App\Entity\Notification[] $enRetard
 * @var \App\Entity\Notification[] $aVenir
 * @var \App\Entity\Notification[] $historique
 * @var int $nbAVenir
 * @var array|null $user
 * @var string $csrf
 */
?>
<section class="rappels-page">
    <nav class="detail-breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?= e(url('/macollection')) ?>">← Retour à ma collection</a>
    </nav>

    <h1 class="page-title">MES RAPPELS DE SOINS</h1>

    <?php
    // Bilan chiffré : les compteurs découlent des mêmes listes que les sections.
    $bilan = [];
    if ($aujourdhui !== []) { $bilan[] = '<strong>' . count($aujourdhui) . '</strong> à faire aujourd\'hui'; }
    if ($enRetard !== [])   { $bilan[] = '<strong>' . count($enRetard) . '</strong> en retard'; }
    if ($aVenir !== [])     { $bilan[] = '<strong>' . count($aVenir) . '</strong> à venir'; }
    ?>
    <p class="rappels-intro">
        Bonjour <?= e($user['prenom'] ?? '') ?>,
        <?= $bilan === []
            ? 'aucun soin n\'est à prévoir pour le moment.'
            : 'vous avez ' . implode(' · ', $bilan) . '.' ?>
    </p>

    <?php include __DIR__ . '/_liste.php'; ?>
</section>
