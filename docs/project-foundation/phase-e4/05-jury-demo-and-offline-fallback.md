# 05 — Plan de soutenance : mode principal + mode secours hors ligne

Ce plan **décrit des opérations futures** ; aucune n'a été exécutée dans E4
(interdictions : données, base, déploiement).

## 0. Identification de la version candidate

- **Version candidate** = branche `main` locale, HEAD `ca40ef5` **+ les 119
  entrées de travail non commitées** (rappels, soins, uploads, tests).
- Problème : cet état n'existe sur **aucune** ref distante et n'est reproductible
  que sur ce poste. Action future hors E4 (avec autorisation explicite) :
  **commiter** le travail (par lots : migrations, code, tests, docs ; **jamais
  `git add .`** — exclure `.vscode/`, la photo uploadée de démo, tout secret),
  puis **tag** `soutenance-v1` et pousser sur une branche dédiée (`mvc-2026`)
  plutôt que d'écraser `main` publié (le legacy GitHub reste témoin de
  l'historique — décision de publication à trancher par le propriétaire).
- Preuve d'appartenance au jury : sortie `git log` + tag + SHA à afficher en
  support d'oral.

## 1. Mode principal — démonstration en ligne (scénario A)

Précondition : bloqueurs E3 corrigés **dans une phase autorisée**
(`DB_PASS`, TLS si requis, docroot, import du schéma+migrations+seeds sur la
base de l'hébergeur) et hébergeur PHP/MySQL à disque persistant validé par une
exécution complète des tests HTTP + parcours navigateur.

Parcours métier prioritaire (ordre scénarisé, ~10-12 min) :

1. Accueil + recherche encyclopédie (démontre FULLTEXT MySQL).
2. Inscription d'un compte **de démo fictif** (nom et adresse e-mail dédiés type
   `demo@mesorchidees.test` — aucun compte personnel, aucune donnée privée).
3. Ajout d'une orchidée à la collection (autocomplete espèce existante).
4. Upload d'une photo (démontre la persistance sur le mode A).
5. Plan de soins : créer un soin, poser un rappel avec opt-in, afficher la
   notification générée.
6. Annuler le rappel (montrer le caractère non destructif).
7. Back-office : rôle admin, moderation d'un conseil, isolation RG1 (deux
   comptes démo, collection A invisible depuis B).
8. Terminer par les tests : relancer `php tests/run.php` en direct si le poste
   le permet.

Vérifications avant le jour J (J-2 puis J-1) : statut du service de l'hébergeur,
connexion BDD réelle (pas seulement rendu de page — PDO paresseux), uploads
présents, cookies `secure` sur HTTPS, compte démo opérationnel, mot de passe
démo consigné hors du dépôt.

## 2. Mode secours — XAMPP local hors ligne (scénario D)

Prérequis logiciels (identiques au poste de dev) :

- XAMPP : Apache (mod_rewrite activé) + MariaDB/MySQL 8-compatible + PHP ≥ 8.1
  avec `pdo_mysql`, `mbstring`, `fileinfo` ;
- Composer (uniquement `dump-autoload`, aucune dépendance externe) ;
- VS Code ou terminal pour la suite de tests ;
- Copie du projet + dump de la base.

Base et données de démonstration reproductibles :

1. Créer `mes_orchidees` via `data/schema_mysql.sql` (CREATE DATABASE en dur —
   l'adapter si besoin pour la machine du jury, opération documentée, pas de
   modification du fichier source en E4).
2. Importer les migrations `data/migrations/*.sql` dans l'ordre des dates
   (20260826 → 20261018 × 5) puis les seeds `data/seeds/01…06`.
3. Comptes de test des seeds (déjà fictifs, convention stricte RG1 : données
   de démo uniquement) — vérifier qu'aucune donnée personnelle ne figure dans
   les seeds avant présentation (à contrôler en phase future ; non prouvé en E4).
4. Jeu d'essai photos : copies de secours des images dans
   `public/assets/images/uploads/` (restauration simple en cas de pépin).

Accès assets/images : le docroot pointant `public/`, vérifier au navigateur
les visuels `orchids/` (dont `vanilla_imperialis.jpg`, fichier **non suivi**
→ à intégrer au lot de commit, sinon image manquante en démo) et SCSS compilé
(`style.min.css` présent et à jour — à re-valider après compilation finale).

Scénarios nécessitant réellement Internet en secours : **aucun** dans le
parcours ci-dessus (PWA `sw.js` et manifest sont servis en local ; le seul
risque réseau = polices/CDN éventuels — à inventorier en phase future, non
vérifié en E4).

Procédure de retour à l'état initial : dump `mysqldump mes_orchidees` de fin
de session ; pour repartir de zéro, recréer la base à partir de
schema+migrations+seeds (procédure identique à l'installation) ; restaurer le
dossier `uploads/` depuis la copie de secours.

## 3. Arbitrage jour J

- Si le mode principal (hébergeur) est validé et responsive au test J-1 :
  l'utiliser en démo, le local en secours chaud (XAMPP déjà prêt sur le poste).
- Sinon : bascule immédiate et assumée sur le mode secours ; expliquer au jury
  le choix (stabilité > gadget en ligne) en **s'appuyant sur le dossier
  d'arbitrage d'infrastructure déjà rédigé** (phases 06/07/E2/E3 : « Render
  free tier incompatible — VERIFIED par analyse », « décision D3 MySQL ») —
  l'audit d'infrastructure fait partie de la compétence montrée.

## 4. Ce que le plan impose comme travaux futurs (hors E4, avec accord)

1. Commiter/étiqueter la version candidate (par lots, sans secrets, sans
   `git add .`).
2. Corriger les bloqueurs de déploiement (phase E' : `DB_PASS`, TLS, health endpoint).
3. Choisir et valider l'hébergeur A (création de compte = action humaine).
4. Mettre à jour le README **après** décision, pas avant (interdit en E4).
