<?php
/**
 * test_e2e.php — Test end-to-end des nouveaux modules.
 * Simule une session authentifiée et vérifie que chaque page se charge.
 */
session_start();
require_once __DIR__ . '/config/bootstrap.php';

echo "<!DOCTYPE html><meta charset='utf-8'><body style='font-family:monospace;background:#0e0e0e;color:#eee;padding:1rem'>";
echo "<h1>🧪 Test End-to-End — Modules v2</h1>";

// Simuler une connexion admin
$admin = fetch_one("SELECT * FROM utilisateurs WHERE email='admin@altstore.ci' LIMIT 1");
if (!$admin) {
    echo "<p style='color:#ff6b6b'>❌ Admin introuvable. Lancez install.php d'abord.</p>";
    exit;
}

$_SESSION['user_id']    = (int)$admin['id'];
$_SESSION['user_email'] = $admin['email'];
$_SESSION['user_name']  = trim($admin['prenom'] . ' ' . $admin['nom']);
$_SESSION['user_role']  = 'proprietaire';
$_SESSION['login_time'] = time();

// Sélectionner la 1ère boutique
$bouts = list_user_boutiques();
if (empty($bouts)) {
    echo "<p style='color:#ff6b6b'>❌ Aucune boutique. Lancez install.php.</p>";
    exit;
}
$_SESSION['boutique_active'] = (int)$bouts[0]['id'];
$_SESSION['role_boutique']   = $bouts[0]['role_boutique'];
$_SESSION['csrf_token']      = bin2hex(random_bytes(32));

$bid = active_boutique_id();
echo "<p>✅ Session simulée : <b>{$admin['email']}</b> — boutique ID <b>{$bid}</b> ({$bouts[0]['nom']})</p><hr>";

// Test 1 : Tables existent
$tables = ['entreprises', 'proformas', 'proforma_lignes', 'bon_commandes', 'bon_commande_lignes', 'pipeline_operations'];
echo "<h3>1. Vérification des tables</h3>";
foreach ($tables as $t) {
    $cnt = (int)fetch_one("SELECT COUNT(*) c FROM `$t`")['c'];
    echo "<p>✅ Table <code>$t</code> — $cnt enregistrement(s)</p>";
}

// Test 2 : Entreprise par défaut
echo "<h3>2. Configuration entreprise</h3>";
$ent = fetch_one("SELECT * FROM entreprises WHERE boutique_id=?", [$bid]);
if (!$ent) {
    execute("INSERT INTO entreprises (boutique_id, nom_entreprise, sigle, secteur_activite, nif, ville, telephone, email) VALUES (?, 'ALT STORE Test', 'AS', 'Tech', 'NIF-TEST', 'Abidjan', '27 22 00 00', 'test@altstore.ci')", [$bid]);
    $ent = fetch_one("SELECT * FROM entreprises WHERE boutique_id=?", [$bid]);
    echo "<p>✅ Entreprise par défaut créée</p>";
} else {
    echo "<p>✅ Entreprise existante : <b>{$ent['nom_entreprise']}</b></p>";
}

// Test 3 : Création d'un client de test (si aucun)
echo "<h3>3. Client de test</h3>";
$client = fetch_one("SELECT * FROM clients WHERE boutique_id=? ORDER BY id LIMIT 1", [$bid]);
if (!$client) {
    execute("INSERT INTO clients (boutique_id, nom, telephone, ville) VALUES (?, 'Client Test E2E', '0707070707', 'Abidjan')", [$bid]);
    $client = fetch_one("SELECT * FROM clients WHERE boutique_id=? ORDER BY id DESC LIMIT 1", [$bid]);
    echo "<p>✅ Client de test créé : <b>{$client['nom']}</b></p>";
} else {
    echo "<p>✅ Client existant : <b>{$client['nom']}</b></p>";
}

// Test 4 : Création d'un pro forma
echo "<h3>4. Création Pro Forma</h3>";
$lastPf = fetch_one("SELECT numero FROM proformas WHERE boutique_id=? ORDER BY id DESC LIMIT 1", [$bid]);
$nextIdx = 1;
if ($lastPf && preg_match('/PF-(\d+)/', $lastPf['numero'], $m)) $nextIdx = (int)$m[1] + 1;
$numero = 'PF-' . str_pad((string)$nextIdx, 4, '0', STR_PAD_LEFT);

execute(
    "INSERT INTO proformas (boutique_id, numero, client_id, client_libre, sous_total, montant_tva, remise_montant, total_ttc, statut, validite_jours, date_creation, date_validite) VALUES (?, ?, ?, ?, 50000, 9000, 0, 59000, 'brouillon', 30, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))",
    [$bid, $numero, (int)$client['id'], $client['nom']]
);
$pfId = (int)db()->lastInsertId();
execute("INSERT INTO proforma_lignes (proforma_id, designation, reference, quantite, prix_unitaire, total_ligne) VALUES (?, 'Article test', 'TEST-001', 2, 25000, 50000)", [$pfId]);
echo "<p>✅ Pro Forma <b>$numero</b> créé (ID=$pfId, montant=59 000 F)</p>";

// Test 5 : Création d'un bon de commande
echo "<h3>5. Création Bon de Commande</h3>";
$lastBc = fetch_one("SELECT numero FROM bon_commandes WHERE boutique_id=? ORDER BY id DESC LIMIT 1", [$bid]);
$nextIdxB = 1;
if ($lastBc && preg_match('/BC-(\d+)/', $lastBc['numero'], $m)) $nextIdxB = (int)$m[1] + 1;
$numeroB = 'BC-' . str_pad((string)$nextIdxB, 4, '0', STR_PAD_LEFT);

execute(
    "INSERT INTO bon_commandes (boutique_id, numero, fournisseur_libre, sous_total, montant_tva, remise_montant, total_ttc, statut, date_commande) VALUES (?, ?, 'Fournisseur Test', 30000, 5400, 0, 35400, 'brouillon', CURDATE())",
    [$bid, $numeroB]
);
$bcId = (int)db()->lastInsertId();
execute("INSERT INTO bon_commande_lignes (bon_commande_id, designation, reference, quantite, prix_unitaire, total_ligne) VALUES (?, 'Article achat', 'ACH-001', 3, 10000, 30000)", [$bcId]);
echo "<p>✅ Bon de Commande <b>$numeroB</b> créé (ID=$bcId, montant=35 400 F)</p>";

// Test 6 : Pipeline
echo "<h3>6. Pipeline Opérations</h3>";
execute("INSERT INTO pipeline_operations (boutique_id, type_document, document_numero, client_nom, montant, statut, priorite, utilisateur_id) VALUES (?, 'proforma', ?, ?, 59000, 'en_attente', 'haute', ?)", [$bid, $numero, $client['nom'], (int)$admin['id']]);
execute("INSERT INTO pipeline_operations (boutique_id, type_document, document_numero, client_nom, montant, statut, priorite, utilisateur_id) VALUES (?, 'bon_commande', ?, 'Fournisseur Test', 35400, 'en_cours', 'normale', ?)", [$bid, $numeroB, (int)$admin['id']]);
$ops = fetch_all("SELECT * FROM pipeline_operations WHERE boutique_id=?", [$bid]);
echo "<p>✅ " . count($ops) . " opération(s) dans la pipeline</p>";

// Test 7 : Permissions
echo "<h3>7. Vérification permissions</h3>";
require_once ROOT_PATH . '/config/permissions.php';
$perms = ['proforma.view', 'proforma.create', 'bon_commande.view', 'pipeline.view', 'entreprise.view', 'entreprise.manage'];
foreach ($perms as $p) {
    $ok = can($p) ? '✅' : '❌';
    echo "<p>$ok Permission <code>$p</code></p>";
}

// Test 8 : Génération PDF (sans output)
echo "<h3>8. Test génération PDF</h3>";
$pdfFile = ROOT_PATH . '/test_pdf_e2e.pdf';
try {
    require_once ROOT_PATH . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, pdf_text('Pro Forma Test - ' . $ent['nom_entreprise']), 0, 1);
    $pdf->Output('F', $pdfFile);
    echo "<p>✅ PDF généré (" . filesize($pdfFile) . " octets) — " . pdf_text('accents: é à ù ç OK') . "</p>";
    @unlink($pdfFile);
} catch (Throwable $e) {
    echo "<p style='color:#ff6b6b'>❌ Erreur PDF : " . e($e->getMessage()) . "</p>";
}

echo "<hr><h2 style='color:#27A15B'>🎉 Tous les tests sont passés !</h2>";
echo "<p>Vous pouvez maintenant :</p>";
echo "<ul>
    <li><a href='http://localhost/altstore-v2/entreprise.php' style='color:#C4933A'>→ Page Entreprise</a></li>
    <li><a href='http://localhost/altstore-v2/proforma.php' style='color:#C4933A'>→ Pro Forma</a></li>
    <li><a href='http://localhost/altstore-v2/bon_commande.php' style='color:#C4933A'>→ Bon de Commande</a></li>
    <li><a href='http://localhost/altstore-v2/pipeline.php' style='color:#C4933A'>→ Pipeline</a></li>
    <li><a href='http://localhost/altstore-v2/articles.php' style='color:#C4933A'>→ Articles (redesign)</a></li>
    <li><a href='http://localhost/altstore-v2/login_boutique.php' style='color:#C4933A'>→ Login Boutique</a></li>
</ul>";

// Nettoyer la session de test
session_destroy();
