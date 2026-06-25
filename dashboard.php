<?php
/**
 * dashboard.php — Vue d'ensemble par boutique active.
 *
 * Tous les indicateurs sont filtrés sur la boutique active (tenancy).
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('dashboard.view');

$bid = active_boutique_id();
$bout = active_boutique();

// ---------------- KPIs (toutes requêtes filtrées par boutique_id) ----------------
$today = date('Y-m-d');

// CA du jour (ventes payées)
$caJour = (float)(fetch_one(
    "SELECT COALESCE(SUM(total_ttc),0) AS m FROM ventes WHERE boutique_id=? AND DATE(date_vente)=? AND statut='payee'",
    [$bid, $today]
)['m'] ?? 0);

// Nombre de ventes du jour
$nbVentes = (int)(fetch_one(
    "SELECT COUNT(*) AS c FROM ventes WHERE boutique_id=? AND DATE(date_vente)=? AND statut='payee'",
    [$bid, $today]
)['c'] ?? 0);

// Nombre d'articles en stock
$nbArticles = (int)(fetch_one("SELECT COUNT(*) AS c FROM articles WHERE boutique_id=?", [$bid])['c'] ?? 0);

// Articles en rupture / stock bas
$ruptures = (int)(fetch_one("SELECT COUNT(*) AS c FROM articles WHERE boutique_id=? AND quantite_stock<=0", [$bid])['c'] ?? 0);
$stockBas = (int)(fetch_one("SELECT COUNT(*) AS c FROM articles WHERE boutique_id=? AND quantite_stock>0 AND quantite_stock<=stock_min", [$bid])['c'] ?? 0);

// Nombre de clients
$nbClients = (int)(fetch_one("SELECT COUNT(*) AS c FROM clients WHERE boutique_id=?", [$bid])['c'] ?? 0);

// Solde total des caisses
$soldeCaisses = (float)(fetch_one(
    "SELECT COALESCE(SUM(c.solde_initial),0) +
            COALESCE((SELECT SUM(montant) FROM caisse_mouvements WHERE boutique_id=? AND type IN('encaissement','transfert_in')),0) -
            COALESCE((SELECT SUM(montant) FROM caisse_mouvements WHERE boutique_id=? AND type IN('decaissement','transfert_out')),0) AS solde
     FROM caisses c WHERE c.boutique_id=?",
    [$bid, $bid, $bid]
)['solde'] ?? 0);

// CA 7 derniers jours (pour mini graph)
$ca7 = fetch_all(
    "SELECT DATE(date_vente) AS d, SUM(total_ttc) AS m
     FROM ventes WHERE boutique_id=? AND statut='payee' AND date_vente >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(date_vente) ORDER BY d",
    [$bid]
);
$map7 = [];
foreach ($ca7 as $r) $map7[$r['d']] = (float)$r['m'];
$serie7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $serie7[] = ['label' => date('D', strtotime($d)), 'val' => $map7[$d] ?? 0];
}

// Dernières ventes
$dernieresVentes = fetch_all(
    "SELECT v.id, v.numero, v.client_libre, v.total_ttc, v.mode_paiement, v.date_vente
     FROM ventes v WHERE v.boutique_id=? ORDER BY v.date_vente DESC LIMIT 6",
    [$bid]
);

// Top articles
$topArticles = fetch_all(
    "SELECT vl.designation, vl.reference, SUM(vl.quantite) AS qte, SUM(vl.total_ligne) AS ca
     FROM vente_lignes vl
     INNER JOIN ventes v ON v.id=vl.vente_id
     WHERE v.boutique_id=? AND v.statut='payee'
     GROUP BY vl.article_id ORDER BY ca DESC LIMIT 5",
    [$bid]
);

$maxCa7 = max(array_column(array_map(fn($s) => ['v'=>$s['val']], $serie7), 'v') + [1]) ?: 1;
?>
<?php layout_header('Tableau de bord — ' . ($bout['nom'] ?? ''), 'dashboard'); ?>

<!-- KPIs -->
<div class="grid grid-4">
  <div class="kpi"><div class="kpi-ic bg-green"><i class="fas fa-money-bill-trend-up"></i></div>
    <div class="kpi-label">CA du jour</div><div class="kpi-val"><?= money($caJour) ?></div>
    <div class="kpi-sub"><?= $nbVentes ?> vente(s)</div></div>

  <div class="kpi gold"><div class="kpi-ic bg-amber"><i class="fas fa-cash-register"></i></div>
    <div class="kpi-label">Solde caisses</div><div class="kpi-val"><?= money($soldeCaisses) ?></div>
    <div class="kpi-sub"><a href="caisses.php" style="color:var(--gold)">Voir les caisses →</a></div></div>

  <div class="kpi blue"><div class="kpi-ic bg-blue"><i class="fas fa-boxes-stacked"></i></div>
    <div class="kpi-label">Articles en stock</div><div class="kpi-val"><?= num($nbArticles) ?></div>
    <div class="kpi-sub"><?= $ruptures ?> rupture(s) · <?= $stockBas ?> stock bas</div></div>

  <div class="kpi"><div class="kpi-ic bg-blue"><i class="fas fa-users"></i></div>
    <div class="kpi-label">Clients</div><div class="kpi-val"><?= num($nbClients) ?></div>
    <div class="kpi-sub"><a href="clients.php" style="color:var(--blue)">Gérer →</a></div></div>
</div>

<!-- Graph CA 7 jours + top articles -->
<div class="grid grid-2" style="margin-top:1rem">
  <div class="card">
    <div class="card-h"><h3><i class="fas fa-chart-line" style="color:var(--ember)"></i> Ventes — 7 derniers jours</h3></div>
    <div style="display:flex;align-items:flex-end;gap:10px;height:160px;padding-top:1rem">
      <?php foreach ($serie7 as $s): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
          <div style="width:100%;background:linear-gradient(180deg,var(--ember),rgba(217,79,26,.4));border-radius:6px 6px 0 0;height:<?= max(4, ($s['val']/$maxCa7)*140) ?>px" title="<?= money($s['val']) ?>"></div>
          <small style="font-size:10px;color:var(--muted)"><?= e($s['label']) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-h"><h3><i class="fas fa-trophy" style="color:var(--gold)"></i> Top articles</h3>
      <a href="articles.php" class="btn btn-ghost btn-sm">Tout voir</a></div>
    <?php if (empty($topArticles)): ?>
      <div class="empty" style="padding:1.5rem"><i class="fas fa-chart-simple"></i><p>Aucune vente enregistrée pour le moment.</p></div>
    <?php else: ?>
      <?php foreach ($topArticles as $i => $a): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--bd)">
          <div class="avatar" style="width:28px;height:28px;font-size:11px;background:<?= ['var(--gold)','var(--ember)','var(--blue)','#666','#444'][$i] ?? '#444' ?>"><?= $i+1 ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($a['designation']) ?></div>
            <small style="color:var(--muted)"><?= (int)$a['qte'] ?> vendus</small>
          </div>
          <b style="color:var(--green)"><?= money($a['ca']) ?></b>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Dernières ventes -->
<div class="card" style="margin-top:1rem">
  <div class="card-h"><h3><i class="fas fa-receipt" style="color:var(--ember)"></i> Dernières ventes</h3>
    <a href="ventes.php" class="btn btn-ghost btn-sm">Point de vente</a></div>
  <?php if (empty($dernieresVentes)): ?>
    <div class="empty"><i class="fas fa-cart-shopping"></i><h3>Aucune vente</h3>
      <p>Dirigez-vous vers le point de vente pour enregistrer votre première vente.</p>
      <a href="ventes.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle vente</a></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>N°</th><th>Client</th><th>Mode</th><th>Montant</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($dernieresVentes as $v): ?>
            <tr>
              <td><b><?= e($v['numero']) ?></b></td>
              <td><?= e($v['client_libre']) ?></td>
              <td><span class="badge bg-gray"><?= e($v['mode_paiement']) ?></span></td>
              <td><b style="color:var(--green)"><?= money($v['total_ttc']) ?></b></td>
              <td style="color:var(--muted)"><?= fdatetime($v['date_vente']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php layout_footer(); ?>
