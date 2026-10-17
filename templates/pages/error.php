<?php
/**
 * Vue : page d'erreur (404 / 403 / 405 / 501 / 500), rendue dans le layout.
 *
 * @var int    $status  Code HTTP.
 * @var string $message Message court et sûr (aucune fuite technique).
 */
?>
<section class="content-page error-page">
    <h1 class="page-title">Erreur <?= (int) ($status ?? 500) ?></h1>
    <p><?= e($message ?? 'Une erreur est survenue.') ?></p>
    <p><a class="btn-primary" href="<?= e(url('/')) ?>">Retour à l'accueil</a></p>
</section>
