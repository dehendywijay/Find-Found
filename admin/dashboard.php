
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Proteksi halaman admin
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil statistik
$jumlah_mahasiswa = $pdo->query("
    SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'
")->fetchColumn();

$jumlah_pending = $pdo->query("
    SELECT COUNT(*) FROM users
    WHERE role = 'mahasiswa' AND status_akun = 'pending'
")->fetchColumn();

$jumlah_aktif = $pdo->query("
    SELECT COUNT(*) FROM users
    WHERE role = 'mahasiswa' AND status_akun = 'aktif'
")->fetchColumn();

$jumlah_laporan = $pdo->query("
    SELECT COUNT(*) FROM reports
")->fetchColumn();

$nama_admin = $_SESSION['nama'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - FIND & FOUND</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px;
            font-family: Arial, sans-serif;
            background: #f5f5f7;
            color: #25233a;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0 0 8px;
        }

        .subtitle {
            color: #777;
            margin: 0;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid #e8e7ed;
        }

        .card p {
            margin: 0;
            color: #777;
            font-size: 14px;
        }

        .card strong {
            display: block;
            font-size: 30px;
            margin-top: 12px;
            color: #6c45d9;
        }

        .menu {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .menu a {
            text-decoration: none;
            padding: 13px 18px;
            border-radius: 8px;
            background: #6c45d9;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .menu a.logout {
            background: #ffe3e3;
            color: #c03939;
        }

        @media (max-width: 850px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 500px) {
            body {
                padding: 18px;
            }

            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <div>
            <h1>Dashboard Admin</h1>
            <p class="subtitle">
                Selamat datang, <?= htmlspecialchars($nama_admin) ?>.
            </p>
        </div>
    </div>

    <div class="cards">

        <div class="card">
            <p>Total Mahasiswa</p>
            <strong><?= (int) $jumlah_mahasiswa ?></strong>
        </div>

        <div class="card">
            <p>Akun Menunggu Aktivasi</p>
            <strong><?= (int) $jumlah_pending ?></strong>
        </div>

        <div class="card">
            <p>Akun Aktif</p>
            <strong><?= (int) $jumlah_aktif ?></strong>
        </div>

        <div class="card">
            <p>Total Laporan</p>
            <strong><?= (int) $jumlah_laporan ?></strong>
        </div>

    </div>

    <div class="menu">
        <a href="aktivasi-akun.php">Kelola Aktivasi Akun</a>
        <a href="../auth/logout.php" class="logout">Keluar</a>
    </div>

</div>
</body>
</html>
