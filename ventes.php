<?php
/**
 * ventes.php — Point de vente (POS) de la boutique active.
 *
 *  - Recherche d'articles (par nom / réf / code-barre)
 *  - Panier en session
 *  - Validation = création vente + lignes + déstockage + encaissement caisse + impression
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('ventes.view');

$bid = active_boutique_id();
$bout = active_boutique();
$erreur = $success = '';
$lastVente = null;

// ---------------- Validation de la vente ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'valider') {
        require_permission('ventes.create');
        try {
            $pdo = db();
            $panier = $_SESSION['panier'][$bid] ?? [];
            if (empty($panier)) throw new RuntimeException('Le panier est vide');

            $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
            $clientLibre = clean($_POST['client_libre'] ?? 'Client libre');
            $modePaie = clean($_POST['mode_paiement'] ?? 'cash');
            $caisseId = (int)($_POST['caisse_id'] ?? 0);
            $recu = (float)str_replace([' ', 'F'], '', (string)($_POST['montant_recu'] ?? '0'));
            $remise = (float)str_replace([' ', 'F'], '', (string)($_POST['remise'] ?? '0'));

            // Caisse par défaut si non choisie
            if ($caisseId === 0) {
                $def = fetch_one("SELECT id FROM caisses WHERE boutique_id=? AND is_default=1", [$bid]);
                $caisseId = $def ? (int)$def['id'] : 0;
            }

            // Calculs
            $sousTotal = 0; $tva = 0;
            foreach ($panier as $item) {
                $lig = $item['prix'] * $item['qte'];
                $sousTotal += $lig;
                $tva += $lig * ($item['tva'] / 100);
            }
            $totalTtc = $sousTotal + $tva - $remise;
            $monnaie = max(0, $recu - $totalTtc);

            $pdo->beginTransaction();

            // Génération n°
            $nbAuj = (int)$pdo->query("SELECT COUNT(*) FROM ventes WHERE boutique_id=$bid")->fetchColumn();
            $numero = 'CMD-' . date('ym') . '-' . str_pad((string)($nbAuj + 1), 4, '0', STR_PAD_LEFT);

            $st = $pdo->prepare(
                "INSERT INTO ventes (boutique_id, caisse_id, numero, client_id, client_libre, vendeur_id, sous_total, montant_tva, remise_montant, total_ttc, montant_recu, monnaie_rendue, mode_paiement, statut)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'payee')"
            );
            $st->execute([$bid, $caisseId ?: null, $numero, $clientId, $clientLibre, current_user_id(), $sousTotal, $tva, $remise, $totalTtc, $recu, $monnaie, $modePaie]);
            $venteId = (int)$pdo->lastInsertId();

            // Lignes + déstockage
            $stLig = $pdo->prepare("INSERT INTO vente_lignes (vente_id, article_id, designation, reference, quantite, prix_unitaire, total_ligne) VALUES (?,?,?,?,?,?,?)");
            $stStock = $pdo->prepare("UPDATE articles SET quantite_stock = quantite_stock - ? WHERE id=? AND boutique_id=?");
            $stMvt = $pdo->prepare("INSERT INTO mouvements_stock (boutique_id, article_id, type, quantite, quantite_avant, reference, utilisateur_id, motif) VALUES (?,?,'sortie',?,?,?,?,?)");
            foreach ($panier as $item) {
                $totalLig = $item['prix'] * $item['qte'];
                $stLig->execute([$venteId, $item['id'], $item['nom'], $item['ref'], $item['qte'], $item['prix'], $totalLig]);
                $avant = (int)fetch_one("SELECT quantite_stock q FROM articles WHERE id=?", [$item['id']])['q'];
                $stStock->execute([$item['qte'], $item['id'], $bid]);
                $stMvt->execute([$bid, $item['id'], $item['qte'], $avant, $numero, current_user_id(), 'Vente ' . $numero]);

                // Mise à jour statut stock
                $nvQ = $avant - $item['qte'];
                $stStatut = $pdo->prepare("UPDATE articles SET statut=? WHERE id=?");
                $minStock = (int)fetch_one("SELECT stock_min m FROM articles WHERE id=?", [$item['id']])['m'];
                $stStatut->execute([$nvQ <= 0 ? 'out_of_stock' : ($nvQ <= $minStock ? 'low_stock' : 'active'), $item['id']]);
            }

            // Encaissement en caisse
            if ($caisseId) {
                $pdo->prepare("INSERT INTO caisse_mouvements (caisse_id, boutique_id, type, montant, mode_paiement, reference, utilisateur_id, motif) VALUES (?,?,'encaissement',?,?,?,?,'Vente')")
                    ->execute([$caisseId, $bid, $totalTtc, $modePaie, $numero, current_user_id()]);
            }

            $pdo->commit();

            // Vider le panier
            unset($_SESSION['panier'][$bid]);

            $success = "Vente {$numero} enregistrée. Total : " . money($totalTtc);
            $lastVente = ['id'=>$venteId, 'numero'=>$numero, 'total'=>$totalTtc, 'recu'=>$recu, 'monnaie'=>$monnaie];

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $erreur = IS_PROD ? 'Erreur lors de la vente.' : $e->getMessage();
        }
    }

    elseif ($action === 'clear') {
        unset($_SESSION['panier'][$bid]);
    }
}

// ---------------- Actions panier (GET) ----------------
if (isset($_GET['add'])) {
    $artId = (int)$_GET['add'];
    $a = fetch_one("SELECT id, nom_article, reference, prix_vente, quantite_stock, tva FROM articles WHERE id=? AND boutique_id=?", [$artId, $bid]);
    if ($a && $a['quantite_stock'] > 0) {
        if (!isset($_SESSION['panier'][$bid])) $_SESSION['panier'][$bid] = [];
        $found = false;
        foreach ($_SESSION['panier'][$bid] as &$p) {
            if ($p['id'] === $artId) { $p['qte']++; $found = true; break; }
        }
        unset($p);
        if (!$found) {
            $_SESSION['panier'][$bid][] = ['id'=>(int)$a['id'],'nom'=>$a['nom_article'],'ref'=>$a['reference'],'prix'=>(float)$a['prix_vente'],'qte'=>1,'tva'=>(float)$a['tva']];
        }
    }
    redirect(APP_URL . '/ventes.php');
}
if (isset($_GET['plus']))  { foreach ($_SESSION['panier'][$bid] ?? [] as &$p) if ($p['id']===(int)$_GET['plus']) $p['qte']++; unset($p); redirect(APP_URL.'/ventes.php'); }
if (isset($_GET['moins'])) { foreach ($_SESSION['panier'][$bid] ?? [] as &$p) if ($p['id']===(int)$_GET['moins']) { $p['qte']--; if ($p['qte']<=0) $_SESSION['panier'][$bid]=array_values(array_filter($_SESSION['panier'][$bid], fn($x)=>$x['id']!==(int)$_GET['moins'])); } unset($p); redirect(APP_URL.'/ventes.php'); }
if (isset($_GET['del']))   { $_SESSION['panier'][$bid]=array_values(array_filter($_SESSION['panier'][$bid] ?? [], fn($x)=>$x['id']!==(int)$_GET['del'])); redirect(APP_URL.'/ventes.php'); }

// ---------------- Données ----------------
$search = clean($_GET['q'] ?? '');
$produits = [];
if ($search !== '') {
    $kw = "%$search%";
    $produits = fetch_all("SELECT id, nom_article, reference, code_barre, prix_vente, quantite_stock, marque FROM articles WHERE boutique_id=? AND statut<>'out_of_stock' AND (nom_article LIKE ? OR reference LIKE ? OR code_barre LIKE ?) ORDER BY nom_article LIMIT 30", [$bid, $kw, $kw, $kw]);
} else {
    $produits = fetch_all("SELECT id, nom_article, reference, code_barre, prix_vente, quantite_stock, marque FROM articles WHERE boutique_id=? AND statut<>'out_of_stock' ORDER BY nom_article LIMIT 24", [$bid]);
}
$panier = $_SESSION['panier'][$bid] ?? [];
$caisses = fetch_all("SELECT id, nom, code_caisse FROM caisses WHERE boutique_id=? AND is_active=1 ORDER BY is_default DESC", [$bid]);
$clients = fetch_all("SELECT id, nom, telephone FROM clients WHERE boutique_id=? ORDER BY nom LIMIT 100", [$bid]);

$totPanier = 0; foreach ($panier as $p) $totPanier += $p['prix'] * $p['qte'];

// Historique court
$recent = fetch_all("SELECT id, numero, client_libre, total_ttc, date_vente, statut FROM ventes WHERE boutique_id=? ORDER BY date_vente DESC LIMIT 8", [$bid]);
?>
<?php layout_header('Point de vente — ' . ($bout['nom'] ?? ''), 'ventes'); ?>

<?php if ($success) echo '<div class="flash flash-success"><i class="fas fa-check-circle"></i> ' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:1.2rem;align-items:start" class="pos-layout">
  <!-- Colonne gauche : catalogue -->
  <div>
    <form method="get" style="margin-bottom:1rem">
      <input name="q" value="<?= e($search) ?>" placeholder="🔎 Scanner ou rechercher un article..." autofocus style="width:100%;padding:13px 16px;background:var(--surf);border:1px solid var(--bd);border-radius:11px;color:var(--text);font-size:14px">
    </form>

    <div class="grid grid-3" id="gridProd" style="gap:.8rem">
      <?php if (empty($produits)): ?>
        <div class="empty" style="grid-column:1/-1"><i class="fas fa-box-open"></i><p>Aucun article disponible. <?= $search?'Affinez votre recherche.':'Ajoutez des articles d\'abord.' ?></p></div>
      <?php else: foreach ($produits as $p): ?>
        <a href="ventes.php?add=<?= (int)$p['id'] ?>" class="caisse-card" style="text-decoration:none;display:block;cursor:pointer">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:40px;height:40px;border-radius:10px;background:var(--ember-soft);color:var(--ember);display:flex;align-items:center;justify-content:center"><i class="fas fa-box"></i></div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($p['nom_article']) ?></div>
              <small style="color:var(--muted)"><?= e($p['reference']) ?> · stock <?= (int)$p['quantite_stock'] ?></small>
            </div>
          </div>
          <div style="margin-top:.7rem;font-weight:700;color:var(--green)"><?= money($p['prix_vente']) ?></div>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Colonne droite : panier -->
  <div class="card" style="position:sticky;top:80px">
    <div class="card-h"><h3><i class="fas fa-cart-shopping" style="color:var(--ember)"></i> Panier (<?= count($panier) ?>)</h3>
      <?php if (!empty($panier) && can('ventes.create')): ?>
        <form method="post" style="display:inline"><?= csrf_field() ?><button name="action" value="clear" class="btn btn-ghost btn-sm"><i class="fas fa-trash"></i></button></form>
      <?php endif; ?>
    </div>

    <?php if (empty($panier)): ?>
      <div class="empty" style="padding:1.5rem"><i class="fas fa-cart-arrow-down"></i><p>Panier vide.<br>Cliquez sur un article pour l'ajouter.</p></div>
    <?php else: ?>
      <div style="max-height:280px;overflow-y:auto;margin-bottom:1rem">
        <?php foreach ($panier as $p): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--bd)">
            <div style="flex:1;min-width:0">
              <div style="font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($p['nom']) ?></div>
              <small style="color:var(--muted)"><?= money($p['prix']) ?> × <?= (int)$p['qte'] ?></small>
            </div>
            <div style="display:flex;gap:2px;align-items:center">
              <a href="ventes.php?moins=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px">−</a>
              <b style="min-width:24px;text-align:center"><?= (int)$p['qte'] ?></b>
              <a href="ventes.php?plus=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px">+</a>
              <a href="ventes.php?del=<?= (int)$p['id'] ?>" class="btn btn-danger btn-sm" style="padding:4px 8px"><i class="fas fa-times"></i></a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (can('ventes.create')): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="valider">

        <div class="form-field" style="margin-bottom:.6rem"><label>Client</label>
          <select name="client_id"><option value="0">Client libre</option>
            <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nom']) ?> (<?= e($c['telephone'] ?: '—') ?>)</option><?php endforeach; ?>
          </select>
          <input name="client_libre" placeholder="ou nom libre" style="margin-top:6px"></div>

        <div class="form-field" style="margin-bottom:.6rem"><label>Caisse</label>
          <select name="caisse_id"><?php foreach ($caisses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nom']) ?></option><?php endforeach; ?></select></div>

        <div class="grid grid-2" style="gap:.6rem">
          <div class="form-field"><label>Mode</label>
            <select name="mode_paiement"><option value="cash">Espèces</option><option value="mobile">Mobile</option><option value="carte">Carte</option><option value="credit">Crédit</option></select></div>
          <div class="form-field"><label>Remise (F)</label><input name="remise" value="0" type="number"></div>
        </div>

        <div style="background:var(--surf2);border-radius:10px;padding:12px;margin:.8rem 0">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span style="color:var(--muted)">Sous-total</span><b><?= money($totPanier) ?></b></div>
          <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;color:var(--ember)"><span>TOTAL TTC</span><span id="totalDisplay"><?= money($totPanier) ?></span></div>
        </div>

        <div class="form-field" style="margin-bottom:.8rem"><label>Montant reçu (F)</label>
          <input name="montant_recu" type="number" id="montantRecu" placeholder="0" oninput="calcMonnaie()"></div>
        <div id="monnaieDisplay" style="text-align:center;font-size:13px;color:var(--green);margin-bottom:.8rem;min-height:18px"></div>

        <button class="btn btn-primary" style="width:100%;font-size:15px;padding:14px" onclick="return confirm('Confirmer la vente ?')">
          <i class="fas fa-check-circle"></i> Valider la vente</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Historique récent -->
<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3><i class="fas fa-clock-rotate-left" style="color:var(--ember)"></i> Ventes récentes</h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Client</th><th>Montant</th><th>Mode</th><th>Date</th></tr></thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--muted)">Aucune vente.</td></tr>
        <?php else: foreach ($recent as $v): ?>
          <tr><td><b><?= e($v['numero']) ?></b></td><td><?= e($v['client_libre']) ?></td>
            <td><b style="color:var(--green)"><?= money($v['total_ttc']) ?></b></td>
            <td><span class="badge bg-gray"><?= e($v['statut']) ?></span></td>
            <td style="color:var(--muted)"><?= fdatetime($v['date_vente']) ?></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function calcMonnaie(){
  const total = <?= $totPanier ?>;
  const recu = parseFloat(document.getElementById('montantRecu').value) || 0;
  const diff = recu - total;
  const el = document.getElementById('monnaieDisplay');
  if (recu > 0) {
    el.innerText = diff >= 0 ? '💰 Monnaie à rendre : ' + diff.toLocaleString('fr-FR') + ' F' : '⚠️ Insuffisant de ' + Math.abs(diff).toLocaleString('fr-FR') + ' F';
    el.style.color = diff >= 0 ? 'var(--green)' : 'var(--red)';
  } else el.innerText = '';
}
</script>
<style>
@media(max-width:980px){ .pos-layout{grid-template-columns:1fr !important} }
</style>
<?php layout_footer(); ?>
