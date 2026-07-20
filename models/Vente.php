<?php
/**
 * Accès à la table ventes.
 * La méthode enregistrer() est le cœur du système : elle modifie
 * deux tables de façon indissociable, sous transaction.
 */
class Vente
{
    private PDO $db;

    private const SELECT_BASE = "
        SELECT v.id, v.id_medicament, v.quantite_vendue,
               v.prix_unitaire, v.prix_total, v.date_vente,
               m.nom AS medicament_nom
        FROM ventes v
        INNER JOIN medicaments m ON m.id = v.id_medicament
    ";

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Historique complet, du plus récent au plus ancien. */
    public function getAll(): array
    {
        return $this->db
            ->query(self::SELECT_BASE . ' ORDER BY v.date_vente DESC, v.id DESC')
            ->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT_BASE . ' WHERE v.id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Enregistre une vente et décrémente le stock, de façon atomique.
     *
     * Les deux opérations doivent réussir ensemble ou échouer ensemble :
     * une vente sans décrément fausserait le stock, un décrément sans
     * vente ferait disparaître de la marchandise de la comptabilité.
     *
     * @throws RuntimeException si le médicament est introuvable
     *                          ou le stock insuffisant
     * @return int l'id de la vente créée
     */
    public function enregistrer(int $idMedicament, int $quantite): int
    {
        $this->db->beginTransaction();

        try {
            // FOR UPDATE verrouille la ligne jusqu'au COMMIT.
            // Sans ce verrou, deux ventes simultanées pourraient lire
            // le même stock de 5, valider chacune une vente de 3,
            // et laisser le stock à -1.
            $stmt = $this->db->prepare(
                'SELECT id, nom, prix, quantite_stock
                 FROM medicaments
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $idMedicament]);
            $medicament = $stmt->fetch();

            if ($medicament === false) {
                throw new RuntimeException("Médicament introuvable (id: $idMedicament).");
            }

            $stock = (int) $medicament['quantite_stock'];

            if ($quantite > $stock) {
                throw new RuntimeException(
                    "Stock insuffisant pour « {$medicament['nom']} » : "
                    . "$stock unité(s) disponible(s), $quantite demandée(s)."
                );
            }

            // Le prix vient de la base, jamais du client.
            $prixUnitaire = (int) $medicament['prix'];
            $prixTotal    = $prixUnitaire * $quantite;

            $stmt = $this->db->prepare(
                'INSERT INTO ventes
                     (id_medicament, quantite_vendue, prix_unitaire, prix_total)
                 VALUES
                     (:id_medicament, :quantite, :prix_unitaire, :prix_total)'
            );
            $stmt->execute([
                ':id_medicament' => $idMedicament,
                ':quantite'      => $quantite,
                ':prix_unitaire' => $prixUnitaire,
                ':prix_total'    => $prixTotal,
            ]);

            $idVente = (int) $this->db->lastInsertId();

            // Décrément relatif (- :quantite) et non affectation d'une
            // valeur calculée en PHP : c'est MySQL qui fait le calcul
            // sur la valeur réelle au moment de l'écriture.
            $stmt = $this->db->prepare(
                'UPDATE medicaments
                 SET quantite_stock = quantite_stock - :quantite
                 WHERE id = :id'
            );
            $stmt->execute([
                ':quantite' => $quantite,
                ':id'       => $idMedicament,
            ]);

            $this->db->commit();

            return $idVente;

        } catch (Throwable $e) {
            // Annule tout : ni vente insérée, ni stock modifié.
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Quelques chiffres pour un éventuel tableau de bord. */
    public function statistiques(): array
    {
        $sql = 'SELECT
                    COUNT(*)                  AS nombre_ventes,
                    COALESCE(SUM(prix_total), 0) AS chiffre_affaires,
                    COALESCE(SUM(quantite_vendue), 0) AS unites_vendues
                FROM ventes';

        return $this->db->query($sql)->fetch();
    }
}