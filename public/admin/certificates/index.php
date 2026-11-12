
<?php
require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();
$csrfToken = Csrf::token();
$adminId = (int) ($_SESSION['user']['id'] ?? 0);
$errors = [];
$successMessage = isset($_GET['reviewed']) ? 'Pemeriksaan sertifikat berhasil disimpan.' : '';

// Proses keputusan Admin.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $certificateId = filter_input(INPUT_POST, 'certificate_id', FILTER_VALIDATE_INT);
    $reviewStatus = $_POST['review_status'] ?? '';
    $reviewNotes = trim($_POST['review_notes'] ?? '');

    if (!$certificateId || $certificateId <= 0) {
        $errors[] = 'ID sertifikat tidak valid.';
    }
    if (!in_array($reviewStatus, ['verified', 'rejected'], true)) {
        $errors[] = 'Status pemeriksaan tidak valid.';
    }
    if ($reviewStatus === 'rejected' && $reviewNotes === '') {
        $errors[] = 'Catatan wajib diisi apabila sertifikat ditolak.';
    }
    if (mb_strlen($reviewNotes) > 5000) {
        $errors[] = 'Catatan pemeriksaan maksimal 5.000 karakter.';
    }

    if (!$errors) {
        $check = $db->prepare("SELECT id, verification_status FROM certificates WHERE id = :id LIMIT 1");
        $check->execute(['id' => $certificateId]);
        $certificate = $check->fetch();

        if (!$certificate) {
            $errors[] = 'Sertifikat tidak ditemukan.';
        } elseif ($certificate['verification_status'] !== 'pending') {
            $errors[] = 'Sertifikat ini sudah pernah diperiksa dan tidak dapat diproses ulang dari halaman ini.';
        }
    }

    if (!$errors) {
        try {
            $db->beginTransaction();

            $update = $db->prepare("
                UPDATE certificates
                SET verification_status = :verification_status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND verification_status = 'pending'
            ");
            $update->execute([
                'verification_status' => $reviewStatus,
                'id' => $certificateId
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Status sertifikat berubah atau sudah diperiksa Admin lain.');
            }

            $insertReview = $db->prepare("
                INSERT INTO certificate_reviews
                    (certificate_id, reviewer_id, review_status, review_notes)
                VALUES
                    (:certificate_id, :reviewer_id, :review_status, :review_notes)
            ");
            $insertReview->execute([
                'certificate_id' => $certificateId,
                'reviewer_id' => $adminId,
                'review_status' => $reviewStatus,
                'review_notes' => $reviewNotes !== '' ? $reviewNotes : null
            ]);

            $db->commit();

            header('Location: ' . BASE_URL . '/admin/certificates/index.php?reviewed=1');
            exit;

        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log($exception->getMessage());
            $errors[] = 'Pemeriksaan sertifikat gagal disimpan. Silakan coba kembali.';
        }
    }
}

// Pencarian dan filter.
$keyword = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'pending';

if (!in_array($status, ['pending', 'verified', 'rejected', 'all'], true)) {
    $status = 'pending';
}

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(
        c.certificate_name LIKE :cert_name
        OR c.issuing_organization LIKE :organization
        OR s.full_name LIKE :student_name
        OR s.nis LIKE :nis
    )";

    $search = '%' . $keyword . '%';

    $params = [
        'cert_name' => $search,
        'organization' => $search,
        'student_name' => $search,
        'nis' => $search
    ];
}

if ($status !== 'all') {
    $where[] = 'c.verification_status = :status';
    $params['status'] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Statistik keseluruhan.
$statsRow = $db->query("
    SELECT COUNT(*) AS total,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) AS verified,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) AS rejected
    FROM certificates
")->fetch() ?: [];

$stats = [
    'total' => (int) ($statsRow['total'] ?? 0),
    'pending' => (int) ($statsRow['pending'] ?? 0),
    'verified' => (int) ($statsRow['verified'] ?? 0),
    'rejected' => (int) ($statsRow['rejected'] ?? 0)
];

// Daftar sertifikat dan catatan review terakhir.
$sql = "
    SELECT c.id, c.certificate_name, c.issuing_organization,
        c.certificate_number, c.issue_date, c.expiry_date,
        c.certificate_file, c.verification_status, c.created_at,
        s.full_name, s.nis,
        (SELECT cr.review_notes FROM certificate_reviews cr
         WHERE cr.certificate_id = c.id
         ORDER BY cr.reviewed_at DESC, cr.id DESC LIMIT 1) AS review_notes,
        (SELECT cr.reviewed_at FROM certificate_reviews cr
         WHERE cr.certificate_id = c.id
         ORDER BY cr.reviewed_at DESC, cr.id DESC LIMIT 1) AS reviewed_at
    FROM certificates c
    INNER JOIN students s ON s.id = c.student_id
    $whereSql
    ORDER BY CASE c.verification_status
        WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END,
        c.created_at DESC, c.id DESC
";

$listStmt = $db->prepare($sql);
$listStmt->execute($params);
$certificates = $listStmt->fetchAll();

function e($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function certDate($value): string {
    if (!$value) return '-';
    $time = strtotime($value);
    return $time !== false ? date('d-m-Y', $time) : '-';
}

function certStatusLabel($status): string {
    $labels = [
        'pending' => 'Menunggu Verifikasi',
        'verified' => 'Terverifikasi',
        'rejected' => 'Ditolak'
    ];

    return $labels[$status] ?? 'Tidak Diketahui';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verifikasi Sertifikat | SIPORSI Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:#102e59;
            --blue:#1769aa;
            --gold:#f4b942;
            --background:#f4f7fc;
            --text:#1c2e45;
            --muted:#718096;
            --border:#e7edf5;
        }

        * {
            box-sizing:border-box;
        }

        body {
            margin:0;
            background:var(--background);
            color:var(--text);
            font-family:'Inter',sans-serif;
        }

        /* SIDEBAR ADMIN */

        .admin-sidebar {
            position:fixed;
            top:0;
            left:0;
            bottom:0;
            z-index:1030;
            width:255px;
            padding:25px 18px;
            overflow-y:auto;
            background:var(--navy);
            color:white;
        }

        .sidebar-brand {
            display:flex;
            align-items:center;
            gap:11px;
            padding:5px 10px 30px;
            color:white;
            text-decoration:none;
        }

        .sidebar-logo {
            display:flex;
            align-items:center;
            justify-content:center;
            width:40px;
            height:40px;
            border-radius:11px;
            background:var(--gold);
            color:var(--navy);
            font-size:19px;
            font-weight:800;
        }

        .sidebar-brand strong {
            display:block;
            font-size:18px;
            letter-spacing:1px;
        }

        .sidebar-brand small {
            color:rgba(255,255,255,.6);
            font-size:10px;
        }

        .sidebar-label {
            margin:17px 10px 10px;
            color:rgba(255,255,255,.45);
            font-size:10px;
            font-weight:700;
            letter-spacing:1.5px;
            text-transform:uppercase;
        }

        .sidebar-menu {
            display:flex;
            align-items:center;
            gap:12px;
            margin-bottom:5px;
            padding:12px 13px;
            border-radius:9px;
            color:rgba(255,255,255,.75);
            font-size:12px;
            font-weight:500;
            text-decoration:none;
            transition:.2s;
        }

        .sidebar-menu:hover {
            background:rgba(255,255,255,.1);
            color:white;
        }

        .sidebar-menu.active {
            background:var(--blue);
            color:white;
            font-weight:700;
        }

        .sidebar-icon {
            width:22px;
            font-size:17px;
            text-align:center;
        }

        .sidebar-bottom {
            margin-top:35px;
            padding:15px 10px;
            border-top:1px solid rgba(255,255,255,.13);
        }

        .sidebar-bottom small {
            color:rgba(255,255,255,.55);
        }

        /* MAIN */

        .admin-main {
            min-height:100vh;
            margin-left:255px;
        }

        .admin-topbar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            min-height:75px;
            padding:15px 35px;
            border-bottom:1px solid var(--border);
            background:white;
        }

        .topbar-title {
            color:var(--navy);
            font-size:14px;
            font-weight:700;
        }

        .admin-content {
            padding:35px;
        }

        .page-title {
            margin:0 0 7px;
            color:var(--navy);
            font-size:25px;
            font-weight:800;
        }

        .page-description {
            margin:0;
            color:var(--muted);
            font-size:12px;
            line-height:1.8;
        }

        /* STATISTICS */

        .summary-card {
            height:100%;
            padding:19px;
            border:1px solid var(--border);
            border-radius:13px;
            background:white;
            box-shadow:0 5px 18px rgba(16,46,89,.035);
        }

        .summary-label {
            color:var(--muted);
            font-size:10px;
            font-weight:600;
        }

        .summary-value {
            margin-top:6px;
            color:var(--navy);
            font-size:25px;
            font-weight:800;
        }

        /* LIST AND FILTER */

        .content-panel {
            padding:24px;
            border:1px solid var(--border);
            border-radius:14px;
            background:white;
            box-shadow:0 5px 18px rgba(16,46,89,.035);
        }

        .panel-heading {
            margin-bottom:5px;
            color:var(--navy);
            font-size:15px;
            font-weight:800;
        }

        .panel-description {
            color:var(--muted);
            font-size:10px;
        }

        .search-input,
        .filter-select {
            min-height:42px;
            border:1px solid var(--border);
            border-radius:8px;
            background:#f9fbfe;
            font-size:11px;
        }

        .btn-search {
            min-height:42px;
            border:0;
            border-radius:8px;
            background:var(--blue);
            color:white;
            font-size:11px;
            font-weight:700;
        }

        .btn-search:hover {
            background:var(--navy);
            color:white;
        }

        /* CERTIFICATE CARD */

        .certificate-item {
            padding:20px;
            border:1px solid var(--border);
            border-radius:12px;
            background:white;
        }

        .certificate-name {
            margin:0 0 6px;
            color:var(--navy);
            font-size:14px;
            font-weight:800;
            line-height:1.55;
        }

        .student-meta {
            color:var(--muted);
            font-size:10px;
            line-height:1.8;
        }

        .status-badge {
            display:inline-block;
            padding:6px 9px;
            border-radius:7px;
            font-size:9px;
            font-weight:700;
        }

        .status-pending {
            background:#fff4d9;
            color:#956b0a;
        }

        .status-verified {
            background:#e5f6ed;
            color:#198754;
        }

        .status-rejected {
            background:#fdebed;
            color:#c23c49;
        }

        .detail-grid {
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:9px 16px;
            margin-top:15px;
            padding:13px;
            border:1px solid var(--border);
            border-radius:9px;
            background:#f9fbfe;
        }

        .detail-label {
            display:block;
            margin-bottom:3px;
            color:var(--muted);
            font-size:9px;
        }

        .detail-value {
            display:block;
            color:var(--navy);
            font-size:10px;
            font-weight:600;
            overflow-wrap:anywhere;
        }

        .file-link {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:35px;
            margin-top:13px;
            padding:8px 12px;
            border:1px solid var(--blue);
            border-radius:7px;
            color:var(--blue);
            font-size:10px;
            font-weight:700;
            text-decoration:none;
        }

        .file-link:hover {
            background:var(--blue);
            color:white;
        }

        /* REVIEW FORM */

        .review-box {
            margin-top:16px;
            padding:15px;
            border:1px solid #d9e6f4;
            border-radius:10px;
            background:#f7faff;
        }

        .review-title {
            margin-bottom:10px;
            color:var(--navy);
            font-size:11px;
            font-weight:800;
        }

        .review-notes {
            min-height:72px;
            border:1px solid var(--border);
            border-radius:7px;
            font-size:10px;
        }

        .btn-verify,
        .btn-reject {
            min-height:36px;
            padding:8px 12px;
            border:0;
            border-radius:7px;
            color:white;
            font-size:10px;
            font-weight:700;
        }

        .btn-verify {
            background:#198754;
        }

        .btn-verify:hover {
            background:#146c43;
            color:white;
        }

        .btn-reject {
            background:#c23c49;
        }

        .btn-reject:hover {
            background:#a52d39;
            color:white;
        }

        .review-note {
            margin-top:12px;
            padding:11px 12px;
            border-radius:8px;
            background:#fff5f5;
            color:#8a4650;
            font-size:10px;
            line-height:1.7;
            white-space:pre-wrap;
        }

        .empty-state {
            padding:40px 15px;
            color:var(--muted);
            text-align:center;
            font-size:11px;
        }

        .page-footer {
            margin-top:25px;
            color:#8996a8;
            font-size:10px;
            text-align:center;
        }

        .mobile-toggle {
            display:none;
        }

        /* RESPONSIVE */

        @media (max-width:1200px) {
            .admin-content {
                padding:25px 20px;
            }
        }

        @media (max-width:991px) {
            .admin-sidebar {
                transform:translateX(-100%);
                transition:transform .25s ease;
            }

            .admin-sidebar.show {
                transform:translateX(0);
            }

            .admin-main {
                margin-left:0;
            }

            .mobile-toggle {
                display:inline-block;
            }

            .admin-topbar {
                padding:15px 20px;
            }
        }

        @media (max-width:576px) {
            .admin-content {
                padding:20px 14px;
            }

            .admin-topbar {
                padding:13px 14px;
            }

            .page-title {
                font-size:21px;
            }

            .content-panel {
                padding:15px;
            }

            .detail-grid {
                grid-template-columns:1fr;
            }

            .summary-value {
                font-size:22px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR ADMIN -->

<aside class="admin-sidebar" id="adminSidebar">

    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-brand">
        <span class="sidebar-logo">S</span>
        <span>
            <strong>SIPORSI</strong>
            <small>ADMINISTRATOR PANEL</small>
        </span>
    </a>

    <div class="sidebar-label">Menu Utama</div>

    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-menu">
        <span class="sidebar-icon">⌂</span>Dashboard
    </a>

    <a href="<?= BASE_URL ?>/admin/students/index.php" class="sidebar-menu">
        <span class="sidebar-icon">♙</span>Data Siswa
    </a>

    <a href="<?= BASE_URL ?>/admin/majors/index.php" class="sidebar-menu">
        <span class="sidebar-icon">▦</span>Data Jurusan
    </a>

    <a href="<?= BASE_URL ?>/admin/industries/index.php" class="sidebar-menu">
        <span class="sidebar-icon">▣</span>Data Industri
    </a>

    <a href="<?= BASE_URL ?>/admin/skills/index.php" class="sidebar-menu">
        <span class="sidebar-icon">✦</span>Data Keterampilan
    </a>

    <a href="<?= BASE_URL ?>/admin/projects/index.php" class="sidebar-menu">
        <span class="sidebar-icon">▤</span>Manajemen Proyek
    </a>

    <a href="<?= BASE_URL ?>/admin/certificates/index.php" class="sidebar-menu active">
        <span class="sidebar-icon">▧</span>Verifikasi Sertifikat
    </a>

    <div class="sidebar-label">Lainnya</div>

    <a href="<?= BASE_URL ?>/" class="sidebar-menu">
        <span class="sidebar-icon">⌂</span>Halaman Publik
    </a>

    <div class="sidebar-bottom">
        <small>Area Pengelolaan</small>
        <div class="fw-bold mt-1">Administrator</div>
        <small>SIPORSI Sekolah</small>
    </div>

</aside>


<div class="admin-main">

    <!-- TOPBAR -->

    <header class="admin-topbar">

        <div class="d-flex align-items-center gap-3">

            <button type="button"
                    class="btn btn-outline-secondary btn-sm mobile-toggle"
                    onclick="toggleSidebar()">☰</button>

            <span class="topbar-title">
                SIPORSI / Verifikasi Sertifikat
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/dashboard.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Dashboard
        </a>

    </header>


    <!-- CONTENT -->

    <main class="admin-content">

        <div class="mb-4">
            <h1 class="page-title">Verifikasi Sertifikat Siswa</h1>
            <p class="page-description">
                Periksa dokumen sertifikat siswa dan tentukan hasil verifikasinya.
            </p>
        </div>


        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success border-0 shadow-sm small">
                <?= e($successMessage) ?>
            </div>

        <?php endif; ?>


        <?php if ($errors): ?>

            <div class="alert alert-danger small">

                <strong>Verifikasi belum berhasil:</strong>

                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>


        <!-- STATISTIK -->

        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">
                <div class="summary-card">
                    <div class="summary-label">Total Sertifikat</div>
                    <div class="summary-value">
                        <?= number_format($stats['total'],0,',','.') ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="summary-card">
                    <div class="summary-label">Menunggu Verifikasi</div>
                    <div class="summary-value">
                        <?= number_format($stats['pending'],0,',','.') ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="summary-card">
                    <div class="summary-label">Terverifikasi</div>
                    <div class="summary-value">
                        <?= number_format($stats['verified'],0,',','.') ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="summary-card">
                    <div class="summary-label">Ditolak</div>
                    <div class="summary-value">
                        <?= number_format($stats['rejected'],0,',','.') ?>
                    </div>
                </div>
            </div>

        </div>


        <!-- DAFTAR SERTIFIKAT -->

        <section class="content-panel">

            <div class="mb-3">

                <h5 class="panel-heading">
                    Daftar Sertifikat
                </h5>

                <p class="panel-description">
                    Buka file sertifikat sebelum memberikan keputusan.
                </p>

            </div>


            <!-- PENCARIAN -->

            <form method="GET" class="row g-2 mb-4">

                <div class="col-lg-7 col-md-7">

                    <input type="text"
                           name="search"
                           class="form-control search-input"
                           placeholder="Cari nama siswa, sertifikat, penerbit, atau NIS..."
                           value="<?= e($keyword) ?>">

                </div>

                <div class="col-lg-3 col-md-3">

                    <select name="status" class="form-select filter-select">

                        <option value="pending"
                            <?= $status === 'pending' ? 'selected' : '' ?>>
                            Menunggu Verifikasi
                        </option>

                        <option value="verified"
                            <?= $status === 'verified' ? 'selected' : '' ?>>
                            Terverifikasi
                        </option>

                        <option value="rejected"
                            <?= $status === 'rejected' ? 'selected' : '' ?>>
                            Ditolak
                        </option>

                        <option value="all"
                            <?= $status === 'all' ? 'selected' : '' ?>>
                            Semua Status
                        </option>

                    </select>

                </div>

                <div class="col-lg-2 col-md-2 d-grid">

                    <button type="submit" class="btn btn-search">
                        Tampilkan
                    </button>

                </div>

            </form>


            <!-- HASIL DAFTAR -->

            <?php if ($certificates): ?>

                <div class="d-flex flex-column gap-3">

                    <?php foreach ($certificates as $certificate): ?>

                        <article class="certificate-item">

                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">

                                <div>

                                    <h2 class="certificate-name">
                                        <?= e($certificate['certificate_name']) ?>
                                    </h2>

                                    <div class="student-meta">

                                        Pemilik:
                                        <strong>
                                            <?= e($certificate['full_name']) ?>
                                        </strong>

                                        <?php if (!empty($certificate['nis'])): ?>
                                            · NIS: <?= e($certificate['nis']) ?>
                                        <?php endif; ?>

                                    </div>

                                </div>


                                <?php

                                $badgeClass = 'status-pending';

                                if ($certificate['verification_status'] === 'verified') {
                                    $badgeClass = 'status-verified';
                                } elseif ($certificate['verification_status'] === 'rejected') {
                                    $badgeClass = 'status-rejected';
                                }

                                ?>

                                <span class="status-badge <?= e($badgeClass) ?>">
                                    <?= e(certStatusLabel($certificate['verification_status'])) ?>
                                </span>

                            </div>


                            <!-- DETAIL SERTIFIKAT -->

                            <div class="detail-grid">

                                <div>
                                    <span class="detail-label">Penerbit</span>
                                    <span class="detail-value">
                                        <?= e($certificate['issuing_organization'] ?: '-') ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="detail-label">Nomor Sertifikat</span>
                                    <span class="detail-value">
                                        <?= e($certificate['certificate_number'] ?: '-') ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="detail-label">Tanggal Terbit</span>
                                    <span class="detail-value">
                                        <?= e(certDate($certificate['issue_date'])) ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="detail-label">Tanggal Kedaluwarsa</span>
                                    <span class="detail-value">
                                        <?= e(certDate($certificate['expiry_date'])) ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="detail-label">Tanggal Unggah</span>
                                    <span class="detail-value">
                                        <?= e(certDate($certificate['created_at'])) ?>
                                    </span>
                                </div>

                                <?php if (!empty($certificate['reviewed_at'])): ?>

                                    <div>
                                        <span class="detail-label">Tanggal Pemeriksaan</span>
                                        <span class="detail-value">
                                            <?= e(certDate($certificate['reviewed_at'])) ?>
                                        </span>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- FILE SERTIFIKAT -->

                            <?php if (!empty($certificate['certificate_file'])): ?>

                                <a href="<?= BASE_URL ?>/certificate-file.php?id=<?= (int) $certificate['id'] ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="file-link">
                                Buka File Sertifikat ↗
                                </a>

                            <?php endif; ?>


                            <!-- FORM PEMERIKSAAN -->

                            <?php if ($certificate['verification_status'] === 'pending'): ?>

                                <div class="review-box">

                                    <div class="review-title">
                                        Keputusan Pemeriksaan Admin
                                    </div>

                                    <form method="POST">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e($csrfToken) ?>">

                                        <input type="hidden"
                                               name="certificate_id"
                                               value="<?= (int) $certificate['id'] ?>">

                                        <label class="form-label small"
                                               for="notes-<?= (int) $certificate['id'] ?>">
                                            Catatan pemeriksaan
                                        </label>

                                        <textarea
                                            id="notes-<?= (int) $certificate['id'] ?>"
                                            name="review_notes"
                                            class="form-control review-notes mb-2"
                                            maxlength="5000"
                                            placeholder="Opsional jika disetujui; wajib jika ditolak."
                                        ></textarea>

                                        <div class="d-flex flex-wrap gap-2">

                                            <button
                                                type="submit"
                                                name="review_status"
                                                value="verified"
                                                class="btn-verify"
                                                onclick="return confirm('Setujui dan verifikasi sertifikat ini?')">

                                                ✓ Verifikasi Sertifikat

                                            </button>


                                            <button
                                                type="submit"
                                                name="review_status"
                                                value="rejected"
                                                class="btn-reject"
                                                onclick="return confirm('Tolak sertifikat ini? Pastikan catatan pemeriksaan sudah diisi.')">

                                                ✕ Tolak Sertifikat

                                            </button>

                                        </div>

                                    </form>

                                </div>


                            <?php elseif (!empty($certificate['review_notes'])): ?>

                                <div class="review-note">

                                    <strong>Catatan pemeriksaan terakhir:</strong>
                                    <br>

                                    <?= e($certificate['review_notes']) ?>

                                </div>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>


            <?php else: ?>

                <div class="empty-state">

                    <div class="fs-2 mb-2">▧</div>

                    <strong>
                        Tidak ada sertifikat pada filter ini.
                    </strong>

                    <p class="mb-0 mt-2">
                        Sertifikat yang dikirim siswa akan muncul di halaman ini.
                    </p>

                </div>

            <?php endif; ?>


            <div class="mt-3 text-secondary" style="font-size:10px;">

                Jumlah sertifikat ditampilkan:
                <strong>
                    <?= number_format(count($certificates),0,',','.') ?>
                </strong>

            </div>

        </section>


        <div class="page-footer">

            SIPORSI — Sistem Informasi Portofolio Digital Siswa
            <br>
            SMK Negeri 4 Banjarmasin

        </div>

    </main>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
}
</script>

</body>
</html>
