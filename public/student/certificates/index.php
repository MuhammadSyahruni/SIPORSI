
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

$db = Database::connection();
$csrfToken = Csrf::token();

$user = $_SESSION['user'];

// Mengambil identitas siswa.
$stmt = $db->prepare("
    SELECT id, full_name
    FROM students
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $user['id']
]);

$student = $stmt->fetch();

if (!$student) {
    exit('Data siswa tidak ditemukan.');
}

$studentId = (int) $student['id'];

// Mengambil seluruh sertifikat milik siswa.
// Catatan pemeriksaan diambil dari review terakhir jika tersedia.
$stmt = $db->prepare("
    SELECT
        c.id,
        c.certificate_name,
        c.issuing_organization,
        c.certificate_number,
        c.issue_date,
        c.expiry_date,
        c.certificate_file,
        c.verification_status,
        c.created_at,

        (
            SELECT cr.review_notes
            FROM certificate_reviews cr
            WHERE cr.certificate_id = c.id
            ORDER BY cr.reviewed_at DESC, cr.id DESC
            LIMIT 1
        ) AS review_notes,

        (
            SELECT cr.reviewed_at
            FROM certificate_reviews cr
            WHERE cr.certificate_id = c.id
            ORDER BY cr.reviewed_at DESC, cr.id DESC
            LIMIT 1
        ) AS reviewed_at

    FROM certificates c

    WHERE c.student_id = :student_id

    ORDER BY c.created_at DESC, c.id DESC
");

$stmt->execute([
    'student_id' => $studentId
]);

$certificates = $stmt->fetchAll();

// Statistik sertifikat.
$stats = [
    'total' => count($certificates),
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0
];

foreach ($certificates as $certificate) {

    $status = $certificate['verification_status'];

    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}

// Format tanggal.
function certificateDate($value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d-m-Y', $timestamp)
        : '-';
}

// Keamanan output.
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Label status.
function certificateStatusLabel($status): string
{
    $labels = [
        'pending' => 'Menunggu Verifikasi',
        'verified' => 'Terverifikasi',
        'rejected' => 'Perlu Perbaikan'
    ];

    return $labels[$status] ?? 'Tidak Diketahui';
}

// Notifikasi.
$successMessage = '';

if (isset($_GET['created'])) {
    $successMessage = 'Sertifikat berhasil ditambahkan dan menunggu verifikasi Admin.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sertifikat Saya | SIPORSI Siswa</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>

        :root {
            --navy: #102e59;
            --blue: #1769aa;
            --gold: #f4b942;
            --background: #f4f7fc;
            --text: #1c2e45;
            --muted: #718096;
            --border: #e7edf5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        /* SIDEBAR */

        .student-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1030;
            width: 255px;
            padding: 25px 18px;
            overflow-y: auto;
            background: var(--navy);
            color: white;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 5px 10px 30px;
            color: white;
            text-decoration: none;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: var(--gold);
            color: var(--navy);
            font-size: 19px;
            font-weight: 800;
        }

        .sidebar-brand strong {
            display: block;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .sidebar-brand small {
            color: rgba(255,255,255,.6);
            font-size: 10px;
        }

        .sidebar-label {
            margin: 17px 10px 10px;
            color: rgba(255,255,255,.45);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 5px;
            padding: 12px 13px;
            border-radius: 9px;
            color: rgba(255,255,255,.75);
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: .2s;
        }

        .sidebar-menu:hover {
            background: rgba(255,255,255,.1);
            color: white;
        }

        .sidebar-menu.active {
            background: var(--blue);
            color: white;
            font-weight: 700;
        }

        .sidebar-icon {
            width: 22px;
            font-size: 17px;
            text-align: center;
        }

        .sidebar-bottom {
            margin-top: 35px;
            padding: 15px 10px;
            border-top: 1px solid rgba(255,255,255,.13);
        }

        .sidebar-bottom small {
            color: rgba(255,255,255,.55);
        }

        /* MAIN */

        .student-main {
            min-height: 100vh;
            margin-left: 255px;
        }

        .student-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 75px;
            padding: 15px 35px;
            border-bottom: 1px solid var(--border);
            background: white;
        }

        .topbar-title {
            color: var(--navy);
            font-size: 14px;
            font-weight: 700;
        }

        .student-content {
            max-width: 1250px;
            margin: 0 auto;
            padding: 35px;
        }

        /* PAGE HEADER */

        .page-title {
            margin-bottom: 7px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .page-description {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* BUTTON ADD */

        .btn-add-certificate {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 17px;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-add-certificate:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin: 24px 0;
        }

        .stat-card {
            padding: 18px 19px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            box-shadow: 0 5px 18px rgba(16,46,89,.035);
        }

        .stat-number {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 23px;
            font-weight: 800;
        }

        .stat-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
        }

        /* CERTIFICATE GRID */

        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .certificate-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 21px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
            transition: .2s;
        }

        .certificate-card:hover {
            border-color: #c5d8ed;
            box-shadow: 0 9px 22px rgba(16,46,89,.07);
            transform: translateY(-2px);
        }

        .certificate-header {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 15px;
        }

        .certificate-icon {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 11px;
            background: #fff4d9;
            color: #956b0a;
            font-size: 20px;
        }

        .certificate-name {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        /* STATUS */

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 9px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 700;
        }

        .status-pending {
            background: #fff4d9;
            color: #956b0a;
        }

        .status-verified {
            background: #e5f6ed;
            color: #198754;
        }

        .status-rejected {
            background: #fdebed;
            color: #c23c49;
        }

        /* DETAILS */

        .certificate-details {
            margin-top: 5px;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f9fbfe;
        }

        .certificate-detail-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .certificate-detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
        }

        .detail-value {
            color: var(--navy);
            font-size: 10px;
            font-weight: 600;
            text-align: right;
            overflow-wrap: anywhere;
        }

        /* FILE */

        .certificate-file-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            margin-top: 15px;
            padding: 8px 13px;
            border: 1px solid var(--blue);
            border-radius: 7px;
            background: white;
            color: var(--blue);
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .certificate-file-link:hover {
            background: var(--blue);
            color: white;
        }
        .certificate-edit-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            margin-top: 10px;
            margin-left: 7px;
            padding: 8px 13px;
            border: 1px solid #c23c49;
            border-radius: 7px;
            background: #fff5f5;
            color: #a72f3b;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .certificate-edit-link:hover {
            background: #c23c49;
            color: white;
        }

        /* REVIEW NOTE */

        .review-note {
            margin-top: 14px;
            padding: 12px 13px;
            border: 1px solid #f1c6ca;
            border-radius: 9px;
            background: #fff5f5;
        }

        .review-note strong {
            display: block;
            margin-bottom: 5px;
            color: #a72f3b;
            font-size: 10px;
        }

        .review-note p {
            margin: 0;
            color: #8a4650;
            font-size: 10px;
            line-height: 1.8;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        /* EMPTY */

        .empty-state {
            padding: 45px 20px;
            border: 1px dashed #cbd7e5;
            border-radius: 11px;
            background: white;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
            line-height: 1.8;
        }

        .empty-state-icon {
            display: block;
            margin-bottom: 10px;
            color: #c4a75c;
            font-size: 35px;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 14px;
        }

        /* NOTICE */

        .info-notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-top: 21px;
            padding: 14px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .notice-icon {
            color: var(--blue);
            font-size: 17px;
        }

        .info-notice strong {
            display: block;
            margin-bottom: 3px;
            color: var(--navy);
            font-size: 10px;
        }

        .info-notice span {
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
        }

        /* FOOTER */

        .page-footer {
            margin-top: 25px;
            color: #8996a8;
            font-size: 10px;
            text-align: center;
        }

        /* RESPONSIVE */

        .mobile-toggle {
            display: none;
        }

        @media (max-width: 1100px) {
            .certificate-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 991px) {

            .student-sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .student-sidebar.show {
                transform: translateX(0);
            }

            .student-main {
                margin-left: 0;
            }

            .mobile-toggle {
                display: inline-block;
            }

            .student-topbar {
                padding: 15px 20px;
            }

            .student-content {
                padding: 25px 20px;
            }

        }

        @media (max-width: 576px) {

            .student-content {
                padding: 20px 14px;
            }

            .page-title {
                font-size: 21px;
            }

            .stats-grid {
                gap: 9px;
            }

            .stat-card {
                padding: 14px;
            }

            .stat-number {
                font-size: 20px;
            }

            .certificate-card {
                padding: 16px;
            }

            .certificate-detail-row {
                gap: 8px;
            }

        }

    </style>

</head>

<body>


<!-- SIDEBAR -->

<aside class="student-sidebar" id="studentSidebar">

    <a href="<?= BASE_URL ?>/student/dashboard.php"
       class="sidebar-brand">

        <span class="sidebar-logo">S</span>

        <span>
            <strong>SIPORSI</strong>
            <small>STUDENT PANEL</small>
        </span>

    </a>


    <div class="sidebar-label">
        Menu Utama
    </div>


    <a href="<?= BASE_URL ?>/student/dashboard.php"
       class="sidebar-menu">

        <span class="sidebar-icon">⌂</span>
        Dashboard

    </a>


    <div class="sidebar-label">
        Portofolio Saya
    </div>


    <a href="<?= BASE_URL ?>/student/skills/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">✦</span>
        Keterampilan

    </a>


    <a href="<?= BASE_URL ?>/student/projects/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▤</span>
        Proyek dan Karya

    </a>


    <a href="<?= BASE_URL ?>/student/experiences/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▣</span>
        Pengalaman PKL

    </a>


    <a href="<?= BASE_URL ?>/student/achievements/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">★</span>
        Prestasi

    </a>


    <a href="<?= BASE_URL ?>/student/certificates/index.php"
       class="sidebar-menu active">

        <span class="sidebar-icon">▧</span>
        Sertifikat

    </a>


    <div class="sidebar-label">
        Lainnya
    </div>


    <a href="<?= BASE_URL ?>/"
       class="sidebar-menu">

        <span class="sidebar-icon">◉</span>
        Halaman Publik

    </a>


    <div class="sidebar-bottom">

        <small>Area Pengguna</small>

        <div class="fw-bold mt-1">
            Siswa SIPORSI
        </div>

        <small>SMK Negeri 4 Banjarmasin</small>

    </div>

</aside>


<!-- MAIN -->

<div class="student-main">


    <!-- TOPBAR -->

    <header class="student-topbar">

        <div class="d-flex align-items-center gap-3">

            <button type="button"
                    class="btn btn-outline-secondary btn-sm mobile-toggle"
                    onclick="toggleSidebar()">

                ☰

            </button>

            <span class="topbar-title">
                SIPORSI / Portofolio Saya / Sertifikat
            </span>

        </div>


        <form action="<?= BASE_URL ?>/logout.php"
              method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($csrfToken) ?>">

            <button type="submit"
                    class="btn btn-sm btn-outline-danger">

                Logout

            </button>

        </form>

    </header>


    <!-- CONTENT -->

    <main class="student-content">


        <!-- PAGE HEADER -->

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

            <div>

                <h1 class="page-title">
                    Sertifikat Saya
                </h1>

                <p class="page-description">
                    Kelola sertifikat dan pantau status verifikasinya.
                </p>

            </div>


            <a href="<?= BASE_URL ?>/student/certificates/create.php"
               class="btn-add-certificate">

                + Tambah Sertifikat

            </a>

        </div>


        <!-- SUCCESS MESSAGE -->

        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success border-0 shadow-sm"
                 style="font-size:11px;">

                <?= e($successMessage) ?>

            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <div class="stats-grid">

            <div class="stat-card">

                <span class="stat-number">
                    <?= number_format($stats['total'], 0, ',', '.') ?>
                </span>

                <span class="stat-label">
                    Total Sertifikat
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= number_format($stats['pending'], 0, ',', '.') ?>
                </span>

                <span class="stat-label">
                    Menunggu Verifikasi
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= number_format($stats['verified'], 0, ',', '.') ?>
                </span>

                <span class="stat-label">
                    Terverifikasi
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= number_format($stats['rejected'], 0, ',', '.') ?>
                </span>

                <span class="stat-label">
                    Perlu Perbaikan
                </span>

            </div>

        </div>


        <!-- CERTIFICATE LIST -->

        <div class="mb-3">

            <h5 class="fw-bold mb-1"
                style="color:var(--navy); font-size:15px;">

                Daftar Sertifikat

            </h5>

            <p class="text-secondary mb-0"
               style="font-size:10px;">

                Seluruh sertifikat yang pernah kamu unggah.

            </p>

        </div>


        <?php if (!empty($certificates)): ?>


            <div class="certificate-grid">


                <?php foreach ($certificates as $certificate): ?>


                    <article class="certificate-card">


                        <!-- HEADER -->

                        <div class="certificate-header">

                            <div class="certificate-icon">
                                ▧
                            </div>

                            <div class="flex-grow-1">

                                <h2 class="certificate-name">
                                    <?= e($certificate['certificate_name']) ?>
                                </h2>


                                <?php

                                $status = $certificate['verification_status'];

                                $statusClass = 'status-pending';

                                if ($status === 'verified') {
                                    $statusClass = 'status-verified';
                                } elseif ($status === 'rejected') {
                                    $statusClass = 'status-rejected';
                                }

                                ?>

                                <span class="status-badge <?= e($statusClass) ?>">

                                    <?= e(certificateStatusLabel($status)) ?>

                                </span>

                            </div>

                        </div>


                        <!-- DETAILS -->

                        <div class="certificate-details">


                            <div class="certificate-detail-row">

                                <span class="detail-label">
                                    Penerbit
                                </span>

                                <span class="detail-value">
                                    <?= e($certificate['issuing_organization'] ?: '-') ?>
                                </span>

                            </div>


                            <div class="certificate-detail-row">

                                <span class="detail-label">
                                    Nomor Sertifikat
                                </span>

                                <span class="detail-value">
                                    <?= e($certificate['certificate_number'] ?: '-') ?>
                                </span>

                            </div>


                            <div class="certificate-detail-row">

                                <span class="detail-label">
                                    Tanggal Terbit
                                </span>

                                <span class="detail-value">
                                    <?= e(certificateDate($certificate['issue_date'])) ?>
                                </span>

                            </div>


                            <div class="certificate-detail-row">

                                <span class="detail-label">
                                    Kedaluwarsa
                                </span>

                                <span class="detail-value">
                                    <?= e(certificateDate($certificate['expiry_date'])) ?>
                                </span>

                            </div>


                            <div class="certificate-detail-row">

                                <span class="detail-label">
                                    Tanggal Unggah
                                </span>

                                <span class="detail-value">
                                    <?= e(certificateDate($certificate['created_at'])) ?>
                                </span>

                            </div>


                            <?php if (!empty($certificate['reviewed_at'])): ?>

                                <div class="certificate-detail-row">

                                    <span class="detail-label">
                                        Tanggal Pemeriksaan
                                    </span>

                                    <span class="detail-value">
                                        <?= e(certificateDate($certificate['reviewed_at'])) ?>
                                    </span>

                                </div>

                            <?php endif; ?>


                        </div>


                        <!-- REVIEW NOTE -->

                        <?php if (
                            $status === 'rejected' &&
                            !empty($certificate['review_notes'])
                        ): ?>

                            <div class="review-note">

                                <strong>Catatan dari Admin</strong>

                                <p><?= e($certificate['review_notes']) ?></p>

                            </div>

                        <?php endif; ?>


                        <!-- CERTIFICATE FILE -->

                        <?php if (!empty($certificate['certificate_file'])): ?>

                            <a href="<?= BASE_URL ?>/certificate-file.php?id=<?= (int) $certificate['id'] ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="certificate-file-link">
                            Lihat File Sertifikat ↗
                            </a>

                        <?php endif; ?>


                        <!-- BUTTON EDIT CERTIFICATE -->

                        <?php if ($status === 'rejected'): ?>

                            <a
                                href="<?= BASE_URL ?>/student/certificates/edit.php?id=<?= (int) $certificate['id'] ?>"
                                class="certificate-edit-link"
                            >
                                Perbaiki Sertifikat ↗
                            </a>

                        <?php endif; ?>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="empty-state">

                <span class="empty-state-icon">▧</span>

                <strong>Belum Ada Sertifikat</strong>

                Kamu belum mengunggah sertifikat apa pun.

                <div class="mt-3">

                    <a href="<?= BASE_URL ?>/student/certificates/create.php"
                       class="btn-add-certificate">

                        Tambahkan Sertifikat Pertama

                    </a>

                </div>

            </div>


        <?php endif; ?>


        <!-- INFORMATION NOTICE -->

        <div class="info-notice">

            <span class="notice-icon">ⓘ</span>

            <div>

                <strong>Informasi Verifikasi Sertifikat</strong>

                <span>
                    Sertifikat yang kamu unggah akan diperiksa oleh Admin.
                    Sertifikat yang telah disetujui dapat ditampilkan pada
                    portofolio publik sesuai kebijakan publikasi SIPORSI.
                </span>

            </div>

        </div>


        <!-- FOOTER -->

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

    document.getElementById('studentSidebar').classList.toggle('show');

}

</script>

</body>
</html>
