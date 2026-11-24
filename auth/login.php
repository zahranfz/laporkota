<?php
/**
 * LaporKota - Civic Complaint Platform (Purwokerto, Banyumas)
 * User Login Page
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
$email = '';
$flashSuccess = get_flash('success');
$flashError = get_flash('error');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email)) {
        $errors['email'] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format alamat email tidak valid.';
    }

    if (empty($password)) {
        $errors['password'] = 'Kata sandi wajib diisi.';
    }

    if (empty($errors)) {
        // Query user using prepared statement
        $stmt = $conn->prepare("SELECT id, name, email, phone, password, role, photo, is_active FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors['auth'] = 'Email atau kata sandi yang Anda masukkan salah.';
        } elseif ((int)$user['is_active'] !== 1) {
            $errors['auth'] = 'Akun Anda sedang dinonaktifkan. Silakan hubungi admin pengelola.';
        } else {
            // Set session and redirect
            setUserSession($user);
            flash('success', 'Selamat datang kembali, ' . $user['name'] . '!');

            if ($user['role'] === 'admin') {
                redirect(base_url('admin/dashboard.php'));
            } else {
                redirect(base_url('index.php'));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - LaporKota Purwokerto</title>
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
            max-width: 1000px;
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

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
        }

        .feature-icon-box {
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

        .feature-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2px;
            color: #FFFFFF;
        }

        .feature-sub {
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
            padding: 48px 40px;
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
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-input);
            padding: 10px 14px;
            font-size: 0.95rem;
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

        .form-check-input:checked {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
        }

        .form-check-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            user-select: none;
        }

        .auth-links {
            margin-top: 24px;
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
            margin-bottom: 20px;
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

        .alert-custom-success {
            background-color: #F0FDF4;
            border: 1px solid #DCFCE7;
            color: #166534;
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

                <h1 class="auth-panel-title">Suara Warga, Solusi Nyata untuk Kota.</h1>
                <p class="auth-panel-desc">
                    Wadah terpadu aspirasi dan laporan infrastruktur Purwokerto &amp; Kabupaten Banyumas yang cepat, transparan, dan terukur.
                </p>

                <div class="feature-item">
                    <div class="feature-icon-box">
                        <span class="material-symbols-outlined" style="font-size: 20px;">bolt</span>
                    </div>
                    <div>
                        <div class="feature-title">Respons Cepat</div>
                        <div class="feature-sub">Laporan diteruskan langsung ke instansi terkait dalam waktu 1x24 jam.</div>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon-box">
                        <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                    </div>
                    <div>
                        <div class="feature-title">Transparan &amp; Terbuka</div>
                        <div class="feature-sub">Pantau tahapan tindak lanjut laporan Anda secara berkala dan akuntabel.</div>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon-box">
                        <span class="material-symbols-outlined" style="font-size: 20px;">verified</span>
                    </div>
                    <div>
                        <div class="feature-title">Dukungan Warga</div>
                        <div class="feature-sub">Beri dukungan suara (upvote) pada masalah kota yang butuh penanganan prioritas.</div>
                    </div>
                </div>
            </div>

            <div class="panel-footer-note">
                &copy; <?= date('Y') ?> LaporKota Purwokerto. Mendukung SDG 11 Kota Inklusif &amp; Berkelanjutan.
            </div>
        </div>

        <!-- Right: Login Form Panel -->
        <div class="col-md-7 auth-form-panel">
            <a href="<?= base_url('index.php') ?>" class="back-home-link">
                <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                Kembali ke Beranda
            </a>

            <h2 class="form-heading">Masuk ke Akun</h2>
            <p class="form-subheading">Masukkan email dan kata sandi Anda untuk mengakses layanan.</p>

            <?php if (!empty($flashSuccess)): ?>
                <div class="alert-custom-success">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--success-color);">check_circle</span>
                    <div><?= e($flashSuccess) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <div class="alert-custom-danger">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--danger-color);">error</span>
                    <div><?= e($flashError) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['auth'])): ?>
                <div class="alert-custom-danger">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--danger-color);">warning</span>
                    <div><?= e($errors['auth']) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/login.php') ?>" method="POST" novalidate>
                <?= csrf_field() ?>

                <!-- Email Input -->
                <div class="mb-3">
                    <label for="email" class="form-label">Alamat Email</label>
                    <input type="email"
                           class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           id="email"
                           name="email"
                           placeholder="nama@email.com"
                           value="<?= e($email) ?>"
                           autocomplete="email"
                           required autofocus>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback-custom">
                            <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                            <span><?= e($errors['email']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Password Input -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label mb-0">Kata Sandi</label>
                    </div>
                    <div class="input-group-pass">
                        <input type="password"
                               class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               id="password"
                               name="password"
                               placeholder="Masukkan kata sandi"
                               autocomplete="current-password"
                               required>
                        <button type="button" class="btn-toggle-pass" id="btnTogglePassword" aria-label="Tampilkan kata sandi">
                            <span class="material-symbols-outlined" id="togglePasswordIcon" style="font-size: 20px;">visibility</span>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback-custom">
                            <span class="material-symbols-outlined" style="font-size: 16px;">error</span>
                            <span><?= e($errors['password']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                        <label class="form-check-label" for="remember">
                            Ingat saya
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-teal w-100 d-flex align-items-center justify-content-center gap-2">
                    <span class="material-symbols-outlined" style="font-size: 20px;">login</span>
                    <span>Masuk Sekarang</span>
                </button>
            </form>

            <div class="auth-links">
                Belum memiliki akun?
                <a href="<?= base_url('auth/register.php') ?>">Daftar di sini</a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Vanilla JS password toggle visibility
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('btnTogglePassword');
        const passInput = document.getElementById('password');
        const passIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passInput && passIcon) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isPassword ? 'text' : 'password');
                passIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
            });
        }
    });
</script>
</body>
</html>
