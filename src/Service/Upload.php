<?php
/**
 * Service d'upload d'image — photo personnelle ajoutée à une collection.
 *
 * Unique point d'entrée de l'application pour recevoir un fichier image envoyé par
 * un navigateur (formulaire `multipart/form-data`). Aucune autre logique ne touche
 * `$_FILES` directement.
 *
 * SÉCURITÉ :
 *  - vérifie le code d'erreur PHP (`$_FILES['...']['error']`) ;
 *  - limite la taille brute (par défaut 5 Mo) ;
 *  - renifle le VRAI type MIME via `finfo` (et non l'en-tête de confiance du client) :
 *    seules jpeg / png / webp / gif sont admises ;
 *  - fabrique un nom aléatoire (impossible à deviner) avec l'extension déduite du
 *    type réellement détecté : pas d'extension forgée, pas de chemin utilisateur ;
 *  - stocke SOUS /public/assets/images/uploads (jamais un chemin fourni par le client).
 *
 * L'URL publique est reconstruite par l'entité (Collection::photoUrl() → asset()).
 *
 * @package App\Service
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-18
 */

namespace App\Service;

class Upload
{
    /** Type MIME autorisé → extension de fichier attribuée. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /** Taille maximale acceptée (octets). */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Traite un champ fichier de photo.
     *
     * @param array|null $file Une entrée de `$_FILES` (ex : `$_FILES['photo']`).
     * @return string|null Chemin relatif de stockage (`assets/images/uploads/…`),
     *                     ou null si aucun fichier n'a été envoyé (upload optionnel).
     * @throws \InvalidArgumentException Si le fichier est présent mais invalide
     *                                   (taille, type, ou échec de déplacement).
     */
    public static function image(?array $file): ?string
    {
        // Aucun champ, ou champ vide volontaire : l'upload est optionnel.
        if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("Le téléversement de la photo a échoué (code " . (int) $file['error'] . ").");
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new \InvalidArgumentException("Fichier d'upload invalide.");
        }

        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new \InvalidArgumentException("Photo trop volumineuse (5 Mo maximum).");
        }

        // Renifle le type réel, jamais celui déclaré par le client.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new \InvalidArgumentException("Format d'image non accepté (JPEG, PNG, WEBP ou GIF uniquement).");
        }

        $dir = self::uploadDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Impossible de créer le dossier de destination des photos.");
        }

        $name = 'photo-' . bin2hex(random_bytes(12)) . '.' . self::ALLOWED[$mime];
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException("Impossible d'enregistrer la photo sur le serveur.");
        }

        // Chemin relatif servi, aligné sur la convention des images du catalogue.
        return 'assets/images/uploads/' . $name;
    }

    /**
     * Chemin absolu du répertoire de stockage (sous /public, servible par le serveur).
     *
     * @return string
     */
    private static function uploadDir(): string
    {
        // src/Service → deux niveaux au-dessus = racine projet, puis /public.
        $root = dirname(__DIR__, 2);
        return $root . '/public/assets/images/uploads';
    }
}
