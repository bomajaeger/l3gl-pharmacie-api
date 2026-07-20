<?php
/**
 * Accès à la table medicaments.
 * Les flags d'alerte sont calculés en SQL : une seule source de vérité,
 * réutilisée par toutes les routes.
 */
class Medicament
{
    private PDO $db;

    /**
     * Colonnes communes à toutes les lectures.
     * - en_alerte_stock      : le stock est retombé au niveau du seuil
     * - en_alerte_peremption : périmé, ou le sera dans les 30 jours
     * - fournisseur_nom      : évite un second appel côté client
     */
    private const SELECT_BASE = "
        SELECT m.id, m.nom, m.description, m.prix,
               m.quantite_stock, m.seuil_alerte, m.date_peremption,
               m.id_fournisseur,
               f.nom AS fournisseur_nom,
               (m.quantite_stock <= m.seuil_alerte) AS en_alerte_stock,
               (m.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS en_alerte_peremption,
               (m.date_peremption < CURDATE()) AS est_perime
        FROM medicaments m
        LEFT JOIN fournisseurs f ON f.id = m.id_fournisseur
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        return $this->db->query(self::SELECT_BASE . ' ORDER BY m.nom ASC')->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT_BASE . ' WHERE m.id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Recherche par nom ou description. */
    public function search(string $terme): array
    {
        $stmt = $this->db->prepare(
            self::SELECT_BASE .
            ' WHERE m.nom LIKE :terme_nom OR m.description LIKE :terme_desc
              ORDER BY m.nom ASC'
        );

        // Un placeholder ne peut pas être réutilisé quand
        // ATTR_EMULATE_PREPARES est à false : MySQL attend
        // autant de valeurs que de marqueurs dans la requête.
        $motif = '%' . $terme . '%';

        $stmt->execute([
            ':terme_nom'  => $motif,
            ':terme_desc' => $motif,
        ]);

        return $stmt->fetchAll();
    }

    public function create(array $d): int
    {
        $sql = 'INSERT INTO medicaments
                    (nom, description, prix, quantite_stock,
                     seuil_alerte, date_peremption, id_fournisseur)
                VALUES
                    (:nom, :description, :prix, :quantite_stock,
                     :seuil_alerte, :date_peremption, :id_fournisseur)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nom'             => $d['nom'],
            ':description'     => $d['description'],
            ':prix'            => $d['prix'],
            ':quantite_stock'  => $d['quantite_stock'],
            ':seuil_alerte'    => $d['seuil_alerte'],
            ':date_peremption' => $d['date_peremption'],
            ':id_fournisseur'  => $d['id_fournisseur'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $sql = 'UPDATE medicaments SET
                    nom = :nom,
                    description = :description,
                    prix = :prix,
                    quantite_stock = :quantite_stock,
                    seuil_alerte = :seuil_alerte,
                    date_peremption = :date_peremption,
                    id_fournisseur = :id_fournisseur
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id'              => $id,
            ':nom'             => $d['nom'],
            ':description'     => $d['description'],
            ':prix'            => $d['prix'],
            ':quantite_stock'  => $d['quantite_stock'],
            ':seuil_alerte'    => $d['seuil_alerte'],
            ':date_peremption' => $d['date_peremption'],
            ':id_fournisseur'  => $d['id_fournisseur'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM medicaments WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Nombre de ventes liées : bloque la suppression (ON DELETE RESTRICT). */
    public function countVentes(int $id): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total FROM ventes WHERE id_medicament = :id'
        );
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetch()['total'];
    }

    /** Vérifie l'existence d'un fournisseur avant de l'associer. */
    public function fournisseurExiste(int $idFournisseur): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM fournisseurs WHERE id = :id');
        $stmt->execute([':id' => $idFournisseur]);

        return $stmt->fetch() !== false;
    }
}