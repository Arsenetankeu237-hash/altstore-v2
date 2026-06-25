<?php
/**
 * bon_commande.php — Bon de Commande Fournisseur.
 *
 *  Onglets :
 *   - Nouveau Bon de Commande (formulaire avec lignes dynamiques)
 *   - Historique (liste de tous les bons de commande)
 *
 *  Bouton "Générer PDF" → api/bon_commande_pdf.php?id=X
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('bon_commande.view');
require_boutique();

$bid     = active_boutique_id();
$bout    = active_boutique();
$erreur  = '';
$success = '';
$tab     = $_GET['tab'] ?? 'nouveau';

// ---------------- Traitement ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        $pdo = db();

        if ($action === 'create') {
            require_permission('bon_commande.create');

            $fournisseurId   = (int)($_POST['fournisseur_id'] ?? 0) ?: null;
            $fournisseurLibre= clean($_POST['fournisseur_libre'] ?? '');
            $dateLivraison   = clean($_POST['date_livraison_prevue'] ?? '');
            $notes           = clean($_POST['notes'] ?? '');
            $remise          = (float)str_replace([' ', 'F'], '', (string)($_POST['remise_montant'] ?? 0));
            $tvaPct          = (float)($_POST['tva_pct'] ?? 18);

            // Récupérer nom fournisseur
            $fournisseurNom = $fournisseurLibre;
            if ($fournisseurId) {
                $f = fetch_one("SELECT nom, telephone, adresse, ville FROM fournisseurs WHERE id=? AND boutique_id=?", [$fournisseurId, $bid]);
                if ($f) {
                    $fournisseurNom = $f['nom'];
                    $fournisseurLibre = $f['nom'] . ' / ' . trim(($f['telephone'] ?? '') . ' ' . ($f['ville'] ?? ''), ' /');
                }
            }
            if (trim($fournisseurNom) === '') throw new RuntimeException('Veuillez sélectionner ou saisir un fournisseur.');

            // Lignes
            $designations = $_POST['designation'] ?? [];
            $refs         = $_POST['reference'] ?? [];
            $qtes         = $_POST['quantite'] ?? [];
            $pus          = $_POST['prix_unitaire'] ?? [];
            $articleIds   = $_POST['article_id'] ?? [];

            $lignes = [];
            for ($i = 0; $i < count($designations); $i++) {
                $des = clean($designations[$i] ?? '');
                if ($des === '') continue;
                $qte = (float)str_replace(',', '.', (string)($qtes[$i] ?? 1));
                $pu  = (float)str_replace([' ', 'F'], '', (string)($pus[$i] ?? 0));
                if ($qte <= 0 || $pu <= 0) continue;
                $lignes[] = [
                    'article_id'    => (int)($articleIds[$i] ?? 0) ?: null,
                    'designation'   => $des,
                    'reference'     => clean($refs[$i] ?? ''),
                    'quantite'      => $qte,
                    'prix_unitaire' => $pu,
                    'total_ligne'   => $qte * $pu,
                ];
            }
            if (empty($lignes)) throw new RuntimeException('Ajoutez au moins une ligne valide.');

            $sousTotal = array_sum(array_column($lignes, 'total_ligne'));
            $baseApresRemise = max(0, $sousTotal - $remise);
            $montantTva = $baseApresRemise * ($tvaPct / 100);
            $totalTtc   = $baseApresRemise + $montantTva;

            // Numérotation auto : BC-0001
            $lastNum = fetch_one("SELECT numero FROM bon_commandes WHERE boutique_id=? ORDER BY id DESC LIMIT 1", [$bid]);
            $nextIdx = 1;
            if ($lastNum && preg_match('/BC-(\d+)/', $lastNum['numero'], $m)) {
                $nextIdx = (int)$m[1] + 1;
            }
            $numero = 'BC-' . str_pad((string)$nextIdx, 4, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO bon_commandes (boutique_id, numero, fournisseur_id, fournisseur_libre, sous_total, montant_tva, remise_montant, total_ttc, statut, date_commande, date_livraison_prevue, notes)
                 VALUES (?,?,?,?,?,?,?, 'brouillon', ?, ?, ?)"
            )->execute([$bid, $numero, $fournisseurId, $fournisseurLibre, $sousTotal, $montantTva, $remise, $totalTtc, date('Y-m-d'), $dateLivraison ?: null, $notes]);
            $bcId = (int)$pdo->lastInsertId();

            $stLigne = $pdo->prepare("INSERT INTO bon_commande_lignes (bon_commande_id, article_id, designation, reference, quantite, prix_unitaire, total_ligne) VALUES (?,?,?,?,?,?,?)");
            foreach ($lignes as $l) {
                $stLigne->execute([$bcId, $l['article_id'], $l['designation'], $l['reference'], $l['quantite'], $l['prix_unitaire'], $l['total_ligne']]);
            }

            // Ajout dans la pipeline
            $pdo->prepare(
                "INSERT INTO pipeline_operations (boutique_id, type_document, document_id, document_numero, client_nom, montant, statut, priorite, utilisateur_id)
                 VALUES (?, 'bon_commande', ?, ?, ?, ?, 'en_attente', 'normale', ?)"
            )->execute([$bid, $bcId, $numero, $fournisseurNom, $totalTtc, current_user_id()]);

            $pdo->commit();
            $success = "Bon de commande {$numero} créé (montant : " . money($totalTtc) . ").";
            redirect(APP_URL . '/bon_commande.php?tab=historique&ok=' . urlencode($success));
        }

        elseif ($action === 'delete') {
            require_permission('bon_commande.delete');
            $id = (int)($_POST['id'] ?? 0);
            execute("DELETE FROM bon_commandes WHERE id=? AND boutique_id=?", [$id, $bid]);
            execute("DELETE FROM pipeline_operations WHERE boutique_id=? AND type_document='bon_commande' AND document_id=?", [$bid, $id]);
            $success = "Bon de commande supprimé.";
        }

        elseif ($action === 'update_statut') {
            require_permission('bon_commande.edit');
            $id     = (int)($_POST['id'] ?? 0);
            $statut = clean($_POST['statut'] ?? '');
            if (in_array($statut, ['brouillon','envoye','confirme','recu','annule'], true)) {
                execute("UPDATE bon_commandes SET statut=? WHERE id=? AND boutique_id=?", [$statut, $id, $bid]);
                $success = "Statut mis à jour.";
            }
        }

    } catch (Throwable $e) {
        $erreur = IS_PROD ? 'Une erreur est survenue.' : $e->getMessage();
    }
}

// ---------------- Données ----------------
$fournisseurs = fetch_all("SELECT id, nom, telephone, ville FROM fournisseurs WHERE boutique_id=? ORDER BY nom", [$bid]);
$articles     = fetch_all("SELECT id, nom_article, reference, prix_achat, tva FROM articles WHERE boutique_id=? ORDER BY nom_article", [$bid]);

// ---------------- Historique ----------------
$bons = fetch_all(
    "SELECT bc.*, f.nom AS fournisseur_nom
     FROM bon_commandes bc
     LEFT JOIN fournisseurs f ON f.id = bc.fournisseur_id
     WHERE bc.boutique_id=?
     ORDER BY bc.id DESC",
    [$bid]
);

if (isset($_GET['ok']) && !$success) $success = $_GET['ok'];
?>
<?php layout_header('Bon de Commande — ' . ($bout['nom'] ?? ''), 'bon_commande'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem">
  <div><h2 style="font-size:20px">📋 Bon de Commande</h2>
  <p style="color:var(--muted);font-size:13px">Commandes fournisseurs avec génération PDF</p></div>
</div>

<?php if ($success) echo '<div class="flash flash-success"><i class="fas fa-check-circle"></i> ' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<div class="tabs">
  <button class="tab <?= $tab!=='historique'?'is-active':'' ?>" onclick="location.href='bon_commande.php?tab=nouveau'">
    <i class="fas fa-plus-circle"></i> Nouveau Bon de Commande
  </button>
  <button class="tab <?= $tab==='historique'?'is-active':'' ?>" onclick="location.href='bon_commande.php?tab=historique'">
    <i class="fas fa-clock-rotate-left"></i> Historique (<?= count($bons) ?>)
  </button>
</div>

<!-- ===== ONGLET NOUVEAU ===== -->
<div class="tab-panel <?= $tab!=='historique'?'is-active':'' ?>">
  <?php if (can('bon_commande.create')): ?>
  <div class="card" style="padding:1.4rem">
    <form method="post" id="bcForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="form-grid" style="margin-bottom:1rem">
        <div class="form-field">
          <label>Fournisseur existant</label>
          <select name="fournisseur_id" id="fourSelect" onchange="fillFourLibre()">
            <option value="0">— Fournisseur libre —</option>
            <?php foreach ($fournisseurs as $f): ?>
              <option value="<?= (int)$f['id'] ?>" data-nom="<?= e($f['nom'] . ' / ' . trim(($f['telephone'] ?? '') . ' ' . ($f['ville'] ?? ''), ' /')) ?>">
                <?= e($f['nom']) ?> <?= $f['telephone'] ? '('.e($f['telephone']).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Désignation fournisseur (libre) *</label>
          <input name="fournisseur_libre" id="fourLibre" placeholder="Nom du fournisseur" required>
        </div>
        <div class="form-field">
          <label>Date livraison prévue</label>
          <input name="date_livraison_prevue" type="date">
        </div>
        <div class="form-field">
          <label>TVA par défaut (%)</label>
          <input name="tva_pct" type="number" step="0.01" value="18" id="tvaPct">
        </div>
      </div>

      <h4 style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--gold);margin:1.2rem 0 .6rem">
        <i class="fas fa-list"></i> Articles / Lignes
      </h4>

      <div class="doc-lignes" id="lignesContainer">
        <div class="doc-ligne" style="font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;background:transparent;border:none;padding:.3rem .5rem">
          <div>Désignation</div><div>Référence</div><div>Qté</div><div>P.U. (F)</div><div>Total (F)</div><div></div>
        </div>
      </div>

      <button type="button" class="btn btn-ghost btn-sm" onclick="addLigne()"><i class="fas fa-plus"></i> Ajouter une ligne</button>

      <div style="margin-top:1rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap">
        <label style="font-size:13px;color:var(--muted)">Remise (F) :</label>
        <input name="remise_montant" type="number" step="0.01" value="0" id="remiseMontant" style="width:140px;padding:8px;background:var(--surf);border:1px solid var(--bd);border-radius:8px;color:var(--text)" oninput="calcTotaux()">
      </div>

      <div class="doc-totaux">
        <div class="tot-row"><span>Sous-total</span><span id="totSousTotal">0 F</span></div>
        <div class="tot-row"><span>Remise</span><span id="totRemise">0 F</span></div>
        <div class="tot-row"><span>TVA</span><span id="totTva">0 F</span></div>
        <div class="tot-row total"><span>TOTAL TTC</span><span id="totTotal">0 F</span></div>
      </div>

      <div class="form-field full" style="margin-top:1rem">
        <label>Notes</label>
        <textarea name="notes" rows="2" placeholder="Détails, conditions..."></textarea>
      </div>

      <button class="btn btn-primary" style="width:100%;margin-top:1rem"><i class="fas fa-save"></i> Enregistrer le Bon de Commande</button>
    </form>
  </div>

  <script>
  const ARTICLES = <?= json_encode(array_map(fn($a) => ['id'=>(int)$a['id'],'nom'=>$a['nom_article'],'ref'=>$a['reference'],'pu'=>(float)$a['prix_achat']], $articles)) ?>;

  function fillFourLibre() {
    const sel = document.getElementById('fourSelect');
    const opt = sel.options[sel.selectedIndex];
    const lib = document.getElementById('fourLibre');
    if (opt.value !== '0' && opt.dataset.nom) lib.value = opt.dataset.nom;
  }

  function addLigne(articleId = null) {
    const c = document.getElementById('lignesContainer');
    const div = document.createElement('div');
    div.className = 'doc-ligne';
    div.innerHTML = `
      <input type="text" name="designation[]" placeholder="Désignation" required oninput="calcLigne(this)">
      <input type="text" name="reference[]" placeholder="Réf.">
      <input type="number" name="quantite[]" value="1" step="0.001" min="0" oninput="calcLigne(this)">
      <input type="number" name="prix_unitaire[]" value="0" step="0.01" min="0" oninput="calcLigne(this)">
      <div class="ligne-total">0 F</div>
      <button type="button" class="btn-remove" onclick="this.parentElement.remove(); calcTotaux()"><i class="fas fa-times"></i></button>
      <input type="hidden" name="article_id[]" value="">
    `;
    c.appendChild(div);
    if (articleId) {
      const art = ARTICLES.find(a => a.id === articleId);
      if (art) {
        div.querySelector('[name="designation[]"]').value = art.nom;
        div.querySelector('[name="reference[]"]').value = art.ref;
        div.querySelector('[name="prix_unitaire[]"]').value = art.pu;
        div.querySelector('[name="article_id[]"]').value = art.id;
        calcLigne(div.querySelector('[name="prix_unitaire[]"]'));
      }
    }
  }

  function calcLigne(input) {
    const ligne = input.closest('.doc-ligne');
    const qte = parseFloat(ligne.querySelector('[name="quantite[]"]').value) || 0;
    const pu  = parseFloat(ligne.querySelector('[name="prix_unitaire[]"]').value) || 0;
    ligne.querySelector('.ligne-total').textContent = formatF(qte * pu);
    calcTotaux();
  }

  function calcTotaux() {
    let sousTotal = 0;
    document.querySelectorAll('#lignesContainer .doc-ligne').forEach(l => {
      const qteInput = l.querySelector('[name="quantite[]"]');
      if (!qteInput) return;
      const qte = parseFloat(qteInput.value) || 0;
      const pu  = parseFloat(l.querySelector('[name="prix_unitaire[]"]').value) || 0;
      sousTotal += qte * pu;
    });
    const remise = parseFloat(document.getElementById('remiseMontant').value) || 0;
    const tvaPct = parseFloat(document.getElementById('tvaPct').value) || 0;
    const base = Math.max(0, sousTotal - remise);
    const tva = base * (tvaPct / 100);
    const total = base + tva;
    document.getElementById('totSousTotal').textContent = formatF(sousTotal);
    document.getElementById('totRemise').textContent = formatF(remise);
    document.getElementById('totTva').textContent = formatF(tva);
    document.getElementById('totTotal').textContent = formatF(total);
  }

  function formatF(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' F'; }
  addLigne();
  </script>
  <?php else: ?>
    <div class="empty" style="padding:2rem">Vous n'avez pas la permission de créer des bons de commande.</div>
  <?php endif; ?>
</div>

<!-- ===== ONGLET HISTORIQUE ===== -->
<div class="tab-panel <?= $tab==='historique'?'is-active':'' ?>">
  <?php if (empty($bons)): ?>
    <div class="card" style="padding:2.5rem;text-align:center;color:var(--muted)">
      <i class="fas fa-file-circle-question" style="font-size:36px;display:block;margin-bottom:.8rem;opacity:.4"></i>
      Aucun bon de commande.<br>
      <?= can('bon_commande.create') ? '<a href="bon_commande.php?tab=nouveau" style="color:var(--ember)">→ Créer le premier</a>' : '' ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>N° BC</th><th>Fournisseur</th><th>Date</th><th>Livraison prévue</th><th>Montant TTC</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($bons as $b):
            $stColors = ['brouillon'=>'bg-gray','envoye'=>'bg-blue','confirme'=>'bg-green','recu'=>'bg-amber','annule'=>'bg-red'];
            $stLabels = ['brouillon'=>'Brouillon','envoye'=>'Envoyé','confirme'=>'Confirmé','recu'=>'Reçu','annule'=>'Annulé'];
          ?>
            <tr>
              <td><b><?= e($b['numero']) ?></b></td>
              <td><?= e($b['fournisseur_nom'] ?: $b['fournisseur_libre']) ?></td>
              <td><?= fdate($b['date_commande']) ?></td>
              <td><?= fdate($b['date_livraison_prevue']) ?></td>
              <td><b style="color:var(--gold)"><?= money($b['total_ttc']) ?></b></td>
              <td><span class="badge <?= $stColors[$b['statut']] ?? 'bg-gray' ?>"><?= e($stLabels[$b['statut']] ?? $b['statut']) ?></span></td>
              <td>
                <a href="api/bon_commande_pdf.php?id=<?= (int)$b['id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="PDF"><i class="fas fa-file-pdf"></i></a>
                <?php if (can('bon_commande.edit')): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_statut"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <select name="statut" onchange="this.form.submit()" style="padding:4px 6px;background:var(--surf);border:1px solid var(--bd);border-radius:6px;color:var(--text);font-size:11px">
                      <?php foreach ($stLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $b['statut']===$k?'selected':'' ?>><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                <?php endif; ?>
                <?php if (can('bon_commande.delete')): ?>
                  <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer ?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>
