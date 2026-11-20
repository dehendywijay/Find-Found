
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Proteksi mahasiswa
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

function kembali($type, $message)
{
    $_SESSION['flash_selesai_laporan'] = [
        'type' => $type,
        'message' => $message
    ];

    header('Location: status-laporan.php');
    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: status-laporan.php');
    exit;
}

// Validasi CSRF
$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_selesai_laporan']) ||
    !hash_equals($_SESSION['csrf_selesai_laporan'], $token)
) {
    kembali('error', 'Permintaan tidak valid. Silakan coba kembali.');
}

$reportId = filter_var(
    $_POST['report_id'] ?? null,
    FILTER_VALIDATE_INT
);

$claimId = filter_var(
    $_POST['finding_claim_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$reportId || !$claimId) {
    kembali('error', 'Data laporan tidak valid.');
}

try {

    $pdo->beginTransaction();

    // Ambil laporan dan klaim sekaligus
    $stmt = $pdo->prepare("
        SELECT
            r.id AS report_id,
            r.user_id AS pemilik_id,
            r.jenis_laporan,
            r.status AS status_laporan,

            fc.id AS claim_id,
            fc.status AS status_klaim

        FROM reports r

        INNER JOIN finding_claims fc
            ON fc.report_id = r.id

        WHERE r.id = :report_id
          AND fc.id = :claim_id

        FOR UPDATE
    ");

    $stmt->execute([
        ':report_id' => $reportId,
        ':claim_id' => $claimId
    ]);

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        $pdo->rollBack();
        kembali('error', 'Laporan atau klaim penemuan tidak ditemukan.');
    }

    // Pastikan yang mengonfirmasi adalah pemilik barang
    if ((int)$data['pemilik_id'] !== (int)$_SESSION['user_id']) {
        $pdo->rollBack();
        kembali('error', 'Kamu tidak memiliki akses untuk menyelesaikan laporan ini.');
    }

    // Pastikan jenis laporan adalah kehilangan
    if ($data['jenis_laporan'] !== 'kehilangan') {
        $pdo->rollBack();
        kembali('error', 'Jenis laporan tidak sesuai.');
    }

    // Cegah konfirmasi berulang
    if (
        $data['status_laporan'] === 'selesai' &&
        $data['status_klaim'] === 'selesai'
    ) {
        $pdo->commit();
        kembali('success', 'Laporan ini sudah selesai sebelumnya.');
    }

    // Hanya klaim yang disetujui yang bisa diselesaikan
    if ($data['status_klaim'] !== 'disetujui') {
        $pdo->rollBack();
        kembali('error', 'Klaim penemuan belum disetujui admin.');
    }

    // Update status klaim
    $stmt = $pdo->prepare("
        UPDATE finding_claims
        SET
            status = 'selesai',
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $claimId
    ]);

    // Update status laporan kehilangan
    $stmt = $pdo->prepare("
        UPDATE reports
        SET status = 'selesai'
        WHERE id = :id
          AND user_id = :user_id
          AND jenis_laporan = 'kehilangan'
    ");

    $stmt->execute([
        ':id' => $reportId,
        ':user_id' => $_SESSION['user_id']
    ]);

    $pdo->commit();

    $_SESSION['csrf_selesai_laporan'] = bin2hex(random_bytes(32));

    kembali(
        'success',
        'Terima kasih! Laporan berhasil ditandai selesai.'
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Proses selesai penemuan: ' . $e->getMessage());

    kembali(
        'error',
        'Terjadi kesalahan saat menyelesaikan laporan.'
    );
}
