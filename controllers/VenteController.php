<?php
class VenteController
{
    private Vente $modele;

    public function __construct()
    {
        $this->modele = new Vente();
    }

    /** GET /ventes */
    public function index(): void
    {
        AuthMiddleware::authenticate();

        Response::success(array_map(
            fn(array $v) => $this->formater($v),
            $this->modele->getAll()
        ));
    }

    /** GET /ventes/{id} */
    public function show(int $id): void
    {
        AuthMiddleware::authenticate();

        $vente = $this->modele->getById($id);

        if ($vente === null) {
            Response::error("Vente introuvable (id: $id).", 404);
        }

        Response::success($this->formater($vente));
    }

    /**
     * POST /ventes
     * Le client n'envoie que l'id du médicament et la quantité.
     * Prix unitaire et total sont déterminés par le serveur.
     */
    public function store(): void
    {
        AuthMiddleware::authenticate();

        $body = Request::body();
        $erreurs = [];

        $idMedicament = $body['id_medicament'] ?? null;
        if ($idMedicament === null || !is_numeric($idMedicament) || (int) $idMedicament <= 0) {
            $erreurs[] = 'Le médicament est obligatoire.';
        }

        $quantite = $body['quantite_vendue'] ?? null;
        if ($quantite === null || !is_numeric($quantite) || (int) $quantite != $quantite) {
            $erreurs[] = 'La quantité doit être un nombre entier.';
        } elseif ((int) $quantite <= 0) {
            $erreurs[] = 'La quantité doit être supérieure à zéro.';
        }

        if ($erreurs !== []) {
            Response::error(implode(' ', $erreurs), 400);
        }

        try {
            $idVente = $this->modele->enregistrer((int) $idMedicament, (int) $quantite);

        } catch (RuntimeException $e) {
            // Stock insuffisant ou médicament inexistant :
            // erreur de l'utilisateur, pas du serveur.
            Response::error($e->getMessage(), 400);
        }

        Response::success($this->formater($this->modele->getById($idVente)), 201);
    }

    /** GET /ventes/stats */
    public function stats(): void
    {
        AuthMiddleware::authenticate();

        $s = $this->modele->statistiques();

        Response::success([
            'nombre_ventes'    => (int) $s['nombre_ventes'],
            'chiffre_affaires' => (int) $s['chiffre_affaires'],   // FCFA
            'unites_vendues'   => (int) $s['unites_vendues'],
        ]);
    }

    // -----------------------------------------------------------------

    private function formater(array $v): array
    {
        return [
            'id'              => (int) $v['id'],
            'id_medicament'   => (int) $v['id_medicament'],
            'medicament_nom'  => $v['medicament_nom'],
            'quantite_vendue' => (int) $v['quantite_vendue'],
            'prix_unitaire'   => (int) $v['prix_unitaire'],
            'prix_total'      => (int) $v['prix_total'],
            'date_vente'      => $v['date_vente'],
        ];
    }
}