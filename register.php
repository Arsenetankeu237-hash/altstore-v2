<?php
/**
 * register.php — Inscription d'un nouveau propriétaire + sa 1ère boutique.
 *
 *  Étapes : validation -> création utilisateur -> création boutique -> caisse par défaut -> connexion.
 */
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) redirect(APP_URL . '/dashboard.php');

$erreur = '';
$form = ['prenom'=>'','nom'=>'','email'=>'','telephone'=>'','nom_entreprise'=>'','ville'=>'Abidjan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['prenom']         = clean($_POST['prenom'] ?? '');
    $form['nom']            = clean($_POST['nom'] ?? '');
    $form['email']          = strtolower(clean($_POST['email'] ?? ''));
    $form['telephone']      = clean($_POST['telephone'] ?? '');
    $form['nom_entreprise'] = clean($_POST['nom_entreprise'] ?? '');
    $form['ville']          = clean($_POST['ville'] ?? 'Abidjan');
    $password  = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    // Validations
    if ($form['prenom']===''||$form['nom']===''||$form['email']==='')
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL))
        $erreur = 'Adresse email invalide.';
    elseif (strlen($password) < 6)
        $erreur = 'Le mot de passe doit comporter au moins 6 caractères.';
    elseif ($password !== $password2)
        $erreur = 'Les mots de passe ne correspondent pas.';
    elseif (fetch_one("SELECT id FROM utilisateurs WHERE email = ?", [$form['email']]))
        $erreur = 'Un compte existe déjà avec cet email.';

    if (!$erreur) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // 1. Utilisateur propriétaire
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $pdo->prepare(
                "INSERT INTO utilisateurs (type_compte, prenom, nom, email, telephone, mot_de_passe, email_verifie, nom_entreprise, secteur_activite, cgu_acceptees, statut_compte)
                 VALUES ('entreprise',?,?,?,?,?,1,?,'Technologie & Innovation',1,'actif')"
            );
            $st->execute([$form['prenom'], $form['nom'], $form['email'], $form['telephone'], $hash, $form['nom_entreprise'] ?: ($form['prenom'].' '.$form['nom'])]);
            $userId = (int)$pdo->lastInsertId();

            // 2. Première boutique (code unique généré)
            $code = 'ALT-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
            // s'assurer de l'unicité
            while (fetch_one("SELECT id FROM boutiques WHERE code = ?", [$code])) {
                $code = 'ALT-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
            }
            $slug = slugify($form['nom_entreprise'] ?: ($form['prenom'] . '-' . $form['nom'])) . '-' . substr($code, 4);

            $st = $pdo->prepare(
                "INSERT INTO boutiques (proprietaire_id, nom, slug, code, description, couleur, ville, telephone, email, devise, tva_defaut)
                 VALUES (?,?,?,?,?,?,?,?,?, 'XOF', 18.00)"
            );
            $boutNom = $form['nom_entreprise'] ?: ('Boutique de ' . $form['prenom']);
            $st->execute([$userId, $boutNom, $slug, $code, 'Boutique créée à l\'inscription', '#F9A825', $form['ville'], $form['telephone'], $form['email']]);
            $boutId = (int)$pdo->lastInsertId();

            // 3. Caisse par défaut
            $pdo->prepare(
                "INSERT INTO caisses (boutique_id, code_caisse, nom, description, couleur, solde_initial, is_default)
                 VALUES (?,?,?,?, '#F9A825', 0, 1)"
            )->execute([$boutId, 'CSE-' . $code, 'Caisse principale', 'Caisse par défaut de ' . $boutNom]);

            $pdo->commit();

            // 4. Connexion automatique
            attempt_login($form['email'], $password);
            redirect(APP_URL . '/dashboard.php');

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[register] ' . $e->getMessage());
            $erreur = IS_PROD ? 'Erreur technique, veuillez réessayer.' : 'Erreur : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Créer un compte — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--ember:#D94F1A;--gold:#C4933A;--bg:#0e0e0e;--surf:#161616;--bd:rgba(255,255,255,.07)}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Sora',sans-serif}
body{background:var(--bg);color:#eee;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;position:relative}
body::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:48px 48px;pointer-events:none}
.card{position:relative;z-index:2;width:100%;max-width:480px;background:var(--surf);border:1px solid var(--bd);border-radius:18px;padding:2.5rem}
.top-line{position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--ember),var(--gold),transparent);border-radius:18px 18px 0 0}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:1.4rem}
.mark{width:42px;height:42px;border-radius:8px;background:var(--ember);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800}
h1{font-size:21px;margin-bottom:.3rem}.sub{color:rgba(255,255,255,.45);font-size:13px;margin-bottom:1.6rem}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.field{margin-bottom:.9rem}.field label{display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:5px;font-weight:600}
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);font-size:13px}
input{width:100%;padding:11px 12px 11px 38px;background:#0e0e0e;border:1px solid var(--bd);border-radius:9px;color:#fff;font-size:13px;transition:.2s}
input:focus{outline:none;border-color:var(--ember);box-shadow:0 0 0 3px rgba(217,79,26,.15)}
.err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.3);color:#fca5a5;padding:10px 13px;border-radius:9px;font-size:12px;margin-bottom:1rem}
.btn{width:100%;padding:12px;background:var(--ember);border:none;border-radius:10px;color:#fff;font-weight:600;font-size:14px;cursor:pointer;margin-top:.5rem;transition:.2s}
.btn:hover{background:#B83F12}
.foot{text-align:center;margin-top:1.4rem;font-size:13px;color:rgba(255,255,255,.4)}.foot a{color:var(--gold);text-decoration:none}
.full{grid-column:1/-1}
</style>
</head>
<body>
<div class="card">
  <div class="top-line"></div>
  <div class="brand"><div class="mark">A</div><div><b>ALT STORE ERP</b></div></div>
  <h1>Créer votre compte 🚀</h1>
  <p class="sub">Vous bénéficiez d'une première boutique et de sa caisse, prêtes à l'emploi.</p>

  <?php if ($erreur): ?><div class="err"><i class="fas fa-circle-exclamation"></i> <?= e($erreur) ?></div><?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="grid">
      <div class="field"><label>Prénom *</label>
        <div class="input-wrap"><i class="fas fa-user"></i><input name="prenom" required value="<?= e($form['prenom']) ?>"></div></div>
      <div class="field"><label>Nom *</label>
        <div class="input-wrap"><i class="fas fa-user"></i><input name="nom" required value="<?= e($form['nom']) ?>"></div></div>
    </div>
    <div class="field"><label>Email *</label>
      <div class="input-wrap"><i class="fas fa-envelope"></i><input type="email" name="email" required value="<?= e($form['email']) ?>"></div></div>
    <div class="grid">
      <div class="field"><label>Téléphone</label>
        <div class="input-wrap"><i class="fas fa-phone"></i><input name="telephone" value="<?= e($form['telephone']) ?>"></div></div>
      <div class="field"><label>Ville</label>
        <div class="input-wrap"><i class="fas fa-city"></i><input name="ville" value="<?= e($form['ville']) ?>"></div></div>
    </div>
    <div class="field"><label>Nom de votre 1ère boutique</label>
      <div class="input-wrap"><i class="fas fa-store"></i><input name="nom_entreprise" value="<?= e($form['nom_entreprise']) ?>" placeholder="Ex : ALT Store Principal"></div></div>
    <div class="grid">
      <div class="field"><label>Mot de passe *</label>
        <div class="input-wrap"><i class="fas fa-lock"></i><input type="password" name="password" required></div></div>
      <div class="field"><label>Confirmer *</label>
        <div class="input-wrap"><i class="fas fa-lock"></i><input type="password" name="password2" required></div></div>
    </div>
    <button class="btn" type="submit"><i class="fas fa-rocket"></i> Créer mon compte & ma boutique</button>
  </form>
  <p class="foot">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
</div>
</body>
</html>
