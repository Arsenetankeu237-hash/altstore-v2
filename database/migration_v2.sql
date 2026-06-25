-- ============================================================
--  ALT STORE ERP v2 — Migration : nouvelles tables (modules)
--  --------------------------------------------------------
--  Idempotente : peut être ré-exécutée sans erreur.
--  Crée : entreprises, proformas, proforma_lignes,
--         bon_commandes, bon_commande_lignes, pipeline_operations
-- ============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
--  TABLE 15 — entreprises (infos société, en-tête PDF)
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
--  TABLE 17 — proforma_lignes
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
--  TABLE 19 — bon_commande_lignes
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
--  Contraintes étrangères (seulement si elles n'existent pas)
-- ============================================================

-- Procédure pour ajouter une FK de façon idempotente
DROP PROCEDURE IF EXISTS `add_fk_if_missing`;
DELIMITER $$
CREATE PROCEDURE `add_fk_if_missing`(IN p_table VARCHAR(64), IN p_fk_name VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND CONSTRAINT_NAME = p_fk_name
    ) THEN
        SET @s = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_fk_name, '` ', p_sql);
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL add_fk_if_missing('entreprises',         'fk_ent_boutique',     'FOREIGN KEY (boutique_id) REFERENCES boutiques(id) ON DELETE CASCADE');
CALL add_fk_if_missing('proformas',           'fk_pf_boutique',      'FOREIGN KEY (boutique_id) REFERENCES boutiques(id) ON DELETE CASCADE');
CALL add_fk_if_missing('proformas',           'fk_pf_client',        'FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL');
CALL add_fk_if_missing('proforma_lignes',     'fk_pl_proforma',      'FOREIGN KEY (proforma_id) REFERENCES proformas(id) ON DELETE CASCADE');
CALL add_fk_if_missing('proforma_lignes',     'fk_pl_article',       'FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL');
CALL add_fk_if_missing('bon_commandes',       'fk_bc_boutique',      'FOREIGN KEY (boutique_id) REFERENCES boutiques(id) ON DELETE CASCADE');
CALL add_fk_if_missing('bon_commandes',       'fk_bc_fournisseur',   'FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL');
CALL add_fk_if_missing('bon_commande_lignes', 'fk_bcl_bc',           'FOREIGN KEY (bon_commande_id) REFERENCES bon_commandes(id) ON DELETE CASCADE');
CALL add_fk_if_missing('bon_commande_lignes', 'fk_bcl_article',      'FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL');
CALL add_fk_if_missing('pipeline_operations', 'fk_po_boutique',      'FOREIGN KEY (boutique_id) REFERENCES boutiques(id) ON DELETE CASCADE');
CALL add_fk_if_missing('pipeline_operations', 'fk_po_utilisateur',   'FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE');

DROP PROCEDURE IF EXISTS `add_fk_if_missing`;
SET FOREIGN_KEY_CHECKS = 1;
