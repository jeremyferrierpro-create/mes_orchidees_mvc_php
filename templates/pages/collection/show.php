<?php
/**
 * Vue : fiche d'une plante de la collection (/collection/{id}) + suivi des soins.
 *
 * Rendue par CollectionController::show(). Variables :
 *  - $item  : App\Entity\Collection (appartient à l'utilisateur connecté — RG1)
 *  - $soins : App\Entity\Soin[]     (historique de soins de cette plante)
 *  - $prochainRappel : ?App\Entity\Notification (rappel à venir pour cette plante)
 *  - $csrf  : string                (jeton pour tous les formulaires)
 *
 * Trois actions : modifier les conditions de culture, enregistrer un soin,
 * supprimer la plante. Toutes passent par des routes authentifiées ; l'isolation
 * RG1 est appliquée côté repository (WHERE user_id).
 *
 * @package App\Templates\Pages\Collection
 * @var \App\Entity\Collection $item
 * @var \App\Entity\Soin[] $soins
 * @var \App\Entity\TypeSoin[] $typesSoin
 * @var \App\Entity\Notification|null $prochainRappel
 * @var string $csrf
 */
$img = $item->displayImageUrl();
?>
<article class="orchid-detail collection-detail">
    <nav class="detail-breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?= e(url('/macollection')) ?>">← Retour à ma collection</a>
    </nav>

    <header class="detail-header-block">
        <?php if ($img !== null): ?>
            <img class="detail-orchid-img" src="<?= e($img) ?>" alt="<?= e($item->displayName()) ?>">
        <?php endif; ?>
        <div class="detail-title-group">
            <h1 class="page-title"><?= e($item->displayName()) ?></h1>
            <?php if ($item->orchidVernacular): ?>
                <p class="detail-vernacular"><?= e($item->orchidVernacular) ?></p>
            <?php endif; ?>
            <p class="detail-scientific">
                <a href="<?= e(url('/orchid/' . rawurlencode($item->orchidId))) ?>">Voir la fiche Encyclopédie</a>
            </p>
        </div>
    </header>

    <!-- Bandeau rappel à venir (RG3) — source unique de vérité : collections.rappel_actif
         (case du formulaire de soin ci-dessous). L'état affiché découle du duo
         {rappel_actif, prochain rappel ouvert} : aucun second interrupteur. -->
    <?php if ($prochainRappel !== null): ?>
        <section class="rappel-bandeau rappel-bandeau--<?= e($prochainRappel->statut) ?>" aria-label="Prochain rappel de soin">
            <p class="rappel-bandeau__texte">
                <strong><?= e($prochainRappel->isLate() ? 'Rappel en retard' : ($prochainRappel->statut === 'actif' ? 'Rappel dû aujourd\'hui' : 'Prochain rappel')) ?></strong>
                <?php if ($prochainRappel->typeSoinLabel !== null): ?>
                    <span class="rappel-badge rappel-badge--soin"><?= e(ucfirst($prochainRappel->typeSoinLabel)) ?></span>
                <?php endif; ?>
                — <?= e($prochainRappel->message) ?>
            </p>
            <div class="rappel-bandeau__actions">
                <?php if ($prochainRappel->statut === 'actif' || $prochainRappel->statut === 'planifie'): ?>
                    <form method="post" action="<?= e(url('/rappel/' . rawurlencode($prochainRappel->id) . '/fait')) ?>" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn-primary btn-mini">✓ Effectuer le soin</button>
                    </form>
                    <form method="post" action="<?= e(url('/rappel/' . rawurlencode($prochainRappel->id) . '/report/7')) ?>" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn-secondary btn-mini">Reporter +7 j</button>
                    </form>
                <?php endif; ?>
                <a class="btn-secondary btn-mini" href="<?= e(url('/rappels')) ?>"
                   data-rappels-modal-trigger aria-controls="rappels-modal" aria-haspopup="dialog">Gérer mes rappels</a>
                <a class="btn-link" href="<?= e(url('/rappels')) ?>">Tous mes rappels →</a>
            </div>
        </section>
    <?php else: ?>
        <section class="rappel-bandeau rappel-bandeau--<?= $item->rappelActif ? 'vide' : 'suspendu' ?>" aria-label="Statut des rappels de cette plante">
            <p class="rappel-bandeau__texte">
                <?= $item->rappelActif
                    ? 'Aucun rappel programmé : enregistrez un soin pour déclencher le prochain rappel (tous les ' . (int) $item->rappelFrequenceJours . ' jours).'
                    : 'Rappels suspendus pour cette plante. Cochez « Programmer le prochain rappel » dans le formulaire de soin pour les réactiver.' ?>
            </p>
            <div class="rappel-bandeau__actions">
                <a class="btn-secondary btn-mini" href="<?= e(url('/rappels')) ?>"
                   data-rappels-modal-trigger aria-controls="rappels-modal" aria-haspopup="dialog">Gérer mes rappels</a>
                <a class="btn-link" href="<?= e(url('/rappels')) ?>">Tous mes rappels →</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- Modifier les conditions de culture -->
    <section class="collection-edit" aria-label="Conditions de culture">
        <h2 class="section-title">Conditions de culture</h2>
        <form method="post" action="<?= e(url('/update/collection')) ?>" class="collection-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= e($item->id) ?>">
            <input type="hidden" name="orchid_id" value="<?= e($item->orchidId) ?>">

            <div class="form-field">
                <label for="location">Emplacement</label>
                <input type="text" id="location" name="location" value="<?= e($item->location) ?>">
            </div>
            <div class="form-field">
                <label for="temp">Température</label>
                <input type="text" id="temp" name="temp" value="<?= e($item->temp) ?>" placeholder="Ex : 18-22°C">
            </div>
            <div class="form-field">
                <label for="hygro">Hygrométrie</label>
                <input type="text" id="hygro" name="hygro" value="<?= e($item->hygro) ?>" placeholder="Ex : 65%">
            </div>
            <div class="form-field">
                <label for="light">Lumière</label>
                <input type="text" id="light" name="light" value="<?= e($item->light) ?>" placeholder="Ex : Lumière tamisée">
            </div>
            <div class="form-field">
                <label for="ventilation">Ventilation</label>
                <input type="text" id="ventilation" name="ventilation" value="<?= e($item->ventilation) ?>">
            </div>
            <div class="form-field">
                <label for="rappel_frequence_jours">Fréquence du rappel (jours)</label>
                <input type="number" id="rappel_frequence_jours" name="rappel_frequence_jours"
                       min="1" max="365" step="1" value="<?= (int) $item->rappelFrequenceJours ?>"
                       help="Interval en jours entre chaque soin enregistré et le rappel planifié par RG3.">
                <small class="help-text">Le rappel est automatiquement replanifié à chaque soin enregistré.</small>
            </div>
            <div class="form-field">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"><?= e($item->notes) ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>
    </section>

    <!-- Historique des soins -->
    <section class="care-history" aria-label="Historique des soins">
        <h2 class="section-title">Historique des soins</h2>
        <?php if ($soins === []): ?>
            <p class="no-results">Aucun soin enregistré pour cette plante.</p>
        <?php else: ?>
            <ul class="care-list">
                <?php foreach ($soins as $soin): ?>
                    <li class="care-item">
                        <span class="care-date"><?= e($soin->soinDate) ?></span>
                        <span class="care-type"><?= e($soin->typeLabel()) ?></span>
                        <span class="care-notes"><?= e($soin->notes ?? '') ?></span>
                        <form method="post" action="<?= e(url('/soin/delete/' . rawurlencode($soin->id))) ?>"
                              class="inline-form js-confirm-delete" data-confirm="Supprimer ce soin ?">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <button type="submit" class="btn-danger btn-mini">✕</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Enregistrer un nouveau soin -->
    <section class="care-add" aria-label="Enregistrer un soin">
        <h2 class="section-title">Enregistrer un soin</h2>
        <form method="post" action="<?= e(url('/soin/new')) ?>" class="collection-form care-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="collection_id" value="<?= e($item->id) ?>">

            <div class="form-field">
                <label for="soin_date">Date *</label>
                <input type="date" id="soin_date" name="soin_date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="form-field">
                <label for="type">Type de soin *</label>
                <select id="type" name="type" required>
                    <?php foreach ($typesSoin as $t): ?>
                        <option value="<?= e($t->label) ?>"><?= e(ucfirst($t->label)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="soin_notes">Notes</label>
                <textarea id="soin_notes" name="notes" rows="2" placeholder="Détail du soin…"></textarea>
            </div>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="engrais" name="engrais" value="1"><label for="engrais">Engrais appliqué</label>
                <input type="checkbox" id="substrat" name="substrat" value="1"><label for="substrat">Substrat renouvelé</label>
                <input type="checkbox" id="ravageurs" name="ravageurs" value="1"><label for="ravageurs">Ravageurs traités</label>
            </div>
            <div class="form-field form-field--checkbox">
                <!-- Opt-in du rappel (RG3 étendu) : le hidden « 0 » placé avant la
                     case garantit une valeur toujours présente au POST. Cochée, le « 1 »
                     suit et gagne (dernière valeur) ; décochée, seul le « 0 » est envoyé. -->
                <input type="hidden" name="rappel_actif" value="0">
                <input type="checkbox" id="rappel_actif" name="rappel_actif" value="1"<?= $item->rappelActif ? ' checked' : '' ?>>
                <label for="rappel_actif">Programmer le prochain rappel (tous les <?= (int) $item->rappelFrequenceJours ?> jours)</label>
                <small class="help-text">Décochez pour suspendre les rappels de cette plante. Le rappel déjà planifié sera annulé (l'historique reste conservé).</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Enregistrer le soin</button>
            </div>
        </form>
    </section>

    <!-- Supprimer la plante -->
    <footer class="detail-actions">
        <form method="post" action="<?= e(url('/collection/delete/' . rawurlencode($item->id))) ?>"
              class="inline-form js-confirm-delete" data-confirm="Retirer définitivement cette plante de votre collection ?">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button type="submit" class="btn-danger">Retirer de ma collection</button>
        </form>
    </footer>

    <?php // Modale « Gérer mes rappels » (Phase D) — squelette + fragment chargé à l'ouverture. ?>
    <?php include dirname(__DIR__, 2) . '/components/rappels_modal.php'; ?>
</article>
