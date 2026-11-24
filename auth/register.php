<?php
/**
 * LaporKota - Civic Complaint Platform (Purwokerto, Banyumas)
 * User Registration Page
 */

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/helpers.php';

startSession();

// If already logged in, redirect to appropriate page
if (isLoggedIn()) {
    $currentUser = currentUser();
    if ($currentUser && $currentUser['role'] === 'admin') {
        redirect(base_url('admin/dashboard.php'));
    } else {
        redirect(base_url('index.php'));
    }
}

$errors = [];
$name = '';
$email = '';
$phone = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';

    // Validate Name
    if (empty($name)) {
        $errors['name'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'Nama lengkap minimal 3 karakter.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Nama lengkap maksimal 100 karakter.';
    }

    // Validate Email
    if (empty($email)) {
        $errors['email'] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format alamat email tidak valid.';
    } else {
        // Unique check
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = 'Alamat email ini sudah terdaftar. Silakan gunakan email lain atau masuk.';
        }
        $stmt->close();
    }

    // Validate Phone
    if (empty($phone)) {
        $errors['phone'] = 'Nomor telepon wajib diisi.';
    } elseif (!preg_match('/^[0-9+\-\s]{9,20}$/', $phone)) {
        $errors['phone'] = 'Format nomor telepon tidak valid (minimal 9 digit angka).';
    }

    // Validate Password (min 8)
    if (empty($password)) {
        $errors['password'] = 'Kata sandi wajib diisi.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Kata sandi minimal terdiri dari 8 karakter.';
    }

    // Validate Password Confirmation
    if (empty($passwordConfirmation)) {
        $errors['password_confirmation'] = 'Konfirmasi kata sandi wajib diisi.';
    } elseif ($password !== $passwordConfirmation) {
        $errors['password_confirmation'] = 'Konfirmasi kata sandi tidak sesuai dengan kata sandi.';
    }

    // If valid, insert new user
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'warga';
        $isActive = 1;

        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $name, $email, $phone, $hashedPassword, $role, $isActive);

        if ($stmt->execute()) {
            $stmt->close();
            flash('success', 'Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.');
            redirect(base_url('auth/login.php'));
        } else {
            $errors['general'] = 'Gagal mendaftar akun karena kendala sistem. Silakan coba kembali.';
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - LaporKota Purwokerto</title>
    <!-- Google Fonts: Poppins & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <!-- Google Material Symbols Outlined -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-teal: #0F766E;
            --primary-dark: #0D6962;
            --primary-light: #E6F5F3;
            --accent-amber: #F59E0B;
            --accent-dark: #D97706;
            --bg-color: #F8F9FF;
            --surface-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --border-light: #F1F5F9;
            --status-diterima: #2563EB;
            --status-diproses: #F59E0B;
            --status-selesai: #16A34A;
            --status-ditolak: #DC2626;
            --danger-color: #DC2626;
            --success-color: #16A34A;
            --radius-card: 12px;
            --radius-input: 8px;
            --radius-pill: 50px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-elevated: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 24px 16px;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--text-primary);
        }

        .auth-container {
            width: 100%;
            max-width: 1020px;
            background: var(--surface-card);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-elevated);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        /* Left Branded Panel */
        .auth-panel-teal {
            background: linear-gradient(145deg, var(--primary-teal), var(--primary-dark));
            color: #FFFFFF;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .auth-panel-teal::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        .auth-panel-teal::after {
            content: '';
            position: absolute;
            bottom: -60px;
            left: -30px;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
            pointer-events: none;
        }

        .brand-logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #FFFFFF;
            margin-bottom: 32px;
        }

        .brand-logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.16);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            backdrop-filter: blur(4px);
        }

        .brand-logo-text {
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .brand-logo-text span {
            color: #FCD34D;
        }

        .auth-panel-title {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.3;
            color: #FFFFFF;
            margin-bottom: 16px;
        }

        .auth-panel-desc {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
        }

        .benefit-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #FCD34D;
        }

        .benefit-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2px;
            color: #FFFFFF;
        }

        .benefit-sub {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.4;
        }

        .panel-footer-note {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 20px;
            margin-top: 20px;
        }

        /* Right Form Panel */
        .auth-form-panel {
            padding: 40px 40px;
        }

        .form-heading {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .form-subheading {
            font-size: 0.92rem;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-input);
            padding: 9px 14px;
            font-size: 0.92rem;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
            background-image: none;
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
        }

        .invalid-feedback-custom {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--danger-color);
            font-size: 0.82rem;
            margin-top: 5px;
        }

        .input-group-pass {
            position: relative;
        }

        .btn-toggle-pass {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--text-secondary);
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            z-index: 5;
        }

        .btn-toggle-pass:hover {
            color: var(--primary-teal);
        }

        .btn-teal {
            background-color: var(--primary-teal);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-input);
            padding: 12px 20px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-teal:hover, .btn-teal:focus {
            background-color: var(--primary-dark);
            color: #FFFFFF;
            box-shadow: 0 4px 10px rgba(15, 118, 110, 0.3);
        }

        .auth-links {
            margin-top: 20px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-align: center;
        }

        .auth-links a {
            color: var(--primary-teal);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-links a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        .back-home-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.85rem;
            margin-bottom: 16px;
            transition: color 0.2s;
        }

        .back-home-link:hover {
            color: var(--primary-teal);
        }

        .alert-custom-danger {
            background-color: #FEF2F2;
            border: 1px solid #FEE2E2;
            color: #991B1B;
            border-radius: var(--radius-input);
            padding: 12px 16px;
            font-size: 0.875rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        @media (max-width: 767.98px) {
            .auth-panel-teal {
                padding: 32px 24px;
            }
            .auth-form-panel {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="row g-0">
        <!-- Left: Teal Branded Panel -->
        <div class="col-md-5 auth-panel-teal">
            <div>
                <a href="<?= base_url('index.php') ?>" class="brand-logo-wrap">
                    <div class="brand-logo-icon">
                        <span class="material-symbols-outlined" style="font-size: 26px;">campaign</span>
                    </div>
                    <div class="brand-logo-text">Lapor<span>Kota</span></div>
                </a>

                <h1 class="auth-panel-title">Jadilah Bagian dari Perubahan Kota Kita.</h1>
                <p class="auth-panel-desc">
                    Daftar akun warga LaporKota dan sampaikan keluhan fasilitas publik Purwokerto langsung kepada pemerintah secara transparan.
                </p>

                <div class="benefit-item">
                    <div class="benefit-icon-box">
                        <span class="material-symbols-outlined" style="font-size: 20px;">add_a_photo</span>
                    </div>
                    <div>
                        <div class="benefit-title">Laporkan Kapan Saja</div>
                        <div class="benefit-sub">Foto, tulis kronologi, dan tandai lokasi langsung dari gawai Anda.</div>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon-box">
                        <span class="material-symbols-outlined" style="font-size: 20px;">track_changes</span>
                    </div>
                    <div>
                        <div class="benefit-title">Pelacakan Tiket Real-Time</div>
                        <div class="benefit-sub">Dapatkan kode tiket unik untuk memantau tahapan penanganan secara terbuka.</div>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon-box">
                        <span class="material-symbols-outlined" style="font-size: 20px;">forum</span>
                    </div>
                    <div>
                        <div class="benefit-title">Ruang Komentar &amp; Upvote</div>
                        <div class="benefit-sub">Berinteraksi dengan sesama warga dan berikan dukungan untuk aduan prioritas.</div>
                    </div>
                </div>
            </div>

            <div class="panel-footer-note">
                &copy; <?= date('Y') ?> LaporKota Purwokerto. Bersama wujudkan Banyumas yang lebih ramah &amp; nyaman.
            </div>
        </div>

        <!-- Right: Register Form Panel -->
        <div class="col-md-7 auth-form-panel">
            <a href="<?= base_url('index.php') ?>" class="back-home-link">
                <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                Kembali ke Beranda
            </a>

            <h2 class="form-heading">Daftar Akun Baru</h2>
            <p class="form-subheading">Lengkapi data diri Anda untuk memulai menyampaikan pengaduan kota.</p>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert-custom-danger">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--danger-color);">warning</span>
                    <div><?= e($errors['general']) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/register.php') ?>" method="POST" novalidate>
                <?= csrf_field() ?>

                <!-- Nama Lengkap -->
                <div class="mb-3">
                    <label for="name" class="form-label">Nama Lengkap</label>
                    <input type="text"
                           class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                           id="name"
                           name="name"
                           placeholder="Contoh: Budi Santoso"
                           value="<?= e($name) ?>"
                           autocomplete="name"
                           required autofocus>
                    <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback-custom">
                            <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                            <span><?= e($errors['name']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Email & Phone (Grid) -->
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label for="email" class="form-label">Alamat Email</label>
                        <input type="email"
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               id="email"
                               name="email"
                               placeholder="nama@email.com"
                               value="<?= e($email) ?>"
                               autocomplete="email"
                               required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback-custom">
                                <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                                <span><?= e($errors['email']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <label for="phone" class="form-label">Nomor Telepon</label>
                        <input type="tel"
                               class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               id="phone"
                               name="phone"
                               placeholder="08123456789"
                               value="<?= e($phone) ?>"
                               autocomplete="tel"
                               required>
                        <?php if (isset($errors['phone'])): ?>
                            <div class="invalid-feedback-custom">
                                <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                                <span><?= e($errors['phone']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Password & Confirm Password (Grid) -->
                <div class="row g-2 mb-4">
                    <div class="col-sm-6">
                        <label for="password" class="form-label">Kata Sandi (Min. 8 Karakter)</label>
                        <div class="input-group-pass">
                            <input type="password"
                                   class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   id="password"
                                   name="password"
                                   placeholder="Minimal 8 karakter"
                                   autocomplete="new-password"
                                   required>
                            <button type="button" class="btn-toggle-pass" id="btnTogglePassword" aria-label="Tampilkan kata sandi">
                                <span class="material-symbols-outlined" id="togglePasswordIcon" style="font-size: 18px;">visibility</span>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback-custom">
                                <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                                <span><?= e($errors['password']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <label for="password_confirmation" class="form-label">Konfirmasi Kata Sandi</label>
                        <div class="input-group-pass">
                            <input type="password"
                                   class="form-control <?= isset($errors['password_confirmation']) ? 'is-invalid' : '' ?>"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   placeholder="Ulangi kata sandi"
                                   autocomplete="new-password"
                                   required>
                            <button type="button" class="btn-toggle-pass" id="btnTogglePasswordConfirm" aria-label="Tampilkan konfirmasi kata sandi">
                                <span class="material-symbols-outlined" id="togglePasswordConfirmIcon" style="font-size: 18px;">visibility</span>
                            </button>
                        </div>
                        <?php if (isset($errors['password_confirmation'])): ?>
                            <div class="invalid-feedback-custom">
                                <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                                <span><?= e($errors['password_confirmation']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-teal w-100 d-flex align-items-center justify-content-center gap-2">
                    <span class="material-symbols-outlined" style="font-size: 20px;">person_add</span>
                    <span>Daftar Akun Sekarang</span>
                </button>
            </form>

            <div class="auth-links">
                Sudah memiliki akun?
                <a href="<?= base_url('auth/login.php') ?>">Masuk di sini</a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Vanilla JS password visibility toggles
    document.addEventListener('DOMContentLoaded', function () {
        function setupToggle(buttonId, inputId, iconId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (btn && input && icon) {
                btn.addEventListener('click', function () {
                    const isPass = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPass ? 'text' : 'password');
                    icon.textContent = isPass ? 'visibility_off' : 'visibility';
                });
            }
        }

        setupToggle('btnTogglePassword', 'password', 'togglePasswordIcon');
        setupToggle('btnTogglePasswordConfirm', 'password_confirmation', 'togglePasswordConfirmIcon');
    });
</script>
</body>
</html>
