<?php
/**
 * caisses.php — Gestion des caisses de la boutique active.
 *
 *  Fonctionnalités :
 *   - Liste les caisses avec solde calculé (initial + encaissements - décaissements)
 *   - Création d'une nouvelle caisse
 *   - Encaissement / décaissement / transfert inter-caisses
 *   - Détail des mouvements par caisse
 *
 *  Toutes les opérations sont filtrées par boutique_id (tenancy).
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('caisse.view');

$bid = active_boutique_id();
$bout = active_boutique();
$erreur = '';
$success = '';

// ---------------- Traitement actions caisse ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        $pdo = db();

        if ($action === 'create_caisse') {
            require_permission('caisse.encaisser'); // un droit de gestion
            $nom   = clean($_POST['nom'] ?? '');
            $desc  = clean($_POST['description'] ?? '');
            $couleur = clean($_POST['couleur'] ?? '#F9A825');
            $icone = clean($_POST['icone'] ?? 'fa-cash-register');
            $solde = (float)str_replace([' ', 'F'], '', (string)($_POST['solde_initial'] ?? '0'));

            if ($nom === '') throw new RuntimeException('Nom obligatoire');

            // code unique pour la boutique
            $count = (int)fetch_one("SELECT COUNT(*) c FROM caisses WHERE boutique_id=?", [$bid])['c'];
            $code = 'CSE-' . $bout['code'] . '-' . str_pad((string)($count + 1), 2, '0', STR_PAD_LEFT);

            $pdo->prepare(
                "INSERT INTO caisses (boutique_id, code_caisse, nom, description, couleur, icone, solde_initial, is_active)
                 VALUES (?,?,?,?,?,?,?,1)"
            )->execute([$bid, $code, $nom, $desc, $couleur, $icone, $solde]);
            $cid = (int)$pdo->lastInsertId();

            if ($solde > 0) {
                $pdo->prepare(
                    "INSERT INTO caisse_mouvements (caisse_id, boutique_id, type, montant, mode_paiement, reference, utilisateur_id, motif)
                     VALUES (?,?,'encaissement',?,'cash','Ouverture',?,'Fonds d''ouverture')"
                )->execute([$cid, $bid, $solde, current_user_id()]);
            }
            $success = "Caisse « {$nom} » créée ({$code}).";
        }

        elseif ($action === 'mouvement') {
            require_permission('caisse.encaisser');
            $cid   = (int)($_POST['caisse_id'] ?? 0);
            $type  = clean($_POST['type'] ?? ''); // encaissement | decaissement
            $montant = (float)str_replace([' ', 'F'], '', (string)($_POST['montant'] ?? '0'));
            $mode  = clean($_POST['mode_paiement'] ?? 'cash');
            $motif = clean($_POST['motif'] ?? '');

            // Vérifier appartenance caisse
            $c = fetch_one("SELECT id FROM caisses WHERE id=? AND boutique_id=?", [$cid, $bid]);
            if (!$c) throw new RuntimeException('Caisse introuvable');
            if ($montant <= 0) throw new RuntimeException('Montant invalide');
            if (!in_array($type, ['encaissement', 'decaissement'], true)) throw new RuntimeException('Type invalide');

            $pdo->prepare(
                "INSERT INTO caisse_mouvements (caisse_id, boutique_id, type, montant, mode_paiement, utilisateur_id, motif)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$cid, $bid, $type, $montant, $mode, current_user_id(), $motif]);
            $success = ucfirst($type) . " de " . money($montant) . " enregistré.";
        }

        elseif ($action === 'transfert') {
            require_permission('caisse.transferer');
            $src = (int)($_POST['source_id'] ?? 0);
            $dst = (int)($_POST['cible_id'] ?? 0);
            $montant = (float)str_replace([' ', 'F'], '', (string)($_POST['montant'] ?? '0'));
            $motif = clean($_POST['motif'] ?? 'Transfert');

            if ($src === $dst) throw new RuntimeException('Source et destination identiques');
            // Vérif solde suffisant
            $soldeSrc = solde_caisse($src);
            if ($soldeSrc < $montant) throw new RuntimeException('Solde insuffisant dans la caisse source');
            // Vérif appartenance
            foreach ([$src, $dst] as $c) {
                if (!fetch_one("SELECT id FROM caisses WHERE id=? AND boutique_id=?", [$c, $bid]))
                    throw new RuntimeException('Caisse introuvable');
            }

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO caisse_mouvements (caisse_id,boutique_id,type,montant,utilisateur_id,motif,caisse_liee_id) VALUES (?,?,'transfert_out',?,?,?,?)")
                ->execute([$src, $bid, $montant, current_user_id(), $motif, $dst]);
            $pdo->prepare("INSERT INTO caisse_mouvements (caisse_id,boutique_id,type,montant,utilisateur_id,motif,caisse_liee_id) VALUES (?,?,'transfert_in',?,?,?,?)")
                ->execute([$dst, $bid, $montant, current_user_id(), $motif, $src]);
            $pdo->commit();
            $success = "Transfert de " . money($montant) . " effectué.";
        }

    } catch (Throwable $e) {
        $erreur = IS_PROD ? 'Une erreur est survenue.' : $e->getMessage();
    }
}

// ---------------- Récupération des caisses + soldes ----------------
$caisses = fetch_all("SELECT * FROM caisses WHERE boutique_id=? ORDER BY is_default DESC, nom", [$bid]);
foreach ($caisses as &$c) {
    $c['solde']    = solde_caisse((int)$c['id']);
    $c['nb_mvt']   = (int)fetch_one("SELECT COUNT(*) c FROM caisse_mouvements WHERE caisse_id=?", [(int)$c['id']])['c'];
    $c['dernier']  = fetch_one("SELECT * FROM caisse_mouvements WHERE caisse_id=? ORDER BY created_at DESC LIMIT 1", [(int)$c['id']]);
}
unset($c);

$soldeTotal = array_sum(array_column($caisses, 'solde'));

// Derniers mouvements globaux
$mouvements = fetch_all(
    "SELECT m.*, c.nom AS caisse_nom, u.prenom, u.nom
     FROM caisse_mouvements m
     LEFT JOIN caisses c ON c.id=m.caisse_id
     LEFT JOIN utilisateurs u ON u.id=m.utilisateur_id
     WHERE m.boutique_id=?
     ORDER BY m.created_at DESC LIMIT 12",
    [$bid]
);
?>
<?php layout_header('Caisses — ' . ($bout['nom'] ?? ''), 'caisses'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.4rem;flex-wrap:wrap;gap:1rem">
  <div>
    <h2 style="font-size:20px;margin-bottom:4px">💵 Caisses — <?= e($bout['nom'] ?? '') ?></h2>
    <p style="color:var(--muted);font-size:13px">Solde cumulé des caisses de cette boutique</p>
  </div>
  <div style="display:flex;gap:.6rem;align-items:center">
    <div style="text-align:right;padding-right:1rem;border-right:1px solid var(--bd)">
      <small style="color:var(--muted);font-size:11px">SOLDE TOTAL</small>
      <div style="font-size:22px;font-weight:700;color:var(--green)"><?= money($soldeTotal) ?></div>
    </div>
    <?php if (can('caisse.encaisser')): ?>
      <button class="btn btn-primary" onclick="openModal('modalCaisse')"><i class="fas fa-plus"></i> Nouvelle caisse</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($success) echo '<div class="flash flash-success"><i class="fas fa-check-circle"></i> ' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<!-- Grille des caisses -->
<?php if (empty($caisses)): ?>
  <div class="empty"><i class="fas fa-cash-register"></i><h3>Aucune caisse</h3>
    <p>Créez votre première caisse pour cette boutique.</p>
    <?php if (can('caisse.encaisser')): ?>
      <button class="btn btn-primary" onclick="openModal('modalCaisse')"><i class="fas fa-plus"></i> Créer une caisse</button>
    <?php endif; ?>
  </div>
<?php else: ?>
<div class="grid grid-3">
  <?php foreach ($caisses as $c): ?>
    <div class="caisse-card">
      <div class="cc-bar" style="background:<?= e($c['couleur']) ?>"></div>
      <div style="padding-left:.4rem">
        <div class="cc-head">
          <div class="cc-ic" style="background:<?= e($c['couleur']) ?>"><i class="fas <?= e($c['icone']) ?>"></i></div>
          <?php if ($c['is_default']): ?><span class="badge bg-amber"><i class="fas fa-star"></i> Principale</span><?php endif; ?>
        </div>
        <h3 style="font-size:15px;margin-bottom:2px"><?= e($c['nom']) ?></h3>
        <small style="color:var(--muted)"><?= e($c['code_caisse']) ?></small>

        <div style="margin:1rem 0;padding:10px 0;border-top:1px solid var(--bd);border-bottom:1px solid var(--bd)">
          <small style="color:var(--muted);text-transform:uppercase;font-size:10px;letter-spacing:.05em">Solde actuel</small>
          <div class="cc-solde" style="color:<?= $c['solde']<0?'var(--red)':'var(--green)' ?>"><?= money($c['solde']) ?></div>
          <small style="color:var(--muted)"><?= (int)$c['nb_mvt'] ?> mouvement(s) · <?= $c['dernier'] ? 'Dernier : '.fdatetime($c['dernier']['created_at']) : 'Aucun mouvement' ?></small>
        </div>

        <?php if (can('caisse.encaisser') || can('caisse.decaisser')): ?>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
          <button class="btn btn-sm bg-green" style="background:var(--green-soft);color:#4ade80" onclick="openMouvement(<?= (int)$c['id'] ?>,'encaissement','<?= e($c['nom']) ?>')"><i class="fas fa-arrow-down"></i> Encaisser</button>
          <button class="btn btn-sm" style="background:rgba(220,38,38,.12);color:#fca5a5" onclick="openMouvement(<?= (int)$c['id'] ?>,'decaissement','<?= e($c['nom']) ?>')"><i class="fas fa-arrow-up"></i> Décaisser</button>
          <button class="btn btn-sm btn-ghost" onclick="voirMouvements(<?= (int)$c['id'] ?>,'<?= e($c['nom']) ?>')"><i class="fas fa-list"></i></button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (can('caisse.transferer') && count($caisses) >= 2): ?>
<button class="btn btn-ghost" style="margin-top:1rem" onclick="openModal('modalTransfert')">
  <i class="fas fa-right-left"></i> Transfert inter-caisses
</button>
<?php endif; ?>
<?php endif; ?>

<!-- Derniers mouvements -->
<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3><i class="fas fa-clock-rotate-left" style="color:var(--ember)"></i> Derniers mouvements</h3></div>
  <?php if (empty($mouvements)): ?>
    <div class="empty" style="padding:1.5rem"><i class="fas fa-inbox"></i><p>Aucun mouvement enregistré.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Caisse</th><th>Type</th><th>Mode</th><th>Montant</th><th>Motif</th><th>Par</th></tr></thead>
      <tbody>
        <?php foreach ($mouvements as $m):
          $isIn = in_array($m['type'], ['encaissement','transfert_in'], true);
          $lbl = ['encaissement'=>'Encaissement','decaissement'=>'Décaissement','transfert_in'=>'Transfert reçu','transfert_out'=>'Transfert émis','ajustement'=>'Ajustement'][$m['type']] ?? $m['type'];
        ?>
        <tr>
          <td style="color:var(--muted)"><?= fdatetime($m['created_at']) ?></td>
          <td><?= e($m['caisse_nom']) ?></td>
          <td><span class="badge <?= $isIn?'bg-green':'bg-red' ?>"><?= e($lbl) ?></span></td>
          <td><span class="badge bg-gray"><?= e($m['mode_paiement'] ?: '—') ?></span></td>
          <td><b style="color:<?= $isIn?'var(--green)':'var(--red)' ?>"><?= $isIn?'+':'-' ?><?= money($m['montant']) ?></b></td>
          <td><?= e($m['motif'] ?: '—') ?></td>
          <td style="color:var(--muted)"><?= e(trim($m['prenom'].' '.$m['nom'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ===== MODAL : nouvelle caisse ===== -->
<div id="modalCaisse" class="modal-overlay" style="display:none">
  <div class="card" style="max-width:480px;width:100%">
    <div class="card-h"><h3><i class="fas fa-cash-register" style="color:var(--ember)"></i> Nouvelle caisse</h3>
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modalCaisse')"><i class="fas fa-times"></i></button></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_caisse">
      <div class="form-grid">
        <div class="form-field full"><label>Nom *</label><input name="nom" required placeholder="Caisse secondaire"></div>
        <div class="form-field full"><label>Description</label><input name="description"></div>
        <div class="form-field"><label>Couleur</label><input type="color" name="couleur" value="#6366f1" style="height:42px;padding:4px"></div>
        <div class="form-field"><label>Solde d'ouverture</label><input name="solde_initial" value="0"></div>
      </div>
      <button class="btn btn-primary" style="width:100%;margin-top:1rem"><i class="fas fa-check"></i> Créer la caisse</button>
    </form>
  </div>
</div>

<!-- ===== MODAL : mouvement (encaissement/décaissement) ===== -->
<div id="modalMouvement" class="modal-overlay" style="display:none">
  <div class="card" style="max-width:420px;width:100%">
    <div class="card-h"><h3 id="mvtTitle">Encaissement</h3>
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modalMouvement')"><i class="fas fa-times"></i></button></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mouvement">
      <input type="hidden" name="caisse_id" id="mvtCaisseId">
      <input type="hidden" name="type" id="mvtType">
      <div class="form-field" style="margin-bottom:1rem"><label>Caisse</label><input id="mvtCaisseNom" disabled></div>
      <div class="form-field" style="margin-bottom:1rem"><label>Montant (F) *</label><input name="montant" required type="number" min="1" step="1"></div>
      <div class="form-field" style="margin-bottom:1rem"><label>Mode de paiement</label>
        <select name="mode_paiement"><option value="cash">Espèces</option><option value="mobile">Mobile money</option><option value="carte">Carte</option><option value="virement">Virement</option></select></div>
      <div class="form-field" style="margin-bottom:1rem"><label>Motif</label><input name="motif" placeholder="Ex : apport, dépense fournisseur..."></div>
      <button class="btn btn-primary" style="width:100%"><i class="fas fa-check"></i> Valider</button>
    </form>
  </div>
</div>

<!-- ===== MODAL : transfert ===== -->
<div id="modalTransfert" class="modal-overlay" style="display:none">
  <div class="card" style="max-width:420px;width:100%">
    <div class="card-h"><h3><i class="fas fa-right-left" style="color:var(--blue)"></i> Transfert inter-caisses</h3>
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modalTransfert')"><i class="fas fa-times"></i></button></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="transfert">
      <div class="form-field" style="margin-bottom:1rem"><label>De (caisse source)</label>
        <select name="source_id" required>
          <?php foreach ($caisses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nom']) ?> (<?= money($c['solde']) ?>)</option><?php endforeach; ?>
        </select></div>
      <div class="form-field" style="margin-bottom:1rem"><label>Vers (caisse cible)</label>
        <select name="cible_id" required>
          <?php foreach ($caisses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nom']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-field" style="margin-bottom:1rem"><label>Montant (F) *</label><input name="montant" required type="number" min="1"></div>
      <div class="form-field" style="margin-bottom:1rem"><label>Motif</label><input name="motif"></div>
      <button class="btn btn-primary" style="width:100%"><i class="fas fa-check"></i> Transférer</button>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
function openMouvement(caisseId, type, nom){
  document.getElementById('mvtCaisseId').value = caisseId;
  document.getElementById('mvtType').value = type;
  document.getElementById('mvtCaisseNom').value = nom;
  document.getElementById('mvtTitle').innerText = type === 'encaissement' ? 'Encaissement' : 'Décaissement';
  openModal('modalMouvement');
}
function voirMouvements(caisseId, nom){
  // redirige vers le détail (filtrable) — ici on filtre les mouvements déjà affichés
  alert('Détails des mouvements de : ' + nom + '\n(Use le tableau ci-dessous, filtrable par caisse)');
}
// fermer au clic sur l'overlay
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) o.style.display='none'; }));
</script>

<?php layout_footer(); ?>
