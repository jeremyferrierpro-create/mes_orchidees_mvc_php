<?php
/**
 * Parseur natif de fichier .env (alternative légère à phpdotenv)
 * 
 * Ce fichier charge les variables d'environnement depuis un fichier .env
 * et les rend disponibles via $_ENV, $_SERVER et putenv().
 * 
 * @package MesOrchidées\API\Config
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */

/**
 * Charge les variables d'environnement depuis un fichier .env
 * 
 * Parse le fichier ligne par ligne, ignore les commentaires (lignes commençant par #),
 * et extrait les paires clé=valeur pour les rendre disponibles globalement.
 * 
 * @param string $path Chemin vers le fichier .env
 * @return bool True si le fichier a été chargé avec succès, false s'il n'existe pas
 */
function loadEnv(string $path): bool {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignore les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Ignore les lignes sans signe égal
        if (strpos($line, '=') === false) {
            continue;
        }

        // Extrait clé et valeur
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Ne surcharge pas les variables déjà définies
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Charge .env depuis la racine du projet (/mes_orchidees/)
$envPath = dirname(__DIR__, 2) . '/.env';
loadEnv($envPath);
