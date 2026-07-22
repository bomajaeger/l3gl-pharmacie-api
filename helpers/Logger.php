<?php
/**
 * Journalisation des erreurs dans un fichier.
 * Chaque entrée : date, méthode HTTP, route, message.
 * Le fichier logs/ n'est jamais exposé (protégé par .htaccess).
 */
class Logger
{
    private const DOSSIER = __DIR__ . '/../logs';

    public static function erreur(string $message, ?string $details = null): void
    {
        // Crée le dossier au premier appel s'il n'existe pas
        if (!is_dir(self::DOSSIER)) {
            mkdir(self::DOSSIER, 0755, true);
        }

        $date    = date('Y-m-d H:i:s');
        $methode = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $route   = $_SERVER['REQUEST_URI'] ?? '-';

        $ligne = "[$date] $methode $route — $message";
        if ($details !== null) {
            $ligne .= " | $details";
        }
        $ligne .= PHP_EOL;

        // FILE_APPEND ajoute à la fin, LOCK_EX évite les écritures
        // entremêlées si deux requêtes loguent en même temps.
        file_put_contents(self::DOSSIER . '/erreurs.log', $ligne, FILE_APPEND | LOCK_EX);
    }
}