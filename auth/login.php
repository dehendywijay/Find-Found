
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';
$emailInput = '';

// Membuat token keamanan form
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $emailInput = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validasi token
    if (!hash_equals($_SESSION['csrf_login'], $csrfToken)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';

    // Validasi email
    } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';

    } elseif (!str_ends_with($emailInput, '@bsi.ac.id')) {
        $error = 'Gunakan email kampus dengan domain @bsi.ac.id.';

    } elseif (empty($password)) {
        $error = 'Kata sandi wajib diisi.';

    } else {
        try {
            // Mencari akun berdasarkan email
            $query = $pdo->prepare("
                SELECT
                    id,
                    nama,
                    email,
                    password,
                    role,
                    status_akun
                FROM users
                WHERE email = :email
                LIMIT 1
            ");

            $query->execute([
                ':email' => $emailInput
            ]);

            $user = $query->fetch();

            // Memeriksa akun dan password
            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Email atau kata sandi salah.';

            } elseif ($user['status_akun'] === 'pending') {
                $error = 'Akun kamu belum diaktifkan oleh admin.';

            } elseif ($user['status_akun'] !== 'aktif') {
                $error = 'Akun kamu sedang tidak aktif. Hubungi admin.';

            } else {
                // Membuat session baru
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Membuat token login baru
                unset($_SESSION['csrf_login']);

                // Mengarahkan pengguna berdasarkan role
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php');
                    exit;

                } elseif ($user['role'] === 'mahasiswa') {
                    header('Location: ../mahasiswa/dashboard.php');
                    exit;

                } else {
                    session_unset();
                    session_destroy();

                    $error = 'Hak akses akun tidak dikenali.';
                }
            }

        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
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
    <title>Masuk - FIND & FOUND</title>

    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .login-alert {
            background: #fff0f0;
            border: 1px solid #ffcaca;
            color: #c62828;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 11px;
            margin-bottom: 16px;
            line-height: 1.5;
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

        <div class="login-card">

            <h2>Masuk</h2>

            <p class="subtitle">
                Gunakan email kampus dan kata sandi akunmu.
            </p>

            <?php if (!empty($error)): ?>
                <div class="login-alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_login'], ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div class="form-group">
                    <label for="email">Email kampus</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="nama@bsi.ac.id"
                        value="<?= htmlspecialchars($emailInput, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Kata sandi</label>

                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan kata sandi"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-label="Tampilkan kata sandi"
                        >
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="forgot-password">
                    <a href="lupa-password.php">
                        Lupa kata sandi?
                    </a>
                </div>

                <button type="submit" class="btn-login">
                    Masuk
                </button>

            </form>

            <div class="register-link">
                Belum punya akun?
                <a href="register.php">Daftar</a>
            </div>

        </div>

    </section>

</div>

<script>
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function () {

    const visible = passwordInput.type === 'password';

    passwordInput.type = visible ? 'text' : 'password';

    this.innerHTML = visible
        ? '<i class="fa-regular fa-eye-slash"></i>'
        : '<i class="fa-regular fa-eye"></i>';

});
</script>

</body>
</html>
