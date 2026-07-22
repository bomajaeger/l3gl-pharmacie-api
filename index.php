<?php
/**
 * Point d'entrée unique de l'API.
 * Toutes les requêtes arrivent ici via le .htaccess.
 */

declare(strict_types=1);

// --- Chargement des dépendances -------------------------------------
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Request.php';
require_once __DIR__ . '/helpers/Logger.php';
require_once __DIR__ . '/models/Utilisateur.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/models/Fournisseur.php';
require_once __DIR__ . '/controllers/FournisseurController.php';
require_once __DIR__ . '/models/Medicament.php';
require_once __DIR__ . '/controllers/MedicamentController.php';
require_once __DIR__ . '/models/Vente.php';
require_once __DIR__ . '/controllers/VenteController.php';

// Segment d'URL correspondant au dossier de l'API dans htdocs.
const BASE_PATH = '/apiPharma';

// --- Découpage de l'URL demandée ------------------------------------
$method = $_SERVER['REQUEST_METHOD'];

// On isole le chemin, sans la query string (?q=...)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

// On retire le préfixe du dossier : /apiPharma/medicaments/5 -> medicaments/5
if (str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}

$path = trim($path, '/');

// segments[0] = ressource, segments[1] = id ou sous-route
$segments = $path === '' ? [] : explode('/', $path);
$resource = $segments[0] ?? '';
$id       = $segments[1] ?? null;

// --- Routage ---------------------------------------------------------
try {
    switch ($resource) {

        case 'ping':
            Response::success(['message' => 'API opérationnelle']);

        case 'login':
            if ($method !== 'POST') {
                Response::error('Méthode non autorisée. Utilisez POST.', 405);
            }
            (new AuthController())->login();
            break;

        case 'logout':
            if ($method !== 'POST') {
                Response::error('Méthode non autorisée. Utilisez POST.', 405);
            }
            (new AuthController())->logout();
            break;

        case 'me':
            if ($method !== 'GET') {
                Response::error('Méthode non autorisée. Utilisez GET.', 405);
            }
            (new AuthController())->me();
            break;
        
        case 'fournisseurs':
            $controleur = new FournisseurController();

            // Les routes avec {id} exigent un id numérique
            if ($id !== null && !ctype_digit($id)) {
                Response::error('Identifiant invalide.', 400);
            }

            switch ($method) {
                case 'GET':
                    $id === null
                        ? $controleur->index()
                        : $controleur->show((int) $id);
                    break;

                case 'POST':
                    $controleur->store();
                    break;

                case 'PUT':
                    if ($id === null) {
                        Response::error('Identifiant requis pour une modification.', 400);
                    }
                    $controleur->update((int) $id);
                    break;

                case 'DELETE':
                    if ($id === null) {
                        Response::error('Identifiant requis pour une suppression.', 400);
                    }
                    $controleur->destroy((int) $id);
                    break;

                default:
                    Response::error('Méthode non autorisée.', 405);
            }
            break;

        case 'medicaments':
            $controleur = new MedicamentController();

            // /medicaments/search est un cas à part : le 2e segment
            // n'est pas un id mais un mot-clé.
            if ($id === 'search') {
                if ($method !== 'GET') {
                    Response::error('Méthode non autorisée.', 405);
                }
                $controleur->search();
                break;
            }

            if ($id !== null && !ctype_digit($id)) {
                Response::error('Identifiant invalide.', 400);
            }

            switch ($method) {
                case 'GET':
                    $id === null
                        ? $controleur->index()
                        : $controleur->show((int) $id);
                    break;

                case 'POST':
                    $controleur->store();
                    break;

                case 'PUT':
                    if ($id === null) {
                        Response::error('Identifiant requis pour une modification.', 400);
                    }
                    $controleur->update((int) $id);
                    break;

                case 'DELETE':
                    if ($id === null) {
                        Response::error('Identifiant requis pour une suppression.', 400);
                    }
                    $controleur->destroy((int) $id);
                    break;

                default:
                    Response::error('Méthode non autorisée.', 405);
            }
            break;

        case 'ventes':
            $controleur = new VenteController();

            if ($id === 'stats') {
                if ($method !== 'GET') {
                    Response::error('Méthode non autorisée.', 405);
                }
                $controleur->stats();
                break;
            }

            if ($id !== null && !ctype_digit($id)) {
                Response::error('Identifiant invalide.', 400);
            }

            switch ($method) {
                case 'GET':
                    $id === null
                        ? $controleur->index()
                        : $controleur->show((int) $id);
                    break;

                case 'POST':
                    $controleur->store();
                    break;

                default:
                    // Pas de PUT ni DELETE : une vente enregistrée
                    // ne se modifie pas et ne s'efface pas.
                    Response::error(
                        'Une vente ne peut être ni modifiée ni supprimée.',
                        405
                    );
            }
            break;

        default:
            Response::error('Route introuvable : ' . $path, 404);
    }

} catch (PDOException $e) {
    // Le détail SQL part dans le log, jamais vers le client.
    Logger::erreur('Erreur base de données', $e->getMessage());
    Response::error('Une erreur interne est survenue. Réessayez plus tard.', 500);

} catch (Throwable $e) {
    Logger::erreur('Erreur serveur', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Une erreur interne est survenue. Réessayez plus tard.', 500);
}