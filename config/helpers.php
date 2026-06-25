<?php
/**
 * config/helpers.php — Fonctions utilitaires transverses.
 */
declare(strict_types=1);

/** Nettoie une chaîne utilisateur (échappement HTML à l'affichage recommandé en plus). */
function clean(?string $v): string { return trim((string)($v ?? '')); }

/** Format monétaire (FCFA, sans décimales). */
function money($n): string { return number_format((float)($n ?? 0), 0, ',', ' ') . ' F'; }
function num($n): string   { return number_format((float)($n ?? 0), 0, ',', ' '); }

/** Date formatée FR. */
function fdate(?string $d): string {
    if (empty($d)) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '—';
}
function fdatetime(?string $d): string {
    if (empty($d)) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y à H:i', $ts) : '—';
}

/** Redirige et termine. */
function redirect(string $url): void { header('Location: ' . $url); exit; }

/** Échappement HTML. */
function e(?string $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Réponse JSON + exit. */
function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lit un paramètre POST. */
function post(string $key, $default = null) { return $_POST[$key] ?? $default; }
/** Lit un paramètre GET. */
function query(string $key, $default = null) { return $_GET[$key] ?? $default; }

/** Exécute une requête préparée et renvoie toutes les lignes (avec gestion d'erreur). */
function fetch_all(string $sql, array $params = []): array {
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        error_log('[SQL] ' . $e->getMessage() . ' | ' . $sql);
        return [];
    }
}
function fetch_one(string $sql, array $params = []): ?array {
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) {
        error_log('[SQL] ' . $e->getMessage() . ' | ' . $sql);
        return null;
    }
}
function execute(string $sql, array $params = []): int {
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    } catch (Throwable $e) {
        error_log('[SQL] ' . $e->getMessage() . ' | ' . $sql);
        return 0;
    }
}

/** Génère un slug à partir d'un texte. */
function slugify(string $text): string {
    $text = trim($text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($conv !== false) $text = $conv;
    }
    $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
    return strtolower(trim($text, '-'));
}

/** Messages flash (succès/erreur d'une page à l'autre). */
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['_flash'][] = ['msg' => $msg, 'type' => $type];
}
function flash_pull(): ?array {
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f ? $f[0] : null;
}

/** Solde calculé d'une caisse (initial + encaissements/transferts in - sorties). */
function solde_caisse(int $caisseId): float {
    $c = fetch_one("SELECT solde_initial FROM caisses WHERE id=?", [$caisseId]);
    if (!$c) return 0.0;
    $in  = (float)(fetch_one("SELECT COALESCE(SUM(montant),0) m FROM caisse_mouvements WHERE caisse_id=? AND type IN('encaissement','transfert_in')", [$caisseId])['m'] ?? 0);
    $out = (float)(fetch_one("SELECT COALESCE(SUM(montant),0) m FROM caisse_mouvements WHERE caisse_id=? AND type IN('decaissement','transfert_out')", [$caisseId])['m'] ?? 0);
    return (float)$c['solde_initial'] + $in - $out;
}

/** Détermine la classe CSS d'un badge de statut de stock. */
function stock_badge(int $qte, int $min): string {
    if ($qte <= 0)   return 'bg-red';
    if ($qte <= $min) return 'bg-amber';
    return 'bg-green';
}

/** Inclut le header commun (avec navigation). */
function layout_header(string $title = '', string $active = ''): void {
    $GLOBALS['_page_title'] = $title;
    $GLOBALS['_page_active'] = $active;
    require ROOT_PATH . '/views/layouts/header.php';
}
/** Inclut le footer commun. */
function layout_footer(): void {
    require ROOT_PATH . '/views/layouts/footer.php';
}
