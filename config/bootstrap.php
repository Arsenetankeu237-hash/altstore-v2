<?php
/**
 * config/bootstrap.php — Charge l'ensemble du socle applicatif.
 *
 * Inclure ce seul fichier en tête de chaque page :
 *   require_once __DIR__ . '/config/bootstrap.php';
 *
 * Il apporte : config, db, sessions, csrf, auth, permissions, helpers.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pdf.php';
