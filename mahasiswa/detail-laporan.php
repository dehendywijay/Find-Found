
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// 1. Proteksi login mahasiswa
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

// 2. Validasi ID laporan
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: detail-laporan.php');
    exit;
}

// 3. Ambil detail laporan milik mahasiswa yang sedang login
// Penting: user_id digunakan untuk mencegah mahasiswa membuka laporan orang lain.
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
      AND r.user_id = ?
    LIMIT 1
");

$stmt->execute([
    $id,
    $_SESSION['user_id']
]);

$laporan = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. Jika laporan tidak ditemukan atau bukan milik mahasiswa
if (!$laporan) {
    header('Location: laporan-saya.php');
    exit;
}

// 5. Ambil foto laporan
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
        'selesai' => 'Selesai',
        'diarsipkan' => 'Diarsipkan'
    ];

    return $labels[$status] ?? ucfirst($status);
}

function labelJenis($jenis) {
    return $jenis === 'kehilangan'
        ? 'Kehilangan'
        : 'Penemuan';
}

function warnaStatus($status) {
    $warna = [
        'menunggu_verifikasi' => 'warning',
        'aktif' => 'success',
        'ditolak' => 'danger',
        'selesai' => 'secondary',
        'diarsipkan' => 'secondary'
    ];

    return $warna[$status] ?? 'secondary';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Laporan - FIND & FOUND</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #f8fafc;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
        }

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
            margin: 0 0 7px;
        }

        .detail-header p {
            color: #64748b;
            margin: 0;
            font-size: 14px;
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
            font-size: 14px;
            transition: .2s;
        }

        .back-button:hover {
            background: #f1f5f9;
        }

        .detail-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            margin: 0 0 22px;
            color: #172033;
        }

        .card-title i {
            color: #6246d9;
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
            margin: 0;
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
        }

        .warning {
            background: #fef3c7;
            color: #92400e;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .status-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            margin-bottom: 20px;
            border-radius: 9px;
            line-height: 1.6;
            font-size: 14px;
        }

        .status-info i {
            margin-top: 3px;
            font-size: 17px;
        }

        .info-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .info-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .info-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .info-secondary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
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
            cursor: pointer;
            transition: .2s;
        }

        .photo-item img:hover {
            transform: scale(1.02);
        }

        .empty-photo {
            color: #94a3b8;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 14px;
        }

        .admin-note {
            background: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 6px;
            color: #92400e;
            line-height: 1.7;
            font-size: 14px;
        }

        .verification-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 13px;
            margin-top: 18px;
        }

        .verification-info i {
            color: #16a34a;
        }

        @media (max-width: 600px) {
            .detail-container {
                margin: 20px auto;
                padding: 0 16px;
            }

            .detail-header {
                align-items: flex-start;
            }

            .detail-header h1 {
                font-size: 23px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-full {
                grid-column: auto;
            }

            .detail-card {
                padding: 18px;
            }

            .photo-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .photo-item img {
                height: 140px;
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
                Informasi lengkap laporan barang yang kamu kirim.
            </p>
        </div>

        <a href="laporan-saya.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali
        </a>

    </div>


    <!-- INFORMASI STATUS -->
    <?php
    $pesanStatus = [
        'menunggu_verifikasi' => [
            'class' => 'info-warning',
            'icon' => 'fa-clock',
            'pesan' => 'Laporan kamu sudah berhasil dikirim dan sedang menunggu pemeriksaan admin.'
        ],
        'aktif' => [
            'class' => 'info-success',
            'icon' => 'fa-circle-check',
            'pesan' => 'Laporan kamu sudah diverifikasi dan telah diaktifkan oleh admin.'
        ],
        'ditolak' => [
            'class' => 'info-danger',
            'icon' => 'fa-circle-xmark',
            'pesan' => 'Laporan kamu belum dapat disetujui oleh admin. Silakan perhatikan catatan admin jika tersedia.'
        ],
        'selesai' => [
            'class' => 'info-secondary',
            'icon' => 'fa-flag-checkered',
            'pesan' => 'Laporan ini telah ditandai selesai.'
        ],
        'diarsipkan' => [
            'class' => 'info-secondary',
            'icon' => 'fa-box-archive',
            'pesan' => 'Laporan ini telah diarsipkan.'
        ]
    ];

    $statusInfo = $pesanStatus[$laporan['status']] ?? [
        'class' => 'info-secondary',
        'icon' => 'fa-circle-info',
        'pesan' => 'Status laporan kamu saat ini: ' . labelStatus($laporan['status'])
    ];
    ?>

    <div class="status-info <?= e($statusInfo['class']) ?>">
        <i class="fa-solid <?= e($statusInfo['icon']) ?>"></i>
        <div>
            <strong><?= e(labelStatus($laporan['status'])) ?></strong><br>
            <?= e($statusInfo['pesan']) ?>
        </div>
    </div>


    <!-- INFORMASI LAPORAN -->
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
                <span class="status-badge <?= e(warnaStatus($laporan['status'])) ?>">
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
                    <?= !empty($laporan['tanggal_kejadian'])
                        ? date('d F Y', strtotime($laporan['tanggal_kejadian']))
                        : '-' ?>
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
                <label>Detail Ciri-ciri Barang</label>
                <p>
                    <?= nl2br(e($laporan['detail_rahasia'] ?: '-')) ?>
                </p>
            </div>

            <div class="detail-item">
                <label>Kontak yang Dapat Dihubungi</label>
                <p><?= e($laporan['kontak'] ?: '-') ?></p>
            </div>

            <div class="detail-item">
                <label>Tanggal Pengiriman</label>
                <p>
                    <?= !empty($laporan['created_at'])
                        ? date('d F Y H:i', strtotime($laporan['created_at']))
                        : '-' ?>
                </p>
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
                            onclick="window.open(this.src, '_blank')"
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
                Catatan dari Admin
            </h2>

            <div class="admin-note">
                <?= nl2br(e($laporan['catatan_admin'])) ?>
            </div>

        </section>

    <?php endif; ?>


    <!-- INFORMASI VERIFIKASI -->
    <section class="detail-card">

        <h2 class="card-title">
            <i class="fa-solid fa-shield-halved"></i>
            Informasi Verifikasi
        </h2>

        <div class="detail-grid">

            <div class="detail-item">
                <label>Diperiksa Oleh</label>
                <p>
                    <?= e($laporan['nama_verifikator'] ?: 'Belum diperiksa') ?>
                </p>
            </div>

            <div class="detail-item">
                <label>Waktu Pemeriksaan</label>
                <p>
                    <?= !empty($laporan['diverifikasi_pada'])
                        ? date('d F Y H:i', strtotime($laporan['diverifikasi_pada']))
                        : 'Belum diperiksa' ?>
                </p>
            </div>

        </div>

        <?php if (!empty($laporan['diverifikasi_pada'])): ?>
            <div class="verification-info">
                <i class="fa-solid fa-circle-check"></i>
                Informasi verifikasi laporan telah diperbarui.
            </div>
        <?php endif; ?>

    </section>

</div>

</body>
</html>
