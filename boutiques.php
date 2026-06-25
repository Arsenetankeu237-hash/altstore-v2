<?php
/**
 * boutiques.php — Gestion des boutiques du propriétaire.
 *
 *  - Liste les boutiques possédées (avec stats)
 *  - Création d'une nouvelle boutique (génère automatiquement sa caisse principale)
 *  - Accès / activation d'une boutique
 *
 *  Réservé au rôle "proprietaire".
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('boutiques.manage');

$uid = current_user_id();
$erreur = '';

// ---------------- Traitement création ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nom      = clean($_POST['nom'] ?? '');
    $desc     = clean($_POST['description'] ?? '');
    $ville    = clean($_POST['ville'] ?? 'Abidjan');
    $tel      = clean($_POST['telephone'] ?? '');
    $email    = strtolower(clean($_POST['email'] ?? ''));
    $couleur  = clean($_POST['couleur'] ?? '#F9A825');
    $soldeIni = (float)str_replace([' ', 'F'], '', (string)($_POST['solde_initial'] ?? '0'));

    if ($nom === '') {
        flash('Le nom de la boutique est obligatoire.', 'error');
        redirect(APP_URL . '/boutiques.php');
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        // Code unique
        do {
            $code = 'ALT-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        } while (fetch_one("SELECT id FROM boutiques WHERE code=?", [$code]));

        $slug = slugify($nom) . '-' . substr($code, 4);

        $st = $pdo->prepare(
            "INSERT INTO boutiques (proprietaire_id, nom, slug, code, description, couleur, ville, telephone, email, devise, tva_defaut)
             VALUES (?,?,?,?,?,?,?,?,?, 'XOF', 18.00)"
        );
        $st->execute([$uid, $nom, $slug, $code, $desc, $couleur, $ville, $tel, $email]);
        $newId = (int)$pdo->lastInsertId();

        // 🔑 Création automatique de la caisse principale de cette boutique
        $pdo->prepare(
            "INSERT INTO caisses (boutique_id, code_caisse, nom, description, couleur, solde_initial, is_default)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        )->execute([$newId, 'CSE-' . $code, 'Caisse principale — ' . $nom, 'Caisse par défaut de ' . $nom, $couleur, $soldeIni]);

        // Si un solde initial > 0, on enregistre un mouvement d'ouverture
        if ($soldeIni > 0) {
            $caisseId = (int)$pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO caisse_mouvements (caisse_id, boutique_id, type, montant, mode_paiement, reference, utilisateur_id, motif)
                 VALUES (?,?,'encaissement',?,'cash','Ouverture',?,'Fonds d''ouverture de caisse')"
            )->execute([$caisseId, $newId, $soldeIni, $uid]);
        }

        $pdo->commit();
        flash("Boutique « {$nom} » créée avec sa caisse principale (code {$code}).");
        redirect(APP_URL . '/boutiques.php');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[boutiques.create] ' . $e->getMessage());
        flash('Erreur lors de la création : ' . (IS_PROD ? 'réessayez plus tard' : $e->getMessage()), 'error');
        redirect(APP_URL . '/boutiques.php');
    }
}

// ---------------- Liste des boutiques du propriétaire ----------------
$boutiques = fetch_all(
    "SELECT b.*,
            (SELECT COUNT(*) FROM articles a WHERE a.boutique_id=b.id) AS nb_articles,
            (SELECT COUNT(*) FROM clients c WHERE c.boutique_id=b.id) AS nb_clients,
            (SELECT COUNT(*) FROM caisses cs WHERE cs.boutique_id=b.id) AS nb_caisses,
            (SELECT COUNT(*) FROM utilisateurs_boutique ub WHERE ub.boutique_id=b.id AND ub.statut='actif') AS nb_staff,
            (SELECT COALESCE(SUM(v.total_ttc),0) FROM ventes v WHERE v.boutique_id=b.id AND v.statut='payee' AND MONTH(v.date_vente)=MONTH(CURDATE()) AND YEAR(v.date_vente)=YEAR(CURDATE())) AS ca_mois
     FROM boutiques b
     WHERE b.proprietaire_id=?
     ORDER BY b.created_at",
    [$uid]
);

$showForm = isset($_GET['new']) || isset($_GET['need']);
?>
<?php layout_header('Mes boutiques', 'boutiques'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.4rem;flex-wrap:wrap;gap:1rem">
  <div>
    <h2 style="font-size:20px;margin-bottom:4px">🏪 Vos boutiques</h2>
    <p style="color:var(--muted);font-size:13px">Chaque boutique dispose de son propre stock, ses caisses, son personnel et ses rôles.</p>
  </div>
  <?php if (current_account_role() === 'proprietaire'): ?>
    <button class="btn btn-primary" onclick="document.getElementById('modalNew').style.display='block'">
      <i class="fas fa-plus"></i> Nouvelle boutique
    </button>
  <?php endif; ?>
</div>

<?php if ($showForm && isset($_GET['need'])): ?>
  <div class="flash flash-error"><i class="fas fa-info-circle"></i> Vous devez d'abord créer une boutique pour commencer.</div>
<?php endif; ?>

<div class="grid grid-3">
  <?php foreach ($boutiques as $b): ?>
    <div class="caisse-card">
      <div class="cc-bar" style="background:<?= e($b['couleur']) ?>"></div>
      <div style="padding-left:.4rem">
        <div class="cc-head">
          <div class="cc-ic" style="background:<?= e($b['couleur']) ?>"><i class="fas fa-store"></i></div>
          <?php if (active_boutique_id() === (int)$b['id']): ?>
            <span class="badge bg-green"><i class="fas fa-check"></i> Active</span>
          <?php endif; ?>
        </div>
        <h3 style="font-size:16px;margin-bottom:2px"><?= e($b['nom']) ?></h3>
        <small style="color:var(--muted)"><?= e($b['code']) ?> · <?= e($b['ville'] ?: '—') ?></small>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin:1rem 0;font-size:12px">
          <div><b style="color:var(--text)"><?= (int)$b['nb_articles'] ?></b> <span style="color:var(--muted)">articles</span></div>
          <div><b style="color:var(--text)"><?= (int)$b['nb_caisses'] ?></b> <span style="color:var(--muted)">caisses</span></div>
          <div><b style="color:var(--text)"><?= (int)$b['nb_staff'] ?></b> <span style="color:var(--muted)">personnel</span></div>
          <div><b style="color:var(--text)"><?= (int)$b['nb_clients'] ?></b> <span style="color:var(--muted)">clients</span></div>
        </div>
        <div style="padding:10px 0;border-top:1px solid var(--bd)">
          <small style="color:var(--muted)">CA ce mois</small><br>
          <b style="color:var(--green);font-size:17px"><?= money($b['ca_mois']) ?></b>
        </div>

        <div style="display:flex;gap:.5rem;margin-top:.6rem">
          <?php if (active_boutique_id() !== (int)$b['id']): ?>
            <button class="btn btn-primary btn-sm" style="flex:1" onclick="switchBoutique(<?= (int)$b['id'] ?>)">
              <i class="fas fa-bolt"></i> Activer
            </button>
          <?php else: ?>
            <a href="caisses.php" class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-cash-register"></i> Caisses</a>
            <a href="personnel.php" class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-users"></i> Personnel</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (empty($boutiques)): ?>
  <div class="empty">
    <i class="fas fa-store-slash"></i>
    <h3>Aucune boutique</h3>
    <p>Créez votre première boutique pour démarrer.</p>
    <button class="btn btn-primary" onclick="document.getElementById('modalNew').style.display='block'">
      <i class="fas fa-plus"></i> Créer une boutique
    </button>
  </div>
<?php endif; ?>

<!-- ============ MODAL : Nouvelle boutique ============ -->
<div id="modalNew" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="max-width:520px;width:100%;max-height:90vh;overflow-y:auto">
    <div class="card-h">
      <h3><i class="fas fa-store" style="color:var(--ember)"></i> Nouvelle boutique</h3>
      <button class="btn btn-ghost btn-sm" onclick="document.getElementById('modalNew').style.display='none'"><i class="fas fa-times"></i></button>
    </div>
    <p style="color:var(--muted);font-size:12px;margin-bottom:1rem">La caisse principale sera créée automatiquement avec son solde d'ouverture.</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-field full"><label>Nom de la boutique *</label>
          <input name="nom" required placeholder="Ex : ALT Store Annexe"></div>
        <div class="form-field"><label>Ville</label><input name="ville" value="Abidjan"></div>
        <div class="form-field"><label>Téléphone</label><input name="telephone"></div>
        <div class="form-field full"><label>Email</label><input type="email" name="email"></div>
        <div class="form-field full"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-field"><label>Couleur</label>
          <input type="color" name="couleur" value="#F9A825" style="height:42px;padding:4px"></div>
        <div class="form-field"><label>Solde d'ouverture caisse</label>
          <input name="solde_initial" value="0" placeholder="0"></div>
      </div>
      <div style="display:flex;gap:.6rem;margin-top:1.2rem">
        <button type="button" class="btn btn-ghost" style="flex:1" onclick="document.getElementById('modalNew').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-check"></i> Créer la boutique</button>
      </div>
    </form>
  </div>
</div>

<?php layout_footer(); ?>
