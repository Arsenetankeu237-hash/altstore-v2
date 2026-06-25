<?php
/**
 * factures.php — Liste des factures de la boutique active.
 *  (Version consultation ; la génération automatique se fait à la vente.)
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('factures.view');

$bid = active_boutique_id();
$bout = active_boutique();

$factures = fetch_all(
    "SELECT f.*, c.nom AS client_nom
     FROM factures f LEFT JOIN clients c ON c.id=f.client_id
     WHERE f.boutique_id=? ORDER BY f.date_facture DESC LIMIT 200",
    [$bid]
);

$stats = [
    'total' => (float)(fetch_one("SELECT COALESCE(SUM(montant_ttc),0) m FROM factures WHERE boutique_id=?", [$bid])['m'] ?? 0),
    'payees' => (float)(fetch_one("SELECT COALESCE(SUM(montant_ttc),0) m FROM factures WHERE boutique_id=? AND statut='payee'", [$bid])['m'] ?? 0),
    'impayees' => (float)(fetch_one("SELECT COALESCE(SUM(montant_ttc - montant_paye),0) m FROM factures WHERE boutique_id=? AND statut IN('impayee','partielle','validee')", [$bid])['m'] ?? 0),
    'nb' => (int)(fetch_one("SELECT COUNT(*) c FROM factures WHERE boutique_id=?", [$bid])['c'] ?? 0),
];
?>
<?php layout_header('Factures — ' . ($bout['nom'] ?? ''), 'factures'); ?>
<h2 style="font-size:20px;margin-bottom:4px">🧾 Factures</h2>
<p style="color:var(--muted);font-size:13px;margin-bottom:1.2rem">Factures émises par <?= e($bout['nom'] ?? '') ?></p>

<div class="grid grid-4">
  <div class="kpi"><div class="kpi-label">Total facturé</div><div class="kpi-val"><?= money($stats['total']) ?></div><div class="kpi-sub"><?= $stats['nb'] ?> facture(s)</div></div>
  <div class="kpi green"><div class="kpi-label">Encaissé</div><div class="kpi-val"><?= money($stats['payees']) ?></div></div>
  <div class="kpi"><div class="kpi-label">Impayé / restant</div><div class="kpi-val" style="color:var(--red)"><?= money($stats['impayees']) ?></div></div>
  <div class="kpi gold"><div class="kpi-label">Taux de recouvrement</div><div class="kpi-val"><?= $stats['total']>0?num($stats['payees']/$stats['total']*100):0 ?>%</div></div>
</div>

<div class="card" style="margin-top:1.2rem">
  <div class="card-h"><h3>Liste des factures</h3>
    <?php if (can('factures.create')): ?><a href="ventes.php" class="btn btn-ghost btn-sm"><i class="fas fa-plus"></i> Nouvelle vente → facture</a><?php endif; ?>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>N° facture</th><th>Client</th><th>Date</th><th>Montant TTC</th><th>Payé</th><th>Statut</th></tr></thead>
    <tbody>
    <?php if (empty($factures)): ?><tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted)">
      Aucune facture. Les ventes encaissées génèrent automatiquement les écritures comptables.<br>
      Pour créer une facture, réalisez une vente via le <a href="ventes.php" style="color:var(--ember)">point de vente</a>.
    </td></tr>
    <?php else: foreach ($factures as $f):
      $stCls = ['payee'=>'bg-green','validee'=>'bg-blue','impayee'=>'bg-red','partielle'=>'bg-amber','brouillon'=>'bg-gray','annulee'=>'bg-gray'][$f['statut']] ?? 'bg-gray';
    ?>
      <tr>
        <td><b><?= e($f['numero']) ?></b></td>
        <td><?= e($f['client_nom'] ?: '—') ?></td>
        <td style="color:var(--muted)"><?= fdate($f['date_facture']) ?></td>
        <td><b><?= money($f['montant_ttc']) ?></b></td>
        <td><?= money($f['montant_paye']) ?></td>
        <td><span class="badge <?= $stCls ?>"><?= e($f['statut']) ?></span></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>
<?php layout_footer(); ?>
