
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// 1. Proteksi admin
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

// 2. Fungsi redirect
function kembali($type, $message)
{
    $_SESSION['flash_klaim_barang'] = [
        'type' => $type,
        'message' => $message
    ];

    $query = http_build_query([
        'keyword' => trim($_POST['keyword'] ?? ''),
        'status' => trim($_POST['filter_status'] ?? '')
    ]);

    header('Location: laporan-klaim-barang.php?' . $query);
    exit;
}

// 3. Validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: laporan-klaim-barang.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_klaim_barang']) ||
    !hash_equals($_SESSION['csrf_klaim_barang'], $csrfToken)
) {
    kembali('error', 'Permintaan tidak valid. Silakan coba kembali.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$statusBaru = $_POST['status'] ?? '';
$pesanAdmin = trim($_POST['pesan_admin'] ?? '');

$statusValid = [
    'menunggu',
    'disetujui',
    'ditolak',
    'selesai'
];

if (!$id || !in_array($statusBaru, $statusValid, true)) {
    kembali('error', 'Data perubahan status tidak valid.');
}

if (mb_strlen($pesanAdmin) > 1000) {
    kembali('error', 'Pesan admin maksimal 1000 karakter.');
}

// 4. Proses perubahan status
try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.report_id,
            c.status,
            r.jenis_laporan

        FROM claims c

        INNER JOIN reports r
            ON r.id = c.report_id

        WHERE c.id = :id

        FOR UPDATE
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $klaim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$klaim) {
        $pdo->rollBack();
        kembali('error', 'Data klaim barang tidak ditemukan.');
    }

    if ($klaim['jenis_laporan'] !== 'penemuan') {
        $pdo->rollBack();
        kembali('error', 'Klaim ini tidak terhubung dengan laporan barang ditemukan.');
    }

    if ($klaim['status'] === 'selesai') {
        $pdo->rollBack();
        kembali('error', 'Klaim yang sudah selesai tidak dapat diubah kembali.');
    }

    if (
        $statusBaru === 'selesai' &&
        $klaim['status'] !== 'disetujui'
    ) {
        $pdo->rollBack();
        kembali('error', 'Klaim harus disetujui terlebih dahulu sebelum dinyatakan selesai.');
    }

    // 5. Simpan status dan pesan admin
    $stmtUpdate = $pdo->prepare("
        UPDATE claims

        SET
            status = :status,
            catatan_admin = :pesan_admin,
            diperiksa_oleh = :admin_id,
            diperiksa_pada = NOW(),
            updated_at = NOW()

        WHERE id = :id
    ");

    $stmtUpdate->execute([
        ':status' => $statusBaru,
        ':pesan_admin' => $pesanAdmin !== '' ? $pesanAdmin : null,
        ':admin_id' => $_SESSION['user_id'],
        ':id' => $id
    ]);

    // 6. Jika klaim selesai, status laporan barang menjadi selesai
    if ($statusBaru === 'selesai') {

        $stmtReport = $pdo->prepare("
            UPDATE reports

            SET status = 'selesai'

            WHERE id = :report_id
              AND jenis_laporan = 'penemuan'
        ");

        $stmtReport->execute([
            ':report_id' => $klaim['report_id']
        ]);
    }

    // 7. Simpan seluruh perubahan
    $pdo->commit();

    // Perbarui token CSRF
    $_SESSION['csrf_klaim_barang'] = bin2hex(random_bytes(32));

    $pesan = [
        'menunggu' => 'Status klaim barang berhasil diubah menjadi Menunggu.',
        'disetujui' => 'Klaim barang berhasil disetujui.',
        'ditolak' => 'Klaim barang berhasil ditolak.',
        'selesai' => 'Klaim selesai. Status barang juga berhasil diperbarui menjadi selesai.'
    ];

    kembali('success', $pesan[$statusBaru]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Gagal mengubah status klaim barang: ' . $e->getMessage());

    kembali('error', 'Terjadi kesalahan saat menyimpan perubahan status.');
}
