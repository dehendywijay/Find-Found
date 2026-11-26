
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// ==========================================
// 1. PROTEKSI LOGIN ADMIN
// ==========================================

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

// ==========================================
// 2. STATISTIK MAHASISWA
// ==========================================

$jumlah_mahasiswa = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'mahasiswa'
")->fetchColumn();

$jumlah_aktif = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'mahasiswa'
    AND status_akun = 'aktif'
")->fetchColumn();

$jumlah_nonaktif = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'mahasiswa'
    AND status_akun = 'nonaktif'
")->fetchColumn();

// ==========================================
// 3. STATISTIK LAPORAN
// ==========================================

$jumlah_laporan = $pdo->query("
    SELECT COUNT(*)
    FROM reports
")->fetchColumn();

$laporan_aktif = $pdo->query("
    SELECT COUNT(*)
    FROM reports
    WHERE status = 'aktif'
")->fetchColumn();

$laporan_ditolak = $pdo->query("
    SELECT COUNT(*)
    FROM reports
    WHERE status = 'ditolak'
")->fetchColumn();

$laporan_selesai = $pdo->query("
    SELECT COUNT(*)
    FROM reports
    WHERE status = 'selesai'
")->fetchColumn();

$nama_admin = $_SESSION['nama'] ?? 'Administrator';

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 35px;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #25233a;
        }

        .container {
            max-width: 1150px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .subtitle {
            color: #777;
            margin: 0;
        }

        .brand {
            color: #6246d9;
            font-weight: bold;
            font-size: 17px;
        }

        .section-title {
            margin: 30px 0 16px;
            font-size: 19px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 13px;
            border: 1px solid #e8e7ed;
            box-shadow: 0 3px 12px rgba(0,0,0,0.02);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .card p {
            margin: 0;
            color: #777;
            font-size: 14px;
        }

        .card strong {
            display: block;
            font-size: 30px;
            margin-top: 10px;
            color: #6246d9;
        }

        .icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f0edff;
            color: #6246d9;
            font-size: 18px;
        }

        .success .icon {
            background: #e5f8ed;
            color: #20a464;
        }

        .danger .icon {
            background: #ffe8e8;
            color: #df4d4d;
        }

        .secondary .icon {
            background: #eeeeF3;
            color: #777;
        }

        /* MENU PENGELOLAAN */

        .menu {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 20px;
        }

        .menu a {
            text-decoration: none;
            padding: 20px;
            border-radius: 12px;
            background: white;
            border: 1px solid #e8e7ed;
            color: #25233a;
            transition: 0.2s;
        }

        .menu a:hover {
            border-color: #6246d9;
            transform: translateY(-2px);
        }

        .menu i {
            display: block;
            font-size: 23px;
            color: #6246d9;
            margin-bottom: 13px;
        }

        .menu strong {
            display: block;
            margin-bottom: 7px;
        }

        .menu small {
            color: #888;
            line-height: 1.5;
        }

        .menu a.logout {
            background: #fff0f0;
            color: #c03939;
        }

        .menu a.logout i {
            color: #c03939;
        }

        @media (max-width: 850px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .menu {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 500px) {
            body {
                padding: 18px;
            }

            .cards,
            .menu {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 23px;
            }
        }
    </style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>
            <h1>Dashboard Admin</h1>

            <p class="subtitle">
                Selamat datang,
                <?= htmlspecialchars($nama_admin, ENT_QUOTES, 'UTF-8') ?>.
                Kelola aktivitas FIND & FOUND di sini.
            </p>
        </div>

        <div class="brand">
            <i class="fa-solid fa-location-dot"></i>
            FIND & FOUND
        </div>

    </div>


    <!-- STATISTIK MAHASISWA -->

    <h2 class="section-title">Statistik Pengguna</h2>

    <div class="cards">

        <div class="card">
            <div class="card-top">
                <p>Total Mahasiswa</p>
                <div class="icon">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <strong><?= (int)$jumlah_mahasiswa ?></strong>
        </div>

        <div class="card success">
            <div class="card-top">
                <p>Akun Aktif</p>
                <div class="icon">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
            <strong><?= (int)$jumlah_aktif ?></strong>
        </div>

        <div class="card danger">
            <div class="card-top">
                <p>Akun Nonaktif</p>
                <div class="icon">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
            </div>
            <strong><?= (int)$jumlah_nonaktif ?></strong>
        </div>

    </div>


    <!-- STATISTIK LAPORAN -->

    <h2 class="section-title">Statistik Laporan Barang</h2>

    <div class="cards">

        <div class="card">
            <div class="card-top">
                <p>Total Laporan</p>
                <div class="icon">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
            </div>
            <strong><?= (int)$jumlah_laporan ?></strong>
        </div>

        <div class="card success">
            <div class="card-top">
                <p>Laporan Aktif</p>
                <div class="icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <strong><?= (int)$laporan_aktif ?></strong>
        </div>

        <div class="card danger">
            <div class="card-top">
                <p>Laporan Ditolak</p>
                <div class="icon">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <strong><?= (int)$laporan_ditolak ?></strong>
        </div>

        <div class="card secondary">
            <div class="card-top">
                <p>Laporan Selesai</p>
                <div class="icon">
                    <i class="fa-solid fa-flag-checkered"></i>
                </div>
            </div>
            <strong><?= (int)$laporan_selesai ?></strong>
        </div>

    </div>


    <!-- MENU PENGELOLAAN -->

    <h2 class="section-title">Menu Pengelolaan</h2>

    <div class="menu">

        <!-- KELOLA AKUN MAHASISWA -->

        <a href="aktivasi-akun.php">
            <i class="fa-solid fa-users-gear"></i>

            <strong>Kelola Akun Mahasiswa</strong>

            <small>
                Lihat daftar akun mahasiswa serta ubah status akun jika diperlukan.
            </small>
        </a>


        <!-- PEMANTAUAN LAPORAN -->

        <a href="laporan-masuk.php">
            <i class="fa-solid fa-inbox"></i>

            <strong>Pemantauan Laporan</strong>

            <small>
                Pantau seluruh laporan kehilangan dan penemuan yang dikirimkan mahasiswa.
            </small>
        </a>


        <!-- KLAIM BARANG -->

        <a href="laporan-klaim-barang.php">
            <i class="fa-solid fa-box-open"></i>

            <strong>Laporan Klaim Barang</strong>

            <small>
                Periksa pengajuan klaim kepemilikan barang yang ditemukan.
            </small>
        </a>


        <!-- KLAIM PENEMUAN -->

        <a href="laporan-klaim-penemuan.php">
            <i class="fa-solid fa-magnifying-glass-location"></i>

            <strong>Laporan Klaim Penemuan</strong>

            <small>
                Periksa laporan mahasiswa yang menemukan barang dari laporan kehilangan.
            </small>
        </a>


        <!-- KELUAR -->

        <a href="../auth/logout.php" class="logout">
            <i class="fa-solid fa-right-from-bracket"></i>

            <strong>Keluar</strong>

            <small>
                Keluar dari akun administrator.
            </small>
        </a>

    </div>

</div>

</body>
</html>
