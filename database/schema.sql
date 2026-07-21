-- =====================================================================
--  Application de Gestion de Pharmacie - Schema MySQL
--  Monnaie : FCFA (XOF) -> tous les montants sont des ENTIERS
-- =====================================================================

DROP DATABASE IF EXISTS pharmacie_db;
CREATE DATABASE pharmacie_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE pharmacie_db;

-- ---------------------------------------------------------------------
--  UTILISATEURS : authentification uniquement
-- ---------------------------------------------------------------------
CREATE TABLE utilisateurs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom_utilisateur VARCHAR(50)  NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255) NOT NULL,          -- hache via password_hash()
    role            VARCHAR(20)  NOT NULL DEFAULT 'admin',
    date_creation   DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  TOKENS : sessions API (Bearer)
--  ON DELETE CASCADE : si l'utilisateur disparait, ses tokens aussi.
-- ---------------------------------------------------------------------
CREATE TABLE tokens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur  INT          NOT NULL,
    token           VARCHAR(255) NOT NULL UNIQUE,
    date_expiration DATETIME     NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  FOURNISSEURS
-- ---------------------------------------------------------------------
CREATE TABLE fournisseurs (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(100) NOT NULL,
    contact   VARCHAR(100),
    telephone VARCHAR(20),
    adresse   VARCHAR(255)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  MEDICAMENTS
--  prix : INT UNSIGNED -> FCFA, pas de centimes
--  ON DELETE SET NULL : supprimer un fournisseur ne detruit pas
--                       les medicaments, il les laisse "orphelins".
-- ---------------------------------------------------------------------
CREATE TABLE medicaments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100)  NOT NULL,
    description     TEXT,
    prix            INT UNSIGNED  NOT NULL,
    quantite_stock  INT           NOT NULL DEFAULT 0,
    seuil_alerte    INT           NOT NULL DEFAULT 10,
    date_peremption DATE          NOT NULL,
    id_fournisseur  INT,
    FOREIGN KEY (id_fournisseur) REFERENCES fournisseurs(id) ON DELETE SET NULL,
    INDEX idx_medicament_nom (nom)          -- accelere la recherche LIKE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  VENTES
--  prix_unitaire : fige le prix au moment de la vente. Si le prix du
--                  medicament change plus tard, l'historique reste juste.
--  ON DELETE RESTRICT : interdit de supprimer un medicament deja vendu.
--                       L'API doit intercepter et renvoyer un 409.
-- ---------------------------------------------------------------------
CREATE TABLE ventes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_medicament   INT          NOT NULL,
    quantite_vendue INT          NOT NULL,
    prix_unitaire   INT UNSIGNED NOT NULL,
    prix_total      INT UNSIGNED NOT NULL,
    date_vente      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_medicament) REFERENCES medicaments(id) ON DELETE RESTRICT,
    INDEX idx_date_vente (date_vente)
) ENGINE=InnoDB;

-- =====================================================================
--  JEU DE DONNEES DE TEST
-- =====================================================================

INSERT INTO fournisseurs (nom, contact, telephone, adresse) VALUES
('Laborex Senegal',    'Mamadou Diallo', '338491200', 'Km 2.5 Bd du Centenaire, Dakar'),
('SODIPHARM',          'Aissatou Ndiaye','338232145', 'Zone Industrielle, Dakar'),
('Cophase',            'Ousmane Fall',   '338675432', 'Route de Rufisque, Dakar'),
('UBIPHARM Senegal',   'Fatou Sow',      '338321099', 'Hann Mariste, Dakar');

-- Prix en FCFA. Certaines lignes sont volontairement en alerte
-- pour pouvoir tester l'affichage cote WPF.
INSERT INTO medicaments
    (nom, description, prix, quantite_stock, seuil_alerte, date_peremption, id_fournisseur)
VALUES
-- situation normale
('Paracetamol 500mg',  'Antalgique et antipyretique, boite de 20 comprimes', 1500,  150, 20, DATE_ADD(CURDATE(), INTERVAL 400 DAY), 1),
('Amoxicilline 1g',    'Antibiotique, boite de 12 comprimes',                4500,   80, 15, DATE_ADD(CURDATE(), INTERVAL 300 DAY), 1),
('Ibuprofene 400mg',   'Anti-inflammatoire, boite de 30 comprimes',          2500,  120, 25, DATE_ADD(CURDATE(), INTERVAL 500 DAY), 2),
('Serum physiologique','Solution de lavage nasal, 20 dosettes',               1200,  200, 30, DATE_ADD(CURDATE(), INTERVAL 600 DAY), 3),
('Vitamine C 1000mg',  'Complement alimentaire, 10 comprimes effervescents',  2000,   95, 20, DATE_ADD(CURDATE(), INTERVAL 350 DAY), 4),

-- STOCK BAS (quantite_stock <= seuil_alerte)
('Artemether 20mg',    'Antipaludique, boite de 24 comprimes',               8500,    6, 15, DATE_ADD(CURDATE(), INTERVAL 250 DAY), 2),
('Insuline Lantus',    'Solution injectable 100 UI/ml, stylo prerempli',    25000,    3, 10, DATE_ADD(CURDATE(), INTERVAL 180 DAY), 4),

-- PEREMPTION PROCHE (< 30 jours)
('Sirop antitussif',   'Sirop 125ml pour toux seche',                        3000,   45, 10, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 3),

-- DEJA PERIME
('Collyre antiseptique','Solution ophtalmique 10ml',                         3500,   28, 10, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  1),

-- DOUBLE ALERTE : stock bas ET peremption proche
('Metronidazole 250mg','Antiparasitaire, boite de 20 comprimes',             2800,    4, 12, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 2);

-- Quelques ventes pour alimenter l'historique.
-- prix_unitaire recopie le prix du medicament au moment de la vente.
INSERT INTO ventes (id_medicament, quantite_vendue, prix_unitaire, prix_total, date_vente) VALUES
(1,  3, 1500,  4500, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2,  1, 4500,  4500, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3,  2, 2500,  5000, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1,  5, 1500,  7500, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5,  2, 2000,  4000, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 10, 1200, 12000, NOW());

-- =====================================================================
--  UTILISATEUR ADMIN
--  Le hash ci-dessous doit etre remplace par celui genere avec
--  password_hash() (voir generer_hash.php). Mot de passe : admin123
-- =====================================================================
INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, role) VALUES
('admin', 'REMPLACER_PAR_LE_HASH', 'admin');
