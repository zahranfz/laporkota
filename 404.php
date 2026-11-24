<?php
/**
 * LaporKota — Civic Complaint Platform
 * 404 Not Found Page
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = 'Halaman Tidak Ditemukan (404)';
http_response_code(404);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="error-page-container">
    <div class="error-content">
      <div class="error-code-badge">404</div>
      <h1 class="error-title">Halaman Tidak Ditemukan</h1>
      <p class="error-description">
        Mohon maaf, halaman atau data pengaduan yang Anda cari tidak dapat ditemukan, tautan telah kedaluwarsa, atau alamat URL telah dipindahkan.
      </p>
      <div class="error-actions">
        <a href="<?= base_url('') ?>" class="btn btn-primary-custom btn-lg">
          <span class="material-symbols-outlined">home</span>
          Kembali ke Beranda
        </a>
        <a href="<?= base_url('user/complaint_list.php') ?>" class="btn btn-outline-custom btn-lg">
          <span class="material-symbols-outlined">explore</span>
          Lihat Daftar Pengaduan
        </a>
      </div>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
