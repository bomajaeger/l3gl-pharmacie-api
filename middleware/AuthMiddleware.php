<?php
/**
 * Garde-barrière des routes protégées.
 * Appelé au début de chaque route qui exige un token valide.
 */
class AuthMiddleware
{
    /**
     * Bloque la requête (401) si le token est absent, inconnu ou expiré.
     * @return array l'utilisateur authentifié, si tout va bien
     */
    public static function authenticate(): array
    {
        $token = Request::bearerToken();

        if ($token === null) {
            Response::error('Token manquant. Header attendu : Authorization: Bearer {token}', 401);
        }

        $modele = new Utilisateur();
        $utilisateur = $modele->findUserByToken($token);

        if ($utilisateur === null) {
            // On ne distingue pas "inconnu" de "expiré" : moins d'infos
            // données à un éventuel attaquant.
            Response::error('Token invalide ou expiré. Veuillez vous reconnecter.', 401);
        }

        return $utilisateur;
    }
}