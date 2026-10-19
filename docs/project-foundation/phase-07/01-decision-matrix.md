# 01 — Matrice des décisions D1–D6 (Phase 07)

> La colonne « Décision du propriétaire » n'est remplie **que** de réponses effectivement
> reçues (date : 2026-10-17, §4). Aucune décision n'est présumée, déduite d'une
> recommandation, ni imputée au propriétaire (règles 11 et 12).
> Statuts : `OPEN` (choix possible, non communiqué) · `BLOCKED` (impossible tant qu'une
> dépendance manque) · `DECIDED` (arbitrage reçu du propriétaire — **2 lignes à ce jour : D2, D4**).

## 1. Matrice compacte (statuts mis à jour après réception partielle des arbitrages — voir §4)

| ID | Options raisonnables | Recommandation (conseil, non décision) | Justification | Risques si différé | Vérifications restantes | Décision propriétaire | Statut |
|---|---|---|---|---|---|---|---|
| **D1** Fournisseur BDD | a) Railway MySQL/MariaDB (≈5 $/mois) · b) Render MySQL 8 conteneur + disque (**payant**, backups auto-gérés) · c) PlanetScale (payant ≥ Base) · d) hébergement mutualisé PHP+MySQL faible/coût (non instruit) · e) rester en local (aucune prod) | **e** temporairement, puis **d** à instruire | **Contrainte nouvelle déclarée par le propriétaire : enveloppe 0 €/mois.** Aucune option instruite ne la satisfait : Railway/PlanetScale = payants ; le service privé MySQL Render n'est **pas éligible aux instances gratuites** (doc officielle : gratuit réservé Static Site / Web Service / Postgres / Key Value) ; et la seule offre « MySQL-compatible gratuite » examinée (TiDB Serverless) est **précisément celle que l'exigence FULLTEXT écarte** | Chaîne D3 → D6b reste bloquée ; aucun déploiement possible | Prix/quotas/rétention chez le fournisseur retenu ; pour l'option (d) : version PHP ≥ 8.1, accès MySQL distant, docroot sur `public/`, FULLTEXT présent — **à instruire, rien n'est affirmé** | **NON COMMUNIQUÉE** (contrainte 0 € exprimée, pas de fournisseur) | `BLOCKED` |
| **D2** Plateforme app | a) Render (service Docker) · b) Railway (app + base) · c) les deux · d) VPS/mutualisé | **b** si payant | Conteneur à port 80 ; Render attend `PORT=10000` par défaut mais **auto-détecte habituellement** un autre binding (doc officielle) | — | Valeur de port retenue en console ; gratuité : un **Web Service Render gratuit** existe mais avec spin-down 15 min, ~1 min de cold start, fichier système éphémère, 750 h/mois, et « do not use for production » (doc officielle) | **Render (app) + base externe** | `DECIDED` (conditionné par D1) |
| **D2b** Health check | a) liveness `GET /`/TCP · b) micro-route `/healthcheck` (ping BDD) | **a + tests de fumée** | `GET /` = 200 même sans base (VERIFIED 06) | Pannes BDD non signalées | Type de sonde réellement offert (TCP par défaut, HTTP optionnel) | **NON COMMUNIQUÉE** | `OPEN` |
| **D3** HTTPS & cookies | a) TLS plateforme + `COOKIE_SECURE=true` · b) attendre | **a** | Render termine le SSL et redirige HTTP→HTTPS (doc officielle, VERIFIED 07) | Sessions exposées si public sans HTTPS | Checklist 06/03 §6 après provisionnement | Dépend de D1/D2 (app Render retenue) | `BLOCKED` (aval de D1) |
| **D4** Tests HTTP | A) 3–6 tests légers · B) suite HTTP sur serveur réel · C) aucun | **A** | — | — | Coût ≈ 1 j, aucune dépendance nouvelle | **Option B — suite HTTP complète** | `DECIDED` |
| **D5** Schémas historiques | a) conserver · b) déplacer `archive/legacy/data/` · c) supprimer | **a** | Aucune référence runtime (VERIFIED 06) | Hygiène uniquement | — | **NON COMMUNIQUÉE** (non posée) | `OPEN` |
| **D6a** Push des commits | a) autoriser (privé) · b) attendre · c) après correction README | **a**, dépôt **privé** | Diff audité propre ; fast-forward pur (VERIFIED 07) ; **coût 0 €** (le dépôt GitHub cible existe déjà) | Travail non sauvegardé hors du disque local | Visibilité publique/privée à confirmer | **NON COMMUNIQUÉE — la question a été posée, la réponse n'est pas parvenue** | `OPEN` (aucun push) |
| **D6b** Déploiement | a) après D1/D2/D3 · b) ne pas déployer | **b** | Une autorisation de push ne vaut pas autorisation de déployer | — | Ordre écrit distinct | **NON COMMUNIQUÉE** | `OPEN` |


## 2. Complétude du dossier de Phase 06 (étape A)

Contrôle des 8 attributs requis par décision, dans `../phase-06/` :

| ID | description | options | contraintes | preuves | points non vérifiés | conséquences | décideur | critère de clôture |
|---|---|---|---|---|---|---|---|---|
| D1 | ✓ | ✓ (3 + mentions UE) | ✓ | ✓ (citations sourcées) | ✓ (§5) | ✓ | ✓ | ✓ (import + `SHOW INDEX`) |
| D2 | ✓ | **partiellement** — les plateformes candidates n'étaient pas comparées | ✓ (port/logs) | ✓ | ✓ | ✓ | ✓ | ✓ |
| D2b | ✓ | ✓ | ✓ | ✓ (`/` = 200 sans BDD) | ✓ | ✓ | ✓ | partiellement (dépend console) |
| D3 | ✓ | **absente** (checklist, pas d'options) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ (tests post-prov.) |
| D4 | ✓ | ✓ (A/B) | ✓ | ✓ (27/0/0) | ✓ (pas de CI) | ✓ | ✓ | ✓ |
| D5 | ✓ | ✓ (a/b/c) | ✓ | ✓ (grep runtime) | — | ✓ | ✓ | ✓ |
| D6 | ✓ (2 lignes séparées) | ✓ | ✓ | ✓ (audit 01) | ✓ | ✓ | ✓ | **question dépôt public/privé non posée** |

**Corrections apportées en Phase 07 sans réécrire Phase 06** : options D2 ajoutées (§1) ;
cadre « options » D3 ajouté ; question public/privé formalisée (`02-publication-risks.md` §4) ;
exigence « prix/limites à vérifier chez le fournisseur » explicitée (§3).

## 3. Faits nouveaux vérifiés en Phase 07 (documentation officielle)

| Fait | Source | Effet |
|---|---|---|
| `PORT` par défaut **10000**, modifiable dans la console ; « Render est _habituellement_ capable de détecter et utiliser » un autre port binding ; ports réservés 18012/18013/19099 | render.com/docs/web-services §Port binding | D2 : Apache `:80` **peut** fonctionner, mais l'auto-détection est conditionnelle → à confirmer à la création du service (NON VÉRIFIÉ en conditions réelles) |
| Render **termine le SSL**, puis **transfère en HTTP** au service ; une requête HTTP entrante est **redirigée vers HTTPS** avant terminaison | render.com/docs/web-services §Connecting | D3 : redirect HTTP→HTTPS = **prise en charge plateforme**, pas à coder ; confirme que `COOKIE_SECURE` doit être serveur (l'app voit du HTTP) |
| Health checks : **TCP par défaut**, sonde HTTP avec path en option | render.com/docs/health-checks + troubleshooting-deploys | D2b : une simple liveness TCP suffit à la plateforme ; la readiness reste un besoin interne |
| Render propose **MySQL 8 en « private service » Docker + Disk** (`/var/lib/mysql`), **avec avertissement officiel** : les snapshots de disque ne sont **pas recommandés** pour restaurer une BDD → `mysqldump` recommandé | render.com/docs/deploy-mysql | D1 option (b) : « Render MySQL auto-hébergé » devient une alternative documentée, au prix de backups **auto-gérés** (cron `mysqldump` à écrire) |
| Railway : essai 30 j avec 5 $ de crédits, puis plan payant (page officielle MariaDB : « à partir du Hobby à 5 $/mois ») | railway.com/pricing + page deploy MariaDB | D1 option (a) confirmée ; **les chiffres exacts de quota/rétention restent à vérifier** |
| PlanetScale : **pas de plan gratuit**, « all databases require a paid subscription starting with our Base plan » | planetscale.com/docs/planetscale-plans | D1 option (c) = coût incompressible pour un projet étudiant |

Aucun prix contractuel, SLA ou quota n'est affirmé au-delà de ces extraits ; toute valeur
chiffrée définitive doit être reprise sur la page officielle du fournisseur au moment du choix.

## 4. Arbitrages effectivement reçus (propriétaire, 2026-10-17)

| ID | Réponse | Interprétation retenue | Effet |
|---|---|---|---|
| **D1** | « Je n'ai pas de fournisseur gratuit actuellement, je suis sur xampp » | **Aucun fournisseur choisi.** Une **contrainte nouvelle** est exprimée : pas d'abonnement payant ; la base reste locale | `BLOCKED` — les 3 options instruites sont payantes ; la piste gratuite (TiDB) est techniquement écartée ; il faut instruire une option à coût nul ou très faible (mutualisé PHP+MySQL), ou assumer un petit budget, ou rester en local |
| **D2** | « Render (app) + base externe » | Plateforme applicative = **Render**, base sur un fournisseur distinct | `DECIDED`, mais **inexploitable tant que D1 est ouvert** (aucune base à atteindre) |
| **D4** | « Option B — suite HTTP complète » | Développement d'une **suite HTTP sur serveur réel** engagé pour la Phase 08 | `DECIDED` — contraire à la recommandation du conseiller, enregistré tel quel ; coût ≈ 1 j, aucune dépendance nouvelle |
| **D6a** | *(question posée, **pas de réponse reçue**)* | **Aucune autorisation de push** | Aucun push effectué ; `OPEN` |
| **D2b, D5, D6b** | *(non soumises / non répondues)* | Aucune | `OPEN` ; par défaut : rien déplacé, rien déployé |

### 4.1 Constat d'environnement local (VERIFIED)

- Le propriétaire mentionne **XAMPP** : `C:\xampp` existe et son client `mysql.exe` répond
  **MariaDB 10.4.32**.
- L'exécutable `php` résolu sur le PATH et le client utilisé par les tests des phases
  précédentes appartiennent à **Laragon** (`C:\laragon\bin\php\php-8.3.33…`,
  `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` → même serveur **10.4.32**).
- **Conséquence technique : aucune.** Les deux clients interrogent le même serveur écoutant
  sur 3306 ; les verdictes de compatibilité (MariaDB 10.4.32, FULLTEXT opérationnel) restent
  valables. Noté seulement pour la précision du futur runbook d'installation.

### 4.2 Ce que la contrainte 0 € change au plan

- **La publication Git (D6a) ne coûte rien** et n'implique aucun fournisseur : le dépôt
  GitHub cible existe déjà. Elle peut donc être autorisée **indépendamment** de D1 — c'est
  la seule action à valeur de sauvegarde immédiatement disponible.
- **Le déploiement (D6b) devient conditionnel** : sans base compatible et gratuite, aucune
  mise en production n'est possible, et un Web Service Render gratuit seul servirait une
  app sans données (plus les limites « do not use for production »).
- **Nouvelle instruction nécessaire (Phase 08, si le propriétaire la demande)** : comparer
  les pistes à coût nul ou quasi nul pour un MySQL/MariaDB réel avec FULLTEXT — hébergement
  mutualisé PHP/MySQL, offre UE d'entrée de gamme, VPS déjà payant de l'école — en vérifiant
  pour chacune : version PHP ≥ 8.1, possibilité de docroot sur `public/`, accès MySQL distant,
  présence de `FULLTEXT`, backups. **Aucune de ces pistes n'a été instruite ni vérifiée ici.**

