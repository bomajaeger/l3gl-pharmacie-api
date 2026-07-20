<?php
/**
 * Centralise l'écriture des réponses JSON et des codes HTTP.
 * Aucun echo ne doit exister ailleurs dans l'API.
 */
class Response
{
    /** Envoie une réponse et arrête l'exécution. */
    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, int $code = 200): never
    {
        self::json(['success' => true, 'data' => $data], $code);
    }

    public static function error(string $message, int $code = 400): never
    {
        self::json(['success' => false, 'message' => $message], $code);
    }
}