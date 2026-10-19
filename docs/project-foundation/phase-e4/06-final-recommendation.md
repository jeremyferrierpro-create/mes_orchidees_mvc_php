# 06 — Recommandation finale

## Recommandation

**Version à présenter au jury : le MVC local (état `ca40ef5` + travail non
commité), démonstration principale sur hébergeur PHP/MySQL gratuit à disque
persistant (scénario A) une fois les bloqueurs E3 corrigés, et secours local
XAMPP hors ligne (scénario D) toujours prêt sur le poste.**

**Plan B assumé : si l'hébergeur n'est pas validé à J-2, soutenance 100 % en
mode secours local**, en valorisant le dossier d'arbitrage d'infrastructure
(phases 06/07/E2/E3/E4) comme preuve de compétence DevOps — argument recevable
devant un jury DWWM, l'architecture MVC présentée étant alors exactement le
code à l'écran.

## Justification

1. **Conformité au livrable pédagogique** : seule la version locale incarne la
   méthode MVC enseignée ; la version GitHub/Render (legacy à plat + Neon) la
   contredit (`VERIFIED`, doc 03 §1).
2. **Stabilité** : le scénario D est le seul environnement où le code a
   réellement tourné (développement + tests documentés phases 05→E2) ; A
   reproduit le même moteur MySQL/MariaDB. B et C introduisent réécriture
   (35 marqueurs dialectaux — `VERIFIED`) ou défaillances structurelles free
   tier (spin-down, uploads éphémères — `DOCUMENTED`/`VERIFIED`).
3. **Coût/durée** : B = plusieurs jours de portage + re-validation totale,
   irréaliste avant le jury et contraire à la priorité « démonstration stable ».
4. **Risque zéro sur les données** : le plan n'exige aucune écriture sur
   Render/Neon/production ; la base distante éventuelle serait neuve et peuplée
   par les seeds de démo.

## Séquence d'exécution proposée (phases futures, chaque action = accord explicite)

| # | Action | Contrainte |
|---|--------|-----------|
| 1 | Inventorier et commiter le travail local **par lots** (migrations, code rappels/uploads, tests, docs ; exclure `.vscode`, photo de démo uploadée) | jamais `git add .` ; aucun secret ; pas de push sans décision de publication |
| 2 | Décision de publication GitHub : nouvelle branche `mvc-2026` (ou `main` remplacé **après** arbitrage) ; réécriture du README ; sort de la branche `dev` legacy (histoire distincte prouvée, doc 01 §3) | décision humaine : le legacy est peut-être à archiver en tag `legacy-neon-final` |
| 3 | Phase E' corrections bloqueurs : `DB_PASS`↔`DB_PASSWORD`, TLS PDO conditionnel, endpoint health, persistance uploads (disque hôte) | modifications de code = hors E4 |
| 4 | Choisir et valider l'hébergeur A, importer schema+migrations+seeds, valider tests HTTP + parcours complet | création de compte = action humaine |
| 5 | Préparer le kit secours D (poste figé, dump de démo, copies assets) et répéter le scénario | sans toucher au dépôt |
| 6 | J-2/J-1 : go/no-go mode principal selon vérifications (doc 05 §1) | — |

## Inconnues exigeant une décision humaine (rappel)

1. Accès dashboard Render + Neon : statut réel, branche/commit déployés, URL
   publique, sort du service legacy (`BLOCKED` en E4).
2. Publication du MVC sur GitHub : remplacer `main` (history déjà réécrite une
   fois) ou branche dédiée ? Que devient `dev` (histoire orpheline) ?
3. README : réécriture à valider (obsolète `VERIFIED`, modification interdite en E4).
4. Hébergeur A : nom final (piste GoogieHost `DOCUMENTED` E2/E3) et acceptation
   de ses limites.
5. La photo uploadée non suivie `photo-e7c9…jpg` : démo ou donnée à exclure ?

## Arrêt

Conformément à la section 13 de la mission, **l'audit s'arrête ici** : aucune
correction implémentée, aucun déploiement lancé. Les documents 01→06 constituent
la photographie complète ; la phase suivante (commits, corrections E',
publication, hébergement) reste soumise à décision explicite.
