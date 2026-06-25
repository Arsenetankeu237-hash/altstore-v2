<?php
/**
 * pipeline.php — Pipeline Opérations (Kanban).
 *
 *  3 colonnes : En attente | En cours | Terminé
 *  Cartes déplaçables entre colonnes (boutons flèches).
 *
 *  Les opérations sont automatiquement ajoutées lors de la création
 *  d'un pro forma ou bon de commande. On peut aussi ajouter manuellement.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('pipeline.view');
require_boutique();

$bid    = active_boutique_id();
$bout   = active_boutique();
$erreur = '';
$tab    = $_GET['tab'] ?? 'pipeline';

// ---------------- Traitement ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'move') {
            require_permission('pipeline.manage');
            $id    = (int)($_POST['id'] ?? 0);
            $statut = clean($_POST['statut'] ?? '');
            if (in_array($statut, ['en_attente', 'en_cours', 'termine'], true)) {
                execute("UPDATE pipeline_operations SET statut=? WHERE id=? AND boutique_id=?", [$statut, $id, $bid]);
                json_response(['ok' => true]);
            }
            json_response(['ok' => false, 'message' => 'Statut invalide'], 400);
        }

        elseif ($action === 'add') {
            require_permission('pipeline.manage');
            $typeDoc    = clean($_POST['type_document'] ?? 'proforma');
            $numero     = clean($_POST['document_numero'] ?? '');
            $clientNom  = clean($_POST['client_nom'] ?? '');
            $montant    = (float)str_replace([' ', 'F'], '', (string)($_POST['montant'] ?? 0));
            $priorite   = clean($_POST['priorite'] ?? 'normale');
            $echeance   = clean($_POST['date_echeance'] ?? '');
            $notes      = clean($_POST['notes'] ?? '');

            if ($clientNom === '') throw new RuntimeException('Nom client requis.');
            if (!in_array($priorite, ['basse', 'normale', 'haute'], true)) $priorite = 'normale';

            execute(
                "INSERT INTO pipeline_operations (boutique_id, type_document, document_numero, client_nom, montant, statut, priorite, date_echeance, notes, utilisateur_id)
                 VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?, ?, ?)",
                [$bid, $typeDoc, $numero, $clientNom, $montant, $priorite, $echeance ?: null, $notes, current_user_id()]
            );
            $success = 'Opération ajoutée dans la pipeline.';
        }

        elseif ($action === 'delete') {
            require_permission('pipeline.manage');
            $id = (int)($_POST['id'] ?? 0);
            execute("DELETE FROM pipeline_operations WHERE id=? AND boutique_id=?", [$id, $bid]);
            json_response(['ok' => true]);
        }

    } catch (Throwable $e) {
        if ($action === 'move' || $action === 'delete') {
            json_response(['ok' => false, 'message' => IS_PROD ? 'Erreur' : $e->getMessage()], 500);
        }
        $erreur = IS_PROD ? 'Une erreur est survenue.' : $e->getMessage();
    }
}

// ---------------- Données Pipeline ----------------
$ops = fetch_all(
    "SELECT * FROM pipeline_operations WHERE boutique_id=? ORDER BY FIELD(priorite,'haute','normale','basse'), FIELD(statut,'en_attente','en_cours','termine'), created_at DESC",
    [$bid]
);

$grouped = ['en_attente' => [], 'en_cours' => [], 'termine' => []];
foreach ($ops as $o) {
    $grouped[$o['statut']][] = $o;
}

$types = [
    'proforma'      => ['label' => 'Pro Forma', 'color' => '#6366f1'],
    'bon_commande'  => ['label' => 'Bon Cmd',   'color' => '#27A15B'],
    'vente'         => ['label' => 'Vente',      'color' => '#D94F1A'],
    'facture'       => ['label' => 'Facture',    'color' => '#a855f7'],
];

$prioriteLabels = ['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute'];
$prioriteClasses = ['basse' => 'kc-badge-basse', 'normale' => 'kc-badge-normale', 'haute' => 'kc-badge-haute'];
?>
<?php layout_header('Pipeline Opérations — ' . ($bout['nom'] ?? ''), 'pipeline'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem">
  <div><h2 style="font-size:20px">📊 Pipeline Opérations</h2>
  <p style="color:var(--muted);font-size:13px">Suivez vos documents en cours de traitement</p></div>
  <div class="grid grid-3" style="gap:.6rem;flex-wrap:wrap">
    <div class="kpi" style="min-width:80px"><div class="kpi-label">En attente</div><div class="kpi-val" style="color:var(--amber)"><?= count($grouped['en_attente']) ?></div></div>
    <div class="kpi" style="min-width:80px"><div class="kpi-label">En cours</div><div class="kpi-val" style="color:var(--blue)"><?= count($grouped['en_cours']) ?></div></div>
    <div class="kpi green" style="min-width:80px"><div class="kpi-label">Terminé</div><div class="kpi-val"><?= count($grouped['termine']) ?></div></div>
  </div>
</div>

<?php if ($erreur) echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<!-- Kanban 3 colonnes -->
<div class="kanban">

  <!-- COLONNE : En attente -->
  <div class="kanban-col attente">
    <div class="kanban-col-head">
      <div class="kanban-col-title"><i class="fas fa-clock"></i> En attente</div>
      <span class="kanban-col-count"><?= count($grouped['en_attente']) ?></span>
    </div>
    <?php foreach ($grouped['en_attente'] as $o): ?>
      <?= renderKanbanCard($o, $types, $prioriteLabels, $prioriteClasses) ?>
    <?php endforeach; ?>
    <?php if (empty($grouped['en_attente'])): ?>
      <div style="text-align:center;padding:2rem;color:var(--muted);font-size:13px"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:.5rem;opacity:.3"></i>Aucune opération</div>
    <?php endif; ?>
  </div>

  <!-- COLONNE : En cours -->
  <div class="kanban-col cours">
    <div class="kanban-col-head">
      <div class="kanban-col-title"><i class="fas fa-spinner"></i> En cours</div>
      <span class="kanban-col-count"><?= count($grouped['en_cours']) ?></span>
    </div>
    <?php foreach ($grouped['en_cours'] as $o): ?>
      <?= renderKanbanCard($o, $types, $prioriteLabels, $prioriteClasses) ?>
    <?php endforeach; ?>
    <?php if (empty($grouped['en_cours'])): ?>
      <div style="text-align:center;padding:2rem;color:var(--muted);font-size:13px"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:.5rem;opacity:.3"></i>Aucune opération</div>
    <?php endif; ?>
  </div>

  <!-- COLONNE : Terminé -->
  <div class="kanban-col termine">
    <div class="kanban-col-head">
      <div class="kanban-col-title"><i class="fas fa-check-circle"></i> Terminé</div>
      <span class="kanban-col-count"><?= count($grouped['termine']) ?></span>
    </div>
    <?php foreach ($grouped['termine'] as $o): ?>
      <?= renderKanbanCard($o, $types, $prioriteLabels, $prioriteClasses) ?>
    <?php endforeach; ?>
    <?php if (empty($grouped['termine'])): ?>
      <div style="text-align:center;padding:2rem;color:var(--muted);font-size:13px"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:.5rem;opacity:.3"></i>Aucune opération</div>
    <?php endif; ?>
  </div>

</div>

<!-- Formulaire ajout rapide -->
<?php if (can('pipeline.manage')): ?>
<div class="card" style="padding:1.4rem;margin-top:1.4rem">
  <h3 style="font-size:15px;margin-bottom:1rem"><i class="fas fa-plus-circle" style="color:var(--ember)"></i> Ajouter une opération</h3>
  <form method="post" id="addOpForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="form-field">
        <label>Type</label>
        <select name="type_document">
          <?php foreach ($types as $k => $t): ?>
            <option value="<?= $k ?>"><?= e($t['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Référence</label>
        <input name="document_numero" placeholder="ex. PF-0001">
      </div>
      <div class="form-field">
        <label>Client / Fournisseur *</label>
        <input name="client_nom" required placeholder="Nom du client ou fournisseur">
      </div>
      <div class="form-field">
        <label>Montant (F)</label>
        <input name="montant" type="number" step="0.01" value="0">
      </div>
      <div class="form-field">
        <label>Priorité</label>
        <select name="priorite">
          <option value="basse">Basse</option>
          <option value="normale" selected>Normale</option>
          <option value="haute">Haute</option>
        </select>
      </div>
      <div class="form-field">
        <label>Date d'échéance</label>
        <input name="date_echeance" type="date">
      </div>
      <div class="form-field full">
        <label>Notes</label>
        <input name="notes" placeholder="Notes additionnelles...">
      </div>
    </div>
    <button class="btn btn-primary" style="margin-top:.5rem"><i class="fas fa-plus"></i> Ajouter dans la pipeline</button>
  </form>
</div>
<?php endif; ?>

<script>
// Déplacement d'une carte entre colonnes
function moveCard(id, newStatut) {
  const token = sessionStorage.getItem('csrf_token');
  fetch(window.APP_URL + '/api/pipeline_update.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': token},
    body: JSON.stringify({id: id, statut: newStatut})
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) location.reload();
    else alert('Erreur : ' + (d.message || 'impossible de déplacer'));
  })
  .catch(e => alert('Erreur réseau'));
}

function deleteCard(id) {
  if (!confirm('Supprimer cette opération ?')) return;
  const token = sessionStorage.getItem('csrf_token');
  fetch(window.APP_URL + '/api/pipeline_update.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': token},
    body: JSON.stringify({id: id, action: 'delete'})
  })
  .then(r => r.json())
  .then(d => { if (d.ok) location.reload(); })
  .catch(() => {});
}
</script>

<?php layout_footer(); ?>

<?php
/**
 * Helper : rendu HTML d'une carte Kanban (appelé dans le template)
 */
function renderKanbanCard(array $o, array $types, array $prioriteLabels, array $prioriteClasses): string
{
    $t = $types[$o['type_document']] ?? ['label' => $o['type_document'], 'color' => '#888'];
    $priorite = $o['priorite'] ?? 'normale';

    // Déterminer les statuts disponibles pour les flèches
    $allStatuts = ['en_attente', 'en_cours', 'termine'];
    $statutIdx  = array_search($o['statut'], $allStatuts);

    ob_start();
    ?>
    <div class="kanban-card type-<?= $o['type_document'] ?>">
      <div class="kc-top">
        <span class="kc-ref"><?= e($o['document_numero'] ?: '—') ?></span>
        <span class="kc-type" style="background:<?= $t['color'] ?>22;color:<?= $t['color'] ?>"><?= e($t['label']) ?></span>
      </div>
      <div class="kc-client"><?= e($o['client_nom']) ?></div>
      <div class="kc-montant"><?= money($o['montant']) ?></div>
      <div class="kc-foot">
        <span class="kc-badge <?= $prioriteClasses[$priorite] ?? 'kc-badge-normale' ?>"><?= e($prioriteLabels[$priorite] ?? 'Normale') ?></span>
        <span><?= fdate($o['date_echeance']) ?></span>
      </div>
      <?php if (!empty($o['notes'])): ?>
        <div style="font-size:11px;color:var(--muted);margin-top:.3rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($o['notes']) ?>"><?= e($o['notes']) ?></div>
      <?php endif; ?>
      <div class="kc-move">
        <?php if ($statutIdx > 0): ?>
          <button type="button" onclick="moveCard(<?= (int)$o['id'] ?>, '<?= $allStatuts[$statutIdx - 1] ?>')" title="Reculer"><i class="fas fa-arrow-left"></i></button>
        <?php endif; ?>
        <?php if ($statutIdx < 2): ?>
          <button type="button" onclick="moveCard(<?= (int)$o['id'] ?>, '<?= $allStatuts[$statutIdx + 1] ?>')" title="Avancer"><i class="fas fa-arrow-right"></i></button>
        <?php endif; ?>
        <button type="button" onclick="deleteCard(<?= (int)$o['id'] ?>)" title="Supprimer" style="margin-left:auto"><i class="fas fa-trash" style="color:var(--red)"></i></button>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
