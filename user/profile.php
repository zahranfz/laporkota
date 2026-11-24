<?php
/**
 * User Profile Page
 * Handles viewing/editing profile information and updating password.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$currentUser = currentUser();
$userId = (int)$currentUser['id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Fetch current password hash from database
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
            flash('error', 'Kata sandi saat ini tidak sesuai.');
            redirect(base_url('user/profile.php'));
        }

        if (strlen($newPassword) < 8) {
            flash('error', 'Kata sandi baru minimal harus 8 karakter.');
            redirect(base_url('user/profile.php'));
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'Konfirmasi kata sandi baru tidak cocok.');
            redirect(base_url('user/profile.php'));
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newHash, $userId);
        if ($updateStmt->execute()) {
            flash('success', 'Kata sandi berhasil diperbarui.');
        } else {
            flash('error', 'Gagal memperbarui kata sandi. Silakan coba lagi.');
        }
        $updateStmt->close();

        redirect(base_url('user/profile.php'));

    } elseif ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            flash('error', 'Nama lengkap tidak boleh kosong.');
            redirect(base_url('user/profile.php'));
        }

        // Get current photo
        $stmt = $conn->prepare("SELECT photo FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $currentPhoto = $stmt->get_result()->fetch_assoc()['photo'] ?? null;
        $stmt->close();

        $photoFilename = $currentPhoto;

        // Check if a new photo is uploaded
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = uploadFile($_FILES['photo']);
            if (isset($upload['error'])) {
                flash('error', $upload['error']);
                redirect(base_url('user/profile.php'));
            }

            // Remove old photo file if it exists
            if (!empty($currentPhoto)) {
                $oldPath = __DIR__ . '/../uploads/' . $currentPhoto;
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $photoFilename = $upload['filename'];
        }

        // Update user profile in database
        $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, photo = ? WHERE id = ?");
        $updateStmt->bind_param("sssi", $name, $phone, $photoFilename, $userId);
        if ($updateStmt->execute()) {
            refreshUserSession($conn);
            flash('success', 'Profil Anda berhasil diperbarui.');
        } else {
            flash('error', 'Gagal memperbarui profil. Silakan coba lagi.');
        }
        $updateStmt->close();

        redirect(base_url('user/profile.php'));
    }
}

// Fetch fresh user data from database
$stmt = $conn->prepare("SELECT id, name, email, phone, role, photo, is_active, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData) {
    logout();
    redirect(base_url('auth/login.php'));
}

$pageTitle = 'Profil Saya';
include __DIR__ . '/../includes/header.php';
?>

<div class="user-profile-page py-4" style="background-color: #F8F9FF; min-height: 80vh;">
    <div class="container" style="max-width: 1200px;">
        <!-- Page Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?= base_url() ?>" class="text-decoration-none" style="color: #0F766E;">Beranda</a></li>
                        <li class="breadcrumb-item active" aria-current="page" style="color: #64748B;">Profil Pengguna</li>
                    </ol>
                </nav>
                <h2 class="fw-bold mb-0" style="color: #1E293B; font-family: 'Poppins', sans-serif;">Profil Saya</h2>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($flashSuccess = get_flash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: 8px; border: 1px solid #BBF7D0; background-color: #F0FDF4; color: #16A34A;">
                <span class="material-symbols-outlined">check_circle</span>
                <div><?= e($flashSuccess) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashError = get_flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: 8px; border: 1px solid #FECACA; background-color: #FEF2F2; color: #DC2626;">
                <span class="material-symbols-outlined">error</span>
                <div><?= e($flashError) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: User Summary Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #FFFFFF; border: 1px solid #E2E8F0;">
                    <div class="card-body text-center p-4">
                        <div class="position-relative d-inline-block mb-3">
                            <?php if (!empty($userData['photo'])): ?>
                                <img src="<?= base_url('uploads/' . e($userData['photo'])) ?>" 
                                     alt="<?= e($userData['name']) ?>" 
                                     class="rounded-circle object-fit-cover shadow-sm" 
                                     style="width: 120px; height: 120px; border: 3px solid #E6F5F3;">
                            <?php else: ?>
                                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto shadow-sm" 
                                     style="width: 120px; height: 120px; background-color: #E6F5F3; color: #0F766E;">
                                    <span class="material-symbols-outlined" style="font-size: 64px;">person</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h4 class="fw-bold mb-1" style="color: #1E293B; font-family: 'Poppins', sans-serif;">
                            <?= e($userData['name']) ?>
                        </h4>
                        <p class="text-secondary small mb-2"><?= e($userData['email']) ?></p>

                        <div class="d-inline-block mb-4">
                            <span class="badge rounded-pill text-uppercase px-3 py-1" 
                                  style="font-size: 0.75rem; letter-spacing: 0.5px; <?= $userData['role'] === 'admin' ? 'background-color: #E6F5F3; color: #0F766E; border: 1px solid #0F766E;' : 'background-color: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0;' ?>">
                                <?= e($userData['role']) ?>
                            </span>
                        </div>

                        <hr style="border-color: #F1F5F9;">

                        <div class="text-start">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-muted fs-5">call</span>
                                <span class="small text-secondary">Telepon:</span>
                                <span class="small fw-semibold ms-auto" style="color: #1E293B;">
                                    <?= !empty($userData['phone']) ? e($userData['phone']) : '<span class="text-muted fst-italic">Belum diisi</span>' ?>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-muted fs-5">calendar_month</span>
                                <span class="small text-secondary">Bergabung:</span>
                                <span class="small fw-semibold ms-auto" style="color: #1E293B;">
                                    <?= formatDate($userData['created_at']) ?>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined text-muted fs-5">verified</span>
                                <span class="small text-secondary">Status Akun:</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-auto" style="border-radius: 50px;">
                                    <?= $userData['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Edit Profile & Change Password Forms -->
            <div class="col-lg-8">
                <!-- Section 1: Edit Profile -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; background: #FFFFFF; border: 1px solid #E2E8F0;">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined" style="color: #0F766E;">badge</span>
                            <h5 class="fw-bold mb-0" style="color: #1E293B; font-family: 'Poppins', sans-serif;">Ubah Profil</h5>
                        </div>
                        <p class="text-secondary small mt-1 mb-0">Perbarui informasi data diri dan foto profil Anda.</p>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="<?= base_url('user/profile.php') ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_profile">

                            <div class="mb-3">
                                <label for="inputName" class="form-label fw-semibold small" style="color: #1E293B;">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                                        <span class="material-symbols-outlined fs-5 text-muted">person</span>
                                    </span>
                                    <input type="text" class="form-control" id="inputName" name="name" 
                                           value="<?= e($userData['name']) ?>" required maxlength="100"
                                           style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="inputEmail" class="form-label fw-semibold small" style="color: #1E293B;">Alamat Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                                        <span class="material-symbols-outlined fs-5 text-muted">mail</span>
                                    </span>
                                    <input type="email" class="form-control bg-light text-muted" id="inputEmail" 
                                           value="<?= e($userData['email']) ?>" disabled readonly
                                           style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                                </div>
                                <div class="form-text small text-muted">Alamat email digunakan untuk masuk akun dan tidak dapat diubah.</div>
                            </div>

                            <div class="mb-3">
                                <label for="inputPhone" class="form-label fw-semibold small" style="color: #1E293B;">Nomor Telepon</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                                        <span class="material-symbols-outlined fs-5 text-muted">call</span>
                                    </span>
                                    <input type="tel" class="form-control" id="inputPhone" name="phone" 
                                           value="<?= e($userData['phone'] ?? '') ?>" placeholder="Contoh: 08123456789" maxlength="20"
                                           style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="inputPhoto" class="form-label fw-semibold small" style="color: #1E293B;">Foto Profil</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                                        <span class="material-symbols-outlined fs-5 text-muted">photo_camera</span>
                                    </span>
                                    <input type="file" class="form-control" id="inputPhoto" name="photo" 
                                           accept="image/jpeg,image/png,image/webp"
                                           style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                                </div>
                                <div class="form-text small text-muted">Format file: JPG, PNG, atau WebP. Ukuran maksimal 2 MB. Kosongkan jika tidak ingin mengubah foto.</div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn text-white fw-semibold d-inline-flex align-items-center gap-2 px-4 py-2" 
                                        style="background-color: #0F766E; border-radius: 8px; border: none;">
                                    <span class="material-symbols-outlined fs-5">save</span>
                                    <span>Simpan Perubahan</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Section 2: Change Password -->
                <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #FFFFFF; border: 1px solid #E2E8F0;">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined" style="color: #0F766E;">lock_reset</span>
                            <h5 class="fw-bold mb-0" style="color: #1E293B; font-family: 'Poppins', sans-serif;">Ubah Kata Sandi</h5>
                        </div>
                        <p class="text-secondary small mt-1 mb-0">Pastikan menggunakan kombinasi kata sandi yang aman dan sulit ditebak.</p>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="<?= base_url('user/profile.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label for="currentPassword" class="form-label fw-semibold small" style="color: #1E293B;">Kata Sandi Saat Ini <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                                        <span class="material-symbols-outlined fs-5 text-muted">lock</span>
                                    </span>
                                    <input type="password" class="form-control" id="currentPassword" name="current_password" required
                                           placeholder="Masukkan kata sandi lama Anda"
                                           style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="newPassword" class="form-label fw-semibold small" style="color: #1E293B;">Kata Sandi Baru <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                                            <span class="material-symbols-outlined fs-5 text-muted">key</span>
                                        </span>
                                        <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="8"
                                               placeholder="Minimal 8 karakter"
                                               style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                                    </div>
                                    <div class="form-text small text-muted">Minimal 8 karakter.</div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label for="confirmPassword" class="form-label fw-semibold small" style="color: #1E293B;">Konfirmasi Kata Sandi Baru <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                                            <span class="material-symbols-outlined fs-5 text-muted">verified_user</span>
                                        </span>
                                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required minlength="8"
                                               placeholder="Ulangi kata sandi baru"
                                               style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn text-white fw-semibold d-inline-flex align-items-center gap-2 px-4 py-2" 
                                        style="background-color: #0F766E; border-radius: 8px; border: none;">
                                    <span class="material-symbols-outlined fs-5">lock</span>
                                    <span>Perbarui Kata Sandi</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
