<?php
/**
 * config/csrf.php — Protection CSRF pour tous les formulaires / actions mutantes.
 *
 * Usage côté vue :   <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 * Côté contrôleur :  csrf_verify()  (arrêt sur échec)
 */
declare(strict_types=1);

/** Génère (si besoin) et retourne le token CSRF de session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF reçu (POST, en-tête X-CSRF-Token ou paramètre GET).
 * Termine avec 419 si invalide.
 */
function csrf_verify(): void
{
    $received = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_GET['csrf_token']
        ?? null;

    if (!is_string($received) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $received)) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Jeton de sécurité expiré ou invalide. Veuillez réessayer.']);
        exit;
    }
}

/** Champ de formulaire prêt à l'emploi. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Vérifie un token CSRF passé directement (pour les appels API JSON).
 * Retourne true/false au lieu de terminer le script.
 */
function csrf_verify_token(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
