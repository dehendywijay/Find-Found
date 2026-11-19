
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// ==========================================
// 1. PROTEKSI ADMIN
// ==========================================

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

// ==========================================
// 2. VALIDASI ID KLAIM
// ==========================================

$claimId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$claimId || $claimId < 1) {
    header('Location: laporan-klaim-barang.php');
    exit;
}

// ==========================================
// 3. AMBIL DATA KLAIM BARANG
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.kode_klaim,
        c.report_id,
        c.user_id,
        c.nama_pemilik,
        c.ciri_barang,
        c.tanggal_klaim,
        c.status,
        c.catatan_admin,
        c.diperiksa_oleh,
        c.diperiksa_pada,
        c.created_at,

        r.kode_laporan,
        r.nama_barang,
        r.tanggal_kejadian,
        r.detail_lokasi,
        r.deskripsi,
        r.jenis_laporan,
        r.status AS status_laporan,

        pengklaim.nama AS nama_pengklaim,
        pengklaim.email AS email_pengklaim,

        pelapor.nama AS nama_pelapor,
        pelapor.email AS email_pelapor,

        admin.nama AS nama_pemeriksa

    FROM claims c

    INNER JOIN reports r
        ON r.id = c.report_id

    INNER JOIN users pengklaim
        ON pengklaim.id = c.user_id

    INNER JOIN users pelapor
        ON pelapor.id = r.user_id

    LEFT JOIN users admin
        ON admin.id = c.diperiksa_oleh

    WHERE c.id = ?

    LIMIT 1
");

$stmt->execute([$claimId]);

$klaim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$klaim || $klaim['jenis_laporan'] !== 'penemuan') {
    header('Location: laporan-klaim-barang.php');
    exit;
}

// ==========================================
// 4. AMBIL FOTO BUKTI KLAIM
// ==========================================

$stmtFoto = $pdo->prepare("
    SELECT
        id,
        nama_file,
        keterangan,
        created_at
    FROM claim_evidences
    WHERE claim_id = ?
    ORDER BY id ASC
");

$stmtFoto->execute([$claimId]);

$fotos = $stmtFoto->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 5. HELPER
// ==========================================

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

function statusLabel($status)
{
    $labels = [
        'menunggu' => 'Menunggu Verifikasi',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

function statusClass($status)
{
    $classes = [
        'menunggu' => 'warning',
        'disetujui' => 'success',
        'ditolak' => 'danger',
        'selesai' => 'secondary'
    ];

    return $classes[$status] ?? 'secondary';
}

$namaAdmin = $_SESSION['nama'] ?? 'Administrator';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Detail Klaim Barang - FIND & FOUND</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f5f6fa;
            font-family: Arial, sans-serif;
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
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 25px;
            margin: 0 0 8px;
        }

        .header p {
            color: #777;
            font-size: 13px;
            margin: 0;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            color: #444;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-back:hover {
            background: #f8f8f8;
        }

        .main-card {
            background: white;
            border: 1px solid #e8e7ed;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .claim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .claim-header h2 {
            font-size: 19px;
            margin: 0 0 7px;
        }

        .claim-code {
            color: #777;
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .warning {
            color: #a66b08;
            background: #fff3d8;
        }

        .success {
            color: #16834b;
            background: #e3f8eb;
        }

        .danger {
            color: #c03939;
            background: #ffe8e8;
        }

        .secondary {
            color: #64748b;
            background: #edf0f4;
        }

        .section-title {
            font-size: 16px;
            margin: 25px 0 17px;
        }

        .section-title i {
            color: #6246d9;
            margin-right: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fc;
            border-radius: 9px;
        }

        .info-item label {
            display: block;
            color: #888;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .info-item p {
            margin: 0;
            font-size: 14px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .description {
            background: #f8f9fc;
            border-radius: 9px;
            padding: 17px;
            font-size: 14px;
            line-height: 1.8;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .report-box {
            border: 1px solid #e3defa;
            background: #faf9ff;
            padding: 20px;
            border-radius: 10px;
        }

        .report-box h3 {
            color: #6246d9;
            font-size: 18px;
            margin: 0 0 8px;
        }

        .report-box .report-code {
            color: #777;
            font-size: 12px;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 18px;
        }

        .photo-card {
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }

        .photo-card img {
            width: 100%;
            height: 220px;
            object-fit: contain;
            background: #f1f3f7;
            display: block;
        }

        .photo-caption {
            padding: 12px;
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }

        .empty-photo {
            background: #f8f9fc;
            padding: 35px;
            text-align: center;
            color: #999;
            border-radius: 9px;
        }

        .empty-photo i {
            font-size: 35px;
            margin-bottom: 12px;
        }

        .admin-note {
            background: #f8f9fc;
            border-radius: 9px;
            padding: 16px;
            font-size: 13px;
            line-height: 1.7;
        }

        .admin-note p {
            margin: 0 0 8px;
        }

        .admin-note p:last-child {
            margin-bottom: 0;
        }

        .footer-info {
            color: #999;
            font-size: 12px;
            margin-top: 15px;
        }

        @media(max-width: 650px) {

            body {
                padding: 16px;
            }

            .main-card {
                padding: 18px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 21px;
            }

            .btn-back {
                width: 100%;
                justify-content: center;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>
            <h1>Detail Klaim Barang</h1>

            <p>
                Periksa informasi dan bukti kepemilikan barang dari mahasiswa.
            </p>
        </div>

        <a href="laporan-klaim-barang.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Daftar Klaim
        </a>

    </div>


    <!-- INFORMASI UTAMA -->

    <div class="main-card">

        <div class="claim-header">

            <div>
                <h2>Informasi Pengajuan Klaim</h2>

                <div class="claim-code">
                    Kode Klaim:
                    <?= e($klaim['kode_klaim']) ?>
                </div>
            </div>

            <span class="badge <?= e(statusClass($klaim['status'])) ?>">
                <?= e(statusLabel($klaim['status'])) ?>
            </span>

        </div>


        <!-- INFORMASI PENGKLAIM -->

        <h3 class="section-title">
            <i class="fa-solid fa-user"></i>
            Informasi Mahasiswa yang Mengklaim
        </h3>

        <div class="info-grid">

            <div class="info-item">
                <label>Nama Mahasiswa</label>
                <p><?= e($klaim['nama_pengklaim']) ?></p>
            </div>

            <div class="info-item">
                <label>Email Mahasiswa</label>
                <p><?= e($klaim['email_pengklaim']) ?></p>
            </div>

            <div class="info-item">
                <label>Tanggal Pengajuan</label>
                <p><?= e(tanggalIndonesia($klaim['created_at'])) ?></p>
            </div>

            <div class="info-item">
                <label>Kode Klaim</label>
                <p><?= e($klaim['kode_klaim']) ?></p>
            </div>

        </div>


        <!-- INFORMASI BARANG TEMUAN -->

        <h3 class="section-title">
            <i class="fa-solid fa-box-open"></i>
            Informasi Barang yang Diklaim
        </h3>

        <div class="report-box">

            <h3><?= e($klaim['nama_barang']) ?></h3>

            <div class="report-code">
                Kode Laporan:
                <?= e($klaim['kode_laporan']) ?>
            </div>

            <div class="info-grid" style="margin-top:18px;">

                <div class="info-item">
                    <label>Nama Pelapor Penemuan</label>
                    <p><?= e($klaim['nama_pelapor']) ?></p>
                </div>

                <div class="info-item">
                    <label>Email Pelapor</label>
                    <p><?= e($klaim['email_pelapor']) ?></p>
                </div>

                <div class="info-item">
                    <label>Tanggal Barang Ditemukan</label>
                    <p><?= e(tanggalIndonesia($klaim['tanggal_kejadian'])) ?></p>
                </div>

                <div class="info-item">
                    <label>Lokasi Penemuan</label>
                    <p><?= e($klaim['detail_lokasi']) ?></p>
                </div>

            </div>

            <h3 class="section-title">Deskripsi Laporan Barang</h3>

            <div class="description">
                <?= e($klaim['deskripsi']) ?>
            </div>

        </div>


        <!-- DETAIL KEPEMILIKAN -->

        <h3 class="section-title">
            <i class="fa-solid fa-clipboard-check"></i>
            Informasi Kepemilikan Barang
        </h3>

        <div class="info-grid">

            <div class="info-item">
                <label>Nama Pemilik Menurut Pengklaim</label>
                <p><?= e($klaim['nama_pemilik']) ?></p>
            </div>

        </div>

        <div class="description" style="margin-top: 15px;">
            <strong>Ciri-ciri barang:</strong><br><br>
            <?= e($klaim['ciri_barang']) ?>
        </div>


        <!-- FOTO BUKTI -->

        <h3 class="section-title">
            <i class="fa-solid fa-images"></i>
            Foto Bukti Kepemilikan
        </h3>

        <?php if (!empty($fotos)): ?>

            <div class="photo-grid">

                <?php foreach ($fotos as $foto): ?>

                    <div class="photo-card">

                        <a
                            href="../uploads/klaim/<?= rawurlencode($foto['nama_file']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <img
                                src="../uploads/klaim/<?= rawurlencode($foto['nama_file']) ?>"
                                alt="Foto bukti kepemilikan"
                            >
                        </a>

                        <div class="photo-caption">

                            <?= e($foto['keterangan'] ?: 'Foto bukti kepemilikan') ?>

                            <br>

                            <span>
                                Klik foto untuk melihat ukuran penuh.
                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-photo">
                <i class="fa-regular fa-image"></i>
                <p>Pengklaim tidak mengunggah foto bukti.</p>
            </div>

        <?php endif; ?>


        <!-- INFORMASI PEMERIKSAAN ADMIN -->

        <h3 class="section-title">
            <i class="fa-solid fa-clipboard-check"></i>
            Informasi Verifikasi
        </h3>

        <div class="admin-note">

            <p>
                <strong>Status:</strong>
                <?= e(statusLabel($klaim['status'])) ?>
            </p>

            <p>
                <strong>Diperiksa oleh:</strong>
                <?= e($klaim['nama_pemeriksa'] ?: 'Belum diperiksa') ?>
            </p>

            <p>
                <strong>Tanggal pemeriksaan:</strong>
                <?= e(
                    $klaim['diperiksa_pada']
                        ? tanggalIndonesia($klaim['diperiksa_pada'])
                        : 'Belum diperiksa'
                ) ?>
            </p>

            <p>
                <strong>Catatan admin:</strong>
                <?= e($klaim['catatan_admin'] ?: 'Belum ada catatan.') ?>
            </p>

        </div>

        <div class="footer-info">
            Halaman ini menampilkan data pengajuan klaim kepemilikan barang.
            Status laporan barang akan menjadi selesai setelah klaim diselesaikan oleh admin.
        </div>

    </div>

</div>

</body>
</html>
