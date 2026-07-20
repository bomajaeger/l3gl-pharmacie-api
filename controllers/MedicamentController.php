<?php
class MedicamentController
{
    private Medicament $modele;

    public function __construct()
    {
        $this->modele = new Medicament();
    }

    /** GET /medicaments */
    public function index(): void
    {
        AuthMiddleware::authenticate();

        Response::success(array_map(
            fn(array $m) => $this->formater($m),
            $this->modele->getAll()
        ));
    }

    /** GET /medicaments/{id} */
    public function show(int $id): void
    {
        AuthMiddleware::authenticate();

        $medicament = $this->modele->getById($id);

        if ($medicament === null) {
            Response::error("Médicament introuvable (id: $id).", 404);
        }

        Response::success($this->formater($medicament));
    }

    /** GET /medicaments/search?q=... */
    public function search(): void
    {
        AuthMiddleware::authenticate();

        $terme = trim($_GET['q'] ?? '');

        // Recherche vide = liste complète, plutôt qu'une erreur :
        // ça simplifie le WPF quand l'utilisateur efface la barre.
        $resultats = $terme === ''
            ? $this->modele->getAll()
            : $this->modele->search($terme);

        Response::success(array_map(
            fn(array $m) => $this->formater($m),
            $resultats
        ));
    }

    /** POST /medicaments */
    public function store(): void
    {
        AuthMiddleware::authenticate();

        $donnees = $this->valider(Request::body());

        $id = $this->modele->create($donnees);

        Response::success($this->formater($this->modele->getById($id)), 201);
    }

    /** PUT /medicaments/{id} */
    public function update(int $id): void
    {
        AuthMiddleware::authenticate();

        if ($this->modele->getById($id) === null) {
            Response::error("Médicament introuvable (id: $id).", 404);
        }

        $donnees = $this->valider(Request::body());

        $this->modele->update($id, $donnees);

        Response::success($this->formater($this->modele->getById($id)));
    }

    /** DELETE /medicaments/{id} */
    public function destroy(int $id): void
    {
        AuthMiddleware::authenticate();

        if ($this->modele->getById($id) === null) {
            Response::error("Médicament introuvable (id: $id).", 404);
        }

        // ON DELETE RESTRICT en base : on intercepte avant que MySQL
        // ne lève une erreur, pour renvoyer un message compréhensible.
        $nbVentes = $this->modele->countVentes($id);

        if ($nbVentes > 0) {
            Response::error(
                "Suppression impossible : ce médicament est lié à $nbVentes vente(s). "
                . "L'historique des ventes doit être préservé.",
                409   // Conflict
            );
        }

        $this->modele->delete($id);

        Response::success(['message' => 'Médicament supprimé.']);
    }

    // -----------------------------------------------------------------

    private function valider(array $body): array
    {
        $erreurs = [];

        $nom = trim($body['nom'] ?? '');
        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom) > 100) {
            $erreurs[] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        // Prix en FCFA : entier, jamais de décimales.
        $prix = $body['prix'] ?? null;
        if ($prix === null || $prix === '') {
            $erreurs[] = 'Le prix est obligatoire.';
        } elseif (!is_numeric($prix) || (int) $prix != $prix) {
            $erreurs[] = 'Le prix doit être un nombre entier (FCFA).';
        } elseif ((int) $prix < 0) {
            $erreurs[] = 'Le prix ne peut pas être négatif.';
        }

        $stock = $body['quantite_stock'] ?? 0;
        if (!is_numeric($stock) || (int) $stock < 0) {
            $erreurs[] = 'La quantité en stock doit être un entier positif ou nul.';
        }

        $seuil = $body['seuil_alerte'] ?? 10;
        if (!is_numeric($seuil) || (int) $seuil < 0) {
            $erreurs[] = 'Le seuil d\'alerte doit être un entier positif ou nul.';
        }

        // Format attendu : AAAA-MM-JJ, et la date doit vraiment exister
        $datePeremption = trim($body['date_peremption'] ?? '');
        $dateObjet = DateTime::createFromFormat('Y-m-d', $datePeremption);

        if ($datePeremption === '') {
            $erreurs[] = 'La date de péremption est obligatoire.';
        } elseif (!$dateObjet || $dateObjet->format('Y-m-d') !== $datePeremption) {
            $erreurs[] = 'La date de péremption est invalide (format attendu : AAAA-MM-JJ).';
        }

        // Fournisseur facultatif, mais s'il est fourni il doit exister
        $idFournisseur = $body['id_fournisseur'] ?? null;

        if ($idFournisseur !== null && $idFournisseur !== '') {
            if (!is_numeric($idFournisseur)) {
                $erreurs[] = 'Le fournisseur est invalide.';
            } elseif (!$this->modele->fournisseurExiste((int) $idFournisseur)) {
                $erreurs[] = "Le fournisseur (id: $idFournisseur) n'existe pas.";
            }
            $idFournisseur = (int) $idFournisseur;
        } else {
            $idFournisseur = null;
        }

        if ($erreurs !== []) {
            Response::error(implode(' ', $erreurs), 400);
        }

        return [
            'nom'             => $nom,
            'description'     => trim($body['description'] ?? '') ?: null,
            'prix'            => (int) $prix,
            'quantite_stock'  => (int) $stock,
            'seuil_alerte'    => (int) $seuil,
            'date_peremption' => $datePeremption,
            'id_fournisseur'  => $idFournisseur,
        ];
    }

    /**
     * Typage strict avant envoi : MySQL renvoie tout en chaîne,
     * et System.Text.Json côté C# refuse "1500" pour un int.
     */
    private function formater(array $m): array
    {
        return [
            'id'                   => (int) $m['id'],
            'nom'                  => $m['nom'],
            'description'          => $m['description'],
            'prix'                 => (int) $m['prix'],
            'quantite_stock'       => (int) $m['quantite_stock'],
            'seuil_alerte'         => (int) $m['seuil_alerte'],
            'date_peremption'      => $m['date_peremption'],
            'id_fournisseur'       => $m['id_fournisseur'] === null ? null : (int) $m['id_fournisseur'],
            'fournisseur_nom'      => $m['fournisseur_nom'],
            'en_alerte_stock'      => (bool) $m['en_alerte_stock'],
            'en_alerte_peremption' => (bool) $m['en_alerte_peremption'],
            'est_perime'           => (bool) $m['est_perime'],
        ];
    }
}