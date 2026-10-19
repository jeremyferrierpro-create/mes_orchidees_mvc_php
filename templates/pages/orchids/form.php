<?php
/**
 * Vue : formulaire de création / modification d'une orchidée (admin).
 *
 * Rendu par OrchidController::newForm() et ::editForm(). Variables :
 *  - $orchid : App\Entity\Orchid (vide en création, peuplé en édition)
 *  - $mode   : 'create' | 'update'
 *  - $action : URL cible du <form>
 *  - $csrf   : jeton CSRF (champ caché obligatoire)
 *
 * @package App\Templates\Pages\Orchids
 * @var \App\Entity\Orchid $orchid
 * @var string $mode
 * @var string $action
 * @var string $csrf
 */
$isUpdate = ($mode === 'update');
$field = function (string $name, string $label, ?string $value, array $opts = []): string {
    $id    = e($name);
    $val   = e((string) $value);
    $req   = !empty($opts['required']) ? ' required' : '';
    $type  = $opts['type'] ?? 'text';
    $ph    = isset($opts['placeholder']) ? ' placeholder="' . e($opts['placeholder']) . '"' : '';
    $help  = isset($opts['help']) ? '<small class="field-help">' . e($opts['help']) . '</small>' : '';
    return '<div class="form-field">'
        . '<label for="' . $id . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="' . $id . '" name="' . $id . '" value="' . $val . '"' . $req . $ph . '>'
        . $help . '</div>';
};
$textarea = function (string $name, string $label, ?string $value): string {
    $id  = e($name);
    $val = e((string) $value);
    return '<div class="form-field">'
        . '<label for="' . $id . '">' . e($label) . '</label>'
        . '<textarea id="' . $id . '" name="' . $id . '" rows="4">' . $val . '</textarea></div>';
};
?>
<section class="orchid-form-wrap">
    <h1 class="page-title"><?= $isUpdate ? 'Modifier l\'orchidée' : 'Nouvelle orchidée' ?></h1>

    <form method="post" action="<?= e($action) ?>" class="orchid-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <?php if ($isUpdate): ?>
            <input type="hidden" name="id" value="<?= e($orchid->id) ?>">
        <?php endif; ?>

        <fieldset class="form-section">
            <legend>Identification</legend>
            <?= $field('name', 'Nom scientifique *', $orchid->name, ['required' => true, 'placeholder' => 'Acacalis cyanea']) ?>
            <?= $field('vernacular', 'Nom vernaculaire', $orchid->vernacular) ?>
            <?= $field('genre', 'Genre *', $orchid->genre, ['required' => true]) ?>
            <?= $field('species', 'Espèce', $orchid->species) ?>
            <?= $field('family', 'Famille', $orchid->family ?: 'Orchidaceae') ?>
        </fieldset>

        <fieldset class="form-section">
            <legend>Taxonomie</legend>
            <?= $field('botanical_order', 'Ordre botanique', $orchid->botanicalOrder) ?>
            <?= $field('subfamily', 'Sous-famille', $orchid->subfamily) ?>
            <?= $field('tribu', 'Tribu', $orchid->tribu) ?>
            <?= $field('subtribu', 'Sous-tribu', $orchid->subtribu) ?>
        </fieldset>

        <fieldset class="form-section">
            <legend>Caractéristiques</legend>
            <?= $field('behavior', 'Comportement (épiphyte, terrestre…)', $orchid->behavior) ?>
            <?= $field('discovered', 'Découverte par', $orchid->discovered) ?>
            <?= $field('origin', 'Origine géographique', $orchid->origin) ?>
            <?= $field('img', 'Chemin de l\'image', $orchid->img, ['placeholder' => 'assets/images/orchids/xxx.webp', 'help' => 'Chemin de l\'image, relatif à la racine du site.']) ?>
        </fieldset>

        <fieldset class="form-section">
            <legend>Descriptions</legend>
            <?= $textarea('short_desc', 'Description courte', $orchid->shortDesc) ?>
            <?= $textarea('long_desc', 'Description détaillée', $orchid->longDesc) ?>
        </fieldset>

        <?php if ($isUpdate): ?>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="is_published" name="is_published" value="1"
                    <?= $orchid->isPublished ? 'checked' : '' ?>>
                <label for="is_published">Fiche publiée (visible dans l'encyclopédie)</label>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $isUpdate ? 'Enregistrer' : 'Créer la fiche' ?></button>
            <a href="<?= e($isUpdate ? url('/orchid/' . rawurlencode($orchid->id)) : url('/encyclopedie')) ?>"
               class="btn-secondary">Annuler</a>
        </div>
    </form>
</section>
