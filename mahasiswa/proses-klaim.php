
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// ==========================================
// 1. PROTEKSI LOGIN
// ==========================================

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cari-barang.php');
    exit;
}

// ==========================================
// 2. FUNGSI KEMBALI KE FORM
// ==========================================

function kembaliKeForm($reportId, $pesan)
{
    $_SESSION['flash_klaim'] = $pesan;

    header('Location: form-klaim.php?id=' . (int)$reportId);
    exit;
}

// ==========================================
// 3. VALIDASI ID DAN CSRF
// ==========================================

$reportId = filter_var(
    $_POST['report_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$reportId || $reportId < 1) {
    header('Location: cari-barang.php');
    exit;
}

$token = $_POST['csrf_klaim'] ?? '';

if (
    empty($_SESSION['csrf_klaim']) ||
    !hash_equals($_SESSION['csrf_klaim'], $token)
) {
    kembaliKeForm(
        $reportId,
        'Sesi formulir tidak valid. Silakan muat ulang halaman.'
    );
}

// ==========================================
// 4. AMBIL DATA FORMULIR
// ==========================================

$userId = (int)$_SESSION['user_id'];
$detailKlaim = trim($_POST['detail_klaim'] ?? '');

if ($detailKlaim === '') {
    kembaliKeForm(
        $reportId,
        'Detail barang yang ditemukan wajib diisi.'
    );
}

if (mb_strlen($detailKlaim) < 10) {
    kembaliKeForm(
        $reportId,
        'Detail barang minimal 10 karakter.'
    );
}

if (mb_strlen($detailKlaim) > 1000) {
    kembaliKeForm(
        $reportId,
        'Detail barang maksimal 1.000 karakter.'
    );
}

// ==========================================
// 5. PERIKSA LAPORAN KEHILANGAN
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        id,
        user_id,
        nama_barang,
        jenis_laporan,
        status
    FROM reports
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$reportId]);
$laporan = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$laporan ||
    $laporan['jenis_laporan'] !== 'kehilangan' ||
    $laporan['status'] !== 'aktif'
) {
    kembaliKeForm(
        $reportId,
        'Laporan kehilangan tidak tersedia atau sudah tidak aktif.'
    );
}

// Pengaju tidak boleh menjadi pemilik laporan
if ((int)$laporan['user_id'] === $userId) {
    kembaliKeForm(
        $reportId,
        'Kamu tidak dapat mengajukan klaim pada laporan sendiri.'
    );
}

// ==========================================
// 6. PERIKSA KLAIM PENEMUAN GANDA
// ==========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM finding_claims
    WHERE report_id = ?
      AND user_id = ?
      AND status IN ('menunggu', 'disetujui')
    LIMIT 1
");

$stmt->execute([$reportId, $userId]);

if ($stmt->fetch()) {
    kembaliKeForm(
        $reportId,
        'Kamu sudah memiliki pengajuan klaim penemuan yang masih diproses.'
    );
}

// ==========================================
// 7. VALIDASI FOTO
// ==========================================

if (!isset($_FILES['foto_klaim'])) {
    kembaliKeForm(
        $reportId,
        'Foto barang yang ditemukan wajib diunggah.'
    );
}

$file = $_FILES['foto_klaim'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $pesanUpload = [
        UPLOAD_ERR_INI_SIZE => 'Ukuran foto melebihi batas server.',
        UPLOAD_ERR_FORM_SIZE => 'Ukuran foto terlalu besar.',
        UPLOAD_ERR_PARTIAL => 'Foto hanya terunggah sebagian.',
        UPLOAD_ERR_NO_FILE => 'Foto barang wajib dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload tidak tersedia.',
        UPLOAD_ERR_CANT_WRITE => 'Foto gagal ditulis ke server.',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh server.'
    ];

    kembaliKeForm(
        $reportId,
        $pesanUpload[$file['error']] ?? 'Terjadi kesalahan saat upload foto.'
    );
}

if (
    empty($file['tmp_name']) ||
    !is_uploaded_file($file['tmp_name'])
) {
    kembaliKeForm(
        $reportId,
        'File foto yang diunggah tidak valid.'
    );
}

// Maksimal 5 MB
$ukuranFile = (int)$file['size'];

if ($ukuranFile <= 0 || $ukuranFile > 5 * 1024 * 1024) {
    kembaliKeForm(
        $reportId,
        'Ukuran foto maksimal 5 MB.'
    );
}

// Periksa MIME berdasarkan isi file
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$daftarMime = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!isset($daftarMime[$mime])) {
    kembaliKeForm(
        $reportId,
        'Format foto hanya boleh JPG, PNG, atau WEBP.'
    );
}

// Pastikan file benar-benar gambar
$infoGambar = @getimagesize($file['tmp_name']);

if (
    $infoGambar === false ||
    ($infoGambar['mime'] ?? '') !== $mime
) {
    kembaliKeForm(
        $reportId,
        'File yang diunggah bukan gambar yang valid.'
    );
}

// ==========================================
// 8. SIAPKAN FOLDER PENYIMPANAN FOTO
// ==========================================

$folderUpload = __DIR__ . '/../uploads/klaim/';

if (
    !is_dir($folderUpload) &&
    !mkdir($folderUpload, 0750, true) &&
    !is_dir($folderUpload)
) {
    kembaliKeForm(
        $reportId,
        'Folder penyimpanan foto gagal dibuat.'
    );
}

$ekstensi = $daftarMime[$mime];

$namaFileSimpan = bin2hex(random_bytes(24)) . '.' . $ekstensi;

$lokasiFile = $folderUpload . $namaFileSimpan;

$fileBerhasilDipindahkan = false;

// ==========================================
// 9. SIMPAN KLAIM PENEMUAN
// ==========================================

try {

    $pdo->beginTransaction();

    // Kunci laporan selama proses penyimpanan
    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            jenis_laporan,
            status
        FROM reports
        WHERE id = ?
        FOR UPDATE
    ");

    $stmt->execute([$reportId]);
    $laporanTerbaru = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$laporanTerbaru ||
        $laporanTerbaru['jenis_laporan'] !== 'kehilangan' ||
        $laporanTerbaru['status'] !== 'aktif' ||
        (int)$laporanTerbaru['user_id'] === $userId
    ) {
        throw new RuntimeException(
            'Laporan tidak lagi memenuhi syarat klaim.'
        );
    }

    // Periksa kembali klaim aktif
    $stmt = $pdo->prepare("
        SELECT id
        FROM finding_claims
        WHERE report_id = ?
          AND user_id = ?
          AND status IN ('menunggu', 'disetujui')
        LIMIT 1
    ");

    $stmt->execute([$reportId, $userId]);

    if ($stmt->fetch()) {
        throw new RuntimeException(
            'Pengajuan klaim penemuan sudah pernah dikirim.'
        );
    }

    // Buat kode klaim
    $kodeKlaim = 'FND-' .
        date('Ymd') . '-' .
        strtoupper(bin2hex(random_bytes(3)));

    // Pindahkan foto ke folder uploads/klaim
    if (!move_uploaded_file($file['tmp_name'], $lokasiFile)) {
        throw new RuntimeException(
            'Foto gagal disimpan ke server.'
        );
    }

    $fileBerhasilDipindahkan = true;

    // Simpan data ke finding_claims
    $stmt = $pdo->prepare("
        INSERT INTO finding_claims (
            kode_klaim,
            report_id,
            user_id,
            detail_penemuan,
            status
        ) VALUES (?, ?, ?, ?, 'menunggu')
    ");

    $stmt->execute([
        $kodeKlaim,
        $reportId,
        $userId,
        $detailKlaim
    ]);

    $findingClaimId = (int)$pdo->lastInsertId();

    // Simpan foto ke finding_claim_evidences
    $stmt = $pdo->prepare("
        INSERT INTO finding_claim_evidences (
            finding_claim_id,
            nama_file,
            keterangan
        ) VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $findingClaimId,
        $namaFileSimpan,
        'Foto barang yang ditemukan'
    ]);

    // Selesaikan transaksi
    $pdo->commit();

    // Perbarui token keamanan
    $_SESSION['csrf_klaim'] = bin2hex(random_bytes(32));

    // Simpan pesan keberhasilan
    $_SESSION['flash_klaim_sukses'] =
        'Pengajuan klaim penemuan berhasil dikirim dan sedang menunggu verifikasi admin.';

    $_SESSION['klaim_berhasil_id'] = $findingClaimId;

    // Arahkan mahasiswa ke halaman status
    header('Location: status-klaim-penemuan.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hapus foto jika transaksi gagal
    if (
        $fileBerhasilDipindahkan &&
        file_exists($lokasiFile)
    ) {
        @unlink($lokasiFile);
    }

    error_log(
        'Gagal menyimpan klaim penemuan: ' . $e->getMessage()
    );

    kembaliKeForm(
        $reportId,
        'Klaim penemuan gagal dikirim. Silakan coba kembali.'
    );
}
