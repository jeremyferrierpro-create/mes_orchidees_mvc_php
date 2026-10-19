/**
 * @file tests/js/router.test.mjs
 * @description Test unitaire exécutable du routeur client (core/router.js) et
 *              vérification statique du registre d'initialisateurs (app.js).
 * @author Jérémy Ferrier
 * @version 1.0
 *
 * Lancement : node tests/js/router.test.mjs
 *
 * Pourquoi un import par « data: URL » ? Le dépôt n'a pas de package.json et
 * router.js est un module ES (.js). Plutôt que d'ajouter une configuration
 * Node (hors périmètre), je lis le VRAI fichier et je l'évalue comme module ES
 * natif. Ainsi, c'est bien le code de production qui est testé.
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const routerPath = resolve(here, '../../public/assets/js/core/router.js');
const appPath = resolve(here, '../../public/assets/js/app.js');

// Chargement du vrai module de production.
const src = readFileSync(routerPath, 'utf8');
const mod = await import('data:text/javascript;base64,' + Buffer.from(src).toString('base64'));
const { getCurrentPage } = mod;

let pass = 0;
let fail = 0;

/** Simule le navigateur puis demande la page reconnue. */
function pageFor(pathname) {
    globalThis.window = { location: { pathname } };
    return getCurrentPage();
}

function check(label, actual, expected) {
    const ok = actual === expected;
    if (ok) {
        pass++;
        console.log(`  PASS | ${label} | attendu=${expected} obtenu=${actual}`);
    } else {
        fail++;
        console.log(`  FAIL | ${label} | attendu=${expected} obtenu=${actual}`);
    }
}

function checkTrue(label, cond, detail = '') {
    if (cond) {
        pass++;
        console.log(`  PASS | ${label}${detail ? ' | ' + detail : ''}`);
    } else {
        fail++;
        console.log(`  FAIL | ${label}${detail ? ' | ' + detail : ''}`);
    }
}

console.log('--- B1 · Reconnaissance des routes MVC (getCurrentPage) ---');

// 1. /macollection exact
check('/macollection → collection-dashboard', pageFor('/macollection'), 'collection-dashboard');

// 2. /collection/{id}
check('/collection/{uuid} → collection-detail',
    pageFor('/collection/22a27a2c-ca44-11f1-8687-c8d9d212e9fa'), 'collection-detail');

// 3. /rappels
check('/rappels → rappels', pageFor('/rappels'), 'rappels');

// 4. Distinction des routes aux initialiseurs différents
check('/collections (API JSON) → other', pageFor('/collections'), 'other');
check('/collection/new (création POST) → other', pageFor('/collection/new'), 'other');
checkTrue('/macollection ≠ /collection/{id}',
    pageFor('/macollection') !== pageFor('/collection/abc'),
    'dashboard vs detail distincts');

// 5. Avec et sans préfixe d'installation (indépendance au base_path)
check('sans préfixe : /rappels', pageFor('/rappels'), 'rappels');
check('avec préfixe : /mes-orchidees/macollection', pageFor('/mes-orchidees/macollection'), 'collection-dashboard');
check('avec préfixe : /mes-orchidees/collection/abc', pageFor('/mes-orchidees/collection/abc'), 'collection-detail');
check('avec préfixe : /mes-orchidees/rappels', pageFor('/mes-orchidees/rappels'), 'rappels');

// 6. Slashes terminaux et repli
check('/macollection/ (slash final)', pageFor('/macollection/'), 'collection-dashboard');
check('/collection/abc/ (slash final)', pageFor('/collection/abc/'), 'collection-detail');
check('/ (racine) → home', pageFor('/'), 'home');
check('/route-inexistante → other', pageFor('/route-inexistante'), 'other');
check('/orchid/abc → other', pageFor('/orchid/abc'), 'other');
check('/conseil/abc → other', pageFor('/conseil/abc'), 'other');

// 7. Compatibilité avec les anciennes pages statiques *.php
check('/macollection.php → collection (legacy)', pageFor('/macollection.php'), 'collection');
check('/conseils.php → conseils (legacy)', pageFor('/conseils.php'), 'conseils');
check('/administration.php → administration (legacy)', pageFor('/administration.php'), 'administration');
check('/authentification.php → authentication (legacy)', pageFor('/authentification.php'), 'authentication');
check('/index.php → home (legacy)', pageFor('/index.php'), 'home');
check('/encyclopedie.php → encyclopedia (legacy)', pageFor('/encyclopedie.php'), 'encyclopedia');
check('/mon_orchidee/encyclopedie.php (legacy préfixé)', pageFor('/mon_orchidee/encyclopedie.php'), 'encyclopedia');

// Routes MVC additionnelles reconnues
check('/administration → admin-dashboard (≠ legacy)', pageFor('/administration'), 'admin-dashboard');
checkTrue('/administration MVC ≠ legacy administration',
    pageFor('/administration') !== pageFor('/administration.php'),
    'le module admin legacy n\'est pas déclenché sur la vue MVC');
check('/login → authentication', pageFor('/login'), 'authentication');
check('/register → authentication', pageFor('/register'), 'authentication');
check('/conseils → conseils', pageFor('/conseils'), 'conseils');
check('/encyclopedie → encyclopedia', pageFor('/encyclopedie'), 'encyclopedia');

// Insensibilité à la casse
check('/MACOLLECTION (majuscules)', pageFor('/MACOLLECTION'), 'collection-dashboard');

// Robustesse : ne jamais lever sur des chemins limites
let threw = false;
try { pageFor(''); pageFor('//'); pageFor('/collection'); } catch (e) { threw = true; }
checkTrue('aucune exception sur chemins limites', !threw);

console.log('\n--- B2 · Registre app.js : les routes MVC ne branchent AUCUN module legacy dangereux (vérif statique) ---');
const appSrc = readFileSync(appPath, 'utf8');

// Identifiants MVC présents et mappés soit à un no-op explicite, soit (Phase D)
// à l'initialiseur MVC dédié déclaré ci-dessous. Les clés sans module autorisé
// doivent rester à () => {} : le test demeure strict, il ne fait qu'acter
// qu'une route MVC peut légitimement brancher un initialiseur de la phase en
// cours — jamais un module legacy du site statique.
const MVC_INITIALIZERS = {
    'collection-dashboard': ['initRappelsModal'],
    'collection-detail': ['initRappelsModal'],
    'rappels': [],
    'admin-dashboard': []
};
const LEGACY_MODULES = ['initCollection', 'initAdministration', 'initSearch',
    'initAddButton', 'initConseils', 'initAuthForm', 'initBackgroundAnimation'];
for (const id of Object.keys(MVC_INITIALIZERS)) {
    const found = appSrc.match(new RegExp(`['"]?${id}['"]?\\s*:\\s*([^,\n]+)`));
    const value = found ? found[1].trim() : '';
    const noOp = /^\(\s*\)\s*=>\s*\{\s*\}$/.test(value);
    const allowed = MVC_INITIALIZERS[id].includes(value);
    const legacy = LEGACY_MODULES.some((m) => value.includes(m));
    checkTrue(`« ${id} » mappé sur un no-op ou un initialiseur MVC autorisé (${value || 'absent'})`,
        value !== '' && !legacy && (noOp || allowed));
}

// Le no-op MVC ne doit PAS être un faux initialiseur : la table doit rester
// explicite et ces clés doivent exister (déjà vérifié ci-dessus).
checkTrue('getCurrentPage importé dans app.js', /import\s*\{\s*getCurrentPage\s*\}/.test(appSrc));

// Les modules legacy dangereux restent réservés aux identifiants legacy.
checkTrue('initCollection n\'est branché qu\'à la clé legacy « collection »',
    /collection:\s*initCollection/.test(appSrc) && !/collection-dashboard['"]?\s*:\s*initCollection/.test(appSrc));
checkTrue('initAdministration n\'est branché qu\'à la clé legacy « administration »',
    /administration:\s*initAdministration/.test(appSrc) && !/admin-dashboard['"]?\s*:\s*initAdministration/.test(appSrc));

// Sécurité d\'initialisation (B3) : appel de l\'initialisateur cloisonné par try/catch.
checkTrue('appel de l\'initialisateur protégé par try/catch',
    /try\s*\{[\s\S]*initializer\(\);[\s\S]*\}\s*catch/.test(appSrc));

console.log('\n=== Bilan routeur JS ===');
console.log(`PASS ${pass} · FAIL ${fail}`);
process.exit(fail === 0 ? 0 : 1);
