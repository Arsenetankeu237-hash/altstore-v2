<?php
/**
 * config/auth.php — Authentification + contexte multi-boutiques.
 *
 *  Concepts clés :
 *  ----------------
 *  - Un "compte propriétaire" (utilisateurs) peut posséder plusieurs BOUTIQUES.
 *  - Chaque boutique a son propre PERSONNEL (utilisateurs_boutique) avec un RÔLE.
 *  - À la connexion, on détermine la "boutique active" stockée en session.
 *  - Toutes les requêtes métier filtrent par boutique_id (jamais par user_id seul).
 *
 *  Sessions utilisées :
 *    $_SESSION['user_id']        -> id du compte connecté
 *    $_SESSION['user_email']
 *    $_SESSION['user_role']      -> 'proprietaire' | 'employe'
 *    $_SESSION['boutique_active'] -> id de la boutique de travail courante
 *    $_SESSION['role_boutique']  -> rôle dans la boutique active (pour les employés)
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ----------------------------------------------------------------
//  Authentification
// ----------------------------------------------------------------

/** Tente de connecter un utilisateur. Retourne true/false + message. */
function attempt_login(string $email, string $password): array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND statut_compte = 'actif' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['mot_de_passe'])) {
        return ['ok' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }

    // Connexion réussie — on régénère l'ID pour éviter la fixation de session
    session_regenerate_id(true);

    // Le compte est-il propriétaire ou employé ?
    // Un propriétaire a au moins une boutique dans la table `boutiques`.
    $owns = $pdo->prepare("SELECT COUNT(*) FROM boutiques WHERE proprietaire_id = ?");
    $owns->execute([$user['id']]);
    $isOwner = (int)$owns->fetchColumn() > 0;

    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_name']   = trim($user['prenom'] . ' ' . $user['nom']);
    $_SESSION['user_role']   = $isOwner ? 'proprietaire' : 'employe';
    $_SESSION['login_time']  = time();

    // Mettre à jour la dernière connexion
    $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")
        ->execute([$user['id']]);

    // Définir la boutique active
    select_default_boutique();

    return ['ok' => true];
}

/** Choisit la première boutique accessible comme boutique active. */
function select_default_boutique(): void
{
    $choices = list_user_boutiques();
    if (!empty($choices)) {
        set_active_boutique($choices[0]['id'], $choices[0]['role_boutique'] ?? null);
    } else {
        unset($_SESSION['boutique_active'], $_SESSION['role_boutique']);
    }
}

/** Déconnexion totale. */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ----------------------------------------------------------------
//  Vérifications d'accès (garde-fous)
// ----------------------------------------------------------------

/**
 * Exige qu'un utilisateur soit connecté. Redirige vers login sinon.
 * À appeler en TÊTE de chaque page protégée.
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/** Exige qu'une boutique soit active. */
function require_boutique(): void
{
    require_login();
    if (empty($_SESSION['boutique_active'])) {
        header('Location: ' . APP_URL . '/boutiques.php?need=1');
        exit;
    }
}

/** Exige un rôle spécifique (ou plusieurs) dans la boutique active. */
function require_role(string ...$allowedRoles): void
{
    require_boutique();
    $current = current_role();
    if ($current === null || !in_array($current, $allowedRoles, true)) {
        http_response_code(403);
        die('Accès refusé : permissions insuffisantes pour cette action.');
    }
}

/** Connecté ? */
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }

/** Rôle du compte (proprietaire / employe). */
function current_account_role(): ?string { return $_SESSION['user_role'] ?? null; }

/**
 * Rôle de l'utilisateur DANS la boutique active.
 *  - Un propriétaire est toujours 'proprietaire' dans ses boutiques.
 *  - Sinon on lit role_boutique depuis utilisateurs_boutique.
 */
function current_role(): ?string
{
    if (empty($_SESSION['boutique_active'])) return null;
    if (($_SESSION['user_role'] ?? null) === 'proprietaire') return 'proprietaire';
    return $_SESSION['role_boutique'] ?? null;
}

/** ID du compte connecté. */
function current_user_id(): int { return (int)($_SESSION['user_id'] ?? 0); }

/** ID de la boutique active. */
function active_boutique_id(): int { return (int)($_SESSION['boutique_active'] ?? 0); }

// ----------------------------------------------------------------
//  Sélection / changement de boutique
// ----------------------------------------------------------------

/** Change la boutique de travail courante (avec contrôle d'accès). */
function set_active_boutique(int $boutiqueId, ?string $role = null): bool
{
    $access = get_boutique_access($boutiqueId);
    if ($access === null) return false; // pas autorisé

    $_SESSION['boutique_active'] = $boutiqueId;
    $_SESSION['role_boutique']   = $access['role_boutique'];
    return true;
}

/**
 * Liste toutes les boutiques auxquelles l'utilisateur a accès,
 * avec son rôle dans chacune.
 */
function list_user_boutiques(): array
{
    $uid  = current_user_id();
    $pdo  = db();

    // 1) Boutiques possédées
    $stmt = $pdo->prepare("
        SELECT b.*, 'proprietaire' AS role_boutique
        FROM boutiques b
        WHERE b.proprietaire_id = ? AND b.statut = 'active'
        ORDER BY b.created_at ASC
    ");
    $stmt->execute([$uid]);
    $owned = $stmt->fetchAll();

    // 2) Boutiques où l'utilisateur est employé
    $stmt = $pdo->prepare("
        SELECT b.*, ub.role_boutique
        FROM boutiques b
        INNER JOIN utilisateurs_boutique ub ON ub.boutique_id = b.id
        WHERE ub.utilisateur_id = ? AND ub.statut = 'actif' AND b.statut = 'active'
        ORDER BY b.created_at ASC
    ");
    $stmt->execute([$uid]);
    $staff = $stmt->fetchAll();

    // On fusionne (une boutique ne peut pas être à la fois possédée et employée)
    $seen = array_column($owned, 'id');
    foreach ($staff as $s) {
        if (!in_array($s['id'], $seen, true)) $owned[] = $s;
    }
    return $owned;
}

/**
 * Vérifie l'accès à une boutique. Retourne ['role_boutique'=>...] ou null.
 */
function get_boutique_access(int $boutiqueId): ?array
{
    $uid = current_user_id();
    $pdo = db();

    // Propriétaire ?
    $stmt = $pdo->prepare("SELECT id FROM boutiques WHERE id = ? AND proprietaire_id = ? AND statut = 'active'");
    $stmt->execute([$boutiqueId, $uid]);
    if ($stmt->fetch()) return ['role_boutique' => 'proprietaire'];

    // Employé ?
    $stmt = $pdo->prepare("
        SELECT role_boutique FROM utilisateurs_boutique
        WHERE boutique_id = ? AND utilisateur_id = ? AND statut = 'actif'
    ");
    $stmt->execute([$boutiqueId, $uid]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Charge les infos de la boutique active (mis en cache dans la requête). */
function active_boutique(): ?array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    if (active_boutique_id() === 0) return null;

    $stmt = db()->prepare("SELECT * FROM boutiques WHERE id = ?");
    $stmt->execute([active_boutique_id()]);
    return $cache = $stmt->fetch() ?: null;
}
