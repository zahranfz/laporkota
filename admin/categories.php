<?php
/**
 * LaporKota Admin - Category Management
 * List categories with complaint counts, add, edit, and delete categories with restrict checks.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Ensure user is authenticated as admin
requireAdmin();

// Set page title for sidebar/topbar
$pageTitle = 'Manajemen Kategori';

// ── 1. Handle POST Actions (Add, Edit, Delete) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim($_POST['action'] ?? '');

    // ── A. ADD CATEGORY ──
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'category');
        if ($icon === '') $icon = 'category';

        if (empty($name)) {
            flash('error', 'Nama kategori wajib diisi.');
            redirect(base_url('admin/categories.php'));
        }

        // Check if name already exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            flash('error', "Kategori dengan nama \"{$name}\" sudah ada.");
            redirect(base_url('admin/categories.php'));
        }

        // Insert new category
        $insertStmt = $conn->prepare("INSERT INTO categories (name, icon, created_at) VALUES (?, ?, NOW())");
        $insertStmt->bind_param("ss", $name, $icon);
        $insertStmt->execute();
        $insertStmt->close();

        flash('success', "Kategori \"{$name}\" berhasil ditambahkan.");
        redirect(base_url('admin/categories.php'));

    // ── B. EDIT CATEGORY ──
    } elseif ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'category');
        if ($icon === '') $icon = 'category';

        if ($id <= 0 || empty($name)) {
            flash('error', 'Data kategori tidak lengkap.');
            redirect(base_url('admin/categories.php'));
        }

        // Check if name already used by another category
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            flash('error', "Kategori \"{$name}\" sudah digunakan oleh kategori lain.");
            redirect(base_url('admin/categories.php'));
        }

        // Update category
        $updateStmt = $conn->prepare("UPDATE categories SET name = ?, icon = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $name, $icon, $id);
        $updateStmt->execute();
        $updateStmt->close();

        flash('success', "Kategori \"{$name}\" berhasil diperbarui.");
        redirect(base_url('admin/categories.php'));

    // ── C. DELETE CATEGORY (RESTRICT IF IN USE) ──
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flash('error', 'ID kategori tidak valid.');
            redirect(base_url('admin/categories.php'));
        }

        // Check whether complaints are referencing this category
        $checkStmt = $conn->prepare("SELECT COUNT(*) AS total, c.name FROM complaints cmp RIGHT JOIN categories c ON cmp.category_id = c.id WHERE c.id = ? GROUP BY c.id, c.name");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $catData = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$catData) {
            flash('error', 'Kategori tidak ditemukan.');
            redirect(base_url('admin/categories.php'));
        }

        $complaintCount = (int)($catData['total'] ?? 0);
        $catName = $catData['name'] ?? 'Kategori';

        if ($complaintCount > 0) {
            flash('error', "Kategori \"{$catName}\" tidak dapat dihapus karena masih digunakan oleh {$complaintCount} pengaduan.");
            redirect(base_url('admin/categories.php'));
        }

        // Delete if no complaints attached
        $delStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $delStmt->bind_param("i", $id);
        $delStmt->execute();
        $delStmt->close();

        flash('success', "Kategori \"{$catName}\" berhasil dihapus.");
        redirect(base_url('admin/categories.php'));
    }
}

// ── 2. Fetch Categories with Complaint Count ────────────────
$query = "SELECT c.id, c.name, c.icon, c.created_at, COUNT(cmp.id) AS complaint_count
FROM categories c
LEFT JOIN complaints cmp ON c.id = cmp.category_id
GROUP BY c.id, c.name, c.icon, c.created_at
ORDER BY c.name ASC";

$result = $conn->query($query);
$categories = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Include layout sidebar and header
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- ── HEADER ACTION ROW ───────────────────────────────────── -->
<div class="card card-custom p-4 mb-4">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
        <div>
            <h5 class="mb-1 fs-6 fw-bold">Daftar Kategori Laporan</h5>
            <p class="text-muted small mb-0">Kelola kategori urusan kota beserta ikon Material Symbols untuk klasifikasi pengaduan.</p>
        </div>
        <div>
            <button type="button" class="btn btn-teal d-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <span class="material-symbols-outlined" style="font-size: 18px;">add_circle</span>
                <span>Tambah Kategori</span>
            </button>
        </div>
    </div>
</div>

<!-- ── CATEGORIES TABLE CARD ───────────────────────────────── -->
<div class="card card-custom overflow-hidden mb-4">
    <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
        <div>
            <span class="fw-semibold">Semua Kategori</span>
            <span class="badge bg-light text-dark ms-2 border"><?= count($categories) ?> kategori</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 90px;">Ikon</th>
                    <th>Nama Kategori</th>
                    <th>Jumlah Pengaduan</th>
                    <th>Tanggal Dibuat</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <span class="material-symbols-outlined d-block mb-2 text-secondary" style="font-size: 40px;">category</span>
                            Belum ada kategori yang ditambahkan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <!-- ID -->
                            <td class="text-secondary small fw-semibold">
                                #<?= (int)$cat['id'] ?>
                            </td>

                            <!-- Icon (Material Symbol displayed as icon) -->
                            <td>
                                <div class="d-inline-flex align-items-center justify-content-center rounded-3 p-2" style="background-color: var(--primary-light); color: var(--primary); width: 40px; height: 40px;" title="Icon: <?= e($cat['icon']) ?>">
                                    <span class="material-symbols-outlined" style="font-size: 22px;">
                                        <?= e($cat['icon']) ?>
                                    </span>
                                </div>
                            </td>

                            <!-- Name -->
                            <td>
                                <div class="fw-semibold" style="color: var(--text-primary); font-size: 0.95rem;">
                                    <?= e($cat['name']) ?>
                                </div>
                                <span class="text-muted small">Icon string: <code><?= e($cat['icon']) ?></code></span>
                            </td>

                            <!-- Jumlah Pengaduan (COUNT) -->
                            <td>
                                <?php $count = (int)$cat['complaint_count']; ?>
                                <?php if ($count > 0): ?>
                                    <a href="<?= base_url('admin/complaints.php?category=' . $cat['id']) ?>" class="badge text-decoration-none" style="background-color: #DBEAFE; color: #1D4ED8; font-size: 0.85rem; padding: 0.35rem 0.65rem;" title="Lihat pengaduan dalam kategori ini">
                                        <?= $count ?> pengaduan
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                                        0 pengaduan
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Created At -->
                            <td class="text-secondary small">
                                <?= formatDate($cat['created_at']) ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <!-- Edit Button -->
                                    <button type="button" class="btn btn-sm btn-light border btn-edit-category"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal"
                                        data-id="<?= $cat['id'] ?>"
                                        data-name="<?= e($cat['name']) ?>"
                                        data-icon="<?= e($cat['icon']) ?>"
                                        title="Ubah Kategori">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">edit</span>
                                    </button>

                                    <!-- Delete Button with Confirmation -->
                                    <button type="button" class="btn btn-sm btn-light border text-danger btn-delete-category"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteCategoryModal"
                                        data-id="<?= $cat['id'] ?>"
                                        data-name="<?= e($cat['name']) ?>"
                                        data-count="<?= $count ?>"
                                        title="<?= $count > 0 ? 'Kategori memiliki pengaduan dan tidak dapat dihapus' : 'Hapus Kategori' ?>">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL TAMBAH KATEGORI ───────────────────────────────── -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card);">
            <form method="POST" action="<?= base_url('admin/categories.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">

                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2" id="addCategoryModalLabel">
                        <span class="material-symbols-outlined" style="color: var(--primary);">add_circle</span>
                        <span>Tambah Kategori Baru</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Category Name -->
                    <div class="mb-3">
                        <label for="addCategoryName" class="form-label fw-semibold small">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="addCategoryName" class="form-control" placeholder="Contoh: Lampu Jalan, Jalan Rusak, Sampah..." required>
                    </div>

                    <!-- Icon Name -->
                    <div class="mb-3">
                        <label for="addCategoryIcon" class="form-label fw-semibold small">Nama Ikon Material Symbol <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <span class="material-symbols-outlined" id="addIconPreview" style="font-size: 20px; color: var(--primary);">category</span>
                            </span>
                            <input type="text" name="icon" id="addCategoryIcon" class="form-control border-start-0" value="category" placeholder="Contoh: lightbulb, add_road, delete..." required>
                        </div>
                        <div class="form-text small">
                            Gunakan nama ikon dari <a href="https://fonts.google.com/icons" target="_blank" class="text-decoration-none">Google Material Symbols</a>.
                        </div>
                    </div>

                    <!-- Quick Icon Suggestions -->
                    <div class="mb-2">
                        <label class="form-label text-muted small d-block mb-1">Rekomendasi Ikon Cepat:</label>
                        <div class="d-flex flex-wrap gap-1">
                            <?php 
                            $suggestedIcons = ['add_road', 'delete', 'water_damage', 'lightbulb', 'park', 'traffic', 'construction', 'campaign', 'report', 'health_and_safety', 'bus_alert', 'nature_people'];
                            foreach ($suggestedIcons as $sIcon): ?>
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2 d-flex align-items-center gap-1 icon-suggestion" data-target="addCategoryIcon" data-preview="addIconPreview" data-icon="<?= $sIcon ?>">
                                    <span class="material-symbols-outlined" style="font-size: 16px;"><?= $sIcon ?></span>
                                    <span style="font-size: 11px;"><?= $sIcon ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 16px;">save</span>
                        <span>Simpan Kategori</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL EDIT KATEGORI ─────────────────────────────────── -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card);">
            <form method="POST" action="<?= base_url('admin/categories.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editCategoryId" value="">

                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2" id="editCategoryModalLabel">
                        <span class="material-symbols-outlined" style="color: var(--primary);">edit</span>
                        <span>Ubah Kategori</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Category Name -->
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label fw-semibold small">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editCategoryName" class="form-control" required>
                    </div>

                    <!-- Icon Name -->
                    <div class="mb-3">
                        <label for="editCategoryIcon" class="form-label fw-semibold small">Nama Ikon Material Symbol <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <span class="material-symbols-outlined" id="editIconPreview" style="font-size: 20px; color: var(--primary);">category</span>
                            </span>
                            <input type="text" name="icon" id="editCategoryIcon" class="form-control border-start-0" required>
                        </div>
                    </div>

                    <!-- Quick Icon Suggestions -->
                    <div class="mb-2">
                        <label class="form-label text-muted small d-block mb-1">Rekomendasi Ikon Cepat:</label>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($suggestedIcons as $sIcon): ?>
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2 d-flex align-items-center gap-1 icon-suggestion" data-target="editCategoryIcon" data-preview="editIconPreview" data-icon="<?= $sIcon ?>">
                                    <span class="material-symbols-outlined" style="font-size: 16px;"><?= $sIcon ?></span>
                                    <span style="font-size: 11px;"><?= $sIcon ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 16px;">save</span>
                        <span>Perbarui Kategori</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL DELETE CONFIRMATION ───────────────────────────── -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card);">
            <form method="POST" action="<?= base_url('admin/categories.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteCategoryId" value="">

                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fs-6 fw-bold text-danger d-flex align-items-center gap-2" id="deleteCategoryModalLabel">
                        <span class="material-symbols-outlined text-danger">warning</span>
                        <span>Konfirmasi Hapus Kategori</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger p-3 mb-2">
                            <span class="material-symbols-outlined" style="font-size: 36px;">delete_forever</span>
                        </div>
                        <h6 class="fw-bold mb-1" id="deleteCategoryName">Nama Kategori</h6>
                        <p class="text-muted small mb-0">Apakah Anda yakin ingin menghapus kategori ini?</p>
                    </div>

                    <!-- Alert if category has complaints -->
                    <div id="deleteBlockedAlert" class="alert alert-danger d-none text-start small mb-0" role="alert">
                        <div class="d-flex align-items-center gap-2 fw-semibold mb-1">
                            <span class="material-symbols-outlined" style="font-size: 18px;">block</span>
                            <span>Kategori Tidak Dapat Dihapus</span>
                        </div>
                        <span id="deleteBlockedReason">Kategori ini sedang digunakan oleh pengaduan dan dilindungi oleh integritas relasi data (RESTRICT).</span>
                    </div>

                    <div id="deleteAllowedAlert" class="alert alert-warning text-start small mb-0" role="alert">
                        Tindakan ini tidak dapat dibatalkan. Kategori yang belum memiliki pengaduan akan dihapus secara permanen.
                    </div>
                </div>

                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="deleteSubmitBtn" class="btn btn-danger btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                        <span>Hapus Permanen</span>
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
    // ── 1. Icon Preview Synchronization ─────────────────────
    function setupIconSync(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (input && preview) {
            input.addEventListener('input', function() {
                const val = input.value.trim();
                preview.textContent = val || 'category';
            });
        }
    }
    setupIconSync('addCategoryIcon', 'addIconPreview');
    setupIconSync('editCategoryIcon', 'editIconPreview');

    // Quick icon suggestion click
    document.querySelectorAll('.icon-suggestion').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = btn.getAttribute('data-target');
            const previewId = btn.getAttribute('data-preview');
            const iconName = btn.getAttribute('data-icon');

            const targetInput = document.getElementById(targetId);
            const previewElem = document.getElementById(previewId);

            if (targetInput) targetInput.value = iconName;
            if (previewElem) previewElem.textContent = iconName;
        });
    });

    // ── 2. Populate Edit Modal ──────────────────────────────
    const editModal = document.getElementById('editCategoryModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const icon = button.getAttribute('data-icon');

            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCategoryIcon').value = icon;
            document.getElementById('editIconPreview').textContent = icon;
        });
    }

    // ── 3. Populate Delete Modal ────────────────────────────
    const deleteModal = document.getElementById('deleteCategoryModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const count = parseInt(button.getAttribute('data-count') || '0', 10);

            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteCategoryName').textContent = name;

            const blockedAlert = document.getElementById('deleteBlockedAlert');
            const allowedAlert = document.getElementById('deleteAllowedAlert');
            const submitBtn = document.getElementById('deleteSubmitBtn');

            if (count > 0) {
                blockedAlert.classList.remove('d-none');
                document.getElementById('deleteBlockedReason').textContent =
                    `Kategori "${name}" masih digunakan oleh ${count} pengaduan. Hapus atau pindahkan pengaduan terkait terlebih dahulu.`;
                allowedAlert.classList.add('d-none');
                submitBtn.disabled = true;
            } else {
                blockedAlert.classList.add('d-none');
                allowedAlert.classList.remove('d-none');
                submitBtn.disabled = false;
            }
        });
    }
});
</script>

</body>
</html>
