  <!-- Public Main Content Close -->
  </main>

  <!-- Public Footer -->
  <footer class="footer-custom">
    <div class="container">
      <div class="row g-4">
        <!-- Kolom 1: Tentang LaporKota & SDG 11 -->
        <div class="col-lg-5 col-md-6">
          <a href="<?= base_url('') ?>" class="footer-brand">
            <span class="material-symbols-outlined">location_city</span>
            <span>Lapor<span style="color: var(--accent);">Kota</span> Purwokerto</span>
          </a>
          <p class="footer-desc">
            Platform pengaduan dan aspirasi warga Purwokerto, Banyumas untuk melaporkan kerusakan fasilitas umum, infrastruktur jalan, drainase banjir, persampahan, dan penerangan jalan secara transparan dan responsif.
          </p>
          <div class="sdg-badge">
            <span class="sdg-badge-icon">SDG 11</span>
            <span>Mendukung Kota dan Komunitas yang Berkelanjutan</span>
          </div>
        </div>

        <!-- Kolom 2: Tautan Cepat -->
        <div class="col-lg-3 col-md-6">
          <h5 class="footer-heading">Tautan Navigasi</h5>
          <ul class="footer-links">
            <li>
              <a href="<?= base_url('') ?>">
                <span class="material-symbols-outlined md-16">chevron_right</span>
                Beranda
              </a>
            </li>
            <li>
              <a href="<?= base_url('user/complaint_list.php') ?>">
                <span class="material-symbols-outlined md-16">chevron_right</span>
                Daftar Pengaduan
              </a>
            </li>
            <li>
              <a href="<?= base_url('user/complaint_create.php') ?>">
                <span class="material-symbols-outlined md-16">chevron_right</span>
                Buat Pengaduan Baru
              </a>
            </li>
            <?php if (isLoggedIn()): ?>
              <li>
                <a href="<?= base_url('user/history.php') ?>">
                  <span class="material-symbols-outlined md-16">chevron_right</span>
                  Riwayat Pengaduan Saya
                </a>
              </li>
            <?php else: ?>
              <li>
                <a href="<?= base_url('auth/login.php') ?>">
                  <span class="material-symbols-outlined md-16">chevron_right</span>
                  Masuk Akun
                </a>
              </li>
              <li>
                <a href="<?= base_url('auth/register.php') ?>">
                  <span class="material-symbols-outlined md-16">chevron_right</span>
                  Daftar Warga
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Kolom 3: Kontak & Informasi Pelayanan -->
        <div class="col-lg-4 col-md-12">
          <h5 class="footer-heading">Kontak & Layanan</h5>
          <ul class="footer-contact-list">
            <li class="footer-contact-item">
              <span class="material-symbols-outlined">location_on</span>
              <span>Gedung Smart Room Pemkab Banyumas, Jl. Kabupaten No. 1, Purwokerto, Banyumas, Jawa Tengah 53115</span>
            </li>
            <li class="footer-contact-item">
              <span class="material-symbols-outlined">mail</span>
              <span>pengaduan@laporkota.banyumaskab.go.id</span>
            </li>
            <li class="footer-contact-item">
              <span class="material-symbols-outlined">call</span>
              <span>(0281) 635-001 / WhatsApp: 0811-2345-6789</span>
            </li>
            <li class="footer-contact-item">
              <span class="material-symbols-outlined">schedule</span>
              <span>Jam Operasional: Senin – Jumat, 08.00 – 16.00 WIB</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Copyright Bottom Bar -->
      <div class="footer-bottom">
        <div>
          &copy; <?= date('Y') ?> <strong>LaporKota Banyumas</strong>. Hak Cipta Dilindungi.
        </div>
        <div class="d-flex align-items-center gap-3">
          <span>Inisiatif Pelayanan Publik Terbuka Purwokerto</span>
        </div>
      </div>
    </div>
  </footer>

  <!-- Toast Notification Container -->
  <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>

  <!-- CDN JS: Bootstrap Bundle & Leaflet JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

  <!-- Application Main JavaScript -->
  <script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
