<?php
/**
 * api/switch_boutique.php — Change la boutique de travail courante.
 *
 *  POST { csrf_token, boutique_id }
 *  -> { success, boutique: {...}, role }
 */
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Méthode non autorisée'], 405);
}
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Non connecté'], 401);
}

csrf_verify();

$boutiqueId = (int)($_POST['boutique_id'] ?? 0);
if ($boutiqueId <= 0) {
    json_response(['success' => false, 'message' => 'Boutique invalide']);
}

if (!set_active_boutique($boutiqueId)) {
    json_response(['success' => false, 'message' => "Vous n'avez pas accès à cette boutique."], 403);
}

$b = active_boutique();
json_response([
    'success'  => true,
    'message'  => 'Boutique activée : ' . $b['nom'],
    'boutique' => [
        'id'     => (int)$b['id'],
        'nom'    => $b['nom'],
        'code'   => $b['code'],
        'couleur'=> $b['couleur'],
    ],
    'role'     => current_role(),
]);
