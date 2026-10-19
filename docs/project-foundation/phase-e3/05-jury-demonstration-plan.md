# 05 — Plan de démonstration jury DWWM, données fictives, sauvegarde, secours

Principe : une **URL publique**, une **base distante persistante**, des **données
fictives reproductibles**, un **reset sûr**, une **sauvegarde** pré-J, et un **plan
B local**. Aucune donnée personnelle recopiée, aucun compte de démo créé, aucune
donnée téléversée dans cette phase (interdits).

## 1. Scénario cible (aligné sur la reco — voir doc 06)

| Priorité | Scénario | Rôle |
|----------|----------|------|
| **Principal** | Hébergeur PHP/MySQL gratuit à disque persistant (**D**) | URL en ligne + base persistante + uploads qui restent |
| **Alternative Render** | A (Render free + MySQL externe) | si l'exigence « Render » est impérative ; **en renonçant à montrer l'upload** |
| **Plan B (garanti)** | Démonstration **locale XAMPP** | si Internet/hébergement tombe le jour J |

## 2. Données de démonstration fictives et reproductibles

- Base de démo = **re-création** depuis le dépôt, jamais un dump de la base
  personnelle :
  1. `schema_mysql.sql` ;
  2. migrations `data/migrations/2026-10-18_*.sql` (ordre chronologique) ;
  3. seeds `data/seeds/*.sql` (orchidées, conseils, collections, soins, notifications).
- ⚠️ **Avant publication** : établir un **seed de démo public** qui **ne contient
  pas** l'adresse personnelle `jeremy.ferrierpro@gmail.com` présente dans
  `01_seed_users.sql`. Utiliser des emails en `.invalid` et des rôles de test.
  **Aucune donnée perso** ne part sur la base distante. **VERIFIED** (seed relu).
- Les **rappels** étant calculés à la volée (`notif_date <= NOW()`), prévoir des
  notifications seed **datées du jour J** pour la démo (sinon aucune alerte visible).

## 3. Comptes pour le jury

- Deux possibilités, **sans** créer de compte ici :
  1. **Comptes de démo** issus du seed public (lecture immédiate) ;
  2. **Inscription publique** du site (le jury crée son propre compte en direct) —
     démontre l'authentification sans préconfiguration.
- Isolation RG1 (stricte par utilisateur) : chaque compte ne voit que **ses**
  collections → la démo « plusieurs comptes » est convaincante et sûre.

## 4. Procédure de réinitialisation sûre (reset)

Reproductible, sans script custom, en cas de données modifiées par le jury :
1. `DROP`/recréer la base de démo (ou `TRUNCATE` des tables dans l'ordre des FK) ;
2. ré-importer schéma → migrations → seeds (snapshot du §5) ;
3. revérifier les datations jour J des notifications.

## 5. Sauvegarde exportable avant le jour J

- **Export** complet (`mysqldump`/phpMyAdmin) de la base de démo peuplée →
  fichier `_pre-demo.sql` conservé **localement**. 
- **Copie** du dossier `public/assets/images/uploads/` si l'hébergeur a un disque
  persistant (sinon ne pas compter sur les uploads en ligne). 
- **Rollback** : Render free ne permet de revenir qu'aux **2 derniers deploys**
  (DOCUMENTED) → garder le dernier build sain + l'export SQL comme source de vérité.

## 6. Checklist de vérification avant l'oral

- [ ] URL publique accessible en **HTTPS** depuis un autre appareil (pas que la
      machine de dev). 
- [ ] **Cold start testé** : si Render free, réveiller le service (~1 min) **avant**
      de le montrer ; éviter une page « spin-up » devant le jury. 
- [ ] Login démo OK ; dashboard collection OK. 
- [ ] Parcours complet fluide : accueil → encyclopédie (**recherche FULLTEXT**) →
      ajout plante → **soin** (opt-in rappel) → **modale « Mes rappels »** →
      **toast jour J** → historique. 
- [ ] **Upload** : seulement si l'hébergeur garantit la persistance (sinus l'occulter). 
- [ ] `_pre-demo.sql` à portée de main ; reset testé une fois. 
- [ ] XAMPP local **démarré et vérifié** = bascule immédiate. 

## 7. Plan B : démonstration locale (XAMPP)

- Le poste de dev **est déjà** une instance complète et hors-ligne : `httpd` :80 +
  `mysqld` :3306, base `mes_orchidees` peuplée (Phase E2, VERIFIED). 
- **Aucune dépendance Internet** au runtime : pas de CDN, pas d'API externe ;
  `vendor/` = autochargeur seul. Le site local tourne même réseau coupé. **VERIFIED**.
- Variante « URL temporaire » si le jury doit cliquer lui-même : tunnel local
  (ngrok / Cloudflare Tunnel) **pendant** l'oral — en secours du mutualisé, pas en
  principal. 
- Option de propreté : démontrer via le harnais isolé
  `php -S 127.0.0.1:8099 -t public tests/http/server-router.php` (base de **test**)
  pour ne pas toucher la base de dev pendant le live.

> Ce document **décrit** la procédure. Il **n'exécute** aucun déploiement, aucune
> création de compte, aucun téléversement (**BLOCKED** en attendant l'arbitrage
> d'architecture du doc 06).
