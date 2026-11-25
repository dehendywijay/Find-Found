
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// 1. Proteksi login
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// 2. Parameter pencarian dan filter
$keyword = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';

// 3. Query pengajuan klaim penemuan milik mahasiswa
$sql = "
    SELECT
        fc.id,
        fc.kode_klaim,
        fc.report_id,
        fc.detail_penemuan,
        fc.status,
        fc.catatan_admin,
        fc.created_at,
        fc.diperiksa_pada,

        r.kode_laporan,
        r.nama_barang,
        r.tanggal_kejadian,

        pemilik.nama AS nama_pemilik,

        (
            SELECT fie.nama_file
            FROM finding_claim_evidences fie
            WHERE fie.finding_claim_id = fc.id
            ORDER BY fie.id ASC
            LIMIT 1
        ) AS foto_bukti

    FROM finding_claims fc

    INNER JOIN reports r
        ON r.id = fc.report_id

    INNER JOIN users pemilik
        ON pemilik.id = r.user_id

    WHERE fc.user_id = :user_id
";

// Parameter awal
$params = [
    ':user_id' => $userId
];

// 4. Pencarian kode klaim dan nama barang
if ($keyword !== '') {
    $sql .= "
        AND (
            fc.kode_klaim LIKE :kode_klaim
            OR r.nama_barang LIKE :nama_barang
            OR r.kode_laporan LIKE :kode_laporan
        )
    ";

    $params[':kode_klaim'] = '%' . $keyword . '%';
    $params[':nama_barang'] = '%' . $keyword . '%';
    $params[':kode_laporan'] = '%' . $keyword . '%';
}

// 5. Filter status
$statusValid = [
    'menunggu',
    'disetujui',
    'ditolak',
    'selesai'
];

if (in_array($filterStatus, $statusValid, true)) {
    $sql .= " AND fc.status = :status";
    $params[':status'] = $filterStatus;
}

// 6. Urutkan pengajuan terbaru
$sql .= " ORDER BY fc.created_at DESC";

// 7. Eksekusi query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$klaim = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Fungsi label status
function labelStatusKlaim($status)
{
    $labels = [
        'menunggu' => 'Menunggu Verifikasi',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

// 9. Fungsi warna status
function warnaStatusKlaim($status)
{
    $warna = [
        'menunggu' => 'warning',
        'disetujui' => 'success',
        'ditolak' => 'danger',
        'selesai' => 'secondary'
    ];

    return $warna[$status] ?? 'secondary';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Status Klaim Penemuan - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="../assets/css/status-laporan.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .claim-detail {
            margin-top: 15px;
            padding: 13px 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            color: #475569;
            font-size: 13px;
            line-height: 1.7;
        }

        .claim-detail strong {
            display: block;
            color: #334155;
            margin-bottom: 4px;
        }

        .claim-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 12px;
            color: #64748b;
            font-size: 12px;
        }

        .claim-meta i {
            margin-right: 5px;
        }

        .admin-note {
            margin-top: 13px;
        }

        .evidence-label {
            display: block;
            margin-top: 12px;
            color: #64748b;
            font-size: 12px;
        }

        /* Tombol kembali */
        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            transition: .2s;
        }

        .back-button:hover {
            color: #6246d9;
            border-color: #c4b5fd;
            background: #f8f7ff;
        }

        @media (max-width: 600px) {
            .claim-meta {
                flex-direction: column;
                gap: 7px;
            }

            .page-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

</head>

<body>

<header class="navbar">

    <a href="dashboard.php" class="brand">
        <i class="fa-solid fa-location-dot"></i>
        FIND & FOUND
    </a>

    <nav>
        <a href="dashboard.php">Beranda</a>
        <a href="cari-barang.php">Cari barang</a>
        <a href="status.php" class="active">Status</a>
    </nav>

    <div class="profile">
        <i class="fa-regular fa-user"></i>

        <?= htmlspecialchars($_SESSION['nama'] ?? 'Mahasiswa') ?>
    </div>

</header>

<main class="container">

    <section class="page-heading">

        <div>
            <h1>Status Klaim Penemuan</h1>

            <p>
                Pantau perkembangan pengajuan ketika kamu menemukan
                barang milik mahasiswa lain.
            </p>
        </div>

        <a href="status.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Status Aktivitas
        </a>

    </section>

    <!-- PENCARIAN DAN FILTER -->
    <section class="filter-card">

        <form method="GET" action="status-klaim-penemuan.php">

            <div class="search-box">

                <i class="fa-solid fa-search"></i>

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama barang atau kode klaim..."
                    value="<?= htmlspecialchars($keyword) ?>"
                >

            </div>

            <select name="status">

                <option value="">Semua status</option>

                <option value="menunggu"
                    <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>>
                    Menunggu verifikasi
                </option>

                <option value="disetujui"
                    <?= $filterStatus === 'disetujui' ? 'selected' : '' ?>>
                    Disetujui
                </option>

                <option value="ditolak"
                    <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>
                    Ditolak
                </option>

                <option value="selesai"
                    <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>
                    Selesai
                </option>

            </select>

            <button type="submit">
                Cari
            </button>

            <a href="status-klaim-penemuan.php" class="reset-button">
                Reset
            </a>

        </form>

    </section>

    <!-- DAFTAR KLAIM -->
    <section class="report-section">

        <div class="section-heading">

            <h2>Riwayat klaim penemuan saya</h2>

            <span>
                <?= count($klaim) ?> pengajuan ditemukan
            </span>

        </div>

        <?php if (count($klaim) > 0): ?>

            <div class="report-list">

                <?php foreach ($klaim as $item): ?>

                    <article class="report-card">

                        <div class="report-image">

                            <?php if (!empty($item['foto_bukti'])): ?>

                                <img
                                    src="../uploads/klaim/<?= htmlspecialchars($item['foto_bukti']) ?>"
                                    alt="Bukti penemuan barang"
                                >

                            <?php else: ?>

                                <i class="fa-regular fa-image"></i>

                            <?php endif; ?>

                        </div>

                        <div class="report-info">

                            <div class="report-top">

                                <span class="report-type">
                                    Klaim Penemuan
                                </span>

                                <span class="status <?= warnaStatusKlaim($item['status']) ?>">
                                    <?= htmlspecialchars(labelStatusKlaim($item['status'])) ?>
                                </span>

                            </div>

                            <h3>
                                <?= htmlspecialchars($item['nama_barang']) ?>
                            </h3>

                            <p class="report-code">
                                Kode klaim:
                                <?= htmlspecialchars($item['kode_klaim']) ?>
                            </p>

                            <div class="claim-meta">

                                <span>
                                    <i class="fa-solid fa-user"></i>
                                    Pemilik: <?= htmlspecialchars($item['nama_pemilik']) ?>
                                </span>

                                <span>
                                    <i class="fa-solid fa-file-lines"></i>
                                    Kode laporan:
                                    <?= htmlspecialchars($item['kode_laporan']) ?>
                                </span>

                                <span>
                                    <i class="fa-regular fa-calendar"></i>
                                    Diajukan:
                                    <?= date('d M Y', strtotime($item['created_at'])) ?>
                                </span>

                            </div>

                            <div class="claim-detail">

                                <strong>Detail penemuan:</strong>

                                <?= nl2br(htmlspecialchars($item['detail_penemuan'])) ?>

                            </div>

                            <?php if (
                                $item['status'] === 'ditolak' &&
                                !empty($item['catatan_admin'])
                            ): ?>

                                <div class="admin-note">

                                    <strong>Catatan admin:</strong>

                                    <?= nl2br(htmlspecialchars($item['catatan_admin'])) ?>

                                </div>

                            <?php elseif (
                                !empty($item['catatan_admin'])
                            ): ?>

                                <div class="admin-note">

                                    <strong>Informasi dari admin:</strong>

                                    <?= nl2br(htmlspecialchars($item['catatan_admin'])) ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <i class="fa-regular fa-folder-open"></i>

                <h3>Belum ada pengajuan klaim penemuan</h3>

                <p>
                    Pengajuan klaim penemuan yang kamu kirim akan
                    muncul di halaman ini.
                </p>

                <a href="cari-barang.php">
                    Cari barang sekarang
                </a>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
