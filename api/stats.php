<?php
/**
 * API: Stats Endpoint
 * Returns platform analytics for the admin dashboard.
 * 
 * Method: GET only
 * Requires: Admin role (requireAdmin)
 * Response (JSON):
 *  - total_complaints: int
 *  - by_status: { Diterima: int, Diproses: int, Selesai: int, Ditolak: int }
 *  - by_category: [ { name: string, count: int } ]
 *  - by_month: last 6 months [ { month: string, count: int } ] for Chart.js
 *  - recent_complaints: last 5 [ { id: int, title: string, status: string, created_at: string } ]
 *  - total_users: int
 *  - total_active_users: int
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metode request tidak diizinkan. Gunakan GET.']);
    exit;
}

// 1. Total Complaints
$totalRes = $conn->query("SELECT COUNT(*) AS total FROM complaints");
$totalComplaints = (int)($totalRes->fetch_assoc()['total'] ?? 0);

// 2. By Status (Diterima, Diproses, Selesai, Ditolak)
$byStatus = [
    'Diterima' => 0,
    'Diproses' => 0,
    'Selesai'  => 0,
    'Ditolak'  => 0
];
$statusRes = $conn->query("SELECT status, COUNT(*) AS count FROM complaints GROUP BY status");
if ($statusRes) {
    while ($row = $statusRes->fetch_assoc()) {
        if (array_key_exists($row['status'], $byStatus)) {
            $byStatus[$row['status']] = (int)$row['count'];
        }
    }
}

// 3. By Category: [{ name, count }]
$byCategory = [];
$catRes = $conn->query("
    SELECT cat.name, COUNT(c.id) AS count
    FROM categories cat
    LEFT JOIN complaints c ON cat.id = c.category_id
    GROUP BY cat.id, cat.name
    ORDER BY count DESC, cat.name ASC
");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $byCategory[] = [
            'name'  => $row['name'],
            'count' => (int)$row['count']
        ];
    }
}

// 4. By Month: last 6 months [{ month, count }] for Chart.js
$monthRes = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS count
    FROM complaints
    GROUP BY ym
");
$countsByMonth = [];
if ($monthRes) {
    while ($row = $monthRes->fetch_assoc()) {
        $countsByMonth[$row['ym']] = (int)$row['count'];
    }
}

$byMonth = [];
$now = new DateTime();
$now->setDate((int)date('Y'), (int)date('n'), 1);

for ($i = 5; $i >= 0; $i--) {
    $dt = clone $now;
    $dt->modify("-$i month");
    $ym = $dt->format('Y-m');

    $byMonth[] = [
        'month' => $ym,
        'count' => $countsByMonth[$ym] ?? 0
    ];
}

// 5. Recent Complaints: last 5 with id, title, status, created_at
$recentComplaints = [];
$recentRes = $conn->query("
    SELECT id, title, status, created_at
    FROM complaints
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");
if ($recentRes) {
    while ($row = $recentRes->fetch_assoc()) {
        $recentComplaints[] = [
            'id'         => (int)$row['id'],
            'title'      => $row['title'],
            'status'     => $row['status'],
            'created_at' => $row['created_at']
        ];
    }
}

// 6. Total Users and Total Active Users
$uRes = $conn->query("
    SELECT 
        COUNT(*) AS total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS total_active_users
    FROM users
");
$uRow = $uRes ? $uRes->fetch_assoc() : [];
$totalUsers = (int)($uRow['total_users'] ?? 0);
$totalActiveUsers = (int)($uRow['total_active_users'] ?? 0);

// Output JSON
echo json_encode([
    'total_complaints'   => $totalComplaints,
    'by_status'          => $byStatus,
    'by_category'        => $byCategory,
    'by_month'           => $byMonth,
    'recent_complaints'  => $recentComplaints,
    'total_users'        => $totalUsers,
    'total_active_users' => $totalActiveUsers
], JSON_UNESCAPED_UNICODE);
exit;
