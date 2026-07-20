<?php
/**
 * Accès aux tables utilisateurs et tokens.
 * Ne connaît rien du HTTP : ne fait que lire/écrire en base.
 */
class Utilisateur
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Retrouve un utilisateur par son nom. null si inexistant. */
    public function findByUsername(string $nomUtilisateur): ?array
    {
        $sql = 'SELECT id, nom_utilisateur, mot_de_passe, role
                FROM utilisateurs
                WHERE nom_utilisateur = :nom
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':nom' => $nomUtilisateur]);

        $row = $stmt->fetch();

        // fetch() renvoie false si aucune ligne : on normalise en null
        return $row === false ? null : $row;
    }

    /**
     * Crée un token pour un utilisateur et le stocke en base.
     * @return array le token et sa date d'expiration
     */
    public function createToken(int $idUtilisateur, int $dureeHeures = 8): array
    {
        $token = bin2hex(random_bytes(32));

        // L'expiration est calculée par MySQL, comme le NOW() qui la vérifie :
        // une seule horloge de référence, aucun décalage de fuseau possible.
        $sql = 'INSERT INTO tokens (id_utilisateur, token, date_expiration)
                VALUES (:id_utilisateur, :token, DATE_ADD(NOW(), INTERVAL :duree HOUR))';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_utilisateur' => $idUtilisateur,
            ':token'          => $token,
            ':duree'          => $dureeHeures,
        ]);

        // On relit la valeur réellement stockée plutôt que de la recalculer
        $stmt = $this->db->prepare('SELECT date_expiration FROM tokens WHERE token = :token');
        $stmt->execute([':token' => $token]);

        return [
            'token'      => $token,
            'expiration' => $stmt->fetch()['date_expiration'],
        ];
    }

    /**
     * Vérifie un token et renvoie l'utilisateur associé.
     * null si le token est inconnu OU expiré.
     */
    public function findUserByToken(string $token): ?array
    {
        $sql = 'SELECT u.id, u.nom_utilisateur, u.role, t.date_expiration
                FROM tokens t
                INNER JOIN utilisateurs u ON u.id = t.id_utilisateur
                WHERE t.token = :token
                  AND t.date_expiration > NOW()
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $token]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Supprime un token précis (déconnexion). */
    public function deleteToken(string $token): void
    {
        $stmt = $this->db->prepare('DELETE FROM tokens WHERE token = :token');
        $stmt->execute([':token' => $token]);
    }

    /** Nettoyage des tokens périmés. Appelé à chaque login. */
    public function purgeExpiredTokens(): void
    {
        $this->db->exec('DELETE FROM tokens WHERE date_expiration <= NOW()');
    }
}