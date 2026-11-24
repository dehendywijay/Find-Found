
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// ==========================================
// 1. PROTEKSI LOGIN MAHASISWA
// ==========================================
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ==========================================
// 2. FUNGSI REDIRECT DAN NOTIFIKASI
// ==========================================
function kembali($type, $message)
{
    $_SESSION['flash_selesai_klaim_barang'] = [
        'type' => $type,
        'message' => $message
    ];

    header('Location: status-klaim-barang.php');
    exit;
}

// ==========================================
// 3. VALIDASI REQUEST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: status-klaim-barang.php');
    exit;
}

// ==========================================
// 4. VALIDASI CSRF
// ==========================================
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_selesai_klaim_barang']) ||
    !hash_equals(
        $_SESSION['csrf_selesai_klaim_barang'],
        $csrfToken
    )
) {
    kembali('error', 'Permintaan tidak valid. Silakan coba kembali.');
}

// ==========================================
// 5. VALIDASI ID KLAIM
// ==========================================
$claimId = filter_input(
    INPUT_POST,
    'claim_id',
    FILTER_VALIDATE_INT
);

if (!$claimId || $claimId < 1) {
    kembali('error', 'Data klaim tidak valid.');
}

try {

    $pdo->beginTransaction();

    // ==========================================
    // 6. AMBIL DAN KUNCI DATA KLAIM
    // ==========================================
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.report_id,
            c.user_id,
            c.status,
            r.jenis_laporan

        FROM claims c

        INNER JOIN reports r
            ON r.id = c.report_id

        WHERE c.id = :claim_id
          AND c.user_id = :user_id

        FOR UPDATE
    ");

    $stmt->execute([
        ':claim_id' => $claimId,
        ':user_id' => $userId
    ]);

    $klaim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$klaim) {
        $pdo->rollBack();
        kembali('error', 'Data klaim tidak ditemukan.');
    }

    // ==========================================
    // 7. PASTIKAN JENIS LAPORAN BENAR
    // ==========================================
    if ($klaim['jenis_laporan'] !== 'penemuan') {
        $pdo->rollBack();
        kembali('error', 'Klaim ini bukan klaim barang temuan.');
    }

    // ==========================================
    // 8. PASTIKAN KLAIM SUDAH DISETUJUI
    // ==========================================
    if ($klaim['status'] === 'selesai') {
        $pdo->rollBack();
        kembali('error', 'Klaim ini sudah diselesaikan sebelumnya.');
    }

    if ($klaim['status'] !== 'disetujui') {
        $pdo->rollBack();
        kembali('error', 'Klaim belum disetujui oleh admin.');
    }

    // ==========================================
    // 9. UBAH STATUS KLAIM MENJADI SELESAI
    // ==========================================
    $stmt = $pdo->prepare("
        UPDATE claims
        SET
            status = 'selesai',
            updated_at = NOW()

        WHERE id = :claim_id
          AND user_id = :user_id
          AND status = 'disetujui'
    ");

    $stmt->execute([
        ':claim_id' => $claimId,
        ':user_id' => $userId
    ]);

    // ==========================================
    // 10. UBAH STATUS LAPORAN BARANG
    // ==========================================
    $stmt = $pdo->prepare("
        UPDATE reports
        SET status = 'selesai'
        WHERE id = :report_id
          AND jenis_laporan = 'penemuan'
    ");

    $stmt->execute([
        ':report_id' => $klaim['report_id']
    ]);

    // ==========================================
    // 11. SIMPAN TRANSAKSI
    // ==========================================
    $pdo->commit();

    // Ganti token CSRF setelah berhasil
    $_SESSION['csrf_selesai_klaim_barang'] = bin2hex(random_bytes(32));

    kembali(
        'success',
        'Klaim berhasil diselesaikan. Status barang sekarang sudah selesai.'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Gagal menyelesaikan klaim barang: ' . $e->getMessage());

    kembali(
        'error',
        'Terjadi kesalahan saat menyelesaikan klaim. Silakan coba kembali.'
    );
}
