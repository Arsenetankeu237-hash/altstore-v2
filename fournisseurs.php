<?php
/**
 * fournisseurs.php — Gestion des fournisseurs de la boutique active.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('fournisseurs.view');

$bid = active_boutique_id();
$bout = active_boutique();
$erreur = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (($_POST['action'] ?? '') === 'create') {
            require_permission('fournisseurs.create');
            $nom = clean($_POST['nom'] ?? ''); if ($nom==='') throw new RuntimeException('Nom obligatoire');
            execute("INSERT INTO fournisseurs (boutique_id, nom, entreprise, email, telephone, adresse, ville, pays) VALUES (?,?,?,?,?,?,?,?)",
                [$bid, $nom, clean($_POST['entreprise']??''), clean($_POST['email']??''), clean($_POST['telephone']??''), clean($_POST['adresse']??''), clean($_POST['ville']??''), clean($_POST['pays']??'Côte d\'Ivoire')]);
            $success = "Fournisseur ajouté.";
        } elseif (($_POST['action'] ?? '') === 'delete') {
            require_permission('fournisseurs.delete');
            execute("DELETE FROM fournisseurs WHERE id=? AND boutique_id=?", [(int)$_POST['id'], $bid]);
            $success = "Fournisseur supprimé.";
        }
    } catch (Throwable $e) { $erreur = IS_PROD ? 'Erreur.' : $e->getMessage(); }
}

$search = clean($_GET['q'] ?? '');
$sql = "SELECT f.* FROM fournisseurs f WHERE f.boutique_id=?"; $params=[$bid];
if ($search) { $sql.=" AND (f.nom LIKE ? OR f.telephone LIKE ?)"; $kw="%$search%"; array_push($params,$kw,$kw); }
$sql .= " ORDER BY f.nom";
$liste = fetch_all($sql, $params);
?>
<?php layout_header('Fournisseurs — ' . ($bout['nom'] ?? ''), 'fournisseurs'); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem">
  <div><h2 style="font-size:20px">🚚 Fournisseurs</h2><p style="color:var(--muted);font-size:13px">Carnet d'adresses de <?= e($bout['nom'] ?? '') ?></p></div>
  <?php if (can('fournisseurs.create')): ?><button class="btn btn-primary" onclick="document.getElementById('m').style.display='flex'"><i class="fas fa-plus"></i> Nouveau</button><?php endif; ?>
</div>
<?php if ($success) echo '<div class="flash flash-success">'.e($success).'</div>' ?>
<?php if ($erreur)  echo '<div class="flash flash-error">'.e($erreur).'</div>' ?>
<form method="get" style="margin-bottom:1rem"><input name="q" value="<?= e($search) ?>" placeholder="🔎 Rechercher" style="width:100%;max-width:400px;padding:10px 14px;background:var(--surf);border:1px solid var(--bd);border-radius:9px;color:var(--text)"></form>
<div class="table-wrap"><table>
  <thead><tr><th>Nom</th><th>Contact</th><th>Ville</th><th>Solde dû</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($liste)): ?><tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted)">Aucun fournisseur.</td></tr>
  <?php else: foreach ($liste as $f): ?>
    <tr><td><b><?= e($f['nom']) ?></b><?= $f['entreprise']?'<br><small style="color:var(--muted)">'.e($f['entreprise']).'</small>':'' ?></td>
      <td style="color:var(--muted)"><?= e($f['telephone'] ?: '—') ?><br><small><?= e($f['email'] ?: '') ?></small></td>
      <td><?= e($f['ville'] ?: '—') ?></td>
      <td><?= (float)$f['solde_du']>0?'<span style="color:var(--red)">'.money($f['solde_du']).'</span>':'<span style="color:var(--muted)">—</span>' ?></td>
      <td><?php if (can('fournisseurs.delete')): ?><form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table></div>

<div id="m" class="modal-overlay" style="display:none"><div class="card" style="max-width:440px;width:100%">
  <div class="card-h"><h3>Nouveau fournisseur</h3><button class="btn btn-ghost btn-sm" onclick="document.getElementById('m').style.display='none'"><i class="fas fa-times"></i></button></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="form-grid">
      <div class="form-field full"><label>Nom *</label><input name="nom" required></div>
      <div class="form-field full"><label>Entreprise</label><input name="entreprise"></div>
      <div class="form-field"><label>Téléphone</label><input name="telephone"></div>
      <div class="form-field"><label>Email</label><input type="email" name="email"></div>
      <div class="form-field"><label>Ville</label><input name="ville" value="Abidjan"></div>
      <div class="form-field full"><label>Adresse</label><textarea name="adresse"></textarea></div>
    </div>
    <button class="btn btn-primary" style="width:100%;margin-top:1rem">Ajouter</button>
  </form></div></div>
<script>document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.style.display='none'}))</script>
<?php layout_footer(); ?>
