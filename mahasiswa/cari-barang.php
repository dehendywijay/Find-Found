
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// ==========================================
// 1. PROTEKSI LOGIN MAHASISWA
// ==========================================
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

// ==========================================
// 2. AMBIL FILTER PENCARIAN
// ==========================================
$keyword = trim($_GET['keyword'] ?? '');
$jenis = trim($_GET['jenis'] ?? '');
$kategori = filter_input(INPUT_GET, 'kategori', FILTER_VALIDATE_INT) ?: '';
$lokasi = filter_input(INPUT_GET, 'lokasi', FILTER_VALIDATE_INT) ?: '';

$jenisValid = ['kehilangan', 'penemuan'];

if (!in_array($jenis, $jenisValid, true)) {
    $jenis = '';
}

// ==========================================
// 3. AMBIL DATA KATEGORI DAN LOKASI
// ==========================================
$stmtKategori = $pdo->query("
    SELECT id, nama_kategori
    FROM categories
    ORDER BY nama_kategori ASC
");

$daftarKategori = $stmtKategori->fetchAll(PDO::FETCH_ASSOC);

$stmtLokasi = $pdo->query("
    SELECT id, nama_lokasi
    FROM locations
    ORDER BY nama_lokasi ASC
");

$daftarLokasi = $stmtLokasi->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 4. QUERY PENCARIAN BARANG
// ==========================================
$sql = "
    SELECT
        r.id,
        r.kode_laporan,
        r.jenis_laporan,
        r.nama_barang,
        r.tanggal_kejadian,
        r.detail_lokasi,
        r.deskripsi,
        r.created_at,
        c.nama_kategori,
        l.nama_lokasi,
        (
            SELECT ri.nama_file
            FROM report_images ri
            WHERE ri.report_id = r.id
            ORDER BY ri.urutan ASC
            LIMIT 1
        ) AS foto_utama
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN locations l ON l.id = r.location_id
    WHERE r.status = 'aktif'
";

$params = [];

// Pencarian nama barang dan deskripsi
if ($keyword !== '') {
    $sql .= "
        AND (
            r.nama_barang LIKE :keyword1
            OR r.deskripsi LIKE :keyword2
            OR r.kode_laporan LIKE :keyword3
        )
    ";

    $params[':keyword1'] = '%' . $keyword . '%';
    $params[':keyword2'] = '%' . $keyword . '%';
    $params[':keyword3'] = '%' . $keyword . '%';
}

// Filter jenis laporan
if ($jenis !== '') {
    $sql .= " AND r.jenis_laporan = :jenis";
    $params[':jenis'] = $jenis;
}

// Filter kategori
if ($kategori !== '') {
    $sql .= " AND r.category_id = :kategori";
    $params[':kategori'] = $kategori;
}

// Filter lokasi
if ($lokasi !== '') {
    $sql .= " AND r.location_id = :lokasi";
    $params[':lokasi'] = $lokasi;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$barangList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 5. HELPER
// ==========================================
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function tanggalIndonesia($tanggal) {
    if (!$tanggal) {
        return '-';
    }

    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $waktu = strtotime($tanggal);

    return date('d', $waktu) . ' ' .
        $bulan[(int)date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cari Barang - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f8fafc;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
        }

        .page-container {
            max-width: 1200px;
            margin: auto;
            padding: 30px 24px 50px;
        }

        /* HEADER */
        .page-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header {
            margin-bottom: 0;
        }

        .page-header h1 {
            font-size: 27px;
            margin: 0 0 9px;
        }

        .page-header p {
            font-size: 14px;
            color: #64748b;
            margin: 0;
            line-height: 1.6;
        }

        /* TOMBOL KEMBALI */
        .back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            flex-shrink: 0;
            padding: 11px 16px;
            color: #475569;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .back-link:hover {
            color: #6246d9;
            border-color: #c4b5fd;
            background: #f5f3ff;
        }

        /* FILTER */
        .filter-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 28px;
        }

        .search-box {
            position: relative;
            margin-bottom: 17px;
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            width: 100%;
            height: 48px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0 15px 0 44px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        .search-box input:focus,
        .filter-group select:focus {
            border-color: #6246d9;
            box-shadow: 0 0 0 3px rgba(98, 70, 217, .08);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 13px;
        }

        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }

        .filter-group select {
            width: 100%;
            height: 44px;
            padding: 0 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            outline: none;
            font-size: 13px;
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 17px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 17px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .btn-search {
            background: #6246d9;
            color: #fff;
        }

        .btn-search:hover {
            background: #5035c0;
        }

        .btn-reset {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-reset:hover {
            background: #e2e8f0;
        }

        /* HASIL PENCARIAN */
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 17px;
        }

        .result-header h2 {
            font-size: 19px;
            margin: 0;
        }

        .result-count {
            font-size: 13px;
            color: #64748b;
        }

        .barang-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            align-items: start;
        }

        .barang-card {
            display: block;
            min-width: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: .2s;
        }

        .barang-card:hover {
            transform: translateY(-3px);
            border-color: #c4b5fd;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .07);
        }

        /* FOTO BARANG - UKURAN SERAGAM */
        .barang-photo {
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .barang-photo img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #94a3b8;
            font-size: 13px;
        }

        .photo-placeholder i {
            font-size: 35px;
        }

        .barang-content {
            padding: 17px;
        }

        .barang-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 11px;
        }

        .badge-kehilangan {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-penemuan {
            background: #dcfce7;
            color: #15803d;
        }

        .barang-content h3 {
            font-size: 16px;
            margin: 0 0 8px;
            color: #172033;
            line-height: 1.4;
        }

        .barang-category {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 14px;
        }

        .barang-info {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .barang-info p {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin: 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .barang-info i {
            width: 14px;
            margin-top: 2px;
            color: #8b7ae8;
        }

        .barang-description {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
            margin: 13px 0 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-footer {
            border-top: 1px solid #f1f5f9;
            padding: 12px 17px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #6246d9;
            font-size: 12px;
            font-weight: 600;
        }

        .card-footer span:last-child {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 400;
        }

        /* KOSONG */
        .empty-state {
            grid-column: 1 / -1;
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 55px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 43px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 17px;
            margin: 0 0 8px;
        }

        .empty-state p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
            line-height: 1.6;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .barang-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .page-container {
                padding: 22px 16px 40px;
            }

            .page-header-top {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .back-link {
                align-self: flex-start;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .barang-grid {
                grid-template-columns: 1fr;
            }

            .filter-card {
                padding: 16px;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="page-container">

    <!-- HEADER JUDUL DAN TOMBOL KEMBALI -->
    <div class="page-header-top">

        <div class="page-header">
            <h1>Cari Barang</h1>
            <p>
                Temukan barang hilang atau barang yang telah ditemukan
                di lingkungan kampus.
            </p>
        </div>

        <a href="dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Beranda
        </a>

    </div>

    <!-- FORM PENCARIAN -->
    <form method="GET" action="cari-barang.php" class="filter-card">

        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                name="keyword"
                placeholder="Cari nama barang atau kode laporan..."
                value="<?= e($keyword) ?>"
            >
        </div>

        <div class="filter-grid">

            <div class="filter-group">
                <label>Jenis Laporan</label>

                <select name="jenis">
                    <option value="">Semua Jenis</option>

                    <option value="kehilangan"
                        <?= $jenis === 'kehilangan' ? 'selected' : '' ?>>
                        Barang Hilang
                    </option>

                    <option value="penemuan"
                        <?= $jenis === 'penemuan' ? 'selected' : '' ?>>
                        Barang Ditemukan
                    </option>
                </select>
            </div>

            <div class="filter-group">
                <label>Kategori Barang</label>

                <select name="kategori">
                    <option value="">Semua Kategori</option>

                    <?php foreach ($daftarKategori as $kat): ?>
                        <option
                            value="<?= (int)$kat['id'] ?>"
                            <?= (string)$kategori === (string)$kat['id'] ? 'selected' : '' ?>
                        >
                            <?= e($kat['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="filter-group">
                <label>Lokasi</label>

                <select name="lokasi">
                    <option value="">Semua Lokasi</option>

                    <?php foreach ($daftarLokasi as $lok): ?>
                        <option
                            value="<?= (int)$lok['id'] ?>"
                            <?= (string)$lokasi === (string)$lok['id'] ? 'selected' : '' ?>
                        >
                            <?= e($lok['nama_lokasi']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

        </div>

        <div class="filter-actions">

            <a href="cari-barang.php" class="btn btn-reset">
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </a>

            <button type="submit" class="btn btn-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                Cari Barang
            </button>

        </div>

    </form>

    <!-- HASIL PENCARIAN -->
    <div class="result-header">

        <h2>Hasil Pencarian</h2>

        <span class="result-count">
            <?= count($barangList) ?> barang ditemukan
        </span>

    </div>

    <div class="barang-grid">

        <?php if (!empty($barangList)): ?>

            <?php foreach ($barangList as $item): ?>

                <a
                    href="detail-barang.php?id=<?= (int)$item['id'] ?>"
                    class="barang-card"
                >

                    <!-- FOTO -->
                    <div class="barang-photo">

                        <?php if (!empty($item['foto_utama'])): ?>

                            <?php
                            $namaFoto = basename($item['foto_utama']);
                            ?>

                            <img
                                src="../uploads/laporan/<?= e(rawurlencode($namaFoto)) ?>"
                                alt="<?= e($item['nama_barang']) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div class="photo-placeholder">
                                <i class="fa-regular fa-image"></i>
                                <span>Foto tidak tersedia</span>
                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- INFORMASI BARANG -->
                    <div class="barang-content">

                        <span class="barang-badge <?= $item['jenis_laporan'] === 'kehilangan' ? 'badge-kehilangan' : 'badge-penemuan' ?>">

                            <?= $item['jenis_laporan'] === 'kehilangan'
                                ? 'Barang Hilang'
                                : 'Barang Ditemukan' ?>

                        </span>

                        <h3><?= e($item['nama_barang']) ?></h3>

                        <div class="barang-category">
                            <?= e($item['nama_kategori'] ?? 'Tanpa kategori') ?>
                        </div>

                        <div class="barang-info">

                            <p>
                                <i class="fa-solid fa-location-dot"></i>
                                <?= e($item['nama_lokasi'] ?? 'Lokasi tidak tersedia') ?>
                            </p>

                            <p>
                                <i class="fa-regular fa-calendar"></i>
                                <?= e(tanggalIndonesia($item['tanggal_kejadian'])) ?>
                            </p>

                        </div>

                        <p class="barang-description">
                            <?= e($item['deskripsi'] ?: 'Tidak ada deskripsi barang.') ?>
                        </p>

                    </div>

                    <!-- FOOTER KARTU -->
                    <div class="card-footer">

                        <span>
                            Lihat Detail
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>

                        <span>
                            <?= e($item['kode_laporan']) ?>
                        </span>

                    </div>

                </a>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-state">

                <i class="fa-solid fa-box-open"></i>

                <h3>Barang Tidak Ditemukan</h3>

                <p>
                    Belum ada barang yang sesuai dengan pencarian kamu.
                    Coba gunakan kata kunci atau filter lainnya.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
