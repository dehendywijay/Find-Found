
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Proteksi halaman mahasiswa
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

// Validasi ID laporan
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: dashboard.php');
    exit;
}

// Ambil detail barang yang sudah aktif
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.user_id,
        r.kode_laporan,
        r.jenis_laporan,
        r.nama_barang,
        r.tanggal_kejadian,
        r.detail_lokasi,
        r.deskripsi,
        r.kontak,
        r.created_at,
        c.nama_kategori,
        l.nama_lokasi,
        u.nama AS nama_pelapor
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN locations l ON l.id = r.location_id
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.id = ?
      AND r.status = 'aktif'
    LIMIT 1
");

$stmt->execute([$id]);
$barang = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika laporan tidak ditemukan atau belum aktif
if (!$barang) {
    header('Location: cari-barang.php');
    exit;
}

// Ambil foto barang
$stmtFoto = $pdo->prepare("
    SELECT nama_file, urutan
    FROM report_images
    WHERE report_id = ?
    ORDER BY urutan ASC, id ASC
");

$stmtFoto->execute([$id]);
$fotos = $stmtFoto->fetchAll(PDO::FETCH_ASSOC);

// Helper
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function tanggalIndonesia($tanggal)
{
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

$isKehilangan = $barang['jenis_laporan'] === 'kehilangan';

// Tombol klaim hanya untuk laporan kehilangan
// dan tidak ditampilkan kepada pemilik laporan
$bolehKlaim = (
    $isKehilangan &&
    (int)$barang['user_id'] !== (int)$_SESSION['user_id']
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Barang - FIND & FOUND</title>

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

        .page-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 24px 50px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .back-link:hover {
            color: #6246d9;
        }

        .detail-layout {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 28px;
            align-items: start;
        }

        .photo-section,
        .info-section {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 22px;
        }

        .main-photo {
            width: 100%;
            height: 360px;
            object-fit: contain;
            background: #f1f5f9;
            border-radius: 9px;
            display: block;
        }

        .photo-list {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 12px;
        }

        .photo-list img {
            width: 100%;
            height: 75px;
            object-fit: cover;
            border-radius: 7px;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .photo-list img:hover {
            border-color: #6246d9;
        }

        .no-photo {
            height: 260px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            border-radius: 9px;
            color: #94a3b8;
            gap: 10px;
        }

        .no-photo i {
            font-size: 40px;
        }

        .report-type {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 13px;
        }

        .lost {
            background: #fee2e2;
            color: #dc2626;
        }

        .found {
            background: #dcfce7;
            color: #15803d;
        }

        .item-name {
            font-size: 27px;
            margin: 0 0 8px;
            color: #172033;
        }

        .item-category {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 22px;
        }

        .info-divider {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 20px 0;
        }

        .info-row {
            display: flex;
            gap: 13px;
            margin-bottom: 18px;
            align-items: flex-start;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 9px;
            background: #f1efff;
            color: #6246d9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-text label {
            display: block;
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-text p {
            margin: 0;
            font-size: 14px;
            color: #334155;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .description-section {
            margin-top: 25px;
        }

        .description-section h3 {
            font-size: 16px;
            margin: 0 0 10px;
        }

        .description-section p {
            color: #475569;
            font-size: 14px;
            line-height: 1.8;
            margin: 0;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .report-code {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 20px;
        }

        .contact-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 9px;
            margin-top: 22px;
        }

        .contact-box h3 {
            margin: 0 0 8px;
            font-size: 15px;
        }

        .contact-box p {
            font-size: 14px;
            color: #475569;
            margin: 0;
            overflow-wrap: anywhere;
        }

        /* TOMBOL KLAIM */
        .claim-box {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .claim-box p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            margin: 0 0 14px;
        }

        .btn-claim {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            padding: 13px 18px;
            border-radius: 8px;
            background: #6246d9;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s;
        }

        .btn-claim:hover {
            background: #5035c0;
        }

        @media (max-width: 750px) {
            .page-wrapper {
                padding: 22px 16px 40px;
            }

            .detail-layout {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .main-photo {
                display: block;
                width: 100%;
                height: auto;
                max-width: 100%;
                object-fit: contain;
                border-radius: 9px;
            }

            .item-name {
                font-size: 23px;
            }

            .photo-section {
                min-width: 0;
                height: fit-content;
            }
        }
    </style>
</head>

<body>

    <div class="page-wrapper">

        <a href="dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Beranda
        </a>

        <div class="detail-layout">

            <!-- FOTO BARANG -->
            <section class="photo-section">

                <?php if (!empty($fotos)): ?>

                    <?php
                    $fotoPertama = basename($fotos[0]['nama_file']);
                    ?>

                    <img
                        src="../uploads/laporan/<?= e(rawurlencode($fotoPertama)) ?>"
                        alt="<?= e($barang['nama_barang']) ?>"
                        class="main-photo"
                        id="mainPhoto">

                    <?php if (count($fotos) > 1): ?>
                        <div class="photo-list">

                            <?php foreach ($fotos as $foto): ?>
                                <?php $namaFoto = basename($foto['nama_file']); ?>

                                <img
                                    src="../uploads/laporan/<?= e(rawurlencode($namaFoto)) ?>"
                                    alt="Foto <?= e($barang['nama_barang']) ?>"
                                    onclick="gantiFoto(this.src)">

                            <?php endforeach; ?>

                        </div>
                    <?php endif; ?>

                <?php else: ?>

                    <div class="no-photo">
                        <i class="fa-regular fa-image"></i>
                        <span>Belum ada foto barang</span>
                    </div>

                <?php endif; ?>

            </section>


            <!-- INFORMASI BARANG -->
            <section class="info-section">

                <span class="report-type <?= $isKehilangan ? 'lost' : 'found' ?>">
                    <?= $isKehilangan ? 'Barang Hilang' : 'Barang Ditemukan' ?>
                </span>

                <h1 class="item-name">
                    <?= e($barang['nama_barang']) ?>
                </h1>

                <div class="item-category">
                    <?= e($barang['nama_kategori'] ?? 'Kategori tidak tersedia') ?>
                </div>

                <hr class="info-divider">

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <div class="info-text">
                        <label>Lokasi Kejadian</label>
                        <p><?= e($barang['nama_lokasi'] ?? 'Belum tersedia') ?></p>
                    </div>
                </div>

                <?php if (!empty($barang['detail_lokasi'])): ?>
                    <div class="info-row">
                        <div class="info-icon">
                            <i class="fa-solid fa-map-pin"></i>
                        </div>

                        <div class="info-text">
                            <label>Detail Lokasi</label>
                            <p><?= e($barang['detail_lokasi']) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fa-regular fa-calendar"></i>
                    </div>

                    <div class="info-text">
                        <label>Tanggal Kejadian</label>
                        <p><?= e(tanggalIndonesia($barang['tanggal_kejadian'])) ?></p>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fa-regular fa-user"></i>
                    </div>

                    <div class="info-text">
                        <label>Dilaporkan oleh</label>
                        <p><?= e($barang['nama_pelapor'] ?? 'Mahasiswa') ?></p>
                    </div>
                </div>

                <hr class="info-divider">

                <div class="description-section">
                    <h3>Deskripsi Barang</h3>
                    <p><?= e($barang['deskripsi'] ?: 'Tidak ada deskripsi.') ?></p>
                </div>

                <?php if (!empty($barang['kontak'])): ?>
                    <div class="contact-box">
                        <h3>
                            <i class="fa-regular fa-message"></i>
                            Informasi Kontak
                        </h3>
                        <p><?= e($barang['kontak']) ?></p>
                    </div>
                <?php endif; ?>

                <div class="report-code">
                    Kode laporan: <?= e($barang['kode_laporan']) ?>
                </div>

                <!-- TOMBOL KLAIM PENEMUAN -->
                <?php if ($bolehKlaim): ?>

                    <div class="claim-box">

                        <p>
                            Menemukan barang yang dilaporkan hilang ini?
                            Kirim foto dan detail barang sebagai bukti
                            untuk diperiksa oleh admin.
                        </p>

                        <a
                            href="form-klaim.php?id=<?= (int)$barang['id'] ?>"
                            class="btn-claim"
                        >
                            <i class="fa-solid fa-hand-holding-heart"></i>
                            Saya Menemukan Barang Ini
                        </a>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </div>

    <script>
        function gantiFoto(sumber) {
            const fotoUtama = document.getElementById('mainPhoto');

            if (fotoUtama) {
                fotoUtama.src = sumber;
            }
        }
    </script>

</body>

</html>
