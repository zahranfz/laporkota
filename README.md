# LaporKota — Platform Pengaduan Masyarakat

Platform aspirasi dan pelaporan keluhan warga berbasis web untuk pemeliharaan fasilitas umum dan tata kelola perkotaan di Purwokerto, Kabupaten Banyumas, Jawa Tengah.

---

## Daftar Isi
- [Deskripsi](#deskripsi)
- [Latar Belakang](#latar-belakang)
- [Tujuan](#tujuan)
- [Sustainable Development Goals (SDG)](#sustainable-development-goals-sdg)
- [Fitur Utama (9 Modul)](#fitur-utama-9-modul)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi & Konfigurasi](#instalasi--konfigurasi)
- [Akun Demo](#akun-demo)
- [Struktur Folder](#struktur-folder)
- [Alur Penggunaan](#alur-penggunaan)
- [Alur Status Pengaduan](#alur-status-pengaduan)
- [Tim Pengembang](#tim-pengembang)
- [Lisensi](#lisensi)
- [Ucapan Terima Kasih](#ucapan-terima-kasih)

---

## Deskripsi

**LaporKota** adalah platform web responsif yang dirancang sebagai sarana komunikasi dan partisipasi aktif antara masyarakat perkotaan dengan instansi pemerintah di wilayah Purwokerto, Kabupaten Banyumas. Melalui LaporKota, warga dapat menyampaikan laporan mengenai berbagai permasalahan infrastruktur kota—seperti kerusakan jalan, lampu penerangan jalan yang padam, timbunan sampah, banjir akibat drainase tersumbat, dan kerusakan fasilitas umum lainnya—secara terpusat, transparan, dan dapat dipantau langsung perkembangannya.

Sistem ini dikembangkan menggunakan **Native PHP** dan basis data **MySQL (MySQLi)** dengan penekanan pada performa ringan, keamanan tinggi (menggunakan *Prepared Statements* dan proteksi *CSRF*), antarmuka modern dengan **Bootstrap 5** dan **Custom CSS**, serta visualisasi geografis interaktif berbasis **Leaflet.js**.

---

## Latar Belakang

Seiring dengan pesatnya pertumbuhan dan dinamika perkotaan di Purwokerto, pemeliharaan infrastruktur dan pelayanan sarana publik menjadi tantangan krusial bagi pemerintah daerah. Mekanisme pelaporan konvensional sering kali menghadapi beberapa kendala nyata:
1. **Informasi Terfragmentasi:** Pengaduan warga sering kali disampaikan melalui kanal media sosial tidak resmi tanpa mekanisme penomoran tiket yang terstruktur, sehingga mudah terabaikan.
2. **Keterbatasan Akurasi Data:** Laporan yang diterima sering kali tidak memiliki data titik koordinat lokasi yang akurat, menyulitkan tim teknis lapangan saat melakukan survei fisik.
3. **Kurangnya Transparansi Progres:** Masyarakat jarang mendapatkan kepastian mengenai sejauh mana tindak lanjut atas keluhan yang telah diajukan.

LaporKota hadir sebagai jembatan transformasi digital guna meningkatkan partisipasi masyarakat (*citizen participation*) dan mendukung tata kelola kota yang responsif (*digital governance*). Proyek ini disusun sebagai **Tugas Akhir untuk Mata Kuliah Praktikum Pemrograman Web I**, mengintegrasikan prinsip rekayasa perangkat lunak web yang bersih, modular, aman, dan mudah digunakan.

---

## Tujuan

1. **Mewadahi Partisipasi Warga:** Mempermudah warga Purwokerto dalam menyampaikan aspirasi dan laporan permasalahan lingkungan sekitar secara cepat dan terintegrasi melalui peramban web.
2. **Menyajikan Data Berbasis Lokasi (Spasial):** Membantu petugas dan dinas terkait mengidentifikasi titik permasalahan infrastruktur secara presisi dengan dukungan peta interaktif dan koordinat GPS.
3. **Mewujudkan Transparansi Pelayanan Publik:** Menyediakan nomor tiket aduan otomatis serta riwayat log perubahan status (*status tracking logs*) yang terbuka dan dapat dipantau setiap saat.
4. **Membangun Komunitas Peduli Kota:** Menyediakan fitur interaktif berupa dukungan suara (*upvote*) dan forum diskusi (*komentar*) pada setiap laporan guna mengukur urgensi penanganan.
5. **Mengimplementasikan Standar Keamanan Aplikasi Web:** Menerapkan praktik keamanan terbaik pada PHP Native, meliputi pencegahan SQL Injection (*Prepared Statements*), pencegahan XSS (*HTML escaping*), token CSRF (*Cross-Site Request Forgery*), dan validasi berkas unggahan yang ketat.

---

## Sustainable Development Goals (SDG)

Pengembangan LaporKota selaras langsung dengan komitmen global **Sustainable Development Goals (SDG) 11: Kota dan Komunitas yang Berkelanjutan (*Sustainable Cities and Communities*)**.

<div align="center">
  <img src="https://img.shields.io/badge/SDG%2011-Sustainable%20Cities%20%26%20Communities-0F766E?style=for-the-badge&logo=target&logoColor=white" alt="SDG 11 - Sustainable Cities and Communities Badge" />
</div>

<br>

Aplikasi ini mendukung pencapaian indikator-indikator kunci SDG 11:
- **Target 11.3 (Urbanisasi Partisipatif & Inklusif):** Meningkatkan kapasitas tata kelola perkotaan yang inklusif dengan membuka ruang keterlibatan langsung seluruh lapisan masyarakat dalam pengawasan fasilitas kota.
- **Target 11.6 (Pengurangan Dampak Lingkungan Perkotaan):** Mempercepat respons terhadap keluhan penumpukan sampah liar dan drainase yang tersumbat guna menjaga kebersihan serta kualitas lingkungan hidup kota.
- **Target 11.7 (Akses Terhadap Ruang Publik yang Nyaman & Aman):** Mendorong perbaikan segera fasilitas pejalan kaki (trotoar), lampu jalan umum (PJU), dan area terbuka hijau/taman kota demi keselamatan warga.

---

## Fitur Utama (9 Modul)

LaporKota dibangun dengan 9 modul fungsional yang saling terintegrasi:

| No | Modul | Ikon | Deskripsi Fitur |
|:--:|-------|:----:|-----------------|
| 1 | **Autentikasi & Hak Akses** | `lock` | Sistem otentikasi aman untuk dua peran (*Warga* dan *Admin*). Dilengkapi fitur pendaftaran warga, login dengan verifikasi hash bcrypt, sesi berbasis cookie HTTP-Only yang terlindungi, dan proteksi serangan CSRF. |
| 2 | **Profil Pengguna** | `account_circle` | Halaman pengaturan profil untuk memperbarui data diri, nomor kontak, foto profil, serta penggantian kata sandi dengan verifikasi password lama. |
| 3 | **Buat Pengaduan** | `add_comment` | Formulir interaktif pengajuan laporan yang dilengkapi pemilihan kategori, penandaan lokasi presisi pada peta Leaflet.js, penangkapan koordinat otomatis, unggah foto bukti dengan validasi MIME/ekstensi/ukuran, serta pembuatan kode tiket unik otomatis (`LK-YYYY-XXXX`). |
| 4 | **Daftar & Detail Pengaduan** | `view_list` | Halaman jelajah laporan publik dengan filter kategori, penyaringan status, pagination, dan kata kunci pencarian. Halaman detail memuat uraian lengkap, foto, peta lokasi, tanggapan admin, dan riwayat status. |
| 5 | **Riwayat & Pelacakan** | `timeline` | Tab *Pengaduan Saya* untuk memantau daftar riwayat keluhan pribadi warga, serta halaman *Lacak Aduan* publik menggunakan kode tiket tanpa kewajiban login. |
| 6 | **Dukungan & Komentar** | `thumb_up` | Fitur voting dukungan (*upvote*) berbasis AJAX (satu pengguna satu suara) untuk memprioritaskan isu genting, serta kolom komentar untuk diskusi interaktif antarwarga. |
| 7 | **Manajemen Pengaduan (Admin)** | `rule` | Panel verifikasi pengaduan bagi administrator untuk meninjau detail, memperbarui status penanganan (Diterima, Diproses, Selesai, Ditolak), memberikan tanggapan resmi dinas, dan mencatat riwayat perubahan status secara otomatis. |
| 8 | **Manajemen Kategori & Pengguna (Admin)** | `manage_accounts` | Pengelolaan data kategori permasalahan kota (tambah, edit nama dan ikon) serta manajemen daftar akun warga (pengawasan akun aktif/nonaktif). |
| 9 | **Dashboard Statistik & Peta (Admin)** | `analytics` | Dashboard pemantauan komprehensif yang menampilkan metrik total laporan, status tindak lanjut, grafik visual tren kategori menggunakan Chart.js, dan peta sebaran titik aduan di wilayah Purwokerto. |

---

## Teknologi yang Digunakan

Aplikasi ini dibangun menggunakan arsitektur web yang efisien, handal, dan mudah dikelola:

| Lapisan / Komponen | Teknologi | Keterangan & Peran |
|--------------------|-----------|---------------------|
| **Bahasa Pemrograman** | PHP (v7.4 - v8.x) | Native PHP terstruktur dan bersih, tanpa framework monolitik |
| **Basis Data** | MySQL / MariaDB (MySQLi) | Penyimpanan relasional dengan eksekusi query *Prepared Statements* penuh |
| **Tata Letak & Grid** | Bootstrap 5.3 | Pengaturan struktur kontainer, grid responsif, dan utilitas flexbox |
| **Gaya Kustom & Tampilan** | Custom CSS3 | Menggunakan Design Tokens palet warna Teal `#0F766E` dan Amber `#F59E0B` |
| **Ikonografi** | Google Material Symbols Outlined | Pustaka simbol modern yang seragam dan ringan |
| **Tipografi** | Google Fonts | Font *Poppins* untuk heading dan *Inter* untuk keterbacaan teks utama |
| **Peta Digital & Geospasial** | Leaflet.js (v1.9) + OpenStreetMap | Visualisasi titik lokasi laporan dan modul pemilih koordinat interaktif |
| **Grafik & Analitik** | Chart.js (v4) | Visualisasi data diagram lingkaran/batang pada dashboard statistik admin |
| **Interaksi Frontend** | Vanilla JavaScript | Operasi AJAX (Fetch API) tanpa jQuery, penanganan peta, dan interaksi DOM |
| **Keamanan Aplikasi** | Token CSRF, Enkripsi Bcrypt, Sanitasi Input | Penerapan proteksi terhadap serangan OWASP Top 10 |

---

## Persyaratan Sistem

Pastikan perangkat komputer pengembang telah memiliki lingkungan server web lokal dengan spesifikasi berikut:

- **Perangkat Lunak Web Stack:** XAMPP, Laragon, WampServer, atau Docker LAMP stack.
- **PHP Engine:** PHP versi 7.4 atau lebih tinggi (direkomendasikan PHP 8.0 / 8.1 / 8.2).
- **Ekstensi PHP yang Wajib Aktif:**
  - `mysqli` (Koneksi database)
  - `fileinfo` (Validasi MIME-type file unggahan)
  - `session` (Manajemen sesi pengguna)
  - `json` (Pengolahan respon data API/AJAX)
  - `mbstring` (Manipulasi string multi-byte)
- **Database Server:** MySQL 5.7+ atau MariaDB 10.3+.
- **Peramban Web:** Google Chrome, Mozilla Firefox, Microsoft Edge, atau Safari versi terbaru dengan dukungan JavaScript aktif.

---

## Instalasi & Konfigurasi

### Opsi A: Menggunakan Docker Compose (Direkomendasikan & Paling Praktis)

Jika Anda sudah menginstal Docker dan Docker Compose, Anda dapat langsung menjalankan seluruh aplikasi beserta database dan phpMyAdmin tanpa perlu menginstal XAMPP/PHP/MySQL secara manual:

1. **Jalankan Docker Compose:**
   ```bash
   docker compose up -d
   ```
   *Skema database dan data awal (seed data) akan diimpor secara otomatis saat kontainer pertama kali dijalankan.*

2. **Akses Layanan:**
   - **Aplikasi Web:** [http://localhost:8080/](http://localhost:8080/) atau [http://localhost:8080/aduan-kota/](http://localhost:8080/aduan-kota/)
   - **phpMyAdmin:** [http://localhost:8081/](http://localhost:8081/) (Username: `laporkota` / Password: `laporkota` atau Root: `root` / `rootpassword`)

3. **Menghentikan Kontainer:**
   ```bash
   docker compose down
   ```

---

### Opsi B: Menggunakan Web Server Lokal (XAMPP / Laragon)

Ikuti panduan langkah demi langkah berikut untuk memasang dan menjalankan LaporKota di komputer lokal Anda:

### 1. Letakkan Berkas Proyek di Direktori Web Server
Unduh atau klon repositori proyek ini ke dalam direktori publik web server lokal Anda:
- **XAMPP (Windows):** `C:\xampp\htdocs\aduan-kota`
- **Laragon (Windows):** `C:\laragon\www\aduan-kota`
- **Linux (Apache):** `/var/www/html/aduan-kota`
- **macOS (XAMPP):** `/Applications/XAMPP/htdocs/aduan-kota`

### 2. Buat Basis Data Baru
Buka antarmuka pengelola basis data (seperti **phpMyAdmin**) melalui peramban di `http://localhost/phpmyadmin/`.
Jalankan perintah SQL berikut untuk membuat basis data:
```sql
CREATE DATABASE laporkota CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Impor Skema dan Data Awal (Seed Data)
Impor struktur tabel dan data demo yang telah disediakan di dalam proyek:
1. Pilih basis data **`laporkota`** di phpMyAdmin.
2. Buka menu **Import** pada navigasi atas.
3. Klik tombol **Choose File** / **Pilih Berkas** dan arahkan ke file:
   ```
   database/schema.sql
   ```
4. Klik tombol **Import** / **Kirim** di bagian bawah halaman.
*(Alternatif melalui Command Line: `mysql -u root -p laporkota < database/schema.sql`)*

### 4. Konfigurasi Koneksi Basis Data
Buka berkas `config/database.php` menggunakan teks editor pilihan Anda (misalnya VS Code) dan sesuaikan kredensial server MySQL jika berbeda:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // Kosongkan jika menggunakan pengaturan bawaan XAMPP
define('DB_NAME', 'laporkota');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
```

### 5. Akses Aplikasi
Jalankan modul Apache dan MySQL pada panel kontrol XAMPP / Laragon, kemudian buka peramban dan kunjungi alamat URL berikut:
```
http://localhost/aduan-kota/
```

---

## Akun Demo

Untuk memudahkan pengujian fungsionalitas aplikasi, gunakan akun uji coba berikut yang telah terdaftar dalam sistem:

| Peran (*Role*) | Nama Pengguna | Alamat Email | Kata Sandi | Deskripsi Hak Akses |
|----------------|---------------|--------------|------------|---------------------|
| **Admin** | Admin Banyumas | `admin@laporkota.id` | `admin123` | Akses menyeluruh ke panel admin, verifikasi pengaduan, ubah status tiket, beri tanggapan resmi, kelola kategori dan pengguna. |
| **Warga** | Budi Santoso | `budi@email.com` | `warga123` | Akses publik, membuat pengaduan baru, memberikan dukungan (*upvote*), menuliskan komentar, dan melihat riwayat laporan sendiri. |
| **Warga** | Siti Rahmawati | `siti@email.com` | `warga123` | Akses publik, membuat pengaduan baru, memberikan dukungan (*upvote*), menuliskan komentar, dan melihat riwayat laporan sendiri. |

---

## Struktur Folder

Berikut adalah bagan struktur direktori proyek LaporKota:

```
aduan-kota/
├── admin/                     # Modul panel kontrol dan manajemen administrator
│   ├── categories.php         # Modul CRUD kategori pengaduan
│   ├── complaints.php         # Modul peninjauan & verifikasi laporan warga
│   ├── dashboard.php          # Halaman dashboard statistik & analitik utama
│   └── users.php              # Modul pengawasan & status akun pengguna
├── api/                       # Endpoint pemrosesan permintaan asinkron (AJAX)
│   ├── comment.php            # Endpoint kirim dan load komentar aduan
│   ├── stats.php              # Endpoint data statistik analitik dashboard
│   └── upvote.php             # Endpoint toggle dukungan suara (upvote)
├── assets/                    # Aset statis web
│   ├── css/style.css          # Berkas stylesheet kustom komprehensif
│   ├── img/                   # Gambar ilustrasi, logo, dan ikon
│   └── js/app.js              # Berkas skrip interaktif JavaScript vanilla
├── auth/                      # Modul autentikasi pengguna
│   ├── login.php              # Formulir masuk akun & validasi
│   ├── logout.php             # Proses penghentian sesi & pembersihan cookie
│   └── register.php           # Formulir pendaftaran akun warga baru
├── config/                    # Berkas konfigurasi aplikasi
│   └── database.php           # Konfigurasi koneksi MySQLi & charset
├── database/                  # Skrip basis data
│   └── schema.sql             # Skema DDL tabel relasional & data benih (seed)
├── includes/                  # Pustaka fungsi pembantu & tata letak bersama
│   ├── auth.php               # Fungsi bantuan autentikasi, session & otorisasi
│   ├── footer.php             # Komponen tata letak kaki halaman
│   ├── header.php             # Komponen header HTML & penyertaan pustaka CDN
│   ├── helpers.php            # Fungsi bantuan umum (CSRF, sanitasi, tanggal, unggah)
│   └── sidebar.php            # Navigasi sidebar admin responsif
├── uploads/                   # Direktori penyimpanan berkas foto yang diunggah
├── user/                      # Modul halaman warga & pengaduan publik
│   ├── complaint_create.php   # Formulir pembuatan & edit pengaduan + peta Leaflet
│   ├── complaint_detail.php   # Halaman detail rincian pengaduan, peta, & komentar
│   ├── complaint_list.php     # Katalog publik pencarian & filter pengaduan
│   ├── history.php            # Riwayat pengaduan pengguna dengan aksi hapus
│   └── profile.php            # Pengelolaan informasi profil & ganti kata sandi
├── 404.php                    # Halaman penanganan error rute tidak ditemukan (404)
├── index.php                  # Halaman beranda utama (Landing Page)
└── README.md                  # Dokumentasi teknis proyek
```

---

## Alur Penggunaan

### 1. Alur Penggunaan Warga (*Citizen Flow*)
```
[Registrasi / Masuk Akun]
           │
           ▼
[Akses Halaman "Buat Pengaduan"]
           │
           ▼
[Isi Judul, Deskripsi, Kategori & Unggah Foto]
           │
           ▼
[Tentukan Titik Masalah pada Peta Interaktif]
           │
           ▼
[Kirim Pengaduan] ──► Menerima Kode Tiket Unik (misal: LK-2026-0001)
           │
           ▼
[Pantau Progres via "Pengaduan Saya" atau "Lacak Tiket"]
```
- **Interaksi Tambahan:** Warga dapat membuka katalog pengaduan untuk memberikan dukungan (*upvote*) pada laporan warga lain yang dianggap mendesak, atau menambahkan informasi pendukung melalui kolom diskusi komentar.

### 2. Alur Penggunaan Administrator (*Admin Flow*)
```
[Masuk dengan Akun Admin]
           │
           ▼
[Pantau Ringkasan di Dashboard Admin (Metrik, Grafik & Peta Persebaran)]
           │
           ▼
[Buka Menu "Manajemen Pengaduan"]
           │
           ▼
[Tinjau Kelayakan Berkas & Lokasi Aduan]
           │
           ▼
[Perbarui Status (Diterima / Diproses / Selesai / Ditolak) + Tuliskan Tanggapan Resmi]
           │
           ▼
[Sistem Menyimpan Riwayat Log Status Secara Otomatis]
```
- **Pengawasan Tambahan:** Administrator dapat menambah/menyesuaikan master kategori laporan dan memantau akun-akun pengguna yang terdaftar di platform.

---

## Alur Status Pengaduan

Setiap keluhan yang masuk ke dalam sistem akan melalui tahapan siklus hidup status yang teratur dan transparan:

```
                  ┌──────────────┐
                  │   Diterima   │
                  └──────┬───────┘
                         │
           ┌─────────────┴─────────────┐
           ▼                           ▼
    ┌──────────────┐            ┌──────────────┐
    │   Diproses   │            │   Ditolak    │
    └──────┬───────┘            └──────────────┘
           │
           ▼
    ┌──────────────┐
    │   Selesai    │
    └──────────────┘
```

### Rincian Penjelasan Status:
1. **Diterima (`#2563EB` - Biru):**
   - Laporan baru saja diajukan oleh warga dan tersimpan ke dalam basis data.
   - Laporan telah diverifikasi oleh sistem dan masuk ke dalam antrean peninjauan oleh petugas administrator.
2. **Diproses (`#F59E0B` - Oranye/Kuning):**
   - Laporan telah ditelaah dan diteruskan ke satuan kerja perangkat daerah / dinas teknis terkait (seperti Dinas PUPR, Dinas Lingkungan Hidup, atau Dinas Perhubungan).
   - Petugas mulai melakukan peninjauan lokasi atau menjadwalkan tindakan perbaikan fisik di lapangan.
3. **Selesai (`#16A34A` - Hijau):**
   - Pekerjaan perbaikan atau pembersihan fasilitas telah rampung dituntaskan oleh instansi terkait.
   - Administrator menyertakan tanggapan resmi penyelesaian pekerjaan sehingga warga dapat memverifikasi hasil tindak lanjut.
4. **Ditolak (`#DC2626` - Merah):**
   - Laporan tidak dapat ditindaklanjuti oleh pemerintah kota karena alasan tertentu (misalnya lokasi merupakan fasilitas privat perumahan yang belum diserahkan ke Pemkab, informasi palsu/kurang lengkap, atau laporan duplikasi).
   - Administrator wajib mencantumkan alasan penolakan secara jelas pada kolom tanggapan demi akuntabilitas publik.

---

## Tim Pengembang

Aplikasi LaporKota dikembangkan oleh tim mahasiswa:

| No | Nama Lengkap | NIM | Peran Utama (*Role*) |
|:--:|--------------|:---:|----------------------|
| 1 | [Zahran Febrian Nugraha] | [H1D025074] | **Project Lead & Fullstack Developer** |
| 2 | [Heksa Aji Permana] | [H1D025098] | **Frontend Developer & UI/UX Specialist** |
| 3 | [Reza Kurniawan] | [H1D025099] | **Backend Developer & Database Architect** |

---

## Lisensi

Proyek ini dibuat dan dipublikasikan di bawah lisensi terbuka [MIT License](https://opensource.org/licenses/MIT). Kode sumber bebas dipelajari, dikembangkan, dan dimodifikasi untuk tujuan akademik serta pengembangan solusi digital masyarakat.

---

## Ucapan Terima Kasih

Ucapan terima kasih yang tulus kami haturkan kepada seluruh pihak yang telah memberikan dukungan, bimbingan, dan inspirasi dalam penyusunan proyek ini:
- **Program Studi & Fakultas:** Jurusan Informatika, Fakultas Ilmu Komputer.
- **Dosen Pengampu & Tim Asisten Laboratorium:** Seluruh dosen dan asisten praktikum mata kuliah **Praktikum Pemrograman Web I**, atas arahan materi pemrograman web, bimbingan teknis, dan evaluasi berkala yang diberikan.
- **Pemerintah Kabupaten Banyumas & Warga Purwokerto:** Atas ketersediaan data terbuka dan inspirasi kebutuhan sistem pelayanan perkotaan yang lebih baik.
