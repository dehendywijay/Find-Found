
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// 1. Proteksi login admin
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

// 2. Validasi ID laporan
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: laporan-masuk.php');
    exit;
}

// 3. Ambil detail laporan
$stmt = $pdo->prepare("
    SELECT
        r.*,
        u.nama AS nama_pelapor,
        u.email AS email_pelapor,
        c.nama_kategori,
        l.nama_lokasi,
        admin.nama AS nama_verifikator
    FROM reports r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN categories c ON c.id = r.category_id
    INNER JOIN locations l ON l.id = r.location_id
    LEFT JOIN users admin ON admin.id = r.diverifikasi_oleh
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$laporan = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. Jika laporan tidak ditemukan
if (!$laporan) {
    header('Location: laporan-masuk.php');
    exit;
}

// 5. Ambil seluruh foto laporan
$stmtFoto = $pdo->prepare("
    SELECT nama_file, urutan
    FROM report_images
    WHERE report_id = ?
    ORDER BY urutan ASC
");

$stmtFoto->execute([$id]);
$fotos = $stmtFoto->fetchAll(PDO::FETCH_ASSOC);

// 6. Helper
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function labelStatus($status) {
    $labels = [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'aktif' => 'Aktif',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

function labelJenis($jenis) {
    return $jenis === 'kehilangan'
        ? 'Kehilangan'
        : 'Penemuan';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Laporan - FIND & FOUND</title>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .detail-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 24px;
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .detail-header h1 {
            font-size: 26px;
            margin-bottom: 6px;
        }

        .detail-header p {
            color: #64748b;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
        }

        .detail-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            margin-bottom: 20px;
            color: #172033;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
        }

        .detail-item label {
            display: block;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 7px;
        }

        .detail-item p {
            font-size: 15px;
            color: #172033;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .detail-full {
            grid-column: 1 / -1;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #fef3c7;
            color: #92400e;
        }

        .status-aktif {
            background: #dcfce7;
            color: #166534;
        }

        .status-ditolak {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-selesai {
            background: #e2e8f0;
            color: #475569;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }

        .photo-item img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 9px;
            border: 1px solid #e2e8f0;
        }

        .empty-photo {
            color: #94a3b8;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .admin-note {
            background: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 6px;
            color: #92400e;
            line-height: 1.6;
        }

        .verification-note {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }

        @media (max-width: 600px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-full {
                grid-column: auto;
            }

            .detail-card {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="detail-container">

    <!-- HEADER -->
    <div class="detail-header">

        <div>
            <h1>Detail Laporan</h1>
            <p>
                Informasi lengkap laporan barang FIND & FOUND.
            </p>
        </div>

        <a href="laporan-masuk.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali
        </a>

    </div>

    <!-- INFORMASI UTAMA -->
    <section class="detail-card">

        <h2 class="card-title">
            <i class="fa-solid fa-file-lines"></i>
            Informasi Laporan
        </h2>

        <div class="detail-grid">

            <div class="detail-item">
                <label>Kode Laporan</label>
                <p><strong><?= e($laporan['kode_laporan']) ?></strong></p>
            </div>

            <div class="detail-item">
                <label>Status Laporan</label>

                <span class="status-badge status-<?= e($laporan['status']) ?>">
                    <?= e(labelStatus($laporan['status'])) ?>
                </span>
            </div>

            <div class="detail-item">
                <label>Jenis Laporan</label>
                <p><?= e(labelJenis($laporan['jenis_laporan'])) ?></p>
            </div>

            <div class="detail-item">
                <label>Nama Barang</label>
                <p><?= e($laporan['nama_barang']) ?></p>
            </div>

            <div class="detail-item">
                <label>Kategori Barang</label>
                <p><?= e($laporan['nama_kategori']) ?></p>
            </div>

            <div class="detail-item">
                <label>Tanggal Kejadian</label>
                <p>
                    <?= date('d F Y', strtotime($laporan['tanggal_kejadian'])) ?>
                </p>
            </div>

            <div class="detail-item">
                <label>Lokasi Kejadian</label>
                <p><?= e($laporan['nama_lokasi']) ?></p>
            </div>

            <div class="detail-item">
                <label>Detail Lokasi</label>
                <p><?= e($laporan['detail_lokasi'] ?: '-') ?></p>
            </div>

            <div class="detail-item detail-full">
                <label>Deskripsi Barang</label>
                <p><?= nl2br(e($laporan['deskripsi'])) ?></p>
            </div>

            <div class="detail-item detail-full">
                <label>Detail Rahasia Barang</label>
                <p>
                    <?= nl2br(e($laporan['detail_rahasia'] ?: '-')) ?>
                </p>
            </div>

            <div class="detail-item">
                <label>Kontak Pelapor</label>
                <p><?= e($laporan['kontak'] ?: '-') ?></p>
            </div>

            <div class="detail-item">
                <label>Tanggal Pengiriman</label>
                <p>
                    <?= $laporan['created_at']
                        ? date('d F Y H:i', strtotime($laporan['created_at']))
                        : '-' ?>
                </p>
            </div>

        </div>

    </section>

    <!-- INFORMASI PELAPOR -->
    <section class="detail-card">

        <h2 class="card-title">
            <i class="fa-solid fa-user"></i>
            Informasi Pelapor
        </h2>

        <div class="detail-grid">

            <div class="detail-item">
                <label>Nama Mahasiswa</label>
                <p><?= e($laporan['nama_pelapor']) ?></p>
            </div>

            <div class="detail-item">
                <label>Email Kampus</label>
                <p><?= e($laporan['email_pelapor']) ?></p>
            </div>

        </div>

    </section>

    <!-- FOTO BARANG -->
    <section class="detail-card">

        <h2 class="card-title">
            <i class="fa-solid fa-images"></i>
            Foto Barang
        </h2>

        <?php if (!empty($fotos)): ?>

            <div class="photo-grid">

                <?php foreach ($fotos as $foto): ?>

                    <div class="photo-item">

                        <img
                            src="../uploads/laporan/<?= e(rawurlencode($foto['nama_file'])) ?>"
                            alt="Foto barang <?= e($foto['urutan']) ?>"
                            loading="lazy"
                        >

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-photo">
                <i class="fa-regular fa-image"></i>
                Belum ada foto barang yang diunggah.
            </div>

        <?php endif; ?>

    </section>

    <!-- CATATAN ADMIN -->
    <?php if (!empty($laporan['catatan_admin'])): ?>

        <section class="detail-card">

            <h2 class="card-title">
                <i class="fa-solid fa-note-sticky"></i>
                Catatan Admin
            </h2>

            <div class="admin-note">
                <?= nl2br(e($laporan['catatan_admin'])) ?>
            </div>

        </section>

    <?php endif; ?>

    <!-- RIWAYAT VERIFIKASI -->
    <section class="detail-card">

        <h2 class="card-title">
            <i class="fa-solid fa-shield-halved"></i>
            Riwayat Verifikasi
        </h2>

        <div class="detail-grid">

            <div class="detail-item">
                <label>Diverifikasi Oleh</label>
                <p><?= e($laporan['nama_verifikator'] ?: '-') ?></p>
            </div>

            <div class="detail-item">
                <label>Waktu Verifikasi</label>
                <p>
                    <?= !empty($laporan['diverifikasi_pada'])
                        ? date('d F Y H:i', strtotime($laporan['diverifikasi_pada']))
                        : 'Tidak ada riwayat verifikasi' ?>
                </p>
            </div>

        </div>

    </section>

</div>

</body>
</html>
