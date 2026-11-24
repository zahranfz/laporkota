<?php
/**
 * API: Upvote Endpoint
 * Allows logged-in users to toggle their upvote on a civic complaint.
 * 
 * Method: POST only
 * Request Body (JSON): { "complaint_id": int }
 * Header: X-CSRF-TOKEN
 * Response (JSON): { "success": true, "upvoted": bool, "count": int }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();

// Method check: POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.']);
    exit;
}

// Require login (return 401 JSON if not logged in)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk terlebih dahulu untuk memberikan dukungan.']);
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

// Extract and validate complaint_id
$complaintId = isset($input['complaint_id']) ? (int)$input['complaint_id'] : 0;
if ($complaintId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter complaint_id tidak valid.']);
    exit;
}

// Check if complaint exists in database
$stmt = $conn->prepare("SELECT id FROM complaints WHERE id = ?");
$stmt->bind_param("i", $complaintId);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$complaint) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pengaduan tidak ditemukan.']);
    exit;
}

$userId = (int)currentUser()['id'];

// Toggle upvote logic: check if user already upvoted this complaint
$checkStmt = $conn->prepare("SELECT id FROM upvotes WHERE user_id = ? AND complaint_id = ?");
$checkStmt->bind_param("ii", $userId, $complaintId);
$checkStmt->execute();
$existingUpvote = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existingUpvote) {
    // If exists: DELETE (un-upvote)
    $delStmt = $conn->prepare("DELETE FROM upvotes WHERE user_id = ? AND complaint_id = ?");
    $delStmt->bind_param("ii", $userId, $complaintId);
    $delStmt->execute();
    $delStmt->close();
    $upvoted = false;
} else {
    // If not: INSERT
    $insStmt = $conn->prepare("INSERT INTO upvotes (user_id, complaint_id, created_at) VALUES (?, ?, NOW())");
    $insStmt->bind_param("ii", $userId, $complaintId);
    $insStmt->execute();
    $insStmt->close();
    $upvoted = true;
}

// Get updated total upvote count for that complaint
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM upvotes WHERE complaint_id = ?");
$countStmt->bind_param("i", $complaintId);
$countStmt->execute();
$totalCount = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

echo json_encode([
    'success' => true,
    'upvoted' => $upvoted,
    'count'   => $totalCount
]);
exit;
