<?php
/**
 * LaporKota Admin - User Management
 * List, search, filter, paginate users, toggle active/inactive status, and change user roles.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Ensure user is authenticated as admin
requireAdmin();

// Set page title for sidebar/topbar
$pageTitle = 'Manajemen Pengguna';

$currentAdmin = currentUser();
$currentAdminId = (int)($currentAdmin['id'] ?? 0);

// ── 1. Handle POST Actions (Toggle Active, Change Role) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action        = trim($_POST['action'] ?? '');
    $targetUserId  = (int)($_POST['user_id'] ?? 0);
    $redirectQuery = trim($_POST['redirect_query'] ?? '');

    // ── A. TOGGLE ACTIVE / INACTIVE ──
    if ($action === 'toggle_active') {
        if ($targetUserId <= 0) {
            flash('error', 'ID pengguna tidak valid.');
            redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
        }

        // Security rule: Admin cannot deactivate themselves
        if ($targetUserId === $currentAdminId) {
            flash('error', 'Anda tidak dapat menonaktifkan akun admin Anda sendiri.');
            redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
        }

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, name, is_active FROM users WHERE id = ?");
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            flash('error', 'Pengguna tidak ditemukan.');
            redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
        }

        // Toggle status
        $newActive = $user['is_active'] ? 0 : 1;
        $statusWord = $newActive ? 'diaktifkan' : 'dinonaktifkan';

        $upStmt = $conn->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->bind_param("ii", $newActive, $targetUserId);
        $upStmt->execute();
        $upStmt->close();

        flash('success', "Akun \"{$user['name']}\" berhasil {$statusWord}.");
        redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));

    // ── B. CHANGE ROLE (WARGA / ADMIN) ──
    } elseif ($action === 'change_role') {
        $newRole = trim($_POST['new_role'] ?? '');

        if ($targetUserId <= 0 || !in_array($newRole, ['warga', 'admin'])) {
            flash('error', 'Parameter perubahan role tidak valid.');
            redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
        }

        // Security rule: Admin cannot change their own role
        if ($targetUserId === $currentAdminId) {
            flash('error', 'Anda tidak dapat mengubah role akun admin Anda sendiri.');
            redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
        }

        // Check user exists
        $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            flash('error', 'Pengguna tidak ditemukan.');
            redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
        }

        if ($user['role'] === $newRole) {
            flash('error', "Pengguna \"{$user['name']}\" sudah memiliki role {$newRole}.");
            redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
        }

        $upStmt = $conn->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->bind_param("si", $newRole, $targetUserId);
        $upStmt->execute();
        $upStmt->close();

        flash('success', "Role pengguna \"{$user['name']}\" berhasil diubah menjadi {$newRole}.");
        redirect(base_url('admin/users.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
    }
}

// ── 2. Parse Search & Filter Parameters ─────────────────────
$search       = trim($_GET['search'] ?? '');
$roleFilter   = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

if ($search !== '') {
    $whereClauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchWild = '%' . $search . '%';
    $bindTypes .= 'sss';
    $bindParams[] = $searchWild;
    $bindParams[] = $searchWild;
    $bindParams[] = $searchWild;
}

if ($roleFilter === 'warga' || $roleFilter === 'admin') {
    $whereClauses[] = "role = ?";
    $bindTypes .= 's';
    $bindParams[] = $roleFilter;
}

if ($statusFilter === 'active') {
    $whereClauses[] = "is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereClauses[] = "is_active = 0";
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// ── 3. Count Total Matching Rows (for pagination) ───────────
$countSql = "SELECT COUNT(*) AS total FROM users {$whereSql}";
$countStmt = $conn->prepare($countSql);
if (!empty($bindParams)) {
    $countStmt->bind_param($bindTypes, ...$bindParams);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pagination  = paginate($totalRows, 15, $currentPage);

// ── 4. Fetch Paginated Users ────────────────────────────────
$dataSql = "SELECT id, name, email, phone, role, photo, is_active, created_at
            FROM users
            {$whereSql}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";

$dataBindTypes = $bindTypes . 'ii';
$dataBindParams = $bindParams;
$dataBindParams[] = $pagination['per_page'];
$dataBindParams[] = $pagination['offset'];

$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataBindTypes, ...$dataBindParams);
$dataStmt->execute();
$usersList = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// Build query string for keeping filter state in pagination
$queryParams = $_GET;
unset($queryParams['page']);
$filterQueryString = http_build_query($queryParams);

// Include layout sidebar and header
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- ── FILTER BAR ──────────────────────────────────────────── -->
<div class="card card-custom p-4 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-auto">
            <h5 class="mb-0 fs-6 fw-bold">Filter Pengguna</h5>
        </div>
        <div class="col-12 col-md">
            <form method="GET" action="<?= base_url('admin/users.php') ?>" class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-sm-6 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <span class="material-symbols-outlined" style="font-size: 18px;">search</span>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama, email, atau no. telp..." value="<?= e($search) ?>">
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <select name="role" class="form-select">
                        <option value="">Semua Role</option>
                        <option value="warga" <?= $roleFilter === 'warga' ? 'selected' : '' ?>>Warga</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-12 col-sm-6 col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="col-12 col-sm-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-teal w-100 d-flex align-items-center justify-content-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 18px;">filter_list</span>
                        <span>Filter</span>
                    </button>
                    <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                        <a href="<?= base_url('admin/users.php') ?>" class="btn btn-outline-secondary d-flex align-items-center justify-content-center px-2" title="Reset Filter">
                            <span class="material-symbols-outlined" style="font-size: 18px;">refresh</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── USERS TABLE CARD ────────────────────────────────────── -->
<div class="card card-custom overflow-hidden mb-4">
    <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
        <div>
            <span class="fw-semibold">Daftar Pengguna Terdaftar</span>
            <span class="badge bg-light text-dark ms-2 border"><?= number_format($totalRows, 0, ',', '.') ?> pengguna</span>
        </div>
        <div class="text-muted small">
            Menampilkan <?= count($usersList) ?> dari <?= $totalRows ?> data (Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?>)
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 60px;">Foto</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>No. Telepon</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Tanggal Terdaftar</th>
                    <th class="text-end" style="min-width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usersList)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <span class="material-symbols-outlined d-block mb-2 text-secondary" style="font-size: 40px;">group_off</span>
                            Tidak ada data pengguna yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usersList as $u): ?>
                        <?php $isSelf = ((int)$u['id'] === $currentAdminId); ?>
                        <tr>
                            <!-- Photo (small avatar) -->
                            <td>
                                <?php if (!empty($u['photo'])): ?>
                                    <img src="<?= base_url('uploads/' . $u['photo']) ?>" alt="<?= e($u['name']) ?>" style="width: 38px; height: 38px; object-fit: cover; border-radius: 50%; border: 1px solid var(--border-color);">
                                <?php else: ?>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 38px; height: 38px; background: linear-gradient(135deg, <?= $u['role'] === 'admin' ? '#0F766E, #0D6962' : '#64748B, #475569' ?>); font-size: 0.85rem;">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Nama -->
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 200px;" title="<?= e($u['name']) ?>">
                                    <?= e($u['name']) ?>
                                    <?php if ($isSelf): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size: 0.7rem;">Anda</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-muted small">ID: #<?= (int)$u['id'] ?></span>
                            </td>

                            <!-- Email -->
                            <td>
                                <span class="text-secondary small"><?= e($u['email']) ?></span>
                            </td>

                            <!-- Telepon -->
                            <td>
                                <?php if (!empty($u['phone'])): ?>
                                    <span class="text-secondary small"><?= e($u['phone']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Role Badge -->
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge d-inline-flex align-items-center gap-1" style="background-color: var(--primary-light); color: var(--primary); border: 1px solid rgba(15, 118, 110, 0.2); font-size: 0.78rem; padding: 0.35rem 0.65rem;">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">shield_person</span>
                                        <span>Admin</span>
                                    </span>
                                <?php else: ?>
                                    <span class="badge d-inline-flex align-items-center gap-1 bg-light text-dark border" style="font-size: 0.78rem; padding: 0.35rem 0.65rem;">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">person</span>
                                        <span>Warga</span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Status (Aktif / Nonaktif) -->
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge-status badge-selesai" style="font-size: 0.78rem; padding: 0.25rem 0.65rem;">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">check_circle</span>
                                        <span>Aktif</span>
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status badge-ditolak" style="font-size: 0.78rem; padding: 0.25rem 0.65rem;">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">cancel</span>
                                        <span>Nonaktif</span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Tanggal Terdaftar -->
                            <td class="text-secondary small" style="white-space: nowrap;">
                                <?= formatDate($u['created_at']) ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-end" style="white-space: nowrap;">
                                <?php if ($isSelf): ?>
                                    <span class="badge bg-light text-muted border py-2 px-2" title="Anda tidak dapat mengubah akun Anda sendiri">
                                        <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: -2px;">lock</span>
                                        Terkunci
                                    </span>
                                <?php else: ?>
                                    <!-- Toggle Active / Inactive Button -->
                                    <button type="button" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-toggle-status me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#toggleActiveModal"
                                        data-id="<?= $u['id'] ?>"
                                        data-name="<?= e($u['name']) ?>"
                                        data-active="<?= (int)$u['is_active'] ?>"
                                        title="<?= $u['is_active'] ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">
                                            <?= $u['is_active'] ? 'person_off' : 'person_check' ?>
                                        </span>
                                        <span class="d-none d-xl-inline ms-1"><?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></span>
                                    </button>

                                    <!-- Change Role Button -->
                                    <button type="button" class="btn btn-sm btn-light border btn-change-role"
                                        data-bs-toggle="modal"
                                        data-bs-target="#changeRoleModal"
                                        data-id="<?= $u['id'] ?>"
                                        data-name="<?= e($u['name']) ?>"
                                        data-role="<?= e($u['role']) ?>"
                                        title="Ubah Role Akun">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">swap_horiz</span>
                                        <span class="d-none d-xl-inline ms-1">Role</span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── PAGINATION (15 PER PAGE) ────────────────────────── -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="p-3 px-4 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?>
            </div>

            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <!-- Previous Page -->
                    <li class="page-item <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('admin/users.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1]))) ?>">
                            &laquo; Prev
                        </a>
                    </li>

                    <!-- Page Numbers -->
                    <?php
                    $startPage = max(1, $pagination['current_page'] - 2);
                    $endPage   = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= base_url('admin/users.php?' . http_build_query(array_merge($_GET, ['page' => 1]))) ?>">1</a></li>
                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                            <a class="page-link <?= $p === $pagination['current_page'] ? 'bg-teal border-teal text-white' : '' ?>" href="<?= base_url('admin/users.php?' . http_build_query(array_merge($_GET, ['page' => $p]))) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($endPage < $pagination['total_pages']): ?>
                        <?php if ($endPage < $pagination['total_pages'] - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= base_url('admin/users.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['total_pages']]))) ?>"><?= $pagination['total_pages'] ?></a></li>
                    <?php endif; ?>

                    <!-- Next Page -->
                    <li class="page-item <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('admin/users.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1]))) ?>">
                            Next &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ── CONFIRMATION MODAL: TOGGLE ACTIVE / INACTIVE ────────── -->
<div class="modal fade" id="toggleActiveModal" tabindex="-1" aria-labelledby="toggleActiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card);">
            <form method="POST" action="<?= base_url('admin/users.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="user_id" id="toggleUserId" value="">
                <input type="hidden" name="redirect_query" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">

                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2" id="toggleActiveModalLabel">
                        <span class="material-symbols-outlined" id="toggleHeaderIcon" style="color: var(--primary);">person_outline</span>
                        <span id="toggleModalTitle">Konfirmasi Ubah Status Pengguna</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center p-3 mb-2" id="toggleIconContainer">
                            <span class="material-symbols-outlined" id="toggleBodyIcon" style="font-size: 36px;">help</span>
                        </div>
                        <h6 class="fw-bold mb-1" id="toggleUserName">Nama Pengguna</h6>
                        <p class="text-muted small mb-0" id="togglePromptText">
                            Apakah Anda yakin ingin mengubah status aktif pengguna ini?
                        </p>
                    </div>

                    <div class="alert text-start small mb-0" id="toggleAlertBox" role="alert">
                        <span id="toggleAlertText">Keterangan konsekuensi perubahan status.</span>
                    </div>
                </div>

                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="toggleSubmitBtn" class="btn btn-teal btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 16px;">check</span>
                        <span id="toggleSubmitBtnText">Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── CONFIRMATION MODAL: CHANGE ROLE ─────────────────────── -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card);">
            <form method="POST" action="<?= base_url('admin/users.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" id="changeRoleUserId" value="">
                <input type="hidden" name="redirect_query" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">

                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2" id="changeRoleModalLabel">
                        <span class="material-symbols-outlined" style="color: var(--primary);">admin_panel_settings</span>
                        <span>Ubah Role Pengguna</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded mb-3 border">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-bold" id="changeRoleUserName">Nama Pengguna</span>
                            <span class="badge bg-secondary-subtle text-secondary" id="changeRoleCurrentRole">Warga</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="newRoleSelect" class="form-label fw-semibold small">Pilih Role Baru <span class="text-danger">*</span></label>
                        <select name="new_role" id="newRoleSelect" class="form-select" required>
                            <option value="warga">Warga (Akses Publik / Pelapor)</option>
                            <option value="admin">Admin (Akses Penuh Manajemen)</option>
                        </select>
                        <div class="form-text small">
                            Pengguna dengan role <strong>Admin</strong> memiliki hak penuh mengelola data pengaduan, kategori, dan pengguna sistem.
                        </div>
                    </div>

                    <div class="alert alert-warning text-start small mb-0 d-flex align-items-center gap-2" role="alert">
                        <span class="material-symbols-outlined text-warning" style="font-size: 20px;">info</span>
                        <div>Pastikan pengguna memiliki otorisasi sebelum memberikan akses hak administratif sistem.</div>
                    </div>
                </div>

                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 16px;">save</span>
                        <span>Perbarui Role</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Layout container closing tags -->
        </main>
    </div>
</div>

<!-- JavaScript Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>

<!-- Page Specific Inline Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── 1. Populate Toggle Active Confirmation Modal ────────
    const toggleModal = document.getElementById('toggleActiveModal');
    if (toggleModal) {
        toggleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const isActive = parseInt(button.getAttribute('data-active') || '0', 10);

            document.getElementById('toggleUserId').value = id;
            document.getElementById('toggleUserName').textContent = name;

            const container = document.getElementById('toggleIconContainer');
            const bodyIcon = document.getElementById('toggleBodyIcon');
            const headerIcon = document.getElementById('toggleHeaderIcon');
            const modalTitle = document.getElementById('toggleModalTitle');
            const promptText = document.getElementById('togglePromptText');
            const alertBox = document.getElementById('toggleAlertBox');
            const alertText = document.getElementById('toggleAlertText');
            const submitBtn = document.getElementById('toggleSubmitBtn');
            const submitBtnText = document.getElementById('toggleSubmitBtnText');

            if (isActive === 1) {
                // Action: Deactivate
                modalTitle.textContent = 'Konfirmasi Nonaktifkan Pengguna';
                headerIcon.textContent = 'person_off';
                headerIcon.style.color = '#DC2626';
                container.className = 'rounded-circle d-inline-flex align-items-center justify-content-center p-3 mb-2 bg-danger-subtle text-danger';
                bodyIcon.textContent = 'person_off';
                promptText.textContent = `Apakah Anda yakin ingin menonaktifkan akun "${name}"?`;
                alertBox.className = 'alert alert-danger text-start small mb-0';
                alertText.textContent = 'Pengguna yang dinonaktifkan tidak akan dapat masuk (login) ke platform LaporKota.';
                submitBtn.className = 'btn btn-danger btn-sm d-flex align-items-center gap-1';
                submitBtnText.textContent = 'Nonaktifkan Akun';
            } else {
                // Action: Activate
                modalTitle.textContent = 'Konfirmasi Aktifkan Pengguna';
                headerIcon.textContent = 'person_check';
                headerIcon.style.color = '#16A34A';
                container.className = 'rounded-circle d-inline-flex align-items-center justify-content-center p-3 mb-2 bg-success-subtle text-success';
                bodyIcon.textContent = 'person_check';
                promptText.textContent = `Apakah Anda yakin ingin mengaktifkan kembali akun "${name}"?`;
                alertBox.className = 'alert alert-success text-start small mb-0';
                alertText.textContent = 'Pengguna akan kembali dapat masuk dan membuat serta memantau pengaduan.';
                submitBtn.className = 'btn btn-success btn-sm d-flex align-items-center gap-1';
                submitBtnText.textContent = 'Aktifkan Akun';
            }
        });
    }

    // ── 2. Populate Change Role Modal ───────────────────────
    const roleModal = document.getElementById('changeRoleModal');
    if (roleModal) {
        roleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const role = button.getAttribute('data-role');

            document.getElementById('changeRoleUserId').value = id;
            document.getElementById('changeRoleUserName').textContent = name;
            document.getElementById('changeRoleCurrentRole').textContent = 'Role saat ini: ' + role.toUpperCase();

            // Set default selected new role to opposite
            const select = document.getElementById('newRoleSelect');
            select.value = (role === 'admin') ? 'warga' : 'admin';
        });
    }
});
</script>

</body>
</html>
