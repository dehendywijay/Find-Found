
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - FIND & FOUND</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
            <h1>Barang hilang di kampus?<br>
                Temukan lagi di sini.</h1>

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

            <form action="" method="POST">

                <div class="form-group">
                    <label for="email">Email kampus</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="nama@bsi.ac.id"
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

                        <button type="button"
                                class="toggle-password"
                                id="togglePassword"
                                aria-label="Tampilkan kata sandi">
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
