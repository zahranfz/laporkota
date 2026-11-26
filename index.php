<?php
/**
 * LaporKota - Civic Complaint Platform (Purwokerto, Banyumas)
 * Landing Page (Beranda)
 */

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';

startSession();

// Fetch Dynamic Statistics from Database
$stats = [
    'total'    => 0,
    'diproses' => 0,
    'selesai'  => 0,
    'warga'    => 0,
];

// 1. Total Laporan
$resTotal = $conn->query("SELECT COUNT(*) AS cnt FROM complaints");
if ($resTotal && $row = $resTotal->fetch_assoc()) {
    $stats['total'] = (int)$row['cnt'];
}

// 2. Sedang Diproses
$resDiproses = $conn->query("SELECT COUNT(*) AS cnt FROM complaints WHERE status = 'Diproses'");
if ($resDiproses && $row = $resDiproses->fetch_assoc()) {
    $stats['diproses'] = (int)$row['cnt'];
}

// 3. Telah Selesai
$resSelesai = $conn->query("SELECT COUNT(*) AS cnt FROM complaints WHERE status = 'Selesai'");
if ($resSelesai && $row = $resSelesai->fetch_assoc()) {
    $stats['selesai'] = (int)$row['cnt'];
}

// 4. Warga Aktif
$resWarga = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'warga' AND is_active = 1");
if ($resWarga && $row = $resWarga->fetch_assoc()) {
    $stats['warga'] = (int)$row['cnt'];
}

// Fetch All Categories with Complaint Counts
$categories = [];
$catQuery = $conn->query("
    SELECT c.id, c.name, c.icon, COUNT(cmp.id) AS total_complaints
    FROM categories c
    LEFT JOIN complaints cmp ON c.id = cmp.category_id
    GROUP BY c.id, c.name, c.icon
    ORDER BY c.id ASC
");
if ($catQuery) {
    while ($cat = $catQuery->fetch_assoc()) {
        $categories[] = $cat;
    }
}

// Fetch Last 6 Complaints
$latestComplaints = [];
$complaintsQuery = $conn->query("
    SELECT c.id, c.ticket_code, c.title, c.description, c.address, c.photo, c.status, c.created_at,
           cat.name AS category_name, cat.icon AS category_icon,
           u.name AS user_name,
           (SELECT COUNT(*) FROM upvotes WHERE complaint_id = c.id) AS upvotes_count,
           (SELECT COUNT(*) FROM comments WHERE complaint_id = c.id) AS comments_count
    FROM complaints c
    JOIN categories cat ON c.category_id = cat.id
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
    LIMIT 6
");
if ($complaintsQuery) {
    while ($cmp = $complaintsQuery->fetch_assoc()) {
        $latestComplaints[] = $cmp;
    }
}

$currentUser = currentUser();
$pageTitle = 'LaporKota - Layanan Aspirasi & Pengaduan Warga Purwokerto';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <!-- Google Fonts: Poppins & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <!-- Google Material Symbols Outlined -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-teal: #0F766E;
            --primary-dark: #0D6962;
            --primary-light: #E6F5F3;
            --accent-amber: #F59E0B;
            --accent-dark: #D97706;
            --bg-color: #F8F9FF;
            --surface-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --border-light: #F1F5F9;
            --status-diterima: #2563EB;
            --status-diproses: #F59E0B;
            --status-selesai: #16A34A;
            --status-ditolak: #DC2626;
            --danger-color: #DC2626;
            --success-color: #16A34A;
            --radius-card: 12px;
            --radius-input: 8px;
            --radius-pill: 50px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-elevated: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--text-primary);
        }

        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Material Symbols vertical alignment */
        .material-symbols-outlined {
            vertical-align: middle;
            font-size: 22px;
            line-height: 1;
        }

        /* Buttons */
        .btn-teal {
            background-color: var(--primary-teal);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-input);
            padding: 10px 22px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-teal:hover, .btn-teal:focus {
            background-color: var(--primary-dark);
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.3);
            transform: translateY(-1px);
        }

        .btn-outline-teal {
            background-color: transparent;
            color: var(--primary-teal);
            border: 1.5px solid var(--primary-teal);
            border-radius: var(--radius-input);
            padding: 10px 22px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline-teal:hover, .btn-outline-teal:focus {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-amber {
            background-color: var(--accent-amber);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-input);
            padding: 10px 22px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-amber:hover {
            background-color: var(--accent-dark);
            color: #FFFFFF;
        }

        /* Navbar */
        .navbar-custom {
            background-color: var(--surface-card);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            padding: 14px 0;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-primary);
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-teal), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            box-shadow: 0 2px 6px rgba(15, 118, 110, 0.3);
        }

        .brand-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: -0.5px;
            color: var(--primary-teal);
        }

        .brand-name span {
            color: var(--accent-amber);
        }

        .nav-link-custom {
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 8px 14px;
            border-radius: var(--radius-input);
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .nav-link-custom:hover {
            color: var(--primary-teal);
            background-color: var(--primary-light);
        }

        /* Homepage navigation: desktop row, mobile collapsible panel */
        .navbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-width: 0;
        }

        .navbar-mobile-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-input);
            background: #FFFFFF;
            color: var(--primary-teal);
        }

        .navbar-mobile-toggle .material-symbols-outlined {
            font-size: 25px;
        }

        .navbar-collapse-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex: 1 1 auto;
            min-width: 0;
            gap: 12px;
            margin-left: 12px;
        }

        .navbar-links-custom {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 2px;
            min-width: 0;
        }

        .navbar-auth-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 auto;
            gap: 8px;
        }

        .navbar-auth-actions > a,
        .navbar-auth-actions .dropdown > button {
            white-space: nowrap;
        }

        /* Hero Section */
        .hero-section {
            padding: 80px 0 60px;
            background: linear-gradient(180deg, #FFFFFF 0%, var(--bg-color) 100%);
            border-bottom: 1px solid var(--border-light);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--primary-light);
            color: var(--primary-teal);
            font-weight: 600;
            font-size: 0.85rem;
            padding: 6px 16px;
            border-radius: var(--radius-pill);
            margin-bottom: 20px;
            border: 1px solid rgba(15, 118, 110, 0.2);
        }

        .hero-title {
            font-size: 3.1rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -1px;
            color: var(--text-primary);
            margin-bottom: 20px;
        }

        .hero-title span {
            color: var(--primary-teal);
            position: relative;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 32px;
            max-width: 580px;
        }

        .hero-cta-group {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 36px;
        }

        .hero-trust-badges {
            display: flex;
            align-items: center;
            gap: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .trust-item .material-symbols-outlined {
            color: var(--primary-teal);
            font-size: 20px;
        }

        .hero-visual-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow-elevated);
            padding: 28px;
            position: relative;
        }

        .hero-preview-badge {
            position: absolute;
            top: -14px;
            right: 24px;
            background: linear-gradient(135deg, var(--accent-amber), var(--accent-dark));
            color: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            box-shadow: var(--shadow-sm);
        }

        /* Stats Cards */
        .stats-section {
            padding: 50px 0;
            margin-top: -30px;
            position: relative;
            z-index: 20;
        }

        .stat-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-elevated);
        }

        .stat-icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-number {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.85rem;
            line-height: 1.1;
            margin-bottom: 2px;
            color: var(--text-primary);
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* Section Titles */
        .section-header {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 48px;
        }

        .section-tag {
            display: inline-block;
            color: var(--primary-teal);
            background-color: var(--primary-light);
            font-weight: 600;
            font-size: 0.82rem;
            padding: 4px 14px;
            border-radius: var(--radius-pill);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 2.1rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .section-desc {
            font-size: 1rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* How It Works Steps */
        .how-it-works-section {
            padding: 70px 0;
        }

        .step-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 32px 24px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            height: 100%;
            position: relative;
            transition: all 0.2s ease;
        }

        .step-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-elevated);
            border-color: var(--primary-teal);
        }

        .step-number {
            position: absolute;
            top: 16px;
            right: 18px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--border-color);
        }

        .step-icon-box {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 16px;
            background: var(--primary-light);
            color: var(--primary-teal);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .step-icon-box .material-symbols-outlined {
            font-size: 32px;
        }

        .step-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .step-desc {
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 0;
        }

        /* Categories */
        .categories-section {
            padding: 70px 0;
            background-color: #FFFFFF;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }

        .category-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 24px;
            text-align: center;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: var(--text-primary);
            display: block;
            height: 100%;
        }

        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-elevated);
            border-color: var(--primary-teal);
            color: var(--primary-teal);
        }

        .category-icon-wrap {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            background-color: var(--primary-light);
            color: var(--primary-teal);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            transition: all 0.2s;
        }

        .category-card:hover .category-icon-wrap {
            background-color: var(--primary-teal);
            color: #FFFFFF;
        }

        .category-icon-wrap .material-symbols-outlined {
            font-size: 28px;
        }

        .category-name {
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .category-count {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Complaints Section */
        .complaints-section {
            padding: 70px 0;
        }

        .complaint-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .complaint-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-elevated);
        }

        .complaint-img-wrap {
            height: 170px;
            background: linear-gradient(135deg, #E2E8F0, #F1F5F9);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .complaint-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .complaint-placeholder-art {
            text-align: center;
            color: var(--text-secondary);
        }

        .complaint-placeholder-art .material-symbols-outlined {
            font-size: 44px;
            color: var(--primary-teal);
            opacity: 0.7;
            margin-bottom: 4px;
        }

        .complaint-badges-bar {
            position: absolute;
            bottom: 12px;
            left: 12px;
            right: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge-cat {
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-primary);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }

        /* Status Badges */
        .badge-diterima {
            background-color: #DBEAFE;
            color: var(--status-diterima);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
        }

        .badge-diproses {
            background-color: #FEF3C7;
            color: var(--status-diproses);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
        }

        .badge-selesai {
            background-color: #DCFCE7;
            color: var(--status-selesai);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
        }

        .badge-ditolak {
            background-color: #FEE2E2;
            color: var(--status-ditolak);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
        }

        .complaint-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .complaint-title {
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.4;
            color: var(--text-primary);
        }

        .complaint-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.5;
            flex-grow: 1;
        }

        .complaint-location {
            font-size: 0.82rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 14px;
        }

        .complaint-footer {
            border-top: 1px solid var(--border-light);
            padding-top: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .ticket-pill {
            font-family: monospace;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--primary-teal);
            background: var(--primary-light);
            padding: 2px 8px;
            border-radius: 4px;
        }

        /* SDG 11 Banner */
        .sdg-section {
            padding: 70px 0;
            background: linear-gradient(135deg, #0F766E 0%, #064E3B 100%);
            color: #FFFFFF;
            position: relative;
            overflow: hidden;
        }

        .sdg-section::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: rgba(245, 158, 11, 0.15);
            border-radius: 50%;
            pointer-events: none;
        }

        .sdg-badge-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--accent-amber);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
        }

        .sdg-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: #FFFFFF;
            line-height: 1.3;
            margin-bottom: 16px;
        }

        .sdg-desc {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.88);
            line-height: 1.7;
            margin-bottom: 32px;
        }

        .sdg-pillar-card {
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: var(--radius-card);
            padding: 20px;
            backdrop-filter: blur(8px);
            margin-bottom: 16px;
        }

        .sdg-pillar-icon {
            color: #FCD34D;
            margin-bottom: 8px;
        }

        .sdg-pillar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .sdg-pillar-desc {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0;
            line-height: 1.4;
        }

        /* Footer */
        .footer-custom {
            background-color: #0F172A;
            color: #94A3B8;
            padding: 60px 0 30px;
            font-size: 0.9rem;
        }

        .footer-custom h5 {
            color: #FFFFFF;
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: #94A3B8;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #FFFFFF;
        }

        .footer-bottom {
            border-top: 1px solid #1E293B;
            padding-top: 24px;
            margin-top: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.85rem;
        }

        @media (max-width: 991.98px) {
            .hero-title {
                font-size: 2.3rem;
            }
            .hero-section {
                padding: 50px 0 40px;
            }

            .navbar-custom {
                padding: 10px 0;
            }

            .navbar-inner {
                width: 100%;
            }

            .navbar-mobile-toggle {
                display: inline-flex;
            }

            .navbar-collapse-custom {
                display: none;
                width: 100%;
                margin: 0;
                padding-top: 12px;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .navbar-collapse-custom.show {
                display: flex;
            }

            .navbar-links-custom {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 2px;
            }

            .navbar-links-custom .nav-link-custom {
                display: flex;
                align-items: center;
                width: 100%;
                padding: 10px 12px;
            }

            .navbar-auth-actions {
                width: 100%;
                justify-content: stretch;
                gap: 10px;
            }

            .navbar-auth-actions > a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 1 1 0;
                min-width: 0;
                padding: 9px 12px;
            }

            .navbar-auth-actions .dropdown {
                width: 100%;
            }

            .navbar-auth-actions .dropdown > button {
                width: 100%;
                justify-content: center;
                min-width: 0;
            }
        }

        @media (min-width: 992px) {
            .navbar-custom .container-custom {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .navbar-inner {
                flex: 0 0 auto;
            }

            .navbar-collapse-custom {
                display: flex !important;
                margin-left: 0;
            }
        }

        @media (max-width: 380px) {
            .container-custom {
                padding-left: 14px;
                padding-right: 14px;
            }

            .brand-name {
                font-size: 1.2rem;
            }

            .navbar-auth-actions > a {
                gap: 5px;
                padding-left: 8px;
                padding-right: 8px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="container-custom">
        <div class="navbar-inner">
            <!-- Logo -->
            <a href="<?= base_url('index.php') ?>" class="brand-logo">
                <div class="brand-icon">
                    <span class="material-symbols-outlined" style="font-size: 24px;">campaign</span>
                </div>
                <div class="brand-name">Lapor<span>Kota</span></div>
            </a>

            <!-- Mobile Navigation Toggle -->
            <button
                class="navbar-mobile-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#homeNavbar"
                aria-controls="homeNavbar"
                aria-expanded="false"
                aria-label="Buka atau tutup menu navigasi"
            >
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>

        <!-- Navigation Links & Auth Actions -->
        <div class="collapse navbar-collapse-custom" id="homeNavbar">
            <div class="navbar-links-custom">
                <a href="<?= base_url('index.php') ?>" class="nav-link-custom">Beranda</a>
                <a href="#cara-kerja" class="nav-link-custom">Cara Kerja</a>
                <a href="#kategori" class="nav-link-custom">Kategori</a>
                <a href="#pengaduan-terbaru" class="nav-link-custom">Pengaduan Terbaru</a>
                <a href="#sdg11" class="nav-link-custom">SDG 11</a>
            </div>

            <div class="navbar-auth-actions">
                <?php if ($currentUser): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-teal d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="material-symbols-outlined">account_circle</span>
                            <span><?= e($currentUser['name']) ?></span>
                            <span class="badge bg-secondary" style="font-size: 0.7rem;"><?= e($currentUser['role']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: var(--radius-input);">
                            <?php if ($currentUser['role'] === 'admin'): ?>
                                <li><a class="dropdown-item py-2" href="<?= base_url('admin/dashboard.php') ?>"><span class="material-symbols-outlined align-middle me-2">dashboard</span>Dashboard Admin</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item py-2" href="<?= base_url('user/profile.php') ?>"><span class="material-symbols-outlined align-middle me-2">account_circle</span>Profil Saya</a></li>
                                <li><a class="dropdown-item py-2" href="<?= base_url('user/history.php') ?>"><span class="material-symbols-outlined align-middle me-2">history</span>Riwayat Saya</a></li>
                                <li><a class="dropdown-item py-2" href="<?= base_url('user/complaint_create.php') ?>"><span class="material-symbols-outlined align-middle me-2">add_circle</span>Buat Laporan</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?= base_url('auth/logout.php') ?>"><span class="material-symbols-outlined align-middle me-2">logout</span>Keluar</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline-teal">
                        <span class="material-symbols-outlined" style="font-size: 18px;">login</span>
                        <span>Masuk</span>
                    </a>
                    <a href="<?= base_url('auth/register.php') ?>" class="btn btn-teal">
                        <span class="material-symbols-outlined" style="font-size: 18px;">person_add</span>
                        <span>Daftar</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>



<!-- 1. Hero Section -->
<section class="hero-section">
    <div class="container-custom">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="hero-badge">
                    <span class="material-symbols-outlined" style="font-size: 18px;">location_city</span>
                    <span>Platform Aspirasi Warga Purwokerto &amp; Banyumas</span>
                </div>
                <h1 class="hero-title">
                    Laporkan Masalah Kotamu, <span>Wujudkan Solusi Nyata.</span>
                </h1>
                <p class="hero-subtitle">
                    Sampaikan keluhan dan aspirasi terkait jalan rusak, drainase banjir, sampah, dan fasilitas publik di Purwokerto secara mudah, transparan, dan terhubung langsung dengan dinas terkait.
                </p>

                <div class="hero-cta-group">
                    <a href="<?= base_url('user/complaint_create.php') ?>" class="btn btn-teal btn-lg">
                        <span class="material-symbols-outlined" style="font-size: 24px;">add_circle</span>
                        <span>Buat Laporan</span>
                    </a>
                    <a href="#pengaduan-terbaru" class="btn btn-outline-teal btn-lg">
                        <span class="material-symbols-outlined" style="font-size: 24px;">travel_explore</span>
                        <span>Lihat Pengaduan</span>
                    </a>
                </div>

                <div class="hero-trust-badges">
                    <div class="trust-item">
                        <span class="material-symbols-outlined">speed</span>
                        <span>Respon Cepat 1x24 Jam</span>
                    </div>
                    <div class="trust-item">
                        <span class="material-symbols-outlined">visibility</span>
                        <span>100% Terbuka &amp; Transparan</span>
                    </div>
                    <div class="trust-item">
                        <span class="material-symbols-outlined">domain_verification</span>
                        <span>Diverifikasi Pemkab Banyumas</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hero-visual-card">
                    <div class="hero-preview-badge">Live Sistem Purwokerto</div>

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="brand-icon" style="width: 48px; height: 48px; border-radius: 12px;">
                            <span class="material-symbols-outlined" style="font-size: 28px;">support_agent</span>
                        </div>
                        <div>
                            <h5 class="mb-1" style="font-size: 1.1rem;">Layanan Terpadu LaporKota</h5>
                            <p class="mb-0 text-secondary" style="font-size: 0.85rem;">Terhubung langsung dengan dinas teknis</p>
                        </div>
                    </div>

                    <!-- Flow tracker mini preview -->
                    <div class="p-3 mb-3" style="background: var(--bg-color); border-radius: var(--radius-input); border: 1px solid var(--border-color);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary" style="font-size: 0.72rem;">STATUS TERBARU</span>
                            <span class="text-muted" style="font-size: 0.75rem;">Baru saja</span>
                        </div>
                        <div class="fw-semibold" style="font-size: 0.95rem;">Jalan Berlubang Depan Pasar Wage</div>
                        <div class="text-secondary mb-2" style="font-size: 0.82rem;">Jl. Jend. Sudirman, Purwokerto Timur</div>
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <span class="badge badge-diproses">Diproses</span>
                            <span class="text-muted" style="font-size: 0.78rem;">Tim PUPR terjadwal survei</span>
                        </div>
                    </div>

                    <div class="p-3" style="background: #F0FDF4; border-radius: var(--radius-input); border: 1px solid #DCFCE7;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined" style="color: var(--success-color); font-size: 22px;">check_circle</span>
                            <span class="fw-medium text-success" style="font-size: 0.88rem;">Terselesaikan: 3 aduan hari ini</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. Dynamic Stats Counter Section -->
<section class="stats-section">
    <div class="container-custom">
        <div class="row g-3">
            <!-- Total Laporan -->
            <div class="col-sm-6 col-lg-3">
                <a href="<?= base_url('user/complaint_list.php') ?>" class="stat-card" style="text-decoration: none; color: inherit;" title="Lihat semua pengaduan">
                    <div class="stat-icon-wrap" style="background-color: var(--primary-light); color: var(--primary-teal);">
                        <span class="material-symbols-outlined" style="font-size: 28px;">assignment</span>
                    </div>
                    <div>
                        <div class="stat-number"><?= number_format($stats['total'], 0, ',', '.') ?></div>
                        <div class="stat-label">Total Laporan</div>
                    </div>
                </a>
            </div>

            <!-- Sedang Diproses -->
            <div class="col-sm-6 col-lg-3">
                <a href="<?= base_url('user/complaint_list.php?status=Diproses') ?>" class="stat-card" style="text-decoration: none; color: inherit;" title="Lihat pengaduan yang sedang diproses">
                    <div class="stat-icon-wrap" style="background-color: #FEF3C7; color: var(--accent-amber);">
                        <span class="material-symbols-outlined" style="font-size: 28px;">pending_actions</span>
                    </div>
                    <div>
                        <div class="stat-number"><?= number_format($stats['diproses'], 0, ',', '.') ?></div>
                        <div class="stat-label">Sedang Diproses</div>
                    </div>
                </a>
            </div>

            <!-- Telah Selesai -->
            <div class="col-sm-6 col-lg-3">
                <a href="<?= base_url('user/complaint_list.php?status=Selesai') ?>" class="stat-card" style="text-decoration: none; color: inherit;" title="Lihat pengaduan yang telah selesai">
                    <div class="stat-icon-wrap" style="background-color: #DCFCE7; color: var(--success-color);">
                        <span class="material-symbols-outlined" style="font-size: 28px;">task_alt</span>
                    </div>
                    <div>
                        <div class="stat-number"><?= number_format($stats['selesai'], 0, ',', '.') ?></div>
                        <div class="stat-label">Telah Selesai</div>
                    </div>
                </a>
            </div>

            <!-- Warga Aktif -->
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrap" style="background-color: #DBEAFE; color: #2563EB;">
                        <span class="material-symbols-outlined" style="font-size: 28px;">groups</span>
                    </div>
                    <div>
                        <div class="stat-number"><?= number_format($stats['warga'], 0, ',', '.') ?></div>
                        <div class="stat-label">Warga Aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. Bagaimana Cara Kerjanya (How It Works) -->
<section class="how-it-works-section" id="cara-kerja">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-tag">Alur Pengaduan</span>
            <h2 class="section-title">Bagaimana Cara Kerjanya?</h2>
            <p class="section-desc">Hanya dalam tiga langkah mudah, suara Anda akan langsung didengar dan ditindaklanjuti oleh instansi berwenang.</p>
        </div>

        <div class="row g-4">
            <!-- Step 1 -->
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">01</div>
                    <div class="step-icon-box">
                        <span class="material-symbols-outlined">edit_document</span>
                    </div>
                    <h3 class="step-title">1. Buat Laporan</h3>
                    <p class="step-desc">
                        Tuliskan permasalahan kota secara jelas, sertakan foto bukti kondisi lapangan, dan tandai titik lokasi kejadian pada peta.
                    </p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">02</div>
                    <div class="step-icon-box" style="background: #FEF3C7; color: var(--accent-amber);">
                        <span class="material-symbols-outlined">verified_user</span>
                    </div>
                    <h3 class="step-title">2. Verifikasi Admin</h3>
                    <p class="step-desc">
                        Petugas verifikator memvalidasi kelengkapan laporan Anda secara objektif dan meneruskannya ke dinas atau unit teknis terkait.
                    </p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">03</div>
                    <div class="step-icon-box" style="background: #DCFCE7; color: var(--success-color);">
                        <span class="material-symbols-outlined">published_with_changes</span>
                    </div>
                    <h3 class="step-title">3. Tindak Lanjut</h3>
                    <p class="step-desc">
                        Dinas teknis bergerak ke lapangan untuk menyelesaikan aduan. Anda dan warga lain dapat memantau progres hingga status selesai.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 4. Kategori Section -->
<section class="categories-section" id="kategori">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-tag">Fokus Masalah</span>
            <h2 class="section-title">Kategori Pengaduan</h2>
            <p class="section-desc">Pilih kategori yang sesuai untuk memastikan aduan Anda diterima oleh dinas yang tepat.</p>
        </div>

        <div class="row g-3 justify-content-center">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?= base_url('user/complaint_list.php?category=' . (int)$cat['id']) ?>" class="category-card" title="Lihat semua aduan kategori <?= e($cat['name']) ?>">
                            <div class="category-icon-wrap">
                                <span class="material-symbols-outlined"><?= e($cat['icon']) ?></span>
                            </div>
                            <div class="category-name"><?= e($cat['name']) ?></div>
                            <div class="category-count"><?= (int)$cat['total_complaints'] ?> Laporan</div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted">Belum ada kategori yang tersedia.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- 5. Pengaduan Terbaru Section -->
<section class="complaints-section" id="pengaduan-terbaru">
    <div class="container-custom">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <span class="section-tag">Aktivitas Terkini</span>
                <h2 class="section-title mb-1">Pengaduan Terbaru</h2>
                <p class="section-desc mb-0">Pantau aspirasi dan laporan permasalahan terkini dari sesama warga Purwokerto.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= base_url('user/complaint_create.php') ?>" class="btn btn-teal">
                    <span class="material-symbols-outlined" style="font-size: 20px;">add_circle</span>
                    <span>Buat Pengaduan Baru</span>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php if (!empty($latestComplaints)): ?>
                <?php foreach ($latestComplaints as $cmp): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="complaint-card">
                            <!-- Image / Preview Header -->
                            <div class="complaint-img-wrap">
                                <?php if (!empty($cmp['photo'])): ?>
                                    <img src="<?= base_url('uploads/' . e($cmp['photo'])) ?>" alt="<?= e($cmp['title']) ?>" class="complaint-img">
                                <?php else: ?>
                                    <div class="complaint-placeholder-art">
                                        <span class="material-symbols-outlined"><?= e($cmp['category_icon']) ?></span>
                                        <div style="font-size: 0.75rem; font-weight: 500;"><?= e($cmp['category_name']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="complaint-badges-bar">
                                    <span class="badge-cat">
                                        <span class="material-symbols-outlined" style="font-size: 14px;"><?= e($cmp['category_icon']) ?></span>
                                        <span><?= e($cmp['category_name']) ?></span>
                                    </span>
                                    <span class="badge <?= statusBadgeClass($cmp['status']) ?>">
                                        <?= e($cmp['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Body Content -->
                            <div class="complaint-body">
                                <h3 class="complaint-title">
                                    <a href="<?= base_url('user/complaint_detail.php?id=' . (int)$cmp['id']) ?>" class="text-decoration-none" style="color: inherit;">
                                        <?= e($cmp['title']) ?>
                                    </a>
                                </h3>

                                <p class="complaint-desc">
                                    <?= e(mb_strimwidth($cmp['description'], 0, 110, '...')) ?>
                                </p>

                                <div class="complaint-location">
                                    <span class="material-symbols-outlined" style="font-size: 16px; color: var(--primary-teal);">location_on</span>
                                    <span class="text-truncate"><?= e($cmp['address']) ?></span>
                                </div>

                                <div class="complaint-footer">
                                    <div>
                                        <a href="<?= base_url('user/complaint_detail.php?ticket=' . urlencode($cmp['ticket_code'])) ?>" class="text-decoration-none">
                                            <span class="ticket-pill"><?= e($cmp['ticket_code']) ?></span>
                                        </a>
                                        <span class="ms-1">&bull; <?= timeAgo($cmp['created_at']) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span title="Dukungan Warga" class="d-flex align-items-center gap-1">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">thumb_up</span>
                                            <span><?= (int)$cmp['upvotes_count'] ?></span>
                                        </span>
                                        <span title="Komentar" class="d-flex align-items-center gap-1">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">comment</span>
                                            <span><?= (int)$cmp['comments_count'] ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">
                    <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.5;">inbox</span>
                    <p class="mt-2">Belum ada pengaduan yang diajukan.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5">
            <a href="<?= base_url('user/complaint_list.php') ?>" class="btn btn-outline-teal px-4 py-2">
                <span class="material-symbols-outlined" style="font-size: 20px;">explore</span>
                <span>Jelajahi Semua Pengaduan (<?= number_format($stats['total'], 0, ',', '.') ?>)</span>
            </a>
        </div>
    </div>
</section>

<!-- 6. SDG 11 Banner Section -->
<section class="sdg-section" id="sdg11">
    <div class="container-custom position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="sdg-badge-label">
                    <span class="material-symbols-outlined" style="font-size: 18px;">nature_people</span>
                    <span>TUJUAN PEMBANGUNAN BERKELANJUTAN (SDGs)</span>
                </div>
                <h2 class="sdg-title">Mendukung Penuh SDG 11: Kota dan Komunitas yang Berkelanjutan</h2>
                <p class="sdg-desc">
                    LaporKota berkomitmen mendukung pencapaian Tujuan Pembangunan Berkelanjutan (SDG 11) di Purwokerto dan Kabupaten Banyumas. Melalui partisipasi aktif seluruh elemen masyarakat, kita menciptakan kota yang inklusif, aman, berketahanan, dan berwawasan lingkungan.
                </p>

                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= base_url('user/complaint_create.php') ?>" class="btn btn-amber btn-lg">
                        <span class="material-symbols-outlined">volunteer_activism</span>
                        <span>Ambil Bagian Sekarang</span>
                    </a>
                    <a href="#kategori" class="btn btn-outline-light btn-lg d-inline-flex align-items-center gap-2">
                        <span class="material-symbols-outlined">search</span>
                        <span>Eksplorasi Isu Kota</span>
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="sdg-pillar-card">
                            <div class="sdg-pillar-icon">
                                <span class="material-symbols-outlined">groups</span>
                            </div>
                            <div class="sdg-pillar-title">Target 11.3: Urbanisasi Inklusif &amp; Partisipatif</div>
                            <p class="sdg-desc mb-0 sdg-pillar-desc">Memberdayakan warga Banyumas agar terlibat langsung dalam pengambilan keputusan dan tata kelola lingkungan.</p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="sdg-pillar-card">
                            <div class="sdg-pillar-icon">
                                <span class="material-symbols-outlined">delete_sweep</span>
                            </div>
                            <div class="sdg-pillar-title">Target 11.6: Pengurangan Dampak Lingkungan &amp; Sampah</div>
                            <p class="sdg-desc mb-0 sdg-pillar-desc">Mempercepat respon terhadap titik sampah liar dan penyumbatan drainase penyebab genangan air kota.</p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="sdg-pillar-card">
                            <div class="sdg-pillar-icon">
                                <span class="material-symbols-outlined">traffic</span>
                            </div>
                            <div class="sdg-pillar-title">Target 11.2 &amp; 11.7: Ruang Publik &amp; Transportasi Aman</div>
                            <p class="sdg-desc mb-0 sdg-pillar-desc">Memastikan perbaikan jalan berlubang, trotoar, dan lampu penerangan jalan demi kenyamanan bersama.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

?>
<!-- Footer -->
<footer class="footer-custom">
    <div class="container-custom">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="brand-icon" style="width: 36px; height: 36px; border-radius: 8px;">
                        <span class="material-symbols-outlined" style="font-size: 20px;">campaign</span>
                    </div>
                    <div class="brand-name" style="color: #FFFFFF; font-size: 1.25rem;">Lapor<span style="color: var(--accent-amber);">Kota</span></div>
                </div>
                <p style="color: #94A3B8; font-size: 0.9rem; line-height: 1.6;">
                    Platform layanan pengaduan dan aspirasi warga Purwokerto, Kabupaten Banyumas. Transparan, akuntabel, dan terintegrasi untuk mewujudkan kota yang ramah dan berkelanjutan.
                </p>
            </div>

            <div class="col-6 col-lg-2 offset-lg-1">
                <h5>Navigasi</h5>
                <ul class="footer-links">
                    <li><a href="<?= base_url('index.php') ?>">Beranda</a></li>
                    <li><a href="#cara-kerja">Cara Kerja</a></li>
                    <li><a href="#kategori">Kategori Isu</a></li>
                    <li><a href="#pengaduan-terbaru">Pengaduan Terbaru</a></li>
                    <li><a href="#sdg11">SDG 11 Banyumas</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h5>Akses Akun</h5>
                <ul class="footer-links">
                    <?php if ($currentUser): ?>
                        <li><a href="<?= base_url('user/complaint_create.php') ?>">Buat Laporan Baru</a></li>
                        <li><a href="<?= base_url('auth/logout.php') ?>">Keluar</a></li>
                    <?php else: ?>
                        <li><a href="<?= base_url('auth/login.php') ?>">Masuk ke Akun</a></li>
                        <li><a href="<?= base_url('auth/register.php') ?>">Daftar Akun Baru</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-3">
                <h5>Pemerintah Kota</h5>
                <p style="color: #94A3B8; font-size: 0.9rem; margin-bottom: 8px;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">location_on</span>
                    Purwokerto, Kabupaten Banyumas, Jawa Tengah
                </p>
                <p style="color: #94A3B8; font-size: 0.9rem;">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">mail</span>
                    kontak@laporkota.id
                </p>
            </div>
        </div>

        <div class="footer-bottom">
            <div>
                &copy; <?= date('Y') ?> LaporKota Purwokerto. Hak cipta dilindungi undang-undang.
            </div>
            <div>
                Mendukung Agenda Pembangunan Berkelanjutan (SDG 11) Banyumas
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

