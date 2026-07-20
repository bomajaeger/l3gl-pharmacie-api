<?php
/**
 * Gère la connexion et la déconnexion.
 */
class AuthController
{
    private Utilisateur $modele;

    public function __construct()
    {
        $this->modele = new Utilisateur();
    }

    /** POST /login */
    public function login(): void
    {
        $body = Request::body();

        $nomUtilisateur = trim($body['nom_utilisateur'] ?? '');
        $motDePasse     = $body['mot_de_passe'] ?? '';

        if ($nomUtilisateur === '' || $motDePasse === '') {
            Response::error('Nom d\'utilisateur et mot de passe requis.', 400);
        }

        $utilisateur = $this->modele->findByUsername($nomUtilisateur);

        // Message identique dans les deux cas d'échec : on n'indique jamais
        // si c'est le login ou le mot de passe qui est faux.
        if ($utilisateur === null
            || !password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            Response::error('Identifiants incorrects.', 401);
        }

        // Un peu de ménage à chaque connexion
        $this->modele->purgeExpiredTokens();

        $session = $this->modele->createToken((int) $utilisateur['id']);

        Response::success([
            'token'      => $session['token'],
            'expiration' => $session['expiration'],
            'utilisateur' => [
                'id'              => (int) $utilisateur['id'],
                'nom_utilisateur' => $utilisateur['nom_utilisateur'],
                'role'            => $utilisateur['role'],
            ],
        ]);
    }

    /** POST /logout — invalide le token courant. */
    public function logout(): void
    {
        AuthMiddleware::authenticate();   // refuse si pas de token valide

        $this->modele->deleteToken(Request::bearerToken());

        Response::success(['message' => 'Déconnexion réussie.']);
    }

    /** GET /me — renvoie l'utilisateur du token. Sert à tester le middleware. */
    public function me(): void
    {
        $utilisateur = AuthMiddleware::authenticate();

        Response::success([
            'id'              => (int) $utilisateur['id'],
            'nom_utilisateur' => $utilisateur['nom_utilisateur'],
            'role'            => $utilisateur['role'],
            'token_expire_le' => $utilisateur['date_expiration'],
        ]);
    }
}