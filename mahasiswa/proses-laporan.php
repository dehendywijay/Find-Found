
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

function kembali($pesan) {
    $_SESSION['flash_laporan'] = $pesan;

    $jenis = $_POST['jenis_laporan'] ?? '';

    $halaman = $jenis === 'penemuan'
        ? 'lapor-penemuan.php'
        : 'lapor-kehilangan.php';

    header("Location: $halaman");
    exit;
}

// 1. Proteksi login
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

// 2. Validasi CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_laporan']) ||
    !hash_equals($_SESSION['csrf_laporan'], $_POST['csrf_token'])
) {
    exit('Permintaan tidak valid. Silakan muat ulang formulir.');
}

$jenis = $_POST['jenis_laporan'] ?? '';

if (!in_array($jenis, ['kehilangan', 'penemuan'], true)) {
    exit('Jenis laporan tidak valid.');
}

// 3. Ambil input
$namaBarang = trim($_POST['nama_barang'] ?? '');
$detailLokasi = trim($_POST['detail_lokasi'] ?? '');

if (mb_strlen($detailLokasi) > 150) {
    kembali('Detail lokasi maksimal 150 karakter.');
}

$categoryId = filter_var(
    $_POST['category_id'] ?? null,
    FILTER_VALIDATE_INT
);

$locationId = filter_var(
    $_POST['location_id'] ?? null,
    FILTER_VALIDATE_INT
);

$tanggal = $_POST['tanggal_kejadian'] ?? '';
$deskripsi = trim($_POST['deskripsi'] ?? '');
$detailRahasia = trim($_POST['detail_rahasia'] ?? '');
$kontak = trim($_POST['kontak'] ?? '');

if (
    $namaBarang === '' ||
    mb_strlen($namaBarang) > 150 ||
    !$categoryId ||
    !$locationId ||
    $deskripsi === '' ||
    $tanggal === ''
) {
    kembali('Mohon lengkapi seluruh data wajib dengan benar.');
}

// 4. Validasi tanggal
$date = DateTime::createFromFormat('!Y-m-d', $tanggal);

if (
    !$date ||
    $date->format('Y-m-d') !== $tanggal ||
    $tanggal > date('Y-m-d')
) {
    kembali('Tanggal kejadian tidak valid.');
}

if (
    mb_strlen($deskripsi) > 10000 ||
    mb_strlen($detailRahasia) > 5000 ||
    mb_strlen($kontak) > 30
) {
    kembali('Ada data yang melebihi batas karakter.');
}

// 5. Pastikan kategori dan lokasi tersedia
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM categories WHERE id = ?
");

$stmt->execute([$categoryId]);

if ((int)$stmt->fetchColumn() === 0) {
    kembali('Kategori tidak ditemukan.');
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM locations
    WHERE id = ? AND status = 'aktif'
");

$stmt->execute([$locationId]);

if ((int)$stmt->fetchColumn() === 0) {
    kembali('Lokasi tidak tersedia.');
}

// 6. Validasi nomor kontak sederhana
if (
    $kontak !== '' &&
    !preg_match('/^[0-9+()\-\s]{8,30}$/', $kontak)
) {
    kembali('Format nomor kontak tidak valid.');
}

// 7. Validasi upload foto
$files = [];

if (isset($_FILES['foto'])) {
    $upload = $_FILES['foto'];

    if (!is_array($upload['name'])) {
        kembali('Format upload tidak valid.');
    }

    $jumlah = count($upload['name']);

    if ($jumlah > 3) {
        kembali('Maksimal upload 3 foto.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    for ($i = 0; $i < $jumlah; $i++) {

        if ($upload['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($upload['error'][$i] !== UPLOAD_ERR_OK) {
            kembali('Terjadi kesalahan saat mengunggah foto.');
        }

        if ($upload['size'][$i] > 5 * 1024 * 1024) {
            kembali('Ukuran setiap foto maksimal 5 MB.');
        }

        $mime = $finfo->file($upload['tmp_name'][$i]);

        if (!isset($allowed[$mime])) {
            kembali('Foto harus berformat JPG, PNG, atau WEBP.');
        }

        if (@getimagesize($upload['tmp_name'][$i]) === false) {
            kembali('File yang diunggah bukan gambar valid.');
        }

        $files[] = [
            'tmp' => $upload['tmp_name'][$i],
            'extension' => $allowed[$mime]
        ];
    }
}

// 8. Siapkan folder upload
$folder = __DIR__ . '/../uploads/laporan';

if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
    kembali('Folder penyimpanan foto gagal dibuat.');
}

$movedFiles = [];

try {

    $pdo->beginTransaction();

    // 9. Buat kode laporan unik
    $kode = 'FF-' . date('Ymd') . '-' .
        strtoupper(bin2hex(random_bytes(4)));

    // 10. Simpan laporan ke database
    $stmt = $pdo->prepare("
        INSERT INTO reports (
            kode_laporan,
            user_id,
            category_id,
            location_id,
            detail_lokasi,
            jenis_laporan,
            nama_barang,
            deskripsi,
            detail_rahasia,
            tanggal_kejadian,
            kontak,
            status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_verifikasi'
        )
    ");

    $stmt->execute([
        $kode,
        $_SESSION['user_id'],
        $categoryId,
        $locationId,
        $detailLokasi !== '' ? $detailLokasi : null,
        $jenis,
        $namaBarang,
        $deskripsi,
        null,
        $tanggal,
        $kontak !== '' ? $kontak : null
    ]);

    $reportId = $pdo->lastInsertId();

    // 11. Simpan foto ke report_images
    $stmtFoto = $pdo->prepare("
        INSERT INTO report_images
            (report_id, nama_file, urutan)
        VALUES (?, ?, ?)
    ");

    foreach ($files as $index => $file) {

        $namaFile = bin2hex(random_bytes(16)) .
            '.' . $file['extension'];

        $tujuan = $folder . '/' . $namaFile;

        if (!move_uploaded_file($file['tmp'], $tujuan)) {
            throw new RuntimeException('Gagal menyimpan foto.');
        }

        $movedFiles[] = $tujuan;

        $stmtFoto->execute([
            $reportId,
            $namaFile,
            $index + 1
        ]);
    }

    // 12. Konfirmasi transaksi database
    $pdo->commit();

    // 13. Buat token CSRF baru
    $_SESSION['csrf_laporan'] = bin2hex(random_bytes(32));

    // 14. Simpan ID laporan untuk halaman sukses
    $_SESSION['laporan_berhasil_id'] = (int)$reportId;

    // 15. Arahkan ke halaman laporan berhasil
    header('Location: laporan-berhasil.php');
    exit;

} catch (Throwable $e) {

    // Batalkan transaksi jika masih berjalan
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hapus foto jika penyimpanan database gagal
    foreach ($movedFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    error_log(
        'Gagal menyimpan laporan: ' . $e->getMessage()
    );

    kembali('Laporan gagal disimpan. Silakan coba kembali.');
}
