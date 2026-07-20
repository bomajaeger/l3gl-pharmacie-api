<?php
/**
 * Lecture de la requête entrante.
 * PHP ne remplit $_POST que pour du form-urlencoded en POST :
 * pour du JSON (et pour PUT/DELETE) il faut lire php://input.
 */
class Request
{
    /** Corps JSON décodé en tableau associatif. */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);

        // json_decode renvoie null si le JSON est invalide
        return is_array($data) ? $data : [];
    }

    /** Récupère le token du header "Authorization: Bearer xxx". */
    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']   // via le .htaccess
            ?? null;

        if ($header === null) {
            return null;
        }

        return preg_match('/Bearer\s+(.+)/i', $header, $m) ? trim($m[1]) : null;
    }
}