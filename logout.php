<?php
/**
 * logout.php — Déconnexion.
 */
require_once __DIR__ . '/config/bootstrap.php';
logout();
redirect(APP_URL . '/login.php');
