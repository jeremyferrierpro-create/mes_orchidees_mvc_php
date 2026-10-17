<?php
/**
 * Vue : fiche d'une plante de la collection (/collection/{id}) + suivi des soins.
 *
 * Rendue par CollectionController::show(). Variables :
 *  - $item  : App\Entity\Collection (appartient à l'utilisateur connecté — RG1)
 *  - $soins : App\Entity\Soin[]     (historique de soins de cette plante)
 *  - $csrf  : string                (jeton pour tous les formulaires)
 *
 * Trois actions : modifier les conditions de culture, enregistrer un soin,
 * supprimer la plante. Toutes passent par des routes authentifiées ; l'isolation
 * RG1 est appliquée côté repository (WHERE user_id).
 *
 * @package App\Templates\Pages\Collection
 * @var \App\Entity\Collection $item
 * @var \App\Entity\Soin[] $soins
 * @var string $csrf
 */
$img = $item->orchidImageUrl();
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
                    <option value="arrosage">Arrosage</option>
                    <option value="engrais">Engrais</option>
                    <option value="rempotage">Rempotage</option>
                    <option value="substrat">Changement de substrat</option>
                    <option value="ravageurs">Traitement ravageurs</option>
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
</article>
