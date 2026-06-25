<?php
/**
 * login.php — Connexion sécurisée.
 */
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) redirect(APP_URL . '/dashboard.php');

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = clean($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $res = attempt_login($email, $password);
    if ($res['ok']) {
        redirect(APP_URL . '/dashboard.php');
    }
    $erreur = $res['message'] ?? 'Connexion impossible.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--ember:#D94F1A;--gold:#C4933A;--bg:#0e0e0e;--surf:#161616;--bd:rgba(255,255,255,.07);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Sora',sans-serif}
body{background:var(--bg);color:#eee;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;position:relative;overflow:hidden}
body::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:48px 48px;pointer-events:none}
body::after{content:'';position:absolute;bottom:-150px;left:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(217,79,26,.18) 0%,transparent 65%);pointer-events:none}
.card{position:relative;z-index:2;width:100%;max-width:420px;background:var(--surf);border:1px solid var(--bd);border-radius:18px;padding:2.5rem;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.top-line{position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--ember),var(--gold),transparent);border-radius:18px 18px 0 0}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:2rem}
.mark{width:42px;height:42px;border-radius:8px;background:var(--ember);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;box-shadow:0 0 24px rgba(217,79,26,.4)}
.brand b{font-size:18px;letter-spacing:.04em}.brand small{display:block;color:rgba(255,255,255,.4);font-size:11px;font-weight:400}
h1{font-size:22px;margin-bottom:.4rem;font-weight:700}
.sub{color:rgba(255,255,255,.45);font-size:13px;margin-bottom:1.8rem}
.field{margin-bottom:1.1rem}
.field label{display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600}
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);font-size:14px}
input{width:100%;padding:13px 14px 13px 42px;background:#0e0e0e;border:1px solid var(--bd);border-radius:10px;color:#fff;font-size:14px;transition:.2s}
input:focus{outline:none;border-color:var(--ember);box-shadow:0 0 0 3px rgba(217,79,26,.15)}
.err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.3);color:#fca5a5;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:1.2rem}
.btn{width:100%;padding:13px;background:var(--ember);border:none;border-radius:10px;color:#fff;font-weight:600;font-size:15px;cursor:pointer;transition:.2s;margin-top:.4rem}
.btn:hover{background:#B83F12;box-shadow:0 8px 24px rgba(217,79,26,.3)}
.foot{text-align:center;margin-top:1.6rem;font-size:13px;color:rgba(255,255,255,.4)}
.foot a{color:var(--gold);text-decoration:none}.foot a:hover{text-decoration:underline}
.hint{margin-top:1.4rem;padding:12px;background:rgba(249,168,37,.08);border:1px solid rgba(249,168,37,.2);border-radius:10px;font-size:12px;color:#fcd34d}
</style>
</head>
<body>
<div class="card">
  <div class="top-line"></div>
  <div class="brand">
    <div class="mark">A</div>
    <div><b>ALT STORE ERP</b><small>Gestion multi-boutiques</small></div>
  </div>
  <h1>Bon retour 👋</h1>
  <p class="sub">Connectez-vous pour accéder à vos boutiques.</p>

  <?php if ($erreur): ?><div class="err"><i class="fas fa-circle-exclamation"></i> <?= e($erreur) ?></div><?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="field">
      <label>Adresse email</label>
      <div class="input-wrap"><i class="fas fa-envelope"></i>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="vous@exemple.ci">
      </div>
    </div>
    <div class="field">
      <label>Mot de passe</label>
      <div class="input-wrap"><i class="fas fa-lock"></i>
        <input type="password" name="password" required placeholder="••••••••">
      </div>
    </div>
    <button class="btn" type="submit"><i class="fas fa-right-to-bracket"></i> Se connecter</button>
  </form>

  <p class="foot">Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
  <p class="foot" style="margin-top:.6rem"><a href="login_boutique.php"><i class="fas fa-store"></i> Connexion Boutique / Personnel</a></p>
  <?php if (!IS_PROD): ?>
  <div class="hint"><b>Démo :</b> admin@altstore.ci / admin123 (après <code>install.php</code>)</div>
  <?php endif; ?>
</div>
</body>
</html>
