<?php
/**
 * LaporKota — Civic Complaint Platform
 * File: user/complaint_detail.php
 * Complaint detail page — shows full complaint info, status timeline,
 * upvote button, comments, Leaflet mini-map, and owner actions.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();

// ── Handle POST: Delete Complaint ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    requireLogin();

    $deleteId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($deleteId <= 0) {
        flash('error', 'ID pengaduan tidak valid.');
        redirect(base_url('user/history.php'));
    }

    // Verify ownership and status
    $user = currentUser();
    $delStmt = $conn->prepare("SELECT id, user_id, status, photo FROM complaints WHERE id = ?");
    $delStmt->bind_param("i", $deleteId);
    $delStmt->execute();
    $delComplaint = $delStmt->get_result()->fetch_assoc();
    $delStmt->close();

    if (!$delComplaint || (int)$delComplaint['user_id'] !== (int)$user['id']) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus pengaduan ini.');
        redirect(base_url('user/history.php'));
    }

    if ($delComplaint['status'] !== 'Diterima') {
        flash('error', 'Pengaduan hanya dapat dihapus jika statusnya masih "Diterima".');
        redirect(base_url('user/complaint_detail.php?id=' . $deleteId));
    }

    // Delete related records first
    $delUpvotes = $conn->prepare("DELETE FROM upvotes WHERE complaint_id = ?");
    $delUpvotes->bind_param("i", $deleteId);
    $delUpvotes->execute();
    $delUpvotes->close();

    $delComments = $conn->prepare("DELETE FROM comments WHERE complaint_id = ?");
    $delComments->bind_param("i", $deleteId);
    $delComments->execute();
    $delComments->close();

    $delLogs = $conn->prepare("DELETE FROM complaint_status_logs WHERE complaint_id = ?");
    $delLogs->bind_param("i", $deleteId);
    $delLogs->execute();
    $delLogs->close();

    // Delete photo file if exists
    if (!empty($delComplaint['photo'])) {
        $photoFile = __DIR__ . '/../uploads/' . $delComplaint['photo'];
        if (file_exists($photoFile)) {
            unlink($photoFile);
        }
    }

    // Delete complaint
    $delMain = $conn->prepare("DELETE FROM complaints WHERE id = ?");
    $delMain->bind_param("i", $deleteId);
    $delMain->execute();
    $delMain->close();

    flash('success', 'Pengaduan berhasil dihapus.');
    redirect(base_url('user/history.php'));
}

// ── GET: Fetch Complaint Detail ──────────────────────────────────────────
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ticketCode  = isset($_GET['ticket']) ? trim($_GET['ticket']) : (isset($_GET['code']) ? trim($_GET['code']) : '');

if ($complaintId <= 0 && $ticketCode === '') {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

// Fetch complaint with user and category info (supports lookup by ID or ticket code)
if ($complaintId > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, 
               u.name AS user_name, u.photo AS user_photo,
               cat.name AS category_name, cat.icon AS category_icon
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $complaintId);
} else {
    $stmt = $conn->prepare("
        SELECT c.*, 
               u.name AS user_name, u.photo AS user_photo,
               cat.name AS category_name, cat.icon AS category_icon
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.ticket_code = ?
    ");
    $stmt->bind_param("s", $ticketCode);
}
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$complaint) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}
$complaintId = (int)$complaint['id'];

// Count upvotes
$upStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM upvotes WHERE complaint_id = ?");
$upStmt->bind_param("i", $complaintId);
$upStmt->execute();
$upvoteCount = (int)$upStmt->get_result()->fetch_assoc()['cnt'];
$upStmt->close();

// Check if current user has upvoted
$hasUpvoted = false;
$currentUser = currentUser();
if ($currentUser) {
    $uvCheck = $conn->prepare("SELECT id FROM upvotes WHERE complaint_id = ? AND user_id = ?");
    $uvCheck->bind_param("ii", $complaintId, $currentUser['id']);
    $uvCheck->execute();
    $hasUpvoted = $uvCheck->get_result()->num_rows > 0;
    $uvCheck->close();
}

// Fetch comments with user info, ordered by created_at DESC
$cmtStmt = $conn->prepare("
    SELECT cm.id, cm.body, cm.created_at,
           u.name AS user_name, u.photo AS user_photo
    FROM comments cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.complaint_id = ?
    ORDER BY cm.created_at DESC
");
$cmtStmt->bind_param("i", $complaintId);
$cmtStmt->execute();
$comments = $cmtStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cmtStmt->close();
$commentCount = count($comments);

// Fetch status logs ordered by created_at ASC
$logStmt = $conn->prepare("
    SELECT csl.old_status, csl.new_status, csl.note, csl.created_at,
           csl.changed_by AS changed_by_name
    FROM complaint_status_logs csl
    WHERE csl.complaint_id = ?
    ORDER BY csl.created_at ASC
");
$logStmt->bind_param("i", $complaintId);
$logStmt->execute();
$statusLogs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logStmt->close();

// Determine ownership
$isOwner = $currentUser && (int)$currentUser['id'] === (int)$complaint['user_id'];

// Set page title and include header
$pageTitle = e($complaint['ticket_code']) . ' — ' . e($complaint['title']);
include __DIR__ . '/../includes/header.php';
?>

<!-- Scoped Page Styles -->
<style>
    /* ── Photo ─────────────────────────────────── */
    .detail-photo-wrap {
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, #E6F5F3 0%, #F1F5F9 100%);
        margin-bottom: 24px;
    }
    .detail-photo-wrap img {
        width: 100%;
        max-height: 500px;
        object-fit: cover;
        display: block;
    }
    .detail-photo-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 320px;
        color: var(--primary-teal, #0F766E);
    }
    .detail-photo-placeholder .material-symbols-outlined {
        font-size: 72px;
        opacity: 0.7;
    }

    /* ── Info Card ─────────────────────────────── */
    .info-card {
        background: #FFFFFF;
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .info-card-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 15px;
        color: var(--text-primary, #1E293B);
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-light, #F1F5F9);
    }
    .info-card-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
    }
    .info-card-row + .info-card-row {
        border-top: 1px solid var(--border-light, #F1F5F9);
    }
    .info-card-label {
        color: var(--text-muted, #94A3B8);
        font-size: 12.5px;
        font-weight: 500;
        min-width: 80px;
        flex-shrink: 0;
    }
    .info-card-value {
        color: var(--text-primary, #1E293B);
        font-size: 13.5px;
        font-weight: 500;
        word-break: break-word;
    }

    /* ── Mini Map ──────────────────────────────── */
    .detail-map-wrap {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border, #E2E8F0);
        margin-bottom: 20px;
    }
    #detail-map {
        height: 250px;
        width: 100%;
    }

    /* ── Upvote Section ────────────────────────── */
    .upvote-section {
        background: #FFFFFF;
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .btn-upvote {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 50px;
        border: 2px solid var(--border, #E2E8F0);
        background: #FFFFFF;
        color: var(--text-secondary, #64748B);
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.25s ease;
        width: 100%;
        justify-content: center;
    }
    .btn-upvote:hover:not(:disabled) {
        border-color: #EF4444;
        color: #EF4444;
        background: #FEF2F2;
    }
    .btn-upvote.upvoted {
        border-color: #EF4444;
        color: #EF4444;
        background: #FEF2F2;
    }
    .btn-upvote:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .upvote-count {
        font-weight: 700;
        font-size: 15px;
    }
    .upvote-label {
        font-size: 12px;
        color: var(--text-muted, #94A3B8);
        margin-top: 8px;
    }

    /* ── Admin Response ────────────────────────── */
    .admin-response-card {
        background: #F0FDFA;
        border: 1px solid #CCFBF1;
        border-left: 4px solid var(--primary-teal, #0F766E);
        border-radius: 12px;
        padding: 18px 20px;
        margin-top: 20px;
    }
    .admin-response-card .response-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 14px;
        color: var(--primary-teal, #0F766E);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .admin-response-card .response-body {
        color: var(--text-primary, #1E293B);
        font-size: 14px;
        line-height: 1.65;
        white-space: pre-line;
    }

    /* ── Status Timeline ──────────────────────── */
    .status-timeline {
        position: relative;
        padding-left: 28px;
    }
    .status-timeline::before {
        content: '';
        position: absolute;
        left: 9px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: var(--border, #E2E8F0);
    }
    .timeline-item {
        position: relative;
        padding-bottom: 24px;
    }
    .timeline-item:last-child {
        padding-bottom: 0;
    }
    .timeline-dot {
        position: absolute;
        left: -28px;
        top: 2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 3px solid #FFFFFF;
        box-shadow: 0 0 0 2px var(--border, #E2E8F0);
        z-index: 1;
    }
    .timeline-dot.dot-diterima  { background: #2563EB; box-shadow: 0 0 0 2px #2563EB33; }
    .timeline-dot.dot-diproses  { background: #F59E0B; box-shadow: 0 0 0 2px #F59E0B33; }
    .timeline-dot.dot-selesai   { background: #16A34A; box-shadow: 0 0 0 2px #16A34A33; }
    .timeline-dot.dot-ditolak   { background: #DC2626; box-shadow: 0 0 0 2px #DC262633; }
    .timeline-content {
        padding-top: 0;
    }
    .timeline-status {
        font-weight: 600;
        font-size: 14px;
        color: var(--text-primary, #1E293B);
    }
    .timeline-meta {
        font-size: 12.5px;
        color: var(--text-muted, #94A3B8);
        margin-top: 2px;
    }
    .timeline-note {
        font-size: 13px;
        color: var(--text-secondary, #64748B);
        margin-top: 4px;
        line-height: 1.5;
    }

    /* ── Comments ──────────────────────────────── */
    .comment-item {
        display: flex;
        gap: 12px;
        padding: 14px 0;
    }
    .comment-item + .comment-item {
        border-top: 1px solid var(--border-light, #F1F5F9);
    }
    .comment-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 15px;
        color: #FFFFFF;
        background: var(--primary-teal, #0F766E);
    }
    img.comment-avatar {
        background: var(--border-light, #F1F5F9);
    }
    .comment-body {
        flex: 1;
        min-width: 0;
    }
    .comment-header {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .comment-author-name {
        font-weight: 600;
        font-size: 13.5px;
        color: var(--text-primary, #1E293B);
    }
    .comment-time {
        font-size: 12px;
        color: var(--text-muted, #94A3B8);
    }
    .comment-text {
        font-size: 13.5px;
        color: var(--text-secondary, #64748B);
        margin-top: 4px;
        margin-bottom: 0;
        line-height: 1.55;
        white-space: pre-line;
        word-break: break-word;
    }
    .comment-empty-state {
        text-align: center;
        padding: 32px 16px;
        color: var(--text-muted, #94A3B8);
    }
    .comment-empty-state .material-symbols-outlined {
        font-size: 40px;
        opacity: 0.5;
        display: block;
        margin-bottom: 8px;
    }

    /* ── Comment Form ─────────────────────────── */
    .comment-form textarea {
        border-radius: 8px;
        border: 1px solid var(--border, #E2E8F0);
        font-size: 14px;
        resize: vertical;
    }
    .comment-form textarea:focus {
        border-color: var(--primary-teal, #0F766E);
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
    }

    /* ── Breadcrumb ────────────────────────────── */
    .breadcrumb-custom {
        background: transparent;
        padding: 0;
        margin-bottom: 20px;
        font-size: 13.5px;
    }
    .breadcrumb-custom .breadcrumb-item a {
        color: var(--text-secondary, #64748B);
        text-decoration: none;
        transition: color 0.15s;
    }
    .breadcrumb-custom .breadcrumb-item a:hover {
        color: var(--primary-teal, #0F766E);
    }
    .breadcrumb-custom .breadcrumb-item.active {
        color: var(--text-primary, #1E293B);
        font-weight: 500;
    }

    /* ── Description ──────────────────────────── */
    .detail-description {
        font-size: 14.5px;
        line-height: 1.75;
        color: var(--text-secondary, #64748B);
        white-space: pre-line;
        word-break: break-word;
    }

    /* ── Owner Actions ────────────────────────── */
    .owner-actions {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--border, #E2E8F0);
    }

    /* ── Section Card ─────────────────────────── */
    .section-card {
        background: #FFFFFF;
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .section-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 17px;
        color: var(--text-primary, #1E293B);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ── Badge Tweaks ─────────────────────────── */
    .badge-ticket {
        background: var(--primary-light, #E6F5F3);
        color: var(--primary-teal, #0F766E);
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 50px;
        letter-spacing: 0.5px;
    }
    .badge-category {
        background: #F1F5F9;
        color: var(--text-secondary, #64748B);
        font-size: 12px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-status-detail {
        font-size: 12px;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 50px;
        color: #FFFFFF;
    }

    /* ── Responsive ────────────────────────────── */
    @media (max-width: 991.98px) {
        .detail-photo-wrap img {
            max-height: 350px;
        }
    }
    @media (max-width: 575.98px) {
        .detail-photo-wrap img {
            max-height: 240px;
        }
        .detail-photo-placeholder {
            height: 200px;
        }
        #detail-map {
            height: 200px;
        }
    }
</style>

<div class="container py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb breadcrumb-custom">
            <li class="breadcrumb-item">
                <a href="<?= base_url('') ?>">
                    <span class="material-symbols-outlined md-16 align-text-bottom">home</span>
                    Beranda
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= base_url('user/complaint_list.php') ?>">Daftar Pengaduan</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <?= e($complaint['ticket_code']) ?>
            </li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- ═══════════ Left Column (8 cols) ═══════════ -->
        <div class="col-lg-8">
            <!-- Photo -->
            <div class="detail-photo-wrap">
                <?php if (!empty($complaint['photo'])): ?>
                    <img src="<?= base_url('uploads/' . e($complaint['photo'])) ?>" 
                         alt="<?= e($complaint['title']) ?>" 
                         loading="lazy">
                <?php else: ?>
                    <div class="detail-photo-placeholder">
                        <span class="material-symbols-outlined"><?= e($complaint['category_icon'] ?: 'report_problem') ?></span>
                        <span class="mt-2 fw-semibold" style="opacity:0.7; font-size:14px;"><?= e($complaint['category_name']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Title, Badges -->
            <div class="mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge-ticket"><?= e($complaint['ticket_code']) ?></span>
                    <span class="badge-status-detail <?= e(statusBadgeClass($complaint['status'])) ?>">
                        <?= e($complaint['status']) ?>
                    </span>
                    <span class="badge-category">
                        <span class="material-symbols-outlined" style="font-size:16px;"><?= e($complaint['category_icon'] ?: 'category') ?></span>
                        <?= e($complaint['category_name']) ?>
                    </span>
                </div>
                <h2 class="h4 font-heading mb-0" style="line-height:1.4;">
                    <?= e($complaint['title']) ?>
                </h2>
            </div>

            <!-- Description -->
            <div class="detail-description mb-4">
                <?= e($complaint['description']) ?>
            </div>

            <!-- Address -->
            <?php if (!empty($complaint['address'])): ?>
                <div class="d-flex align-items-start gap-2 mb-4" style="color: var(--text-secondary, #64748B); font-size: 14px;">
                    <span class="material-symbols-outlined text-danger" style="font-size: 20px; flex-shrink: 0;">location_on</span>
                    <span><?= e($complaint['address']) ?></span>
                </div>
            <?php endif; ?>

            <!-- Admin Response -->
            <?php if (!empty($complaint['admin_response'])): ?>
                <div class="admin-response-card">
                    <div class="response-title">
                        <span class="material-symbols-outlined" style="font-size:20px;">verified</span>
                        Tanggapan Resmi
                    </div>
                    <div class="response-body"><?= e($complaint['admin_response']) ?></div>
                </div>
            <?php endif; ?>

            <!-- Owner Actions (Edit + Delete) -->
            <?php if ($isOwner && $complaint['status'] === 'Diterima'): ?>
                <div class="owner-actions d-flex flex-wrap gap-2">
                    <a href="<?= base_url('user/complaint_create.php?edit=' . (int)$complaint['id']) ?>" 
                       class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" 
                       style="border-radius: 8px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                        Edit Pengaduan
                    </a>
                    <button type="button" 
                            class="btn btn-outline-danger d-inline-flex align-items-center gap-1" 
                            style="border-radius: 8px;"
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        Hapus Pengaduan
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══════════ Right Column (4 cols) ═══════════ -->
        <div class="col-lg-4">
            <!-- Info Card -->
            <div class="info-card">
                <div class="info-card-title">
                    <span class="material-symbols-outlined align-text-bottom me-1" style="font-size:18px;">info</span>
                    Informasi Pengaduan
                </div>

                <div class="info-card-row">
                    <span class="info-card-label">Pelapor</span>
                    <span class="info-card-value d-flex align-items-center gap-2">
                        <?php if (!empty($complaint['user_photo'])): ?>
                            <img src="<?= base_url('uploads/' . e($complaint['user_photo'])) ?>" 
                                 alt="<?= e($complaint['user_name']) ?>" 
                                 style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <span style="width:24px; height:24px; border-radius:50%; background:var(--primary-teal,#0F766E); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:600;">
                                <?= e(strtoupper(mb_substr($complaint['user_name'], 0, 1))) ?>
                            </span>
                        <?php endif; ?>
                        <?= e($complaint['user_name']) ?>
                    </span>
                </div>

                <div class="info-card-row">
                    <span class="info-card-label">Tanggal</span>
                    <span class="info-card-value"><?= e(formatDate($complaint['created_at'])) ?></span>
                </div>

                <div class="info-card-row">
                    <span class="info-card-label">Kategori</span>
                    <span class="info-card-value d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary-teal,#0F766E);">
                            <?= e($complaint['category_icon'] ?: 'category') ?>
                        </span>
                        <?= e($complaint['category_name']) ?>
                    </span>
                </div>

                <div class="info-card-row">
                    <span class="info-card-label">Status</span>
                    <span class="info-card-value">
                        <span class="badge <?= e(statusBadgeClass($complaint['status'])) ?>" style="font-size:12px; padding:4px 10px; border-radius:50px;">
                            <?= e($complaint['status']) ?>
                        </span>
                    </span>
                </div>
            </div>

            <!-- Leaflet Mini Map -->
            <?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
                <div class="detail-map-wrap">
                    <div id="detail-map"></div>
                </div>
            <?php endif; ?>

            <!-- Upvote Section -->
            <div class="upvote-section">
                <?php if (isLoggedIn()): ?>
                    <button type="button" 
                            class="btn-upvote<?= $hasUpvoted ? ' upvoted' : '' ?>" 
                            data-complaint-id="<?= (int)$complaint['id'] ?>">
                        <span class="material-symbols-outlined" style="font-size:20px;<?= $hasUpvoted ? " font-variation-settings:'FILL' 1;" : '' ?>">
                            <?= $hasUpvoted ? 'favorite' : 'favorite_border' ?>
                        </span>
                        <span class="upvote-count"><?= $upvoteCount ?></span>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-upvote" disabled title="Masuk untuk memberikan dukungan">
                        <span class="material-symbols-outlined" style="font-size:20px;">favorite_border</span>
                        <span class="upvote-count"><?= $upvoteCount ?></span>
                    </button>
                <?php endif; ?>
                <div class="upvote-label">Saya juga mengalami</div>
            </div>
        </div>
    </div>

    <!-- ═══════════ Full-width Sections Below ═══════════ -->

    <!-- Status Timeline -->
    <?php if (!empty($statusLogs)): ?>
        <div class="section-card">
            <div class="section-title">
                <span class="material-symbols-outlined" style="color:var(--primary-teal,#0F766E);">timeline</span>
                Riwayat Status
            </div>
            <div class="status-timeline">
                <?php foreach ($statusLogs as $log): ?>
                    <?php
                    $dotClass = 'dot-diterima';
                    switch ($log['new_status']) {
                        case 'Diproses': $dotClass = 'dot-diproses'; break;
                        case 'Selesai':  $dotClass = 'dot-selesai';  break;
                        case 'Ditolak':  $dotClass = 'dot-ditolak';  break;
                        case 'Diterima': $dotClass = 'dot-diterima'; break;
                    }
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?= $dotClass ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-status">
                                <?php if (!empty($log['old_status'])): ?>
                                    <?= e($log['old_status']) ?> → <?= e($log['new_status']) ?>
                                <?php else: ?>
                                    <?= e($log['new_status']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-meta">
                                <?php if (!empty($log['changed_by_name'])): ?>
                                    <span class="material-symbols-outlined align-text-bottom" style="font-size:14px;">person</span>
                                    <?= e($log['changed_by_name']) ?> · 
                                <?php endif; ?>
                                <?= e(formatDate($log['created_at'])) ?>
                            </div>
                            <?php if (!empty($log['note'])): ?>
                                <div class="timeline-note"><?= e($log['note']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Comments Section -->
    <div class="section-card">
        <div class="section-title">
            <span class="material-symbols-outlined" style="color:var(--primary-teal,#0F766E);">chat_bubble_outline</span>
            Komentar (<span id="comment-count"><?= $commentCount ?></span>)
        </div>

        <!-- Comment Form -->
        <?php if (isLoggedIn()): ?>
            <form class="comment-form mb-4">
                <input type="hidden" name="complaint_id" value="<?= (int)$complaint['id'] ?>">
                <div class="mb-2">
                    <textarea name="body" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Tulis komentar Anda..." 
                              data-maxlength="1000" 
                              required></textarea>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-sm d-inline-flex align-items-center gap-1" 
                            style="background:var(--primary-teal,#0F766E); color:#fff; border-radius:8px; padding:8px 18px; font-weight:500;">
                        <span class="material-symbols-outlined" style="font-size:18px;">send</span>
                        Kirim Komentar
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="mb-4 p-3 text-center rounded-3" style="background:#F1F5F9; border-radius:8px;">
                <span class="text-secondary" style="font-size:14px;">
                    <a href="<?= base_url('auth/login.php') ?>" class="fw-semibold" style="color:var(--primary-teal,#0F766E);">Masuk</a> 
                    untuk menambahkan komentar.
                </span>
            </div>
        <?php endif; ?>

        <!-- Comment List -->
        <div id="comment-list">
            <?php if (empty($comments)): ?>
                <div class="comment-empty-state">
                    <span class="material-symbols-outlined">forum</span>
                    <p class="mb-0" style="font-size:14px;">Belum ada komentar. Jadilah yang pertama berkomentar!</p>
                </div>
            <?php else: ?>
                <?php foreach ($comments as $cmt): ?>
                    <div class="comment-item">
                        <?php if (!empty($cmt['user_photo'])): ?>
                            <img src="<?= base_url('uploads/' . e($cmt['user_photo'])) ?>" 
                                 alt="<?= e($cmt['user_name']) ?>" 
                                 class="comment-avatar">
                        <?php else: ?>
                            <div class="comment-avatar">
                                <?= e(strtoupper(mb_substr($cmt['user_name'], 0, 1))) ?>
                            </div>
                        <?php endif; ?>
                        <div class="comment-body">
                            <div class="comment-header">
                                <span class="comment-author-name"><?= e($cmt['user_name']) ?></span>
                                <span class="comment-time"><?= e(timeAgo($cmt['created_at'])) ?></span>
                            </div>
                            <p class="comment-text"><?= e($cmt['body']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════ Delete Confirmation Modal ═══════════ -->
<?php if ($isOwner && $complaint['status'] === 'Diterima'): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px; border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="deleteModalLabel" style="font-family:'Poppins',sans-serif; font-weight:600;">
                    Konfirmasi Penghapusan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center py-4">
                <span class="material-symbols-outlined mb-3 d-block" style="font-size:56px; color:#DC2626;">warning</span>
                <p class="mb-1" style="font-size:15px; color:var(--text-primary,#1E293B);">
                    Apakah Anda yakin ingin menghapus pengaduan ini?
                </p>
                <p class="mb-0" style="font-size:13.5px; color:var(--text-secondary,#64748B);">
                    <strong><?= e($complaint['ticket_code']) ?></strong> — <?= e($complaint['title']) ?>
                </p>
                <p class="mt-2 mb-0 small text-danger">
                    Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px; padding:8px 20px;">
                    Batal
                </button>
                <form method="POST" action="<?= base_url('user/complaint_detail.php?id=' . (int)$complaint['id']) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$complaint['id'] ?>">
                    <button type="submit" class="btn btn-danger d-inline-flex align-items-center gap-1" style="border-radius:8px; padding:8px 20px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        Hapus Pengaduan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════ Leaflet Map Initialization ═══════════ -->
<?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof LaporKota !== 'undefined' && LaporKota.initLeafletMap) {
        LaporKota.initLeafletMap('detail-map', {
            latitude: <?= (float)$complaint['latitude'] ?>,
            longitude: <?= (float)$complaint['longitude'] ?>,
            zoom: 15
        });
    }
});
</script>
<?php endif; ?>

<?php
include __DIR__ . '/../includes/footer.php';
?>
