<?php
/**
 * Traite les requêtes HTTP sur /fournisseurs.
 * Rôle : valider les entrées, appeler le modèle, formater la sortie.
 */
class FournisseurController
{
    private Fournisseur $modele;

    public function __construct()
    {
        $this->modele = new Fournisseur();
    }

    /** GET /fournisseurs */
    public function index(): void
    {
        AuthMiddleware::authenticate();

        // Les id remontent en string depuis MySQL : on les caste pour le C#
        $liste = array_map(
            fn(array $f) => $this->formater($f),
            $this->modele->getAll()
        );

        Response::success($liste);
    }

    /** GET /fournisseurs/{id} */
    public function show(int $id): void
    {
        AuthMiddleware::authenticate();

        $fournisseur = $this->modele->getById($id);

        if ($fournisseur === null) {
            Response::error("Fournisseur introuvable (id: $id).", 404);
        }

        Response::success($this->formater($fournisseur));
    }

    /** POST /fournisseurs */
    public function store(): void
    {
        AuthMiddleware::authenticate();

        $donnees = $this->valider(Request::body());

        $id = $this->modele->create($donnees);

        // 201 Created : la ressource a été créée
        Response::success($this->formater($this->modele->getById($id)), 201);
    }

    /** PUT /fournisseurs/{id} */
    public function update(int $id): void
    {
        AuthMiddleware::authenticate();

        if ($this->modele->getById($id) === null) {
            Response::error("Fournisseur introuvable (id: $id).", 404);
        }

        $donnees = $this->valider(Request::body());

        $this->modele->update($id, $donnees);

        Response::success($this->formater($this->modele->getById($id)));
    }

    /** DELETE /fournisseurs/{id} */
    public function destroy(int $id): void
    {
        AuthMiddleware::authenticate();

        if ($this->modele->getById($id) === null) {
            Response::error("Fournisseur introuvable (id: $id).", 404);
        }

        // Suppression autorisée, mais on informe de l'effet de bord :
        // les médicaments concernés se retrouvent sans fournisseur.
        $nbMedicaments = $this->modele->countMedicaments($id);

        $this->modele->delete($id);

        Response::success([
            'message' => $nbMedicaments > 0
                ? "Fournisseur supprimé. $nbMedicaments médicament(s) sont désormais sans fournisseur."
                : 'Fournisseur supprimé.',
        ]);
    }

    // -----------------------------------------------------------------
    //  Méthodes internes
    // -----------------------------------------------------------------

    /**
     * Valide et nettoie les données entrantes.
     * Coupe la requête avec un 400 si quelque chose ne va pas.
     */
    private function valider(array $body): array
    {
        $erreurs = [];

        $nom = trim($body['nom'] ?? '');

        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom) > 100) {
            $erreurs[] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        $telephone = trim($body['telephone'] ?? '');

        // Optionnel, mais s'il est fourni il doit ressembler à un numéro
        if ($telephone !== '' && !preg_match('/^[0-9+\s()-]{6,20}$/', $telephone)) {
            $erreurs[] = 'Le téléphone est invalide.';
        }

        if ($erreurs !== []) {
            Response::error(implode(' ', $erreurs), 400);
        }

        return [
            'nom'       => $nom,
            'contact'   => trim($body['contact'] ?? '') ?: null,
            'telephone' => $telephone ?: null,
            'adresse'   => trim($body['adresse'] ?? '') ?: null,
        ];
    }

    /** Force les types avant l'envoi en JSON (MySQL renvoie tout en string). */
    private function formater(array $f): array
    {
        return [
            'id'        => (int) $f['id'],
            'nom'       => $f['nom'],
            'contact'   => $f['contact'],
            'telephone' => $f['telephone'],
            'adresse'   => $f['adresse'],
        ];
    }
}