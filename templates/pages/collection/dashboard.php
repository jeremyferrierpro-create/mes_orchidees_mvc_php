<?php
/**
 * Vue : tableau de bord « Ma Collection » (/macollection, connecté uniquement).
 *
 * Rendue par CollectionController::dashboard(). Variables :
 *  - $collection : App\Entity\Collection[] (plantes de l'utilisateur — RG1)
 *  - $genres     : string[] (genres distincts de l'encyclopédie, pour le menu déroulant)
 *  - $families   : string[] (familles distinctes)
 *  - $behaviors  : string[] (comportements distincts)
 *  - $published  : \App\Entity\Orchid[] (espèces publiées de l'encyclopédie, pour l'autocomplétion)
 *  - $nbPlantes  : int
 *  - $user       : array|null (session courante)
 *  - $csrf       : string (jeton pour le formulaire d'ajout)
 *
 * @package App\Templates\Pages\Collection
 * @var \App\Entity\Collection[] $collection
 * @var string[] $genres
 * @var string[] $families
 * @var string[] $behaviors
 * @var \App\Entity\Orchid[] $published
 * @var int $nbPlantes
 * @var array|null $user
 * @var string $csrf
 */
?>
<section class="collection-page">
    <h1 class="page-title">MA COLLECTION</h1>
    <p class="collection-intro">
        Bonjour <?= e($user['prenom'] ?? '') ?>, vous cultivez
        <strong><?= $nbPlantes ?></strong> plante<?= $nbPlantes > 1 ? 's' : '' ?>.
    </p>

    <!-- Entrée « Gérer mes rappels » (Phase D) : lien vers /rappels qui devient
         ouverture de la modale quand features/rappels-modal.js est actif
         (progressive enhancement — sans JS, la page autonome prend le relais). -->
    <p class="collection-rappels-entry">
        <a class="btn-secondary" href="<?= e(url('/rappels')) ?>"
           data-rappels-modal-trigger aria-controls="rappels-modal" aria-haspopup="dialog">Gérer mes rappels</a>
    </p>

    <!-- Grille des plantes possédées -->
    <section class="collection-grid-container" aria-label="Mes plantes">
        <?php if ($collection === []): ?>
            <p class="no-results">Votre collection est vide. Ajoutez une première espèce ci-dessous.</p>
        <?php else: ?>
            <div class="orchid-grid">
                <?php foreach ($collection as $item): $img = $item->displayImageUrl(); ?>
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

    <!-- Ajout libre d'une orchidée à la collection (+ proposition d'encyclopédie) -->
    <section class="collection-add" aria-label="Ajouter une orchidée">
        <h2 class="section-title">Ajouter une orchidée</h2>
        <p class="field-help collection-add__hint">
            Saisissez librement le nom de votre orchidée. Les espèces déjà publiées dans
            l'encyclopédie s'affichent en autocomplétion : sélectionnez-les pour les lier
            directement à votre collection. Sinon, votre proposition est transmise à la
            modération. Dans tous les cas, la plante est enregistrée dans votre collection,
            photo comprise.
        </p>
        <form method="post" action="<?= e(url('/collection/new')) ?>" class="collection-form"
              enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <fieldset class="form-section">
                <legend>Identification</legend>
                <div class="form-field">
                    <label for="name">Nom de l'orchidée *</label>
                    <input type="text" id="name" name="name" required
                           placeholder="Ex : Phalaenopsis bellina"
                           list="liste-especes" autocomplete="off">
                    <datalist id="liste-especes">
                        <?php foreach ($published as $o): ?>
                            <option value="<?= e($o->name) ?>"><?= e($o->vernacular ?: $o->genre) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <small class="field-help">Commencez à saisir : les espèces publiées de l'encyclopédie s'affichent automatiquement. Sinon, laissez le nom libre pour proposer une nouvelle espèce.</small>
                </div>
                <div class="form-field">
                    <label for="vernacular">Nom vernaculaire</label>
                    <input type="text" id="vernacular" name="vernacular" placeholder="Ex : Orchidée lune bleue">
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Botanique <small>(requis pour proposer une nouvelle espèce)</small></legend>
                <div class="form-field">
                    <label for="genre">Genre *</label>
                    <select id="genre" name="genre" required>
                        <option value="">— Choisir un genre —</option>
                        <?php foreach ($genres as $g): ?>
                            <option value="<?= e($g) ?>"><?= e($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="family">Famille *</label>
                    <select id="family" name="family" required>
                        <option value="">— Choisir une famille —</option>
                        <?php foreach ($families as $f): ?>
                            <option value="<?= e($f) ?>"<?= $f === 'Orchidaceae' ? ' selected' : '' ?>><?= e($f) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="behavior">Comportement *</label>
                    <select id="behavior" name="behavior" required>
                        <option value="">— Choisir un comportement —</option>
                        <?php foreach ($behaviors as $b): ?>
                            <option value="<?= e($b) ?>"><?= e($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="origin">Origine géographique</label>
                    <input type="text" id="origin" name="origin" placeholder="Ex : Bornéo">
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Photo</legend>
                <div class="form-field">
                    <label for="photo">Photo de votre plante</label>
                    <input type="file" id="photo" name="photo" class="form-file"
                           accept="image/jpeg,image/png,image/webp,image/gif">
                    <small class="field-help">JPEG, PNG, WEBP ou GIF — 5 Mo maximum. Enregistrée dans votre collection ; visible dans l'encyclopédie seulement si la proposition est validée.</small>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Conditions de culture</legend>
                <div class="form-field">
                    <label for="location">Emplacement</label>
                    <input type="text" id="location" name="location" placeholder="Ex : Fenêtre Est du salon">
                </div>
                <div class="form-field">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Observations, date d'achat…"></textarea>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Ajouter à ma collection</button>
            </div>
        </form>
    </section>

    <?php // Modale « Gérer mes rappels » (Phase D) — squelette + fragment chargé à l'ouverture. ?>
    <?php include dirname(__DIR__, 2) . '/components/rappels_modal.php'; ?>
</section>
