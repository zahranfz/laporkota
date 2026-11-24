-- ============================================================
-- LaporKota Database Schema
-- Civic Complaint Platform — Purwokerto, Banyumas
-- ============================================================

CREATE DATABASE IF NOT EXISTS laporkota DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laporkota;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS comments, upvotes, complaint_status_logs, complaints, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    phone       VARCHAR(20)   DEFAULT NULL,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('warga','admin') NOT NULL DEFAULT 'warga',
    photo       VARCHAR(255)  DEFAULT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categories ───────────────────────────────────────────────
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL UNIQUE,
    icon        VARCHAR(50)   DEFAULT 'category',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Complaints ───────────────────────────────────────────────
CREATE TABLE complaints (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT           NOT NULL,
    category_id     INT           NOT NULL,
    ticket_code     VARCHAR(20)   NOT NULL UNIQUE,
    title           VARCHAR(255)  NOT NULL,
    description     TEXT          NOT NULL,
    address         VARCHAR(500)  NOT NULL,
    latitude        DECIMAL(10,8) DEFAULT NULL,
    longitude       DECIMAL(11,8) DEFAULT NULL,
    photo           VARCHAR(255)  DEFAULT NULL,
    status          ENUM('Diterima','Diproses','Selesai','Ditolak') NOT NULL DEFAULT 'Diterima',
    admin_response  TEXT          DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_status   (status),
    INDEX idx_user     (user_id),
    INDEX idx_category (category_id),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Status Change Logs ───────────────────────────────────────
CREATE TABLE complaint_status_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id    INT          NOT NULL,
    old_status      VARCHAR(20)  DEFAULT NULL,
    new_status      VARCHAR(20)  NOT NULL,
    changed_by      VARCHAR(100) NOT NULL,
    note            TEXT         DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Upvotes ──────────────────────────────────────────────────
CREATE TABLE upvotes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT      NOT NULL,
    complaint_id    INT      NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_complaint (user_id, complaint_id),
    FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Comments ─────────────────────────────────────────────────
CREATE TABLE comments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id    INT      NOT NULL,
    user_id         INT      NOT NULL,
    body            TEXT     NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA
-- ============================================================

-- ── Users (passwords: admin123, warga123, warga123) ──────────
INSERT INTO users (name, email, phone, password, role, is_active) VALUES
('Admin Banyumas', 'admin@laporkota.id', '08110001111', '$2b$10$qdr5wke9mNfkGaPn5LokVODCmR08A0cBRvqmhPWaTMLxLIOVXskuG', 'admin', 1),
('Budi Santoso',   'budi@email.com',    '08123456789', '$2b$10$1qchmx/PwV4b2Rgu.FG9weRQHwGu1VurIF4jBqWsmTkL33Wgy89x2', 'warga', 1),
('Siti Rahmawati', 'siti@email.com',    '08579881122', '$2b$10$1qchmx/PwV4b2Rgu.FG9weRQHwGu1VurIF4jBqWsmTkL33Wgy89x2', 'warga', 1);

-- ── Categories ───────────────────────────────────────────────
INSERT INTO categories (name, icon) VALUES
('Jalan Rusak',          'add_road'),
('Sampah & Kebersihan',  'delete'),
('Drainase & Banjir',    'water_damage'),
('Lampu Jalan',          'lightbulb'),
('Fasilitas Umum',       'park');

-- ── Complaints ───────────────────────────────────────────────
INSERT INTO complaints (user_id, category_id, ticket_code, title, description, address, latitude, longitude, status, admin_response, created_at) VALUES
(2, 1, 'LK-2026-0001', 'Jalan Berlubang Parah di Depan Pasar Wage',
 'Lubang berdiameter sekitar 1,5 meter dengan kedalaman 15 cm sangat membahayakan pengendara motor terutama saat hujan turun dan tergenang air. Sudah berlangsung lebih dari dua minggu.',
 'Jl. Jend. Sudirman, Purwokerto Timur', -7.4280, 109.2350, 'Diproses',
 'Tim URC Dinas PUPR telah melakukan survei dan menjadwalkan penambalan aspal darurat dalam 2x24 jam.', '2026-02-24 08:45:00'),

(3, 4, 'LK-2026-0002', 'Lampu Penerangan Jalan Mati di Jl. Gerilya',
 'Sebanyak 4 tiang lampu PJU padam total sejak tiga hari terakhir, mengakibatkan ruas jalan gelap gulita dan rawan kecelakaan di malam hari.',
 'Jl. Gerilya, Purwokerto Selatan', -7.4350, 109.2290, 'Diterima',
 NULL, '2026-02-23 14:20:00'),

(2, 3, 'LK-2026-0003', 'Saluran Drainase Tersumbat di Berkoh',
 'Sedimentasi lumpur dan sampah plastik menyumbat gorong-gorong sehingga air meluap ke badan jalan setiap kali hujan deras mengguyur.',
 'Jl. Sunan Bonang, Berkoh', -7.4310, 109.2410, 'Selesai',
 'Pembersihan drainase telah selesai dilaksanakan oleh tim DPU pada tanggal 25 Februari.', '2026-02-21 09:15:00'),

(3, 2, 'LK-2026-0004', 'Penumpukan Sampah di TPS Sementara Sumampir',
 'Tumpukan sampah rumah tangga belum diangkut selama 4 hari, menimbulkan bau tidak sedap dan mengganggu warga sekitar serta pengguna jalan.',
 'Jl. Riyanto, Sumampir, Purwokerto Utara', -7.4140, 109.2320, 'Diproses',
 'Dinas Lingkungan Hidup telah mengirim armada pengangkut ke lokasi.', '2026-02-20 07:30:00'),

(2, 1, 'LK-2026-0005', 'Paving Gang Masuk Perumahan Griya Satria Amblas',
 'Paving blok di dalam area gang perumahan amblas. Ditolak karena merupakan jalan lingkungan swasta yang belum diserahkan ke Pemkab.',
 'Jl. Griya Satria, Bantarsoka', -7.4190, 109.2200, 'Ditolak',
 'Mohon maaf, lokasi yang dilaporkan merupakan jalan lingkungan swasta yang belum diserahkan ke Pemerintah Kabupaten.', '2026-02-18 10:00:00'),

(3, 3, 'LK-2026-0006', 'Genangan Air Setinggi 30 cm di Simpang Karangkobar',
 'Setiap hujan lebih dari 30 menit, simpang empat Karangkobar selalu tergenang cukup tinggi dan menyebabkan kemacetan panjang.',
 'Jl. Dr. Angka, Sokanegara', -7.4260, 109.2300, 'Diterima',
 NULL, '2026-02-17 16:45:00'),

(2, 4, 'LK-2026-0007', 'Tiang Lampu PJU Miring Berbahaya di Jl. Overste Isdiman',
 'Tiang lampu PJU di depan SPBU condong sekitar 30 derajat dan terancam roboh. Sangat berbahaya bagi pengguna jalan dan pejalan kaki.',
 'Jl. Overste Isdiman, Purwokerto Barat', -7.4230, 109.2250, 'Diproses',
 'Tim teknis Dinas Perhubungan telah menjadwalkan penggantian tiang.', '2026-03-01 11:00:00'),

(3, 2, 'LK-2026-0008', 'Sampah Berserakan di Taman Kota Andhang Pangrenan',
 'Area taman kota penuh sampah bekas piknik pengunjung di akhir pekan. Tempat sampah yang ada sudah penuh dan meluber.',
 'Taman Andhang Pangrenan, Purwokerto Selatan', -7.4370, 109.2340, 'Selesai',
 'Pembersihan telah dilaksanakan dan tempat sampah tambahan telah dipasang.', '2026-03-05 08:00:00'),

(2, 5, 'LK-2026-0009', 'Trotoar Rusak di Jl. Jend. Sudirman',
 'Trotoar pejalan kaki rusak parah dan berlubang di depan kantor pos. Pejalan kaki terpaksa berjalan di badan jalan yang berbahaya.',
 'Jl. Jend. Sudirman, Purwokerto Timur', -7.4275, 109.2360, 'Diterima',
 NULL, '2026-03-10 09:30:00'),

(3, 1, 'LK-2026-0010', 'Jalan Retak dan Amblas di Jl. Profesor Suharso',
 'Permukaan jalan retak memanjang sekitar 10 meter dan mulai amblas di sisi kiri. Diduga akibat beban kendaraan berat yang sering melintas.',
 'Jl. Prof. Suharso, Purwokerto Utara', -7.4150, 109.2380, 'Diproses',
 'Survei teknis telah dilakukan. Perbaikan dijadwalkan minggu depan.', '2026-03-12 13:15:00'),

(2, 3, 'LK-2026-0011', 'Saluran Air Meluap di Jl. Kampus',
 'Saluran air di depan kampus Unsoed meluap saat hujan dan menggenangi jalan serta area parkir kampus.',
 'Jl. HR Bunyamin, Grendeng', -7.4200, 109.2440, 'Diterima',
 NULL, '2026-03-15 15:00:00'),

(3, 4, 'LK-2026-0012', 'Lampu Lalu Lintas Mati di Perempatan Palma',
 'Traffic light di perempatan Palma mati total sejak kemarin sore, menyebabkan kemacetan dan hampir terjadi kecelakaan.',
 'Jl. Jend. Sudirman, Purwokerto Timur', -7.4290, 109.2370, 'Diproses',
 'Dinas Perhubungan sedang memperbaiki kerusakan pada panel kontrol.', '2026-03-18 07:00:00'),

(2, 2, 'LK-2026-0013', 'TPA Liar di Lahan Kosong Bancarkembar',
 'Warga membuang sampah sembarangan di lahan kosong pinggir sungai. Sudah menumpuk tinggi dan menimbulkan bau serta lalat.',
 'Bancarkembar, Purwokerto Utara', -7.4130, 109.2400, 'Diterima',
 NULL, '2026-03-20 10:30:00'),

(3, 5, 'LK-2026-0014', 'Bangku Taman Rusak di Alun-Alun Purwokerto',
 'Beberapa bangku taman di alun-alun sudah rusak dan patah. Berbahaya jika diduduki, terutama oleh anak-anak.',
 'Alun-Alun Purwokerto, Sokanegara', -7.4240, 109.2330, 'Selesai',
 'Penggantian bangku taman telah selesai dilaksanakan.', '2026-03-22 14:00:00'),

(2, 1, 'LK-2026-0015', 'Jalan Berlubang di Jl. Martadireja',
 'Lubang jalan cukup besar di dekat pertigaan Martadireja. Sudah ada beberapa motor yang jatuh karena tidak melihat lubang di malam hari.',
 'Jl. Martadireja, Purwokerto Selatan', -7.4340, 109.2310, 'Diterima',
 NULL, '2026-03-25 08:15:00');

-- ── Status Logs ──────────────────────────────────────────────
INSERT INTO complaint_status_logs (complaint_id, old_status, new_status, changed_by, note, created_at) VALUES
(1,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-02-24 08:50:00'),
(1,  'Diterima',  'Diproses',  'Admin Banyumas', 'Diteruskan ke Dinas PUPR.',               '2026-02-25 10:15:00'),
(2,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-02-23 14:25:00'),
(3,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-02-21 09:20:00'),
(3,  'Diterima',  'Diproses',  'Admin Banyumas', 'Tim DPU dijadwalkan untuk pembersihan.',  '2026-02-22 08:00:00'),
(3,  'Diproses',  'Selesai',   'Admin Banyumas', 'Pembersihan drainase selesai.',           '2026-02-25 16:00:00'),
(4,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-02-20 07:35:00'),
(4,  'Diterima',  'Diproses',  'Admin Banyumas', 'Armada pengangkut dikirim.',              '2026-02-21 09:00:00'),
(5,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-02-18 10:05:00'),
(5,  'Diterima',  'Ditolak',   'Admin Banyumas', 'Jalan lingkungan swasta.',                '2026-02-19 11:00:00'),
(6,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-02-17 16:50:00'),
(7,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-01 11:05:00'),
(7,  'Diterima',  'Diproses',  'Admin Banyumas', 'Penggantian tiang dijadwalkan.',          '2026-03-02 09:00:00'),
(8,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-05 08:05:00'),
(8,  'Diterima',  'Diproses',  'Admin Banyumas', 'Tim kebersihan dikirim.',                 '2026-03-06 07:00:00'),
(8,  'Diproses',  'Selesai',   'Admin Banyumas', 'Pembersihan dan penambahan tempat sampah.','2026-03-07 15:00:00'),
(9,  NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-10 09:35:00'),
(10, NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-12 13:20:00'),
(10, 'Diterima',  'Diproses',  'Admin Banyumas', 'Survei teknis selesai.',                  '2026-03-13 10:00:00'),
(11, NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-15 15:05:00'),
(12, NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-18 07:05:00'),
(12, 'Diterima',  'Diproses',  'Admin Banyumas', 'Perbaikan panel kontrol dimulai.',        '2026-03-18 14:00:00'),
(13, NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-20 10:35:00'),
(14, NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-22 14:05:00'),
(14, 'Diterima',  'Diproses',  'Admin Banyumas', 'Penggantian bangku dimulai.',             '2026-03-23 08:00:00'),
(14, 'Diproses',  'Selesai',   'Admin Banyumas', 'Penggantian bangku taman selesai.',       '2026-03-24 16:00:00'),
(15, NULL,        'Diterima',  'Sistem',         'Laporan diterima oleh sistem.',           '2026-03-25 08:20:00');

-- ── Upvotes ──────────────────────────────────────────────────
INSERT INTO upvotes (user_id, complaint_id) VALUES
(2, 2), (2, 3), (2, 4), (2, 6), (2, 8), (2, 12), (2, 14),
(3, 1), (3, 3), (3, 5), (3, 6), (3, 7), (3, 9), (3, 11), (3, 15);

-- ── Comments ─────────────────────────────────────────────────
INSERT INTO comments (complaint_id, user_id, body, created_at) VALUES
(1, 3, 'Betul sekali, kemarin sore hampir ada pengendara motor yang jatuh karena menghindari genangan di titik ini. Semoga segera ditambal.', '2026-02-25 14:30:00'),
(1, 2, 'Update: sudah dipasang pembatas sementara oleh warga sekitar supaya pengendara lebih berhati-hati.', '2026-02-26 08:00:00'),
(3, 2, 'Alhamdulillah sudah bersih. Terima kasih Dinas PUPR.', '2026-02-26 10:00:00'),
(6, 2, 'Setiap hujan pasti banjir di sini. Mohon segera ditangani.', '2026-02-18 09:00:00'),
(6, 3, 'Betul, kemarin motor saya mogok karena banjir di perempatan ini.', '2026-02-19 07:30:00'),
(2, 2, 'Sangat gelap di malam hari. Rawan kejahatan. Mohon prioritaskan.', '2026-02-24 20:00:00'),
(7, 3, 'Berbahaya sekali, apalagi kalau ada angin kencang bisa roboh.', '2026-03-02 15:00:00'),
(10, 3, 'Truk-truk besar memang sering lewat jalur ini. Pantas jalan cepat rusak.', '2026-03-13 11:00:00');
