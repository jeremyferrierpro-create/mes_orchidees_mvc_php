<?php
/**
 * Partial : listes de rappels de soins (sections + cartes glass + historique).
 *
 * Extrait de la page C0 `pages/rappels/index.php` à l'identique (Phase D) pour
 * être partagé entre :
 *   - la page autonome /rappels (include depuis pages/rappels/index.php) ;
 *   - le fragment de la modale (include depuis pages/rappels/modal.php,
 *     servi par GET /rappels/modal → RappelController::modal()).
 *
 * Variables attendues dans la portée appelante :
 *  - $aujourdhui, $enRetard, $aVenir, $historique : \App\Entity\Notification[]
 *  - $csrf : string (jeton embarqué dans chaque formulaire POST)
 *
 * Le markup et les routes consommées sont ceux déjà validés en C0 : aucune
 * logique métier n'est dupliquée ni réécrite côté JavaScript.
 *
 * @package App\Templates\Pages\Rappels
 * @var \App\Entity\Notification[] $aujourdhui
 * @var \App\Entity\Notification[] $enRetard
 * @var \App\Entity\Notification[] $aVenir
 * @var \App\Entity\Notification[] $historique
 * @var string $csrf
 */

/** Retard en jours entiers par rapport à l'échéance (ne sert qu'aux sections « en retard »). */
$joursRetard = static function (\App\Entity\Notification $r): int {
    $due = substr((string) $r->notifDate, 0, 10);
    if ($due === '') {
        return 0;
    }
    try {
        return (int) (new DateTime($due))->diff(new DateTime('today'))->days;
    } catch (Exception $e) {
        return 0;
    }
};

/**
 * Carte de rappel en verre givré : plante (photo → espèce → placeholder), type de
 * soin attendu (badge, seulement si le rappel le porte), échéance relative,
 * fréquence, statut et actions adaptées.
 *
 * @param \App\Entity\Notification $r    Rappel.
 * @param string                   $tone 'jour' | 'retard' | 'avenir'.
 */
$rappelCard = static function (\App\Entity\Notification $r, string $tone) use ($csrf, $joursRetard): void {
    $img  = $r->displayImageUrl();
    $nom  = $r->orchidName ?? 'votre plante';
    $date = $r->dateFr() !== '' ? substr($r->dateFr(), 0, 10) : '—';
    ?>
    <article class="rappel-card rappel-card--<?= e($tone) ?>">
        <?php if ($img !== null): ?>
            <img class="rappel-card__img" src="<?= e($img) ?>" alt="<?= e($nom) ?>" loading="lazy">
        <?php else: ?>
            <span class="rappel-card__img rappel-card__img--placeholder" aria-hidden="true"></span>
        <?php endif; ?>

        <div class="rappel-card__body">
            <p class="rappel-card__plante">
                <a href="<?= e(url('/collection/' . rawurlencode($r->collectionId ?? ''))) ?>"><?= e($nom) ?></a>
            </p>

            <p class="rappel-card__meta">
                <?php if ($r->typeSoinLabel !== null): ?>
                    <span class="rappel-badge rappel-badge--soin"><?= e(ucfirst($r->typeSoinLabel)) ?></span>
                <?php endif; ?>
                <?php if ($tone === 'retard'): ?>
                    <span class="rappel-badge rappel-badge--retard">En retard<?= $joursRetard($r) > 0 ? ' de ' . $joursRetard($r) . ' j' : '' ?></span>
                <?php elseif ($tone === 'jour'): ?>
                    <span class="rappel-badge rappel-badge--jour">À faire aujourd'hui</span>
                <?php else: ?>
                    <span class="rappel-badge rappel-badge--avenir">Prévu le <?= e($date) ?></span>
                <?php endif; ?>
                <?php if ($r->collectionFrequence !== null && $r->collectionFrequence > 0): ?>
                    <span class="rappel-card__frequence">Tous les <?= (int) $r->collectionFrequence ?> j</span>
                <?php endif; ?>
            </p>

            <p class="rappel-card__message"><?= e($r->message) ?></p>

            <div class="rappel-card__actions">
                <form method="post" action="<?= e(url('/rappel/' . rawurlencode($r->id) . '/fait')) ?>" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn-primary btn-mini">✓ Effectuer le soin<span class="sr-only"> : <?= e($nom) ?></span></button>
                </form>
                <form method="post" action="<?= e(url('/rappel/' . rawurlencode($r->id) . '/report/7')) ?>" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn-secondary btn-mini" title="Repousser de 7 jours">+7 j<span class="sr-only"> : <?= e($nom) ?></span></button>
                </form>
                <form method="post" action="<?= e(url('/rappel/' . rawurlencode($r->id) . '/report/14')) ?>" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn-secondary btn-mini" title="Repousser de 14 jours">+14 j<span class="sr-only"> : <?= e($nom) ?></span></button>
                </form>
                <form method="post" action="<?= e(url('/rappel/' . rawurlencode($r->id) . '/ignorer')) ?>" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn-secondary btn-mini">Ignorer<span class="sr-only"> : <?= e($nom) ?></span></button>
                </form>
                <form method="post" action="<?= e(url('/rappel/delete/' . rawurlencode($r->id))) ?>"
                      class="inline-form js-confirm-delete" data-confirm="Supprimer définitivement ce rappel ?">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn-danger btn-mini">✕<span class="sr-only">Supprimer ce rappel</span></button>
                </form>
            </div>
        </div>
    </article>
    <?php
};

/** Section « À faire aujourd'hui / En retard / À venir » : titre + compteur réel. */
$rappelSection = static function (string $title, array $items, string $tone) use ($rappelCard): void {
    if ($items === []) {
        return; // Une section vide n'affiche rien : l'intro porte déjà le bilan.
    }
    ?>
    <section class="rappel-section rappel-section--<?= e($tone) ?>" aria-label="<?= e($title) ?>">
        <h2 class="section-title"><?= e($title) ?>
            <span class="rappel-section__count" aria-hidden="true"><?= count($items) ?></span>
        </h2>
        <div class="rappel-section__grid">
            <?php foreach ($items as $r) { $rappelCard($r, $tone); } ?>
        </div>
    </section>
    <?php
};

// Bilan ouvert calculé sur les mêmes listes (indépendant de la page hôte).
$ouvert = count($aujourdhui) + count($enRetard) + count($aVenir);

$rappelSection("À faire aujourd'hui", $aujourdhui, 'jour');
$rappelSection('En retard', $enRetard, 'retard');

if ($ouvert === 0): ?>
    <section class="rappel-section rappel-section--vide" aria-label="Aucun rappel ouvert">
        <p class="no-results">Aucun rappel ouvert. Enregistrez un soin sur une plante pour programmer le prochain rappel.</p>
    </section>
<?php endif;

$rappelSection('À venir', $aVenir, 'avenir');

// Historique : distinct de l'historique des soins (fiche plante) — lignes
// compactes des rappels clos, avec suppression seule.
if ($historique !== []): ?>
    <section class="rappel-section rappel-section--historique" aria-label="Historique des rappels">
        <h2 class="section-title">Historique
            <span class="rappel-section__count" aria-hidden="true"><?= count($historique) ?></span>
        </h2>
        <ul class="rappel-histo-list">
            <?php foreach ($historique as $r): ?>
                <li class="rappel-histo<?= $r->statut === 'ignore' ? ' rappel-histo--ignore' : '' ?>">
                    <span class="rappel-histo__plante">
                        <?php if ($r->collectionId !== null): ?>
                            <a href="<?= e(url('/collection/' . rawurlencode($r->collectionId))) ?>"><?= e($r->orchidName ?? 'votre plante') ?></a>
                        <?php else: ?>
                            <?= e($r->orchidName ?? 'Notification globale') ?>
                        <?php endif; ?>
                    </span>
                    <?php // Phase D : la note du rappel clos reste lisible (le message est
                    // la trace de ce qui a été fait ou ignoré), échappée par e() comme le reste.
                    if ($r->message !== null && $r->message !== ''): ?>
                        <span class="rappel-histo__libelle"><?= e($r->message) ?></span>
                    <?php endif; ?>
                    <?php if ($r->typeSoinLabel !== null): ?>
                        <span class="rappel-badge rappel-badge--soin"><?= e(ucfirst($r->typeSoinLabel)) ?></span>
                    <?php endif; ?>
                    <span class="rappel-badge <?= $r->statut === 'ignore' ? 'rappel-badge--ignore' : 'rappel-badge--traite' ?>">
                        <?= $r->statut === 'ignore' ? 'Ignoré' : 'Traité' ?>
                    </span>
                    <span class="rappel-histo__date"><?= e($r->dateFr()) ?></span>
                    <form method="post" action="<?= e(url('/rappel/delete/' . rawurlencode($r->id))) ?>"
                          class="inline-form js-confirm-delete" data-confirm="Supprimer définitivement ce rappel ?">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn-danger btn-mini">✕<span class="sr-only">Supprimer ce rappel de l'historique</span></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif;
