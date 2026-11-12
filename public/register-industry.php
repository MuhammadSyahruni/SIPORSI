
<?php

require_once __DIR__ . '/../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$db = Database::connection();

$errors = [];
$companyName = '';
$industryType = '';
$companyDescription = '';
$website = '';
$emailContact = '';
$phoneContact = '';
$address = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $companyName = trim($_POST['company_name'] ?? '');
    $industryType = trim($_POST['industry_type'] ?? '');
    $companyDescription = trim($_POST['company_description'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $emailContact = trim($_POST['email_contact'] ?? '');
    $phoneContact = trim($_POST['phone_contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid. Muat ulang halaman.';
    }

    if ($companyName === '' || $username === '' || $email === '') {
        $errors[] = 'Nama perusahaan, username, dan email wajib diisi.';
    }

    if (strlen($companyName) > 180 || strlen($industryType) > 100) {
        $errors[] = 'Nama perusahaan atau jenis industri terlalu panjang.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email akun tidak valid.';
    }

    if ($emailContact !== '' && !filter_var($emailContact, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email perusahaan tidak valid.';
    }

    if ($website !== '' &&
        (!filter_var($website, FILTER_VALIDATE_URL) ||
         !in_array(strtolower(parse_url($website, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))) {
        $errors[] = 'Format website harus menggunakan alamat HTTP atau HTTPS yang valid.';
    }

    if ($password === '' || strlen($password) < 12) {
        $errors[] = 'Password minimal 12 karakter.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if (!$errors) {
        $check = $db->prepare("
            SELECT id
            FROM users
            WHERE username = :username
               OR email = :email
            LIMIT 1
        ");

        $check->execute([
            'username' => $username,
            'email' => $email
        ]);

        if ($check->fetch()) {
            $errors[] = 'Username atau email akun sudah digunakan.';
        }
    }

    if (!$errors) {
        try {
            $db->beginTransaction();

            // Membuat akun industri
            $insertUser = $db->prepare("
                INSERT INTO users (
                    role_id,
                    username,
                    email,
                    password_hash,
                    account_status
                )
                VALUES (
                    3,
                    :username,
                    :email,
                    :password_hash,
                    'active'
                )
            ");

            $insertUser->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            $userId = (int) $db->lastInsertId();

            // Membuat profil perusahaan
            $insertIndustry = $db->prepare("
                INSERT INTO industries (
                    user_id,
                    company_name,
                    industry_type,
                    company_description,
                    website,
                    email_contact,
                    phone_contact,
                    address,
                    verification_status
                )
                VALUES (
                    :user_id,
                    :company_name,
                    :industry_type,
                    :company_description,
                    :website,
                    :email_contact,
                    :phone_contact,
                    :address,
                    'pending'
                )
            ");

            $insertIndustry->execute([
                'user_id' => $userId,
                'company_name' => $companyName,
                'industry_type' => $industryType ?: null,
                'company_description' => $companyDescription ?: null,
                'website' => $website ?: null,
                'email_contact' => $emailContact ?: null,
                'phone_contact' => $phoneContact ?: null,
                'address' => $address ?: null
            ]);

            $db->commit();

            header('Location: ' . BASE_URL . '/login.php?registered=1');
            exit;

        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log($exception->getMessage());
            $errors[] = 'Pendaftaran gagal. Silakan coba kembali.';
        }
    }
}

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

    <title>Pendaftaran Industri | SIPORSI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="<?= BASE_URL ?>/assets/css/style.css"
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
            --border: #e5ebf3;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        /* NAVBAR */

        .register-navbar {
            background: var(--navy);
            box-shadow: 0 3px 15px rgba(16,46,89,.12);
        }

        .register-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 1px;
            text-decoration: none;
        }

        .brand-symbol {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 37px;
            height: 37px;
            border-radius: 10px;
            background: var(--gold);
            color: var(--navy);
            font-weight: 800;
        }

        .navbar-back {
            color: rgba(255,255,255,.85);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .navbar-back:hover {
            color: var(--gold);
        }

        /* PAGE */

        .register-wrapper {
            padding: 45px 15px 65px;
        }

        .register-layout {
            display: grid;
            grid-template-columns: 310px minmax(0, 1fr);
            max-width: 1050px;
            margin: auto;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: white;
            box-shadow: 0 20px 55px rgba(16,46,89,.08);
        }

        /* LEFT PANEL */

        .register-info {
            position: relative;
            overflow: hidden;
            padding: 40px 30px;
            background: linear-gradient(145deg, #102e59, #1769aa);
            color: white;
        }

        .register-info::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -140px;
            bottom: -90px;
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 50%;
            box-shadow: 0 0 0 35px rgba(255,255,255,.04);
        }

        .info-content {
            position: relative;
            z-index: 2;
        }

        .info-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 58px;
            height: 58px;
            margin-bottom: 28px;
            border-radius: 15px;
            background: var(--gold);
            color: var(--navy);
            font-size: 26px;
        }

        .info-title {
            font-size: 29px;
            font-weight: 800;
            line-height: 1.25;
        }

        .info-description {
            margin-top: 17px;
            color: rgba(255,255,255,.78);
            font-size: 13px;
            line-height: 1.9;
        }

        .info-divider {
            width: 48px;
            height: 4px;
            margin: 27px 0;
            border-radius: 10px;
            background: var(--gold);
        }

        .info-feature {
            display: flex;
            gap: 12px;
            margin-top: 19px;
            color: rgba(255,255,255,.9);
            font-size: 12px;
            line-height: 1.7;
        }

        .feature-check {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(255,255,255,.15);
            color: var(--gold);
            font-weight: 800;
        }

        .info-footer {
            position: relative;
            z-index: 2;
            margin-top: 45px;
            color: rgba(255,255,255,.55);
            font-size: 10px;
        }

        /* FORM AREA */

        .register-form-area {
            padding: 42px;
        }

        .form-heading {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .form-subheading {
            margin-bottom: 28px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .form-section-title {
            display: flex;
            align-items: center;
            gap: 11px;
            margin: 25px 0 20px;
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
        }

        .form-section-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            border-radius: 10px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 16px;
        }

        .section-caption {
            margin-top: 2px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 400;
        }

        .form-label {
            margin-bottom: 7px;
            color: #35465e;
            font-size: 12px;
            font-weight: 600;
        }

        .required-mark {
            color: #dc3545;
        }

        .form-control {
            min-height: 43px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #f9fbfe;
            color: var(--text);
            font-size: 12px;
        }

        .form-control:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.1);
        }

        textarea.form-control {
            min-height: 85px;
            resize: vertical;
        }

        .field-help {
            margin-top: 6px;
            color: var(--muted);
            font-size: 10px;
        }

        .section-divider {
            margin: 28px 0;
            border: 0;
            border-top: 1px solid var(--border);
        }

        /* ALERTS */

        .register-alert {
            border-radius: 10px;
            font-size: 12px;
        }

        /* BUTTONS */

        .btn-register {
            min-height: 45px;
            padding: 10px 22px;
            border: none;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-register:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        .btn-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 45px;
            padding: 10px 18px;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #52647b;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
        }

        .btn-login:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        /* VERIFICATION NOTICE */

        .verification-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 23px 0;
            padding: 15px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .notice-icon {
            color: var(--blue);
            font-size: 19px;
        }

        .verification-notice strong {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 11px;
        }

        .verification-notice p {
            margin: 0;
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
        }

        .form-copyright {
            margin-top: 25px;
            color: #94a0b1;
            font-size: 10px;
            text-align: center;
        }

        /* RESPONSIVE */

        @media (max-width: 767px) {

            .register-wrapper {
                padding: 22px 12px 40px;
            }

            .register-layout {
                grid-template-columns: 1fr;
                border-radius: 16px;
            }

            .register-info {
                padding: 30px 25px;
            }

            .info-title {
                font-size: 25px;
            }

            .info-icon {
                margin-bottom: 18px;
            }

            .info-divider {
                margin: 20px 0;
            }

            .info-feature {
                margin-top: 12px;
            }

            .info-footer {
                margin-top: 25px;
            }

            .register-form-area {
                padding: 28px 23px;
            }

            .form-heading {
                font-size: 22px;
            }

        }

    </style>

</head>

<body>

<!-- NAVBAR -->
<nav class="navbar register-navbar py-3">

    <div class="container">

        <a href="<?= BASE_URL ?>/"
           class="register-brand">

            <span class="brand-symbol">S</span>
            SIPORSI

        </a>

        <a href="<?= BASE_URL ?>/login.php"
           class="navbar-back">
            Sudah punya akun? Login →
        </a>

    </div>

</nav>


<main class="register-wrapper">

    <div class="register-layout">

        <!-- PANEL INFORMASI -->
        <aside class="register-info">

            <div class="info-content">

                <div class="info-icon">🏢</div>

                <h1 class="info-title">
                    Bergabung Menjadi Mitra Industri SIPORSI
                </h1>

                <p class="info-description">
                    Hubungkan perusahaan Anda dengan potensi,
                    keterampilan, dan karya siswa SMK Negeri 4
                    Banjarmasin melalui platform portofolio digital.
                </p>

                <div class="info-divider"></div>

                <div class="fw-bold small">
                    Keuntungan menjadi mitra
                </div>

                <div class="info-feature">
                    <span class="feature-check">✓</span>
                    <span>Mengenal kompetensi dan hasil karya siswa.</span>
                </div>

                <div class="info-feature">
                    <span class="feature-check">✓</span>
                    <span>Menemukan calon tenaga kerja potensial.</span>
                </div>

                <div class="info-feature">
                    <span class="feature-check">✓</span>
                    <span>Mendukung hubungan dunia pendidikan dan industri.</span>
                </div>

                <div class="info-footer">
                    SIPORSI — SMK Negeri 4 Banjarmasin
                </div>

            </div>

        </aside>


        <!-- FORM PENDAFTARAN -->
        <section class="register-form-area">

            <h2 class="form-heading">
                Pendaftaran Industri
            </h2>

            <p class="form-subheading">
                Lengkapi informasi perusahaan dan akun untuk
                mendaftar sebagai mitra SIPORSI.
                Kolom bertanda <span class="required-mark">*</span>
                wajib diisi.
            </p>


            <!-- PESAN ERROR -->
            <?php if ($errors): ?>

                <div class="alert alert-danger register-alert">

                    <strong class="d-block mb-2">
                        Pendaftaran belum berhasil.
                    </strong>

                    <ul class="mb-0 ps-3">

                        <?php foreach ($errors as $error): ?>

                            <li><?= escape($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= escape(Csrf::token()) ?>">


                <!-- INFORMASI PERUSAHAAN -->
                <div class="form-section-title">

                    <span class="form-section-icon">🏢</span>

                    <span>
                        Informasi Perusahaan
                        <span class="section-caption d-block">
                            Identitas dan informasi kontak perusahaan
                        </span>
                    </span>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Nama Perusahaan
                        <span class="required-mark">*</span>
                    </label>

                    <input type="text"
                           name="company_name"
                           class="form-control"
                           maxlength="180"
                           value="<?= escape($companyName) ?>"
                           placeholder="Masukkan nama perusahaan"
                           required>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Jenis Industri
                    </label>

                    <input type="text"
                           name="industry_type"
                           class="form-control"
                           maxlength="100"
                           value="<?= escape($industryType) ?>"
                           placeholder="Contoh: Perhotelan, Kuliner, Teknologi">

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Deskripsi Perusahaan
                    </label>

                    <textarea name="company_description"
                              class="form-control"
                              rows="3"
                              placeholder="Jelaskan secara singkat profil perusahaan..."><?= escape($companyDescription) ?></textarea>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Website Perusahaan
                    </label>

                    <input type="url"
                           name="website"
                           class="form-control"
                           value="<?= escape($website) ?>"
                           placeholder="https://contoh.com">

                </div>


                <div class="row g-3">

                    <div class="col-md-6">

                        <div class="mb-3">

                            <label class="form-label">
                                Email Perusahaan
                            </label>

                            <input type="email"
                                   name="email_contact"
                                   class="form-control"
                                   value="<?= escape($emailContact) ?>"
                                   placeholder="kontak@perusahaan.com">

                        </div>

                    </div>

                    <div class="col-md-6">

                        <div class="mb-3">

                            <label class="form-label">
                                Nomor Telepon
                            </label>

                            <input type="text"
                                   name="phone_contact"
                                   class="form-control"
                                   maxlength="20"
                                   value="<?= escape($phoneContact) ?>"
                                   placeholder="Nomor telepon perusahaan">

                        </div>

                    </div>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Alamat Perusahaan
                    </label>

                    <textarea name="address"
                              class="form-control"
                              rows="3"
                              placeholder="Masukkan alamat lengkap perusahaan..."><?= escape($address) ?></textarea>

                </div>


                <hr class="section-divider">


                <!-- INFORMASI AKUN -->
                <div class="form-section-title">

                    <span class="form-section-icon">🔐</span>

                    <span>
                        Informasi Akun
                        <span class="section-caption d-block">
                            Informasi untuk masuk ke SIPORSI
                        </span>
                    </span>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Username
                        <span class="required-mark">*</span>
                    </label>

                    <input type="text"
                           name="username"
                           class="form-control"
                           value="<?= escape($username) ?>"
                           placeholder="Buat username akun"
                           autocomplete="username"
                           required>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Email Login
                        <span class="required-mark">*</span>
                    </label>

                    <input type="email"
                           name="email"
                           class="form-control"
                           value="<?= escape($email) ?>"
                           placeholder="email@perusahaan.com"
                           autocomplete="email"
                           required>

                    <div class="field-help">
                        Email ini akan digunakan sebagai identitas akun.
                    </div>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Password
                        <span class="required-mark">*</span>
                    </label>

                    <input type="password"
                           name="password"
                           class="form-control"
                           minlength="12"
                           placeholder="Buat password minimal 12 karakter"
                           autocomplete="new-password"
                           required>

                    <div class="field-help">
                        Password minimal 12 karakter.
                    </div>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Konfirmasi Password
                        <span class="required-mark">*</span>
                    </label>

                    <input type="password"
                           name="confirm_password"
                           class="form-control"
                           minlength="12"
                           placeholder="Masukkan kembali password"
                           autocomplete="new-password"
                           required>

                </div>


                <!-- PEMBERITAHUAN VERIFIKASI -->
                <div class="verification-notice">

                    <div class="notice-icon">ⓘ</div>

                    <div>

                        <strong>Informasi Verifikasi Akun</strong>

                        <p>
                            Setelah mendaftar, akun perusahaan akan
                            menunggu pemeriksaan dan verifikasi dari
                            Admin Sekolah. Anda dapat login setelah
                            akun disetujui.
                        </p>

                    </div>

                </div>


                <!-- TOMBOL -->
                <div class="d-flex flex-wrap gap-2">

                    <button type="submit"
                            class="btn btn-register flex-grow-1">
                        Daftar sebagai Mitra Industri →
                    </button>

                    <a href="<?= BASE_URL ?>/login.php"
                       class="btn-login">
                        Kembali ke Login
                    </a>

                </div>

            </form>


            <div class="form-copyright">
                © <?= date('Y') ?> SIPORSI
                <br>
                SMK Negeri 4 Banjarmasin
            </div>

        </section>

    </div>

</main>

</body>
</html>
