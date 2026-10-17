# 03 — Exigences plateforme, Docker, HTTPS et sécurité de production (Phase 06)

## 1. Port d'écoute et interface réseau

| Constat | Détail | Statut |
|---|---|---|
| Apache écoute `*:80` | `<VirtualHost *:80>` + `EXPOSE 80` générés dans le `Dockerfile` | VERIFIED |
| **Aucune lecture de `$PORT`** | grep `PORT`/`X-Forwarded` : aucun traitement dans l'image ni l'app ; le commentaire du Dockerfile (l. 65-66) anticipe déjà ce point | VERIFIED |
| Conséquence plateforme | Une plateforme qui **impose** un port dynamique via `$PORT` (cas courant des PaaS non-Docker) imposerait une **modification ultérieure** : faire écouter Apache sur `${PORT:-80}` + adapter `EXPOSE`. Sur Render (service Docker), le port est **déclarable** dans la configuration → pas de changement code si 80 est accepté | INFERRED (dépend du réglage réel retenu) |

## 2. Health check — analyse des routes existantes

| Candidate | Comportement observé | Usage |
|---|---|---|
| `GET /` | **200 même sans base** (test 06 : conteneur lancé sans `.env` → page rendue, aucune query bloquante) | Sonde de **livabilité** (processus+Apache+PHP+autoload) — **inadaptée comme sonde de préparation (readiness)** : ne détecte pas une panne BDD |
| `GET /login`, `/mentions` | Pages publiques légères (pas de donnée privée) | Alternatives livabilité |
| `/manifest.json`, `/sw.js` | Fichiers statiques servis par Apache (sans PHP) | Valident docroot/assets uniquement |
| Sonde « avec BDD » | **N'existe pas** ; e.g. une route `/healthcheck` (DB ping) demanderait un ajout de code | **Hors périmètre 06** — à décider en phase de déploiement |

**Recommandation :** accepterait `GET / → 200` pour le redémarrage auto (liveness) ;
pour le readiness, soit s'en remettre aux tests de fumée post-déploiement du runbook
§7, soit autoriser ultérieurement une micro-route de santé (décision humaine).

## 3. Journaux et diagnostic (VERIFIED)

Test 06 : `docker logs` sur le conteneur montre **access log** (`GET / 200`) et
**error log** Apache sur stdout/stderr → directement collectables par la plateforme.
Message mineur : `AH00558` (ServerName non défini) — cosmétique ; ajouter
`ServerName localhost` globalement réduirait le bruit (modification ultérieure optionnelle).

## 4. Variables obligatoires et comportement d'échec

| Variable | Rôle | Si absente |
|---|---|---|
| `DB_DSN` ou `DB_HOST/DB_NAME/DB_USER/DB_PASS` | connexion | `RuntimeException` → **500 générique** sans fuite (VERIFIED par lecture `db.php`/`index.php`) |
| `APP_DEBUG` | traces | défaut `false` → `display_errors=0` |
| `COOKIE_SECURE` | flag secure du cookie de session | défaut non-safe → à passer à `true` derrière HTTPS |
| `COOKIE_SAMESITE` | Lax recommandé | défaut Strict (peut gêner certains retours OAuth-like, non utilisés ici) |
| `APP_BASE_PATH` | sous-répertoire | défaut racine |

Échec conteneur : Apache tourne en foreground (`apache2-foreground`) ; la mort du
processus = arrêt du conteneur → politique de redémarrage de la plateforme applicable
(INFERRED ; comportement exact plateforme : NOT VERIFIED).

## 5. HTTPS, cookies et proxy (exigences — pas une validation)

- **Flag `secure` côté serveur :** le drapeau du cookie de session est décidé par la
  config (`COOKIE_SECURE`), pas par le protocole de la requête → **pas besoin** de gérer
  `X-Forwarded-Proto` pour les cookies (VERIFIED par lecture `public/index.php`).
- **Mixte/TLS :** la CSP applique `upgrade-insecure-requests` ; le contenu est servi en
  relatif via `url()`/`asset()`.
- **Redirect HTTP→HTTPS :** à activer dans la console de la plateforme (le `.htaccess`
  local ne force pas HTTPS — adapté aux tests locaux). **NON CONFIGURÉ = NOT VERIFIED.**
- **Debug exposition :** `APP_DEBUG=false` obligatoire en prod (gabarit `.env.production.example`
  déjà aligné).
- **Secrets :** hors image (`.dockerignore` + `rm -f .env*` à la build — VERIFIED 04B/05) ;
  à injecter par le coffre de la plateforme ; surveillance journaux : ne jamais y loguer
  de DSN complet.

## 6. Tests à faire APRÈS provisionnement, AVANT ouverture publique

1. `curl -I https://<domaine>/` → 200, HSTS si offert, aucune redirection mixte.
2. Connexion réelle via HTTPS → inspecter le cookie : `Secure`, `HttpOnly`, `SameSite=Lax`.
3. `POST /logout` sans jeton → 400 ; parcours login/logout complet → 403 après déconnexion.
4. Recherche `?q=` avec 1000+ fleurs → latence FULLTEXT acceptable.
5. Couper temporairement la base (rotation/indispo) → l'app doit montrer l'erreur 500
   **générique** sans trace ni DSN.
6. Vérifier l'absence de fuite de secret dans les logs plateforme.

## 7. Résumé par catégorie

- **Déjà prouvé en local :** port 80 + docroot public, logs stdout, erreurs sans fuite,
  build/run 200, env lues.
- **Dépendant du fournisseur :** latence/auth réseau vers la BDD, TLS BDD, backups.
- **Vérifiables seulement après provisionnement :** HTTPS bord, HSTS, cookies Secure en
  conditions réelles, redémarrages plateforme, health check effectif.
- **Exigeant une modification ultérieure (non appliquée ici) :** écoute `${PORT:-80}`
  si la plateforme l'impose ; micro-route `/healthcheck` (readiness) si voulue ;
  `ServerName` global (confort logs) ; correction du README (01 §5).
