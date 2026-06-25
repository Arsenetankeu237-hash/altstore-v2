-- ============================================================
--  ALT STORE ERP v2 — Schéma multi-boutiques
--  --------------------------------------------------------
--  Architecture : un propriétaire possède N boutiques.
--  Chaque boutique a son propre personnel, ses rôles, ses caisses,
--  son stock, ses ventes. Les données métier sont isolées par
--  `boutique_id` (clé de tenancy).
--
--  À exécuter une fois sur une base MySQL 8 / MariaDB 10.4+.
--  Toutes les tables utilisent InnoDB + utf8mb4.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- Activer Barracuda pour les index longs utf8mb4
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_large_prefix = 1;

-- ============================================================
--  TABLE 1 — utilisateurs (comptes propriétaires ET employés)
-- ============================================================
CREATE TABLE IF NOT EXISTS `utilisateurs` (
    `id`                INT NOT NULL AUTO_INCREMENT,
    `type_compte`       VARCHAR(20) NOT NULL DEFAULT 'particulier' COMMENT 'entreprise | particulier',
    `prenom`            VARCHAR(100) NOT NULL,
    `nom`               VARCHAR(100) NOT NULL,
    `email`             VARCHAR(255) NOT NULL,
    `telephone`         VARCHAR(30)  NULL,
    `mot_de_passe`      VARCHAR(255) NOT NULL,
    `code_verification` VARCHAR(6)   NULL,
    `email_verifie`     TINYINT(1)   NOT NULL DEFAULT 0,
    `nom_entreprise`    VARCHAR(255) NULL,
    `rccm_niu`          VARCHAR(100) NULL,
    `secteur_activite`  VARCHAR(100) NULL,
    `profession`        VARCHAR(100) NULL,
    `avatar`            VARCHAR(255) NULL,
    `cgu_acceptees`     TINYINT(1)   NOT NULL DEFAULT 0,
    `date_inscription`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `ip_inscription`    VARCHAR(45)  NULL,
    `derniere_connexion` DATETIME    NULL,
    `statut_compte`     VARCHAR(20)  NOT NULL DEFAULT 'actif' COMMENT 'actif | inactif | suspendu',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    KEY `idx_statut` (`statut_compte`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 2 — boutiques  (CŒUR du multi-boutiques)
-- ============================================================
CREATE TABLE IF NOT EXISTS `boutiques` (
    `id`              INT NOT NULL AUTO_INCREMENT,
    `proprietaire_id` INT NOT NULL COMMENT 'utilisateur propriétaire',
    `nom`             VARCHAR(150) NOT NULL,
    `slug`            VARCHAR(160) NOT NULL,
    `code`            VARCHAR(30)  NOT NULL COMMENT 'code court ex. ALT-001',
    `description`     TEXT NULL,
    `logo`            VARCHAR(255) NULL,
    `couleur`         VARCHAR(20)  NOT NULL DEFAULT '#F9A825',
    `adresse`         TEXT NULL,
    `ville`           VARCHAR(100) NULL,
    `pays`            VARCHAR(100) NOT NULL DEFAULT "Côte d'Ivoire",
    `telephone`       VARCHAR(30)  NULL,
    `email`           VARCHAR(150) NULL,
    `devise`          VARCHAR(10)  NOT NULL DEFAULT 'XOF',
    `tva_defaut`      DECIMAL(5,2) NOT NULL DEFAULT 18.00,
    `rccm`            VARCHAR(100) NULL,
    `statut`          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`),
    UNIQUE KEY `uq_proprio_slug` (`proprietaire_id`, `slug`),
    KEY `idx_proprietaire` (`proprietaire_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 3 — utilisateurs_boutique  (PERSONNEL + RÔLE par boutique)
--  Un même utilisateur peut être caissier dans la boutique A
--  et directeur dans la boutique B.
-- ============================================================
CREATE TABLE IF NOT EXISTS `utilisateurs_boutique` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `utilisateur_id` INT NOT NULL,
    `boutique_id`   INT NOT NULL,
    `role_boutique` VARCHAR(40) NOT NULL DEFAULT 'employe'
                    COMMENT 'directeur | comptable | commercial | caissier | gestionnaire_stock | employe',
    `statut`        ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    `date_ajout`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_boutique` (`utilisateur_id`, `boutique_id`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_user` (`utilisateur_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 4 — caisses (clé de tenancy = boutique_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS `caisses` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `boutique_id`   INT NOT NULL,
    `code_caisse`   VARCHAR(30)  NOT NULL,
    `nom`           VARCHAR(100) NOT NULL,
    `description`   VARCHAR(255) NULL,
    `couleur`       VARCHAR(20)  NOT NULL DEFAULT '#F9A825',
    `icone`         VARCHAR(50)  NOT NULL DEFAULT 'fa-cash-register',
    `solde_initial` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `devise`        VARCHAR(10)  NOT NULL DEFAULT 'XOF',
    `mot_de_passe`  VARCHAR(255) NULL COMMENT 'hash optionnel d''ouverture',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `is_default`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code_boutique` (`boutique_id`, `code_caisse`),
    KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 5 — caisse_sessions (ouverture / clôture journalière)
-- ============================================================
CREATE TABLE IF NOT EXISTS `caisse_sessions` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `caisse_id`     INT NOT NULL,
    `boutique_id`   INT NOT NULL,
    `utilisateur_id` INT NOT NULL COMMENT 'qui a ouvert',
    `fonds_ouverture` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `fonds_cloture` DECIMAL(15,2) NULL,
    `opened_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `closed_at`     DATETIME NULL,
    `statut`        ENUM('ouverte','cloturee') NOT NULL DEFAULT 'ouverte',
    `note`          TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_caisse` (`caisse_id`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_statut` (`statut`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 6 — caisse_mouvements (encaissements / décaissements)
-- ============================================================
CREATE TABLE IF NOT EXISTS `caisse_mouvements` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `caisse_id`     INT NOT NULL,
    `boutique_id`   INT NOT NULL,
    `session_id`    INT NULL,
    `type`          ENUM('encaissement','decaissement','transfert_in','transfert_out','ajustement') NOT NULL,
    `montant`       DECIMAL(15,2) NOT NULL,
    `mode_paiement` VARCHAR(30) NULL COMMENT 'cash | mobile | carte | virement',
    `reference`     VARCHAR(100) NULL COMMENT 'ex. n° de vente associée',
    `caisse_liee_id` INT NULL COMMENT 'pour les transferts inter-caisses',
    `utilisateur_id` INT NOT NULL,
    `motif`         VARCHAR(255) NULL,
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_caisse` (`caisse_id`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_type` (`type`),
    KEY `idx_date` (`created_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 7 — categories (par boutique)
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT NOT NULL AUTO_INCREMENT,
    `boutique_id` INT NOT NULL,
    `nom`         VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cat_boutique` (`boutique_id`, `nom`),
    KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 8 — articles (stock, par boutique)
-- ============================================================
CREATE TABLE IF NOT EXISTS `articles` (
    `id`              INT NOT NULL AUTO_INCREMENT,
    `boutique_id`     INT NOT NULL,
    `categorie_id`    INT NULL,
    `nom_article`     VARCHAR(200) NOT NULL,
    `reference`       VARCHAR(60)  NOT NULL,
    `code_barre`      VARCHAR(60)  NULL,
    `marque`          VARCHAR(100) NULL,
    `fournisseur_id`  INT NULL,
    `quantite_stock`  INT NOT NULL DEFAULT 0,
    `stock_min`       INT NOT NULL DEFAULT 10,
    `stock_max`       INT NULL,
    `prix_achat`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `prix_vente`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tva`             DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
    `unite_mesure`    VARCHAR(30)   NOT NULL DEFAULT 'unite',
    `couleur`         VARCHAR(50)   NULL,
    `description`     TEXT NULL,
    `image_url`       VARCHAR(255)  NULL,
    `statut`          VARCHAR(20)   NOT NULL DEFAULT 'active' COMMENT 'active | low_stock | out_of_stock',
    `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ref_boutique` (`boutique_id`, `reference`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_categorie` (`categorie_id`),
    KEY `idx_statut` (`statut`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 9 — clients (par boutique)
-- ============================================================
CREATE TABLE IF NOT EXISTS `clients` (
    `id`           INT NOT NULL AUTO_INCREMENT,
    `boutique_id`  INT NOT NULL,
    `nom`          VARCHAR(200) NOT NULL,
    `entreprise`   VARCHAR(200) NULL,
    `email`        VARCHAR(200) NULL,
    `telephone`    VARCHAR(50)  NULL,
    `telephone2`   VARCHAR(50)  NULL,
    `adresse`      TEXT NULL,
    `ville`        VARCHAR(100) NULL,
    `pays`         VARCHAR(100) NOT NULL DEFAULT "Côte d'Ivoire",
    `type_client`  ENUM('particulier','entreprise','vip') NOT NULL DEFAULT 'particulier',
    `statut`       ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    `solde_credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `limite_credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_nom` (`nom`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 10 — fournisseurs (par boutique)
-- ============================================================
CREATE TABLE IF NOT EXISTS `fournisseurs` (
    `id`           INT NOT NULL AUTO_INCREMENT,
    `boutique_id`  INT NOT NULL,
    `nom`          VARCHAR(200) NOT NULL,
    `entreprise`   VARCHAR(200) NULL,
    `email`        VARCHAR(200) NULL,
    `telephone`    VARCHAR(50)  NULL,
    `adresse`      TEXT NULL,
    `ville`        VARCHAR(100) NULL,
    `pays`         VARCHAR(100) NOT NULL DEFAULT "Côte d'Ivoire",
    `statut`       ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    `solde_du`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 11 — ventes (POS, par boutique + caisse)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ventes` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `boutique_id`   INT NOT NULL,
    `caisse_id`     INT NULL,
    `numero`        VARCHAR(40) NOT NULL COMMENT 'CMD-XXXX',
    `client_id`     INT NULL,
    `client_libre`  VARCHAR(200) NOT NULL DEFAULT 'Client libre',
    `vendeur_id`    INT NOT NULL,
    `sous_total`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_tva`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remise_montant` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_ttc`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_recu`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monnaie_rendue` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `mode_paiement` VARCHAR(30) NOT NULL DEFAULT 'cash',
    `statut`        ENUM('brouillon','payee','annulee','remboursee') NOT NULL DEFAULT 'payee',
    `date_vente`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `notes`         TEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_numero_boutique` (`boutique_id`, `numero`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_caisse` (`caisse_id`),
    KEY `idx_date` (`date_vente`),
    KEY `idx_statut` (`statut`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 12 — vente_lignes (détail des ventes)
-- ============================================================
CREATE TABLE IF NOT EXISTS `vente_lignes` (
    `id`           INT NOT NULL AUTO_INCREMENT,
    `vente_id`     INT NOT NULL,
    `article_id`   INT NULL,
    `designation`  VARCHAR(255) NOT NULL,
    `reference`    VARCHAR(100) NULL,
    `quantite`     DECIMAL(12,3) NOT NULL DEFAULT 1,
    `prix_unitaire` DECIMAL(15,2) NOT NULL,
    `total_ligne`  DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_vente` (`vente_id`),
    KEY `idx_article` (`article_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 13 — factures (par boutique)
-- ============================================================
CREATE TABLE IF NOT EXISTS `factures` (
    `id`             INT NOT NULL AUTO_INCREMENT,
    `boutique_id`    INT NOT NULL,
    `numero`         VARCHAR(40) NOT NULL,
    `client_id`      INT NULL,
    `vente_id`       INT NULL,
    `date_facture`   DATE NOT NULL,
    `montant_ht`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_tva`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_remise` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_ttc`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_paye`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `statut`         ENUM('brouillon','validee','impayee','partielle','payee','annulee') NOT NULL DEFAULT 'validee',
    `echeance`       DATE NULL,
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_numero_boutique` (`boutique_id`, `numero`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_date` (`date_facture`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 14 — mouvements_stock (traçabilité)
-- ============================================================
CREATE TABLE IF NOT EXISTS `mouvements_stock` (
    `id`           INT NOT NULL AUTO_INCREMENT,
    `boutique_id`  INT NOT NULL,
    `article_id`   INT NOT NULL,
    `type`         ENUM('entree','sortie','ajustement','inventaire') NOT NULL,
    `quantite`     INT NOT NULL,
    `quantite_avant` INT NULL,
    `reference`    VARCHAR(100) NULL COMMENT 'vente / achat / inventaire',
    `utilisateur_id` INT NOT NULL,
    `motif`        VARCHAR(255) NULL,
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_article` (`article_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 15 — entreprises (infos société, pour en-tête PDF)
-- ============================================================
CREATE TABLE IF NOT EXISTS `entreprises` (
    `id`                INT NOT NULL AUTO_INCREMENT,
    `boutique_id`       INT NOT NULL,
    `logo`              VARCHAR(255) NULL,
    `nom_entreprise`    VARCHAR(200) NOT NULL,
    `sigle`             VARCHAR(100) NULL,
    `secteur_activite`  VARCHAR(150) NULL,
    `nif`               VARCHAR(60)  NULL,
    `registre_commerce` VARCHAR(100) NULL,
    `date_creation`     DATE NULL,
    `adresse`           TEXT NULL,
    `ville`             VARCHAR(100) NULL,
    `telephone`         VARCHAR(30)  NULL,
    `email`             VARCHAR(200) NULL,
    `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_boutique_ent` (`boutique_id`),
    KEY `idx_boutique` (`boutique_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 16 — proformas
-- ============================================================
CREATE TABLE IF NOT EXISTS `proformas` (
    `id`                INT NOT NULL AUTO_INCREMENT,
    `boutique_id`       INT NOT NULL,
    `numero`            VARCHAR(40) NOT NULL COMMENT 'PF-XXXX',
    `client_id`         INT NULL,
    `client_libre`      VARCHAR(200) NOT NULL DEFAULT 'Client libre',
    `sous_total`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_tva`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remise_montant`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_ttc`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `statut`            ENUM('brouillon','envoye','accepte','refuse','expire') NOT NULL DEFAULT 'brouillon',
    `validite_jours`    INT NOT NULL DEFAULT 30,
    `date_creation`     DATE NOT NULL,
    `date_validite`     DATE NULL,
    `notes`             TEXT NULL,
    `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_numero_boutique` (`boutique_id`, `numero`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_statut` (`statut`),
    KEY `idx_date` (`date_creation`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 17 — proforma_lignes (détail des proformas)
-- ============================================================
CREATE TABLE IF NOT EXISTS `proforma_lignes` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `proforma_id`      INT NOT NULL,
    `article_id`       INT NULL,
    `designation`      VARCHAR(255) NOT NULL,
    `reference`        VARCHAR(100) NULL,
    `quantite`         DECIMAL(12,3) NOT NULL DEFAULT 1,
    `prix_unitaire`    DECIMAL(15,2) NOT NULL,
    `total_ligne`      DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_proforma` (`proforma_id`),
    KEY `idx_article` (`article_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 18 — bon_commandes
-- ============================================================
CREATE TABLE IF NOT EXISTS `bon_commandes` (
    `id`                    INT NOT NULL AUTO_INCREMENT,
    `boutique_id`           INT NOT NULL,
    `numero`                VARCHAR(40) NOT NULL COMMENT 'BC-XXXX',
    `fournisseur_id`        INT NULL,
    `fournisseur_libre`     VARCHAR(200) NOT NULL DEFAULT 'Fournisseur libre',
    `sous_total`            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_tva`           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remise_montant`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_ttc`             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `statut`                ENUM('brouillon','envoye','confirme','recu','annule') NOT NULL DEFAULT 'brouillon',
    `date_commande`         DATE NOT NULL,
    `date_livraison_prevue` DATE NULL,
    `notes`                 TEXT NULL,
    `created_at`            DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_numero_boutique` (`boutique_id`, `numero`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_statut` (`statut`),
    KEY `idx_date` (`date_commande`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 19 — bon_commande_lignes (détail des bons de commande)
-- ============================================================
CREATE TABLE IF NOT EXISTS `bon_commande_lignes` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `bon_commande_id`  INT NOT NULL,
    `article_id`       INT NULL,
    `designation`      VARCHAR(255) NOT NULL,
    `reference`        VARCHAR(100) NULL,
    `quantite`         DECIMAL(12,3) NOT NULL DEFAULT 1,
    `prix_unitaire`    DECIMAL(15,2) NOT NULL,
    `total_ligne`      DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_bon_commande` (`bon_commande_id`),
    KEY `idx_article` (`article_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE 20 — pipeline_operations (kanban)
-- ============================================================
CREATE TABLE IF NOT EXISTS `pipeline_operations` (
    `id`              INT NOT NULL AUTO_INCREMENT,
    `boutique_id`     INT NOT NULL,
    `type_document`   ENUM('proforma','bon_commande','vente','facture') NOT NULL,
    `document_id`     INT NULL,
    `document_numero` VARCHAR(60) NULL,
    `client_nom`      VARCHAR(200) NOT NULL,
    `montant`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `statut`          ENUM('en_attente','en_cours','termine') NOT NULL DEFAULT 'en_attente',
    `priorite`        ENUM('basse','normale','haute') NOT NULL DEFAULT 'normale',
    `date_echeance`   DATE NULL,
    `notes`           TEXT NULL,
    `utilisateur_id`  INT NOT NULL,
    `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_boutique` (`boutique_id`),
    KEY `idx_statut` (`statut`),
    KEY `idx_type` (`type_document`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Contraintes étrangères (activées à la fin)
-- ============================================================
ALTER TABLE `boutiques`
    ADD CONSTRAINT `fk_boutique_proprio` FOREIGN KEY (`proprietaire_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE;

ALTER TABLE `utilisateurs_boutique`
    ADD CONSTRAINT `fk_ub_user`      FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ub_boutique`  FOREIGN KEY (`boutique_id`)    REFERENCES `boutiques`(`id`)    ON DELETE CASCADE;

ALTER TABLE `entreprises`
    ADD CONSTRAINT `fk_ent_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques`(`id`) ON DELETE CASCADE;

ALTER TABLE `proformas`
    ADD CONSTRAINT `fk_pf_boutique`  FOREIGN KEY (`boutique_id`) REFERENCES `boutiques`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pf_client`    FOREIGN KEY (`client_id`)  REFERENCES `clients`(`id`) ON DELETE SET NULL;

ALTER TABLE `proforma_lignes`
    ADD CONSTRAINT `fk_pl_proforma`  FOREIGN KEY (`proforma_id`) REFERENCES `proformas`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pl_article`   FOREIGN KEY (`article_id`)  REFERENCES `articles`(`id`) ON DELETE SET NULL;

ALTER TABLE `bon_commandes`
    ADD CONSTRAINT `fk_bc_boutique`       FOREIGN KEY (`boutique_id`)    REFERENCES `boutiques`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_bc_fournisseur`    FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs`(`id`) ON DELETE SET NULL;

ALTER TABLE `bon_commande_lignes`
    ADD CONSTRAINT `fk_bcl_bc`      FOREIGN KEY (`bon_commande_id`) REFERENCES `bon_commandes`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_bcl_article` FOREIGN KEY (`article_id`)      REFERENCES `articles`(`id`) ON DELETE SET NULL;

ALTER TABLE `pipeline_operations`
    ADD CONSTRAINT `fk_po_boutique`     FOREIGN KEY (`boutique_id`)    REFERENCES `boutiques`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_po_utilisateur`  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
