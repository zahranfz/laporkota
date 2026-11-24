<?php
/**
 * User Complaint History Page
 * Displays a list of complaints submitted by the logged-in user with search, filter, and pagination.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$currentUser = currentUser();
$userId = (int)$currentUser['id'];

// Handle Complaint Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $complaintId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // Verify ownership and status is 'Diterima'
        $stmt = $conn->prepare("SELECT id, photo, status FROM complaints WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $complaintId, $userId);
        $stmt->execute();
        $complaint = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$complaint) {
            flash('error', 'Pengaduan tidak ditemukan atau Anda tidak memiliki akses.');
        } elseif ($complaint['status'] !== 'Diterima') {
            flash('error', 'Hanya pengaduan dengan status "Diterima" yang dapat dihapus.');
        } else {
            // Delete associated photo file if exists
            if (!empty($complaint['photo'])) {
                $photoPath = __DIR__ . '/../uploads/' . $complaint['photo'];
                if (file_exists($photoPath) && is_file($photoPath)) {
                    @unlink($photoPath);
                }
            }

            // Delete complaint (cascade removes comments, upvotes, status logs)
            $delStmt = $conn->prepare("DELETE FROM complaints WHERE id = ? AND user_id = ?");
            $delStmt->bind_param("ii", $complaintId, $userId);
            if ($delStmt->execute()) {
                flash('success', 'Pengaduan berhasil dihapus.');
            } else {
                flash('error', 'Gagal menghapus pengaduan.');
            }
            $delStmt->close();
        }

        redirect(base_url('user/history.php'));
    }
}

// Search and Filter parameters
$q            = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage      = 10;

// Build query conditions
$where = ["c.user_id = ?"];
$types = "i";
$params = [$userId];

if ($q !== '') {
    $where[] = "(c.ticket_code LIKE ? OR c.title LIKE ?)";
    $types .= "ss";
    $searchWildcard = '%' . $q . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$validStatuses = ['Diterima', 'Diproses', 'Selesai', 'Ditolak'];
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $where[] = "c.status = ?";
    $types .= "s";
    $params[] = $statusFilter;
}

$whereSql = implode(" AND ", $where);

// Count total records for pagination
$countSql = "SELECT COUNT(*) AS total FROM complaints c WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$pagination = paginate($totalRows, $perPage, $page);
$offset     = $pagination['offset'];

// Fetch paginated complaints with category details
$dataSql = "
    SELECT c.id, c.ticket_code, c.title, c.description, c.address, c.status, c.created_at, c.photo,
           cat.name AS category_name, cat.icon AS category_icon
    FROM complaints c
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE {$whereSql}
    ORDER BY c.created_at DESC, c.id DESC
    LIMIT ? OFFSET ?
";
$dataStmt = $conn->prepare($dataSql);
$typesWithLimit = $types . "ii";
$paramsWithLimit = array_merge($params, [$perPage, $offset]);
$dataStmt->bind_param($typesWithLimit, ...$paramsWithLimit);
$dataStmt->execute();
$complaints = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

$pageTitle = 'Riwayat Pengaduan Saya';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* Scoped styles adhering to design tokens */
.history-page {
    background-color: #F8F9FF;
    min-height: 80vh;
}
.history-card {
    border-radius: 12px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.badge-diterima {
    background-color: #EFF6FF;
    color: #2563EB;
    border: 1px solid #BFDBFE;
}
.badge-diproses {
    background-color: #FFFBEB;
    color: #D97706;
    border: 1px solid #FDE68A;
}
.badge-selesai {
    background-color: #F0FDF4;
    color: #16A34A;
    border: 1px solid #BBF7D0;
}
.badge-ditolak {
    background-color: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}
.btn-primary-teal {
    background-color: #0F766E;
    color: #FFFFFF;
    border: none;
    border-radius: 8px;
    transition: background-color 0.2s ease;
}
.btn-primary-teal:hover {
    background-color: #0D6962;
    color: #FFFFFF;
}
.table-complaint-row:hover {
    background-color: #F8F9FF;
}
.pagination .page-link {
    color: #0F766E;
    border-color: #E2E8F0;
}
.pagination .page-item.active .page-link {
    background-color: #0F766E;
    border-color: #0F766E;
    color: #FFFFFF;
}
</style>

<div class="history-page py-4">
    <div class="container" style="max-width: 1200px;">
        <!-- Breadcrumb & Title -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?= base_url() ?>" class="text-decoration-none" style="color: #0F766E;">Beranda</a></li>
                        <li class="breadcrumb-item active" aria-current="page" style="color: #64748B;">Riwayat Pengaduan</li>
                    </ol>
                </nav>
                <h2 class="fw-bold mb-0" style="color: #1E293B; font-family: 'Poppins', sans-serif;">Riwayat Pengaduan Saya</h2>
            </div>
            <a href="<?= base_url('user/complaint_create.php') ?>" class="btn btn-primary-teal d-inline-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="material-symbols-outlined fs-5">add_circle</span>
                <span>Buat Pengaduan</span>
            </a>
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

        <!-- Search & Filter Card -->
        <div class="history-card p-3 p-md-4 mb-4">
            <form method="GET" action="<?= base_url('user/history.php') ?>" class="row g-3 align-items-end">
                <div class="col-md-6 col-lg-5">
                    <label for="searchQuery" class="form-label small fw-semibold text-secondary mb-1">Cari Pengaduan</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="border-radius: 8px 0 0 8px; border-color: #E2E8F0;">
                            <span class="material-symbols-outlined fs-5 text-muted">search</span>
                        </span>
                        <input type="text" class="form-control" id="searchQuery" name="q" 
                               value="<?= e($q) ?>" placeholder="Cari kode tiket atau judul..."
                               style="border-radius: 0 8px 8px 0; border-color: #E2E8F0;">
                    </div>
                </div>

                <div class="col-md-4 col-lg-4">
                    <label for="statusFilter" class="form-label small fw-semibold text-secondary mb-1">Filter Status</label>
                    <select class="form-select" id="statusFilter" name="status" style="border-radius: 8px; border-color: #E2E8F0;">
                        <option value="">Semua Status</option>
                        <option value="Diterima" <?= $statusFilter === 'Diterima' ? 'selected' : '' ?>>Diterima</option>
                        <option value="Diproses" <?= $statusFilter === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                        <option value="Selesai"  <?= $statusFilter === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Ditolak"  <?= $statusFilter === 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                </div>

                <div class="col-md-2 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-teal d-inline-flex align-items-center justify-content-center gap-1 w-100 py-2">
                        <span class="material-symbols-outlined fs-5">filter_alt</span>
                        <span>Filter</span>
                    </button>
                    <?php if ($q !== '' || $statusFilter !== ''): ?>
                        <a href="<?= base_url('user/history.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center py-2" title="Reset Filter" style="border-radius: 8px;">
                            <span class="material-symbols-outlined fs-5">restart_alt</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Complaint List -->
        <?php if (empty($complaints)): ?>
            <!-- Empty State -->
            <div class="history-card text-center py-5 px-3">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; background-color: #E6F5F3; color: #0F766E;">
                    <span class="material-symbols-outlined" style="font-size: 40px;">
                        <?= ($q !== '' || $statusFilter !== '') ? 'search_off' : 'inbox' ?>
                    </span>
                </div>
                <h4 class="fw-bold mb-2" style="color: #1E293B; font-family: 'Poppins', sans-serif;">
                    <?= ($q !== '' || $statusFilter !== '') ? 'Pengaduan Tidak Ditemukan' : 'Belum Ada Pengaduan' ?>
                </h4>
                <p class="text-secondary mb-4 mx-auto" style="max-width: 480px;">
                    <?php if ($q !== '' || $statusFilter !== ''): ?>
                        Tidak ada pengaduan yang cocok dengan kata kunci atau filter status yang Anda pilih. Silakan sesuaikan pencarian Anda.
                    <?php else: ?>
                        Anda belum pernah membuat laporan pengaduan. Sampaikan permasalahan fasilitas umum di sekitar Purwokerto agar segera ditindaklanjuti.
                    <?php endif; ?>
                </p>
                <?php if ($q !== '' || $statusFilter !== ''): ?>
                    <a href="<?= base_url('user/history.php') ?>" class="btn btn-outline-secondary px-4 py-2" style="border-radius: 8px;">
                        Reset Filter
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('user/complaint_create.php') ?>" class="btn btn-primary-teal px-4 py-2">
                        Mulai Buat Pengaduan
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Results Table & Cards -->
            <div class="history-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="border-collapse: collapse;">
                        <thead style="background-color: #F8F9FF; border-bottom: 2px solid #E2E8F0;">
                            <tr>
                                <th scope="col" class="py-3 px-4 text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Kode Tiket</th>
                                <th scope="col" class="py-3 px-3 text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Judul Laporan</th>
                                <th scope="col" class="py-3 px-3 text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Kategori</th>
                                <th scope="col" class="py-3 px-3 text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Status</th>
                                <th scope="col" class="py-3 px-3 text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Tanggal</th>
                                <th scope="col" class="py-3 px-4 text-secondary small fw-bold text-uppercase text-end" style="letter-spacing: 0.5px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $row): ?>
                                <tr class="table-complaint-row" style="border-bottom: 1px solid #F1F5F9;">
                                    <td class="py-3 px-4">
                                        <span class="badge font-monospace fw-semibold px-2 py-1" style="background-color: #E6F5F3; color: #0F766E; border-radius: 6px; font-size: 0.85rem;">
                                            <?= e($row['ticket_code']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="fw-semibold text-dark mb-0 text-truncate" style="max-width: 320px;" title="<?= e($row['title']) ?>">
                                            <?= e($row['title']) ?>
                                        </div>
                                        <?php if (!empty($row['address'])): ?>
                                            <div class="small text-muted text-truncate d-flex align-items-center gap-1 mt-1" style="max-width: 320px;">
                                                <span class="material-symbols-outlined fs-6">location_on</span>
                                                <span><?= e($row['address']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="d-inline-flex align-items-center gap-1 text-secondary small">
                                            <span class="material-symbols-outlined fs-5 text-muted"><?= e($row['category_icon'] ?? 'category') ?></span>
                                            <span><?= e($row['category_name'] ?? 'Umum') ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="badge rounded-pill px-3 py-1 fw-semibold <?= statusBadgeClass($row['status']) ?>" style="font-size: 0.8rem; border-radius: 50px;">
                                            <?= e($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 small text-secondary">
                                        <?= formatDate($row['created_at']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <!-- Lihat Detail (Always available) -->
                                            <a href="<?= base_url('user/complaint_detail.php?id=' . $row['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                                               style="border-radius: 8px; border-color: #0F766E; color: #0F766E;"
                                               title="Lihat Detail">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                <span class="d-none d-md-inline">Lihat Detail</span>
                                            </a>

                                            <?php if ($row['status'] === 'Diterima'): ?>
                                                <!-- Edit Link (When status is Diterima) -->
                                                <a href="<?= base_url('user/complaint_create.php?edit=' . $row['id']) ?>" 
                                                   class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1"
                                                   style="border-radius: 8px; border-color: #F59E0B; color: #D97706;"
                                                   title="Edit Pengaduan">
                                                    <span class="material-symbols-outlined fs-6">edit</span>
                                                    <span class="d-none d-md-inline">Edit</span>
                                                </a>

                                                <!-- Hapus Button (When status is Diterima) -->
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-delete-complaint"
                                                        style="border-radius: 8px;"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteComplaintModal"
                                                        data-id="<?= $row['id'] ?>"
                                                        data-ticket="<?= e($row['ticket_code']) ?>"
                                                        data-title="<?= e($row['title']) ?>"
                                                        title="Hapus Pengaduan">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                    <span class="d-none d-md-inline">Hapus</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="d-flex flex-wrap align-items-center justify-content-between p-3 border-top gap-3" style="border-color: #F1F5F9 !important;">
                    <div class="small text-secondary">
                        Menampilkan <span class="fw-semibold text-dark"><?= count($complaints) ?></span> dari <span class="fw-semibold text-dark"><?= $totalRows ?></span> pengaduan
                    </div>

                    <?php if ($pagination['total_pages'] > 1): ?>
                        <nav aria-label="Navigasi Halaman">
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $queryParams = [];
                                if ($q !== '') $queryParams['q'] = $q;
                                if ($statusFilter !== '') $queryParams['status'] = $statusFilter;

                                $prevPage = $pagination['current_page'] - 1;
                                $nextPage = $pagination['current_page'] + 1;
                                ?>

                                <!-- Previous Button -->
                                <li class="page-item <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= base_url('user/history.php?' . http_build_query(array_merge($queryParams, ['page' => $prevPage]))) ?>" aria-label="Sebelumnya">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <!-- Page Number Links -->
                                <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                                    <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= base_url('user/history.php?' . http_build_query(array_merge($queryParams, ['page' => $p]))) ?>">
                                            <?= $p ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <li class="page-item <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= base_url('user/history.php?' . http_build_query(array_merge($queryParams, ['page' => $nextPage]))) ?>" aria-label="Berikutnya">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteComplaintModal" tabindex="-1" aria-labelledby="deleteComplaintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; border: 1px solid #E2E8F0;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteComplaintModalLabel">
                    <span class="material-symbols-outlined fs-4">warning</span>
                    <span>Konfirmasi Hapus Pengaduan</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary mb-3">
                    Apakah Anda yakin ingin menghapus pengaduan berikut? Tindakan ini bersifat permanen dan tidak dapat dibatalkan.
                </p>
                <div class="p-3 bg-light rounded mb-2" style="border-radius: 8px; border: 1px solid #E2E8F0;">
                    <div class="fw-bold font-monospace mb-1" id="modalTicketCode" style="color: #0F766E;"></div>
                    <div class="small text-dark fw-semibold" id="modalComplaintTitle"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <form method="POST" action="<?= base_url('user/history.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="modalDeleteId" value="">
                    <button type="button" class="btn btn-outline-secondary px-3 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                    <button type="submit" class="btn btn-danger px-3 py-2 fw-semibold" style="border-radius: 8px;">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Handle passing data to delete confirmation modal using vanilla JS
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteComplaintModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const complaintId = button.getAttribute('data-id') || '';
            const ticketCode  = button.getAttribute('data-ticket') || '';
            const title       = button.getAttribute('data-title') || '';

            document.getElementById('modalDeleteId').value = complaintId;
            document.getElementById('modalTicketCode').textContent = ticketCode;
            document.getElementById('modalComplaintTitle').textContent = title;
        });
    }
});
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
