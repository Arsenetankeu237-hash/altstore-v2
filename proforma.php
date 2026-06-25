<?php
/**
 * proforma.php — Pro Forma Client.
 *
 *  Onglets :
 *   - Nouveau Pro Forma (formulaire avec lignes dynamiques)
 *   - Historique (liste de tous les proformas)
 *
 *  Bouton "Générer PDF" → api/proforma_pdf.php?id=X
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('proforma.view');
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
            require_permission('proforma.create');

            $clientId   = (int)($_POST['client_id'] ?? 0) ?: null;
            $clientLibre= clean($_POST['client_libre'] ?? '');
            $validite   = (int)($_POST['validite_jours'] ?? 30);
            $notes      = clean($_POST['notes'] ?? '');
            $remise     = (float)str_replace([' ', 'F'], '', (string)($_POST['remise_montant'] ?? 0));
            $tvaPct     = (float)($_POST['tva_pct'] ?? 18);

            // Récupérer nom client
            $clientNom = $clientLibre;
            if ($clientId) {
                $c = fetch_one("SELECT nom, entreprise, telephone, adresse, ville FROM clients WHERE id=? AND boutique_id=?", [$clientId, $bid]);
                if ($c) {
                    $clientNom = $c['entreprise'] ?: $c['nom'];
                    $clientLibre = $clientNom . ' / ' . trim(($c['telephone'] ?? '') . ' ' . ($c['ville'] ?? ''));
                }
            }
            if (trim($clientNom) === '') throw new RuntimeException('Veuillez sélectionner ou saisir un client.');

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

            // Numérotation auto : PF-0001
            $lastNum = fetch_one("SELECT numero FROM proformas WHERE boutique_id=? ORDER BY id DESC LIMIT 1", [$bid]);
            $nextIdx = 1;
            if ($lastNum && preg_match('/PF-(\d+)/', $lastNum['numero'], $m)) {
                $nextIdx = (int)$m[1] + 1;
            }
            $numero = 'PF-' . str_pad((string)$nextIdx, 4, '0', STR_PAD_LEFT);

            $dateCrea = date('Y-m-d');
            $dateVal  = date('Y-m-d', strtotime("+{$validite} days"));

            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO proformas (boutique_id, numero, client_id, client_libre, sous_total, montant_tva, remise_montant, total_ttc, statut, validite_jours, date_creation, date_validite, notes)
                 VALUES (?,?,?,?,?,?,?,?, 'brouillon', ?, ?, ?, ?)"
            )->execute([$bid, $numero, $clientId, $clientLibre, $sousTotal, $montantTva, $remise, $totalTtc, $validite, $dateCrea, $dateVal, $notes]);
            $proformaId = (int)$pdo->lastInsertId();

            $stLigne = $pdo->prepare("INSERT INTO proforma_lignes (proforma_id, article_id, designation, reference, quantite, prix_unitaire, total_ligne) VALUES (?,?,?,?,?,?,?)");
            foreach ($lignes as $l) {
                $stLigne->execute([$proformaId, $l['article_id'], $l['designation'], $l['reference'], $l['quantite'], $l['prix_unitaire'], $l['total_ligne']]);
            }

            // Ajout automatique dans la pipeline
            $pdo->prepare(
                "INSERT INTO pipeline_operations (boutique_id, type_document, document_id, document_numero, client_nom, montant, statut, priorite, utilisateur_id)
                 VALUES (?, 'proforma', ?, ?, ?, ?, 'en_attente', 'normale', ?)"
            )->execute([$bid, $proformaId, $numero, $clientNom, $totalTtc, current_user_id()]);

            $pdo->commit();
            $success = "Pro Forma {$numero} créé (montant : " . money($totalTtc) . ").";

            // Rediriger vers l'historique après création
            redirect(APP_URL . '/proforma.php?tab=historique&ok=' . urlencode($success));
        }

        elseif ($action === 'delete') {
            require_permission('proforma.delete');
            $id = (int)($_POST['id'] ?? 0);
            execute("DELETE FROM proformas WHERE id=? AND boutique_id=?", [$id, $bid]);
            execute("DELETE FROM pipeline_operations WHERE boutique_id=? AND type_document='proforma' AND document_id=?", [$bid, $id]);
            $success = "Pro Forma supprimé.";
        }

        elseif ($action === 'update_statut') {
            require_permission('proforma.edit');
            $id     = (int)($_POST['id'] ?? 0);
            $statut = clean($_POST['statut'] ?? '');
            if (in_array($statut, ['brouillon','envoye','accepte','refuse','expire'], true)) {
                execute("UPDATE proformas SET statut=? WHERE id=? AND boutique_id=?", [$statut, $id, $bid]);
                $success = "Statut mis à jour.";
            }
        }

    } catch (Throwable $e) {
        $erreur = IS_PROD ? 'Une erreur est survenue.' : $e->getMessage();
    }
}

// ---------------- Données pour le formulaire ----------------
$clients  = fetch_all("SELECT id, nom, entreprise, telephone, ville FROM clients WHERE boutique_id=? ORDER BY nom", [$bid]);
$articles = fetch_all("SELECT id, nom_article, reference, prix_vente, tva FROM articles WHERE boutique_id=? ORDER BY nom_article", [$bid]);

// ---------------- Historique ----------------
$proformas = fetch_all(
    "SELECT p.*, c.nom AS client_nom, c.entreprise AS client_ent
     FROM proformas p
     LEFT JOIN clients c ON c.id = p.client_id
     WHERE p.boutique_id=?
     ORDER BY p.id DESC",
    [$bid]
);

if (isset($_GET['ok']) && !$success) $success = $_GET['ok'];
?>
<?php layout_header('Pro Forma — ' . ($bout['nom'] ?? ''), 'proforma'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem">
  <div><h2 style="font-size:20px">📄 Pro Forma Client</h2>
  <p style="color:var(--muted);font-size:13px">Créer des devis pro forma et générer des PDF</p></div>
</div>

<?php if ($success) echo '<div class="flash flash-success"><i class="fas fa-check-circle"></i> ' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<!-- Onglets -->
<div class="tabs">
  <button class="tab <?= $tab!=='historique'?'is-active':'' ?>" onclick="location.href='proforma.php?tab=nouveau'">
    <i class="fas fa-plus-circle"></i> Nouveau Pro Forma
  </button>
  <button class="tab <?= $tab==='historique'?'is-active':'' ?>" onclick="location.href='proforma.php?tab=historique'">
    <i class="fas fa-clock-rotate-left"></i> Historique (<?= count($proformas) ?>)
  </button>
</div>

<!-- ===== ONGLET NOUVEAU ===== -->
<div class="tab-panel <?= $tab!=='historique'?'is-active':'' ?>" id="tab-nouveau">
  <?php if (can('proforma.create')): ?>
  <div class="card" style="padding:1.4rem">
    <form method="post" id="proformaForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <!-- Infos client -->
      <div class="form-grid" style="margin-bottom:1rem">
        <div class="form-field">
          <label>Client existant</label>
          <select name="client_id" id="clientSelect" onchange="fillClientLibre()">
            <option value="0">— Client libre —</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= (int)$c['id'] ?>" data-nom="<?= e(($c['entreprise'] ?: $c['nom']) . ' / ' . trim(($c['telephone'] ?? '') . ' ' . ($c['ville'] ?? ''), ' /')) ?>">
                <?= e($c['entreprise'] ?: $c['nom']) ?> <?= $c['telephone'] ? '('.e($c['telephone']).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Désignation client (libre) *</label>
          <input name="client_libre" id="clientLibre" placeholder="Nom du client / contact" required>
        </div>
        <div class="form-field">
          <label>Validité (jours)</label>
          <input name="validite_jours" type="number" value="30">
        </div>
        <div class="form-field">
          <label>TVA par défaut (%)</label>
          <input name="tva_pct" type="number" step="0.01" value="18" id="tvaPct">
        </div>
      </div>

      <!-- Lignes dynamiques -->
      <h4 style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--gold);margin:1.2rem 0 .6rem">
        <i class="fas fa-list"></i> Articles / Lignes
      </h4>

      <div class="doc-lignes" id="lignesContainer">
        <div class="doc-ligne" style="font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;background:transparent;border:none;padding:.3rem .5rem">
          <div>Désignation</div><div>Référence</div><div>Qté</div><div>P.U. (F)</div><div>Total (F)</div><div></div>
        </div>
        <!-- Lignes ajoutées par JS -->
      </div>

      <button type="button" class="btn btn-ghost btn-sm" onclick="addLigne()"><i class="fas fa-plus"></i> Ajouter une ligne</button>

      <!-- Remise -->
      <div style="margin-top:1rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap">
        <label style="font-size:13px;color:var(--muted)">Remise (F) :</label>
        <input name="remise_montant" type="number" step="0.01" value="0" id="remiseMontant" style="width:140px;padding:8px;background:var(--surf);border:1px solid var(--bd);border-radius:8px;color:var(--text)" oninput="calcTotaux()">
      </div>

      <!-- Totaux -->
      <div class="doc-totaux">
        <div class="tot-row"><span>Sous-total</span><span id="totSousTotal">0 F</span></div>
        <div class="tot-row"><span>Remise</span><span id="totRemise">0 F</span></div>
        <div class="tot-row"><span>TVA</span><span id="totTva">0 F</span></div>
        <div class="tot-row total"><span>TOTAL TTC</span><span id="totTotal">0 F</span></div>
      </div>

      <!-- Notes -->
      <div class="form-field full" style="margin-top:1rem">
        <label>Notes / Conditions</label>
        <textarea name="notes" rows="2" placeholder="Conditions de paiement, délai de livraison..."></textarea>
      </div>

      <button class="btn btn-primary" style="width:100%;margin-top:1rem"><i class="fas fa-save"></i> Enregistrer le Pro Forma</button>
    </form>
  </div>

  <script>
  const ARTICLES = <?= json_encode(array_map(fn($a) => ['id'=>(int)$a['id'],'nom'=>$a['nom_article'],'ref'=>$a['reference'],'pu'=>(float)$a['prix_vente']], $articles)) ?>;

  function fillClientLibre() {
    const sel = document.getElementById('clientSelect');
    const opt = sel.options[sel.selectedIndex];
    const lib = document.getElementById('clientLibre');
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

    // Autocomplétion : si articleId fourni, pré-remplir
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
    return div;
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
      // Ignorer la ligne d'en-tête (sans input)
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

  function formatF(n) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' F';
  }

  // Initialiser avec une ligne vide
  addLigne();
  </script>
  <?php else: ?>
    <div class="empty" style="padding:2rem">Vous n'avez pas la permission de créer des pro forma.</div>
  <?php endif; ?>
</div>

<!-- ===== ONGLET HISTORIQUE ===== -->
<div class="tab-panel <?= $tab==='historique'?'is-active':'' ?>" id="tab-historique">
  <?php if (empty($proformas)): ?>
    <div class="card" style="padding:2.5rem;text-align:center;color:var(--muted)">
      <i class="fas fa-file-circle-question" style="font-size:36px;display:block;margin-bottom:.8rem;opacity:.4"></i>
      Aucun pro forma créé pour le moment.<br>
      <?= can('proforma.create') ? '<a href="proforma.php?tab=nouveau" style="color:var(--ember)">→ Créer le premier pro forma</a>' : '' ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>N° Pro Forma</th><th>Client</th><th>Date</th><th>Valable jusqu'au</th>
            <th>Montant TTC</th><th>Statut</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($proformas as $p):
            $stColors = [
              'brouillon' => 'bg-gray', 'envoye' => 'bg-blue', 'accepte' => 'bg-green',
              'refuse' => 'bg-red', 'expire' => 'bg-amber',
            ];
            $stLabels = [
              'brouillon' => 'Brouillon', 'envoye' => 'Envoyé', 'accepte' => 'Accepté',
              'refuse' => 'Refusé', 'expire' => 'Expiré',
            ];
            $clientDisplay = $p['client_ent'] ?: ($p['client_nom'] ?: $p['client_libre']);
          ?>
            <tr>
              <td><b><?= e($p['numero']) ?></b></td>
              <td><?= e($clientDisplay ?: $p['client_libre']) ?></td>
              <td><?= fdate($p['date_creation']) ?></td>
              <td><?= fdate($p['date_validite']) ?></td>
              <td><b style="color:var(--gold)"><?= money($p['total_ttc']) ?></b></td>
              <td><span class="badge <?= $stColors[$p['statut']] ?? 'bg-gray' ?>"><?= e($stLabels[$p['statut']] ?? $p['statut']) ?></span></td>
              <td>
                <a href="api/proforma_pdf.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="Générer PDF"><i class="fas fa-file-pdf"></i></a>
                <?php if (can('proforma.edit')): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_statut">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <select name="statut" onchange="this.form.submit()" style="padding:4px 6px;background:var(--surf);border:1px solid var(--bd);border-radius:6px;color:var(--text);font-size:11px">
                      <?php foreach ($stLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $p['statut']===$k?'selected':'' ?>><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                <?php endif; ?>
                <?php if (can('proforma.delete')): ?>
                  <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer ce pro forma ?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-danger btn-sm" title="Supprimer"><i class="fas fa-trash"></i></button>
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
