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

// ==========================================
// 2. TOKEN CSRF
// ==========================================
if (empty($_SESSION['csrf_klaim_penemuan'])) {
    $_SESSION['csrf_klaim_penemuan'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_klaim_penemuan'];

// ==========================================
// 3. AMBIL FILTER
// ==========================================
$keyword = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';

$statusValid = [
    'menunggu',
    'disetujui',
    'ditolak',
    'selesai'
];

if (!in_array($filterStatus, $statusValid, true)) {
    $filterStatus = '';
}

// ==========================================
// 4. QUERY DATA KLAIM PENEMUAN
// ==========================================
$sql = "
    SELECT
        fc.id,
        fc.kode_klaim,
        fc.report_id,
        fc.user_id,
        fc.detail_penemuan,
        fc.status,
        fc.catatan_admin,
        fc.created_at,

        r.kode_laporan,
        r.nama_barang,
        r.detail_lokasi,

        u.nama AS nama_penemu

    FROM finding_claims fc

    INNER JOIN reports r
        ON r.id = fc.report_id

    INNER JOIN users u
        ON u.id = fc.user_id

    WHERE 1 = 1
";

$params = [];

// ==========================================
// 5. PENCARIAN
// ==========================================
if ($keyword !== '') {
    $sql .= "
        AND (
            fc.kode_klaim LIKE :kode_klaim
            OR r.kode_laporan LIKE :kode_laporan
            OR r.nama_barang LIKE :nama_barang
            OR u.nama LIKE :nama_penemu
        )
    ";

    $params[':kode_klaim'] = '%' . $keyword . '%';
    $params[':kode_laporan'] = '%' . $keyword . '%';
    $params[':nama_barang'] = '%' . $keyword . '%';
    $params[':nama_penemu'] = '%' . $keyword . '%';
}

// ==========================================
// 6. FILTER STATUS
// ==========================================
if ($filterStatus !== '') {
    $sql .= " AND fc.status = :status";
    $params[':status'] = $filterStatus;
}

$sql .= " ORDER BY fc.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$klaimPenemuan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 7. FUNGSI BANTU
// ==========================================
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function labelStatus($status)
{
    $labels = [
        'menunggu' => 'Menunggu',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

function warnaStatus($status)
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

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Laporan Klaim Penemuan - FIND & FOUND</title>
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
            grid-template-columns: 2fr 1fr auto auto;
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

        button:hover {
            background: #5035c0;
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
            min-width: 1050px;
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

        .status-form {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 135px;
        }

        .status-form select {
            padding: 9px;
            font-size: 12px;
        }

        .save-button {
            padding: 8px 10px;
            font-size: 12px;
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

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-success {
            background: #dcf7e8;
            color: #16834b;
        }

        .alert-error {
            background: #ffe2e2;
            color: #d13d3d;
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

            textarea {
                width: 190px;
                min-height: 65px;
                padding: 9px;
                border: 1px solid #ddd;
                border-radius: 8px;
                resize: vertical;
                font-family: Arial, sans-serif;
                font-size: 12px;
                outline: none;
            }

            textarea:focus {
                border-color: #6246d9;
            }

            textarea[readonly] {
                background: #f5f5f8;
                color: #777;
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

            <h1>Laporan Klaim Penemuan</h1>

            <p>
                Kelola laporan mahasiswa yang menemukan barang
                berdasarkan laporan kehilangan.
            </p>

        </section>

        <!-- NOTIFIKASI -->
        <?php if (!empty($_SESSION['flash_klaim_penemuan'])): ?>

            <?php
            $flash = $_SESSION['flash_klaim_penemuan'];
            unset($_SESSION['flash_klaim_penemuan']);
            ?>

            <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= e($flash['message'] ?? '') ?>
            </div>

        <?php endif; ?>

        <!-- FILTER -->
        <section class="filter-card">

            <form method="GET"
                action="laporan-klaim-penemuan.php"
                class="filter-form">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari kode klaim, barang, atau nama penemu..."
                    value="<?= e($keyword) ?>">

                <select name="status">

                    <option value="">Semua Status</option>

                    <option value="menunggu"
                        <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>>
                        Menunggu
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
                    <i class="fa-solid fa-search"></i>
                    Cari
                </button>

                <a href="laporan-klaim-penemuan.php" class="reset">
                    Reset
                </a>

            </form>

        </section>

        <!-- DAFTAR KLAIM -->
        <section>

            <div class="section-heading">

                <h2>Daftar Klaim Penemuan</h2>

                <span class="total">
                    <?= count($klaimPenemuan) ?> klaim ditemukan
                </span>

            </div>

            <?php if (count($klaimPenemuan) > 0): ?>

                <div class="table-card">

                    <table>

                        <thead>
                            <tr>
                                <th>Kode Klaim</th>
                                <th>Barang</th>
                                <th>Nama Penemu</th>
                                <th>Kode Laporan</th>
                                <th>Tanggal Klaim</th>
                                <th>Status</th>
                                <th>Pesan Admin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($klaimPenemuan as $item): ?>

                                <tr>

                                    <td>
                                        <div class="item-name">
                                            <?= e($item['kode_klaim']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="item-name">
                                            <?= e($item['nama_barang']) ?>
                                        </div>
                                        <div class="item-code">
                                            <?= e($item['detail_lokasi']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= e($item['nama_penemu']) ?>
                                    </td>

                                    <td>
                                        <?= e($item['kode_laporan']) ?>
                                    </td>

                                    <td>
                                        <?= !empty($item['created_at'])
                                            ? date('d M Y', strtotime($item['created_at']))
                                            : '-' ?>
                                    </td>

                                    <!-- STATUS -->
                                    <td>

                                        <?php if ($item['status'] === 'selesai'): ?>

                                            <span class="badge secondary">
                                                Selesai
                                            </span>

                                        <?php else: ?>

                                            <form
                                                id="form-klaim-<?= (int)$item['id'] ?>"
                                                method="POST"
                                                action="proses-status-klaim-penemuan.php"
                                                class="status-form">

                                                <input type="hidden" name="csrf_token"
                                                    value="<?= e($csrfToken) ?>">

                                                <input type="hidden" name="id"
                                                    value="<?= (int)$item['id'] ?>">

                                                <input type="hidden" name="keyword"
                                                    value="<?= e($keyword) ?>">

                                                <input type="hidden" name="filter_status"
                                                    value="<?= e($filterStatus) ?>">

                                                <select name="status" required>

                                                    <option value="menunggu"
                                                        <?= $item['status'] === 'menunggu' ? 'selected' : '' ?>>
                                                        Menunggu
                                                    </option>

                                                    <option value="disetujui"
                                                        <?= $item['status'] === 'disetujui' ? 'selected' : '' ?>>
                                                        Disetujui
                                                    </option>

                                                    <option value="ditolak"
                                                        <?= $item['status'] === 'ditolak' ? 'selected' : '' ?>>
                                                        Ditolak
                                                    </option>

                                                    <?php if ($item['status'] === 'disetujui'): ?>
                                                        <option value="selesai">Selesai</option>
                                                    <?php endif; ?>

                                                </select>

                                                <button type="submit" class="save-button">
                                                    <i class="fa-solid fa-save"></i>
                                                    Simpan
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </td>

                                    <!-- PESAN ADMIN -->
                                    <td>

                                        <?php if ($item['status'] === 'selesai'): ?>

                                            <textarea
                                                rows="2"
                                                readonly
                                                placeholder="Tidak ada pesan"><?= e($item['catatan_admin'] ?? '') ?></textarea>

                                        <?php else: ?>

                                            <textarea
                                                name="pesan_admin"
                                                form="form-klaim-<?= (int)$item['id'] ?>"
                                                rows="2"
                                                maxlength="1000"
                                                placeholder="Tulis pesan untuk mahasiswa..."><?= e($item['catatan_admin'] ?? '') ?></textarea>

                                        <?php endif; ?>

                                    </td>

                                    <!-- AKSI -->
                                    <td>

                                        <a
                                            href="detail-klaim-penemuan.php?id=<?= (int)$item['id'] ?>"
                                            class="detail-button">
                                            <i class="fa-solid fa-eye"></i>
                                            Detail
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-regular fa-folder-open"></i>

                    <h3>Belum ada klaim penemuan</h3>

                    <p>
                        Belum ada laporan klaim penemuan yang sesuai
                        dengan pencarian atau filter yang dipilih.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </div>

</body>

</html>