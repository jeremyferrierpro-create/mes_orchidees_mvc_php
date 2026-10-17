<!DOCTYPE html>
<!-- Page branchee sur /assets/js/data/*.json via db.js (simulation Supabase) - 100% dynamique -->
<html lang="fr">

<head>
    <?php 
    $title = 'Ma Collection';
    $description = 'Gérez votre collection personnelle d\'orchidées : suivi des soins, rappels, statistiques de culture et gestion des sites de culture.';
    $keywords = 'collection orchidées, gestion orchidées, soins orchidées, suivi plante, statistiques culture';
    require_once 'includes/head.php'; 
    ?>
</head>

<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content" id="main-content">
        <section class="collection-header" aria-label="Ma collection">
            <h1 class="page-title">MA COLLECTION</h1>
        </section>

        <div id="collection-guest-message" class="collection-guest-message" hidden>
            <p>Veuillez vous <a href="authentification.php">connecter</a> pour accéder à votre collection.</p>
        </div>

        <div id="collection-app" class="collection-app" hidden>
            <!-- Carte d'identité du propriétaire : prouve que la collection appartient bien à la personne connectée -->
            <!-- Elle affiche les vraies données de la table users dans /assets/js/data/users.json via db.js -->
            <section id="user-identity-card" class="user-identity-card" aria-label="Carte d'identité du propriétaire">
                <div class="user-identity-header">
                    <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                    <h3>PROPRIÉTAIRE DE LA COLLECTION</h3>
                    <span id="user-identity-badge" class="user-role-badge">-</span>
                </div>
                <div class="user-identity-body">
                    <img id="user-avatar" src="./assets/images/site/logotransparent.png" alt="Avatar du propriétaire"
                        class="user-avatar">
                    <div class="user-identity-infos">
                        <p class="user-fullname"><strong id="user-fullname">-</strong></p>
                        <p class="user-email"><i class="fa-solid fa-envelope" aria-hidden="true"></i> <span
                                id="user-email">-</span></p>
                        <p class="user-meta"><span id="user-role-detail">-</span> • <span id="user-id">ID: -</span></p>
                        <p class="user-dates"><i class="fa-solid fa-calendar" aria-hidden="true"></i> <span
                                id="user-created">Créé le : -</span> • <span id="user-modified">Modifié le : -</span>
                        </p>
                        <p class="user-plant-count"><i class="fa-solid fa-seedling" aria-hidden="true"></i> <span
                                id="user-plant-count">0 plante(s)</span> dans la collection</p>
                    </div>
                </div>
            </section>

            <section class="collection-dashboard" aria-label="Tableau de bord">
                <article class="dash-card dash-plants" aria-label="Statistiques des plantes">
                    <h3>PLANTES</h3>
                    <ul id="dash-plants-stats">
                        <li><span>Total</span><strong id="stat-total">0</strong></li>
                        <li><span>Épiphytes</span><strong id="stat-epiphytes">0</strong></li>
                        <li><span>Terrestres</span><strong id="stat-terrestres">0</strong></li>
                        <li><span>Hémi-épiphytes</span><strong id="stat-hemi">0</strong></li>
                    </ul>
                </article>

                <article class="dash-card dash-climate" aria-label="Climat moyen de la collection">
                    <h3>CLIMAT</h3>
                    <div class="dash-climate-values" id="dash-climate-values">
                        <div class="climate-item"><i class="fa-solid fa-temperature-half" aria-hidden="true"></i><strong
                                id="climate-temp">—</strong></div>
                        <div class="climate-item"><i class="fa-solid fa-droplet" aria-hidden="true"></i><strong
                                id="climate-humidity">—</strong></div>
                        <div class="climate-item"><i class="fa-solid fa-sun" aria-hidden="true"></i><strong
                                id="climate-light">—</strong></div>
                    </div>
                </article>

                <article class="dash-card dash-notifications" aria-label="Notifications">
                    <h3>NOTIFICATIONS</h3>
                    <ul id="dash-notifications">
                        <li><i class="fa-solid fa-bell" aria-hidden="true"></i><span class="notif-dot notif-orange"
                                aria-hidden="true"></span><span>Soins à prévoir</span></li>
                        <li><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span
                                class="notif-dot notif-green" aria-hidden="true"></span><span>Alertes</span></li>
                        <li><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><span
                                class="notif-dot notif-green" aria-hidden="true"></span><span>Bilan santé</span></li>
                    </ul>
                </article>
            </section>

            <section class="collection-workspace" aria-label="Espace de gestion">
                <div class="collection-panel">
                    <div class="care-panel-header" style="margin-bottom: 20px;">
                        <h2 class="panel-title" style="margin-bottom: 0;">COLLECTION</h2>
                        <button type="button" class="btn-new-care" id="btn-add-collection-orchid" aria-label="Ajouter une orchidée à la collection">+</button>
                    </div>
                    <div id="collection-grid" class="collection-grid"></div>
                </div>

                <div class="side-panels">
                    <div class="care-panel">
                        <div class="care-panel-header">
                            <h2 class="panel-title">SOINS</h2>
                            <button type="button" class="btn-new-care" id="btn-new-care"
                                aria-label="Ajouter un nouveau soin">+</button>
                        </div>
                        <div class="care-table-wrap">
                            <table class="care-table" id="care-table" aria-label="Historique des soins">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Soin effectué</th>
                                        <th>Rappel</th>
                                        <th>Date rappel</th>
                                    </tr>
                                </thead>
                                <tbody id="care-table-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="conseil-panel">
                        <h2 class="panel-title">CONSEILS</h2>
                        <div id="conseil-preview" class="conseil-preview"></div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php require_once 'includes/footer.php'; ?>

    <?php require_once 'includes/modals/collection-add-modal.php'; ?>
    <?php require_once 'includes/modals/collection-edit-modal.php'; ?>
    <?php require_once 'includes/modals/collection-care-modal.php'; ?>

</body>

</html>