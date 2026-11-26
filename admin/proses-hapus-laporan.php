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
// 2. FUNGSI REDIRECT DAN PESAN
// ==========================================
function kembaliKeLaporan($type, $message)
{
    $_SESSION['flash_hapus_laporan'] = [
        'type' => $type,
        'message' => $message
    ];

    $query = http_build_query([
        'keyword' => trim($_POST['keyword'] ?? ''),
        'status' => trim($_POST['status'] ?? ''),
        'jenis' => trim($_POST['jenis'] ?? '')
    ]);

    header('Location: laporan-masuk.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

// ==========================================
// 3. VALIDASI REQUEST DAN CSRF
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: laporan-masuk.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_hapus_laporan']) ||
    !hash_equals($_SESSION['csrf_hapus_laporan'], $csrfToken)
) {
    kembaliKeLaporan('error', 'Permintaan tidak valid. Silakan muat ulang halaman dan coba kembali.');
}

$reportId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$reportId || $reportId <= 0) {
    kembaliKeLaporan('error', 'ID laporan tidak valid.');
}

// File fisik baru dihapus setelah transaksi database berhasil.
$fileLaporanHapus = [];
$fileKlaimHapus = [];

try {
    $pdo->beginTransaction();

    // Kunci laporan agar statusnya tidak berubah saat proses penghapusan.
    $stmtReport = $pdo->prepare("
        SELECT id, kode_laporan, nama_barang, jenis_laporan, status
        FROM reports
        WHERE id = :id
        FOR UPDATE
    ");
    $stmtReport->execute([':id' => $reportId]);
    $laporan = $stmtReport->fetch(PDO::FETCH_ASSOC);

    if (!$laporan) {
        $pdo->rollBack();
        kembaliKeLaporan('error', 'Laporan tidak ditemukan atau sudah dihapus.');
    }

    // Laporan selesai selalu dilindungi dari penghapusan.
    if ($laporan['status'] === 'selesai') {
        $pdo->rollBack();
        kembaliKeLaporan('error', 'Laporan yang berstatus Selesai tidak dapat dihapus.');
    }

    // Ambil dan kunci semua klaim barang terkait laporan.
    $stmtClaims = $pdo->prepare("
        SELECT id, status
        FROM claims
        WHERE report_id = :report_id
        FOR UPDATE
    ");
    $stmtClaims->execute([':report_id' => $reportId]);
    $claims = $stmtClaims->fetchAll(PDO::FETCH_ASSOC);

    foreach ($claims as $claim) {
        if (!in_array($claim['status'], ['ditolak', 'dibatalkan'], true)) {
            $pdo->rollBack();
            kembaliKeLaporan('error', 'Laporan tidak dapat dihapus karena masih memiliki klaim barang yang menunggu, disetujui, atau sudah selesai.');
        }
    }

    // Ambil dan kunci semua klaim penemuan terkait laporan.
    $stmtFindingClaims = $pdo->prepare("
        SELECT id, status
        FROM finding_claims
        WHERE report_id = :report_id
        FOR UPDATE
    ");
    $stmtFindingClaims->execute([':report_id' => $reportId]);
    $findingClaims = $stmtFindingClaims->fetchAll(PDO::FETCH_ASSOC);

    foreach ($findingClaims as $findingClaim) {
        if ($findingClaim['status'] !== 'ditolak') {
            $pdo->rollBack();
            kembaliKeLaporan('error', 'Laporan tidak dapat dihapus karena masih memiliki klaim penemuan yang belum ditolak.');
        }
    }

    $claimIds = array_map('intval', array_column($claims, 'id'));
    $findingClaimIds = array_map('intval', array_column($findingClaims, 'id'));

    // Ambil bukti foto klaim barang.
    if ($claimIds) {
        $placeholders = implode(',', array_fill(0, count($claimIds), '?'));

        $stmtEvidence = $pdo->prepare("
            SELECT nama_file
            FROM claim_evidences
            WHERE claim_id IN ($placeholders)
        ");
        $stmtEvidence->execute($claimIds);
        $fileKlaimHapus = array_merge(
            $fileKlaimHapus,
            array_column($stmtEvidence->fetchAll(PDO::FETCH_ASSOC), 'nama_file')
        );

        // Handover aktif/selesai harus dipertahankan sebagai riwayat.
        $stmtHandover = $pdo->prepare("
            SELECT h.id, h.status
            FROM handovers h
            WHERE h.claim_id IN ($placeholders)
            FOR UPDATE
        ");
        $stmtHandover->execute($claimIds);
        $handovers = $stmtHandover->fetchAll(PDO::FETCH_ASSOC);

        foreach ($handovers as $handover) {
            if ($handover['status'] !== 'dibatalkan') {
                $pdo->rollBack();
                kembaliKeLaporan('error', 'Laporan tidak dapat dihapus karena memiliki proses serah terima yang belum dibatalkan.');
            }
        }

        // Hapus handover yang sudah dibatalkan agar klaim dapat dihapus.
        $stmtDeleteHandovers = $pdo->prepare("
            DELETE FROM handovers
            WHERE claim_id IN ($placeholders)
              AND status = 'dibatalkan'
        ");
        $stmtDeleteHandovers->execute($claimIds);

        // Hapus baris bukti terlebih dahulu; file fisiknya setelah commit.
        $stmtDeleteEvidence = $pdo->prepare("
            DELETE FROM claim_evidences
            WHERE claim_id IN ($placeholders)
        ");
        $stmtDeleteEvidence->execute($claimIds);

        $stmtDeleteClaims = $pdo->prepare("
            DELETE FROM claims
            WHERE report_id = ?
        ");
        $stmtDeleteClaims->execute([$reportId]);
    }

    // Ambil dan hapus bukti foto klaim penemuan.
    if ($findingClaimIds) {
        $placeholdersFinding = implode(',', array_fill(0, count($findingClaimIds), '?'));

        $stmtFindingEvidence = $pdo->prepare("
            SELECT nama_file
            FROM finding_claim_evidences
            WHERE finding_claim_id IN ($placeholdersFinding)
        ");
        $stmtFindingEvidence->execute($findingClaimIds);
        $fileKlaimHapus = array_merge(
            $fileKlaimHapus,
            array_column($stmtFindingEvidence->fetchAll(PDO::FETCH_ASSOC), 'nama_file')
        );

        $stmtDeleteFindingEvidence = $pdo->prepare("
            DELETE FROM finding_claim_evidences
            WHERE finding_claim_id IN ($placeholdersFinding)
        ");
        $stmtDeleteFindingEvidence->execute($findingClaimIds);

        $stmtDeleteFindingClaims = $pdo->prepare("
            DELETE FROM finding_claims
            WHERE report_id = ?
        ");
        $stmtDeleteFindingClaims->execute([$reportId]);
    }

    // Ambil seluruh foto laporan.
    $stmtImages = $pdo->prepare("
        SELECT nama_file
        FROM report_images
        WHERE report_id = :report_id
    ");
    $stmtImages->execute([':report_id' => $reportId]);
    $fileLaporanHapus = array_column(
        $stmtImages->fetchAll(PDO::FETCH_ASSOC),
        'nama_file'
    );

    // Putuskan tautan dari laporan penemuan lain yang merujuk laporan ini.
    // Postingan lain tetap ada, hanya referensinya yang dikosongkan.
    $stmtUnlink = $pdo->prepare("
        UPDATE reports
        SET referensi_laporan_id = NULL
        WHERE referensi_laporan_id = :report_id
    ");
    $stmtUnlink->execute([':report_id' => $reportId]);

    // Hapus data gambar laporan dan laporan utama.
    $stmtDeleteImages = $pdo->prepare("
        DELETE FROM report_images
        WHERE report_id = :report_id
    ");
    $stmtDeleteImages->execute([':report_id' => $reportId]);

    $stmtDeleteReport = $pdo->prepare("
        DELETE FROM reports
        WHERE id = :report_id
    ");
    $stmtDeleteReport->execute([':report_id' => $reportId]);

    if ($stmtDeleteReport->rowCount() !== 1) {
        throw new RuntimeException('Data laporan tidak berhasil dihapus.');
    }

    // Catat aktivitas admin pada audit log.
    $keterangan = sprintf(
        'Menghapus laporan %s (%s), jenis %s, ID %d. Klaim yang sudah ditolak/dibatalkan beserta bukti terkait ikut dihapus.',
        $laporan['kode_laporan'],
        $laporan['nama_barang'],
        $laporan['jenis_laporan'],
        $reportId
    );

    $stmtAudit = $pdo->prepare("
        INSERT INTO audit_logs (user_id, aktivitas, keterangan, ip_address)
        VALUES (:user_id, :aktivitas, :keterangan, :ip_address)
    ");
    $stmtAudit->execute([
        ':user_id' => $_SESSION['user_id'],
        ':aktivitas' => 'Hapus Laporan',
        ':keterangan' => $keterangan,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Siapkan token baru sebelum commit agar tidak terjadi error setelah data terhapus.
    $tokenBaru = bin2hex(random_bytes(32));

    $pdo->commit();

    // Hapus file fisik setelah transaksi berhasil. File yang masih dipakai
    // oleh baris data lain tidak akan ikut dihapus.
    $hapusFileAman = static function (
        array $namaFileList,
        string $folder,
        array $tabelReferensi
    ) use ($pdo): void {
        try {
            $folderReal = realpath($folder);
            if ($folderReal === false) {
                return;
            }

            foreach (array_unique($namaFileList) as $namaFile) {
                $namaFile = basename((string)$namaFile);
                if ($namaFile === '' || $namaFile === '.' || $namaFile === '..') {
                    continue;
                }

                // Jangan hapus file jika masih direferensikan data lain.
                $masihDipakai = false;
                foreach ($tabelReferensi as $tabel) {
                    $stmtCekFile = $pdo->prepare(
                        "SELECT 1 FROM {$tabel} WHERE nama_file = :nama_file LIMIT 1"
                    );
                    $stmtCekFile->execute([':nama_file' => $namaFile]);
                    if ($stmtCekFile->fetchColumn()) {
                        $masihDipakai = true;
                        break;
                    }
                }

                if ($masihDipakai) {
                    continue;
                }

                $path = $folderReal . DIRECTORY_SEPARATOR . $namaFile;
                $pathReal = realpath($path);

                // Pastikan file yang dihapus benar-benar berada di folder upload.
                if (
                    $pathReal !== false &&
                    is_file($pathReal) &&
                    strpos($pathReal, $folderReal . DIRECTORY_SEPARATOR) === 0
                ) {
                    if (!@unlink($pathReal)) {
                        error_log('Gagal menghapus file upload: ' . $pathReal);
                    }
                }
            }
        } catch (Throwable $cleanupError) {
            // Data database sudah berhasil dihapus; kegagalan file cleanup dicatat.
            error_log('Gagal membersihkan file upload setelah penghapusan laporan: ' . $cleanupError->getMessage());
        }
    };

    $hapusFileAman(
        $fileLaporanHapus,
        __DIR__ . '/../uploads/laporan',
        ['report_images']
    );
    $hapusFileAman(
        $fileKlaimHapus,
        __DIR__ . '/../uploads/klaim',
        ['claim_evidences', 'finding_claim_evidences']
    );

    // Perbarui token setelah berhasil.
    $_SESSION['csrf_hapus_laporan'] = $tokenBaru;

    kembaliKeLaporan(
        'success',
        'Laporan ' . $laporan['kode_laporan'] . ' berhasil dihapus. Foto dan data klaim yang sudah tidak aktif juga telah dibersihkan.'
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Gagal menghapus laporan ID ' . $reportId . ': ' . $e->getMessage());

    kembaliKeLaporan(
        'error',
        'Terjadi kesalahan saat menghapus laporan. Tidak ada perubahan database yang disimpan.'
    );
}
