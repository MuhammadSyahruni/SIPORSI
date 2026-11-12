<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role_name'] ?? '';

    $destinations = [
        'admin' => BASE_URL . '/admin/dashboard.php',
        'student' => BASE_URL . '/student/dashboard.php',
        'industry' => BASE_URL . '/industry/dashboard.php',
    ];

    if (isset($destinations[$role])) {
        header('Location: ' . $destinations[$role]);
        exit;
    }
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

require_once APP_ROOT . '/app/core/Csrf.php';
$csrfToken = Csrf::token();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | SIPORSI SMK Negeri 4 Banjarmasin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --navy: #102e59;
            --blue: #1769aa;
            --gold: #f4b942;
            --light: #f4f7fc;
            --muted: #718096;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 10% 10%, #e7effb, transparent 35%),
                var(--light);
            font-family: 'Inter', sans-serif;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 18px;
        }

        .login-card {
            width: 100%;
            max-width: 1000px;
            overflow: hidden;
            border: 1px solid #e7edf5;
            border-radius: 23px;
            background: white;
            box-shadow: 0 25px 65px rgba(16, 46, 89, .13);
        }

        /* PANEL IDENTITAS */

        .login-brand-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 570px;
            overflow: hidden;
            padding: 55px 45px;
            background: linear-gradient(145deg, #102e59, #1769aa);
            color: white;
        }

        .login-brand-panel::before {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            right: -150px;
            top: -120px;
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 50%;
            box-shadow:
                0 0 0 35px rgba(255,255,255,.035),
                0 0 0 75px rgba(255,255,255,.025);
        }

        .login-brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-symbol {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 55px;
            height: 55px;
            margin-bottom: 25px;
            border-radius: 15px;
            background: var(--gold);
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .brand-title {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .brand-subtitle {
            margin-top: 15px;
            font-size: 21px;
            font-weight: 700;
            line-height: 1.5;
        }

        .brand-school {
            margin-top: 12px;
            color: rgba(255,255,255,.7);
            font-size: 13px;
        }

        .brand-divider {
            width: 55px;
            height: 4px;
            margin: 30px 0;
            border-radius: 10px;
            background: var(--gold);
        }

        .brand-description {
            max-width: 350px;
            color: rgba(255,255,255,.8);
            font-size: 14px;
            line-height: 1.9;
        }

        .brand-footer {
            position: absolute;
            bottom: 30px;
            left: 45px;
            z-index: 2;
            color: rgba(255,255,255,.55);
            font-size: 11px;
        }

        /* FORM LOGIN */

        .login-form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 55px 55px;
        }

        .login-heading {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 29px;
            font-weight: 800;
        }

        .login-description {
            margin-bottom: 35px;
            color: var(--muted);
            font-size: 13px;
        }

        .form-label {
            margin-bottom: 9px;
            color: #263b56;
            font-size: 13px;
            font-weight: 700;
        }

        .form-control {
            min-height: 50px;
            padding: 12px 15px;
            border: 1px solid #e1e8f1;
            border-radius: 9px;
            background: #f8fafd;
            font-size: 13px;
        }

        .form-control:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.10);
        }

        .login-button {
            min-height: 51px;
            margin-top: 10px;
            border: none;
            border-radius: 9px;
            background: var(--navy);
            color: white;
            font-size: 14px;
            font-weight: 700;
            transition: .2s;
        }

        .login-button:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        .login-home {
            display: inline-block;
            margin-top: 25px;
            color: var(--blue);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .login-home:hover {
            color: var(--navy);
            text-decoration: underline;
        }

        .login-copyright {
            margin-top: 35px;
            color: #94a0b1;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 767px) {
            .login-wrapper {
                padding: 20px 13px;
            }

            .login-card {
                border-radius: 17px;
            }

            .login-brand-panel {
                min-height: auto;
                padding: 35px 27px;
            }

            .brand-title {
                font-size: 29px;
            }

            .brand-subtitle {
                font-size: 18px;
            }

            .brand-divider {
                margin: 20px 0;
            }

            .brand-footer {
                position: static;
                margin-top: 25px;
            }

            .login-form-panel {
                padding: 35px 27px;
            }

            .login-heading {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <div class="row g-0">

            <!-- IDENTITAS SIPORSI -->
            <div class="col-md-6">

                <div class="login-brand-panel">

                    <div class="login-brand-content">

                        <div class="brand-symbol">
                            S
                        </div>

                        <h1 class="brand-title">
                            SIPORSI
                        </h1>

                        <h2 class="brand-subtitle">
                            Sistem Informasi Portofolio Digital Siswa
                        </h2>

                        <p class="brand-school">
                            SMK Negeri 4 Banjarmasin
                        </p>

                        <div class="brand-divider"></div>

                        <p class="brand-description">
                            Tampilkan karya, keterampilan, pengalaman,
                            sertifikat, dan prestasi siswa dalam satu
                            platform digital untuk memperkenalkan
                            kompetensi kepada dunia industri.
                        </p>

                    </div>

                    <div class="brand-footer">
                        Platform Portofolio Digital Siswa SMK
                    </div>

                </div>

            </div>


            <!-- FORM LOGIN -->
            <div class="col-md-6">

                <div class="login-form-panel">

                    <h2 class="login-heading">
                        Selamat Datang
                    </h2>

                    <p class="login-description">
                        Silakan masuk menggunakan akun SIPORSI Anda.
                    </p>

                    <?php if ($error): ?>

                        <div class="alert alert-danger rounded-3 small">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>

                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/auth.php"
                          method="POST">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-4">

                            <label class="form-label">
                                Username
                            </label>

                            <input type="text"
                                   name="username"
                                   class="form-control"
                                   placeholder="Masukkan username"
                                   autocomplete="username"
                                   required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Password
                            </label>

                            <input type="password"
                                   name="password"
                                   class="form-control"
                                   placeholder="Masukkan password"
                                   autocomplete="current-password"
                                   required>

                        </div>

                        <button type="submit"
                                class="btn login-button w-100">
                            Masuk ke SIPORSI &nbsp; →
                        </button>

                    </form>

                    <div class="text-center">

                        <a href="<?= BASE_URL ?>/"
                           class="login-home">
                            ← Kembali ke Beranda
                        </a>

                    </div>

                    <div class="login-copyright">
                        © <?= date('Y') ?> SIPORSI
                        <br>
                        SMK Negeri 4 Banjarmasin
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
