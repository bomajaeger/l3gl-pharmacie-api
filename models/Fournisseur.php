<?php
/**
 * Accès à la table fournisseurs.
 * Uniquement du SQL : aucune validation, aucun HTTP.
 */
class Fournisseur
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Liste complète, triée par nom. */
    public function getAll(): array
    {
        $sql = 'SELECT id, nom, contact, telephone, adresse
                FROM fournisseurs
                ORDER BY nom ASC';

        return $this->db->query($sql)->fetchAll();
    }

    /** Un fournisseur par son id. null si introuvable. */
    public function getById(int $id): ?array
    {
        $sql = 'SELECT id, nom, contact, telephone, adresse
                FROM fournisseurs
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Insère un fournisseur et renvoie son id auto-généré. */
    public function create(array $donnees): int
    {
        $sql = 'INSERT INTO fournisseurs (nom, contact, telephone, adresse)
                VALUES (:nom, :contact, :telephone, :adresse)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nom'       => $donnees['nom'],
            ':contact'   => $donnees['contact']   ?? null,
            ':telephone' => $donnees['telephone'] ?? null,
            ':adresse'   => $donnees['adresse']   ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Met à jour un fournisseur existant. */
    public function update(int $id, array $donnees): void
    {
        $sql = 'UPDATE fournisseurs
                SET nom = :nom,
                    contact = :contact,
                    telephone = :telephone,
                    adresse = :adresse
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id'        => $id,
            ':nom'       => $donnees['nom'],
            ':contact'   => $donnees['contact']   ?? null,
            ':telephone' => $donnees['telephone'] ?? null,
            ':adresse'   => $donnees['adresse']   ?? null,
        ]);
    }

    /** Supprime un fournisseur. Ses médicaments passent à id_fournisseur = NULL. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM fournisseurs WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Nombre de médicaments rattachés. Sert à prévenir avant suppression. */
    public function countMedicaments(int $id): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total FROM medicaments WHERE id_fournisseur = :id'
        );
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetch()['total'];
    }
}