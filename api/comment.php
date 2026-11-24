<?php
/**
 * API: Comment Endpoint
 * Handles fetching comments for a complaint and posting new comments.
 * 
 * GET: fetch comments for ?complaint_id= — returns JSON array of comments
 * POST: submit new comment — expects JSON body { complaint_id, body }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// ── GET: Fetch Comments ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $complaintId = isset($_GET['complaint_id']) ? (int)$_GET['complaint_id'] : 0;

    if ($complaintId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Parameter complaint_id tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT c.id, c.body, c.created_at, u.name AS user_name, u.photo AS user_photo
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.complaint_id = ?
        ORDER BY c.created_at ASC, c.id ASC
    ");
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'id'         => (int)$row['id'],
            'user_name'  => $row['user_name'],
            'user_photo' => $row['user_photo'],
            'photo'      => $row['user_photo'],
            'body'       => $row['body'],
            'created_at' => $row['created_at'],
            'timeAgo'    => timeAgo($row['created_at']),
            'time_ago'   => timeAgo($row['created_at'])
        ];
    }
    $stmt->close();

    echo json_encode($comments, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST: Add Comment ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    startSession();

    // Require login (return 401 JSON if not logged in)
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Silakan masuk terlebih dahulu untuk mengirim komentar.']);
        exit;
    }

    // Parse JSON request body
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Verify CSRF via X-CSRF-TOKEN header or in JSON payload
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) && isset($input['csrf_token'])) {
        $_POST['csrf_token'] = $input['csrf_token'];
    }
    verify_csrf();

    $complaintId = isset($input['complaint_id']) ? (int)$input['complaint_id'] : 0;
    $body        = trim($input['body'] ?? '');

    if ($complaintId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter complaint_id tidak valid.']);
        exit;
    }

    // Validate body: not empty, max 1000 characters
    if ($body === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Komentar tidak boleh kosong.']);
        exit;
    }

    if (mb_strlen($body) > 1000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Komentar maksimal 1000 karakter.']);
        exit;
    }

    // Verify complaint exists
    $stmt = $conn->prepare("SELECT id FROM complaints WHERE id = ?");
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengaduan tidak ditemukan.']);
        exit;
    }
    $stmt->close();

    $userId = (int)currentUser()['id'];

    // Insert comment
    $insStmt = $conn->prepare("INSERT INTO comments (complaint_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())");
    $insStmt->bind_param("iis", $complaintId, $userId, $body);
    if (!$insStmt->execute()) {
        $insStmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan komentar. Silakan coba lagi.']);
        exit;
    }
    $commentId = $insStmt->insert_id;
    $insStmt->close();

    // Fetch newly created comment details
    $fetchStmt = $conn->prepare("
        SELECT c.id, c.body, c.created_at, u.name AS user_name, u.photo AS user_photo
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $fetchStmt->bind_param("i", $commentId);
    $fetchStmt->execute();
    $commentData = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    echo json_encode([
        'success' => true,
        'comment' => [
            'id'         => (int)$commentData['id'],
            'user_name'  => $commentData['user_name'],
            'user_photo' => $commentData['user_photo'],
            'body'       => $commentData['body'],
            'created_at' => $commentData['created_at'],
            'time_ago'   => timeAgo($commentData['created_at'])
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Any other method: 405 Method Not Allowed
http_response_code(405);
echo json_encode(['error' => 'Metode request tidak diizinkan.']);
exit;
