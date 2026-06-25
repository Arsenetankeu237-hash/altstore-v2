<?php
/**
 * views/layouts/header.php — Layout commun (topbar + sidebar + sélecteur de boutique).
 *
 * Variables attendues :
 *   $_page_title, $_page_active
 *
 * La navigation est filtrée par permissions (can()).
 */
require_once ROOT_PATH . '/config/bootstrap.php';
require_login();

$active    = $GLOBALS['_page_active'] ?? '';
$title     = $GLOBALS['_page_title'] ?? 'Tableau de bord';
$bout      = active_boutique();
$boutiques = list_user_boutiques();
$role      = current_role();
$userName  = $_SESSION['user_name'] ?? 'Utilisateur';

// Menu principal : [clé, libellé, icône, permission requise]
$menu = [
    ['dashboard',    'Tableau de bord',  'fa-chart-line',     'dashboard.view'],
    ['ventes',       'Point de vente',   'fa-cash-register',  'ventes.view'],
    ['articles',     'Articles / Stock', 'fa-boxes-stacked',  'articles.view'],
    ['clients',      'Clients',          'fa-users',          'clients.view'],
    ['fournisseurs', 'Fournisseurs',    'fa-truck',          'fournisseurs.view'],
    ['proforma',     'Pro Forma',        'fa-file-lines',     'proforma.view'],
    ['bon_commande', 'Bon de commande',  'fa-file-signature', 'bon_commande.view'],
    ['factures',     'Factures',         'fa-file-invoice',   'factures.view'],
    ['pipeline',     'Pipeline Op.',     'fa-diagram-project','pipeline.view'],
    ['caisses',      'Caisses',          'fa-money-bill-wave','caisse.view'],
    ['rapports',     'Rapports',         'fa-chart-pie',      'rapports.view'],
    ['entreprise',   'Entreprise',       'fa-building',       'entreprise.view'],
    ['personnel',    'Personnel',        'fa-user-tie',       'personnel.view'],
    ['boutiques',    'Mes boutiques',    'fa-store',          'boutiques.view'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/app.css">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<script>window.APP_URL = '<?= APP_URL ?>'; sessionStorage.setItem('csrf_token', '<?= csrf_token() ?>');</script>
</head>
<body>
<div class="app">

  <!-- ============ SIDEBAR ============ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="mark">A</div>
      <div class="brand-txt"><b>ALT STORE</b><small>ERP v2</small></div>
    </div>

    <!-- Sélecteur de boutique -->
    <div class="boutique-select">
      <label><i class="fas fa-store"></i> Boutique active</label>
      <div class="bs-current" id="bsCurrent" onclick="toggleBoutiqueDropdown()">
        <span class="bs-dot" style="background:<?= e($bout['couleur'] ?? '#F9A825') ?>"></span>
        <span class="bs-name"><?= e($bout['nom'] ?? '— Aucune —') ?></span>
        <i class="fas fa-chevron-down bs-caret"></i>
      </div>
      <div class="bs-dropdown" id="bsDropdown">
        <?php if (empty($boutiques)): ?>
          <div class="bs-empty">Aucune boutique accessible.<br>
            <a href="<?= APP_URL ?>/boutiques.php">→ Créer une boutique</a>
          </div>
        <?php else: foreach ($boutiques as $b): ?>
          <div class="bs-item <?= (int)$b['id']===active_boutique_id()?'is-active':'' ?>"
               onclick="switchBoutique(<?= (int)$b['id'] ?>)">
            <span class="bs-dot" style="background:<?= e($b['couleur']) ?>"></span>
            <div>
              <div class="bs-item-name"><?= e($b['nom']) ?></div>
              <small><?= e($b['code']) ?> · <em><?= e($b['role_boutique']) ?></em></small>
            </div>
            <?php if ((int)$b['id']===active_boutique_id()): ?><i class="fas fa-check"></i><?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
        <?php if (current_account_role() === 'proprietaire'): ?>
          <a class="bs-new" href="<?= APP_URL ?>/boutiques.php?new=1"><i class="fas fa-plus"></i> Ajouter une boutique</a>
        <?php endif; ?>
      </div>
    </div>

    <nav class="nav">
      <?php foreach ($menu as [$key, $label, $icon, $perm]):
          if (!can($perm)) continue; ?>
        <a href="<?= APP_URL ?>/<?= $key ?>.php" class="nav-item <?= $active===$key?'is-active':'' ?>">
          <i class="fas <?= $icon ?>"></i><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
        <div class="user-meta">
          <b><?= e($userName) ?></b>
          <small><?= e($bout['nom'] ?? '') ?> · <?= e($role ?? '') ?></small>
        </div>
      </div>
      <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Déconnexion"><i class="fas fa-right-from-bracket"></i></a>
    </div>
  </aside>

  <!-- ============ MAIN ============ -->
  <main class="main">
    <header class="topbar">
      <button class="burger" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
      <h1><?= e($title) ?></h1>
      <div class="topbar-right">
        <?php if ($bout): ?>
          <span class="badge-bout"><span class="bs-dot" style="background:<?= e($bout['couleur']) ?>"></span><?= e($bout['code']) ?></span>
        <?php endif; ?>
      </div>
    </header>

    <div class="content" id="flash-zone">
      <?php if ($flash = flash_pull()): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
      <?php endif; ?>
