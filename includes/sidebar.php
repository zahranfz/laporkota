<?php
/**
 * LaporKota — Civic Complaint Platform
 * Admin Sidebar & Topbar Layout Partial
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireAdmin();

$currentUser   = currentUser();
$currentPage   = basename($_SERVER['PHP_SELF']);
$topbarTitle   = isset($pageTitle) && !empty($pageTitle) ? $pageTitle : 'Dashboard';
$htmlPageTitle = $topbarTitle . ' - LaporKota Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title><?= e($htmlPageTitle) ?></title>

  <!-- Google Fonts: Poppins & Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

  <!-- Google Material Symbols Outlined -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">

  <!-- Bootstrap 5.3 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

  <!-- Leaflet 1.9 CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">

  <!-- CDNs: Bootstrap JS, Leaflet JS, Chart.js 4 -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

  <!-- Main Application JS -->
  <script src="<?= base_url('assets/js/app.js') ?>"></script>
</head>
<body class="admin-body">

  <!-- Admin Fixed Left Sidebar (260px) -->
  <aside class="sidebar">
    <!-- Sidebar Brand / Logo -->
    <div class="sidebar-header">
      <div class="sidebar-logo-icon">
        <span class="material-symbols-outlined">admin_panel_settings</span>
      </div>
      <div class="sidebar-brand-text">
        <span class="sidebar-brand-title">LaporKota Admin</span>
        <span class="sidebar-brand-badge">Kabupaten Banyumas</span>
      </div>
    </div>

    <!-- Navigation Items -->
    <ul class="sidebar-nav">
      <li class="sidebar-label">Menu Administrasi</li>

      <!-- Dashboard -->
      <li class="sidebar-item">
        <a href="<?= base_url('admin/dashboard.php') ?>" class="sidebar-link <?= in_array($currentPage, ['dashboard.php', 'index.php']) ? 'active' : '' ?>">
          <span class="material-symbols-outlined">dashboard</span>
          <span>Dashboard</span>
        </a>
      </li>

      <!-- Pengaduan -->
      <li class="sidebar-item">
        <a href="<?= base_url('admin/complaints.php') ?>" class="sidebar-link <?= in_array($currentPage, ['complaints.php', 'pengaduan.php', 'complaint-detail.php', 'detail-pengaduan.php']) ? 'active' : '' ?>">
          <span class="material-symbols-outlined">inbox</span>
          <span>Pengaduan</span>
        </a>
      </li>

      <!-- Kategori -->
      <li class="sidebar-item">
        <a href="<?= base_url('admin/categories.php') ?>" class="sidebar-link <?= in_array($currentPage, ['categories.php', 'kategori.php']) ? 'active' : '' ?>">
          <span class="material-symbols-outlined">category</span>
          <span>Kategori</span>
        </a>
      </li>

      <!-- Pengguna -->
      <li class="sidebar-item">
        <a href="<?= base_url('admin/users.php') ?>" class="sidebar-link <?= in_array($currentPage, ['users.php', 'pengguna.php']) ? 'active' : '' ?>">
          <span class="material-symbols-outlined">group</span>
          <span>Pengguna</span>
        </a>
      </li>

      <li class="sidebar-divider"></li>
      <li class="sidebar-label">Akses Pintas</li>

      <!-- Kembali ke Beranda -->
      <li class="sidebar-item">
        <a href="<?= base_url('') ?>" class="sidebar-link">
          <span class="material-symbols-outlined">home</span>
          <span>Kembali ke Beranda</span>
        </a>
      </li>

      <!-- Keluar -->
      <li class="sidebar-item">
        <a href="<?= base_url('auth/logout.php') ?>" class="sidebar-link text-white-50" data-confirm="Apakah Anda yakin ingin keluar dari sesi admin?">
          <span class="material-symbols-outlined">logout</span>
          <span>Keluar</span>
        </a>
      </li>
    </ul>
  </aside>

  <!-- Admin Main Layout Wrapper (Margin-Left: 260px) -->
  <div class="admin-wrapper">
    <!-- Admin Topbar -->
    <header class="admin-topbar">
      <div class="admin-topbar-left">
        <!-- Mobile Sidebar Toggler -->
        <button type="button" class="sidebar-mobile-toggle" id="sidebarToggle" aria-label="Buka Menu Sidebar">
          <span class="material-symbols-outlined">menu</span>
        </button>
        <h1 class="admin-topbar-title"><?= e($topbarTitle) ?></h1>
      </div>

      <div class="admin-topbar-right">
        <!-- Admin Profile Info -->
        <div class="admin-user-profile">
          <?php if (!empty($currentUser['photo'])): ?>
            <img src="<?= base_url('uploads/' . e($currentUser['photo'])) ?>" alt="<?= e($currentUser['name']) ?>" class="admin-user-avatar">
          <?php else: ?>
            <div class="admin-user-avatar">
              <?= e(strtoupper(substr($currentUser['name'] ?? 'A', 0, 1))) ?>
            </div>
          <?php endif; ?>
          <div class="admin-user-info d-none d-sm-flex">
            <span class="admin-user-name"><?= e($currentUser['name'] ?? 'Administrator') ?></span>
            <span class="admin-user-role"><?= e(ucfirst($currentUser['role'] ?? 'admin')) ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Admin Flash Messages Container -->
    <?php
    $adminSuccess = get_flash('success');
    $adminError   = get_flash('error');
    $adminWarning = get_flash('warning');
    $adminInfo    = get_flash('info');
    ?>
    <?php if ($adminSuccess || $adminError || $adminWarning || $adminInfo): ?>
      <div class="px-4 pt-3">
        <?php if ($adminSuccess): ?>
          <div class="alert alert-custom alert-custom-success alert-dismissible fade show flash-message" role="alert">
            <span class="material-symbols-outlined">check_circle</span>
            <div class="flex-grow-1"><?= e($adminSuccess) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
          </div>
        <?php endif; ?>

        <?php if ($adminError): ?>
          <div class="alert alert-custom alert-custom-danger alert-dismissible fade show flash-message" role="alert">
            <span class="material-symbols-outlined">error</span>
            <div class="flex-grow-1"><?= e($adminError) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
          </div>
        <?php endif; ?>

        <?php if ($adminWarning): ?>
          <div class="alert alert-custom alert-custom-warning alert-dismissible fade show flash-message" role="alert">
            <span class="material-symbols-outlined">warning</span>
            <div class="flex-grow-1"><?= e($adminWarning) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
          </div>
        <?php endif; ?>

        <?php if ($adminInfo): ?>
          <div class="alert alert-custom alert-custom-info alert-dismissible fade show flash-message" role="alert">
            <span class="material-symbols-outlined">info</span>
            <div class="flex-grow-1"><?= e($adminInfo) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Admin Content Container Open -->
    <div class="admin-content">
