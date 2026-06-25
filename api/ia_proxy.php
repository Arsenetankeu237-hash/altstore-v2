<?php
/**
 * api/ia_proxy.php — Proxy serveur pour l'assistant IA.
 *
 *  ⚠️  La clé API reste CÔTÉ SERVEUR. Elle n'est JAMAIS envoyée au navigateur.
 *  Le JS de la page ia.php appelle CE fichier au lieu d'appeler OpenRouter directement.
 *
 *  POST { csrf_token, message, history (JSON) }
 *  -> flux SSE ou JSON
 */
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Seuls les utilisateurs connectés peuvent utiliser l'IA
if (!is_logged_in()) json_response(['success' => false, 'message' => 'Authentification requise.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Méthode non autorisée'], 405);
csrf_verify();

if (OPENROUTER_API_KEY === '') {
    json_response(['success' => false, 'message' => 'Assistant IA non configuré (clé API absente).'], 503);
}

$message  = clean($_POST['message'] ?? '');
$history  = json_decode($_POST['history'] ?? '[]', true) ?: [];
$stream   = ($_POST['stream'] ?? '0') === '1';

if ($message === '') json_response(['success' => false, 'message' => 'Message vide.']);

$bout = active_boutique();
$context = $bout
    ? "Tu es l'assistant IA d'ALT STORE ERP, pour la boutique « {$bout['nom']} » (code {$bout['code']}). Réponds en français, de façon concise et utile, pour aider à la gestion (stocks, ventes, clients, caisse)."
    : "Tu es l'assistant IA d'ALT STORE ERP. Réponds en français, de façon concise.";

// Construction des messages
$messages = [['role' => 'system', 'content' => $context]];
foreach (array_slice($history, -8) as $h) {
    $messages[] = ['role' => $h['role'] ?? 'user', 'content' => $h['content'] ?? ''];
}
$messages[] = ['role' => 'user', 'content' => $message];

if ($stream) {
    // Mode streaming (SSE)
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $payload = json_encode([
        'model' => OPENROUTER_MODEL,
        'messages' => $messages,
        'stream' => true,
        'max_tokens' => 1024,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
            'HTTP-Referer: ' . APP_URL,
            'X-Title: ALT STORE ERP Assistant',
        ],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) {
            echo $data;
            @flush();
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 60,
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// Mode simple (non streamé)
$payload = json_encode([
    'model' => OPENROUTER_MODEL,
    'messages' => $messages,
    'max_tokens' => 1024,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENROUTER_API_KEY,
        'HTTP-Referer: ' . APP_URL,
        'X-Title: ALT STORE ERP Assistant',
    ],
    CURLOPT_TIMEOUT => 60,
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    error_log('[IA proxy] curl error: ' . $err);
    json_response(['success' => false, 'message' => 'Erreur réseau vers le service IA.'], 502);
}

$data = json_decode($resp, true);
if ($code !== 200 || empty($data['choices'][0]['message']['content'])) {
    json_response(['success' => false, 'message' => 'Réponse IA invalide.', 'detail' => IS_PROD ? null : $data], 502);
}

json_response([
    'success' => true,
    'reply' => $data['choices'][0]['message']['content'],
]);
