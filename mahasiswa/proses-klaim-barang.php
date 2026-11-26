
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
function kembaliKeDetail($reportId, $pesan)
{
    $_SESSION['flash_klaim_barang'] = $pesan;

    header('Location: detail-barang.php?id=' . (int) $reportId);
    exit;
}

// ==========================================
// 3. VALIDASI METODE REQUEST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cari-barang.php');
    exit;
}

// ==========================================
// 4. VALIDASI ID LAPORAN
// ==========================================
$reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId < 1) {
    kembaliKeDetail(0, 'ID laporan tidak valid.');
}

// ==========================================
// 5. VALIDASI TOKEN CSRF
// ==========================================
$csrfToken = $_POST['csrf_klaim_barang'] ?? '';

if (
    empty($_SESSION['csrf_klaim_barang']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_klaim_barang'], $csrfToken)
) {
    kembaliKeDetail(
        $reportId,
        'Permintaan tidak valid. Silakan muat ulang halaman dan coba kembali.'
    );
}

// ==========================================
// 6. VALIDASI DATA FORM
// ==========================================
$namaPemilik = trim($_POST['nama_pemilik'] ?? '');
$ciriBarang = trim($_POST['ciri_barang'] ?? '');

if (
    $namaPemilik === '' ||
    mb_strlen($namaPemilik) > 100
) {
    kembaliKeDetail(
        $reportId,
        'Nama pemilik wajib diisi dan maksimal 100 karakter.'
    );
}

if (
    mb_strlen($ciriBarang) < 10 ||
    mb_strlen($ciriBarang) > 1000
) {
    kembaliKeDetail(
        $reportId,
        'Ciri-ciri barang harus berisi 10 sampai 1000 karakter.'
    );
}

// ==========================================
// 7. VALIDASI FOTO (OPSIONAL)
// ==========================================
$fotoAda = false;
$namaFileSimpan = null;
$folderUpload = __DIR__ . '/../uploads/klaim/';

if (isset($_FILES['foto_klaim'])) {

    $foto = $_FILES['foto_klaim'];

    if (is_array($foto['error'])) {
        kembaliKeDetail(
            $reportId,
            'Format unggahan foto tidak valid.'
        );
    }

    // Tidak memilih foto bukan kesalahan
    if ($foto['error'] === UPLOAD_ERR_NO_FILE) {
        $fotoAda = false;
    } elseif ($foto['error'] === UPLOAD_ERR_OK) {

        $fotoAda = true;

        // Maksimal 5 MB
        $batasUkuran = 5 * 1024 * 1024;

        if ($foto['size'] > $batasUkuran) {
            kembaliKeDetail(
                $reportId,
                'Ukuran foto maksimal 5 MB.'
            );
        }

        if (
            !is_uploaded_file($foto['tmp_name']) ||
            !file_exists($foto['tmp_name'])
        ) {
            kembaliKeDetail(
                $reportId,
                'File foto tidak valid.'
            );
        }

        // Validasi MIME menggunakan isi file
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($foto['tmp_name']);

        $formatDiizinkan = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($formatDiizinkan[$mimeType])) {
            kembaliKeDetail(
                $reportId,
                'Format foto harus JPG, JPEG, PNG, atau WEBP.'
            );
        }

        // Pastikan file benar-benar gambar
        if (getimagesize($foto['tmp_name']) === false) {
            kembaliKeDetail(
                $reportId,
                'File yang diunggah bukan gambar yang valid.'
            );
        }

        // Nama file acak agar tidak menggunakan nama asli
        $namaFileSimpan = bin2hex(random_bytes(24))
            . '.'
            . $formatDiizinkan[$mimeType];

    } else {

        kembaliKeDetail(
            $reportId,
            'Terjadi kesalahan saat mengunggah foto.'
        );
    }
}

// ==========================================
// 8. PROSES DATABASE
// ==========================================
$pdo->beginTransaction();

$fileTersimpan = null;

try {

    // Pastikan laporan masih aktif dan merupakan barang ditemukan
    $stmtReport = $pdo->prepare("
        SELECT id, user_id
        FROM reports
        WHERE id = ?
          AND jenis_laporan = 'penemuan'
          AND status = 'aktif'
        LIMIT 1
        FOR UPDATE
    ");

    $stmtReport->execute([$reportId]);

    $barang = $stmtReport->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        throw new Exception(
            'Barang tidak ditemukan atau sudah tidak aktif.'
        );
    }

    // Pemilik laporan tidak boleh mengklaim barang sendiri
    if ((int) $barang['user_id'] === $userId) {
        throw new Exception(
            'Kamu tidak dapat mengklaim barang yang kamu laporkan sendiri.'
        );
    }

    // Cek pengajuan klaim yang masih berjalan
    $stmtCek = $pdo->prepare("
        SELECT id
        FROM claims
        WHERE report_id = ?
          AND user_id = ?
          AND status IN ('menunggu', 'disetujui')
        LIMIT 1
    ");

    $stmtCek->execute([$reportId, $userId]);

    if ($stmtCek->fetch()) {
        throw new Exception(
            'Kamu sudah memiliki pengajuan klaim yang sedang diproses untuk barang ini.'
        );
    }

    // Buat kode klaim
    $kodeKlaim = 'KLB-'
        . date('Ymd')
        . '-'
        . strtoupper(bin2hex(random_bytes(3)));

    // Simpan data klaim
    $stmtInsert = $pdo->prepare("
        INSERT INTO claims (
            kode_klaim,
            report_id,
            user_id,
            nama_pemilik,
            ciri_barang,
            tanggal_klaim,
            status
        ) VALUES (
            :kode_klaim,
            :report_id,
            :user_id,
            :nama_pemilik,
            :ciri_barang,
            :tanggal_klaim,
            'menunggu'
        )
    ");

    $stmtInsert->execute([
        ':kode_klaim' => $kodeKlaim,
        ':report_id' => $reportId,
        ':user_id' => $userId,
        ':nama_pemilik' => $namaPemilik,
        ':ciri_barang' => $ciriBarang,
        ':tanggal_klaim' => date('Y-m-d')
    ]);

    $claimId = (int) $pdo->lastInsertId();

    // ==========================================
    // 9. SIMPAN FOTO JIKA ADA
    // ==========================================
    if ($fotoAda) {

        if (
            !is_dir($folderUpload) &&
            !mkdir($folderUpload, 0755, true)
        ) {
            throw new Exception(
                'Folder penyimpanan foto tidak dapat dibuat.'
            );
        }

        $tujuanFoto = $folderUpload . $namaFileSimpan;

        if (!move_uploaded_file(
            $foto['tmp_name'],
            $tujuanFoto
        )) {
            throw new Exception(
                'Foto gagal disimpan. Silakan coba kembali.'
            );
        }

        $fileTersimpan = $tujuanFoto;

        // Simpan data foto ke tabel claim_evidences
        $stmtEvidence = $pdo->prepare("
            INSERT INTO claim_evidences (
                claim_id,
                nama_file,
                keterangan
            ) VALUES (?, ?, ?)
        ");

        $stmtEvidence->execute([
            $claimId,
            $namaFileSimpan,
            'Bukti foto kepemilikan barang'
        ]);
    }

    // ==========================================
    // 10. SELESAIKAN TRANSAKSI
    // ==========================================
    $pdo->commit();

    // Perbarui token CSRF
    $_SESSION['csrf_klaim_barang'] = bin2hex(random_bytes(32));

    $_SESSION['flash_klaim_barang'] =
        'Pengajuan klaim berhasil dikirim. Silakan tunggu pemeriksaan admin.';

    $_SESSION['klaim_barang_berhasil_id'] = $claimId;

    header('Location: status-klaim-barang.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hapus foto jika database gagal menyimpan
    if (
        $fileTersimpan !== null &&
        file_exists($fileTersimpan)
    ) {
        unlink($fileTersimpan);
    }

    kembaliKeDetail(
        $reportId,
        $e instanceof PDOException
            ? 'Terjadi kesalahan database. Pengajuan belum berhasil disimpan.'
            : $e->getMessage()
    );
}
