# 04 — Scénarios de déploiement évalués

Priorité rappelée par la mission : **démonstration stable, explicable et
conforme au travail MVC** — pas l'option la moins chère. Les coûts/limites
free tiers proviennent de la doc Render/Neon consultée en phase E3
(`DOCUMENTED`, dates 2026-10-18) ; ils ne sont pas re-consultés ici.

## Scénario A — Conserver MySQL/MariaDB, déployer le MVC sur hébergeur PHP/MySQL compatible

| Critère | Évaluation | Niveau |
|---------|------------|--------|
| Adaptations | Corriger `DB_PASS`/`DB_PASSWORD` ou fournir `DB_DSN` ; ajouter TLS PDO si base distante exigente ; importer `schema_mysql.sql` + migrations + seeds hors conteneur (migrations non embarquées) ; pointeur docroot vers `public/` | VERIFIED (blocages connus) |
| Risques fonctionnels | Modérés : le code cible MySQL/MariaDB depuis la décision D3, testé localement sur ce moteur | DOCUMENTED (phases 05/C1) |
| Risques sécurité | À valider sur mutualisé (TLS, cookies `secure`, config `.htaccess` du docroot) | NOT VERIFIED (dépend du fournisseur) |
| Validation | Relance complète de `tests/http` + parcours navigateur sur l'hôte | à programmer |
| Persistance uploads | **Résolue** si l'hébergeur offre un disque persistant (cas des mutualisés gratuits de type GoogieHost évalués en E2/E3) | DOCUMENTED |
| Coût/limites | 0 €/mois possible ; quotas gratuits non garantis dans la durée | DOCUMENTED (E2 doc 03, E3 doc 03) |
| Inconnues | Choix définitif de l'hébergeur et vérification de ses limites réelles (PHP ≥ 8.1, docroot, mod_rewrite/LiteSpeed) | NOT VERIFIED |

**Faisabilité : bonne. C'est le scénario à moindre friction pour le jury, à
condition de valider l'hébergeur avant la soutenance.**

## Scénario B — Conserver Render et porter le MVC vers PostgreSQL/Neon

| Critère | Évaluation | Niveau |
|---------|------------|--------|
| Adaptations | **Réécriture profonde** : FULLTEXT→tsvector/GIN, MATCH AGAINST→requêtes texte, ON DUPLICATE KEY→ON CONFLICT, DEFAULT(UUID())→gen_random_uuid, DDL complet du schéma + 6 migrations + seeds, `pdo_pgsql` à réintroduire (retiré en D3), `config/db.php` multi-driver | VERIFIED (35 marqueurs dialectaux, doc 03) |
| Risques fonctionnels | Élevés : régression recherche, types, tests à revalider intégralement ; schémas PG du dépôt obsolètes (ne couvrent pas rappels/soins) | VERIFIED |
| Difficulté de validation | Maximale : tout le périmètre tests HTTP + JS + navigateur à rejouer sur PG | INFÉRENCE argumentée |
| Persistance uploads | Non résolue sur Render free (filesystem éphémère, disque persistant payant) | DOCUMENTED (E3) + VERIFIED (Upload.php) |
| Coût | 0 € possible (Neon free) mais coût de développement très élevé à quelques jours du jury | DOCUMENTED |
| Verdict | **Déconseillé** en état de cause : contredit la décision D3, durée trop longue, n'apporte aucun bénéfice démonstratif | — |

## Scénario C — Render + base MySQL externe

| Critère | Évaluation | Niveau |
|---------|------------|--------|
| Compatibilité Render | Render ne fournit **aucune** base MySQL (Postgres seulement) ; une MySQL externe gratuite (type PlanetFree/000webhost-tier) n'est **pas démontrable** comme fiable ni accessible depuis Render (whitelist IP, TLS) | DOCUMENTED (doc Render free, E3) |
| Adaptations | TLS PDO obligatoire (absent — bloqueur), persistance uploads toujours perdue au spin-down Render free, redémarrages ~1 min | VERIFIED (code) + DOCUMENTED (Render) |
| Risques | Spin-down pendant la démo, base externe gratuite instable/intermittente, double dépendance réseau | DOCUMENTED/INFÉRÉ |
| Coût | 0 € théorique, mais 2 points de défaillance gratuits = faux sentiment d'économie | — |
| Verdict | **Écarté** pour la soutenance : cumule les deux faiblesses (éphémère + base non garantie) sans résoudre les bloqueurs | — |

## Scénario D — Démonstration locale MVC sous XAMPP (hors ligne)

| Critère | Évaluation | Niveau |
|---------|------------|--------|
| Adaptations | **Aucune modification de code** : environnement déjà utilisé en développement (MariaDB local, `mes_orchidees` + `mes_orchidees_test`) | DOCUMENTED (phases) + VERIFIED (clés .env) |
| Risques fonctionnels | Quasi nuls : c'est l'environnement d'exécution réel du code ; seuls aléas = machine du jour J | VERIFIED (état config) |
| Validation | Triviallement re-jouable : `php tests/run.php`, suite HTTP, parcours browser | DOCUMENTED |
| Persistance uploads | Totale (disque local) | VERIFIED (Upload.php sur filesystem) |
| Coût | 0 € | — |
| Limites | Valeur « produite en ligne » plus faible pour le jury ; dépendance à l'ordinateur apporté ; le `DB_PASSWORD`/`DB_PASS` fonctionne ici par accident (root mot de passe vide) → à documenter oralement, pas à corriger dans l'urgence | INFERRED (E3 §4) |

## Matrice de décision

| Critère | A (hôte PHP/MySQL) | B (Render+Neon) | C (Render+MySQL ext.) | D (XAMPP local) |
|---------|:---:|:---:|:---:|:---:|
| Code MVC présenté = code exécuté | ✅ | ✅ après réécriture | ✅ | ✅ immédiatement |
| Stabilité jour J | ⚠️ (dépend hébergeur) | ❌ | ❌ | ✅ |
| Hors-ligne / secours | ❌ | ❌ | ❌ | ✅ |
| Uploads persistants | ✅ (si disque persistant) | ❌ | ❌ | ✅ |
| Travail restant | Bloqueurs config (corrigeables en phase E') | Réécriture DB (semaines) | Bloqueurs + fiabilité | ✅ Aucun (ou quasi) |
| 0 €/mois | ✅ | ✅ | ✅ | ✅ |
| Explicable au jury (MVC enseigné) | ✅ | ✅ | ✅ | ✅ |

## Conclusion de la doc 04

Le **couple D ( secours hors ligne) + A (version en ligne si un hébergeur est
validé à temps)** est le seul qui maximise la stabilité sans réécriture interdite
par le périmètre. B et C exigent des travaux lourds ou introduisent des
défaillances structurelles non résolvables au 0 € gratuit (uploads, spin-down,
dialecte SQL). Détails du plan de soutenance : doc 05 ; recommandation : doc 06.
