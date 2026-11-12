<?php
require_once __DIR__ . '/../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    http_response_code(404);
    exit('Portofolio tidak ditemukan.');
}

$db = Database::connection();

// Mengambil profil siswa yang memang diizinkan tampil secara publik.
$sql = "
    SELECT
        s.id,
        s.full_name,
        s.class_level,
        s.short_bio,
        s.biography,
        s.photo,
        s.public_slug,
        m.major_name
    FROM students s
    INNER JOIN majors m ON m.id = s.major_id
    WHERE s.public_slug = :slug
      AND s.is_public = 1
      AND s.student_status IN ('active', 'graduated')
    LIMIT 1
";

$stmt = $db->prepare($sql);
$stmt->execute(['slug' => $slug]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Portofolio tidak ditemukan atau tidak tersedia untuk publik.');
}

// Mengambil keterampilan siswa.
$skillStmt = $db->prepare("
    SELECT
        sk.skill_name,
        sk.skill_category,
        ss.proficiency,
        ss.description
    FROM student_skills ss
    INNER JOIN skills sk ON sk.id = ss.skill_id
    WHERE ss.student_id = :student_id
    ORDER BY sk.skill_category, sk.skill_name
");

$skillStmt->execute(['student_id' => $student['id']]);
$studentSkills = $skillStmt->fetchAll();

$levelLabels = [
    'beginner' => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced' => 'Mahir',
    'expert' => 'Ahli'
];

// Hanya proyek yang telah disetujui Admin yang ditampilkan ke publik.
$projectStmt = $db->prepare("
    SELECT
        title,
        description,
        category,
        student_role,
        tools_used,
        thumbnail,
        demo_url,
        repository_url,
        completion_date
    FROM projects
    WHERE student_id = :student_id
      AND publication_status = 'approved'
    ORDER BY is_featured DESC, published_at DESC
");

$projectStmt->execute([
    'student_id' => $student['id']
]);

$studentProjects = $projectStmt->fetchAll();

// Mengambil prestasi siswa.
$achievementStmt = $db->prepare("
    SELECT
        achievement_name,
        achievement_level,
        organizer,
        achievement_date,
        description
    FROM achievements
    WHERE student_id = :student_id
    ORDER BY achievement_date DESC, created_at DESC
");

$achievementStmt->execute([
    'student_id' => $student['id']
]);

$studentAchievements = $achievementStmt->fetchAll();

$achievementLevelLabels = [
    'school' => 'Sekolah',
    'city' => 'Kota/Kabupaten',
    'province' => 'Provinsi',
    'national' => 'Nasional',
    'international' => 'Internasional'
];

// Mengambil pengalaman PKL dan magang siswa.
$experienceStmt = $db->prepare("
    SELECT
        company_name,
        position,
        description,
        start_date,
        end_date
    FROM experiences
    WHERE student_id = :student_id
    ORDER BY start_date DESC, created_at DESC
");

$experienceStmt->execute([
    'student_id' => $student['id']
]);

$studentExperiences = $experienceStmt->fetchAll();


// Mengambil sertifikat yang telah diverifikasi Admin.
// Sertifikat yang belum disetujui tidak ditampilkan kepada publik.
$certificateStmt = $db->prepare("
    SELECT
        id,
        certificate_name,
        issuing_organization,
        certificate_number,
        issue_date,
        expiry_date,
        certificate_file
    FROM certificates
    WHERE student_id = :student_id
      AND verification_status = 'verified'
    ORDER BY issue_date DESC, created_at DESC
");

$certificateStmt->execute([
    'student_id' => $student['id']
]);

$studentCertificates = $certificateStmt->fetchAll();


// Helper tampilan.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function portfolioDate($value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '-' : date('d-m-Y', $timestamp);
}

function portfolioUrl($value): ?string
{
    $url = trim((string) ($value ?? ''));

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

$safeName = e($student['full_name']);
$safeMajor = e($student['major_name']);
$safeClass = e($student['class_level'] ?? '-');
$safeBio = trim((string) ($student['short_bio'] ?? ''));
$safeBiography = trim((string) ($student['biography'] ?? ''));
$initial = strtoupper(mb_substr((string) $student['full_name'], 0, 1, 'UTF-8'));

$sectionCounts = [
    'skills' => count($studentSkills),
    'projects' => count($studentProjects),
    'experiences' => count($studentExperiences),
    'achievements' => count($studentAchievements),
    'certificates' => count($studentCertificates)
];
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description"
          content="Portofolio digital <?= $safeName ?>, siswa <?= $safeMajor ?> di SIPORSI SMK Negeri 4 Banjarmasin.">

    <title>Portofolio <?= $safeName ?> | SIPORSI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>

        :root {
            --navy: #102e59;
            --navy-deep: #0b2142;
            --blue: #1769aa;
            --blue-bright: #2678bd;
            --gold: #f4b942;
            --background: #f4f7fc;
            --text: #1c2e45;
            --muted: #718096;
            --border: #e7edf5;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }

        a {
            text-decoration: none;
        }

        /* NAVBAR */

        .site-navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 72px;
            background: rgba(16, 46, 89, .97);
            box-shadow: 0 5px 20px rgba(11, 33, 66, .12);
            backdrop-filter: blur(12px);
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            margin-right: 10px;
            border-radius: 10px;
            background: var(--gold);
            color: var(--navy);
            font-size: 17px;
            font-weight: 800;
        }

        .navbar-brand {
            color: white !important;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: .7px;
        }

        .navbar-caption {
            color: rgba(255,255,255,.72);
            font-size: 11px;
        }

        .nav-public-link {
            padding: 9px 14px;
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 8px;
            color: white;
            font-size: 11px;
            font-weight: 600;
            transition: .2s;
        }

        .nav-public-link:hover {
            border-color: var(--gold);
            background: rgba(255,255,255,.08);
            color: white;
        }

        /* PAGE */

        .page-shell {
            width: min(1120px, calc(100% - 36px));
            margin: 38px auto 52px;
        }

        /* PROFILE HERO */

        .profile-hero {
            position: relative;
            overflow: hidden;
            padding: 42px 46px;
            border-radius: 22px;
            background: linear-gradient(120deg, var(--navy-deep), #174f86 62%, #1b70ae);
            box-shadow: 0 18px 42px rgba(16,46,89,.15);
            color: white;
        }

        .profile-hero::before,
        .profile-hero::after {
            position: absolute;
            content: "";
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50%;
            pointer-events: none;
        }

        .profile-hero::before {
            top: -180px;
            right: -90px;
            width: 420px;
            height: 420px;
            box-shadow: 0 0 0 35px rgba(255,255,255,.025),
                        0 0 0 75px rgba(255,255,255,.02);
        }

        .profile-hero::after {
            right: 190px;
            bottom: -260px;
            width: 360px;
            height: 360px;
        }

        .profile-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 27px;
        }

        .profile-photo,
        .profile-initial {
            display: flex;
            flex: 0 0 116px;
            align-items: center;
            justify-content: center;
            width: 116px;
            height: 116px;
            border: 4px solid rgba(255,255,255,.28);
            border-radius: 24px;
            background: var(--gold);
            color: var(--navy);
            object-fit: cover;
            font-size: 43px;
            font-weight: 800;
            box-shadow: 0 8px 22px rgba(0,0,0,.14);
        }

        .profile-eyebrow {
            margin-bottom: 8px;
            color: #f8d88d;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .profile-name {
            margin: 0 0 8px;
            color: white;
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .profile-major {
            margin-bottom: 14px;
            color: rgba(255,255,255,.8);
            font-size: 13px;
        }

        .profile-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .profile-tag {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 7px;
            background: rgba(255,255,255,.1);
            color: white;
            font-size: 10px;
            font-weight: 600;
        }

        .profile-tag.highlight {
            border-color: transparent;
            background: var(--gold);
            color: var(--navy);
        }

        .profile-intro {
            max-width: 650px;
            margin: 21px 0 0;
            color: rgba(255,255,255,.82);
            font-size: 12px;
            line-height: 1.9;
        }

        /* QUICK STATS */

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-top: 18px;
        }

        .stat-card {
            padding: 17px 18px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            box-shadow: 0 5px 18px rgba(16,46,89,.035);
        }

        .stat-number {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 22px;
            font-weight: 800;
        }

        .stat-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
        }

        /* CONTENT SECTION */

        .content-section {
            margin-top: 22px;
            padding: 28px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
            scroll-margin-top: 95px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-icon {
            display: flex;
            flex: 0 0 40px;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 18px;
            font-weight: 700;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--navy);
            font-size: 16px;
            font-weight: 800;
        }

        .section-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 10px;
        }

        .about-text {
            margin: 0;
            color: #52657c;
            font-size: 12px;
            line-height: 1.9;
            white-space: pre-wrap;
        }

        .about-text + .about-text {
            margin-top: 14px;
        }

        .subheading {
            margin: 21px 0 9px;
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
        }

        /* ITEM GRIDS */

    /* PUBLIC CERTIFICATES */

        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .public-certificate-card {
            height: 100%;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: white;
            transition: .2s;
        }

        .public-certificate-card:hover {
            border-color: #c5d8ed;
            box-shadow: 0 9px 22px rgba(16,46,89,.07);
            transform: translateY(-2px);
        }

        .certificate-file-public {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 35px;
            margin-top: 13px;
            padding: 8px 12px;
            border-radius: 7px;
            background: var(--navy);
            color: white;
            font-size: 10px;
            font-weight: 700;
        }

        .certificate-file-public:hover {
            background: var(--blue);
            color: white;
        }

        .skill-grid,
        .experience-grid,
        .achievement-grid,
        .project-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .skill-card,
        .experience-card,
        .achievement-card {
            height: 100%;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
            transition: .2s;
        }

        .skill-card:hover,
        .experience-card:hover,
        .achievement-card:hover,
        .project-card:hover {
            border-color: #c5d8ed;
            box-shadow: 0 9px 22px rgba(16,46,89,.07);
            transform: translateY(-2px);
        }

        .item-title {
            margin: 0 0 9px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.55;
        }

        /* BADGES */

        .category-pill,
        .level-pill {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 700;
        }

        .category-pill {
            margin: 0 5px 7px 0;
            background: #eaf2fc;
            color: var(--blue);
        }

        .level-pill {
            background: #fff4d9;
            color: #8c650d;
        }

        .item-copy {
            margin: 7px 0 0;
            color: #66778c;
            font-size: 11px;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .item-meta {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .item-meta strong {
            color: #465a72;
            font-weight: 700;
        }

        /* PROJECT CARDS */

        .project-card {
            overflow: hidden;
            height: 100%;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            transition: .2s;
        }

        .project-image-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 215px;
            overflow: hidden;
            background: #f1f5fa;
        }

        .project-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .3s;
        }

        .project-card:hover .project-image {
            transform: scale(1.025);
        }

        .project-image-placeholder {
            color: #9aabc0;
            font-size: 34px;
        }

        .project-body {
            padding: 19px;
        }

        .project-body .item-title {
            font-size: 14px;
        }

        .project-description {
            margin: 8px 0 12px;
            color: #66778c;
            font-size: 11px;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .project-details {
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .project-details p {
            margin: 5px 0;
            color: #66778c;
            font-size: 10px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .project-details strong {
            color: var(--navy);
        }

        .project-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .btn-project {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 8px 12px;
            border-radius: 7px;
            background: var(--navy);
            color: white;
            font-size: 10px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-project:hover {
            background: var(--blue);
            color: white;
        }

        .btn-project.outline {
            border: 1px solid var(--border);
            background: white;
            color: var(--navy);
        }

        .btn-project.outline:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 27px 18px;
            border: 1px dashed #cbd7e5;
            border-radius: 11px;
            background: #fafcff;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
            line-height: 1.8;
        }

        .empty-state-icon {
            display: block;
            margin-bottom: 8px;
            color: #a4b4c8;
            font-size: 25px;
        }

        /* PUBLIC NOTICE */

        .public-notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-top: 22px;
            padding: 15px 17px;
            border: 1px solid #cfe4f8;
            border-radius: 11px;
            background: #f0f7ff;
            color: #63758a;
            font-size: 10px;
            line-height: 1.8;
        }

        .public-notice strong {
            display: block;
            margin-bottom: 2px;
            color: var(--navy);
            font-size: 11px;
        }

        .notice-symbol {
            color: var(--blue);
            font-size: 17px;
        }

        /* FOOTER */

        .site-footer {
            padding: 28px 18px 32px;
            background: var(--navy-deep);
            color: rgba(255,255,255,.66);
            text-align: center;
        }

        .footer-brand {
            margin-bottom: 6px;
            color: white;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .site-footer p {
            margin: 4px 0;
            font-size: 10px;
            line-height: 1.7;
        }

        .back-top {
            display: inline-block;
            margin-top: 10px;
            color: #f8d88d;
            font-size: 10px;
            font-weight: 700;
        }

        /* RESPONSIVE */

        @media (max-width: 768px) {

            .page-shell {
                width: min(100% - 24px, 650px);
                margin-top: 20px;
            }

            .profile-hero {
                padding: 27px 23px;
                border-radius: 17px;
            }

            .profile-hero-content {
                align-items: flex-start;
                gap: 17px;
            }

            .profile-photo,
            .profile-initial {
                flex-basis: 82px;
                width: 82px;
                height: 82px;
                border-radius: 18px;
                font-size: 31px;
            }

            .profile-name {
                font-size: 25px;
            }

            .profile-major {
                font-size: 11px;
            }

            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 9px;
            }

            .content-section {
                padding: 21px 18px;
            }

            .skill-grid,
            .experience-grid,
            .achievement-grid,
            .project-grid
            .certificate-grid {
                grid-template-columns: 1fr;
            }

            .project-image-wrap {
                height: 205px;
            }
        }

        @media (max-width: 420px) {

            .navbar-caption {
                display: none;
            }

            .profile-hero-content {
                flex-direction: column;
            }

            .profile-photo,
            .profile-initial {
                width: 78px;
                height: 78px;
            }

            .profile-name {
                font-size: 23px;
            }

            .stat-card {
                padding: 13px;
            }

            .stat-number {
                font-size: 19px;
            }

            .section-heading h2 {
                font-size: 14px;
            }
        }

        /* NAVIGASI BAGIAN PORTOFOLIO */

        .portfolio-section-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 3px;
            white-space: nowrap;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
        }

        .portfolio-section-nav a {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 7px;
            color: rgba(255,255,255,.85);
            font-size: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
        }

        .portfolio-section-nav a:hover {
            border-color: var(--gold);
            background: rgba(255,255,255,.10);
            color: var(--gold);
        }

        /* Ruang untuk navbar sticky ketika menuju bagian */
        .content-section {
            scroll-margin-top: 125px;
        }

    </style>

</head>

<body id="top">

<!-- NAVBAR -->
<nav class="navbar site-navbar navbar-dark">

    <!-- BARIS UTAMA -->
    <div class="container">

        <a class="navbar-brand d-flex align-items-center"
           href="<?= BASE_URL ?>/">

            <span class="brand-mark">S</span>SIPORSI

        </a>

        <div class="d-flex align-items-center gap-3">

            <span class="navbar-caption d-none d-sm-inline">
                Portofolio Digital Siswa SMK Negeri 4 Banjarmasin
            </span>

            <a href="<?= BASE_URL ?>/"
               class="nav-public-link">

                Beranda

            </a>

        </div>

    </div>

    <!-- NAVIGASI PORTOFOLIO -->
    <div class="container pb-3">

        <div class="portfolio-section-nav"
             role="navigation"
             aria-label="Navigasi bagian portofolio">

            <a href="#tentang">
                Tentang Saya
            </a>

            <a href="#keterampilan">
                Keterampilan
            </a>

            <a href="#proyek">
                Proyek dan Karya
            </a>

            <a href="#pengalaman">
                Pengalaman PKL
            </a>

            <a href="#prestasi">
                Prestasi
            </a>

            <a href="#sertifikat">
                Sertifikat
            </a>

        </div>

    </div>

</nav>


<main class="page-shell">

    <!-- PROFIL UTAMA SISWA -->
    <section class="profile-hero">

        <div class="profile-hero-content">

            <?php if (!empty($student['photo'])): ?>

                <img
                    src="<?= BASE_URL . '/' . e($student['photo']) ?>"
                    alt="Foto <?= $safeName ?>"
                    class="profile-photo"
                >

            <?php else: ?>

                <div class="profile-initial"
                     aria-label="Inisial <?= $safeName ?>">

                    <?= e($initial) ?>

                </div>

            <?php endif; ?>


            <div>

                <div class="profile-eyebrow">
                    Portofolio Kompetensi Siswa
                </div>

                <h1 class="profile-name">
                    <?= $safeName ?>
                </h1>

                <p class="profile-major">
                    <?= $safeMajor ?>
                </p>

                <div class="profile-tags">

                    <span class="profile-tag highlight">
                        Kelas <?= $safeClass ?>
                    </span>

                    <span class="profile-tag">
                        SMK Negeri 4 Banjarmasin
                    </span>

                </div>

                <?php if ($safeBio !== ''): ?>

                    <p class="profile-intro">
                        <?= e($safeBio) ?>
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- RINGKASAN PORTOFOLIO -->
    <section class="quick-stats"
             aria-label="Ringkasan portofolio">

        <div class="stat-card">

            <span class="stat-number">
                <?= $sectionCounts['skills'] ?>
            </span>

            <span class="stat-label">
                Keterampilan
            </span>

        </div>

        <div class="stat-card">

            <span class="stat-number">
                <?= $sectionCounts['projects'] ?>
            </span>

            <span class="stat-label">
                Proyek Disetujui
            </span>

        </div>

        <div class="stat-card">

            <span class="stat-number">
                <?= $sectionCounts['experiences'] ?>
            </span>

            <span class="stat-label">
                Pengalaman
            </span>

        </div>

        <div class="stat-card">

            <span class="stat-number">
                <?= $sectionCounts['achievements'] ?>
            </span>

            <span class="stat-label">
                Prestasi
            </span>

        </div>

        <div class="stat-card">

            <span class="stat-number">
                <?= $sectionCounts['certificates'] ?>
            </span>

            <span class="stat-label">
                Sertifikat Terverifikasi
            </span>

        </div>

    </section>


    <!-- TENTANG SISWA -->
    <section class="content-section"
             id="tentang">

        <div class="section-heading">

            <span class="section-icon">✦</span>

            <div>

                <h2>Tentang Saya</h2>

                <p>
                    Kenali profil dan latar belakang siswa.
                </p>

            </div>

        </div>


        <p class="about-text">
            <?= $safeBio !== '' ? e($safeBio) : 'Belum ada deskripsi singkat.' ?>
        </p>


        <h3 class="subheading">
            Profil Lengkap
        </h3>

        <p class="about-text">
            <?= $safeBiography !== '' ? nl2br(e($safeBiography)) : 'Belum ada profil lengkap.' ?>
        </p>

    </section>


    <!-- KETERAMPILAN -->
    <section class="content-section"
             id="keterampilan">

        <div class="section-heading">

            <span class="section-icon">◇</span>

            <div>

                <h2>Keterampilan dan Keahlian</h2>

                <p>
                    Kompetensi yang dicantumkan dalam profil siswa.
                </p>

            </div>

        </div>


        <?php if (!empty($studentSkills)): ?>

            <div class="skill-grid">

                <?php foreach ($studentSkills as $skill): ?>

                    <article class="skill-card">

                        <h3 class="item-title">
                            <?= e($skill['skill_name']) ?>
                        </h3>

                        <span class="category-pill">
                            <?= e($skill['skill_category'] ?? 'Umum') ?>
                        </span>

                        <span class="level-pill">
                            <?= e($levelLabels[$skill['proficiency']] ?? '-') ?>
                        </span>

                        <p class="item-copy">
                            <?= e($skill['description'] ?? 'Belum ada deskripsi.') ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <span class="empty-state-icon">◇</span>

                Belum ada keterampilan yang ditampilkan.

            </div>

        <?php endif; ?>

    </section>


    <!-- PROYEK -->
    <section class="content-section"
             id="proyek">

        <div class="section-heading">

            <span class="section-icon">▤</span>

            <div>

                <h2>Proyek dan Hasil Karya</h2>

                <p>
                    Karya siswa yang telah melalui pemeriksaan Admin SIPORSI.
                </p>

            </div>

        </div>


        <?php if (!empty($studentProjects)): ?>

            <div class="project-grid">

                <?php foreach ($studentProjects as $project): ?>

                    <?php
                    $demoUrl = portfolioUrl($project['demo_url'] ?? '');
                    $repositoryUrl = portfolioUrl($project['repository_url'] ?? '');
                    ?>

                    <article class="project-card">

                        <div class="project-image-wrap">

                            <?php if (!empty($project['thumbnail'])): ?>

                                <img
                                    src="<?= BASE_URL . '/' . e($project['thumbnail']) ?>"
                                    class="project-image"
                                    alt="Gambar proyek <?= e($project['title']) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <span class="project-image-placeholder">
                                    ▧
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="project-body">

                            <h3 class="item-title">
                                <?= e($project['title']) ?>
                            </h3>

                            <span class="category-pill">
                                <?= e($project['category'] ?? 'Proyek') ?>
                            </span>

                            <p class="project-description">
                                <?= nl2br(e($project['description'])) ?>
                            </p>


                            <div class="project-details">

                                <?php if (!empty($project['student_role'])): ?>

                                    <p>
                                        <strong>Peran:</strong>
                                        <?= e($project['student_role']) ?>
                                    </p>

                                <?php endif; ?>


                                <?php if (!empty($project['tools_used'])): ?>

                                    <p>
                                        <strong>Teknologi / Alat:</strong>
                                        <?= e($project['tools_used']) ?>
                                    </p>

                                <?php endif; ?>


                                <?php if (!empty($project['completion_date'])): ?>

                                    <p>
                                        <strong>Selesai:</strong>
                                        <?= e(portfolioDate($project['completion_date'])) ?>
                                    </p>

                                <?php endif; ?>

                            </div>


                            <?php if ($demoUrl || $repositoryUrl): ?>

                                <div class="project-actions">

                                    <?php if ($demoUrl): ?>

                                        <a href="<?= e($demoUrl) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn-project">

                                            Lihat Demo ↗

                                        </a>

                                    <?php endif; ?>


                                    <?php if ($repositoryUrl): ?>

                                        <a href="<?= e($repositoryUrl) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn-project outline">

                                            Repository ↗

                                        </a>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <span class="empty-state-icon">▤</span>

                Belum ada proyek yang disetujui untuk ditampilkan.

            </div>

        <?php endif; ?>

    </section>


    <!-- PENGALAMAN -->
    <section class="content-section"
             id="pengalaman">

        <div class="section-heading">

            <span class="section-icon">▣</span>

            <div>

                <h2>Pengalaman PKL dan Magang</h2>

                <p>
                    Pengalaman praktik kerja dan kegiatan profesional siswa.
                </p>

            </div>

        </div>


        <?php if (!empty($studentExperiences)): ?>

            <div class="experience-grid">

                <?php foreach ($studentExperiences as $experience): ?>

                    <?php
                    $start = !empty($experience['start_date'])
                        ? portfolioDate($experience['start_date'])
                        : '-';

                    $end = !empty($experience['end_date'])
                        ? portfolioDate($experience['end_date'])
                        : 'Sekarang';
                    ?>

                    <article class="experience-card">

                        <h3 class="item-title">
                            <?= e($experience['company_name']) ?>
                        </h3>

                        <span class="category-pill">
                            <?= e($experience['position'] ?? '-') ?>
                        </span>

                        <p class="item-meta">
                            <strong>Periode:</strong>
                            <?= e($start) ?> s.d. <?= e($end) ?>
                        </p>

                        <p class="item-copy">
                            <?= nl2br(e($experience['description'] ?? 'Tidak ada deskripsi.')) ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <span class="empty-state-icon">▣</span>

                Belum ada pengalaman PKL atau magang yang ditampilkan.

            </div>

        <?php endif; ?>

    </section>


    <!-- PRESTASI -->
    <section class="content-section"
             id="prestasi">

        <div class="section-heading">

            <span class="section-icon">★</span>

            <div>

                <h2>Prestasi dan Penghargaan</h2>

                <p>
                    Pencapaian dan penghargaan yang dicantumkan siswa.
                </p>

            </div>

        </div>


        <?php if (!empty($studentAchievements)): ?>

            <div class="achievement-grid">

                <?php foreach ($studentAchievements as $achievement): ?>

                    <article class="achievement-card">

                        <h3 class="item-title">
                            <?= e($achievement['achievement_name']) ?>
                        </h3>

                        <span class="level-pill">
                            <?= e($achievementLevelLabels[$achievement['achievement_level']] ?? 'Tidak ditentukan') ?>
                        </span>


                        <?php if (!empty($achievement['organizer'])): ?>

                            <p class="item-meta">
                                <strong>Penyelenggara:</strong>
                                <?= e($achievement['organizer']) ?>
                            </p>

                        <?php endif; ?>


                        <?php if (!empty($achievement['achievement_date'])): ?>

                            <p class="item-meta">
                                <strong>Tanggal:</strong>
                                <?= e(portfolioDate($achievement['achievement_date'])) ?>
                            </p>

                        <?php endif; ?>


                        <p class="item-copy">
                            <?= nl2br(e($achievement['description'] ?? 'Tidak ada deskripsi.')) ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <span class="empty-state-icon">★</span>

                Belum ada prestasi atau penghargaan yang ditampilkan.

            </div>

        <?php endif; ?>

    </section>

    <!-- SERTIFIKAT TERVERIFIKASI -->
    <section class="content-section" id="sertifikat">

        <div class="section-heading">

            <span class="section-icon">▧</span>

            <div>
                <h2>Sertifikat dan Kompetensi</h2>

                <p>
                    Sertifikat siswa yang telah diperiksa dan disetujui Admin SIPORSI.
                </p>
            </div>

        </div>

        <?php if (!empty($studentCertificates)): ?>

            <div class="certificate-grid">

                <?php foreach ($studentCertificates as $certificate): ?>

                    <article class="public-certificate-card">

                        <h3 class="item-title">
                            <?= e($certificate['certificate_name']) ?>
                        </h3>

                        <?php if (!empty($certificate['issuing_organization'])): ?>

                            <p class="item-meta">
                                <strong>Penerbit:</strong>
                                <?= e($certificate['issuing_organization']) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($certificate['certificate_number'])): ?>

                            <p class="item-meta">
                                <strong>Nomor Sertifikat:</strong>
                                <?= e($certificate['certificate_number']) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($certificate['issue_date'])): ?>

                            <p class="item-meta">
                                <strong>Tanggal Terbit:</strong>
                                <?= e(portfolioDate($certificate['issue_date'])) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($certificate['expiry_date'])): ?>

                            <p class="item-meta">
                                <strong>Berlaku Sampai:</strong>
                                <?= e(portfolioDate($certificate['expiry_date'])) ?>
                            </p>

                        <?php endif; ?>


                        <?php if (!empty($certificate['certificate_file'])): ?>

                            <a
                                href="<?= BASE_URL ?>/certificate-file.php?id=<?= (int) $certificate['id'] ?>&amp;slug=<?= urlencode($student['public_slug']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="certificate-file-public">

                                Lihat Sertifikat ↗
                            </a>

                        <?php endif; ?>


                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <span class="empty-state-icon">▧</span>

                Belum ada sertifikat yang terverifikasi untuk ditampilkan.

            </div>

        <?php endif; ?>

    </section>

    <!-- INFORMASI PUBLIK -->
    <div class="public-notice">

        <span class="notice-symbol">ⓘ</span>

        <div>

            <strong>Informasi Portofolio Publik</strong>

            Halaman ini menampilkan informasi portofolio yang dipublikasikan
            oleh siswa melalui SIPORSI. Data pribadi seperti alamat dan
            nomor telepon privat tidak ditampilkan.

        </div>

    </div>

</main>


<!-- FOOTER -->
<footer class="site-footer">

    <div class="footer-brand">
        SIPORSI
    </div>

    <p>
        Sistem Informasi Portofolio Digital Siswa
    </p>

    <p>
        SMK Negeri 4 Banjarmasin
    </p>

    <p>
        &copy; <?= date('Y') ?> SIPORSI. Seluruh hak cipta dilindungi.
    </p>

    <a href="#top" class="back-top">
        Kembali ke atas ↑
    </a>

</footer>

</body>
</html>