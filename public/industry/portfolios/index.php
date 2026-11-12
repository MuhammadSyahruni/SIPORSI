<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';
require_once APP_ROOT . '/app/core/Database.php';

AuthMiddleware::requireRole(['industry']);

$db = Database::connection();
$csrfToken = Csrf::token();
$user = $_SESSION['user'];

// Mengambil informasi perusahaan untuk bagian bawah sidebar.
$industryStmt = $db->prepare("
    SELECT company_name, industry_type
    FROM industries
    WHERE user_id = :user_id
    LIMIT 1
");
$industryStmt->execute(['user_id' => $user['id']]);
$industry = $industryStmt->fetch();

$companyName = $industry['company_name'] ?? 'Mitra Industri';
$industryType = $industry['industry_type'] ?? 'Industri';

// Pencarian portofolio siswa yang dipublikasikan.
$keyword = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        s.id,
        s.full_name,
        s.photo,
        s.short_bio,
        s.public_slug,
        s.class_level,
        m.major_name,
        COUNT(DISTINCT p.id) AS total_projects
    FROM students s
    INNER JOIN majors m ON m.id = s.major_id
    LEFT JOIN projects p
        ON p.student_id = s.id
        AND p.publication_status = 'approved'
    WHERE s.is_public = 1
      AND s.student_status IN ('active', 'graduated')
";

$params = [];

if ($keyword !== '') {
    $sql .= "
        AND (
            s.full_name LIKE :name
            OR m.major_name LIKE :major
            OR s.short_bio LIKE :bio
        )
    ";

    $search = '%' . $keyword . '%';

    $params = [
        'name' => $search,
        'major' => $search,
        'bio' => $search
    ];
}

$sql .= "
    GROUP BY
        s.id,
        s.full_name,
        s.photo,
        s.short_bio,
        s.public_slug,
        s.class_level,
        m.major_name
    ORDER BY s.full_name ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

function escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Jelajahi Portofolio | SIPORSI Industri</title>

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

        /* SIDEBAR - MENGIKUTI DASHBOARD INDUSTRI */

        .industry-sidebar {
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
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--gold);
            color: var(--navy);
            font-size: 20px;
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
            margin: 18px 10px 10px;
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

        /* MAIN LAYOUT */

        .industry-main {
            min-height: 100vh;
            margin-left: 255px;
        }

        .industry-topbar {
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

        .industry-content {
            padding: 35px;
        }

        .page-title {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .page-description {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .page-eyebrow {
            display: inline-block;
            margin-bottom: 9px;
            color: var(--blue);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        /* SEARCH */

        .search-card {
            margin-bottom: 25px;
            padding: 17px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-input {
            min-height: 44px;
            padding: 11px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #f9fbfe;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 11px;
        }

        .search-input::placeholder {
            color: #98a6b8;
        }

        .search-input:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.10);
            outline: none;
        }

        .btn-search {
            min-width: 125px;
            min-height: 44px;
            padding: 10px 17px;
            border: 0;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-search:hover {
            background: var(--blue);
        }

        /* STUDENT CARDS */

        .student-card {
            height: 100%;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
            transition: .2s;
        }

        .student-card:hover {
            border-color: #c5d8ed;
            box-shadow: 0 10px 25px rgba(16,46,89,.08);
            transform: translateY(-3px);
        }

        .student-photo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 210px;
            overflow: hidden;
            background: #eaf0f7;
        }

        .student-photo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            height: 100%;
            color: #8a9bb0;
        }

        .placeholder-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 55px;
            height: 55px;
            border-radius: 15px;
            background: white;
            color: var(--blue);
            font-size: 25px;
        }

        .photo-placeholder span {
            font-size: 10px;
            font-weight: 600;
        }

        .student-card-body {
            display: flex;
            flex-direction: column;
            min-height: 225px;
            padding: 20px;
        }

        .student-name {
            margin: 0 0 6px;
            color: var(--navy);
            font-size: 16px;
            font-weight: 800;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .student-major {
            margin: 0 0 12px;
            color: var(--blue);
            font-size: 11px;
            font-weight: 700;
            line-height: 1.6;
        }

        .student-class {
            display: inline-flex;
            align-self: flex-start;
            margin-bottom: 12px;
            padding: 5px 9px;
            border-radius: 6px;
            background: #eaf2fc;
            color: var(--navy);
            font-size: 9px;
            font-weight: 700;
        }

        .student-bio {
            display: -webkit-box;
            min-height: 36px;
            margin: 0 0 13px;
            overflow: hidden;
            color: #66778c;
            font-size: 11px;
            line-height: 1.8;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .student-project-count {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: auto;
            margin-bottom: 15px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
        }

        .count-icon {
            color: var(--blue);
            font-size: 14px;
        }

        .btn-portfolio {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            align-self: flex-start;
            min-height: 37px;
            padding: 9px 13px;
            border: 1px solid var(--blue);
            border-radius: 7px;
            background: white;
            color: var(--blue);
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-portfolio:hover {
            background: var(--blue);
            color: white;
        }

        /* EMPTY STATE AND RESULT */

        .empty-card {
            padding: 45px 20px;
            border: 1px dashed #cbd7e5;
            border-radius: 13px;
            background: white;
            text-align: center;
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 57px;
            height: 57px;
            margin: 0 auto 15px;
            border-radius: 15px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 25px;
        }

        .empty-title {
            margin-bottom: 7px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .empty-description {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .result-info {
            margin-top: 23px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 11px;
        }

        .result-info strong {
            color: var(--navy);
        }

        /* RESPONSIVE SIDEBAR */

        .mobile-toggle {
            display: none;
        }

        @media (max-width: 991px) {

            .industry-sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .industry-sidebar.show {
                transform: translateX(0);
            }

            .industry-main {
                margin-left: 0;
            }

            .mobile-toggle {
                display: inline-block;
            }

            .industry-content {
                padding: 25px 20px;
            }

            .industry-topbar {
                padding: 15px 20px;
            }
        }

        @media (max-width: 767px) {

            .industry-content {
                padding: 25px 18px;
            }

            .page-title {
                font-size: 22px;
            }

            .student-photo-wrap {
                height: 190px;
            }
        }

        @media (max-width: 576px) {

            .industry-content {
                padding: 20px 14px;
            }

            .industry-topbar {
                padding: 15px 14px;
            }

            .search-form {
                flex-direction: column;
            }

            .btn-search {
                width: 100%;
            }

            .search-card {
                padding: 13px;
            }

            .student-photo-wrap {
                height: 220px;
            }
        }

    </style>

</head>

<body>

<!-- SIDEBAR INDUSTRI: STRUKTUR MENGIKUTI DASHBOARD -->

<aside class="industry-sidebar" id="industrySidebar">

    <a href="<?= BASE_URL ?>/industry/dashboard.php"
       class="sidebar-brand">

        <span class="sidebar-logo">S</span>

        <span>
            <strong>SIPORSI</strong>
            <small>INDUSTRY PORTAL</small>
        </span>

    </a>


    <div class="sidebar-label">
        Workspace
    </div>


    <a href="<?= BASE_URL ?>/industry/dashboard.php"
       class="sidebar-menu">

        <span class="sidebar-icon">⌂</span>
        Dashboard

    </a>


    <div class="sidebar-label">
        Talent Discovery
    </div>


    <a href="<?= BASE_URL ?>/industry/portfolios/index.php"
       class="sidebar-menu active">

        <span class="sidebar-icon">▤</span>
        Jelajahi Portofolio

    </a>


    <div class="sidebar-label">
        Komunikasi
    </div>


    <div class="sidebar-menu"
         style="opacity:.55; cursor:not-allowed;"
         title="Modul pesan rekrutmen belum tersedia">

        <span class="sidebar-icon">✉</span>
        Pesan Rekrutmen

        <span class="ms-auto small">Segera</span>

    </div>


    <div class="sidebar-label">
        Perusahaan
    </div>


    <a href="<?= BASE_URL ?>/industry/dashboard.php#profil-perusahaan"
       class="sidebar-menu">

        <span class="sidebar-icon">▣</span>
        Profil Perusahaan

    </a>


    <div class="sidebar-label">
        Lainnya
    </div>


    <a href="<?= BASE_URL ?>/"
       class="sidebar-menu">

        <span class="sidebar-icon">⌂</span>
        Halaman Publik

    </a>


    <div class="sidebar-bottom">

        <small>Perusahaan</small>

        <div class="fw-bold mt-1">
            <?= escape($companyName) ?>
        </div>

        <small><?= escape($industryType) ?></small>

    </div>

</aside>


<!-- MAIN -->

<div class="industry-main">


    <!-- TOPBAR -->

    <header class="industry-topbar">

        <div class="d-flex align-items-center gap-3">

            <button type="button"
                    class="btn btn-outline-secondary btn-sm mobile-toggle"
                    onclick="toggleSidebar()">

                ☰

            </button>

            <span class="topbar-title">
                SIPORSI / Talent Discovery / Jelajahi Portofolio
            </span>

        </div>


        <form action="<?= BASE_URL ?>/logout.php"
              method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= escape($csrfToken) ?>">

            <button type="submit"
                    class="btn btn-sm btn-outline-danger">

                Logout

            </button>

        </form>

    </header>


    <!-- CONTENT -->

    <main class="industry-content">


        <!-- PAGE HEADING -->

        <div class="mb-4">

            <span class="page-eyebrow">
                Portal Mitra Industri
            </span>

            <h1 class="page-title">
                Jelajahi Portofolio Siswa
            </h1>

            <p class="page-description">
                Temukan karya, keahlian, dan potensi siswa SMK Negeri 4 Banjarmasin.
            </p>

        </div>


        <!-- SEARCH -->

        <section class="search-card">

            <form method="GET" class="search-form">

                <input type="text"
                       name="search"
                       class="form-control search-input flex-grow-1"
                       placeholder="Cari nama siswa, jurusan, atau keahlian..."
                       value="<?= escape($keyword) ?>">

                <button type="submit" class="btn-search">
                    Cari Portofolio
                </button>

            </form>

        </section>


        <!-- DAFTAR PORTOFOLIO -->

        <div class="row g-4">

            <?php if (count($students) > 0): ?>

                <?php foreach ($students as $student): ?>

                    <div class="col-md-6 col-xl-4">

                        <article class="student-card">


                            <!-- FOTO SISWA -->

                            <div class="student-photo-wrap">

                                <?php if (!empty($student['photo'])): ?>

                                    <img src="<?= BASE_URL ?>/<?= escape($student['photo']) ?>"
                                         class="student-photo"
                                         alt="Foto <?= escape($student['full_name']) ?>">

                                <?php else: ?>

                                    <div class="photo-placeholder">

                                        <div class="placeholder-icon">
                                            ♙
                                        </div>

                                        <span>Foto belum tersedia</span>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- INFORMASI SISWA -->

                            <div class="student-card-body">

                                <h2 class="student-name">
                                    <?= escape($student['full_name']) ?>
                                </h2>


                                <p class="student-major">
                                    <?= escape($student['major_name']) ?>
                                </p>


                                <span class="student-class">
                                    Kelas: <?= escape($student['class_level'] ?: '-') ?>
                                </span>


                                <p class="student-bio">
                                    <?= escape($student['short_bio'] ?: 'Belum ada deskripsi.') ?>
                                </p>


                                <div class="student-project-count">

                                    <span class="count-icon">▤</span>

                                    <span>
                                        <?= (int) $student['total_projects'] ?> proyek disetujui
                                    </span>

                                </div>


                                <a href="<?= BASE_URL ?>/portfolio.php?slug=<?= urlencode($student['public_slug']) ?>"
                                   class="btn-portfolio">

                                    Lihat Portofolio
                                    <span class="ms-1">→</span>

                                </a>

                            </div>

                        </article>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="col-12">

                    <div class="empty-card">

                        <div class="empty-icon">
                            ♧
                        </div>

                        <h2 class="empty-title">
                            Portofolio Belum Tersedia
                        </h2>

                        <p class="empty-description">
                            Belum ada siswa yang mempublikasikan portofolionya
                            atau data yang kamu cari tidak ditemukan.
                        </p>

                    </div>

                </div>

            <?php endif; ?>

        </div>


        <!-- JUMLAH SISWA -->

        <div class="result-info">

            Jumlah siswa ditampilkan:
            <strong><?= count($students) ?></strong>

        </div>


        <!-- FOOTER -->

        <div class="text-center mt-4">

            <small class="text-secondary">
                SIPORSI — Sistem Informasi Portofolio Digital Siswa<br>
                SMK Negeri 4 Banjarmasin
            </small>

        </div>


    </main>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('industrySidebar').classList.toggle('show');
}
</script>

</body>
</html>