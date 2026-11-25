
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

// 2. Token CSRF
if (empty($_SESSION['csrf_selesai_klaim_barang'])) {
    $_SESSION['csrf_selesai_klaim_barang'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_selesai_klaim_barang'];

// 3. Parameter pencarian dan filter
$keyword = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';

// 4. Query klaim barang milik mahasiswa
$sql = "
    SELECT
        c.id,
        c.kode_klaim,
        c.report_id,
        c.nama_pemilik,
        c.ciri_barang,
        c.tanggal_klaim,
        c.status,
        c.catatan_admin,
        c.created_at,
        c.diperiksa_pada,

        r.kode_laporan,
        r.nama_barang,
        r.tanggal_kejadian,

        pemilik.nama AS nama_pelapor,

        (
            SELECT ce.nama_file
            FROM claim_evidences ce
            WHERE ce.claim_id = c.id
            ORDER BY ce.id ASC
            LIMIT 1
        ) AS foto_bukti

    FROM claims c

    INNER JOIN reports r
        ON r.id = c.report_id

    INNER JOIN users pemilik
        ON pemilik.id = r.user_id

    WHERE c.user_id = :user_id
";

$params = [
    ':user_id' => $userId
];

// 5. Pencarian
if ($keyword !== '') {
    $sql .= "
        AND (
            c.kode_klaim LIKE :kode_klaim
            OR r.nama_barang LIKE :nama_barang
            OR r.kode_laporan LIKE :kode_laporan
        )
    ";

    $params[':kode_klaim'] = '%' . $keyword . '%';
    $params[':nama_barang'] = '%' . $keyword . '%';
    $params[':kode_laporan'] = '%' . $keyword . '%';
}

// 6. Filter status
$statusValid = [
    'menunggu',
    'disetujui',
    'ditolak',
    'selesai'
];

if (in_array($filterStatus, $statusValid, true)) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $filterStatus;
}

// 7. Urutkan pengajuan terbaru
$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$klaim = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Fungsi label status
function labelStatusKlaimBarang($status)
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
function warnaStatusKlaimBarang($status)
{
    $warna = [
        'menunggu' => 'warning',
        'disetujui' => 'success',
        'ditolak' => 'danger',
        'selesai' => 'secondary'
    ];

    return $warna[$status] ?? 'secondary';
}

// 10. Fungsi keamanan output
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Status Klaim Barang - FIND & FOUND</title>

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

        .finish-claim-form {
            margin-top: 15px;
        }

        .finish-claim-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border: none;
            border-radius: 8px;
            background: #16a34a;
            color: white;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .finish-claim-button:hover {
            background: #15803d;
        }

        .finish-info {
            margin-top: 10px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        .success-message {
            margin-bottom: 18px;
            padding: 13px 16px;
            border-radius: 8px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
        }

        .error-message {
            margin-bottom: 18px;
            padding: 13px 16px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 13px;
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

        <?= e($_SESSION['nama'] ?? 'Mahasiswa') ?>
    </div>

</header>

<main class="container">

    <section class="page-heading">

        <div>
            <h1>Status Klaim Barang</h1>

            <p>
                Pantau perkembangan pengajuan klaim barang temuan
                yang kamu yakini sebagai milikmu.
            </p>
        </div>

        <a href="status.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Status Aktivitas
        </a>

    </section>

    <!-- NOTIFIKASI -->
    <?php if (!empty($_SESSION['flash_selesai_klaim_barang'])): ?>

        <?php
        $flash = $_SESSION['flash_selesai_klaim_barang'];
        unset($_SESSION['flash_selesai_klaim_barang']);
        ?>

        <div class="<?= ($flash['type'] ?? '') === 'success' ? 'success-message' : 'error-message' ?>">
            <?= e($flash['message'] ?? '') ?>
        </div>

    <?php endif; ?>

    <!-- PENCARIAN DAN FILTER -->
    <section class="filter-card">

        <form method="GET" action="status-klaim-barang.php">

            <div class="search-box">

                <i class="fa-solid fa-search"></i>

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama barang atau kode klaim..."
                    value="<?= e($keyword) ?>"
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

            <a href="status-klaim-barang.php" class="reset-button">
                Reset
            </a>

        </form>

    </section>

    <!-- DAFTAR KLAIM -->
    <section class="report-section">

        <div class="section-heading">

            <h2>Riwayat klaim barang saya</h2>

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
                                    src="../uploads/klaim/<?= e($item['foto_bukti']) ?>"
                                    alt="Bukti klaim barang"
                                >

                            <?php else: ?>

                                <i class="fa-regular fa-image"></i>

                            <?php endif; ?>

                        </div>

                        <div class="report-info">

                            <div class="report-top">

                                <span class="report-type">
                                    Klaim Barang
                                </span>

                                <span class="status <?= e(warnaStatusKlaimBarang($item['status'])) ?>">
                                    <?= e(labelStatusKlaimBarang($item['status'])) ?>
                                </span>

                            </div>

                            <h3>
                                <?= e($item['nama_barang']) ?>
                            </h3>

                            <p class="report-code">
                                Kode klaim:
                                <?= e($item['kode_klaim']) ?>
                            </p>

                            <div class="claim-meta">

                                <span>
                                    <i class="fa-solid fa-user"></i>
                                    Pelapor:
                                    <?= e($item['nama_pelapor']) ?>
                                </span>

                                <span>
                                    <i class="fa-solid fa-file-lines"></i>
                                    Kode laporan:
                                    <?= e($item['kode_laporan']) ?>
                                </span>

                                <span>
                                    <i class="fa-regular fa-calendar"></i>
                                    Diajukan:
                                    <?= !empty($item['created_at'])
                                        ? date('d M Y', strtotime($item['created_at']))
                                        : '-' ?>
                                </span>

                            </div>

                            <div class="claim-detail">

                                <strong>Nama pemilik:</strong>

                                <?= e($item['nama_pemilik']) ?>

                                <strong style="margin-top: 10px;">
                                    Ciri-ciri barang:
                                </strong>

                                <?= nl2br(e($item['ciri_barang'])) ?>

                            </div>

                            <?php if (!empty($item['catatan_admin'])): ?>

                                <div class="admin-note">

                                    <strong>
                                        <?= $item['status'] === 'ditolak'
                                            ? 'Catatan admin:'
                                            : 'Informasi dari admin:' ?>
                                    </strong>

                                    <?= nl2br(e($item['catatan_admin'])) ?>

                                </div>

                            <?php endif; ?>

                            <!-- TOMBOL SELESAI -->
                            <?php if ($item['status'] === 'disetujui'): ?>

                                <form
                                    method="POST"
                                    action="proses-selesai-klaim-barang.php"
                                    class="finish-claim-form"
                                    onsubmit="return confirm('Apakah kamu yakin barang sudah diterima dan proses klaim ini ingin diselesaikan?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="claim_id"
                                        value="<?= (int)$item['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="finish-claim-button"
                                    >
                                        <i class="fa-solid fa-circle-check"></i>
                                        Selesaikan Klaim
                                    </button>

                                </form>

                                <p class="finish-info">
                                    Tekan tombol ini setelah barang benar-benar
                                    kamu terima dari pelapor.
                                </p>

                            <?php elseif ($item['status'] === 'selesai'): ?>

                                <p class="finish-info">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Klaim ini sudah diselesaikan.
                                </p>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <i class="fa-regular fa-folder-open"></i>

                <h3>Belum ada pengajuan klaim barang</h3>

                <p>
                    Pengajuan klaim barang yang kamu kirim akan
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
