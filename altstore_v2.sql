-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 25 juin 2026 à 18:07
-- Version du serveur : 5.6.17
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `altstore_v2`
--

-- --------------------------------------------------------

--
-- Structure de la table `articles`
--

DROP TABLE IF EXISTS `articles`;
CREATE TABLE IF NOT EXISTS `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `categorie_id` int(11) DEFAULT NULL,
  `nom_article` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_barre` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marque` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fournisseur_id` int(11) DEFAULT NULL,
  `quantite_stock` int(11) NOT NULL DEFAULT '0',
  `stock_min` int(11) NOT NULL DEFAULT '10',
  `stock_max` int(11) DEFAULT NULL,
  `prix_achat` decimal(15,2) NOT NULL DEFAULT '0.00',
  `prix_vente` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tva` decimal(5,2) NOT NULL DEFAULT '18.00',
  `unite_mesure` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unite',
  `couleur` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'active | low_stock | out_of_stock',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ref_boutique` (`boutique_id`,`reference`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `articles`
--

INSERT INTO `articles` (`id`, `boutique_id`, `categorie_id`, `nom_article`, `reference`, `code_barre`, `marque`, `fournisseur_id`, `quantite_stock`, `stock_min`, `stock_max`, `prix_achat`, `prix_vente`, `tva`, `unite_mesure`, `couleur`, `description`, `image_url`, `statut`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'fds', 'trre', 'sff', 'sp', NULL, 29, 10, NULL, 10000.00, 230000.00, 0.00, 'unite', NULL, '', NULL, 'active', '2026-06-24 21:50:14', '2026-06-24 23:12:52');

-- --------------------------------------------------------

--
-- Structure de la table `bon_commandes`
--

DROP TABLE IF EXISTS `bon_commandes`;
CREATE TABLE IF NOT EXISTS `bon_commandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'BC-XXXX',
  `fournisseur_id` int(11) DEFAULT NULL,
  `fournisseur_libre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Fournisseur libre',
  `sous_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_tva` decimal(15,2) NOT NULL DEFAULT '0.00',
  `remise_montant` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_ttc` decimal(15,2) NOT NULL DEFAULT '0.00',
  `statut` enum('brouillon','envoye','confirme','recu','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `date_commande` date NOT NULL,
  `date_livraison_prevue` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_numero_boutique` (`boutique_id`,`numero`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date` (`date_commande`),
  KEY `fk_bc_fournisseur` (`fournisseur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `bon_commande_lignes`
--

DROP TABLE IF EXISTS `bon_commande_lignes`;
CREATE TABLE IF NOT EXISTS `bon_commande_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bon_commande_id` int(11) NOT NULL,
  `article_id` int(11) DEFAULT NULL,
  `designation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` decimal(12,3) NOT NULL DEFAULT '1.000',
  `prix_unitaire` decimal(15,2) NOT NULL,
  `total_ligne` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bon_commande` (`bon_commande_id`),
  KEY `idx_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `boutiques`
--

DROP TABLE IF EXISTS `boutiques`;
CREATE TABLE IF NOT EXISTS `boutiques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proprietaire_id` int(11) NOT NULL COMMENT 'utilisateur propriétaire',
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'code court ex. ALT-001',
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `couleur` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#F9A825',
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pays` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Côte d''Ivoire',
  `telephone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `devise` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF',
  `tva_defaut` decimal(5,2) NOT NULL DEFAULT '18.00',
  `rccm` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  UNIQUE KEY `uq_proprio_slug` (`proprietaire_id`,`slug`),
  KEY `idx_proprietaire` (`proprietaire_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `boutiques`
--

INSERT INTO `boutiques` (`id`, `proprietaire_id`, `nom`, `slug`, `code`, `description`, `logo`, `couleur`, `adresse`, `ville`, `pays`, `telephone`, `email`, `devise`, `tva_defaut`, `rccm`, `statut`, `created_at`) VALUES
(1, 1, 'ALT STORE Principal', 'alt-store-principal', 'ALT-001', 'Boutique principale', NULL, '#F9A825', NULL, 'Abidjan', 'Côte d\'Ivoire', '27 22 00 00', 'principal@altstore.ci', 'XOF', 18.00, NULL, 'active', '2026-06-24 20:34:55'),
(2, 1, 'ALT STORE Annexe', 'alt-store-annexe', 'ALT-002', 'Boutique secondaire', NULL, '#6366f1', NULL, 'Abidjan', 'Côte d\'Ivoire', '27 22 00 01', 'annexe@altstore.ci', 'XOF', 18.00, NULL, 'active', '2026-06-24 20:34:55'),
(3, 1, 'AKWA Nord', 'akwa-nord-576', 'ALT-576', '', NULL, '#f9a825', NULL, 'Douala', 'Côte d\'Ivoire', '65555326', 'admin@altstore.ci', 'XOF', 18.00, NULL, 'active', '2026-06-24 21:57:01');

-- --------------------------------------------------------

--
-- Structure de la table `caisses`
--

DROP TABLE IF EXISTS `caisses`;
CREATE TABLE IF NOT EXISTS `caisses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `code_caisse` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `couleur` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#F9A825',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-cash-register',
  `solde_initial` decimal(15,2) NOT NULL DEFAULT '0.00',
  `devise` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XOF',
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'hash optionnel d''ouverture',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code_boutique` (`boutique_id`,`code_caisse`),
  KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `caisses`
--

INSERT INTO `caisses` (`id`, `boutique_id`, `code_caisse`, `nom`, `description`, `couleur`, `icone`, `solde_initial`, `devise`, `mot_de_passe`, `is_active`, `is_default`, `created_at`) VALUES
(1, 1, 'CSE-001', 'Caisse principale — ALT STORE Principal', 'Caisse par défaut de ALT STORE Principal', '#F9A825', 'fa-cash-register', 0.00, 'XOF', NULL, 1, 1, '2026-06-24 20:34:55'),
(2, 2, 'CSE-002', 'Caisse principale — ALT STORE Annexe', 'Caisse par défaut de ALT STORE Annexe', '#F9A825', 'fa-cash-register', 0.00, 'XOF', NULL, 1, 1, '2026-06-24 20:34:55'),
(3, 3, 'CSE-ALT-576', 'Caisse principale — AKWA Nord', 'Caisse par défaut de AKWA Nord', '#f9a825', 'fa-cash-register', 0.00, 'XOF', NULL, 1, 1, '2026-06-24 21:57:01');

-- --------------------------------------------------------

--
-- Structure de la table `caisse_mouvements`
--

DROP TABLE IF EXISTS `caisse_mouvements`;
CREATE TABLE IF NOT EXISTS `caisse_mouvements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caisse_id` int(11) NOT NULL,
  `boutique_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `type` enum('encaissement','decaissement','transfert_in','transfert_out','ajustement') COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `mode_paiement` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'cash | mobile | carte | virement',
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ex. n° de vente associée',
  `caisse_liee_id` int(11) DEFAULT NULL COMMENT 'pour les transferts inter-caisses',
  `utilisateur_id` int(11) NOT NULL,
  `motif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caisse` (`caisse_id`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `caisse_mouvements`
--

INSERT INTO `caisse_mouvements` (`id`, `caisse_id`, `boutique_id`, `session_id`, `type`, `montant`, `mode_paiement`, `reference`, `caisse_liee_id`, `utilisateur_id`, `motif`, `created_at`) VALUES
(1, 1, 1, NULL, 'encaissement', 230000.00, 'cash', 'CMD-2606-0001', NULL, 1, 'Vente', '2026-06-24 23:12:52');

-- --------------------------------------------------------

--
-- Structure de la table `caisse_sessions`
--

DROP TABLE IF EXISTS `caisse_sessions`;
CREATE TABLE IF NOT EXISTS `caisse_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caisse_id` int(11) NOT NULL,
  `boutique_id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL COMMENT 'qui a ouvert',
  `fonds_ouverture` decimal(15,2) NOT NULL DEFAULT '0.00',
  `fonds_cloture` decimal(15,2) DEFAULT NULL,
  `opened_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  `statut` enum('ouverte','cloturee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_caisse` (`caisse_id`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_boutique` (`boutique_id`,`nom`),
  KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entreprise` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pays` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Côte d''Ivoire',
  `type_client` enum('particulier','entreprise','vip') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'particulier',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `solde_credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `limite_credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `entreprises`
--

DROP TABLE IF EXISTS `entreprises`;
CREATE TABLE IF NOT EXISTS `entreprises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_entreprise` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sigle` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secteur_activite` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registre_commerce` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` date DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_boutique_ent` (`boutique_id`),
  KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `entreprises`
--

INSERT INTO `entreprises` (`id`, `boutique_id`, `logo`, `nom_entreprise`, `sigle`, `secteur_activite`, `nif`, `registre_commerce`, `date_creation`, `adresse`, `ville`, `telephone`, `email`, `created_at`, `updated_at`) VALUES
(1, 3, 'uploads/logos/logo_3_1782410084.png', 'AKWA Nord', '', '', '', '', NULL, '', 'Douala', '65555326', 'admin@altstore.ci', '2026-06-25 18:54:44', '2026-06-25 18:54:44');

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

DROP TABLE IF EXISTS `factures`;
CREATE TABLE IF NOT EXISTS `factures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `vente_id` int(11) DEFAULT NULL,
  `date_facture` date NOT NULL,
  `montant_ht` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_tva` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_remise` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_ttc` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_paye` decimal(15,2) NOT NULL DEFAULT '0.00',
  `statut` enum('brouillon','validee','impayee','partielle','payee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'validee',
  `echeance` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_numero_boutique` (`boutique_id`,`numero`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_date` (`date_facture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

DROP TABLE IF EXISTS `fournisseurs`;
CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entreprise` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pays` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Côte d''Ivoire',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `solde_du` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id`, `boutique_id`, `nom`, `entreprise`, `email`, `telephone`, `adresse`, `ville`, `pays`, `statut`, `solde_du`, `created_at`) VALUES
(1, 1, 'Junior', 'JNR', 'admin@altstore.ci', '65555326', '', 'Abidjan', 'Côte d\'Ivoire', 'actif', 0.00, '2026-06-24 21:47:34');

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_stock`
--

DROP TABLE IF EXISTS `mouvements_stock`;
CREATE TABLE IF NOT EXISTS `mouvements_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `type` enum('entree','sortie','ajustement','inventaire') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` int(11) NOT NULL,
  `quantite_avant` int(11) DEFAULT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'vente / achat / inventaire',
  `utilisateur_id` int(11) NOT NULL,
  `motif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_article` (`article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `mouvements_stock`
--

INSERT INTO `mouvements_stock` (`id`, `boutique_id`, `article_id`, `type`, `quantite`, `quantite_avant`, `reference`, `utilisateur_id`, `motif`, `created_at`) VALUES
(1, 1, 1, 'entree', 30, 0, 'Création', 1, 'Stock initial', '2026-06-24 21:50:14'),
(2, 1, 1, 'sortie', 1, 30, 'CMD-2606-0001', 1, 'Vente CMD-2606-0001', '2026-06-24 23:12:52');

-- --------------------------------------------------------

--
-- Structure de la table `pipeline_operations`
--

DROP TABLE IF EXISTS `pipeline_operations`;
CREATE TABLE IF NOT EXISTS `pipeline_operations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `type_document` enum('proforma','bon_commande','vente','facture') COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `document_numero` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(15,2) NOT NULL DEFAULT '0.00',
  `statut` enum('en_attente','en_cours','termine') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `priorite` enum('basse','normale','haute') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normale',
  `date_echeance` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `utilisateur_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_type` (`type_document`),
  KEY `fk_po_utilisateur` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `proformas`
--

DROP TABLE IF EXISTS `proformas`;
CREATE TABLE IF NOT EXISTS `proformas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PF-XXXX',
  `client_id` int(11) DEFAULT NULL,
  `client_libre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Client libre',
  `sous_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_tva` decimal(15,2) NOT NULL DEFAULT '0.00',
  `remise_montant` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_ttc` decimal(15,2) NOT NULL DEFAULT '0.00',
  `statut` enum('brouillon','envoye','accepte','refuse','expire') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `validite_jours` int(11) NOT NULL DEFAULT '30',
  `date_creation` date NOT NULL,
  `date_validite` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_numero_boutique` (`boutique_id`,`numero`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date` (`date_creation`),
  KEY `fk_pf_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `proforma_lignes`
--

DROP TABLE IF EXISTS `proforma_lignes`;
CREATE TABLE IF NOT EXISTS `proforma_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proforma_id` int(11) NOT NULL,
  `article_id` int(11) DEFAULT NULL,
  `designation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` decimal(12,3) NOT NULL DEFAULT '1.000',
  `prix_unitaire` decimal(15,2) NOT NULL,
  `total_ligne` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proforma` (`proforma_id`),
  KEY `idx_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_compte` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'particulier' COMMENT 'entreprise | particulier',
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_verification` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verifie` tinyint(1) NOT NULL DEFAULT '0',
  `nom_entreprise` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rccm_niu` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secteur_activite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profession` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cgu_acceptees` tinyint(1) NOT NULL DEFAULT '0',
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_inscription` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derniere_connexion` datetime DEFAULT NULL,
  `statut_compte` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif' COMMENT 'actif | inactif | suspendu',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_statut` (`statut_compte`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `type_compte`, `prenom`, `nom`, `email`, `telephone`, `mot_de_passe`, `code_verification`, `email_verifie`, `nom_entreprise`, `rccm_niu`, `secteur_activite`, `profession`, `avatar`, `cgu_acceptees`, `date_inscription`, `ip_inscription`, `derniere_connexion`, `statut_compte`) VALUES
(1, 'entreprise', 'TSEFO', 'STEVE', 'admin@altstore.ci', '69901529', '$2y$10$ugBPU7XcHgqGK.xA68tViOQk8thMaxrpRXuykYLypKgHJjWhJYHz6', NULL, 1, 'ALT STORE', NULL, 'Technologie & Innovation', NULL, NULL, 1, '2026-06-24 20:34:55', NULL, '2026-06-25 18:07:50', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs_boutique`
--

DROP TABLE IF EXISTS `utilisateurs_boutique`;
CREATE TABLE IF NOT EXISTS `utilisateurs_boutique` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) NOT NULL,
  `boutique_id` int(11) NOT NULL,
  `role_boutique` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'employe' COMMENT 'directeur | comptable | commercial | caissier | gestionnaire_stock | employe',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_boutique` (`utilisateur_id`,`boutique_id`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_user` (`utilisateur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `utilisateurs_boutique`
--

INSERT INTO `utilisateurs_boutique` (`id`, `utilisateur_id`, `boutique_id`, `role_boutique`, `statut`, `date_ajout`) VALUES
(1, 1, 2, 'directeur', 'actif', '2026-06-24 21:48:59'),
(2, 1, 1, 'directeur', 'actif', '2026-06-24 21:49:21'),
(3, 1, 3, 'commercial', 'actif', '2026-06-24 21:58:00');

-- --------------------------------------------------------

--
-- Structure de la table `ventes`
--

DROP TABLE IF EXISTS `ventes`;
CREATE TABLE IF NOT EXISTS `ventes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `caisse_id` int(11) DEFAULT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CMD-XXXX',
  `client_id` int(11) DEFAULT NULL,
  `client_libre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Client libre',
  `vendeur_id` int(11) NOT NULL,
  `sous_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_tva` decimal(15,2) NOT NULL DEFAULT '0.00',
  `remise_montant` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_ttc` decimal(15,2) NOT NULL DEFAULT '0.00',
  `montant_recu` decimal(15,2) NOT NULL DEFAULT '0.00',
  `monnaie_rendue` decimal(15,2) NOT NULL DEFAULT '0.00',
  `mode_paiement` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `statut` enum('brouillon','payee','annulee','remboursee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payee',
  `date_vente` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_numero_boutique` (`boutique_id`,`numero`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_caisse` (`caisse_id`),
  KEY `idx_date` (`date_vente`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `ventes`
--

INSERT INTO `ventes` (`id`, `boutique_id`, `caisse_id`, `numero`, `client_id`, `client_libre`, `vendeur_id`, `sous_total`, `montant_tva`, `remise_montant`, `total_ttc`, `montant_recu`, `monnaie_rendue`, `mode_paiement`, `statut`, `date_vente`, `notes`) VALUES
(1, 1, 1, 'CMD-2606-0001', NULL, '', 1, 230000.00, 0.00, 0.00, 230000.00, 0.00, 0.00, 'cash', 'payee', '2026-06-24 23:12:52', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `vente_lignes`
--

DROP TABLE IF EXISTS `vente_lignes`;
CREATE TABLE IF NOT EXISTS `vente_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vente_id` int(11) NOT NULL,
  `article_id` int(11) DEFAULT NULL,
  `designation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` decimal(12,3) NOT NULL DEFAULT '1.000',
  `prix_unitaire` decimal(15,2) NOT NULL,
  `total_ligne` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vente` (`vente_id`),
  KEY `idx_article` (`article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Déchargement des données de la table `vente_lignes`
--

INSERT INTO `vente_lignes` (`id`, `vente_id`, `article_id`, `designation`, `reference`, `quantite`, `prix_unitaire`, `total_ligne`) VALUES
(1, 1, 1, 'fds', 'trre', 1.000, 230000.00, 230000.00);

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bon_commandes`
--
ALTER TABLE `bon_commandes`
  ADD CONSTRAINT `fk_bc_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bc_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `bon_commande_lignes`
--
ALTER TABLE `bon_commande_lignes`
  ADD CONSTRAINT `fk_bcl_bc` FOREIGN KEY (`bon_commande_id`) REFERENCES `bon_commandes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bcl_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `boutiques`
--
ALTER TABLE `boutiques`
  ADD CONSTRAINT `fk_boutique_proprio` FOREIGN KEY (`proprietaire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `entreprises`
--
ALTER TABLE `entreprises`
  ADD CONSTRAINT `fk_ent_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `pipeline_operations`
--
ALTER TABLE `pipeline_operations`
  ADD CONSTRAINT `fk_po_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_po_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `proformas`
--
ALTER TABLE `proformas`
  ADD CONSTRAINT `fk_pf_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pf_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `proforma_lignes`
--
ALTER TABLE `proforma_lignes`
  ADD CONSTRAINT `fk_pl_proforma` FOREIGN KEY (`proforma_id`) REFERENCES `proformas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pl_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `utilisateurs_boutique`
--
ALTER TABLE `utilisateurs_boutique`
  ADD CONSTRAINT `fk_ub_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ub_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
