<?php
/**
 * LaporKota - Civic Complaint Platform
 * File: user/complaint_list.php
 * Public complaint list page with search, filters, responsive card grid, and pagination.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();

// Page metadata
$pageTitle = 'Daftar Pengaduan';

// Retrieve and sanitize search & filter inputs from GET
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoryFilter = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : 0;
$validStatuses = ['Diterima', 'Diproses', 'Selesai']; // Status Ditolak tidak ditampilkan di daftar publik.
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], $validStatuses, true) ? $_GET['status'] : '';
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Fetch all categories for filter dropdown
$categoriesQuery = "SELECT id, name, icon FROM categories ORDER BY name ASC";
$categoriesResult = $conn->query($categoriesQuery);
$categoriesList = $categoriesResult ? $categoriesResult->fetch_all(MYSQLI_ASSOC) : [];

// Build dynamic WHERE clause with prepared statements.
// Pengaduan berstatus Ditolak selalu dikecualikan dari daftar publik.
$whereClauses = ["c.status <> 'Ditolak'"];
$params = [];
$types = '';

// 1. Text search across title, description, address, and ticket code
if ($q !== '') {
    $whereClauses[] = "(c.title LIKE ? OR c.description LIKE ? OR c.address LIKE ? OR c.ticket_code LIKE ?)";
    $qLike = '%' . $q . '%';
    $params[] = $qLike;
    $params[] = $qLike;
    $params[] = $qLike;
    $params[] = $qLike;
    $types .= 'ssss';
}

// 2. Category filter
if ($categoryFilter > 0) {
    $whereClauses[] = "c.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

// 3. Status filter
if ($statusFilter !== '') {
    $whereClauses[] = "c.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total matching rows for pagination
$countSql = "SELECT COUNT(*) AS total FROM complaints c " . $whereSql;
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Pagination configuration (12 items per page)
$perPage = 12;
$pagination = paginate($totalRows, $perPage, $currentPage);
$limit = $pagination['per_page'];
$offset = $pagination['offset'];

// Fetch complaints for current page
$dataSql = "
    SELECT c.id, c.user_id, c.category_id, c.ticket_code, c.title, c.description, c.address, c.photo, c.status, c.created_at,
           cat.name AS category_name, cat.icon AS category_icon,
           (SELECT COUNT(*) FROM upvotes u WHERE u.complaint_id = c.id) AS upvote_count,
           (SELECT COUNT(*) FROM comments cm WHERE cm.complaint_id = c.id) AS comment_count
    FROM complaints c
    JOIN categories cat ON c.category_id = cat.id
    " . $whereSql . "
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";

$dataStmt = $conn->prepare($dataSql);
$dataTypes = $types . 'ii';
$dataParams = array_merge($params, [$limit, $offset]);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$complaints = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// Helper to construct pagination URLs while preserving active filter params
function buildPaginationUrl($targetPage) {
    $query = $_GET;
    $query['page'] = $targetPage;
    return '?' . http_build_query($query);
}

// Flash messages
$flashSuccess = get_flash('success');
$flashError = get_flash('error');

// Include header
if (file_exists(__DIR__ . '/../includes/header.php')) {
    include __DIR__ . '/../includes/header.php';
} else {
    // Standalone fallback layout header
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($pageTitle) ?> - LaporKota Purwokerto</title>
        <!-- Google Fonts: Poppins & Inter -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
        <!-- Google Material Symbols Outlined -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
        <!-- Bootstrap 5.3 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Design Tokens & Custom CSS -->
        <style>
            :root {
                --primary-teal: #0F766E;
                --primary-dark: #0D6962;
                --primary-light: #E6F5F3;
                --accent-amber: #F59E0B;
                --accent-dark: #D97706;
                --background: #F8F9FF;
                --surface-card: #FFFFFF;
                --text-primary: #1E293B;
                --text-secondary: #64748B;
                --text-muted: #94A3B8;
                --border: #E2E8F0;
                --border-light: #F1F5F9;
                --status-diterima: #2563EB;
                --status-diproses: #F59E0B;
                --status-selesai: #16A34A;
                --status-ditolak: #DC2626;
                --danger: #DC2626;
                --success: #16A34A;
            }
            body {
                font-family: 'Inter', sans-serif;
                background-color: var(--background);
                color: var(--text-primary);
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }
            h1, h2, h3, h4, h5, h6, .font-heading {
                font-family: 'Poppins', sans-serif;
                font-weight: 600;
                color: var(--text-primary);
            }
            .navbar-custom {
                background-color: var(--surface-card);
                border-bottom: 1px solid var(--border);
            }
            .navbar-brand {
                font-family: 'Poppins', sans-serif;
                font-weight: 700;
                color: var(--primary-teal) !important;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .nav-link {
                color: var(--text-secondary);
                font-weight: 500;
            }
            .nav-link:hover, .nav-link.active {
                color: var(--primary-teal);
            }
            .btn-primary-teal {
                background-color: var(--primary-teal);
                border-color: var(--primary-teal);
                color: #FFFFFF;
                font-weight: 500;
            }
            .btn-primary-teal:hover {
                background-color: var(--primary-dark);
                border-color: var(--primary-dark);
                color: #FFFFFF;
            }
            .btn-outline-teal {
                border-color: var(--primary-teal);
                color: var(--primary-teal);
                font-weight: 500;
            }
            .btn-outline-teal:hover {
                background-color: var(--primary-teal);
                color: #FFFFFF;
            }
            .badge-diterima { background-color: var(--status-diterima); color: #fff; }
            .badge-diproses { background-color: var(--status-diproses); color: #fff; }
            .badge-selesai  { background-color: var(--status-selesai); color: #fff; }
            .badge-ditolak  { background-color: var(--status-ditolak); color: #fff; }
            .main-content {
                max-width: 1200px;
                width: 100%;
                margin: 0 auto;
                padding: 24px 16px;
                flex: 1;
            }
            .footer-custom {
                background-color: var(--surface-card);
                border-top: 1px solid var(--border);
                padding: 24px 0;
                color: var(--text-secondary);
                margin-top: auto;
            }
        </style>
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container" style="max-width: 1200px;">
            <a class="navbar-brand" href="<?= base_url() ?>">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary-teal);">campaign</span>
                <span>LaporKota</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url() ?>">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= base_url('user/complaint_list.php') ?>">Daftar Pengaduan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('user/complaint_create.php') ?>">Buat Pengaduan</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <?php if (isLoggedIn()): ?>
                        <?php $user = currentUser(); ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-teal dropdown-toggle d-flex align-items-center gap-1 rounded-pill px-3 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="material-symbols-outlined" style="font-size: 20px;">account_circle</span>
                                <span><?= e($user['name']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item" href="<?= base_url('user/complaint_create.php') ?>">Buat Pengaduan</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= base_url('auth/logout.php') ?>">Keluar</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline-teal rounded-pill px-3 py-1">Masuk</a>
                        <a href="<?= base_url('auth/register.php') ?>" class="btn btn-primary-teal rounded-pill px-3 py-1">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="main-content">
    <?php
}
?>

<!-- Scoped Page Styling -->
<style>
    .complaint-list-hero {
        margin-bottom: 24px;
    }
    .filter-card {
        background-color: var(--surface-card, #FFFFFF);
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        padding: 20px;
        margin-bottom: 28px;
    }
    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid var(--border, #E2E8F0);
        padding: 9px 14px;
        font-size: 14px;
        color: var(--text-primary, #1E293B);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-teal, #0F766E);
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
    }
    .complaint-card {
        background-color: var(--surface-card, #FFFFFF);
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .complaint-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        border-color: #CBD5E1;
    }
    .complaint-img-wrapper {
        position: relative;
        width: 100%;
        height: 190px;
        background-color: #F1F5F9;
        overflow: hidden;
    }
    .complaint-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.3s ease;
    }
    .complaint-card:hover .complaint-img {
        transform: scale(1.03);
    }
    .complaint-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #E6F5F3 0%, #F1F5F9 100%);
        color: var(--primary-teal, #0F766E);
    }
    .badge-status-pill {
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 12px;
        letter-spacing: 0.3px;
    }
    .badge-category-pill {
        background-color: rgba(255, 255, 255, 0.95);
        color: var(--text-primary, #1E293B);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
        padding: 4px 10px;
        display: inline-flex;
        align-items: center;
        backdrop-filter: blur(4px);
    }
    .complaint-body {
        padding: 16px 18px 12px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    .complaint-title-link {
        color: var(--text-primary, #1E293B);
        text-decoration: none;
        font-size: 16px;
        font-weight: 600;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        transition: color 0.15s ease;
    }
    .complaint-title-link:hover {
        color: var(--primary-teal, #0F766E);
    }
    .complaint-desc {
        color: var(--text-secondary, #64748B);
        font-size: 13.5px;
        line-height: 1.5;
        margin-top: 8px;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .complaint-address {
        color: var(--text-muted, #94A3B8);
        font-size: 12.5px;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: auto;
    }
    .complaint-footer {
        padding: 10px 18px;
        background-color: #FAFAFC;
        border-top: 1px solid var(--border-light, #F1F5F9);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: var(--text-secondary, #64748B);
    }
    .upvote-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 600;
        color: var(--primary-teal, #0F766E);
        background-color: var(--primary-light, #E6F5F3);
        padding: 3px 8px;
        border-radius: 50px;
    }
    .btn-detail-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
        color: #0F766E;
        background-color: #E6F5F3;
        border-radius: 50px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-detail-pill:hover {
        background-color: #0F766E;
        color: #FFFFFF;
        transform: translateX(2px);
    }
    .btn-detail-pill .material-symbols-outlined {
        font-size: 15px;
        transition: transform 0.2s ease;
    }
    .btn-detail-pill:hover .material-symbols-outlined {
        transform: translateX(2px);
    }
    .empty-state-box {
        background-color: var(--surface-card, #FFFFFF);
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 12px;
        padding: 60px 20px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .empty-state-icon {
        font-size: 56px;
        color: var(--text-muted, #94A3B8);
        margin-bottom: 16px;
    }
    .pagination .page-link {
        color: var(--text-primary, #1E293B);
        border-color: var(--border, #E2E8F0);
        border-radius: 8px;
        margin: 0 3px;
        padding: 8px 14px;
        font-size: 14px;
        font-weight: 500;
    }
    .pagination .page-item.active .page-link {
        background-color: var(--primary-teal, #0F766E);
        border-color: var(--primary-teal, #0F766E);
        color: #FFFFFF;
    }
    .pagination .page-link:hover:not(.active) {
        background-color: var(--primary-light, #E6F5F3);
        color: var(--primary-teal, #0F766E);
    }
</style>

<div class="container py-2" style="max-width: 1200px;">
    <!-- Flash Messages (if not consumed by header) -->
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <span class="material-symbols-outlined me-2" style="font-size: 20px;">check_circle</span>
                <div><?= e($flashSuccess) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <span class="material-symbols-outlined me-2" style="font-size: 20px;">error</span>
                <div><?= e($flashError) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header Section with Title & Action Button -->
    <div class="complaint-list-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h1 class="h3 font-heading mb-1" style="color: #1E293B;">Daftar Pengaduan</h1>
            <p class="text-secondary mb-0 small">Pantau aspirasi dan laporan infrastruktur serta fasilitas kota di Purwokerto, Banyumas.</p>
        </div>
        <div>
            <a href="<?= base_url('user/complaint_create.php') ?>" class="btn btn-primary-teal rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined" style="font-size: 20px;">add</span>
                <span>Buat Pengaduan</span>
            </a>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="filter-card">
        <form method="GET" action="<?= base_url('user/complaint_list.php') ?>" class="row g-3 align-items-end">
            <!-- Search Input -->
            <div class="col-12 col-md-5">
                <label for="searchQuery" class="form-label small fw-semibold text-secondary mb-1">Cari Kata Kunci</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                        <span class="material-symbols-outlined" style="font-size: 20px;">search</span>
                    </span>
                    <input type="text"
                           name="q"
                           id="searchQuery"
                           class="form-control border-start-0"
                           style="border-radius: 0 8px 8px 0;"
                           placeholder="Cari judul, tiket, lokasi..."
                           value="<?= e($q) ?>">
                </div>
            </div>

            <!-- Category Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label for="categorySelect" class="form-label small fw-semibold text-secondary mb-1">Kategori</label>
                <select name="category" id="categorySelect" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categoriesList as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-12 col-sm-6 col-md-2">
                <label for="statusSelect" class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="status" id="statusSelect" class="form-select">
                    <option value="">Semua Status</option>
                    <?php foreach ($validStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                            <?= e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary-teal flex-grow-1 d-flex align-items-center justify-content-center gap-1" style="border-radius: 8px; padding: 9px 12px;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">filter_alt</span>
                    <span>Terapkan</span>
                </button>
                <?php if ($q !== '' || $categoryFilter > 0 || $statusFilter !== ''): ?>
                    <a href="<?= base_url('user/complaint_list.php') ?>" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" style="border-radius: 8px;" title="Reset Filter">
                        <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Meta Counter -->
    <div class="d-flex justify-content-between align-items-center mb-3 text-secondary small">
        <div>
            Menampilkan <strong><?= count($complaints) ?></strong> dari <strong><?= $totalRows ?></strong> pengaduan
            <?php if ($q !== '' || $categoryFilter > 0 || $statusFilter !== ''): ?>
                <span class="badge bg-light text-secondary border ms-1">Filter Aktif</span>
            <?php endif; ?>
        </div>
        <div>
            Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?>
        </div>
    </div>

    <!-- Complaints Grid (3 columns desktop, 2 tablet, 1 mobile) -->
    <?php if (empty($complaints)): ?>
        <!-- Empty State -->
        <div class="empty-state-box">
            <span class="material-symbols-outlined empty-state-icon">inbox</span>
            <h3 class="h5 font-heading text-dark mb-2">Tidak ada pengaduan ditemukan</h3>
            <p class="text-secondary small mb-4" style="max-width: 440px; margin-left: auto; margin-right: auto;">
                Kami tidak menemukan pengaduan yang sesuai dengan kriteria pencarian atau filter yang Anda pilih. Silakan sesuaikan kata kunci atau bersihkan filter.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <?php if ($q !== '' || $categoryFilter > 0 || $statusFilter !== ''): ?>
                    <a href="<?= base_url('user/complaint_list.php') ?>" class="btn btn-outline-teal rounded-pill px-4">
                        Reset Filter
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('user/complaint_create.php') ?>" class="btn btn-primary-teal rounded-pill px-4">
                    Tulis Pengaduan Baru
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
            <?php foreach ($complaints as $c): ?>
                <?php
                // Check photo availability
                $hasPhoto = !empty($c['photo']);
                $photoPath = $hasPhoto ? base_url('uploads/' . $c['photo']) : null;
                $statusClass = statusBadgeClass($c['status']);
                // Truncate description to 100 characters
                $rawDesc = $c['description'];
                $truncatedDesc = mb_strlen($rawDesc) > 100 ? mb_substr($rawDesc, 0, 100) . '...' : $rawDesc;
                ?>
                <div class="col">
                    <div class="complaint-card">
                        <!-- Image Container with Badges -->
                        <div class="complaint-img-wrapper">
                            <a href="<?= base_url('user/complaint_detail.php?id=' . (int)$c['id']) ?>" class="d-block w-100 h-100 text-decoration-none">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?= e($photoPath) ?>" alt="<?= e($c['title']) ?>" class="complaint-img" loading="lazy">
                                <?php else: ?>
                                    <div class="complaint-placeholder">
                                        <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.85;">
                                            <?= e($c['category_icon'] ?: 'report_problem') ?>
                                        </span>
                                        <span class="small fw-semibold mt-1" style="opacity: 0.9;"><?= e($c['category_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <!-- Category Badge (Top Left) -->
                            <div class="position-absolute top-0 start-0 m-2">
                                <span class="badge-category-pill shadow-sm">
                                    <span class="material-symbols-outlined me-1" style="font-size: 15px; color: var(--primary-teal, #0F766E);">
                                        <?= e($c['category_icon'] ?: 'category') ?>
                                    </span>
                                    <span><?= e($c['category_name']) ?></span>
                                </span>
                            </div>

                            <!-- Status Badge (Top Right) -->
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge badge-status-pill <?= e($statusClass) ?> shadow-sm">
                                    <?= e($c['status']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="complaint-body">
                            <!-- Ticket Code -->
                            <div class="text-muted small fw-semibold mb-1" style="font-size: 12px; letter-spacing: 0.5px;">
                                <a href="<?= base_url('user/complaint_detail.php?ticket=' . urlencode($c['ticket_code'])) ?>" class="text-decoration-none text-muted">
                                    <?= e($c['ticket_code']) ?>
                                </a>
                            </div>

                            <!-- Title (Linked to Detail) -->
                            <h2 class="h6 mb-0">
                                <a href="<?= base_url('user/complaint_detail.php?id=' . (int)$c['id']) ?>" class="complaint-title-link">
                                    <?= e($c['title']) ?>
                                </a>
                            </h2>

                            <!-- Description (Truncated 100 chars) -->
                            <p class="complaint-desc">
                                <?= e($truncatedDesc) ?>
                            </p>

                            <!-- Address with Location Pin -->
                            <div class="complaint-address">
                                <span class="material-symbols-outlined text-danger" style="font-size: 16px;">location_on</span>
                                <span class="text-truncate"><?= e($c['address']) ?></span>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="complaint-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <!-- Upvote Count -->
                                <span class="d-flex align-items-center gap-1 text-secondary small" title="Dukungan Warga" style="font-size: 12.5px;">
                                    <span class="material-symbols-outlined" style="font-size: 15px; color: #0F766E;">thumb_up</span>
                                    <span><?= (int)$c['upvote_count'] ?></span>
                                </span>

                                <!-- Comment Count -->
                                <span class="d-flex align-items-center gap-1 text-secondary small" title="Jumlah Komentar" style="font-size: 12.5px;">
                                    <span class="material-symbols-outlined" style="font-size: 15px; color: #64748B;">chat_bubble_outline</span>
                                    <span><?= (int)$c['comment_count'] ?></span>
                                </span>
                            </div>

                            <!-- Explicit Detail & Comments Button -->
                            <a href="<?= base_url('user/complaint_detail.php?id=' . (int)$c['id']) ?>" class="btn-detail-pill" title="Buka Detail Aduan dan Komentar">
                                <span>Lihat Detail</span>
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <nav aria-label="Navigasi Halaman Pengaduan" class="d-flex justify-content-center my-4">
                <ul class="pagination pagination-sm flex-wrap justify-content-center">
                    <!-- Previous Button -->
                    <?php if ($pagination['current_page'] > 1): ?>
                        <li class="page-item">
                            <a class="page-link d-flex align-items-center" href="<?= e(buildPaginationUrl($pagination['current_page'] - 1)) ?>" aria-label="Sebelumnya">
                                <span class="material-symbols-outlined" style="font-size: 16px;">chevron_left</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link d-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size: 16px;">chevron_left</span>
                            </span>
                        </li>
                    <?php endif; ?>

                    <!-- Numeric Page Numbers -->
                    <?php
                    $startPage = max(1, $pagination['current_page'] - 2);
                    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);

                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . e(buildPaginationUrl(1)) . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                        }
                    }

                    for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(buildPaginationUrl($p)) ?>"><?= $p ?></a>
                        </li>
                    <?php
                    endfor;

                    if ($endPage < $pagination['total_pages']) {
                        if ($endPage < $pagination['total_pages'] - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="' . e(buildPaginationUrl($pagination['total_pages'])) . '">' . $pagination['total_pages'] . '</a></li>';
                    }
                    ?>

                    <!-- Next Button -->
                    <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                        <li class="page-item">
                            <a class="page-link d-flex align-items-center" href="<?= e(buildPaginationUrl($pagination['current_page'] + 1)) ?>" aria-label="Berikutnya">
                                <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link d-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
                            </span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include __DIR__ . '/../includes/footer.php';
} else {
    // Standalone fallback layout footer
    ?>
    </main>
    <footer class="footer-custom">
        <div class="container text-center text-md-start d-flex flex-column flex-md-row justify-content-between align-items-center gap-2" style="max-width: 1200px;">
            <div class="small">
                <strong>LaporKota</strong> &copy; <?= date('Y') ?> Purwokerto, Kabupaten Banyumas. Suara Warga untuk Kota Tercinta.
            </div>
            <div class="small text-muted">
                Dikelola oleh Dinas Komunikasi dan Informatika Kab. Banyumas
            </div>
        </div>
    </footer>
    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>
