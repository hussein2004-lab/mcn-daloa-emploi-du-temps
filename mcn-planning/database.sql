-- ============================================================================
--  MCN DALOA — EMPLOI DU TEMPS
--  Script de creation de la base de donnees
-- ----------------------------------------------------------------------------
--  A QUOI SERT CE FICHIER ?
--    Sur votre ordinateur (XAMPP), il cree la base "mcn_daloa" :
--      phpMyAdmin (http://localhost/phpmyadmin) -> onglet "Importer" -> ce fichier.
--    La table "notes" est de toute facon creee AUTOMATIQUEMENT par l'application
--    au premier lancement (voir db() dans config.php) : chez un hebergeur, il
--    suffit de creer une base vide et de renseigner config.local.php.
--  Le script peut etre relance sans risque (il ne supprime AUCUNE donnee).
-- ============================================================================

-- 1) Creer la base si elle n'existe pas, en UTF-8 complet (accents + emojis).
CREATE DATABASE IF NOT EXISTS `mcn_daloa`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- 2) Travailler dans cette base.
USE `mcn_daloa`;

-- 3) Creer la table des notes (l'unite de base du planning).
--    Chaque ligne = une note affichee dans le calendrier.
CREATE TABLE IF NOT EXISTS `notes` (
  `id`          INT UNSIGNED NOT NULL,                 -- identifiant de la note (genere par la page)
  `type`        VARCHAR(20)  NOT NULL DEFAULT 'commande',
                                                       -- 'commande' | 'cheque' | 'rendezvous' | 'autre'
  `titre`       VARCHAR(255) NOT NULL,                 -- 1re ligne de la note
  `contenu`     TEXT         NULL,                     -- reste du texte (optionnel)
  `date`        DATE         NOT NULL,                 -- jour de la note (AAAA-MM-JJ)
  `heure_debut` VARCHAR(5)   NULL,                     -- 'HH:MM' ou vide
  `heure_fin`   VARCHAR(5)   NULL,                     -- 'HH:MM' ou vide
  `montant`     DECIMAL(14,2) NULL,                    -- montant en FCFA (optionnel)
  `rappel`      TINYINT(1)   NOT NULL DEFAULT 0,       -- 1 = note a reporter automatiquement
  `done`        TINYINT(1)   NOT NULL DEFAULT 0,       -- 1 = note terminee
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),                             -- recherche rapide par jour/mois
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
