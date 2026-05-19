CREATE DATABASE IF NOT EXISTS SmartGest;
USE SmartGest;

CREATE TABLE produits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  categorie VARCHAR(50),
  prix_achat DECIMAL(10,2),
  prix_vente DECIMAL(10,2),
  stock INT DEFAULT 0,
  seuil_alerte INT DEFAULT 0
);

CREATE TABLE mouvements_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT,
  nom_produit VARCHAR(100) NOT NULL,
  type ENUM('entrée', 'sortie') NOT NULL,
  quantite INT NOT NULL,
  date DATETIME DEFAULT CURRENT_TIMESTAMP,
  commentaire TEXT,
  FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
);

CREATE TABLE caisse (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('revenu', 'dépense') NOT NULL,
  montant DECIMAL(10,2) NOT NULL,
  description TEXT,
  date DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE journal_comptable (
  id INT AUTO_INCREMENT PRIMARY KEY,
  libelle VARCHAR(255),
  compte_debit VARCHAR(20),
  compte_credit VARCHAR(20),
  montant DECIMAL(10,2),
  date DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100),
  poste VARCHAR(50),
  salaire_base DECIMAL(10,2),
  date_embauche DATE
);

CREATE TABLE fiches_paie (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employe_id INT,
  mois VARCHAR(20),
  annee INT,
  salaire_brut DECIMAL(10,2),
  retenues DECIMAL(10,2),
  salaire_net DECIMAL(10,2),
  date_paie DATE,
  FOREIGN KEY (employe_id) REFERENCES employes(id) ON DELETE CASCADE
);

CREATE TABLE utilisateurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(50),
  email VARCHAR(100) UNIQUE,
  mot_de_passe VARCHAR(255),
  role ENUM('admin', 'comptable', 'vendeur') DEFAULT 'admin'
);





