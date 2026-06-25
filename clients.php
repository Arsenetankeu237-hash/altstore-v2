<?php
/**
 * clients.php — Gestion des clients de la boutique active.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('clients.view');

$bid = active_boutique_id();
$bout = active_boutique();
$erreur = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        $pdo = db();
        if ($action === 'create') {
            require_permission('clients.create');
            $nom = clean($_POST['nom'] ?? ''); if ($nom==='') throw new RuntimeException('Nom obligatoire');
            execute("INSERT INTO clients (boutique_id, nom, entreprise, email, telephone, telephone2, adresse, ville, pays, type_client, solde_credit, limite_credit) VALUES (?,?,?,?,?,?,?,?,?,'particulier',0,0)",
                [$bid, $nom, clean($_POST['entreprise'] ?? ''), clean($_POST['email'] ?? ''), clean($_POST['telephone'] ?? ''), clean($_POST['telephone2'] ?? ''), clean($_POST['adresse'] ?? ''), clean($_POST['ville'] ?? ''), clean($_POST['pays'] ?? "Côte d'Ivoire")]);
            $success = "Client « {$nom} » ajouté.";
        } elseif ($action === 'delete') {
            require_permission('clients.delete');
            execute("DELETE FROM clients WHERE id=? AND boutique_id=?", [(int)$_POST['id'], $bid]);
            $success = "Client supprimé.";
        }
    } catch (Throwable $e) { $erreur = IS_PROD ? 'Erreur.' : $e->getMessage(); }
}

$search = clean($_GET['q'] ?? '');
$sql = "SELECT c.*, (SELECT COUNT(*) FROM ventes v WHERE v.client_id=c.id AND v.boutique_id=c.boutique_id) AS nb_ventes,
        (SELECT COALESCE(SUM(v.total_ttc),0) FROM ventes v WHERE v.client_id=c.id AND v.boutique_id=c.boutique_id AND v.statut='payee') AS ca_total
        FROM clients c WHERE c.boutique_id=?";
$params = [$bid];
if ($search) { $sql .= " AND (c.nom LIKE ? OR c.telephone LIKE ? OR c.email LIKE ?)"; $kw="%$search%"; array_push($params,$kw,$kw,$kw); }
$sql .= " ORDER BY c.nom LIMIT 200";
$clients = fetch_all($sql, $params);
?>
<?php layout_header('Clients — ' . ($bout['nom'] ?? ''), 'clients'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem">
  <div><h2 style="font-size:20px">👥 Clients</h2><p style="color:var(--muted);font-size:13px">Fichier clients de <?= e($bout['nom'] ?? '') ?></p></div>
  <?php if (can('clients.create')): ?><button class="btn btn-primary" onclick="openModal('modalClient')"><i class="fas fa-plus"></i> Nouveau client</button><?php endif; ?>
</div>

<?php if ($success) echo '<div class="flash flash-success">' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error">' . e($erreur) . '</div>'; ?>

<form method="get" style="margin-bottom:1rem"><input name="q" value="<?= e($search) ?>" placeholder="🔎 Rechercher un client" style="width:100%;max-width:400px;padding:10px 14px;background:var(--surf);border:1px solid var(--bd);border-radius:9px;color:var(--text)"></form>

<div class="table-wrap">
  <table>
    <thead><tr><th>Client</th><th>Contact</th><th>Ville</th><th>Type</th><th>Achats</th><th>CA total</th><th>Solde</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (empty($clients)): ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--muted)">Aucun client.</td></tr>
      <?php else: foreach ($clients as $c): ?>
        <tr>
          <td><b><?= e($c['nom']) ?></b><?= $c['entreprise']?'<br><small style="color:var(--muted)">'.e($c['entreprise']).'</small>':'' ?></td>
          <td style="color:var(--muted)"><?= e($c['telephone'] ?: '—') ?><br><small><?= e($c['email'] ?: '') ?></small></td>
          <td><?= e($c['ville'] ?: '—') ?></td>
          <td><span class="badge bg-blue"><?= e($c['type_client']) ?></span></td>
          <td><?= (int)$c['nb_ventes'] ?></td>
          <td><b style="color:var(--green)"><?= money($c['ca_total']) ?></b></td>
          <td><?= (float)$c['solde_credit']>0 ? '<span style="color:var(--red)">'.money($c['solde_credit']).'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
          <td>
            <?php if (can('clients.delete')): ?>
            <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer ce client ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div id="modalClient" class="modal-overlay" style="display:none">
  <div class="card" style="max-width:480px;width:100%">
    <div class="card-h"><h3><i class="fas fa-user-plus" style="color:var(--ember)"></i> Nouveau client</h3>
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modalClient')"><i class="fas fa-times"></i></button></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-field full"><label>Nom *</label><input name="nom" required></div>
        <div class="form-field full"><label>Entreprise</label><input name="entreprise"></div>
        <div class="form-field"><label>Téléphone</label><input name="telephone"></div>
        <div class="form-field"><label>Téléphone 2</label><input name="telephone2"></div>
        <div class="form-field full"><label>Email</label><input type="email" name="email"></div>
        <div class="form-field"><label>Ville</label><input name="ville" value="Abidjan"></div>
        <div class="form-field"><label>Pays</label><input name="pays" value="Côte d'Ivoire"></div>
        <div class="form-field full"><label>Adresse</label><textarea name="adresse"></textarea></div>
      </div>
      <button class="btn btn-primary" style="width:100%;margin-top:1rem"><i class="fas fa-check"></i> Ajouter</button>
    </form>
  </div>
</div>
<script>function openModal(id){document.getElementById(id).style.display='flex'}function closeModal(id){document.getElementById(id).style.display='none'}document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.style.display='none'}))</script>
<?php layout_footer(); ?>
