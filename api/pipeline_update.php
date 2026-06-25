<?php
/**
 * api/pipeline_update.php — API JSON pour déplacer/supprimer des cartes pipeline.
 *
 *  POST JSON : { id: int, statut: string }  → déplacer
 *  POST JSON : { id: int, action: 'delete' } → supprimer
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_boutique();

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['ok' => false, 'message' => 'JSON invalide'], 400);
}

// CSRF verification
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!csrf_verify_token($token)) {
    json_response(['ok' => false, 'message' => 'CSRF invalide'], 403);
}

$id = (int)($input['id'] ?? 0);
if ($id === 0) {
    json_response(['ok' => false, 'message' => 'ID manquant'], 400);
}

$bid = active_boutique_id();

// Suppression
if (($input['action'] ?? '') === 'delete') {
    require_permission('pipeline.manage');
    execute("DELETE FROM pipeline_operations WHERE id=? AND boutique_id=?", [$id, $bid]);
    json_response(['ok' => true]);
}

// Déplacement
$statut = clean($input['statut'] ?? '');
if (!in_array($statut, ['en_attente', 'en_cours', 'termine'], true)) {
    json_response(['ok' => false, 'message' => 'Statut invalide'], 400);
}

require_permission('pipeline.manage');
execute("UPDATE pipeline_operations SET statut=? WHERE id=? AND boutique_id=?", [$statut, $id, $bid]);
json_response(['ok' => true]);
