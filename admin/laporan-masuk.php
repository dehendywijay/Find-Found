
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// ==========================================
// 1. PROTEKSI LOGIN ADMIN
// ==========================================
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

// Token CSRF untuk aksi penghapusan laporan
if (empty($_SESSION['csrf_hapus_laporan'])) {
    $_SESSION['csrf_hapus_laporan'] = bin2hex(random_bytes(32));
}
$csrfTokenHapus = $_SESSION['csrf_hapus_laporan'];

// Pesan hasil aksi penghapusan
$flashHapus = $_SESSION['flash_hapus_laporan'] ?? null;
unset($_SESSION['flash_hapus_laporan']);

// ==========================================
// 2. AMBIL FILTER PENCARIAN
// ==========================================
$keyword = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterJenis = $_GET['jenis'] ?? '';

// Status yang diperbolehkan
$statusValid = [
    'menunggu_verifikasi',
    'aktif',
    'ditolak',
    'selesai'
];

$jenisValid = [
    'kehilangan',
    'penemuan'
];

// ==========================================
// 3. QUERY DATA LAPORAN
// ==========================================
$sql = "
    SELECT
        r.id,
        r.kode_laporan,
        r.jenis_laporan,
        r.nama_barang,
        r.deskripsi,
        r.detail_lokasi,
        r.tanggal_kejadian,
        r.status,
        r.created_at,

        NOT EXISTS (
            SELECT 1
            FROM claims c_hapus
            WHERE c_hapus.report_id = r.id
              AND c_hapus.status NOT IN ('ditolak', 'dibatalkan')
        ) AS klaim_barang_aman_dihapus,

        NOT EXISTS (
            SELECT 1
            FROM finding_claims fc_hapus
            WHERE fc_hapus.report_id = r.id
              AND fc_hapus.status <> 'ditolak'
        ) AS klaim_penemuan_aman_dihapus,

        NOT EXISTS (
            SELECT 1
            FROM claims c_handover
            INNER JOIN handovers h
                ON h.claim_id = c_handover.id
            WHERE c_handover.report_id = r.id
              AND h.status <> 'dibatalkan'
        ) AS handover_aman_dihapus,

        u.nama AS nama_pelapor,

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

    INNER JOIN users u
        ON u.id = r.user_id

    INNER JOIN categories c
        ON c.id = r.category_id

    INNER JOIN locations l
        ON l.id = r.location_id

    WHERE 1 = 1
";

$params = [];

// ==========================================
// 4. PENCARIAN
// ==========================================
if ($keyword !== '') {

    $sql .= "
        AND (
            r.nama_barang LIKE :keyword_nama
            OR r.kode_laporan LIKE :keyword_kode
            OR u.nama LIKE :keyword_pelapor
        )
    ";

    $params[':keyword_nama'] = '%' . $keyword . '%';
    $params[':keyword_kode'] = '%' . $keyword . '%';
    $params[':keyword_pelapor'] = '%' . $keyword . '%';
}

// ==========================================
// 5. FILTER STATUS
// ==========================================
if (in_array($filterStatus, $statusValid, true)) {

    $sql .= " AND r.status = :status";

    $params[':status'] = $filterStatus;
}

// ==========================================
// 6. FILTER JENIS LAPORAN
// ==========================================
if (in_array($filterJenis, $jenisValid, true)) {

    $sql .= " AND r.jenis_laporan = :jenis";

    $params[':jenis'] = $filterJenis;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);



// ==========================================
// 7. FUNGSI LABEL STATUS
// ==========================================
function labelStatus($status)
{
    $labels = [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'aktif' => 'Aktif',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

function warnaStatus($status)
{
    $warna = [
        'menunggu_verifikasi' => 'warning',
        'aktif' => 'success',
        'ditolak' => 'danger',
        'selesai' => 'secondary'
    ];

    return $warna[$status] ?? 'secondary';
}

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

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Pemantauan Laporan - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #25233a;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .brand {
            color: #6246d9;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
        }

        .back-link {
            color: #6246d9;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .heading {
            margin-bottom: 25px;
        }

        .heading h1 {
            margin: 0 0 8px;
            font-size: 27px;
        }

        .heading p {
            color: #777;
            margin: 0;
        }

        .filter-card {
            background: white;
            border: 1px solid #e8e7ed;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 12px;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            background: white;
        }

        input:focus,
        select:focus {
            border-color: #6246d9;
        }

        button,
        .reset {
            border: none;
            border-radius: 8px;
            padding: 12px 18px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
        }

        button {
            background: #6246d9;
            color: white;
        }

        .reset {
            background: #eeeeF3;
            color: #555;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 10px;
        }

        .section-heading h2 {
            font-size: 19px;
            margin: 0;
        }

        .total {
            color: #777;
            font-size: 13px;
        }

        .table-card {
            background: white;
            border: 1px solid #e8e7ed;
            border-radius: 12px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            vertical-align: middle;
        }

        th {
            background: #faf9fd;
            color: #777;
            font-weight: bold;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 190px;
        }

        .item-info img,
        .no-image {
            width: 58px;
            height: 58px;
            border-radius: 8px;
            object-fit: cover;
            background: #f0f1f5;
            flex-shrink: 0;
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 20px;
        }

        .item-name {
            font-weight: bold;
            color: #25233a;
            margin-bottom: 5px;
        }

        .item-code {
            color: #888;
            font-size: 11px;
        }

        .badge {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .warning {
            background: #fff3d8;
            color: #bd8010;
        }

        .success {
            background: #dcf7e8;
            color: #16834b;
        }

        .danger {
            background: #ffe2e2;
            color: #d13d3d;
        }

        .secondary {
            background: #eeeeF3;
            color: #666;
        }

        .type {
            display: inline-block;
            padding: 6px 9px;
            background: #f0edff;
            color: #6246d9;
            border-radius: 6px;
            font-size: 11px;
            font-weight: bold;
        }

        .detail-button {
            display: inline-block;
            padding: 9px 13px;
            border-radius: 7px;
            background: #6246d9;
            color: white;
            text-decoration: none;
            font-size: 12px;
            white-space: nowrap;
        }

        .detail-button:hover {
            background: #5035c0;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .delete-form {
            display: inline-block;
            margin: 0;
        }

        .delete-button {
            display: inline-block;
            padding: 9px 13px;
            border-radius: 7px;
            background: #ffe2e2;
            color: #c53030;
            border: none;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .delete-button:hover {
            background: #ffd0d0;
        }

        .action-unavailable {
            display: inline-block;
            color: #999;
            font-size: 11px;
            white-space: nowrap;
        }

        .flash-message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
        }

        .flash-success {
            background: #dcf7e8;
            color: #16834b;
            border: 1px solid #b9ebce;
        }

        .flash-error {
            background: #ffe2e2;
            color: #b83232;
            border: 1px solid #ffcaca;
        }

        .empty-state {
            background: white;
            border: 1px solid #e8e7ed;
            border-radius: 12px;
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 45px;
            color: #c7c1dc;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #888;
            font-size: 14px;
        }

        @media (max-width: 900px) {

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

            .filter-form input {
                grid-column: 1 / -1;
            }

        }

        @media (max-width: 500px) {

            body {
                padding: 18px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-form input {
                grid-column: auto;
            }

            .heading h1 {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- NAVBAR -->
    <div class="navbar">

        <a href="dashboard.php" class="brand">
            <i class="fa-solid fa-location-dot"></i>
            FIND & FOUND
        </a>

        <a href="dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Dashboard
        </a>

    </div>

    <!-- HEADING -->
    <section class="heading">

        <h1>Pemantauan Laporan</h1>

        <p>
            Pantau seluruh laporan kehilangan dan penemuan
            barang yang dikirimkan oleh mahasiswa.
        </p>

    </section>

    <?php if ($flashHapus): ?>
        <div class="flash-message <?= ($flashHapus['type'] ?? '') === 'success' ? 'flash-success' : 'flash-error' ?>">
            <?= e($flashHapus['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <!-- FILTER -->
    <section class="filter-card">

        <form method="GET"
            action="laporan-masuk.php"
            class="filter-form">

            <input
                type="text"
                name="keyword"
                placeholder="Cari nama barang, kode, atau pelapor..."
                value="<?= e($keyword) ?>"
            >

            <select name="jenis">

                <option value="">Semua Jenis</option>

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

                <option value="">Semua Status</option>

                <option value="menunggu_verifikasi"
                    <?= $filterStatus === 'menunggu_verifikasi' ? 'selected' : '' ?>>
                    Menunggu Verifikasi
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
                <i class="fa-solid fa-search"></i>
                Cari
            </button>

            <a href="laporan-masuk.php" class="reset">
                Reset
            </a>

        </form>

    </section>

    <!-- DAFTAR LAPORAN -->
    <section>

        <div class="section-heading">

            <h2>Daftar Laporan</h2>

            <span class="total">
                <?= count($laporan) ?> laporan ditemukan
            </span>

        </div>

        <?php if (count($laporan) > 0): ?>

            <div class="table-card">

                <table>

                    <thead>

                        <tr>
                            <th>Barang</th>
                            <th>Pelapor</th>
                            <th>Jenis</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Tanggal Kejadian</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($laporan as $item): ?>

                            <tr>

                                <!-- BARANG -->
                                <td>

                                    <div class="item-info">

                                        <?php if (!empty($item['foto'])): ?>

                                            <img
                                                src="../uploads/laporan/<?= e($item['foto']) ?>"
                                                alt="Foto barang"
                                            >

                                        <?php else: ?>

                                            <div class="no-image">
                                                <i class="fa-regular fa-image"></i>
                                            </div>

                                        <?php endif; ?>

                                        <div>

                                            <div class="item-name">
                                                <?= e($item['nama_barang']) ?>
                                            </div>

                                            <div class="item-code">
                                                <?= e($item['kode_laporan']) ?>
                                            </div>

                                        </div>

                                    </div>

                                </td>

                                <!-- PELAPOR -->
                                <td>
                                    <?= e($item['nama_pelapor']) ?>
                                </td>

                                <!-- JENIS -->
                                <td>

                                    <span class="type">
                                        <?= e(ucfirst($item['jenis_laporan'])) ?>
                                    </span>

                                </td>

                                <!-- KATEGORI -->
                                <td>
                                    <?= e($item['nama_kategori']) ?>
                                </td>

                                <!-- LOKASI -->
                                <td>
                                    <?= e($item['nama_lokasi']) ?>
                                </td>

                                <!-- TANGGAL -->
                                <td>
                                    <?= date(
                                        'd M Y',
                                        strtotime($item['tanggal_kejadian'])
                                    ) ?>
                                </td>

                                <!-- STATUS -->
                                <td>

                                    <span class="badge <?= e(warnaStatus($item['status'])) ?>">
                                        <?= e(labelStatus($item['status'])) ?>
                                    </span>

                                </td>

                                <!-- AKSI -->
                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="detail-laporan.php?id=<?= (int)$item['id'] ?>"
                                            class="detail-button"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                            Detail
                                        </a>

                                        <?php
                                        $bolehHapus =
                                            $item['status'] !== 'selesai' &&
                                            (int)$item['klaim_barang_aman_dihapus'] === 1 &&
                                            (int)$item['klaim_penemuan_aman_dihapus'] === 1 &&
                                            (int)$item['handover_aman_dihapus'] === 1;
                                        ?>

                                        <?php if ($bolehHapus): ?>
                                            <form
                                                action="proses-hapus-laporan.php"
                                                method="POST"
                                                class="delete-form"
                                                onsubmit="return confirm('Yakin ingin menghapus laporan <?= e($item['kode_laporan']) ?>? Data klaim dan foto terkait yang sudah tidak aktif juga akan dihapus. Tindakan ini tidak dapat dibatalkan.');"
                                            >
                                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfTokenHapus) ?>">
                                                <input type="hidden" name="keyword" value="<?= e($keyword) ?>">
                                                <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
                                                <input type="hidden" name="jenis" value="<?= e($filterJenis) ?>">
                                                <button type="submit" class="delete-button">
                                                    <i class="fa-solid fa-trash"></i>
                                                    Hapus
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="action-unavailable">
                                                <?= $item['status'] === 'selesai' ? 'Laporan selesai' : 'Klaim/serah terima terkait' ?>
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <i class="fa-regular fa-folder-open"></i>

                <h3>Tidak ada laporan ditemukan</h3>

                <p>
                    Belum ada laporan yang sesuai dengan
                    pencarian atau filter yang kamu pilih.
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>

</body>
</html>
