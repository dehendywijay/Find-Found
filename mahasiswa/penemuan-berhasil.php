
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Proteksi login
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

// Pastikan berasal dari proses laporan yang berhasil
$reportId = $_SESSION['laporan_berhasil_id'] ?? null;

if (!$reportId) {
    header('Location: dashboard.php');
    exit;
}

// Ambil laporan penemuan milik mahasiswa yang sedang login
$stmt = $pdo->prepare("
    SELECT
        id,
        kode_laporan,
        jenis_laporan,
        nama_barang,
        status
    FROM reports
    WHERE id = ?
      AND user_id = ?
      AND jenis_laporan = 'penemuan'
    LIMIT 1
");

$stmt->execute([
    $reportId,
    $_SESSION['user_id']
]);

$laporan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$laporan) {
    unset($_SESSION['laporan_berhasil_id']);
    header('Location: dashboard.php');
    exit;
}

// Hapus penanda agar halaman sukses tidak bisa
// dibuka kembali sebagai laporan baru
unset($_SESSION['laporan_berhasil_id']);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$namaMahasiswa = $_SESSION['nama']
    ?? $_SESSION['nama_lengkap']
    ?? 'Mahasiswa';

$statusLabel = [
    'menunggu_verifikasi' => 'Menunggu verifikasi',
    'aktif' => 'Aktif',
    'ditolak' => 'Ditolak',
    'selesai' => 'Selesai'
];

$status = $statusLabel[$laporan['status']]
    ?? ucfirst(str_replace('_', ' ', $laporan['status']));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Penemuan Berhasil - FIND & FOUND</title>

    <link rel="stylesheet"
          href="../assets/css/laporan-berhasil.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">

    <a href="dashboard.php" class="brand">
        <i class="fa-solid fa-location-dot"></i>
        FIND & FOUND
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="active">Beranda</a>
        <a href="cari-barang.php">Cari barang</a>
        <a href="status-laporan.php">Status laporan</a>
    </div>

    <div class="profile">
        <i class="fa-regular fa-user"></i>
        <span><?= e($namaMahasiswa) ?></span>
    </div>

</nav>

<!-- SUCCESS CONTENT -->
<main class="success-container">

    <div class="success-icon">
        <i class="fa-solid fa-check"></i>
    </div>

    <h1>Laporan penemuan berhasil dikirim</h1>

    <p class="success-description">
        Laporan penemuanmu berhasil dikirim dan sedang
        menunggu verifikasi admin. Setelah disetujui,
        informasi barang ditemukan akan tersedia bagi
        pengguna yang sedang mencari barang tersebut.
    </p>

    <!-- REPORT INFORMATION -->
    <section class="report-card">

        <span class="card-label">KODE LAPORAN</span>

        <h2><?= e($laporan['kode_laporan']) ?></h2>

        <div class="card-divider"></div>

        <div class="report-detail">

            <span>Jenis laporan</span>

            <strong>Laporan penemuan</strong>

        </div>

        <div class="report-detail">

            <span>Barang</span>

            <strong><?= e($laporan['nama_barang']) ?></strong>

        </div>

        <div class="report-detail">

            <span>Status</span>

            <span class="status-badge status-<?= e($laporan['status']) ?>">
                <?= e($status) ?>
            </span>

        </div>

    </section>

    <!-- ACTION BUTTONS -->
    <div class="success-actions">

        <a href="cari-barang.php" class="btn-primary">
            Lihat di katalog
        </a>

        <a href="dashboard.php" class="btn-secondary">
            Kembali ke beranda
        </a>

    </div>

</main>

</body>
</html>
