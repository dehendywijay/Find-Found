
<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$nama_mahasiswa = $_SESSION['nama'] ?? 'Mahasiswa';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Status Saya - FIND & FOUND</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            font-size: 14px;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .navbar {
            min-height: 70px;
            padding: 0 4.5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4f46e5;
            font-size: 18px;
            font-weight: 700;
        }

        .brand i {
            font-size: 20px;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-menu a {
            color: #475569;
            font-size: 14px;
            transition: .2s;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            color: #4f46e5;
        }

        .nav-menu a.active {
            font-weight: 600;
        }

        .navbar-profile {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #334155;
            font-weight: 600;
            white-space: nowrap;
        }

        .navbar-profile i {
            color: #64748b;
            font-size: 17px;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 55px 25px 70px;
        }

        .page-heading {
            margin-bottom: 35px;
        }

        .page-heading h1 {
            font-size: 30px;
            font-weight: 700;
            color: #0f172a;
        }

        .page-heading p {
            margin-top: 12px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .status-card {
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 28px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            transition: .2s ease;
        }

        .status-card:hover {
            border-color: #a5a0f5;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            gap: 18px;
        }

        .card-icon {
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 23px;
        }

        .card-info h2 {
            margin-top: 3px;
            font-size: 18px;
            line-height: 1.4;
            color: #0f172a;
        }

        .card-info p {
            margin-top: 9px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        .card-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 25px;
            padding-top: 17px;
            border-top: 1px solid #f1f5f9;
            color: #4f46e5;
            font-size: 13px;
            font-weight: 600;
        }

        .card-link i {
            transition: transform .2s;
        }

        .status-card:hover .card-link i {
            transform: translateX(5px);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-top: 32px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

        .back-link:hover {
            color: #4f46e5;
        }

        @media (max-width: 700px) {
            .navbar {
                padding: 15px 20px;
                flex-wrap: wrap;
                gap: 15px;
            }

            .nav-menu {
                order: 3;
                width: 100%;
                gap: 20px;
            }

            .main-content {
                padding: 35px 20px 50px;
            }

            .page-heading h1 {
                font-size: 25px;
            }

            .status-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .status-card {
                min-height: 190px;
                padding: 22px;
            }

            .card-info h2 {
                font-size: 16px;
            }

            .navbar-profile {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">

    <a href="dashboard.php" class="brand">
        <i class="fa-solid fa-location-dot"></i>
        <span>FIND & FOUND</span>
    </a>

    <nav class="nav-menu">
        <a href="dashboard.php">Beranda</a>
        <a href="cari-barang.php">Cari barang</a>
        <a href="status.php" class="active">Status</a>
    </nav>

    <div class="navbar-profile">
        <i class="fa-regular fa-user"></i>
        <span><?= e($nama_mahasiswa) ?></span>
    </div>

</header>

<main class="main-content">

    <div class="page-heading">
        <h1>Status Aktivitas Saya</h1>
        <p>
            Pantau perkembangan laporan dan klaim barang yang kamu ajukan
            melalui FIND & FOUND.
        </p>
    </div>

    <div class="status-grid">

        <!-- STATUS LAPORAN -->
        <a href="status-laporan.php" class="status-card">

            <div class="card-top">

                <div class="card-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>

                <div class="card-info">
                    <h2>Status Laporan</h2>
                    <p>
                        Pantau perkembangan seluruh laporan kehilangan
                        dan penemuan barang yang kamu ajukan.
                    </p>
                </div>

            </div>

            <div class="card-link">
                <span>Lihat status laporan</span>
                <i class="fa-solid fa-arrow-right"></i>
            </div>

        </a>

        <!-- STATUS KLAIM PENEMUAN -->
        <a href="status-klaim-penemuan.php" class="status-card">

            <div class="card-top">

                <div class="card-icon">
                    <i class="fa-solid fa-hand-holding"></i>
                </div>

                <div class="card-info">
                    <h2>Status Klaim Penemuan</h2>
                    <p>
                        Pantau pengajuan ketika kamu menemukan barang
                        milik orang lain.
                    </p>
                </div>

            </div>

            <div class="card-link">
                <span>Lihat status klaim penemuan</span>
                <i class="fa-solid fa-arrow-right"></i>
            </div>

        </a>

        <!-- STATUS KLAIM BARANG -->
        <a href="status-klaim-barang.php" class="status-card">

            <div class="card-top">

                <div class="card-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>

                <div class="card-info">
                    <h2>Status Klaim Barang</h2>
                    <p>
                        Pantau pengajuan klaim untuk mengambil kembali
                        barang milikmu yang ditemukan.
                    </p>
                </div>

            </div>

            <div class="card-link">
                <span>Lihat status klaim barang</span>
                <i class="fa-solid fa-arrow-right"></i>
            </div>

        </a>

    </div>

    <a href="dashboard.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Kembali ke beranda
    </a>

</main>

</body>
</html>
