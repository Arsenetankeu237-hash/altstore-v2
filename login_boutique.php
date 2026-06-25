<?php
/**
 * login_boutique.php — Connexion pour le personnel des boutiques.
 *
 *  Même design que login.php mais orienté boutique :
 *   - Email + Mot de passe + sélection de boutique (si l'utilisateur est dans plusieurs)
 *   - Après connexion, redirige vers le dashboard avec les permissions du rôle boutique
 *
 *  Un lien vers cette page est affiché sur login.php principal.
 */
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) redirect(APP_URL . '/dashboard.php');

$erreur = '';
$boutiquesList = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email     = clean($_POST['email'] ?? '');
    $password  = (string)($_POST['password'] ?? '');
    $boutiqueId = (int)($_POST['boutique_id'] ?? 0);

    $res = attempt_login($email, $password);
    if ($res['ok']) {
        // Si l'utilisateur a spécifié une boutique, la forcer
        if ($boutiqueId > 0) {
            $success = set_active_boutique($boutiqueId);
            if (!$success) {
                // Boutique non accessible, on prend la première disponible
                select_default_boutique();
            }
        } else {
            select_default_boutique();
        }

        // Vérifier que l'utilisateur a bien une boutique (sinon rediriger vers login principal)
        if (empty($_SESSION['boutique_active'])) {
            logout();
            $erreur = 'Aucune boutique accessible. Veuillez contacter votre administrateur.';
        } else {
            redirect(APP_URL . '/dashboard.php');
        }
    } else {
        // Avant d'afficher l'erreur, on veut quand même montrer les boutiques disponibles
        // pour un email donné (pour aider l'utilisateur à choisir)
        $erreur = $res['message'] ?? 'Connexion impossible.';

        // Charger les boutiques si l'email est dans la base (pour le dropdown)
        $userRow = fetch_one("SELECT id FROM utilisateurs WHERE email = ? AND statut_compte = 'actif'", [$email]);
        if ($userRow) {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT b.*, ub.role_boutique
                FROM boutiques b
                INNER JOIN utilisateurs_boutique ub ON ub.boutique_id = b.id AND ub.utilisateur_id = ? AND ub.statut = 'actif'
                WHERE b.statut = 'active'
                ORDER BY b.nom
            ");
            $stmt->execute([(int)$userRow['id']]);
            $boutiquesList = $stmt->fetchAll();

            // Ajouter les boutiques possédées
            $stmt2 = $pdo->prepare("SELECT b.*, 'proprietaire' AS role_boutique FROM boutiques b WHERE b.proprietaire_id = ? AND b.statut = 'active' ORDER BY b.nom");
            $stmt2->execute([(int)$userRow['id']]);
            $owned = $stmt2->fetchAll();
            $seen = array_column($boutiquesList, 'id');
            foreach ($owned as $o) {
                if (!in_array($o['id'], $seen, true)) $boutiquesList[] = $o;
            }
        }
    }
} elseif (!empty($_GET['email'])) {
    // Pré-remplir email depuis login.php
    $email = clean($_GET['email']);
    // Charger les boutiques pour cet email
    $userRow = fetch_one("SELECT id FROM utilisateurs WHERE email = ? AND statut_compte = 'actif'", [$email]);
    if ($userRow) {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT b.*, ub.role_boutique
            FROM boutiques b
            INNER JOIN utilisateurs_boutique ub ON ub.boutique_id = b.id AND ub.utilisateur_id = ? AND ub.statut = 'actif'
            WHERE b.statut = 'active'
            ORDER BY b.nom
        ");
        $stmt->execute([(int)$userRow['id']]);
        $boutiquesList = $stmt->fetchAll();

        $stmt2 = $pdo->prepare("SELECT b.*, 'proprietaire' AS role_boutique FROM boutiques b WHERE b.proprietaire_id = ? AND b.statut = 'active' ORDER BY b.nom");
        $stmt2->execute([(int)$userRow['id']]);
        $owned = $stmt2->fetchAll();
        $seen = array_column($boutiquesList, 'id');
        foreach ($owned as $o) {
            if (!in_array($o['id'], $seen, true)) $boutiquesList[] = $o;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion Boutique — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--ember:#D94F1A;--gold:#C4933A;--green:#27A15B;--bg:#0e0e0e;--surf:#161616;--bd:rgba(255,255,255,.07)}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Sora',sans-serif}
body{background:var(--bg);color:#eee;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;position:relative;overflow:hidden}
body::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:48px 48px;pointer-events:none}
body::after{content:'';position:absolute;bottom:-150px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(39,161,91,.18) 0%,transparent 65%);pointer-events:none}
.card{position:relative;z-index:2;width:100%;max-width:440px;background:var(--surf);border:1px solid var(--bd);border-radius:18px;padding:2.5rem;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.top-line{position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--green),var(--gold),transparent);border-radius:18px 18px 0 0}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:2rem}
.mark{width:42px;height:42px;border-radius:8px;background:var(--green);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;box-shadow:0 0 24px rgba(39,161,91,.4)}
.brand b{font-size:18px;letter-spacing:.04em}.brand small{display:block;color:rgba(255,255,255,.4);font-size:11px;font-weight:400}
h1{font-size:22px;margin-bottom:.4rem;font-weight:700}
.sub{color:rgba(255,255,255,.45);font-size:13px;margin-bottom:1.8rem}
.field{margin-bottom:1.1rem}
.field label{display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600}
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);font-size:14px}
input,select{width:100%;padding:13px 14px 13px 42px;background:#0e0e0e;border:1px solid var(--bd);border-radius:10px;color:#fff;font-size:14px;transition:.2s;font-family:inherit}
input:focus,select:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(39,161,91,.15)}
select{appearance:none;cursor:pointer}
.err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.3);color:#fca5a5;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:1.2rem}
.btn{width:100%;padding:13px;background:var(--green);border:none;border-radius:10px;color:#fff;font-weight:600;font-size:15px;cursor:pointer;transition:.2s;margin-top:.4rem;font-family:inherit}
.btn:hover{background:#1E8A4B;box-shadow:0 8px 24px rgba(39,161,91,.3)}
.foot{text-align:center;margin-top:1.6rem;font-size:13px;color:rgba(255,255,255,.4)}
.foot a{color:var(--gold);text-decoration:none}.foot a:hover{text-decoration:underline}
.boutique-option{display:flex;align-items:center;gap:10px;padding:10px 14px;background:#0e0e0e;border:1px solid var(--bd);border-radius:10px;cursor:pointer;transition:.2s;margin-bottom:.5rem}
.boutique-option:hover{border-color:var(--green)}
.boutique-option.selected{border-color:var(--green);background:rgba(39,161,91,.08)}
.boutique-option .bo-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.boutique-option .bo-name{font-size:14px;font-weight:500}
.boutique-option .bo-meta{font-size:11px;color:rgba(255,255,255,.4)}
</style>
</head>
<body>
<div class="card">
  <div class="top-line"></div>
  <div class="brand">
    <div class="mark"><i class="fas fa-store" style="font-size:18px"></i></div>
    <div><b>ALT STORE ERP</b><small>Connexion Boutique</small></div>
  </div>
  <h1>Bonjour 👋</h1>
  <p class="sub">Connectez-vous pour accéder à votre boutique.</p>

  <?php if ($erreur): ?><div class="err"><i class="fas fa-circle-exclamation"></i> <?= e($erreur) ?></div><?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="field">
      <label>Adresse email</label>
      <div class="input-wrap"><i class="fas fa-envelope"></i>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? $_GET['email'] ?? '') ?>" placeholder="vous@exemple.ci">
      </div>
    </div>
    <div class="field">
      <label>Mot de passe</label>
      <div class="input-wrap"><i class="fas fa-lock"></i>
        <input type="password" name="password" required placeholder="••••••••">
      </div>
    </div>

    <?php if (!empty($boutiquesList)): ?>
    <div class="field">
      <label>Boutique d'accès</label>
      <div id="boutiqueList">
        <?php foreach ($boutiquesList as $i => $b): ?>
          <div class="boutique-option <?= $i === 0 ? 'selected' : '' ?>" onclick="selectBoutique(this, <?= (int)$b['id'] ?>)">
            <span class="bo-dot" style="background:<?= e($b['couleur'] ?? '#F9A825') ?>"></span>
            <div>
              <div class="bo-name"><?= e($b['nom']) ?></div>
              <div class="bo-meta"><?= e($b['code']) ?> · <em><?= e($b['role_boutique']) ?></em></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="boutique_id" id="boutiqueId" value="<?= (int)($boutiquesList[0]['id'] ?? 0) ?>">
    </div>
    <?php endif; ?>

    <button class="btn" type="submit"><i class="fas fa-right-to-bracket"></i> Se connecter</button>
  </form>

  <p class="foot">
    <a href="login.php"><i class="fas fa-arrow-left"></i> Connexion propriétaire</a>
  </p>
</div>

<script>
function selectBoutique(el, id) {
  document.querySelectorAll('.boutique-option').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('boutiqueId').value = id;
}
</script>
</body>
</html>
