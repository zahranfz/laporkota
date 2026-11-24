<?php
/**
 * LaporKota Admin - Complaints Management
 * List, filter, search, paginate complaints, view details, and handle status transitions with logs.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Ensure user is authenticated as admin
requireAdmin();

// Set page title for sidebar/topbar
$pageTitle = 'Manajemen Pengaduan';

// ── 1. Handle Status Change POST ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $complaintId   = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
    $newStatus     = trim($_POST['new_status'] ?? '');
    $adminResponse = trim($_POST['admin_response'] ?? '');
    $redirectQuery = trim($_POST['redirect_query'] ?? '');

    if ($complaintId <= 0 || empty($newStatus)) {
        flash('error', 'Parameter perubahan status tidak lengkap.');
        redirect(base_url('admin/complaints.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
    }

    if (empty($adminResponse)) {
        flash('error', 'Tanggapan/keterangan admin wajib diisi saat mengubah status pengaduan.');
        redirect(base_url('admin/complaints.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
    }

    // Fetch complaint current status
    $checkStmt = $conn->prepare("SELECT id, status, ticket_code FROM complaints WHERE id = ?");
    $checkStmt->bind_param("i", $complaintId);
    $checkStmt->execute();
    $complaint = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$complaint) {
        flash('error', 'Pengaduan tidak ditemukan.');
        redirect(base_url('admin/complaints.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
    }

    $currentStatus = $complaint['status'];

    // Valid transitions:
    // Diterima -> Diproses, Ditolak
    // Diproses -> Selesai, Ditolak
    $allowedTransitions = [
        'Diterima' => ['Diproses', 'Ditolak'],
        'Diproses' => ['Selesai', 'Ditolak']
    ];

    if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
        flash('error', "Perubahan status dari \"{$currentStatus}\" ke \"{$newStatus}\" tidak diizinkan.");
        redirect(base_url('admin/complaints.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
    }

    // Update complaint record
    $updateStmt = $conn->prepare("UPDATE complaints SET status = ?, admin_response = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("ssi", $newStatus, $adminResponse, $complaintId);
    $updateStmt->execute();
    $updateStmt->close();

    // Insert into complaint_status_logs
    $adminUser = currentUser();
    $changedBy = !empty($adminUser['name']) ? $adminUser['name'] : 'Admin';
    $logStmt = $conn->prepare("INSERT INTO complaint_status_logs (complaint_id, old_status, new_status, changed_by, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $logStmt->bind_param("issss", $complaintId, $currentStatus, $newStatus, $changedBy, $adminResponse);
    $logStmt->execute();
    $logStmt->close();

    flash('success', "Status pengaduan #{$complaint['ticket_code']} berhasil diperbarui menjadi \"{$newStatus}\".");
    redirect(base_url('admin/complaints.php' . ($redirectQuery ? '?' . $redirectQuery : '')));
}

// ── 2. Fetch All Categories for Filter Dropdown ─────────────
$allCategoriesRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categoriesList = [];
if ($allCategoriesRes) {
    while ($row = $allCategoriesRes->fetch_assoc()) {
        $categoriesList[] = $row;
    }
}

// ── 3. Parse Filter & Search GET Parameters ──────────────────
$search = trim($_GET['search'] ?? '');
$categoryId = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : 0;
$statusFilter = trim($_GET['status'] ?? '');
$validStatuses = ['Diterima', 'Diproses', 'Selesai', 'Ditolak'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = '';
}

$whereClauses = [];
$bindTypes = '';
$bindParams = [];

if ($search !== '') {
    $whereClauses[] = "(c.ticket_code LIKE ? OR c.title LIKE ? OR c.description LIKE ?)";
    $searchWild = '%' . $search . '%';
    $bindTypes .= 'sss';
    $bindParams[] = $searchWild;
    $bindParams[] = $searchWild;
    $bindParams[] = $searchWild;
}

if ($categoryId > 0) {
    $whereClauses[] = "c.category_id = ?";
    $bindTypes .= 'i';
    $bindParams[] = $categoryId;
}

if ($statusFilter !== '') {
    $whereClauses[] = "c.status = ?";
    $bindTypes .= 's';
    $bindParams[] = $statusFilter;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// ── 4. Count Total Rows for Pagination (15 per page) ────────
$countSql = "SELECT COUNT(*) AS total
FROM complaints c
JOIN users u ON c.user_id = u.id
JOIN categories cat ON c.category_id = cat.id
{$whereSql}";

$countStmt = $conn->prepare($countSql);
if (!empty($bindParams)) {
    $countStmt->bind_param($bindTypes, ...$bindParams);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pagination = paginate($totalRows, 15, $currentPage);

// ── 5. Fetch Paginated Complaints ───────────────────────────
$dataSql = "SELECT c.id, c.ticket_code, c.photo, c.title, c.description, c.status, c.admin_response, c.created_at,
                   u.name AS pelapor_name, cat.name AS category_name, cat.icon AS category_icon,
                   (SELECT COUNT(*) FROM upvotes up WHERE up.complaint_id = c.id) AS upvotes_count
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            JOIN categories cat ON c.category_id = cat.id
            {$whereSql}
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";

$dataBindTypes = $bindTypes . 'ii';
$dataBindParams = $bindParams;
$dataBindParams[] = $pagination['per_page'];
$dataBindParams[] = $pagination['offset'];

$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataBindTypes, ...$dataBindParams);
$dataStmt->execute();
$complaints = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// Build query string for keeping filter state in pagination and redirects
$queryParams = $_GET;
unset($queryParams['page']);
$filterQueryString = http_build_query($queryParams);

// Include layout sidebar and header
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- ── HEADER & SEARCH / FILTER BAR ────────────────────────── -->
<div class="card card-custom p-4 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-auto">
            <h5 class="mb-0 fs-6 fw-bold">Filter Pengaduan</h5>
        </div>
        <div class="col-12 col-md">
            <form method="GET" action="<?= base_url('admin/complaints.php') ?>" class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <span class="material-symbols-outlined" style="font-size: 18px;">search</span>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="No. Tiket atau Judul..." value="<?= e($search) ?>">
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <select name="category" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categoriesList as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Dropdown -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="Diterima" <?= $statusFilter === 'Diterima' ? 'selected' : '' ?>>Diterima</option>
                        <option value="Diproses" <?= $statusFilter === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                        <option value="Selesai" <?= $statusFilter === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Ditolak" <?= $statusFilter === 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="col-12 col-sm-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-teal w-100 d-flex align-items-center justify-content-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 18px;">tune</span>
                        <span>Filter</span>
                    </button>
                    <?php if ($search !== '' || $categoryId > 0 || $statusFilter !== ''): ?>
                        <a href="<?= base_url('admin/complaints.php') ?>" class="btn btn-outline-secondary d-flex align-items-center justify-content-center px-2" title="Reset Filter">
                            <span class="material-symbols-outlined" style="font-size: 18px;">refresh</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── COMPLAINTS TABLE CARD ───────────────────────────────── -->
<div class="card card-custom overflow-hidden mb-4">
    <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
        <div>
            <span class="fw-semibold">Daftar Pengaduan</span>
            <span class="badge bg-light text-dark ms-2 border"><?= number_format($totalRows, 0, ',', '.') ?> laporan</span>
        </div>
        <div class="text-muted small">
            Menampilkan <?= count($complaints) ?> dari <?= $totalRows ?> data (Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?>)
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th>No. Tiket</th>
                    <th>Foto</th>
                    <th>Judul & Deskripsi</th>
                    <th>Pelapor</th>
                    <th>Kategori</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Dukungan</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($complaints)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <span class="material-symbols-outlined d-block mb-2 text-secondary" style="font-size: 40px;">inbox</span>
                            Tidak ada pengaduan yang sesuai dengan filter atau kriteria pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($complaints as $item): ?>
                        <tr>
                            <!-- Ticket Code -->
                            <td>
                                <span class="fw-bold" style="color: var(--primary); font-family: 'Poppins', sans-serif; font-size: 0.85rem;">
                                    <?= e($item['ticket_code']) ?>
                                </span>
                            </td>

                            <!-- Photo Thumbnail -->
                            <td>
                                <?php if (!empty($item['photo'])): ?>
                                    <a href="<?= base_url('uploads/' . $item['photo']) ?>" target="_blank" title="Lihat Foto Ukuran Penuh">
                                        <img src="<?= base_url('uploads/' . $item['photo']) ?>" alt="Foto Pengaduan" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color);">
                                    </a>
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-light text-muted border rounded" style="width: 48px; height: 48px;">
                                        <span class="material-symbols-outlined" style="font-size: 20px;">image_not_supported</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Judul & Deskripsi Preview -->
                            <td style="max-width: 260px;">
                                <div class="fw-semibold text-truncate" title="<?= e($item['title']) ?>">
                                    <?= e($item['title']) ?>
                                </div>
                                <div class="text-muted small text-truncate" style="font-size: 0.78rem;" title="<?= e($item['description']) ?>">
                                    <?= e($item['description']) ?>
                                </div>
                            </td>

                            <!-- Pelapor -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 28px; height: 28px; background-color: var(--primary); font-size: 0.72rem; flex-shrink: 0;">
                                        <?= strtoupper(substr($item['pelapor_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <span class="text-truncate" style="max-width: 120px;" title="<?= e($item['pelapor_name']) ?>">
                                        <?= e($item['pelapor_name']) ?>
                                    </span>
                                </div>
                            </td>

                            <!-- Kategori -->
                            <td>
                                <div class="d-flex align-items-center gap-1 text-secondary small">
                                    <span class="material-symbols-outlined" style="font-size: 16px; color: var(--primary);">
                                        <?= e($item['category_icon'] ?? 'category') ?>
                                    </span>
                                    <span><?= e($item['category_name']) ?></span>
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td>
                                <span class="badge-status <?= statusBadgeClass($item['status']) ?>">
                                    <?= e($item['status']) ?>
                                </span>
                            </td>

                            <!-- Tanggal -->
                            <td class="text-secondary small" style="white-space: nowrap;">
                                <?= formatDate($item['created_at']) ?>
                            </td>

                            <!-- Dukungan Count -->
                            <td>
                                <div class="d-flex align-items-center gap-1 text-secondary small">
                                    <span class="material-symbols-outlined" style="font-size: 16px; color: var(--accent);">thumb_up</span>
                                    <span class="fw-semibold"><?= (int)$item['upvotes_count'] ?></span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="text-end" style="white-space: nowrap;">
                                <!-- Lihat Publik -->
                                <a href="<?= base_url('user/complaint_detail.php?id=' . $item['id']) ?>" class="btn btn-sm btn-light border me-1" title="Lihat Detail Publik" target="_blank">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                                    <span class="d-none d-xl-inline ms-1">Lihat</span>
                                </a>

                                <!-- Ubah Status Modal Trigger -->
                                <?php if ($item['status'] === 'Diterima' || $item['status'] === 'Diproses'): ?>
                                    <button type="button" class="btn btn-sm btn-teal btn-change-status"
                                        data-bs-toggle="modal"
                                        data-bs-target="#statusModal"
                                        data-id="<?= $item['id'] ?>"
                                        data-ticket="<?= e($item['ticket_code']) ?>"
                                        data-title="<?= e($item['title']) ?>"
                                        data-status="<?= e($item['status']) ?>"
                                        data-response="<?= e($item['admin_response'] ?? '') ?>">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">edit_note</span>
                                        <span class="d-none d-xl-inline ms-1">Ubah Status</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-light border text-muted" disabled title="Status final tidak dapat diubah lagi">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">lock</span>
                                        <span class="d-none d-xl-inline ms-1">Selesai</span>
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
                        <a class="page-link" href="<?= base_url('admin/complaints.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1]))) ?>">
                            &laquo; Prev
                        </a>
                    </li>

                    <!-- Page Numbers -->
                    <?php
                    $startPage = max(1, $pagination['current_page'] - 2);
                    $endPage   = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= base_url('admin/complaints.php?' . http_build_query(array_merge($_GET, ['page' => 1]))) ?>">1</a></li>
                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                            <a class="page-link <?= $p === $pagination['current_page'] ? 'bg-teal border-teal text-white' : '' ?>" href="<?= base_url('admin/complaints.php?' . http_build_query(array_merge($_GET, ['page' => $p]))) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($endPage < $pagination['total_pages']): ?>
                        <?php if ($endPage < $pagination['total_pages'] - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= base_url('admin/complaints.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['total_pages']]))) ?>"><?= $pagination['total_pages'] ?></a></li>
                    <?php endif; ?>

                    <!-- Next Page -->
                    <li class="page-item <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('admin/complaints.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1]))) ?>">
                            Next &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ── STATUS CHANGE MODAL ─────────────────────────────────── -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card);">
            <form method="POST" action="<?= base_url('admin/complaints.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="complaint_id" id="modalComplaintId" value="">
                <input type="hidden" name="redirect_query" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">

                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2" id="statusModalLabel">
                        <span class="material-symbols-outlined text-teal" style="color: var(--primary);">edit_note</span>
                        <span>Ubah Status Pengaduan</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Complaint Meta Preview -->
                    <div class="p-3 bg-light rounded mb-3 border">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold" id="modalTicketCode" style="color: var(--primary);">#LK-XXXX</span>
                            <span class="badge-status" id="modalCurrentStatusBadge">Diterima</span>
                        </div>
                        <div class="text-secondary small fw-medium text-truncate" id="modalComplaintTitle">Judul Pengaduan</div>
                    </div>

                    <!-- New Status Selection -->
                    <div class="mb-3">
                        <label for="newStatusSelect" class="form-label fw-semibold small">Pilih Status Baru <span class="text-danger">*</span></label>
                        <select name="new_status" id="newStatusSelect" class="form-select" required>
                            <!-- Dynamically populated via JS according to current status -->
                        </select>
                        <div class="form-text small" id="statusTransitionHelp">
                            Pilih status lanjutan sesuai SOP penanganan pengaduan.
                        </div>
                    </div>

                    <!-- Response Textarea -->
                    <div class="mb-3">
                        <label for="adminResponseTextarea" class="form-label fw-semibold small">Tanggapan / Catatan Tindak Lanjut <span class="text-danger">*</span></label>
                        <textarea name="admin_response" id="adminResponseTextarea" class="form-control" rows="4" placeholder="Tuliskan keterangan tindakan, progres penanganan, atau alasan penolakan..." required></textarea>
                        <div class="form-text small">
                            Tanggapan ini akan dicatat dalam riwayat status dan dapat dilihat oleh pelapor.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size: 16px;">save</span>
                        <span>Simpan Perubahan</span>
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
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>

<!-- Page Specific Inline Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusModal = document.getElementById('statusModal');
    if (!statusModal) return;

    statusModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;

        const id = button.getAttribute('data-id');
        const ticket = button.getAttribute('data-ticket');
        const title = button.getAttribute('data-title');
        const currentStatus = button.getAttribute('data-status');
        const response = button.getAttribute('data-response');

        // Populate modal hidden and text fields
        document.getElementById('modalComplaintId').value = id;
        document.getElementById('modalTicketCode').textContent = '#' + ticket;
        document.getElementById('modalComplaintTitle').textContent = title;
        document.getElementById('adminResponseTextarea').value = response || '';

        // Badge styling for current status
        const badgeElem = document.getElementById('modalCurrentStatusBadge');
        badgeElem.textContent = currentStatus;
        badgeElem.className = 'badge-status badge-' + currentStatus.toLowerCase();

        // Populate new status options based on current status transitions:
        // Diterima -> Diproses, Ditolak
        // Diproses -> Selesai, Ditolak
        const select = document.getElementById('newStatusSelect');
        select.innerHTML = '';

        if (currentStatus === 'Diterima') {
            const optDiproses = document.createElement('option');
            optDiproses.value = 'Diproses';
            optDiproses.textContent = 'Diproses (Sedang Dikerjakan/Diteruskan)';
            select.appendChild(optDiproses);

            const optDitolak = document.createElement('option');
            optDitolak.value = 'Ditolak';
            optDitolak.textContent = 'Ditolak (Tidak Memenuhi Syarat/Bukan Wewenang)';
            select.appendChild(optDitolak);
        } else if (currentStatus === 'Diproses') {
            const optSelesai = document.createElement('option');
            optSelesai.value = 'Selesai';
            optSelesai.textContent = 'Selesai (Penanganan Tuntas)';
            select.appendChild(optSelesai);

            const optDitolak = document.createElement('option');
            optDitolak.value = 'Ditolak';
            optDitolak.textContent = 'Ditolak (Dibatalkan/Tidak Dapat Diteruskan)';
            select.appendChild(optDitolak);
        }
    });
});
</script>

</body>
</html>
