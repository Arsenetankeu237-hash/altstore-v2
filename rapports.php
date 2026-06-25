<?php
/**
 * rapports.php — Rapports consolidés de la boutique active.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('rapports.view');

$bid = active_boutique_id();
$bout = active_boutique();

$debut = $_GET['debut'] ?? date('Y-m-01');
$fin   = $_GET['fin']   ?? date('Y-m-d');

// CA période
$ca = (float)(fetch_one("SELECT COALESCE(SUM(total_ttc),0) m FROM ventes WHERE boutique_id=? AND statut='payee' AND DATE(date_vente) BETWEEN ? AND ?", [$bid, $debut, $fin])['m'] ?? 0);
$nbVentes = (int)(fetch_one("SELECT COUNT(*) c FROM ventes WHERE boutique_id=? AND statut='payee' AND DATE(date_vente) BETWEEN ? AND ?", [$bid, $debut, $fin])['c'] ?? 0);
$panierMoy = $nbVentes > 0 ? $ca / $nbVentes : 0;

// Ventes par mode de paiement
$modes = fetch_all("SELECT mode_paiement, COUNT(*) nb, SUM(total_ttc) total FROM ventes WHERE boutique_id=? AND statut='payee' AND DATE(date_vente) BETWEEN ? AND ? GROUP BY mode_paiement", [$bid, $debut, $fin]);

// Top 10 articles
$top = fetch_all("SELECT vl.designation, SUM(vl.quantite) qte, SUM(vl.total_ligne) ca FROM vente_lignes vl INNER JOIN ventes v ON v.id=vl.vente_id WHERE v.boutique_id=? AND v.statut='payee' AND DATE(v.date_vente) BETWEEN ? AND ? GROUP BY vl.article_id ORDER BY ca DESC LIMIT 10", [$bid, $debut, $fin]);

// CA par jour
$parJour = fetch_all("SELECT DATE(date_vente) d, SUM(total_ttc) m, COUNT(*) nb FROM ventes WHERE boutique_id=? AND statut='payee' AND DATE(date_vente) BETWEEN ? AND ? GROUP BY DATE(date_vente) ORDER BY d", [$bid, $debut, $fin]);

// Valeur stock
$stock = fetch_one("SELECT COUNT(*) nb, COALESCE(SUM(quantite_stock),0) unites, COALESCE(SUM(quantite_stock*prix_achat),0) valeur FROM articles WHERE boutique_id=?", [$bid]);
?>
<?php layout_header('Rapports — ' . ($bout['nom'] ?? ''), 'rapports'); ?>
<h2 style="font-size:20px;margin-bottom:4px">📊 Rapports</h2>
<p style="color:var(--muted);font-size:13px;margin-bottom:1.2rem">Analyse de la période pour <?= e($bout['nom'] ?? '') ?></p>

<form method="get" style="display:flex;gap:.6rem;margin-bottom:1.4rem;flex-wrap:wrap;align-items:end">
  <div class="form-field" style="margin:0"><label>Du</label><input type="date" name="debut" value="<?= e($debut) ?>" style="padding:9px;background:var(--surf);border:1px solid var(--bd);border-radius:9px;color:var(--text)"></div>
  <div class="form-field" style="margin:0"><label>Au</label><input type="date" name="fin" value="<?= e($fin) ?>" style="padding:9px;background:var(--surf);border:1px solid var(--bd);border-radius:9px;color:var(--text)"></div>
  <button class="btn btn-ghost"><i class="fas fa-filter"></i> Filtrer</button>
</form>

<div class="grid grid-4">
  <div class="kpi"><div class="kpi-label">Chiffre d'affaires</div><div class="kpi-val"><?= money($ca) ?></div><div class="kpi-sub"><?= $nbVentes ?> ventes</div></div>
  <div class="kpi gold"><div class="kpi-label">Panier moyen</div><div class="kpi-val"><?= money($panierMoy) ?></div></div>
  <div class="kpi green"><div class="kpi-label">Articles en stock</div><div class="kpi-val"><?= num($stock['nb']) ?></div><div class="kpi-sub"><?= num($stock['unites']) ?> unités</div></div>
  <div class="kpi blue"><div class="kpi-label">Valeur du stock</div><div class="kpi-val"><?= money($stock['valeur']) ?></div></div>
</div>

<div class="grid grid-2" style="margin-top:1.2rem">
  <div class="card">
    <div class="card-h"><h3>💳 Par mode de paiement</h3></div>
    <?php if (empty($modes)): ?><div class="empty" style="padding:1.5rem"><i class="fas fa-inbox"></i><p>Aucune vente sur la période.</p></div>
    <?php else: foreach ($modes as $m): $pct = $ca>0?($m['total']/$ca*100):0; ?>
      <div style="margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span><?= e($m['mode_paiement']) ?> (<?= (int)$m['nb'] ?>)</span><b><?= money($m['total']) ?></b></div>
        <div style="height:8px;background:var(--bg);border-radius:4px;overflow:hidden"><div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--ember),var(--gold))"></div></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <div class="card-h"><h3>🏆 Top 10 articles</h3></div>
    <?php if (empty($top)): ?><div class="empty" style="padding:1.5rem"><i class="fas fa-inbox"></i><p>Aucune donnée.</p></div>
    <?php else: foreach ($top as $i=>$t): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--bd)">
        <b style="color:var(--gold);width:20px"><?= $i+1 ?></b>
        <div style="flex:1;min-width:0;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($t['designation']) ?></div>
        <small style="color:var(--muted)"><?= (int)$t['qte'] ?></small>
        <b style="color:var(--green)"><?= money($t['ca']) ?></b>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="card" style="margin-top:1.2rem">
  <div class="card-h"><h3>📅 CA par jour</h3></div>
  <?php if (empty($parJour)): ?><div class="empty"><i class="fas fa-inbox"></i><p>Aucune vente.</p></div>
  <?php else:
    $maxJ = max(array_column(array_map(fn($x)=>['m'=>$x['m']],$parJour),'m')) ?: 1;
    foreach ($parJour as $j): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:5px 0">
        <small style="width:90px;color:var(--muted)"><?= fdate($j['d']) ?></small>
        <div style="flex:1;height:18px;background:var(--bg);border-radius:4px;overflow:hidden"><div style="height:100%;width:<?= ($j['m']/$maxJ*100) ?>%;background:var(--ember);min-width:2px"></div></div>
        <b style="width:110px;text-align:right"><?= money($j['m']) ?></b>
      </div>
  <?php endforeach; endif; ?>
</div>
<?php layout_footer(); ?>
