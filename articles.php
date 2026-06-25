<?php
/**
 * articles.php — Catalogue & stock de la boutique active.
 *
 * Layout split-screen : formulaire à gauche, cards articles à droite.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('articles.view');

$bid = active_boutique_id();
$bout = active_boutique();
$erreur = $success = '';

// ---------------- Traitement ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        $pdo = db();

        if ($action === 'create' || $action === 'update') {
            require_permission('articles.' . ($action === 'create' ? 'create' : 'edit'));
            $id        = (int)($_POST['id'] ?? 0);
            $nom       = clean($_POST['nom_article'] ?? '');
            $reference = clean($_POST['reference'] ?? '');
            $code_barre= clean($_POST['code_barre'] ?? '');
            $marque    = clean($_POST['marque'] ?? '');
            $cat       = (int)($_POST['categorie_id'] ?? 0) ?: null;
            $qte       = (int)($_POST['quantite_stock'] ?? 0);
            $min       = (int)($_POST['stock_min'] ?? 10);
            $max       = (int)($_POST['stock_max'] ?? 0) ?: null;
            $achat     = (float)str_replace([' ', 'F'], '', (string)($_POST['prix_achat'] ?? 0));
            $vente     = (float)str_replace([' ', 'F'], '', (string)($_POST['prix_vente'] ?? 0));
            $tva       = (float)($_POST['tva'] ?? 18);
            $unite     = clean($_POST['unite_mesure'] ?? 'unite');
            $couleur   = clean($_POST['couleur'] ?? '');
            $desc      = clean($_POST['description'] ?? '');

            if ($nom === '' || $reference === '') throw new RuntimeException('Nom et référence obligatoires');
            if ($vente <= 0) throw new RuntimeException('Prix de vente invalide');

            // Unicité de la référence dans la boutique
            $exist = fetch_one("SELECT id FROM articles WHERE boutique_id=? AND reference=? AND id<>?", [$bid, $reference, $id]);
            if ($exist) throw new RuntimeException('Cette référence existe déjà dans la boutique');

            $statut = $qte <= 0 ? 'out_of_stock' : ($qte <= $min ? 'low_stock' : 'active');

            if ($action === 'create') {
                $pdo->prepare(
                    "INSERT INTO articles (boutique_id, categorie_id, nom_article, reference, code_barre, marque, quantite_stock, stock_min, stock_max, prix_achat, prix_vente, tva, unite_mesure, couleur, description, statut)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([$bid, $cat, $nom, $reference, $code_barre, $marque, $qte, $min, $max, $achat, $vente, $tva, $unite, $couleur, $desc, $statut]);
                $newId = (int)$pdo->lastInsertId();
                if ($qte != 0) {
                    $pdo->prepare("INSERT INTO mouvements_stock (boutique_id, article_id, type, quantite, quantite_avant, reference, utilisateur_id, motif) VALUES (?,?,'entree',?,0,'Création',?,'Stock initial')")
                        ->execute([$bid, $newId, $qte, current_user_id()]);
                }
                $success = "Article « {$nom} » créé.";
            } else {
                execute(
                    "UPDATE articles SET categorie_id=?, nom_article=?, reference=?, code_barre=?, marque=?, quantite_stock=?, stock_min=?, stock_max=?, prix_achat=?, prix_vente=?, tva=?, unite_mesure=?, couleur=?, description=?, statut=? WHERE id=? AND boutique_id=?",
                    [$cat, $nom, $reference, $code_barre, $marque, $qte, $min, $max, $achat, $vente, $tva, $unite, $couleur, $desc, $statut, $id, $bid]
                );
                redirect(APP_URL . '/articles.php?ok=1');
            }
        }

        elseif ($action === 'delete') {
            require_permission('articles.delete');
            $id = (int)($_POST['id'] ?? 0);
            execute("DELETE FROM articles WHERE id=? AND boutique_id=?", [$id, $bid]);
            $success = "Article supprimé.";
        }

    } catch (Throwable $e) {
        $erreur = IS_PROD ? 'Une erreur est survenue.' : $e->getMessage();
    }
}

// ---------------- Filtres + liste ----------------
$search = clean($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);

$sql = "SELECT a.*, c.nom AS categorie_nom FROM articles a LEFT JOIN categories c ON c.id=a.categorie_id WHERE a.boutique_id=?";
$params = [$bid];
if ($search !== '') { $sql .= " AND (a.nom_article LIKE ? OR a.reference LIKE ? OR a.code_barre LIKE ?)"; $kw = "%$search%"; array_push($params, $kw, $kw, $kw); }
if ($catFilter) { $sql .= " AND a.categorie_id=?"; $params[] = $catFilter; }
$sql .= " ORDER BY a.nom_article LIMIT 200";
$articles = fetch_all($sql, $params);

$categories = fetch_all("SELECT * FROM categories WHERE boutique_id=? ORDER BY nom", [$bid]);

// Stats
$totalArticles = count($articles);
$totalStock = array_sum(array_column($articles, 'quantite_stock'));
$valeurStock = 0; foreach ($articles as $a) $valeurStock += $a['quantite_stock'] * $a['prix_achat'];
$ruptures = count(array_filter($articles, fn($a) => $a['quantite_stock'] <= 0));

// Article en édition (pré-remplit le formulaire)
$editArticle = null;
if (isset($_GET['edit'])) {
    $editArticle = fetch_one("SELECT * FROM articles WHERE id=? AND boutique_id=?", [(int)$_GET['edit'], $bid]);
}

if (isset($_GET['ok']) && !$success) $success = "Article modifié avec succès.";
?>
<?php layout_header('Articles — ' . ($bout['nom'] ?? ''), 'articles'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem">
  <div><h2 style="font-size:20px">📦 Articles / Stock</h2>
  <p style="color:var(--muted);font-size:13px">Catalogue de <?= e($bout['nom'] ?? '') ?></p></div>
</div>

<?php if ($success) echo '<div class="flash flash-success"><i class="fas fa-check-circle"></i> ' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<div class="grid grid-4">
  <div class="kpi"><div class="kpi-label">Articles</div><div class="kpi-val"><?= num($totalArticles) ?></div></div>
  <div class="kpi green"><div class="kpi-label">Unités en stock</div><div class="kpi-val"><?= num($totalStock) ?></div></div>
  <div class="kpi gold"><div class="kpi-label">Valeur du stock (achat)</div><div class="kpi-val"><?= money($valeurStock) ?></div></div>
  <div class="kpi"><div class="kpi-label">Ruptures</div><div class="kpi-val" style="color:var(--red)"><?= num($ruptures) ?></div></div>
</div>

<div class="art-layout" style="margin-top:1.4rem">

  <!-- ===== COLONNE GAUCHE : FORMULAIRE ===== -->
  <div class="card art-form-card" style="padding:1.4rem">
    <?php if ($editArticle): ?>
      <h3 style="font-size:16px;margin-bottom:1rem"><i class="fas fa-pen" style="color:var(--ember)"></i> Modifier l'article</h3>
    <?php elseif (can('articles.create')): ?>
      <h3 style="font-size:16px;margin-bottom:1rem"><i class="fas fa-plus" style="color:var(--ember)"></i> Nouvel article</h3>
    <?php endif; ?>

    <?php if ($editArticle || can('articles.create')): ?>
    <form method="post" id="artForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editArticle ? 'update' : 'create' ?>">
      <?php if ($editArticle): ?><input type="hidden" name="id" value="<?= (int)$editArticle['id'] ?>"><?php endif; ?>

      <!-- Section Identité -->
      <div class="art-form-section">
        <h4><i class="fas fa-tag"></i> Identité</h4>
        <div class="form-grid">
          <div class="form-field full">
            <label>Nom de l'article *</label>
            <input name="nom_article" required value="<?= e($editArticle['nom_article'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label>Référence *</label>
            <input name="reference" required value="<?= e($editArticle['reference'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label>Code-barre</label>
            <input name="code_barre" value="<?= e($editArticle['code_barre'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label>Marque</label>
            <input name="marque" value="<?= e($editArticle['marque'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label>Catégorie</label>
            <select name="categorie_id">
              <option value="0">—</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ($editArticle['categorie_id'] ?? 0)===(int)$c['id']?'selected':'' ?>><?= e($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label>Couleur</label>
            <input name="couleur" value="<?= e($editArticle['couleur'] ?? '') ?>" placeholder="ex. Noir">
          </div>
        </div>
      </div>

      <!-- Section Stock & Prix -->
      <div class="art-form-section">
        <h4><i class="fas fa-boxes-stacked"></i> Stock & Prix</h4>
        <div class="form-grid">
          <div class="form-field">
            <label>Quantité stock</label>
            <input name="quantite_stock" type="number" value="<?= (int)($editArticle['quantite_stock'] ?? 0) ?>">
          </div>
          <div class="form-field">
            <label>Stock min</label>
            <input name="stock_min" type="number" value="<?= (int)($editArticle['stock_min'] ?? 10) ?>">
          </div>
          <div class="form-field">
            <label>Stock max</label>
            <input name="stock_max" type="number" value="<?= (int)($editArticle['stock_max'] ?? 0) ?>">
          </div>
          <div class="form-field">
            <label>Unité</label>
            <input name="unite_mesure" value="<?= e($editArticle['unite_mesure'] ?? 'unite') ?>">
          </div>
          <div class="form-field">
            <label>Prix achat (F)</label>
            <input name="prix_achat" type="number" step="0.01" value="<?= e($editArticle['prix_achat'] ?? 0) ?>">
          </div>
          <div class="form-field">
            <label>Prix vente (F) *</label>
            <input name="prix_vente" type="number" step="0.01" required value="<?= e($editArticle['prix_vente'] ?? 0) ?>">
          </div>
          <div class="form-field">
            <label>TVA %</label>
            <input name="tva" type="number" step="0.01" value="<?= e($editArticle['tva'] ?? 18) ?>">
          </div>
        </div>
      </div>

      <!-- Section Description -->
      <div class="art-form-section">
        <h4><i class="fas fa-align-left"></i> Description</h4>
        <div class="form-field full">
          <textarea name="description" rows="3" placeholder="Description de l'article..."><?= e($editArticle['description'] ?? '') ?></textarea>
        </div>
      </div>

      <button class="btn btn-primary" style="width:100%"><i class="fas fa-check"></i> <?= $editArticle ? 'Enregistrer les modifications' : 'Créer l\'article' ?></button>
      <?php if ($editArticle): ?>
        <a href="articles.php" class="btn btn-ghost" style="width:100%;margin-top:.5rem;text-align:center"><i class="fas fa-times"></i> Annuler</a>
      <?php endif; ?>
    </form>
    <?php else: ?>
      <div class="empty" style="padding:1.5rem;text-align:center;color:var(--muted)">
        <i class="fas fa-lock" style="font-size:24px;display:block;margin-bottom:.5rem"></i>
        Vous n'avez pas la permission de créer des articles.
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== COLONNE DROITE : LISTE EN CARDS ===== -->
  <div>
    <!-- Recherche -->
    <form method="get" style="display:flex;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap">
      <input name="q" value="<?= e($search) ?>" placeholder="🔎 Rechercher (nom, réf, code-barre)" style="flex:1;min-width:180px;padding:10px 14px;background:var(--surf);border:1px solid var(--bd);border-radius:9px;color:var(--text)">
      <select name="cat" style="padding:10px 14px;background:var(--surf);border:1px solid var(--bd);border-radius:9px;color:var(--text)">
        <option value="0">Toutes catégories</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $catFilter===(int)$c['id']?'selected':'' ?>><?= e($c['nom']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-ghost"><i class="fas fa-filter"></i> Filtrer</button>
    </form>

    <?php if (empty($articles)): ?>
      <div class="card" style="padding:2.5rem;text-align:center;color:var(--muted)">
        <i class="fas fa-box-open" style="font-size:36px;display:block;margin-bottom:.8rem;opacity:.4"></i>
        Aucun article trouvé.<br>
        <?= can('articles.create') ? 'Utilisez le formulaire à gauche pour ajouter votre premier article.' : '' ?>
      </div>
    <?php else: ?>
      <div class="art-grid-cards">
        <?php foreach ($articles as $a):
            $stCls = $a['statut']==='active'?'bg-green':($a['statut']==='low_stock'?'bg-amber':'bg-red');
            $stLbl = $a['statut']==='active'?'En stock':($a['statut']==='low_stock'?'Stock bas':'Rupture');
        ?>
          <div class="art-card">
            <div class="ac-actions">
              <?php if (can('articles.edit')): ?>
                <a href="articles.php?edit=<?= (int)$a['id'] ?>" title="Modifier"><i class="fas fa-pen"></i></a>
              <?php endif; ?>
              <?php if (can('articles.delete')): ?>
                <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer cet article ?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" title="Supprimer"><i class="fas fa-trash"></i></button>
                </form>
              <?php endif; ?>
            </div>
            <div class="ac-head">
              <div>
                <div class="ac-name"><?= e($a['nom_article']) ?></div>
                <div class="ac-ref"><?= e($a['reference']) ?> · <?= e($a['marque'] ?: '—') ?></div>
              </div>
            </div>
            <div class="ac-price"><?= money($a['prix_vente']) ?></div>
            <div class="ac-foot">
              <span><i class="fas fa-warehouse"></i> <?= (int)$a['quantite_stock'] ?> <?= e($a['unite_mesure']) ?></span>
              <span class="badge <?= $stCls ?>"><?= e($stLbl) ?></span>
            </div>
            <?php if (!empty($a['categorie_nom'])): ?>
              <div style="margin-top:.5rem;font-size:11px;color:var(--muted)"><i class="fas fa-folder"></i> <?= e($a['categorie_nom']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
<?php layout_footer(); ?>
