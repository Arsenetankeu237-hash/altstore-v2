<?php
/**
 * install.php — Installation en un clic.
 *
 *  1. Exécute le schéma SQL (database/schema.sql)
 *  2. Crée un propriétaire de démo (si base vide)
 *  3. Crée 2 boutiques de démo + leurs caisses
 *
 *  ⚠️  À SUPPRIMER en production une fois l'installation faite.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><meta charset="utf-8">';
echo '<title>Installation ALT STORE ERP v2</title>';
echo '<body style="font-family:Segoe UI,Arial;background:#0e0e0e;color:#eee;padding:2rem;line-height:1.6">';

function step(string $msg): void { echo "<div>✅ {$msg}</div>"; if (!IS_PROD) while (ob_get_level()) ob_end_flush(); @flush(); }
function fail(string $msg): void { echo "<div style='color:#ff6b6b'>❌ {$msg}</div>"; exit; }

echo '<h1>🛠️ Installation ALT STORE ERP v2</h1>';

// ----------------------------------------------------------------
//  1. Schéma
// ----------------------------------------------------------------
echo '<h2>1/6 — Création du schéma</h2>';
$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false) fail('schema.sql introuvable');

try {
    db()->exec($schema);
    step('Schéma appliqué (tables créées si absentes)');
} catch (Throwable $e) {
    fail('Erreur schéma : ' . $e->getMessage());
}

// ----------------------------------------------------------------
//  2. Admin par défaut (si aucun utilisateur)
// ----------------------------------------------------------------
echo '<h2>2/6 — Compte propriétaire</h2>';
$count = (int) db()->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
if ($count === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    execute(
        "INSERT INTO utilisateurs (type_compte, prenom, nom, email, telephone, mot_de_passe, email_verifie, nom_entreprise, secteur_activite, cgu_acceptees, statut_compte)
         VALUES ('entreprise', 'TSEFO', 'STEVE', 'admin@altstore.ci', '69901529', ?, 1, 'ALT STORE', 'Technologie & Innovation', 1, 'actif')",
        [$hash]
    );
    $ownerId = (int) db()->lastInsertId();
    step("Compte propriétaire créé — <b>admin@altstore.ci</b> / <b>admin123</b>");
} else {
    $ownerId = (int) db()->query("SELECT id FROM utilisateurs ORDER BY id LIMIT 1")->fetchColumn();
    step('Compte existant détecté (id=' . $ownerId . ')');
}

// ----------------------------------------------------------------
//  3. Deux boutiques de démo (si aucune)
// ----------------------------------------------------------------
echo '<h2>3/6 — Boutiques de démo</h2>';
$nbBout = (int) db()->query("SELECT COUNT(*) FROM boutiques")->fetchColumn();
if ($nbBout === 0) {
    $stmtB = db()->prepare(
        "INSERT INTO boutiques (proprietaire_id, nom, slug, code, description, couleur, ville, telephone, email, devise, tva_defaut)
         VALUES (?,?,?,?,?,?,?,?,?,?,18.00)"
    );

    $boutiques = [
        ['ALT STORE Principal', 'alt-store-principal', 'ALT-001', 'Boutique principale', '#F9A825', 'Abidjan', '27 22 00 00', 'principal@altstore.ci'],
        ['ALT STORE Annexe',     'alt-store-annexe',    'ALT-002', 'Boutique secondaire', '#6366f1', 'Abidjan', '27 22 00 01', 'annexe@altstore.ci'],
    ];
    foreach ($boutiques as $b) {
        $stmtB->execute([$ownerId, $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], 'XOF']);
        step('Boutique créée : ' . $b[0] . ' (' . $b[2] . ')');
    }
} else {
    step('Boutiques déjà présentes (' . $nbBout . ')');
}

// ----------------------------------------------------------------
//  4. Caisse par défaut pour chaque boutique sans caisse
// ----------------------------------------------------------------
echo '<h2>4/6 — Caisses par défaut</h2>';
$bouts = fetch_all("SELECT id, nom, code FROM boutiques");
$createdCaisse = 0;
foreach ($bouts as $b) {
    $has = (int) fetch_one("SELECT COUNT(*) c FROM caisses WHERE boutique_id = ?", [$b['id']])['c'];
    if ($has === 0) {
        $code = str_replace('ALT', 'CSE', $b['code']);
        execute(
            "INSERT INTO caisses (boutique_id, code_caisse, nom, description, couleur, solde_initial, is_default)
             VALUES (?,?,?,?,?,0,1)",
            [$b['id'], $code, 'Caisse principale — ' . $b['nom'], 'Caisse par défaut de ' . $b['nom'], '#F9A825']
        );
        $createdCaisse++;
    }
}
step($createdCaisse . ' caisse(s) créée(s). Total boutiques équipées : ' . count($bouts));

// ----------------------------------------------------------------
//  5. Entreprise par défaut pour chaque boutique
// ----------------------------------------------------------------
echo '<h2>5/6 — Entreprise par défaut</h2>';
$createdEnt = 0;
foreach ($bouts as $b) {
    $has = (int) fetch_one("SELECT COUNT(*) c FROM entreprises WHERE boutique_id = ?", [$b['id']])['c'];
    if ($has === 0) {
        execute(
            "INSERT INTO entreprises (boutique_id, nom_entreprise, sigle, secteur_activite, nif, ville, telephone, email)
             VALUES (?, 'ALT STORE', 'AS', 'Technologie & Innovation', 'NIF-0000000A', 'Abidjan', '27 22 00 00', 'contact@altstore.ci')",
            [$b['id']]
        );
        $createdEnt++;
    }
}
step($createdEnt . ' entreprise(s) par défaut créée(s).');

// ----------------------------------------------------------------
//  6. Catégories de démo
// ----------------------------------------------------------------
echo '<h2>6/6 — Catégories de démo</h2>';
$createdCat = 0;
$catNames = ['Électronique', 'Accessoires', 'Consommables', 'Logiciel', 'Services'];
foreach ($bouts as $b) {
    $has = (int) fetch_one("SELECT COUNT(*) c FROM categories WHERE boutique_id = ?", [$b['id']])['c'];
    if ($has === 0) {
        foreach ($catNames as $cn) {
            execute("INSERT INTO categories (boutique_id, nom) VALUES (?, ?)", [$b['id'], $cn]);
            $createdCat++;
        }
    }
}
step($createdCat . ' catégorie(s) créée(s).');

// ----------------------------------------------------------------
echo '<hr style="border-color:#333;margin:2rem 0">';
echo '<h2>🎉 Installation terminée !</h2>';
echo '<p>Identifiants de connexion :</p>';
echo '<ul>
        <li><b>Email</b> : admin@altstore.ci</li>
        <li><b>Mot de passe</b> : admin123</li>
      </ul>';
echo '<p style="color:#f9a825">⚠️ Pensez à <b>supprimer install.php</b> après vérification.</p>';
echo '<p><a href="login.php" style="color:#F9A825">→ Aller à la connexion</a></p>';
echo '</body>';
