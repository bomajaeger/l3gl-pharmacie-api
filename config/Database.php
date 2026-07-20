<?php
/**
 * Connexion PDO en singleton : une seule instance partagée
 * pour toute la durée de la requête HTTP.
 */
class Database
{
    private const HOST    = 'localhost';
    private const PORT    = 3306;
    private const DB_NAME = 'pharmacie_db';
    private const USER    = 'root';
    private const PASS    = '';          // XAMPP par défaut

    private static ?PDO $instance = null;

    // Constructeur privé : on interdit le "new Database()".
    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                self::HOST, self::PORT, self::DB_NAME
            );

            self::$instance = new PDO($dsn, self::USER, self::PASS, [
                // Les erreurs SQL lèvent une exception au lieu d'être silencieuses
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // fetch() renvoie un tableau associatif, pas un doublon indexé
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Vraies requêtes préparées côté MySQL (protection injection)
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }
}