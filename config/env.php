<?php
/**
 * Parseur natif de fichier .env (alternative légère à phpdotenv)
 *
 * Charge les variables d'environnement depuis le fichier .env situé à la RACINE
 * du projet (un niveau au-dessus de /config) et les rend disponibles via
 * $_ENV, $_SERVER et putenv().
 *
 * Déplacé depuis api/config/env.php lors de la restructuration MVC (Phase 02).
 *
 * @package App\Config
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

/**
 * Charge les variables d'environnement depuis un fichier .env.
 *
 * Parse le fichier ligne par ligne, ignore les commentaires (#) et les lignes
 * sans signe égal, et expose chaque paire clé=valeur globalement.
 *
 * @param string $path Chemin absolu vers le fichier .env.
 * @return bool True si le fichier a été chargé, false s'il est absent.
 */
function loadEnv(string $path): bool
{
    // Le fichier .env n'existe pas (ex : CI) : on sort sans erreur.
    if (!file_exists($path)) {
        return false;
    }

    // On lit ligne à ligne en ignorant les lignes vides et les fins de ligne.
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignore les commentaires et les lignes sans '='.
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        // Sépare la clé de la valeur (au premier '=' uniquement).
        list($name, $value) = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        // Ne surcharge pas une variable déjà définie par l'environnement système.
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }

    return true;
}

// Racine du projet = dossier parent de /config.
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);
