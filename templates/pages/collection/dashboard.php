<?php
/**
 * Vue : tableau de bord « Ma Collection » (/macollection, connecté uniquement).
 *
 * Rendue par CollectionController::dashboard(). Variables :
 *  - $collection : App\Entity\Collection[] (plantes de l'utilisateur — RG1)
 *  - $orchids    : App\Entity\Orchid[] (especes publiées pour le selecteur d'ajout)
 *  - $nbPlantes  : int
 *  - $user       : array|null (session courante)
 *  - $csrf       : string (jeton pour les formulaires d'ajout)
 *
 * @package App\Templates\Pages\Collection
 * @var \App\Entity\Collection[] $collection
 * @var \App\Entity\Orchid[] $orchids
 * @var int $nbPlantes
 * @var array|null $user
 * @var string $csrf
 */
?>
<section class="collection-dashboard">
    <h1 class="page-title">MA COLLECTION</h1>
    <p class="collection-intro">
        Bonjour <?= e($user['prenom'] ?? '') ?>, vous cultivez
        <strong><?= $nbPlantes ?></strong> plante<?= $nbPlantes > 1 ? 's' : '' ?>.
    </p>

    <!-- Grille des plantes possédées -->
    <section class="collection-grid-container" aria-label="Mes plantes">
        <?php if ($collection === []): ?>
            <p class="no-results">Votre collection est vide. Ajoutez une première espèce ci-dessous.</p>
        <?php else: ?>
            <div class="orchid-grid">
                <?php foreach ($collection as $item): $img = $item->orchidImageUrl(); ?>
                    <article class="orchid-card collection-card">
                        <?php if ($img !== null): ?>
                            <img class="card-img" src="<?= e($img) ?>" alt="<?= e($item->displayName()) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="card-img card-img--placeholder" aria-hidden="true"></span>
                        <?php endif; ?>
                        <div class="card-info">
                            <h3><?= e($item->displayName()) ?></h3>
                            <p class="vernacular-name"><?= e($item->orchidVernacular ?? '') ?></p>
                            <p class="short-desc"><?= e($item->location ?? 'Emplacement non précisé') ?></p>
                            <a class="card-btn" href="<?= e(url('/collection/' . rawurlencode($item->id))) ?>">
                                SUIVI<span class="sr-only"> : <?= e($item->displayName()) ?></span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Ajout d'une plante à la collection -->
    <section class="collection-add" aria-label="Ajouter une plante">
        <h2 class="section-title">Ajouter une orchidée</h2>
        <?php if ($orchids === []): ?>
            <p>Aucune espèce publiée disponible pour le moment.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('/collection/new')) ?>" class="collection-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <div class="form-field">
                    <label for="orchid_id">Espèce *</label>
                    <select id="orchid_id" name="orchid_id" required>
                        <option value="">— Choisir une espèce —</option>
                        <?php foreach ($orchids as $o): ?>
                            <option value="<?= e($o->id) ?>"><?= e($o->name ?: $o->scientificName()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="location">Emplacement</label>
                    <input type="text" id="location" name="location" placeholder="Ex : Fenêtre Est du salon">
                </div>
                <div class="form-field">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Observations, date d'achat…"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Ajouter à ma collection</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</section>
