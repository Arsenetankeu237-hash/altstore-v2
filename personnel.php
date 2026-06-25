<?php
/**
 * personnel.php — Gestion du personnel & rôles de la boutique active.
 *
 *  - Invite un utilisateur existant (par email) dans cette boutique avec un rôle
 *  - Crée un compte employé s'il n'existe pas encore
 *  - Modifie le rôle / active / désactive un membre
 *
 *  Réservé au propriétaire (et directeurs si on l'étend).
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('personnel.manage');

$bid = active_boutique_id();
$bout = active_boutique();
$erreur = '';
$success = '';

// ---------------- Traitement ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        $pdo = db();

        if ($action === 'ajouter') {
            $email = strtolower(clean($_POST['email'] ?? ''));
            $role  = clean($_POST['role'] ?? 'employe');
            $prenom = clean($_POST['prenom'] ?? '');
            $nom    = clean($_POST['nom'] ?? '');
            $pass   = (string)($_POST['password'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email invalide');
            if (!in_array($role, available_roles(), true)) throw new RuntimeException('Rôle invalide');

            // L'utilisateur existe-t-il déjà ?
            $user = fetch_one("SELECT id FROM utilisateurs WHERE email=?", [$email]);

            if ($user) {
                $userId = (int)$user['id'];
                // Déjà membre de cette boutique ?
                $deja = fetch_one("SELECT id FROM utilisateurs_boutique WHERE utilisateur_id=? AND boutique_id=?", [$userId, $bid]);
                if ($deja) throw new RuntimeException('Cet utilisateur fait déjà partie du personnel.');
            } else {
                if ($prenom === '' || $nom === '') throw new RuntimeException('Prénom et nom requis pour un nouvel utilisateur');
                if (strlen($pass) < 6) throw new RuntimeException('Mot de passe ≥ 6 caractères');
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $st = $pdo->prepare("INSERT INTO utilisateurs (type_compte,prenom,nom,email,mot_de_passe,email_verifie,statut_compte) VALUES ('particulier',?,?,?,?,1,'actif')");
                $st->execute([$prenom, $nom, $email, $hash]);
                $userId = (int)$pdo->lastInsertId();
            }

            $pdo->prepare("INSERT INTO utilisateurs_boutique (utilisateur_id, boutique_id, role_boutique, statut) VALUES (?,?,?,'actif')")
                ->execute([$userId, $bid, $role]);
            $success = "Membre ajouté à la boutique avec le rôle « {$role} ».";
        }

        elseif ($action === 'modifier') {
            $ubId = (int)($_POST['ub_id'] ?? 0);
            $role = clean($_POST['role'] ?? '');
            if (!in_array($role, available_roles(), true)) throw new RuntimeException('Rôle invalide');
            // Vérif appartenance
            $ub = fetch_one("SELECT * FROM utilisateurs_boutique WHERE id=? AND boutique_id=?", [$ubId, $bid]);
            if (!$ub) throw new RuntimeException('Membre introuvable');
            execute("UPDATE utilisateurs_boutique SET role_boutique=? WHERE id=?", [$role, $ubId]);
            $success = "Rôle mis à jour.";
        }

        elseif ($action === 'toggle') {
            $ubId = (int)($_POST['ub_id'] ?? 0);
            $ub = fetch_one("SELECT * FROM utilisateurs_boutique WHERE id=? AND boutique_id=?", [$ubId, $bid]);
            if (!$ub) throw new RuntimeException('Membre introuvable');
            $new = $ub['statut'] === 'actif' ? 'inactif' : 'actif';
            execute("UPDATE utilisateurs_boutique SET statut=? WHERE id=?", [$new, $ubId]);
            $success = "Membre " . ($new === 'actif' ? 'activé' : 'désactivé') . ".";
        }

        elseif ($action === 'supprimer') {
            $ubId = (int)($_POST['ub_id'] ?? 0);
            execute("DELETE FROM utilisateurs_boutique WHERE id=? AND boutique_id=?", [$ubId, $bid]);
            $success = "Membre retiré de la boutique.";
        }

    } catch (Throwable $e) {
        $erreur = IS_PROD ? 'Une erreur est survenue.' : $e->getMessage();
    }
}

// ---------------- Liste du personnel ----------------
$staff = fetch_all(
    "SELECT ub.*, u.prenom, u.nom, u.email, u.telephone, u.derniere_connexion
     FROM utilisateurs_boutique ub
     INNER JOIN utilisateurs u ON u.id=ub.utilisateur_id
     WHERE ub.boutique_id=?
     ORDER BY ub.role_boutique, u.nom",
    [$bid]
);

// Le propriétaire est affiché séparément (non modifiable ici)
$proprio = fetch_one("SELECT u.* FROM utilisateurs u INNER JOIN boutiques b ON b.proprietaire_id=u.id WHERE b.id=?", [$bid]);

$roleLabels = ['directeur'=>'Directeur','comptable'=>'Comptable','commercial'=>'Commercial','caissier'=>'Caissier','gestionnaire_stock'=>'Gestionnaire de stock','employe'=>'Employé'];
$roleColors = ['directeur'=>'bg-blue','comptable'=>'bg-amber','commercial'=>'bg-green','caissier'=>'bg-green','gestionnaire_stock'=>'bg-amber','employe'=>'bg-gray'];
?>
<?php layout_header('Personnel — ' . ($bout['nom'] ?? ''), 'personnel'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.4rem;flex-wrap:wrap;gap:1rem">
  <div>
    <h2 style="font-size:20px;margin-bottom:4px">👥 Personnel — <?= e($bout['nom'] ?? '') ?></h2>
    <p style="color:var(--muted);font-size:13px">Gérez les accès et rôles des membres de cette boutique.</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fas fa-user-plus"></i> Ajouter un membre</button>
</div>

<?php if ($success) echo '<div class="flash flash-success"><i class="fas fa-check-circle"></i> ' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<!-- Carte permissions du rôle (info) -->
<div class="card" style="margin-bottom:1rem;background:var(--surf2)">
  <details>
    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--muted)">📖 Voir la matrice des rôles & permissions</summary>
    <div style="margin-top:1rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
      <?php foreach (available_roles() as $r): ?>
      <div style="background:var(--surf);border:1px solid var(--bd);border-radius:10px;padding:1rem">
        <b style="color:var(--ember)"><?= e($roleLabels[$r]) ?></b>
        <ul style="margin-top:.6rem;font-size:11px;color:var(--muted);list-style:none;line-height:1.8">
          <?php foreach (array_unique(array_map(fn($p)=>explode('.',$p)[0], role_permissions($r))) as $mod): ?>
            <li><i class="fas fa-check" style="color:var(--green);width:14px"></i> <?= e($mod) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
  </details>
</div>

<!-- Propriétaire -->
<div class="card" style="margin-bottom:1rem;border-left:3px solid var(--gold)">
  <div style="display:flex;align-items:center;gap:1rem">
    <div class="avatar" style="width:46px;height:46px;background:linear-gradient(135deg,var(--gold),var(--ember))"><?= strtoupper(substr($proprio['prenom'] ?? 'P',0,1)) ?></div>
    <div style="flex:1">
      <div style="font-size:14px;font-weight:600"><?= e(trim(($proprio['prenom'] ?? '').' '.($proprio['nom'] ?? ''))) ?></div>
      <small style="color:var(--muted)"><?= e($proprio['email'] ?? '') ?> · <?= e($proprio['telephone'] ?? '') ?></small>
    </div>
    <span class="badge bg-amber"><i class="fas fa-crown"></i> Propriétaire</span>
  </div>
</div>

<!-- Personnel -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Membre</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (empty($staff)): ?>
        <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted)">Aucun membre du personnel. Cliquez sur « Ajouter un membre ».</td></tr>
      <?php else: foreach ($staff as $s): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar" style="width:32px;height:32px;font-size:12px"><?= strtoupper(substr($s['prenom'],0,1)) ?></div>
              <div><b><?= e(trim($s['prenom'].' '.$s['nom'])) ?></b></div>
            </div>
          </td>
          <td style="color:var(--muted)"><?= e($s['email']) ?></td>
          <td>
            <form method="post" style="display:inline-flex;gap:6px;align-items:center">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="modifier">
              <input type="hidden" name="ub_id" value="<?= (int)$s['id'] ?>">
              <select name="role" onchange="this.form.submit()" style="background:var(--bg);border:1px solid var(--bd);color:var(--text);padding:4px 8px;border-radius:6px;font-size:12px">
                <?php foreach (available_roles() as $r): ?>
                  <option value="<?= e($r) ?>" <?= $s['role_boutique']===$r?'selected':'' ?>><?= e($roleLabels[$r]) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><span class="badge <?= $s['statut']==='actif'?'bg-green':'bg-red' ?>"><?= e($s['statut']) ?></span></td>
          <td style="color:var(--muted)"><?= $s['derniere_connexion']?fdatetime($s['derniere_connexion']):'<em style="opacity:.5">jamais</em>' ?></td>
          <td>
            <form method="post" style="display:inline-flex;gap:4px" onsubmit="return confirmDelete('Confirmer ?')">
              <?= csrf_field() ?>
              <input type="hidden" name="ub_id" value="<?= (int)$s['id'] ?>">
              <button type="submit" name="action" value="toggle" class="btn btn-ghost btn-sm" title="<?= $s['statut']==='actif'?'Désactiver':'Activer' ?>">
                <i class="fas <?= $s['statut']==='actif'?'fa-toggle-on':'fa-toggle-off' ?>"></i></button>
              <button type="submit" name="action" value="supprimer" class="btn btn-danger btn-sm" title="Retirer"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- ===== MODAL : Ajouter ===== -->
<div id="modalAdd" class="modal-overlay" style="display:none">
  <div class="card" style="max-width:460px;width:100%">
    <div class="card-h"><h3><i class="fas fa-user-plus" style="color:var(--ember)"></i> Ajouter un membre</h3>
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modalAdd')"><i class="fas fa-times"></i></button></div>
    <p style="color:var(--muted);font-size:12px;margin-bottom:1rem">S'il existe déjà un compte avec cet email, il sera ajouté tel quel. Sinon, un compte sera créé.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="ajouter">
      <div class="form-field" style="margin-bottom:1rem"><label>Email *</label><input type="email" name="email" required placeholder="employe@exemple.ci"></div>
      <div class="form-field" style="margin-bottom:1rem"><label>Rôle *</label>
        <select name="role" required>
          <?php foreach (available_roles() as $r): ?><option value="<?= e($r) ?>"><?= e($roleLabels[$r]) ?></option><?php endforeach; ?>
        </select></div>
      <div class="section-title">Si nouveau compte (laisser vide si existe déjà)</div>
      <div class="grid grid-2" style="gap:1rem">
        <div class="form-field"><label>Prénom</label><input name="prenom"></div>
        <div class="form-field"><label>Nom</label><input name="nom"></div>
      </div>
      <div class="form-field" style="margin-top:1rem"><label>Mot de passe initial</label><input type="password" name="password" placeholder="min. 6 caractères"></div>
      <button class="btn btn-primary" style="width:100%;margin-top:1rem"><i class="fas fa-check"></i> Ajouter</button>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) o.style.display='none'; }));
</script>

<?php layout_footer(); ?>
