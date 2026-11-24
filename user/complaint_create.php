<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$user = currentUser();
$errors = [];
$old = [];
$isEdit = false;
$complaint = null;

// Fetch all categories
$catResult = $conn->query("SELECT id, name, icon FROM categories ORDER BY name ASC");
$categories = [];
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row;
}

// Edit mode: load existing complaint
if (isset($_GET['edit'])) {
    $isEdit = true;
    $editId = (int) $_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM complaints WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();

    if (!$complaint || (int) $complaint['user_id'] !== (int) $user['id'] || $complaint['status'] !== 'Diterima') {
        flash('error', 'Anda tidak dapat mengedit pengaduan ini.');
        redirect(base_url('user/history.php'));
        exit;
    }

    // Pre-populate old values from existing complaint
    $old = [
        'title' => $complaint['title'],
        'category_id' => $complaint['category_id'],
        'description' => $complaint['description'],
        'address' => $complaint['address'],
        'latitude' => $complaint['latitude'],
        'longitude' => $complaint['longitude'],
    ];
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';

    // Preserve old values on error
    $old = [
        'title' => $title,
        'category_id' => $category_id,
        'description' => $description,
        'address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];

    // Validation
    if ($title === '') {
        $errors['title'] = 'Judul pengaduan wajib diisi.';
    } elseif (mb_strlen($title) > 255) {
        $errors['title'] = 'Judul pengaduan maksimal 255 karakter.';
    }

    if ($category_id <= 0) {
        $errors['category_id'] = 'Kategori wajib dipilih.';
    } else {
        $catCheck = $conn->prepare("SELECT id FROM categories WHERE id = ?");
        $catCheck->bind_param("i", $category_id);
        $catCheck->execute();
        if ($catCheck->get_result()->num_rows === 0) {
            $errors['category_id'] = 'Kategori yang dipilih tidak valid.';
        }
        $catCheck->close();
    }

    if ($description === '') {
        $errors['description'] = 'Deskripsi pengaduan wajib diisi.';
    } elseif (mb_strlen($description) < 20) {
        $errors['description'] = 'Deskripsi pengaduan minimal 20 karakter.';
    }

    if ($address === '') {
        $errors['address'] = 'Alamat lokasi wajib diisi.';
    }

    if ($latitude === '') {
        $errors['latitude'] = 'Latitude wajib diisi. Silakan pilih lokasi di peta.';
    } elseif (!is_numeric($latitude)) {
        $errors['latitude'] = 'Latitude harus berupa angka.';
    }

    if ($longitude === '') {
        $errors['longitude'] = 'Longitude wajib diisi. Silakan pilih lokasi di peta.';
    } elseif (!is_numeric($longitude)) {
        $errors['longitude'] = 'Longitude harus berupa angka.';
    }

    // Photo handling
    $photoPath = $isEdit ? $complaint['photo'] : null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploaded = uploadFile($_FILES['photo']);
        if (isset($uploaded['filename'])) {
            $photoPath = $uploaded['filename'];
            // If editing and previous photo exists, delete old file
            if ($isEdit && !empty($complaint['photo'])) {
                $oldFile = __DIR__ . '/../uploads/' . $complaint['photo'];
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        } elseif (isset($uploaded['error'])) {
            $errors['photo'] = $uploaded['error'];
        }
    }

    // If no errors, proceed
    if (empty($errors)) {
        $latVal = (float) $latitude;
        $lngVal = (float) $longitude;
        $now = date('Y-m-d H:i:s');

        if ($isEdit) {
            // Update existing complaint
            $stmt = $conn->prepare("UPDATE complaints SET category_id = ?, title = ?, description = ?, address = ?, latitude = ?, longitude = ?, photo = ?, updated_at = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("isssddssii", $category_id, $title, $description, $address, $latVal, $lngVal, $photoPath, $now, $complaint['id'], $user['id']);
            $stmt->execute();
            $stmt->close();

            flash('success', 'Pengaduan berhasil diperbarui.');
            redirect(base_url('user/complaint_detail.php?id=' . $complaint['id']));
            exit;
        } else {
            // Create new complaint
            $ticketCode = generateTicketCode($conn);
            $status = 'Diterima';

            $stmt = $conn->prepare("INSERT INTO complaints (user_id, category_id, ticket_code, title, description, address, latitude, longitude, photo, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssddssss", $user['id'], $category_id, $ticketCode, $title, $description, $address, $latVal, $lngVal, $photoPath, $status, $now, $now);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            // Insert status log
            $logStmt = $conn->prepare("INSERT INTO complaint_status_logs (complaint_id, old_status, new_status, changed_by, note, created_at) VALUES (?, NULL, ?, ?, ?, ?)");
            $changedBy = 'Sistem';
            $logNote = 'Laporan diterima oleh sistem.';
            $logStmt->bind_param("issss", $newId, $status, $changedBy, $logNote, $now);
            $logStmt->execute();
            $logStmt->close();

            flash('success', 'Pengaduan berhasil dikirim! Kode tiket Anda: ' . $ticketCode);
            redirect(base_url('user/complaint_detail.php?id=' . $newId));
            exit;
        }
    }
}

$pageTitle = $isEdit ? 'Edit Pengaduan' : 'Buat Pengaduan Baru';
include __DIR__ . '/../includes/header.php';

// Determine map center
$mapLat = isset($old['latitude']) && is_numeric($old['latitude']) ? (float) $old['latitude'] : -7.4244;
$mapLng = isset($old['longitude']) && is_numeric($old['longitude']) ? (float) $old['longitude'] : 109.2378;
?>

<style>
    .create-complaint-section {
        padding: 2rem 0 3rem;
        background-color: #F8F9FF;
        min-height: calc(100vh - 200px);
    }

    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
        color: #64748b;
        flex-wrap: wrap;
    }

    .breadcrumb-nav a {
        color: #0F766E;
        text-decoration: none;
        transition: color 0.2s;
    }

    .breadcrumb-nav a:hover {
        color: #0d6560;
        text-decoration: underline;
    }

    .breadcrumb-nav .separator {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .breadcrumb-nav .current {
        color: #334155;
        font-weight: 500;
    }

    .create-form-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
        padding: 2rem;
        max-width: 800px;
        margin: 0 auto;
    }

    .form-header {
        margin-bottom: 2rem;
    }

    .form-header h1 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 0.25rem 0;
    }

    .form-header p {
        color: #64748b;
        font-size: 0.9rem;
        margin: 0;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
        font-size: 0.9rem;
        color: #334155;
        margin-bottom: 0.5rem;
    }

    .form-group label .material-symbols-outlined {
        font-size: 1.1rem;
        color: #0F766E;
    }

    .form-group label .required-star {
        color: #ef4444;
        font-weight: 600;
    }

    .form-group input[type="text"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.9rem;
        color: #1e293b;
        background: #fff;
        transition: border-color 0.2s, box-shadow 0.2s;
        outline: none;
        box-sizing: border-box;
    }

    .form-group input[type="text"]:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #0F766E;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
    }

    .form-group input.is-invalid,
    .form-group select.is-invalid,
    .form-group textarea.is-invalid {
        border-color: #ef4444;
    }

    .form-group input.is-invalid:focus,
    .form-group select.is-invalid:focus,
    .form-group textarea.is-invalid:focus {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    .form-group textarea {
        min-height: 140px;
        resize: vertical;
    }

    .form-group select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M4.646 5.646a.5.5 0 0 1 .708 0L8 8.293l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        padding-right: 2.5rem;
        cursor: pointer;
    }

    .error-message {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        margin-top: 0.35rem;
        font-size: 0.8rem;
        color: #ef4444;
    }

    .error-message .material-symbols-outlined {
        font-size: 0.95rem;
    }

    .field-hint {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-top: 0.3rem;
    }

    .char-counter {
        font-size: 0.8rem;
        color: #94a3b8;
        text-align: right;
        margin-top: 0.25rem;
    }

    #create-map {
        width: 100%;
        height: 350px;
        border-radius: 8px;
        border: 1.5px solid #e2e8f0;
        z-index: 1;
    }

    .map-hint {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.4rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .map-hint .material-symbols-outlined {
        font-size: 0.95rem;
        color: #0F766E;
    }

    .dropzone-upload {
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.2s, background-color 0.2s;
        background: #f8fafc;
    }

    .dropzone-upload:hover {
        border-color: #0F766E;
        background: #f0fdfa;
    }

    .dropzone-upload.dragover {
        border-color: #0F766E;
        background: #e6f7f5;
    }

    .dropzone-upload .material-symbols-outlined {
        font-size: 2.5rem;
        color: #94a3b8;
        display: block;
        margin-bottom: 0.5rem;
    }

    .dropzone-upload .dropzone-text {
        font-size: 0.9rem;
        color: #64748b;
    }

    .dropzone-upload .dropzone-text strong {
        color: #0F766E;
    }

    .dropzone-upload .dropzone-hint {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    .dropzone-upload input[type="file"] {
        display: none;
    }

    #photo-preview {
        margin-top: 1rem;
    }

    .img-preview-area {
        border-radius: 8px;
        max-height: 250px;
        max-width: 100%;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }

    .existing-photo-preview {
        margin-bottom: 1rem;
    }

    .existing-photo-preview img {
        border-radius: 8px;
        max-height: 200px;
        max-width: 100%;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }

    .existing-photo-preview p {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.3rem;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #f1f5f9;
    }

    .btn-submit {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.8rem 2rem;
        background: #0F766E;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, transform 0.1s;
    }

    .btn-submit:hover {
        background: #0d6560;
    }

    .btn-submit:active {
        transform: scale(0.98);
    }

    .btn-submit .material-symbols-outlined {
        font-size: 1.2rem;
    }

    .btn-cancel {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.8rem 1.5rem;
        background: transparent;
        color: #64748b;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.9rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: border-color 0.2s, color 0.2s;
    }

    .btn-cancel:hover {
        border-color: #94a3b8;
        color: #475569;
    }

    @media (max-width: 768px) {
        .create-form-card {
            padding: 1.25rem;
            border-radius: 8px;
        }

        .form-header h1 {
            font-size: 1.25rem;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn-submit,
        .btn-cancel {
            width: 100%;
            justify-content: center;
        }

        #create-map {
            height: 280px;
        }

        .dropzone-upload {
            padding: 1.5rem;
        }
    }

    .btn-location-gps {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        font-size: 0.8rem;
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        color: #0F766E;
        background: #E6F5F3;
        border: 1px solid rgba(15, 118, 110, 0.2);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-location-gps:hover {
        background: #0F766E;
        color: #FFFFFF;
    }

    .btn-location-gps:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-location-gps .material-symbols-outlined {
        font-size: 1rem;
    }
</style>

<section class="create-complaint-section">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <a href="<?= e(base_url()) ?>">Beranda</a>
            <span class="separator material-symbols-outlined">chevron_right</span>
            <span class="current"><?= $isEdit ? 'Edit Pengaduan' : 'Buat Pengaduan' ?></span>
        </nav>

        <div class="create-form-card">
            <div class="form-header">
                <h1><?= $isEdit ? 'Edit Pengaduan' : 'Buat Pengaduan Baru' ?></h1>
                <p><?= $isEdit ? 'Perbarui informasi pengaduan Anda di bawah ini.' : 'Sampaikan keluhan atau laporan Anda mengenai fasilitas dan layanan kota.' ?></p>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="edit_id" value="<?= (int) $complaint['id'] ?>">
                <?php endif; ?>

                <!-- Judul Pengaduan -->
                <div class="form-group">
                    <label for="title">
                        <span class="material-symbols-outlined">title</span>
                        Judul Pengaduan <span class="required-star">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        placeholder="Contoh: Jalan rusak di Jl. Sudirman"
                        value="<?= e($old['title'] ?? '') ?>"
                        class="<?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                        required
                    >
                    <?php if (isset($errors['title'])): ?>
                        <div class="error-message">
                            <span class="material-symbols-outlined">error</span>
                            <?= e($errors['title']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kategori -->
                <div class="form-group">
                    <label for="category_id">
                        <span class="material-symbols-outlined">category</span>
                        Kategori <span class="required-star">*</span>
                    </label>
                    <select
                        id="category_id"
                        name="category_id"
                        class="<?= isset($errors['category_id']) ? 'is-invalid' : '' ?>"
                        required
                    >
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option
                                value="<?= (int) $cat['id'] ?>"
                                <?= (isset($old['category_id']) && (int) $old['category_id'] === (int) $cat['id']) ? 'selected' : '' ?>
                            ><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <div class="error-message">
                            <span class="material-symbols-outlined">error</span>
                            <?= e($errors['category_id']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Deskripsi -->
                <div class="form-group">
                    <label for="description">
                        <span class="material-symbols-outlined">description</span>
                        Deskripsi Pengaduan <span class="required-star">*</span>
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        data-maxlength="2000"
                        placeholder="Jelaskan secara detail mengenai keluhan Anda (minimal 20 karakter)..."
                        class="<?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                        required
                    ><?= e($old['description'] ?? '') ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <div class="error-message">
                            <span class="material-symbols-outlined">error</span>
                            <?= e($errors['description']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="field-hint">Minimal 20 karakter, maksimal 2000 karakter.</div>
                </div>

                <!-- Alamat Lokasi -->
                <div class="form-group">
                    <label for="address">
                        <span class="material-symbols-outlined">location_on</span>
                        Alamat Lokasi <span class="required-star">*</span>
                    </label>
                    <input
                        type="text"
                        id="address"
                        name="address"
                        placeholder="Contoh: Jl. Jenderal Soedirman No. 1, Purwokerto"
                        value="<?= e($old['address'] ?? '') ?>"
                        class="<?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                        required
                    >
                    <div id="address-status" class="field-hint" style="display: none; align-items: center; gap: 0.35rem; margin-top: 0.4rem;"></div>
                    <div class="field-hint">Alamat otomatis terisi saat Anda memilih lokasi di peta, dan tetap dapat disunting manual.</div>
                    <?php if (isset($errors['address'])): ?>
                        <div class="error-message">
                            <span class="material-symbols-outlined">error</span>
                            <?= e($errors['address']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Peta Lokasi -->
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                        <label style="margin-bottom: 0;">
                            <span class="material-symbols-outlined">map</span>
                            Peta Lokasi <span class="required-star">*</span>
                        </label>
                        <button type="button" id="btn-current-location" class="btn-location-gps" title="Gunakan lokasi GPS saya saat ini">
                            <span class="material-symbols-outlined">my_location</span>
                            Gunakan Lokasi Saya
                        </button>
                    </div>
                    <div id="create-map"></div>
                    <div class="map-hint">
                        <span class="material-symbols-outlined">info</span>
                        Klik pada peta atau geser pin untuk menentukan lokasi pengaduan. Form alamat akan terisi otomatis.
                    </div>
                    <?php if (isset($errors['latitude']) || isset($errors['longitude'])): ?>
                        <div class="error-message">
                            <span class="material-symbols-outlined">error</span>
                            <?= e($errors['latitude'] ?? $errors['longitude'] ?? '') ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Hidden lat/lng -->
                <input type="hidden" id="latitude" name="latitude" value="<?= e($old['latitude'] ?? '') ?>">
                <input type="hidden" id="longitude" name="longitude" value="<?= e($old['longitude'] ?? '') ?>">

                <!-- Foto Pengaduan -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">photo_camera</span>
                        Foto Pengaduan <?= $isEdit ? '' : '' ?>
                    </label>

                    <?php if ($isEdit && !empty($complaint['photo'])): ?>
                        <div class="existing-photo-preview">
                            <img src="<?= e(base_url('uploads/' . $complaint['photo'])) ?>" alt="Foto pengaduan saat ini" class="img-preview-area">
                            <p>Foto saat ini. Unggah foto baru untuk mengganti.</p>
                        </div>
                    <?php endif; ?>

                    <div class="dropzone-upload" id="photo-dropzone">
                        <span class="material-symbols-outlined">cloud_upload</span>
                        <div class="dropzone-text">
                            Seret & lepas foto di sini, atau <strong>klik untuk memilih</strong>
                        </div>
                        <div class="dropzone-hint">Format: JPG, PNG, JPEG. Maksimal 2MB.</div>
                        <input type="file" name="photo" id="photo-input" accept="image/jpeg,image/png,image/jpg">
                    </div>
                    <div id="photo-preview"></div>
                    <?php if (isset($errors['photo'])): ?>
                        <div class="error-message">
                            <span class="material-symbols-outlined">error</span>
                            <?= e($errors['photo']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <span class="material-symbols-outlined"><?= $isEdit ? 'save' : 'send' ?></span>
                        <?= $isEdit ? 'Simpan Perubahan' : 'Kirim Pengaduan' ?>
                    </button>
                    <a href="<?= e(base_url($isEdit ? 'user/complaint_detail.php?id=' . (int) $complaint['id'] : 'user/history.php')) ?>" class="btn-cancel">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Leaflet map
    const mapFn = typeof initLeafletMap === 'function' ? initLeafletMap : (window.LaporKota && window.LaporKota.initLeafletMap);
    if (typeof mapFn === 'function') {
        mapFn('create-map', {
            picker: true,
            latInputId: 'latitude',
            lngInputId: 'longitude',
            addressInputId: 'address',
            addressStatusId: 'address-status',
            locateBtnId: 'btn-current-location',
            latitude: <?= json_encode((float) $mapLat) ?>,
            longitude: <?= json_encode((float) $mapLng) ?>,
            zoom: 13
        });
    }

    // Initialize file preview
    const previewFn = typeof initFilePreview === 'function' ? initFilePreview : (window.LaporKota && window.LaporKota.initFilePreview);
    if (typeof previewFn === 'function') {
        previewFn('photo-input', 'photo-preview', {
            dropzoneId: 'photo-dropzone'
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
