<?php
/**
 * LaporKota — Civic Complaint Platform
 * Public Page Header Partial
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
$currentUser = currentUser();
$pageTitle = isset($pageTitle) && !empty($pageTitle)
    ? $pageTitle . ' - LaporKota'
    : 'LaporKota - Platform Pengaduan Masyarakat Purwokerto';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="LaporKota Purwokerto - Wadah aspirasi dan pengaduan infrastruktur serta fasilitas publik di Banyumas.">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title><?= e($pageTitle) ?></title>

  <!-- Google Fonts: Poppins (600,700) & Inter (400,500,600) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

  <!-- Google Material Symbols Outlined -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">

  <!-- Bootstrap 5.3 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

  <!-- Leaflet 1.9 CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

  <!-- Custom Stylesheet -->
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

  <!-- Sticky Navbar -->
  <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
      <!-- Logo "LaporKota" (Teal text with city icon) -->
      <a class="navbar-brand" href="<?= base_url('') ?>">
        <span class="brand-icon-box">
          <span class="material-symbols-outlined">location_city</span>
        </span>
        <span>Lapor<span class="brand-city">Kota</span></span>
      </a>

      <!-- Mobile Toggle Button -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigasi">
        <span class="material-symbols-outlined">menu</span>
      </button>

      <!-- Nav Links & Auth Area -->
      <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
          <li class="nav-item">
            <a class="nav-link <?= in_array($currentScript, ['index.php', '']) ? 'active' : '' ?>" href="<?= base_url('') ?>">
              <span class="material-symbols-outlined md-20">home</span>
              Beranda
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= in_array($currentScript, ['complaint_list.php', 'complaint_detail.php']) ? 'active' : '' ?>" href="<?= base_url('user/complaint_list.php') ?>">
              <span class="material-symbols-outlined md-20">list_alt</span>
              Daftar Pengaduan
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalLacakTiket">
              <span class="material-symbols-outlined md-20">search_check</span>
              Lacak Aduan
            </a>
          </li>
        </ul>

        <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
          <?php if (isLoggedIn()): ?>
            <!-- User Dropdown (Profil, Riwayat Saya, Keluar) -->
            <div class="dropdown navbar-user-dropdown">
              <a href="#" class="dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (!empty($currentUser['photo'])): ?>
                  <img src="<?= base_url('uploads/' . e($currentUser['photo'])) ?>" alt="<?= e($currentUser['name']) ?>" class="navbar-user-avatar">
                <?php else: ?>
                  <span class="navbar-user-avatar"><?= e(strtoupper(substr($currentUser['name'] ?? 'U', 0, 1))) ?></span>
                <?php endif; ?>
                <span class="navbar-user-name d-none d-sm-inline"><?= e($currentUser['name']) ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom">
                <?php if ($currentUser['role'] === 'admin'): ?>
                  <li>
                    <a class="dropdown-item" href="<?= base_url('admin/dashboard.php') ?>">
                      <span class="material-symbols-outlined md-18 text-teal">dashboard</span>
                      Panel Admin
                    </a>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li>
                  <a class="dropdown-item" href="<?= base_url('user/profile.php') ?>">
                    <span class="material-symbols-outlined md-18">person</span>
                    Profil
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="<?= base_url('user/history.php') ?>">
                    <span class="material-symbols-outlined md-18">history</span>
                    Riwayat Saya
                  </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <a class="dropdown-item text-danger" href="<?= base_url('auth/logout.php') ?>">
                    <span class="material-symbols-outlined md-18">logout</span>
                    Keluar
                  </a>
                </li>
              </ul>
            </div>
          <?php else: ?>
            <!-- Guest Buttons (Masuk / Daftar) -->
            <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline-custom btn-sm">
              <span class="material-symbols-outlined md-18">login</span>
              Masuk
            </a>
            <a href="<?= base_url('auth/register.php') ?>" class="btn btn-primary-custom btn-sm">
              <span class="material-symbols-outlined md-18">person_add</span>
              Daftar
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Flash Message Display Area -->
  <?php
  $flashSuccess = get_flash('success');
  $flashError   = get_flash('error');
  $flashWarning = get_flash('warning');
  $flashInfo    = get_flash('info');
  ?>
  <?php if ($flashSuccess || $flashError || $flashWarning || $flashInfo): ?>
    <div class="container mt-3">
      <?php if ($flashSuccess): ?>
        <div class="alert alert-custom alert-custom-success alert-dismissible fade show flash-message" role="alert">
          <span class="material-symbols-outlined">check_circle</span>
          <div class="flex-grow-1"><?= e($flashSuccess) ?></div>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashError): ?>
        <div class="alert alert-custom alert-custom-danger alert-dismissible fade show flash-message" role="alert">
          <span class="material-symbols-outlined">error</span>
          <div class="flex-grow-1"><?= e($flashError) ?></div>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashWarning): ?>
        <div class="alert alert-custom alert-custom-warning alert-dismissible fade show flash-message" role="alert">
          <span class="material-symbols-outlined">warning</span>
          <div class="flex-grow-1"><?= e($flashWarning) ?></div>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
      <?php endif; ?>

      <?php if ($flashInfo): ?>
        <div class="alert alert-custom alert-custom-info alert-dismissible fade show flash-message" role="alert">
          <span class="material-symbols-outlined">info</span>
          <div class="flex-grow-1"><?= e($flashInfo) ?></div>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Modal Lacak Aduan / Tiket -->
  <div class="modal fade" id="modalLacakTiket" tabindex="-1" aria-labelledby="modalLacakTiketLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
        <div class="modal-header" style="border-bottom: 1px solid #F1F5F9; padding: 1.25rem 1.5rem;">
          <div class="d-flex align-items-center gap-2">
            <span class="material-symbols-outlined text-teal" style="font-size: 26px;">manage_search</span>
            <h5 class="modal-title fw-bold mb-0" id="modalLacakTiketLabel" style="font-family: 'Poppins', sans-serif; font-size: 1.15rem; color: #1E293B;">
              Lacak Status Pengaduan
            </h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <form action="<?= base_url('user/complaint_detail.php') ?>" method="GET">
          <div class="modal-body p-4">
            <p class="text-secondary small mb-3">
              Masukkan kode tiket pengaduan yang Anda miliki (contoh: <strong>LK-2026-0001</strong>) untuk memeriksa riwayat dan progres penanganan terkini.
            </p>
            <div class="mb-3">
              <label for="inputTicketCode" class="form-label small fw-semibold text-dark">Kode Tiket Pengaduan</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted">
                  <span class="material-symbols-outlined md-20">tag</span>
                </span>
                <input type="text" class="form-control border-start-0" id="inputTicketCode" name="ticket" placeholder="LK-2026-XXXX" required autofocus style="text-transform: uppercase;">
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light border-top-0 p-3" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary-custom btn-sm px-4">
              <span class="material-symbols-outlined md-18">search</span>
              Lacak Sekarang
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Public Main Content Area -->
  <main class="main-content">
