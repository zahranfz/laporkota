<?php
/**
 * Shared Helper Functions
 */

// Escape HTML output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Base URL helper
function base_url($path = '') {
    return '/aduan-kota/' . ltrim($path, '/');
}

// ── CSRF Protection ──────────────────────────────────────────

function csrf_token() {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    startSession();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF tidak valid. Silakan muat ulang halaman.');
    }
}

// ── Flash Messages ───────────────────────────────────────────

function flash($key, $message) {
    startSession();
    $_SESSION['flash_' . $key] = $message;
}

function get_flash($key) {
    startSession();
    $k = 'flash_' . $key;
    $msg = $_SESSION[$k] ?? null;
    unset($_SESSION[$k]);
    return $msg;
}

// ── Redirect ─────────────────────────────────────────────────

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ── Ticket Code Generator ────────────────────────────────────

function generateTicketCode($conn) {
    $year = date('Y');
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM complaints WHERE ticket_code LIKE ?"
    );
    $pattern = "LK-{$year}-%";
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $next = ($row['cnt'] ?? 0) + 1;
    return sprintf("LK-%s-%04d", $year, $next);
}

// ── File Upload ──────────────────────────────────────────────

function uploadFile($file, $targetDir = null) {
    if ($targetDir === null) {
        $targetDir = __DIR__ . '/../uploads/';
    }
    $targetDir = rtrim($targetDir, '/') . '/';

    // Pastikan direktori tujuan tersedia dan memiliki izin tulis
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return ['error' => 'Direktori penyimpanan file tidak tersedia atau gagal dibuat.'];
        }
        @chmod($targetDir, 0777);
    }

    if (!is_writable($targetDir)) {
        return ['error' => 'Direktori penyimpanan file tidak memiliki izin penulisan (writable).'];
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
    $maxSize     = 2 * 1024 * 1024; // 2 MB

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Gagal mengunggah file. Kode error: ' . $file['error']];
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        return ['error' => 'Ukuran file maksimal 2 MB.'];
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime)) {
        return ['error' => 'Format file tidak didukung. Gunakan JPG, PNG, atau WebP.'];
    }

    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        return ['error' => 'Ekstensi file tidak diizinkan.'];
    }

    // Generate random filename
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $targetDir . $newName;

    if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['error' => 'Gagal menyimpan file. Pastikan izin folder uploads sudah benar.'];
    }

    return ['filename' => $newName];
}

// ── Pagination ───────────────────────────────────────────────

function paginate($totalRows, $perPage, $currentPage) {
    $totalPages = max(1, ceil($totalRows / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total_rows'   => $totalRows,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset
    ];
}

// ── Time Ago (Indonesian) ────────────────────────────────────

function timeAgo($datetime) {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . ' tahun yang lalu';
    if ($diff->m > 0) return $diff->m . ' bulan yang lalu';
    if ($diff->d > 0) return $diff->d . ' hari yang lalu';
    if ($diff->h > 0) return $diff->h . ' jam yang lalu';
    if ($diff->i > 0) return $diff->i . ' menit yang lalu';
    return 'Baru saja';
}

// ── Format Date (Indonesian) ─────────────────────────────────

function formatDate($datetime) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $dt = new DateTime($datetime);
    $day   = $dt->format('j');
    $month = $months[(int)$dt->format('n')];
    $year  = $dt->format('Y');
    $time  = $dt->format('H:i');
    return "{$day} {$month} {$year}, {$time} WIB";
}

// ── Status Badge Classes ─────────────────────────────────────

function statusBadgeClass($status) {
    switch ($status) {
        case 'Diterima': return 'badge-diterima';
        case 'Diproses': return 'badge-diproses';
        case 'Selesai':  return 'badge-selesai';
        case 'Ditolak':  return 'badge-ditolak';
        default:         return 'badge-diterima';
    }
}
