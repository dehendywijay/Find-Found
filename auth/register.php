
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';
$nama = '';
$nim = '';
$email = '';
$berhasil = false;

// Token keamanan form
if (empty($_SESSION['csrf_register'])) {
    $_SESSION['csrf_register'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_register'], $csrf)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';

    } elseif ($nama === '' || $nim === '' || $email === '' || $password === '' || $konfirmasi === '') {
        $error = 'Semua kolom wajib diisi.';

    } elseif (strlen($nama) > 100) {
        $error = 'Nama maksimal 100 karakter.';

    } elseif (strlen($nim) > 30) {
        $error = 'NIM maksimal 30 karakter.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';

    } elseif (!str_ends_with($email, '@bsi.ac.id')) {
        $error = 'Pendaftaran hanya menggunakan email kampus @bsi.ac.id.';

    } elseif (strlen($password) < 8) {
        $error = 'Kata sandi minimal 8 karakter.';

    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi kata sandi tidak cocok.';

    } else {
        try {
            // Memeriksa email dan NIM yang sudah terdaftar
            $cek = $pdo->prepare("
                SELECT email, nim
                FROM users
                WHERE email = :email OR nim = :nim
                LIMIT 1
            ");

            $cek->execute([
                ':email' => $email,
                ':nim' => $nim
            ]);

            $duplikat = $cek->fetch();

            if ($duplikat) {
                if ($duplikat['email'] === $email) {
                    $error = 'Email tersebut sudah terdaftar.';
                } elseif ($duplikat['nim'] === $nim) {
                    $error = 'NIM tersebut sudah terdaftar.';
                }
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare("
                    INSERT INTO users (
                        nama,
                        nim,
                        email,
                        password,
                        role,
                        status_akun
                    ) VALUES (
                        :nama,
                        :nim,
                        :email,
                        :password,
                        'mahasiswa',
                        'aktif'
                    )
                ");

                $insert->execute([
                    ':nama' => $nama,
                    ':nim' => $nim,
                    ':email' => $email,
                    ':password' => $passwordHash
                ]);

                unset($_SESSION['csrf_register']);

                $berhasil = true;
            }

        } catch (PDOException $e) {
            error_log('Register error: ' . $e->getMessage());
            $error = 'Terjadi kesalahan pada server. Silakan coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - FIND & FOUND</title>

    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .register-card {
            max-width: 380px;
        }

        .register-card .subtitle {
            margin-bottom: 18px;
        }

        .register-card .form-group {
            margin-bottom: 13px;
        }

        .register-card .form-group input {
            height: 36px;
        }

        .register-card .btn-login {
            margin-top: 4px;
        }

        .register-alert {
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 11px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .register-error {
            background: #fff0f0;
            border: 1px solid #ffcaca;
            color: #c62828;
        }

        .register-success {
            background: #effaf2;
            border: 1px solid #c8ebd0;
            color: #24743b;
        }

        .register-success p {
            margin: 10px 0 18px;
            font-size: 12px;
            line-height: 1.6;
        }

        .register-success a {
            display: block;
            text-decoration: none;
            text-align: center;
            background: #5145df;
            color: white;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>

<body>

<div class="login-container">

    <!-- PANEL KIRI -->
    <section class="welcome-panel">

        <div class="brand">
            <i class="fa-solid fa-location-dot"></i>
            FIND & FOUND
        </div>

        <div class="welcome-content">
            <h1>
                Barang hilang di kampus?<br>
                Temukan lagi di sini.
            </h1>

            <p>
                Satu tempat untuk melaporkan, mencari, dan
                mengembalikan barang hilang maupun temuan
                di lingkungan kampus.
            </p>
        </div>

    </section>

    <!-- PANEL KANAN -->
    <section class="form-panel">

        <div class="login-card register-card">

            <?php if ($berhasil): ?>

                <h2>Pendaftaran berhasil!</h2>

                <div class="register-alert register-success">
                    <i class="fa-solid fa-circle-check"></i>
                    Akun berhasil dibuat dan sudah aktif.
                </div>

                <p>
                    Pendaftaran akun kamu berhasil.
                    Sekarang kamu sudah bisa masuk menggunakan
                    email kampus dan kata sandi yang telah didaftarkan.
                </p>

                <a href="login.php" style="
                    display:block;
                    text-align:center;
                    text-decoration:none;
                    background:#5145df;
                    color:white;
                    padding:12px;
                    border-radius:6px;
                    font-size:12px;
                    font-weight:600;
                ">
                    Masuk ke akun
                </a>

            <?php else: ?>

                <h2>Daftar</h2>

                <p class="subtitle">
                    Buat akun menggunakan identitas mahasiswa.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="register-alert register-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_register'], ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="form-group">
                        <label for="nama">Nama lengkap</label>
                        <input
                            type="text"
                            id="nama"
                            name="nama"
                            placeholder="Masukkan nama lengkap"
                            value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="nim">NIM</label>
                        <input
                            type="text"
                            id="nim"
                            name="nim"
                            placeholder="Masukkan NIM"
                            value="<?= htmlspecialchars($nim, ENT_QUOTES, 'UTF-8'); ?>"
                            maxlength="30"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email kampus</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="nama@bsi.ac.id"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Kata sandi</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Minimal 8 karakter"
                            minlength="8"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="konfirmasi_password">Konfirmasi kata sandi</label>
                        <input
                            type="password"
                            id="konfirmasi_password"
                            name="konfirmasi_password"
                            placeholder="Ulangi kata sandi"
                            minlength="8"
                            required
                        >
                    </div>

                    <button type="submit" class="btn-login">
                        Daftar
                    </button>

                </form>

                <div class="register-link">
                    Sudah punya akun?
                    <a href="login.php">Masuk</a>
                </div>

            <?php endif; ?>

        </div>

    </section>

</div>

</body>
</html>
