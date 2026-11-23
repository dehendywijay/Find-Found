<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Proteksi halaman mahasiswa
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

// Ambil kategori
$stmtKategori = $pdo->query("
    SELECT id, nama_kategori
    FROM categories
    ORDER BY FIELD(
        nama_kategori,
        'Dompet',
        'Ponsel',
        'Laptop',
        'Kartu mahasiswa',
        'Kunci'
    ), nama_kategori ASC
    LIMIT 5
");

$kategori = $stmtKategori->fetchAll(PDO::FETCH_ASSOC);

// Ambil 4 laporan terbaru yang sudah aktif
$stmtLaporan = $pdo->query("
    SELECT
        r.id,
        r.kode_laporan,
        r.jenis_laporan,
        r.nama_barang,
        r.tanggal_kejadian,
        c.nama_kategori,
        l.nama_lokasi,
        (
            SELECT ri.nama_file
            FROM report_images ri
            WHERE ri.report_id = r.id
            ORDER BY ri.urutan ASC, ri.id ASC
            LIMIT 1
        ) AS foto
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN locations l ON l.id = r.location_id
    WHERE r.status = 'aktif'
    ORDER BY r.created_at DESC
    LIMIT 4
");

$laporan = $stmtLaporan->fetchAll(PDO::FETCH_ASSOC);

function tanggalIndonesia($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    $bulan = [
        1 => 'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des'
    ];

    $waktu = strtotime($tanggal);

    return date('d', $waktu) . ' ' .
        $bulan[(int) date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Beranda - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="../assets/css/mahasiswa-dashboard.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

    <div class="page-wrapper">


        <header class="navbar">
            <a href="dashboard.php" class="brand">
                <i class="fa-solid fa-location-dot"></i>
                <span>FIND & FOUND</span>
            </a>

            <nav class="nav-menu">
                <a href="dashboard.php" class="active">Beranda</a>
                <a href="cari-barang.php">Cari barang</a>
                <a href="status.php">Status</a>
            </nav>

            <div class="navbar-profile">
                <i class="fa-regular fa-user"></i>
                <span><?= e($nama_mahasiswa) ?></span>
            </div>
        </header>
        
        <main class="main-content">

            <!-- BAGIAN HERO -->
            <section class="hero">

                <div class="hero-left">

                    <h1>
                        Barang hilang atau menemukan
                        barang di kampus?
                    </h1>

                    <p class="hero-description">
                        Semua laporan barang hilang dan temuan kampus ada di satu tempat.
                        Cari, lapor, dan klaim dengan aman.
                    </p>

                    <form action="cari-barang.php" method="GET" class="search-form">
                        <div class="search-input">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input
                                type="text"
                                name="q"
                                placeholder="Cari dompet, kunci, laptop...">
                        </div>

                        <button type="submit">Cari</button>
                    </form>

                    <div class="category-list">
                        <?php foreach ($kategori as $item): ?>
                            <a href="cari-barang.php?kategori=<?= (int) $item['id'] ?>">
                                <?= e($item['nama_kategori']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="report-buttons">
                        <a href="lapor-kehilangan.php">
                            Lapor kehilangan
                        </a>

                        <a href="lapor-penemuan.php">
                            Lapor penemuan
                        </a>
                    </div>

                </div>

                <div class="hero-right">
                    <!-- Area gambar utama sesuai frame Figma -->
                </div>

            </section>


            <!-- CARA KERJA -->
            <section class="how-section">

                <h2>Cara kerja FIND & FOUND</h2>

                <div class="how-grid">

                    <div class="how-card">
                        <div class="step-number">1</div>

                        <h3>Lapor atau cari barang</h3>

                        <p>
                            Isi formulir singkat atau cari lewat kata kunci dan filter.
                        </p>
                    </div>

                    <div class="how-card">
                        <div class="step-number">2</div>

                        <h3>Ajukan klaim</h3>

                        <p>
                            Jawab pertanyaan verifikasi dan unggah bukti kepemilikan.
                        </p>
                    </div>

                    <div class="how-card">
                        <div class="step-number">3</div>

                        <h3>Ambil barang</h3>

                        <p>
                            Setelah disetujui admin, ambil barang sesuai jadwal yang kamu pilih.
                        </p>
                    </div>

                </div>

            </section>

            <!-- LAPORAN TERBARU -->
            <section class="latest-section">

                <div class="section-heading">
                    <h2>Laporan terbaru</h2>

                    <a href="cari-barang.php">Lihat semua</a>
                </div>

                <div class="report-grid">

                    <?php if (count($laporan) > 0): ?>

                        <?php foreach ($laporan as $item): ?>

                            <a
                                href="detail-barang.php?id=<?= (int) $item['id'] ?>"
                                class="report-card">

                                <div class="report-image">

                                    <?php if (!empty($item['foto'])): ?>

                                        <img
                                            src="../uploads/laporan/<?= rawurlencode(basename($item['foto'])) ?>"
                                            alt="<?= e($item['nama_barang']) ?>"
                                            onerror="this.style.display='none';">

                                    <?php endif; ?>

                                </div>

                                <div class="report-info">

                                    <?php if ($item['jenis_laporan'] === 'kehilangan'): ?>

                                        <span class="report-status status-lost">
                                            Hilang
                                        </span>

                                    <?php else: ?>

                                        <span class="report-status status-found">
                                            Ditemukan
                                        </span>

                                    <?php endif; ?>

                                    <h3><?= e($item['nama_barang']) ?></h3>

                                    <div class="report-location">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <span><?= e($item['nama_lokasi'] ?? 'Lokasi belum tersedia') ?></span>
                                    </div>

                                    <div class="report-date">
                                        <?= e(tanggalIndonesia($item['tanggal_kejadian'])) ?>
                                    </div>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-report">
                            <i class="fa-regular fa-folder-open"></i>
                            <p>Belum ada laporan barang terbaru.</p>
                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </main>

    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const dropdown = document.getElementById('statusDropdown');
            const button = document.getElementById('statusButton');

            if (!dropdown || !button) return;

            button.addEventListener('click', function(event) {
                event.stopPropagation();
                dropdown.classList.toggle('active');
            });

            document.addEventListener('click', function(event) {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove('active');
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    dropdown.classList.remove('active');
                }
            });

        });
    </script>

</body>

</html>