<?php
/**
 * Point d'entrée — redirige vers le login ou le dashboard.
 */
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect(APP_URL . '/dashboard.php');
}
redirect(APP_URL . '/login.php');
