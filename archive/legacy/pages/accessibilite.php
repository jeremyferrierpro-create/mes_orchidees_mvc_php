<!DOCTYPE html>
<html lang="fr">

<head>
    <?php 
    $title = 'Déclaration d\'accessibilité';
    $description = 'Déclaration d\'accessibilité du site Mes Orchidées conformément au RGAA 4.1.2. Engagement pour l\'accessibilité numérique.';
    $keywords = 'accessibilité, RGAA, handicap, navigation, conformité';
    require_once 'includes/head.php'; 
    ?>
</head>

<body>
    <a class="skip-link" href="#main-content">Aller au contenu principal</a>

    <?php require_once 'includes/sidebar.php'; ?>

    <?php require_once 'includes/header.php'; ?>

    <main class="main-content text-content" id="main-content">
        <section class="legal-page page-section" aria-labelledby="declaration-title">
            <h1 class="page-title" id="declaration-title">DÉCLARATION D’ACCESSIBILITÉ</h1>

            <article class="legal-section">
                <p><strong>Mes Orchidées</strong> s’engage à rendre ses services de communication au public en ligne
                    accessibles conformément à l’article 47 de la loi n° 2005-102 du 11 février 2005.</p>
                <p>Cette déclaration d’accessibilité s’applique au site web <strong>Mes Orchidées</strong>
                    (<code>https://github.com/jeremyferrierpro-create/mes_orchidees</code>).</p>
            </article>

            <article class="legal-section">
                <h2>ÉTAT DE CONFORMITÉ</h2>
                <p>Le site web <strong>Mes Orchidées</strong> est en <strong>conformité partielle</strong> avec le
                    Référentiel général d’amélioration de l’accessibilité (RGAA), version 4.1.2, en raison des
                    non-conformités énumérées ci-dessous.</p>
            </article>

            <article class="legal-section">
                <h2>RÉSULTATS DES TESTS</h2>
                <p>L’audit d’accessibilité interne réalisé au moyen de tests automatisés et manuels révèle que :</p>
                <ul>
                    <li><strong>83 %</strong> des critères de contrôle du RGAA version 4.1.2 sont respectés.</li>
                    <li>Le score global d’accessibilité automatisé Google Lighthouse s’élève à <strong>96 / 100</strong>
                        sur l’échantillon de pages principales.</li>
                </ul>
            </article>

            <article class="legal-section">
                <h2>CONTENUS NON ACCESSIBLES</h2>
                <p>Les contenus listés ci-dessous ne sont pas accessibles pour les motifs suivants :</p>

                <h3>Non-conformités</h3>
                <ul>
                    <li><strong>Composants dynamiques et formulaires (Critère 11.1 &amp; 11.2) :</strong> Certains
                        champs d'édition avancée dans le module d'administration nécessitent un renforcement de
                        l'association explicite entre les étiquettes et les messages d'erreurs en direct.</li>
                    <li><strong>Navigation et fenêtres modales (Critère 7.1 &amp; 12.9) :</strong> Sur certaines
                        configurations mobiles anciennes, la capture intégrale du focus (focus trap) dans la modale
                        d'enregistrement des soins est en cours d'optimisation pour assurer une libération systématique
                        via la touche Échap.</li>
                    <li><strong>Tableaux de données (Critère 5.6 &amp; 5.7) :</strong> Les en-têtes complexes des
                        tableaux de gestion du back-office doivent être complétés par des attributs <code>scope</code>
                        stricts sur l'ensemble des colonnes d'actions.</li>
                </ul>

                <h3>Dérogations pour charge disproportionnée</h3>
                <p>Aucun contenu ne fait l'objet d'une dérogation pour charge disproportionnée.</p>

                <h3>Contenus non soumis à l'obligation d'accessibilité</h3>
                <p>Les polices d'icônes et bibliothèques tierces fournies par des CDN externes (Font Awesome).</p>
            </article>

            <article class="legal-section">
                <h2>ÉTABLISSEMENT DE CETTE DÉCLARATION D’ACCESSIBILITÉ</h2>
                <p>Cette déclaration a été établie le <strong>20 août 2026</strong>. Elle a été mise à jour le
                    <strong>20 août 2026</strong>.
                </p>

                <h3>Technologies utilisées pour la réalisation du site web</h3>
                <ul>
                    <li>HTML5</li>
                    <li>CSS3 / SCSS (Architecture modulaire 7-1, variables, flexbox et CSS grid)</li>
                    <li>JavaScript natif (Vanilla ES6+)</li>
                </ul>

                <h3>Environnement de test</h3>
                <p>Les vérifications de restitution des contenus ont été réalisées avec les combinaisons suivantes :</p>
                <ul>
                    <li><strong>Navigateurs :</strong> Google Chrome 151, Mozilla Firefox, Microsoft Edge</li>
                    <li><strong>Technologies d'assistance :</strong> NVDA (dernière version sous Windows), lecteur
                        d'écran ChromeVox / VoiceOver (iOS)</li>
                    <li><strong>Outils d'évaluation :</strong> Google Lighthouse 13.4, RGAA Checker, Web Developer
                        Toolbar, Nu HTML Checker (W3C), Adobe Color Accessibility Tools</li>
                </ul>

                <h3>Pages du site ayant fait l'objet de la vérification de conformité</h3>
                <ol>
                    <li>Accueil (<code>index.php</code>)</li>
                    <li>Encyclopédie botanique (<code>encyclopedie.php</code>)</li>
                    <li>Ma collection (<code>macollection.php</code>)</li>
                    <li>Conseils de culture (<code>conseils.php</code>)</li>
                    <li>Administration (<code>administration.php</code>)</li>
                    <li>Authentification / Inscription (<code>authentification.php</code>)</li>
                    <li>Mentions légales (<code>mentions.php</code>)</li>
                    <li>Politique de confidentialité (<code>confidentialite.php</code>)</li>
                    <li>Déclaration d'accessibilité (<code>accessibilite.php</code>)</li>
                </ol>
            </article>

            <article class="legal-section">
                <h2>RETOUR D’INFORMATION ET CONTACT</h2>
                <p>Si vous n’arrivez pas à accéder à un contenu ou à un service, vous pouvez contacter le responsable du
                    site internet pour être orienté vers une alternative accessible ou obtenir le contenu sous une autre
                    forme :</p>
                <ul>
                    <li>Courriel du responsable : <a
                            href="mailto:contact@mes-orchidees.fr">jeremy.ferrierpro@gmail.com</a>
                    </li>
                    <li>Référent technique : <strong>Jérémy FERRIER</strong></li>
                    <li>Dépôt du projet : <a href="https://github.com/jeremyferrierpro-create/mes_orchidees"
                            target="_blank" rel="noopener noreferrer">Dépôt GitHub Mes Orchidées (nouvelle fenêtre)</a>
                    </li>
                </ul>
            </article>

            <article class="legal-section">
                <h2>VOIES DE RECOURS</h2>
                <p>Cette procédure est à utiliser si vous avez signalé au responsable du site internet un défaut
                    d’accessibilité qui vous empêche d’accéder à un contenu ou à un des services du portail et que vous
                    n’avez pas obtenu de réponse satisfaisante.</p>
                <ul>
                    <li>Écrire un message au Défenseur des droits : <a href="https://formulaire.defenseurdesdroits.fr/"
                            target="_blank" rel="noopener noreferrer">formulaire en ligne du Défenseur des droits
                            Défenseur des droits</a></li>
                    <li>Contacter le délégué du Défenseur des droits dans votre région : <a
                            href="https://www.defenseurdesdroits.fr/saisir/delegues" target="_blank"
                            rel="noopener noreferrer">liste des délégués régionaux du défenseur des droits</a></li>
                    <li>Envoyer un courrier par la poste (gratuit, sans affranchissement) :<br>
                        <strong>Défenseur des droits</strong><br>
                        Libre réponse 71120<br>
                        75342 Paris CEDEX 07
                    </li>
                </ul>
            </article>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>

