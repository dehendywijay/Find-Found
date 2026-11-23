
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

$userId = $_SESSION['user_id'];

// Token keamanan
if (empty($_SESSION['csrf_selesai_laporan'])) {
    $_SESSION['csrf_selesai_laporan'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_selesai_laporan'];

// Notifikasi
$flash = $_SESSION['flash_selesai_laporan'] ?? null;
unset($_SESSION['flash_selesai_laporan']);

// Parameter pencarian dan filter
$keyword = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterJenis = $_GET['jenis'] ?? '';

// Query laporan mahasiswa
$sql = "
    SELECT
        r.id,
        r.kode_laporan,
        r.jenis_laporan,
        r.nama_barang,
        r.tanggal_kejadian,
        r.status,
        r.catatan_admin,
        r.created_at,
        c.nama_kategori,
        l.nama_lokasi,

        (
            SELECT ri.nama_file
            FROM report_images ri
            WHERE ri.report_id = r.id
            ORDER BY ri.urutan ASC
            LIMIT 1
        ) AS foto,

        (
            SELECT fc.id
            FROM finding_claims fc
            WHERE fc.report_id = r.id
              AND fc.status IN ('disetujui', 'selesai')
            ORDER BY fc.updated_at DESC
            LIMIT 1
        ) AS finding_claim_id,

        (
            SELECT fc.status
            FROM finding_claims fc
            WHERE fc.report_id = r.id
              AND fc.status IN ('disetujui', 'selesai')
            ORDER BY fc.updated_at DESC
            LIMIT 1
        ) AS status_klaim_penemuan,

        (
            SELECT fc.catatan_admin
            FROM finding_claims fc
            WHERE fc.report_id = r.id
              AND fc.status IN ('disetujui', 'selesai')
            ORDER BY fc.updated_at DESC
            LIMIT 1
        ) AS pesan_admin_penemuan

    FROM reports r

    INNER JOIN categories c
        ON c.id = r.category_id

    INNER JOIN locations l
        ON l.id = r.location_id

    WHERE r.user_id = :user_id
";

$params = [
    ':user_id' => $userId
];

// Pencarian
if ($keyword !== '') {
    $sql .= "
        AND (
            r.nama_barang LIKE :nama_keyword
            OR r.kode_laporan LIKE :kode_keyword
        )
    ";

    $params[':nama_keyword'] = '%' . $keyword . '%';
    $params[':kode_keyword'] = '%' . $keyword . '%';
}

// Filter status
$statusValid = [
    'menunggu_verifikasi',
    'aktif',
    'ditolak',
    'selesai'
];

if (in_array($filterStatus, $statusValid, true)) {
    $sql .= " AND r.status = :status";
    $params[':status'] = $filterStatus;
}

// Filter jenis
if (in_array($filterJenis, ['kehilangan', 'penemuan'], true)) {
    $sql .= " AND r.jenis_laporan = :jenis";
    $params[':jenis'] = $filterJenis;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Label status
function labelStatus($status, $statusKlaim = null)
{
    if ($status === 'selesai') {
        return 'Selesai';
    }

    if (in_array($statusKlaim, ['disetujui', 'selesai'], true)) {
        return 'Barang Ditemukan';
    }

    $labels = [
        'menunggu_verifikasi' => 'Menunggu verifikasi',
        'aktif' => 'Aktif',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

// Warna status
function warnaStatus($status, $statusKlaim = null)
{
    if ($status === 'selesai') {
        return 'secondary';
    }

    if (in_array($statusKlaim, ['disetujui', 'selesai'], true)) {
        return 'found';
    }

    $warna = [
        'menunggu_verifikasi' => 'warning',
        'aktif' => 'success',
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

    <title>Status Laporan - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="../assets/css/status-laporan.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .status.found {
            background: #fff0d6;
            color: #c77700;
        }

        .confirm-finish {
            margin-top: 12px;
        }

        .finish-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 15px;
            border: none;
            border-radius: 7px;
            background: #16a34a;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .finish-button:hover {
            background: #15803d;
        }

        .finish-info {
            margin-top: 9px;
            color: #64748b;
            font-size: 12px;
        }

        .flash-message {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 13px;
        }

        .flash-message.success {
            background: #dcfce7;
            color: #166534;
        }

        .flash-message.error {
            background: #fee2e2;
            color: #991b1b;
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
        <h1>Status laporan</h1>

        <p>
            Pantau perkembangan laporan kehilangan dan penemuan barangmu.
        </p>
    </section>

    <?php if ($flash): ?>
        <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- FILTER DAN PENCARIAN -->
    <section class="filter-card">

        <form method="GET" action="status-laporan.php">

            <div class="search-box">
                <i class="fa-solid fa-search"></i>

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama barang atau kode laporan..."
                    value="<?= htmlspecialchars($keyword) ?>"
                >
            </div>

            <select name="jenis">
                <option value="">Semua jenis laporan</option>

                <option value="kehilangan"
                    <?= $filterJenis === 'kehilangan' ? 'selected' : '' ?>>
                    Kehilangan
                </option>

                <option value="penemuan"
                    <?= $filterJenis === 'penemuan' ? 'selected' : '' ?>>
                    Penemuan
                </option>
            </select>

            <select name="status">
                <option value="">Semua status</option>

                <option value="menunggu_verifikasi"
                    <?= $filterStatus === 'menunggu_verifikasi' ? 'selected' : '' ?>>
                    Menunggu verifikasi
                </option>

                <option value="aktif"
                    <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>
                    Aktif
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

            <button type="submit">Cari</button>

            <a href="status-laporan.php" class="reset-button">
                Reset
            </a>

        </form>

    </section>

    <!-- DAFTAR LAPORAN -->
    <section class="report-section">

        <div class="section-heading">
            <h2>Riwayat laporan saya</h2>

            <span>
                <?= count($laporan) ?> laporan ditemukan
            </span>
        </div>

        <?php if (count($laporan) > 0): ?>

            <div class="report-list">

                <?php foreach ($laporan as $item): ?>

                    <article class="report-card">

                        <div class="report-image">

                            <?php if (!empty($item['foto'])): ?>

                                <img
                                    src="../uploads/laporan/<?= htmlspecialchars($item['foto']) ?>"
                                    alt="Foto barang"
                                >

                            <?php else: ?>

                                <i class="fa-regular fa-image"></i>

                            <?php endif; ?>

                        </div>

                        <div class="report-info">

                            <div class="report-top">

                                <span class="report-type">
                                    Laporan <?= htmlspecialchars(ucfirst($item['jenis_laporan'])) ?>
                                </span>

                                <span class="status <?= warnaStatus(
                                    $item['status'],
                                    $item['status_klaim_penemuan']
                                ) ?>">
                                    <?= htmlspecialchars(labelStatus(
                                        $item['status'],
                                        $item['status_klaim_penemuan']
                                    )) ?>
                                </span>

                            </div>

                            <h3>
                                <?= htmlspecialchars($item['nama_barang']) ?>
                            </h3>

                            <p class="report-code">
                                <?= htmlspecialchars($item['kode_laporan']) ?>
                            </p>

                            <div class="report-details">

                                <span>
                                    <i class="fa-solid fa-layer-group"></i>
                                    <?= htmlspecialchars($item['nama_kategori']) ?>
                                </span>

                                <span>
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($item['nama_lokasi']) ?>
                                </span>

                                <span>
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= date('d M Y', strtotime($item['tanggal_kejadian'])) ?>
                                </span>

                            </div>

                            <!-- CATATAN ADMIN LAPORAN -->
                            <?php if (
                                $item['status'] === 'ditolak' &&
                                !empty($item['catatan_admin'])
                            ): ?>

                                <div class="admin-note">
                                    <strong>Catatan admin:</strong>
                                    <?= nl2br(htmlspecialchars($item['catatan_admin'])) ?>
                                </div>

                            <?php endif; ?>

                            <!-- PESAN ADMIN KLAIM PENEMUAN -->
                            <?php if (
                                !empty($item['pesan_admin_penemuan']) &&
                                in_array(
                                    $item['status_klaim_penemuan'],
                                    ['disetujui', 'selesai'],
                                    true
                                )
                            ): ?>

                                <div class="admin-note">
                                    <strong>Pesan admin:</strong>
                                    <?= nl2br(htmlspecialchars($item['pesan_admin_penemuan'])) ?>
                                </div>

                            <?php endif; ?>

                            <!-- TOMBOL KONFIRMASI SELESAI -->
                            <?php if (
                                $item['jenis_laporan'] === 'kehilangan' &&
                                $item['status'] !== 'selesai' &&
                                $item['status_klaim_penemuan'] === 'disetujui' &&
                                !empty($item['finding_claim_id'])
                            ): ?>

                                <div class="confirm-finish">

                                    <form
                                        method="POST"
                                        action="proses-selesai-penemuan.php"
                                        onsubmit="return confirm('Apakah barang sudah kamu terima kembali dari penemu? Jika ya, laporan akan ditandai selesai.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="report_id"
                                            value="<?= (int)$item['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="finding_claim_id"
                                            value="<?= (int)$item['finding_claim_id'] ?>"
                                        >

                                        <button type="submit" class="finish-button">
                                            <i class="fa-solid fa-check-circle"></i>
                                            Selesai
                                        </button>

                                    </form>

                                    <p class="finish-info">
                                        Klik setelah barang benar-benar kamu terima kembali.
                                    </p>

                                </div>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <i class="fa-regular fa-folder-open"></i>

                <h3>Belum ada laporan</h3>

                <p>
                    Tidak ditemukan laporan yang sesuai dengan pencarianmu.
                </p>

                <a href="lapor-kehilangan.php">
                    Buat laporan sekarang
                </a>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
