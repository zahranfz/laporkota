<?php
/**
 * LaporKota Admin - Dashboard
 * Displays overview stats, status charts, category breakdown, monthly trend,
 * full-width GIS map of complaints, and recent complaints table.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Ensure user is authenticated as admin
requireAdmin();

// Set page title for sidebar/topbar
$pageTitle = 'Dashboard';

// ── 1. Fetch Summary Stats ──────────────────────────────────
$statsQuery = "SELECT 
    COUNT(*) AS total_complaints,
    SUM(CASE WHEN status = 'Diterima' THEN 1 ELSE 0 END) AS total_diterima,
    SUM(CASE WHEN status = 'Diproses' THEN 1 ELSE 0 END) AS total_diproses,
    SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) AS total_selesai,
    SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) AS total_ditolak
FROM complaints";
$statsRes = $conn->query($statsQuery);
$stats = $statsRes ? $statsRes->fetch_assoc() : [];

$countTotal    = (int)($stats['total_complaints'] ?? 0);
$countDiterima = (int)($stats['total_diterima'] ?? 0);
$countDiproses = (int)($stats['total_diproses'] ?? 0);
$countSelesai  = (int)($stats['total_selesai'] ?? 0);
$countDitolak  = (int)($stats['total_ditolak'] ?? 0);

// ── 2. Fetch Complaints by Category (for Bar Chart) ─────────
$catQuery = "SELECT c.id, c.name, COUNT(cmp.id) AS total
FROM categories c
LEFT JOIN complaints cmp ON c.id = cmp.category_id
GROUP BY c.id, c.name
ORDER BY total DESC, c.name ASC";
$catRes = $conn->query($catQuery);

$categoryLabels = [];
$categoryCounts = [];
if ($catRes) {
    while ($cat = $catRes->fetch_assoc()) {
        $categoryLabels[] = $cat['name'];
        $categoryCounts[] = (int)$cat['total'];
    }
}

// ── 3. Fetch Monthly Complaints for the Last 6 Months (Line Chart) ─
$monthNamesIndo = [
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
    '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Ags',
    '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
];

// Query grouped by year and month
$monthlyQuery = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
FROM complaints
GROUP BY ym
ORDER BY ym DESC
LIMIT 6";
$monthlyRes = $conn->query($monthlyQuery);
$monthlyRaw = [];
if ($monthlyRes) {
    while ($mRow = $monthlyRes->fetch_assoc()) {
        $monthlyRaw[] = $mRow;
    }
}
$monthlyRaw = array_reverse($monthlyRaw);

$monthlyLabels = [];
$monthlyData = [];
foreach ($monthlyRaw as $mRow) {
    $parts = explode('-', $mRow['ym']);
    $y = $parts[0] ?? '';
    $m = $parts[1] ?? '';
    $monthlyLabels[] = ($monthNamesIndo[$m] ?? $m) . ' ' . $y;
    $monthlyData[]   = (int)$mRow['total'];
}

// ── 4. Fetch Complaints for Leaflet Map ──────────────────────
$mapQuery = "SELECT id, ticket_code, title, address, latitude, longitude, status, created_at
FROM complaints
WHERE latitude IS NOT NULL AND longitude IS NOT NULL
ORDER BY created_at DESC";
$mapRes = $conn->query($mapQuery);

$mapMarkers = [];
if ($mapRes) {
    while ($m = $mapRes->fetch_assoc()) {
        $mapMarkers[] = [
            'id'          => (int)$m['id'],
            'ticket_code' => $m['ticket_code'],
            'title'       => $m['title'],
            'address'     => $m['address'],
            'status'      => $m['status'],
            'badge_class' => statusBadgeClass($m['status']),
            'lat'         => (float)$m['latitude'],
            'lng'         => (float)$m['longitude'],
            'detail_url'  => base_url('user/complaint_detail.php?id=' . $m['id']),
            'date_str'    => formatDate($m['created_at'])
        ];
    }
}

// ── 5. Fetch Recent Complaints (Last 10) ─────────────────────
$recentQuery = "SELECT c.id, c.ticket_code, c.title, c.status, c.created_at, u.name AS pelapor_name
FROM complaints c
JOIN users u ON c.user_id = u.id
ORDER BY c.created_at DESC
LIMIT 10";
$recentRes = $conn->query($recentQuery);
$recentComplaints = [];
if ($recentRes) {
    while ($r = $recentRes->fetch_assoc()) {
        $recentComplaints[] = $r;
    }
}

// Include layout sidebar and header
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- ── STAT CARDS ROW ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Total Pengaduan -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background-color: var(--primary-light); color: var(--primary);">
                <span class="material-symbols-outlined">folder</span>
            </div>
            <div>
                <p class="stat-label">Total Pengaduan</p>
                <div class="stat-value"><?= number_format($countTotal, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Diterima -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background-color: #DBEAFE; color: #2563EB;">
                <span class="material-symbols-outlined">inbox</span>
            </div>
            <div>
                <p class="stat-label">Diterima</p>
                <div class="stat-value" style="color: #2563EB;"><?= number_format($countDiterima, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Diproses -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background-color: #FEF3C7; color: #D97706;">
                <span class="material-symbols-outlined">sync</span>
            </div>
            <div>
                <p class="stat-label">Diproses</p>
                <div class="stat-value" style="color: #D97706;"><?= number_format($countDiproses, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Selesai -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background-color: #DCFCE7; color: #16A34A;">
                <span class="material-symbols-outlined">task_alt</span>
            </div>
            <div>
                <p class="stat-label">Selesai</p>
                <div class="stat-value" style="color: #16A34A;"><?= number_format($countSelesai, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Ditolak -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background-color: #FEE2E2; color: #DC2626;">
                <span class="material-symbols-outlined">cancel</span>
            </div>
            <div>
                <p class="stat-label">Ditolak</p>
                <div class="stat-value" style="color: #DC2626;"><?= number_format($countDitolak, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ── CHARTS SECTION (2 COLUMNS) ─────────────────────────── -->
<div class="row g-4 mb-4">
    <!-- Status Distribution Doughnut Chart -->
    <div class="col-12 col-lg-5">
        <div class="card card-custom h-100 p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0 fs-6">Distribusi Status Pengaduan</h5>
                <span class="badge" style="background-color: var(--primary-light); color: var(--primary);">Real-time</span>
            </div>
            <div style="position: relative; height: 280px; width: 100%; display: flex; align-items: center; justify-content: center;">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="row text-center mt-3 pt-3 border-top g-2">
                <div class="col-3">
                    <small class="text-muted d-block">Diterima</small>
                    <span class="fw-bold" style="color: #2563EB;"><?= $countDiterima ?></span>
                </div>
                <div class="col-3">
                    <small class="text-muted d-block">Diproses</small>
                    <span class="fw-bold" style="color: #F59E0B;"><?= $countDiproses ?></span>
                </div>
                <div class="col-3">
                    <small class="text-muted d-block">Selesai</small>
                    <span class="fw-bold" style="color: #16A34A;"><?= $countSelesai ?></span>
                </div>
                <div class="col-3">
                    <small class="text-muted d-block">Ditolak</small>
                    <span class="fw-bold" style="color: #DC2626;"><?= $countDitolak ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Breakdown Bar Chart -->
    <div class="col-12 col-lg-7">
        <div class="card card-custom h-100 p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0 fs-6">Pengaduan per Kategori</h5>
                <span class="badge" style="background-color: var(--border-light); color: var(--text-secondary);">Semua Data</span>
            </div>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── LINE CHART: MONTHLY TREND ───────────────────────────── -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="mb-1 fs-6">Tren Pengaduan per Bulan</h5>
                    <p class="text-muted small mb-0">Statistik laporan masuk dalam 6 bulan terakhir</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge" style="background-color: var(--primary-light); color: var(--primary);">6 Bulan Terakhir</span>
                </div>
            </div>
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── LEAFLET GIS MAP (FULL WIDTH) ────────────────────────── -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom p-4">
            <div class="d-flex flex-column flex-md-row md-align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="mb-1 fs-6">Peta Sebaran Pengaduan Kota Purwokerto</h5>
                    <p class="text-muted small mb-0">Pemetaan geografis seluruh titik laporan pengaduan masyarakat</p>
                </div>
                <!-- Map Legend -->
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge-status badge-diterima" style="font-size: 0.75rem;">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#2563EB;"></span> Diterima
                    </span>
                    <span class="badge-status badge-diproses" style="font-size: 0.75rem;">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#F59E0B;"></span> Diproses
                    </span>
                    <span class="badge-status badge-selesai" style="font-size: 0.75rem;">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#16A34A;"></span> Selesai
                    </span>
                    <span class="badge-status badge-ditolak" style="font-size: 0.75rem;">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#DC2626;"></span> Ditolak
                    </span>
                </div>
            </div>
            <div id="map"></div>
        </div>
    </div>
</div>

<!-- ── RECENT COMPLAINTS TABLE (LAST 10) ───────────────────── -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom overflow-hidden">
            <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1 fs-6">Pengaduan Terbaru</h5>
                    <p class="text-muted small mb-0">10 laporan terkini dari warga Purwokerto</p>
                </div>
                <a href="<?= base_url('admin/complaints.php') ?>" class="btn btn-sm btn-teal d-flex align-items-center gap-1">
                    <span>Lihat Semua</span>
                    <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>No. Tiket</th>
                            <th>Judul Pengaduan</th>
                            <th>Pelapor</th>
                            <th>Status</th>
                            <th>Tanggal Masuk</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentComplaints)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Belum ada pengaduan yang terdaftar.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentComplaints as $c): ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('admin/complaints.php?search=' . urlencode($c['ticket_code'])) ?>" class="fw-semibold text-decoration-none" style="color: var(--primary);">
                                            <?= e($c['ticket_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 320px;" title="<?= e($c['title']) ?>">
                                            <?= e($c['title']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 28px; height: 28px; background-color: var(--primary); font-size: 0.75rem;">
                                                <?= strtoupper(substr($c['pelapor_name'], 0, 1)) ?>
                                            </div>
                                            <span><?= e($c['pelapor_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= statusBadgeClass($c['status']) ?>">
                                            <?= e($c['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-secondary small">
                                        <?= formatDate($c['created_at']) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= base_url('admin/complaints.php?search=' . urlencode($c['ticket_code'])) ?>" class="btn btn-sm btn-light border" title="Kelola di Pengaduan">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">manage_search</span>
                                        </a>
                                        <a href="<?= base_url('user/complaint_detail.php?id=' . $c['id']) ?>" class="btn btn-sm btn-light border text-primary" title="Lihat Publik" target="_blank">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>

<!-- Page Specific Inline Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── 1. Status Doughnut Chart ────────────────────────────
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const totalStatuses = <?= $countTotal ?>;
    
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Diterima', 'Diproses', 'Selesai', 'Ditolak'],
            datasets: [{
                data: [
                    <?= $countDiterima ?>,
                    <?= $countDiproses ?>,
                    <?= $countSelesai ?>,
                    <?= $countDitolak ?>
                ],
                backgroundColor: ['#2563EB', '#F59E0B', '#16A34A', '#DC2626'],
                borderWidth: 2,
                borderColor: '#FFFFFF',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 16,
                        font: { family: 'Inter', size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const pct = totalStatuses > 0 ? ((val / totalStatuses) * 100).toFixed(1) : 0;
                            return ` ${context.label}: ${val} (${pct}%)`;
                        }
                    }
                }
            },
            cutout: '68%'
        }
    });

    // ── 2. Category Bar Chart ───────────────────────────────
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Jumlah Pengaduan',
                data: <?= json_encode($categoryCounts) ?>,
                backgroundColor: '#0F766E',
                hoverBackgroundColor: '#0D6962',
                borderRadius: 6,
                maxBarThickness: 36
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.raw} pengaduan`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { family: 'Inter', size: 11 }
                    },
                    grid: { color: '#F1F5F9' }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Inter', size: 11 }
                    }
                }
            }
        }
    });

    // ── 3. Monthly Trend Line Chart ─────────────────────────
    const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Jumlah Pengaduan',
                data: <?= json_encode($monthlyData) ?>,
                borderColor: '#0F766E',
                backgroundColor: 'rgba(15, 118, 110, 0.08)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#0F766E',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.raw} pengaduan`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { family: 'Inter', size: 11 }
                    },
                    grid: { color: '#F1F5F9' }
                },
                x: {
                    grid: { color: '#F1F5F9' },
                    ticks: {
                        font: { family: 'Inter', size: 11 }
                    }
                }
            }
        }
    });

    // ── 4. Leaflet Map Initialization ───────────────────────
    // Purwokerto coordinates: -7.4240, 109.2330
    const map = L.map('map').setView([-7.4240, 109.2330], 13);

    // OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    // Complaint Markers Data
    const markers = <?= json_encode($mapMarkers, JSON_UNESCAPED_UNICODE) ?>;
    const statusColors = {
        'Diterima': '#2563EB',
        'Diproses': '#F59E0B',
        'Selesai': '#16A34A',
        'Ditolak': '#DC2626'
    };

    if (markers.length > 0) {
        const bounds = [];
        markers.forEach(function(item) {
            const color = statusColors[item.status] || '#0F766E';

            const marker = L.circleMarker([item.lat, item.lng], {
                radius: 8,
                fillColor: color,
                color: '#FFFFFF',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(map);

            bounds.push([item.lat, item.lng]);

            // Popup content
            const popupHtml = `
                <div style="min-width: 200px; font-family: 'Inter', sans-serif;">
                    <div style="font-size: 11px; font-weight: 700; color: #0F766E;">${item.ticket_code}</div>
                    <div style="font-size: 13px; font-weight: 600; margin: 4px 0; color: #1E293B;">${item.title}</div>
                    <div style="margin-bottom: 6px;">
                        <span class="badge-status ${item.badge_class}" style="font-size: 11px; padding: 2px 8px;">${item.status}</span>
                    </div>
                    <div style="font-size: 11px; color: #64748B; margin-bottom: 8px;">
                        <span class="material-symbols-outlined" style="font-size: 12px; vertical-align: -1px;">location_on</span>
                        ${item.address}
                    </div>
                    <a href="${item.detail_url}" class="btn btn-sm btn-teal text-white w-100 text-center text-decoration-none" style="font-size: 11px; padding: 4px 8px;" target="_blank">
                        Lihat Detail
                    </a>
                </div>
            `;
            marker.bindPopup(popupHtml);
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    }
});
</script>

</body>
</html>
