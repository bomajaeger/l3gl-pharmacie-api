# Application de Gestion de Pharmacie

Projet d'examen — Licence 3 Génie Logiciel
Auteur : **[MOI]**

Application desktop de gestion d'une pharmacie, construite sur une architecture
3-tiers : un client WPF en C# communique en HTTP avec une API REST écrite en PHP,
qui est seule à accéder à la base MySQL.

---

## Sommaire

- [Architecture](#architecture)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Utilisation](#utilisation)
- [Structure du projet](#structure-du-projet)
- [Documentation de l'API](#documentation-de-lapi)
- [Modèle de données](#modèle-de-données)
- [Règles métier](#règles-métier)
- [Choix techniques](#choix-techniques)
- [Fonctionnalités](#fonctionnalités)
- [Dépôts](#dépôts)

---

## Architecture

```
┌─────────────────────────────────────┐
│   Client WPF (C# / .NET 10)         │
│   Architecture MVVM                 │
└─────────────────────────────────────┘
                 ⇅
       HTTP : GET / POST / PUT / DELETE
       Corps et réponses en JSON
       Authentification : Bearer token
                 ⇅
┌─────────────────────────────────────┐
│   API REST (PHP 8, PDO)             │
│   Point d'entrée unique : index.php │
└─────────────────────────────────────┘
                 ⇅
       SQL via requêtes préparées
                 ⇅
┌─────────────────────────────────────┐
│   Base de données MySQL             │
│   pharmacie_db                      │
└─────────────────────────────────────┘
```

Le client WPF n'accède **jamais** directement à la base de données. Toute
opération de lecture ou d'écriture transite par l'API.

---

## Prérequis

| Composant | Version | Rôle |
|---|---|---|
| XAMPP | 8.x | Apache + MySQL + PHP |
| .NET SDK | 10.0 | Compilation et exécution du client WPF |
| Visual Studio | 2022+ | Charge de travail « Développement pour le Bureau .NET » |
| Windows | 10 / 11 | WPF ne fonctionne que sous Windows |

Le module Apache `mod_rewrite` doit être activé, et la directive
`AllowOverride All` positionnée sur le répertoire `htdocs` — sans quoi le
fichier `.htaccess` de l'API est ignoré et le routage ne fonctionne pas.

---

## Installation

### 1. Base de données

Démarrer Apache et MySQL depuis le panneau de contrôle XAMPP, puis ouvrir
phpMyAdmin (`http://localhost/phpmyadmin`).

Dans l'onglet **SQL**, exécuter le contenu de `database/schema.sql`. Le script
crée la base `pharmacie_db`, ses cinq tables, et insère un jeu de données de
démonstration (4 fournisseurs, 10 médicaments, 6 ventes).

> Le script commence par `DROP DATABASE IF EXISTS` : il est relançable à
> volonté pendant le développement, mais efface les données existantes.

### 2. Compte administrateur

Le mot de passe est stocké haché avec `password_hash()`. Le hash dépendant d'un
sel aléatoire, il doit être généré localement.

Créer un fichier temporaire `apiPharma/generer_hash.php` :

```php
<?php
echo password_hash('admin123', PASSWORD_DEFAULT);
```

Ouvrir `http://localhost/apiPharma/generer_hash.php`, copier la chaîne obtenue,
puis dans phpMyAdmin :

```sql
UPDATE utilisateurs
SET mot_de_passe = 'LE_HASH_COPIE'
WHERE nom_utilisateur = 'admin';
```

Supprimer ensuite `generer_hash.php`.

### 3. API

Copier le dossier `api` dans `C:\xampp\htdocs\` en le renommant `apiPharma`.

Vérification : `http://localhost/apiPharma/ping` doit renvoyer un JSON de la
forme `{"success":true,...}`. Si Apache retourne une page 404, `mod_rewrite`
n'est pas actif.

Si la configuration MySQL diffère des valeurs par défaut de XAMPP, ajuster les
constantes en tête de `api/config/Database.php`.

### 4. Client WPF

Ouvrir `GestionPharmacie.sln` dans Visual Studio, puis **Régénérer la solution**.

L'URL de l'API est définie dans `PharmacieApp/Services/ApiService.cs` :

```csharp
private const string BaseUrl = "http://localhost/apiPharma/";
```

---

## Utilisation

Lancer l'application (F5). L'écran de connexion s'affiche.

**Identifiants par défaut** : `admin` / `admin123`

L'application s'ouvre ensuite sur trois onglets :

- **Médicaments** — liste, recherche, CRUD complet, alertes visuelles
- **Fournisseurs** — CRUD complet
- **Ventes** — enregistrement d'une vente, historique, chiffre d'affaires

Le token de session expire au bout de 8 heures. Passé ce délai, l'application
détecte le rejet de l'API et renvoie automatiquement vers l'écran de connexion.

---

## Structure du projet

### API PHP

```
api/
├── config/
│   └── Database.php            Connexion PDO (singleton)
├── models/
│   ├── Utilisateur.php         Comptes et tokens
│   ├── Fournisseur.php
│   ├── Medicament.php          Inclut le calcul SQL des alertes
│   └── Vente.php               Transaction de vente
├── controllers/
│   ├── AuthController.php      /login, /logout, /me
│   ├── FournisseurController.php
│   ├── MedicamentController.php
│   └── VenteController.php
├── middleware/
│   └── AuthMiddleware.php      Vérification du Bearer token
├── helpers/
│   ├── Response.php            Formatage JSON et codes HTTP
│   └── Request.php             Lecture du corps et du header d'auth
├── index.php                   Point d'entrée unique et routage
└── .htaccess                   Réécriture d'URL
```

### Client WPF

```
PharmacieApp/
├── Models/                     Objets de transfert (POCO)
│   ├── ApiResponse.cs          Enveloppe { success, data, message }
│   ├── Fournisseur.cs
│   ├── Medicament.cs
│   ├── Vente.cs
│   ├── Utilisateur.cs
│   └── StatistiquesVentes.cs
├── Services/
│   ├── ApiService.cs           Point d'accès unique à l'API
│   └── AuthService.cs          Conservation du token en mémoire
├── ViewModels/
│   ├── BaseViewModel.cs        INotifyPropertyChanged
│   ├── LoginViewModel.cs
│   ├── MainViewModel.cs
│   ├── FournisseurViewModel.cs
│   ├── MedicamentViewModel.cs
│   └── VenteViewModel.cs
├── Views/
│   ├── LoginWindow.xaml
│   ├── MainWindow.xaml
│   ├── FournisseursView.xaml
│   ├── MedicamentsView.xaml
│   └── VentesView.xaml
├── Commands/
│   ├── RelayCommand.cs         ICommand générique
│   └── AsyncRelayCommand.cs    Version asynchrone
├── Helpers/
│   ├── MySqlDateTimeConverter.cs
│   └── FcfaConverter.cs
├── Styles/
│   └── Styles.xaml             Palette et styles partagés
└── App.xaml
```

---

## Documentation de l'API

Base : `http://localhost/apiPharma/`

Toutes les routes sauf `/login` exigent l'en-tête
`Authorization: Bearer {token}`.

### Authentification

| Méthode | Route | Description |
|---|---|---|
| POST | `/login` | Authentifie et retourne un token (validité 8 h) |
| POST | `/logout` | Invalide le token courant |
| GET | `/me` | Retourne l'utilisateur associé au token |

### Fournisseurs

| Méthode | Route | Description |
|---|---|---|
| GET | `/fournisseurs` | Liste complète |
| GET | `/fournisseurs/{id}` | Détail |
| POST | `/fournisseurs` | Création |
| PUT | `/fournisseurs/{id}` | Modification |
| DELETE | `/fournisseurs/{id}` | Suppression |

### Médicaments

| Méthode | Route | Description |
|---|---|---|
| GET | `/medicaments` | Liste avec indicateurs d'alerte |
| GET | `/medicaments/{id}` | Détail |
| GET | `/medicaments/search?q=` | Recherche sur le nom et la description |
| POST | `/medicaments` | Création |
| PUT | `/medicaments/{id}` | Modification |
| DELETE | `/medicaments/{id}` | Suppression (409 si des ventes existent) |

### Ventes

| Méthode | Route | Description |
|---|---|---|
| GET | `/ventes` | Historique, du plus récent au plus ancien |
| GET | `/ventes/{id}` | Détail |
| GET | `/ventes/stats` | Nombre de ventes, chiffre d'affaires, unités |
| POST | `/ventes` | Enregistrement (décrémente le stock) |

Les ventes ne peuvent être ni modifiées ni supprimées : toute autre méthode
retourne un 405.

### Format des réponses

Succès :

```json
{ "success": true, "data": { } }
```

Erreur :

```json
{ "success": false, "message": "Description de l'erreur" }
```

Codes utilisés : 200, 201, 400, 401, 404, 405, 409, 500.

---

## Modèle de données

```
fournisseurs (1) ──── (N) medicaments (1) ──── (N) ventes

utilisateurs (1) ──── (N) tokens
```

| Table | Rôle |
|---|---|
| `utilisateurs` | Comptes, mot de passe haché |
| `tokens` | Sessions actives, avec date d'expiration |
| `fournisseurs` | Laboratoires et distributeurs |
| `medicaments` | Produits, stock, seuil d'alerte, péremption |
| `ventes` | Historique, prix figé au moment de la vente |

Contraintes d'intégrité :

- `medicaments.id_fournisseur` → `ON DELETE SET NULL`
  Supprimer un fournisseur ne supprime pas ses médicaments.
- `ventes.id_medicament` → `ON DELETE RESTRICT`
  Un médicament déjà vendu ne peut pas être supprimé : l'historique commercial
  doit être préservé.
- `tokens.id_utilisateur` → `ON DELETE CASCADE`
  Supprimer un compte supprime ses sessions.

---

## Règles métier

**Vente et cohérence du stock.**
Avant insertion, l'API vérifie que la quantité demandée est disponible. La
lecture du stock, l'insertion de la vente et la décrémentation se déroulent dans
une transaction unique. La ligne du médicament est verrouillée pendant
l'opération (`SELECT ... FOR UPDATE`), ce qui empêche deux ventes simultanées de
lire le même stock et de le rendre négatif. En cas d'échec, un `ROLLBACK` ramène
la base à son état initial.

**Prix déterminé par le serveur.**
Le client n'envoie que l'identifiant du médicament et la quantité. Le prix
unitaire est lu en base et le total calculé côté API. Le prix est ensuite figé
dans la ligne de vente : une évolution ultérieure du tarif ne fausse pas
l'historique.

**Alerte de stock bas.**
Un médicament est en alerte lorsque `quantite_stock <= seuil_alerte`. La
comparaison est effectuée en SQL et transmise au client sous forme de booléen.

**Alerte de péremption.**
Un médicament est signalé lorsque sa date de péremption est dépassée ou tombe
dans les 30 jours. Même principe : calcul en SQL, transmission en booléen.

Ces deux règles ne sont jamais recalculées côté client. Les modifier ne demande
de changer qu'une ligne de SQL.

---

## Choix techniques

**Montants en nombres entiers.**
L'application travaille en francs CFA, monnaie sans subdivision décimale en
usage courant. Les prix sont donc stockés en `INT UNSIGNED` et manipulés en
`int`, ce qui écarte au passage les erreurs d'arrondi propres aux flottants.

**Requêtes préparées natives.**
PDO est configuré avec `ATTR_EMULATE_PREPARES = false`. MySQL reçoit la
structure de la requête et les valeurs séparément, ce qui rend une injection SQL
structurellement impossible.

**Point d'entrée unique.**
Le `.htaccess` redirige toute requête vers `index.php`, qui analyse l'URL et
délègue au contrôleur approprié. Le fichier réinjecte également l'en-tête
`Authorization`, qu'Apache supprime par défaut lorsque PHP tourne en CGI.

**Séparation stricte des responsabilités côté API.**
Les modèles ne contiennent que du SQL, les contrôleurs la validation et le
formatage, le middleware l'authentification, les helpers les entrées-sorties
HTTP.

**MVVM côté client.**
Les fichiers `.xaml.cs` ne contiennent que l'initialisation du composant,
l'affectation du `DataContext` et le déclenchement du chargement initial. Toute
la logique réside dans les ViewModels, exposée aux vues sous forme de
propriétés liées et de commandes `ICommand`.

**Client HTTP unique.**
`ApiService` utilise une instance `static readonly` de `HttpClient`. Instancier
un client par appel épuiserait les sockets disponibles, ceux-ci restant
plusieurs minutes en état `TIME_WAIT` après fermeture.

**Commandes asynchrones.**
`AsyncRelayCommand` permet d'attendre les appels réseau sans bloquer le thread
d'interface. Le bouton associé se désactive pendant l'opération, ce qui
prévient les doubles soumissions.

---

## Fonctionnalités

Exigences du cahier des charges :

- [x] Ajout de données via l'interface WPF, en passant par l'API
- [x] Modification de données
- [x] Suppression de données
- [x] Recherche de données
- [x] Affichage de la liste des données
- [x] Communication WPF ↔ PHP en HTTP (GET, POST, PUT, DELETE)
- [x] Communication PHP ↔ MySQL en SQL

Fonctionnalités additionnelles :

- [x] Authentification par token, avec expiration et déconnexion
- [x] Alerte de stock bas, calculée côté serveur
- [x] Alerte de péremption, calculée côté serveur
- [x] Architecture MVVM côté client
- [x] API structurée en modèles, contrôleurs, middleware et helpers
- [x] Transaction et verrou sur l'enregistrement d'une vente
- [x] Feuille de styles centralisée
- [x] Statistiques de ventes

---

## Dépôts

| Composant | Adresse |
|---|---|
| API PHP | [https://github.com/bomajaeger/l3gl-pharmacie-api.git] |
| Client WPF | [https://github.com/bomajaeger/l3gl-pharmacie-clientWpf.git] |

---

## Limites connues

- Le mot de passe de connexion à MySQL figure en clair dans `Database.php`.
  Acceptable dans un contexte de développement local avec la configuration
  XAMPP par défaut, à externaliser dans un fichier de configuration hors dépôt
  pour un déploiement réel.
- Les échanges ne sont pas chiffrés (HTTP et non HTTPS).
- La recherche repose sur `LIKE`, adapté au volume de données du projet. Un
  index `FULLTEXT` serait préférable sur un catalogue important.
- Un seul rôle d'utilisateur est géré. La colonne `role` est présente en base et
  permettrait d'introduire une gestion de droits.
