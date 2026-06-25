<?php
/**
 * entreprise.php — Paramètres de l'entreprise (pour en-tête des PDF).
 *
 * Toutes les infos saisies ici apparaissent sur les PDF générés
 * (pro forma, bon de commande...).
 */
require_once __DIR__ . '/config/bootstrap.php';
require_permission('entreprise.view');
require_boutique();

$bid     = active_boutique_id();
$bout    = active_boutique();
$erreur  = '';
$success = '';

// ---------------- Traitement ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('entreprise.manage');

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $nom        = clean($_POST['nom_entreprise'] ?? '');
            $sigle      = clean($_POST['sigle'] ?? '');
            $secteur    = clean($_POST['secteur_activite'] ?? '');
            $nif        = clean($_POST['nif'] ?? '');
            $rc         = clean($_POST['registre_commerce'] ?? '');
            $dateCrea   = clean($_POST['date_creation'] ?? '');
            $adresse    = clean($_POST['adresse'] ?? '');
            $ville      = clean($_POST['ville'] ?? '');
            $telephone  = clean($_POST['telephone'] ?? '');
            $email      = clean($_POST['email'] ?? '');

            if ($nom === '') throw new RuntimeException('Le nom de l\'entreprise est obligatoire.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Adresse email invalide.');
            }
            if ($dateCrea !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCrea)) {
                $dateCrea = '';
            }

            // Traitement du logo (upload)
            $logoPath = clean($_POST['logo_actuel'] ?? '');
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $mime = mime_content_type($_FILES['logo']['tmp_name']);
                if (!isset($allowed[$mime])) {
                    throw new RuntimeException('Format de logo non supporté (PNG, JPG, GIF, WEBP uniquement).');
                }
                if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    throw new RuntimeException('Le logo ne doit pas dépasser 2 Mo.');
                }
                $uploadDir = ROOT_PATH . '/uploads/logos';
                if (!is_dir($uploadDir)) {
                    if (!@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('Impossible de créer le dossier d\'upload.');
                    }
                }
                $fileName = 'logo_' . $bid . '_' . time() . '.' . $allowed[$mime];
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . '/' . $fileName)) {
                    $logoPath = 'uploads/logos/' . $fileName;
                }
            }

            // INSERT ou UPDATE (upsert par boutique_id)
            $exists = fetch_one("SELECT id FROM entreprises WHERE boutique_id = ?", [$bid]);
            if ($exists) {
                execute(
                    "UPDATE entreprises
                     SET logo=?, nom_entreprise=?, sigle=?, secteur_activite=?, nif=?, registre_commerce=?,
                         date_creation=?, adresse=?, ville=?, telephone=?, email=?
                     WHERE boutique_id=?",
                    [$logoPath, $nom, $sigle, $secteur, $nif, $rc, $dateCrea ?: null, $adresse, $ville, $telephone, $email, $bid]
                );
            } else {
                execute(
                    "INSERT INTO entreprises
                     (boutique_id, logo, nom_entreprise, sigle, secteur_activite, nif, registre_commerce, date_creation, adresse, ville, telephone, email)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$bid, $logoPath, $nom, $sigle, $secteur, $nif, $rc, $dateCrea ?: null, $adresse, $ville, $telephone, $email]
                );
            }
            $success = "Paramètres de l'entreprise enregistrés. Les PDF utiliseront ces informations.";
        }
    } catch (Throwable $e) {
        $erreur = IS_PROD ? 'Une erreur est survenue.' : $e->getMessage();
    }
}

// ---------------- Chargement des données ----------------
$ent = fetch_one("SELECT * FROM entreprises WHERE boutique_id = ?", [$bid]);
if (!$ent) {
    $ent = [
        'logo' => '', 'nom_entreprise' => $bout['nom'] ?? '', 'sigle' => '', 'secteur_activite' => '',
        'nif' => '', 'registre_commerce' => '', 'date_creation' => '', 'adresse' => $bout['adresse'] ?? '',
        'ville' => $bout['ville'] ?? '', 'telephone' => $bout['telephone'] ?? '', 'email' => $bout['email'] ?? '',
    ];
}
?>
<?php layout_header('Entreprise — ' . ($bout['nom'] ?? ''), 'entreprise'); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem">
  <div>
    <h2 style="font-size:20px">🏢 Paramètres de l'entreprise</h2>
    <p style="color:var(--muted);font-size:13px">Ces informations apparaîtront sur tous les PDF générés (pro forma, bon de commande).</p>
  </div>
</div>

<?php if ($success) echo '<div class="flash flash-success"><i class="fas fa-check-circle"></i> ' . e($success) . '</div>'; ?>
<?php if ($erreur)  echo '<div class="flash flash-error"><i class="fas fa-circle-exclamation"></i> ' . e($erreur) . '</div>'; ?>

<div class="grid grid-2" style="align-items:start">
  <!-- ===== Formulaire ===== -->
  <div class="card" style="padding:1.6rem">
    <?php if (can('entreprise.manage')): ?>
    <form method="post" enctype="multipart/form-data" id="entForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="logo_actuel" value="<?= e($ent['logo'] ?? '') ?>">

      <!-- Logo -->
      <div class="ent-logo-block">
        <label class="ent-logo-label">Logo de l'entreprise</label>
        <div class="ent-logo-preview" id="logoPreview">
          <?php if (!empty($ent['logo']) && is_file(ROOT_PATH . '/' . $ent['logo'])): ?>
            <img src="<?= APP_URL ?>/<?= e($ent['logo']) ?>" alt="Logo">
          <?php else: ?>
            <i class="fas fa-image"></i>
            <small>Aucun logo</small>
          <?php endif; ?>
        </div>
        <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" class="ent-logo-input">
        <label for="logoInput" class="btn btn-ghost btn-sm">
          <i class="fas fa-upload"></i> Télécharger un logo
        </label>
        <small style="color:var(--muted);display:block;margin-top:.4rem">PNG, JPG, GIF ou WEBP — 2 Mo max</small>
      </div>

      <div class="form-grid">
        <div class="form-field full">
          <label>Nom de l'entreprise *</label>
          <input name="nom_entreprise" value="<?= e($ent['nom_entreprise'] ?? '') ?>" required>
        </div>
        <div class="form-field">
          <label>Sigle / Nom commercial</label>
          <input name="sigle" value="<?= e($ent['sigle'] ?? '') ?>" placeholder="ex. ALT STORE">
        </div>
        <div class="form-field">
          <label>Secteur d'activité</label>
          <input name="secteur_activite" value="<?= e($ent['secteur_activite'] ?? '') ?>" placeholder="ex. Technologie & Innovation">
        </div>
        <div class="form-field">
          <label>NIF</label>
          <input name="nif" value="<?= e($ent['nif'] ?? '') ?>" placeholder="Numéro d'Identification Fiscale">
        </div>
        <div class="form-field">
          <label>Registre du commerce</label>
          <input name="registre_commerce" value="<?= e($ent['registre_commerce'] ?? '') ?>" placeholder="N° RC">
        </div>
        <div class="form-field">
          <label>Date de création</label>
          <input name="date_creation" type="date" value="<?= e($ent['date_creation'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Ville</label>
          <input name="ville" value="<?= e($ent['ville'] ?? '') ?>" placeholder="ex. Abidjan">
        </div>
        <div class="form-field">
          <label>Téléphone</label>
          <input name="telephone" value="<?= e($ent['telephone'] ?? '') ?>" placeholder="ex. 27 22 00 00">
        </div>
        <div class="form-field">
          <label>Email</label>
          <input name="email" type="email" value="<?= e($ent['email'] ?? '') ?>" placeholder="contact@entreprise.ci">
        </div>
        <div class="form-field full">
          <label>Adresse</label>
          <textarea name="adresse" rows="2" placeholder="Adresse complète"><?= e($ent['adresse'] ?? '') ?></textarea>
        </div>
      </div>

      <button class="btn btn-primary" style="width:100%;margin-top:1rem"><i class="fas fa-save"></i> Enregistrer les informations</button>
    </form>
    <?php else: ?>
      <div class="empty" style="padding:2rem">Vous n'avez pas la permission de modifier ces informations.</div>
    <?php endif; ?>
  </div>

  <!-- ===== Aperçu en-tête PDF ===== -->
  <div class="card" style="padding:1.6rem">
    <h3 style="font-size:15px;margin-bottom:1rem;color:var(--muted)"><i class="fas fa-eye"></i> Aperçu (en-tête PDF)</h3>
    <div class="pdf-preview">
      <div class="pdf-preview-head">
        <?php if (!empty($ent['logo']) && is_file(ROOT_PATH . '/' . $ent['logo'])): ?>
          <img src="<?= APP_URL ?>/<?= e($ent['logo']) ?>" class="pdf-prev-logo" alt="">
        <?php else: ?>
          <div class="pdf-prev-logo-placeholder">LOGO</div>
        <?php endif; ?>
        <div>
          <div class="pdf-prev-name"><?= e($ent['nom_entreprise'] ?: 'Nom de l\'entreprise') ?></div>
          <?php if (!empty($ent['sigle']) || !empty($ent['secteur_activite'])): ?>
            <div class="pdf-prev-sub"><?= e(implode(' · ', array_filter([$ent['sigle'] ?? '', $ent['secteur_activite'] ?? '']))) ?></div>
          <?php endif; ?>
          <div class="pdf-prev-coord">
            <?php
              $coord = [];
              if (!empty($ent['nif'])) $coord[] = 'NIF: ' . $ent['nif'];
              if (!empty($ent['registre_commerce'])) $coord[] = 'RC: ' . $ent['registre_commerce'];
              if (!empty($ent['telephone'])) $coord[] = 'Tel: ' . $ent['telephone'];
              if (!empty($ent['email'])) $coord[] = $ent['email'];
              if (!empty($ent['ville'])) $coord[] = $ent['ville'];
              echo e(implode('  |  ', $coord));
            ?>
          </div>
        </div>
      </div>
      <div class="pdf-prev-line"></div>
      <div class="pdf-prev-doctitle">PRO FORMA — N° PF-0001</div>
      <div style="margin-top:1rem;color:var(--muted);font-size:12px">Cet aperçu montre comment les informations ci-contre apparaîtront en haut de chaque PDF généré.</div>
    </div>
  </div>
</div>

<script>
// Aperçu en temps réel du logo téléchargé
document.getElementById('logoInput')?.addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(ev) {
    const preview = document.getElementById('logoPreview');
    preview.innerHTML = '<img src="' + ev.target.result + '" alt="Logo">';
  };
  reader.readAsDataURL(file);
});
</script>

<?php layout_footer(); ?>
