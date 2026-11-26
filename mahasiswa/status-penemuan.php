
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

// 2. Ambil parameter pencarian dan filter
$keyword = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';

// 3. Query khusus laporan penemuan mahasiswa
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
        ) AS foto

    FROM reports r

    INNER JOIN categories c
        ON c.id = r.category_id

    INNER JOIN locations l
        ON l.id = r.location_id

    WHERE r.user_id = :user_id
    AND r.jenis_laporan = 'penemuan'
";

// Parameter awal
$params = [
    ':user_id' => $userId
];

// 4. Pencarian nama barang dan kode laporan
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

// 5. Filter status
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

// 6. Urutkan laporan terbaru
$sql .= " ORDER BY r.created_at DESC";

// 7. Eksekusi query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Fungsi label status
function labelStatus($status) {
    $labels = [
        'menunggu_verifikasi' => 'Menunggu verifikasi',
        'aktif' => 'Aktif',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

// 9. Fungsi warna status
function warnaStatus($status) {
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

    <title>Status Laporan Penemuan - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="../assets/css/status-laporan.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
        <a href="status.php" class="active">
            Status
        </a>
    </nav>

    <div class="profile">
        <i class="fa-regular fa-user"></i>

        <?= htmlspecialchars($_SESSION['nama'] ?? 'Mahasiswa') ?>
    </div>

</header>

<main class="container">

    <section class="page-heading">

        <h1>Status laporan penemuan</h1>

        <p>
            Pantau perkembangan laporan barang yang telah kamu temukan.
        </p>

    </section>

    <!-- FILTER DAN PENCARIAN -->
    <section class="filter-card">

        <form method="GET" action="status-penemuan.php">

            <div class="search-box">

                <i class="fa-solid fa-search"></i>

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama barang atau kode laporan..."
                    value="<?= htmlspecialchars($keyword) ?>"
                >

            </div>

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

            <button type="submit">
                Cari
            </button>

            <a href="status-penemuan.php" class="reset-button">
                Reset
            </a>

        </form>

    </section>

    <!-- DAFTAR LAPORAN PENEMUAN -->
    <section class="report-section">

        <div class="section-heading">

            <h2>Riwayat laporan penemuan saya</h2>

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
                                    Laporan Penemuan
                                </span>

                                <span class="status <?= warnaStatus($item['status']) ?>">
                                    <?= htmlspecialchars(labelStatus($item['status'])) ?>
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

                            <?php if (
                                $item['status'] === 'ditolak' &&
                                !empty($item['catatan_admin'])
                            ): ?>

                                <div class="admin-note">

                                    <strong>Catatan admin:</strong>

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

                <h3>Belum ada laporan penemuan</h3>

                <p>
                    Tidak ditemukan laporan penemuan yang sesuai dengan pencarianmu.
                </p>

                <a href="lapor-penemuan.php">
                    Buat laporan penemuan sekarang
                </a>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
