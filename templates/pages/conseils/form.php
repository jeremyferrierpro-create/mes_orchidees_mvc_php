<?php
/**
 * Vue : formulaire de création / modification d'un conseil (admin).
 *
 * Rendu par ConseilController::newForm() et ::editForm(). Variables :
 *  - $conseil : App\Entity\Conseil (vide en création, peuplé en édition)
 *  - $mode    : 'create' | 'update'
 *  - $action  : URL cible du <form>
 *  - $csrf    : jeton CSRF (champ caché obligatoire)
 *
 * @package App\Templates\Pages\Conseils
 * @var \App\Entity\Conseil $conseil
 * @var string $mode
 * @var string $action
 * @var string $csrf
 * @var \App\Entity\CategorieConseil[] $categories
 */
$isUpdate = ($mode === 'update');
$field = function (string $name, string $label, ?string $value, array $opts = []): string {
    $id   = e($name);
    $val  = e((string) $value);
    $req  = !empty($opts['required']) ? ' required' : '';
    $type = $opts['type'] ?? 'text';
    $ph   = isset($opts['placeholder']) ? ' placeholder="' . e($opts['placeholder']) . '"' : '';
    $help = isset($opts['help']) ? '<small class="field-help">' . e($opts['help']) . '</small>' : '';
    $list = isset($opts['list'])  ? ' list="' . e($opts['list']) . '"' : '';
    return '<div class="form-field">'
        . '<label for="' . $id . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="' . $id . '" name="' . $id . '" value="' . $val . '"' . $req . $ph . $list . '>'
        . $help . '</div>';
};
$textarea = function (string $name, string $label, ?string $value): string {
    $id  = e($name);
    $val = e((string) $value);
    return '<div class="form-field">'
        . '<label for="' . $id . '">' . e($label) . '</label>'
        . '<textarea id="' . $id . '" name="' . $id . '" rows="6">' . $val . '</textarea></div>';
};
$careLabels = [
    'temperature' => 'Température',
    'arrosage'    => 'Arrosage',
    'hygrometrie' => 'Hygrométrie',
    'rempotage'   => 'Rempotage',
    'engrais'     => 'Engrais',
    'substrats'   => 'Substrat',
];
?>
<section class="orchid-form-wrap">
    <h1 class="page-title"><?= $isUpdate ? 'Modifier le conseil' : 'Nouveau conseil' ?></h1>

    <form method="post" action="<?= e($action) ?>" class="orchid-form conseil-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <?php if ($isUpdate): ?>
            <input type="hidden" name="id" value="<?= e($conseil->id) ?>">
        <?php endif; ?>

        <fieldset class="form-section">
            <legend>Identification</legend>
            <?= $field('name', 'Titre du conseil *', $conseil->name, ['required' => true, 'placeholder' => 'Pour les épiphytes']) ?>
            <div class="form-field">
                <label for="type">Type de conseil *</label>
                <select id="type" name="type">
                    <option value="category" <?= !$conseil->isSpecies() ? 'selected' : '' ?>>Conseil général (catégorie)</option>
                    <option value="species"  <?= $conseil->isSpecies() ? 'selected' : '' ?>>Fiche par espèce</option>
                </select>
            </div>
            <?= $field('category', 'Catégorie / libellé', $conseil->category, ['list' => 'liste-categories', 'help' => 'Sélectionnez une catégorie existante ou saisissez un nouveau libellé (le vocabulaire s\'enrichit automatiquement).']) ?>
            <datalist id="liste-categories">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat->label) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <?php if (!$isUpdate): ?>
                <?= $field('id', 'Identifiant (slug) — optionnel', null, ['help' => 'Vide = généré automatiquement (fiche-<espece> ou titre).']) ?>
            <?php endif; ?>
            <?= $field('img', "Chemin de l'image", $conseil->img, ['placeholder' => 'assets/images/site/epiphyte.jpg']) ?>
            <?= $field('orchid_id', 'Espèce concernée (id) — fiches espèce uniquement', $conseil->orchidId, ['help' => 'Laisser vide pour un conseil général. Doit exister dans l\'encyclopédie.']) ?>
        </fieldset>

        <fieldset class="form-section">
            <legend>Contenu</legend>
            <?= $textarea('content', 'Texte du conseil', $conseil->content) ?>
        </fieldset>

        <fieldset class="form-section">
            <legend>Carte de soins</legend>
            <?php foreach ($careLabels as $key => $label): ?>
                <?= $field('care_' . $key, $label, $conseil->careCards[$key] ?? null) ?>
            <?php endforeach; ?>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $isUpdate ? 'Enregistrer' : 'Créer le conseil' ?></button>
            <a href="<?= e($isUpdate ? url('/conseil/' . rawurlencode($conseil->id)) : url('/conseils')) ?>"
               class="btn-secondary">Annuler</a>
        </div>
    </form>
</section>
