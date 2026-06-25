<?php
/**
 * config/config.php — Chargement central de la configuration.
 *
 * Lit le fichier .env (jamais versionné) et expose des constantes.
 * En production, on peut remplacer le parse du .env par de vraies variables
 * d'environnement serveur (getenv).
 */
declare(strict_types=1);

if (defined('APP_LOADED')) return;
define('APP_LOADED', true);

// ----------------------------------------------------------------
//  1. Fuseau horaire & erreurs
// ----------------------------------------------------------------
date_default_timezone_set('Africa/Abidjan');

$isProd = getenv('APP_ENV') === 'production';
if ($isProd) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ----------------------------------------------------------------
//  2. Lecture du .env (clé=valeur)
// ----------------------------------------------------------------
$envPath = __DIR__ . '/../.env';
$env = [];

if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        // retirer d'éventuels guillemets
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $env[trim($k)] = $v;
    }
}

/**
 * Récupère une valeur de config : .env > variable d'environnement > défaut.
 */
function env(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false && isset($GLOBALS['env'][$key])) $v = $GLOBALS['env'][$key];
    if ($v === false) return $default;
    return $v;
}

// On rend le tableau env accessible globalement
$GLOBALS['env'] = $env;

// ----------------------------------------------------------------
//  3. Constantes applicatives
// ----------------------------------------------------------------
define('APP_NAME',    env('APP_NAME', 'ALT STORE ERP'));
define('APP_ENV',     env('APP_ENV', 'development'));
define('APP_DEBUG',   env('APP_DEBUG', 'true') === 'true');
define('APP_URL',     rtrim(env('APP_URL', 'http://localhost'), '/'));
define('APP_KEY',     env('APP_KEY', ''));
define('IS_PROD',     APP_ENV === 'production');

define('DB_HOST',     env('DB_HOST', '127.0.0.1'));
define('DB_PORT',     env('DB_PORT', '3306'));
define('DB_NAME',     env('DB_NAME', 'altstore'));
define('DB_USER',     env('DB_USER', 'root'));
define('DB_PASS',     env('DB_PASS', ''));

define('OPENROUTER_API_KEY', env('OPENROUTER_API_KEY', ''));
define('OPENROUTER_MODEL',   env('OPENROUTER_MODEL', 'deepseek/deepseek-chat'));

// Chemins
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// ----------------------------------------------------------------
//  4. Session démarrée de façon sécurisée
// ----------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('ALTSTORE_SESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}
