
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

if (
    !isset($jenisLaporan) ||
    !in_array($jenisLaporan, ['kehilangan', 'penemuan'], true)
) {
    exit('Jenis laporan tidak valid.');
}

// ==========================================
// VALIDASI REFERENSI LAPORAN KEHILANGAN
// ==========================================

$referensiLaporanId = null;

if (
    $jenisLaporan === 'penemuan' &&
    isset($_GET['referensi_laporan'])
) {
    $referensiLaporanId = filter_var(
        $_GET['referensi_laporan'],
        FILTER_VALIDATE_INT
    );

    if (!$referensiLaporanId || $referensiLaporanId <= 0) {
        exit('Referensi laporan tidak valid.');
    }

    $stmtReferensi = $pdo->prepare("
        SELECT id, nama_barang
        FROM reports
        WHERE id = ?
          AND jenis_laporan = 'kehilangan'
          AND status = 'aktif'
        LIMIT 1
    ");

    $stmtReferensi->execute([$referensiLaporanId]);

    $laporanReferensi = $stmtReferensi->fetch(PDO::FETCH_ASSOC);

    if (!$laporanReferensi) {
        exit('Laporan kehilangan tidak ditemukan atau sudah tidak aktif.');
    }
}

// ==========================================
// AMBIL DATA KATEGORI DAN LOKASI
// ==========================================

$stmt = $pdo->query("
    SELECT id, nama_kategori
    FROM categories
    ORDER BY nama_kategori ASC
");

$kategori = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT id, nama_lokasi
    FROM locations
    WHERE status = 'aktif'
    ORDER BY nama_lokasi ASC
");

$lokasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// TOKEN CSRF
// ==========================================

if (empty($_SESSION['csrf_laporan'])) {
    $_SESSION['csrf_laporan'] = bin2hex(random_bytes(32));
}

$pesan = $_SESSION['flash_laporan'] ?? '';
unset($_SESSION['flash_laporan']);

$judul = $jenisLaporan === 'kehilangan'
    ? 'Lapor kehilangan'
    : 'Lapor penemuan';

$namaMahasiswa = $_SESSION['nama']
    ?? $_SESSION['nama_lengkap']
    ?? 'Mahasiswa';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($judul) ?> - FIND & FOUND</title>

    <link rel="stylesheet" href="../assets/css/form-laporan.css">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">

    <a href="dashboard.php" class="brand">
        <i class="fa-solid fa-location-dot"></i>
        <span>FIND & FOUND</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php">Beranda</a>
        <a href="cari-barang.php">Cari barang</a>
        <a href="status-laporan.php">Status laporan</a>
    </div>

    <div class="profile">
        <i class="fa-regular fa-user"></i>
        <span><?= e($namaMahasiswa) ?></span>
    </div>

</nav>


<!-- MAIN CONTENT -->
<main class="main-container">

    <div class="page-heading">

        <h1><?= e($judul) ?></h1>

        <p>
            <?= $jenisLaporan === 'kehilangan'
                ? 'Isi data barangmu selengkapnya supaya mudah dicocokkan dengan laporan temuan.'
                : 'Isi data barang yang kamu temukan agar pemiliknya dapat menemukannya kembali.'
            ?>
        </p>

    </div>


    <!-- TAB LAPORAN -->
    <div class="report-tabs">

        <a href="lapor-kehilangan.php"
           class="tab <?= $jenisLaporan === 'kehilangan' ? 'active' : '' ?>">
            Lapor kehilangan
        </a>

        <a href="lapor-penemuan.php"
           class="tab <?= $jenisLaporan === 'penemuan' ? 'active' : '' ?>">
            Lapor penemuan
        </a>

    </div>


    <!-- FORM LAYOUT -->
    <div class="content-layout">

        <section class="form-card">

            <?php if ($pesan): ?>
                <div class="alert-message">
                    <?= e($pesan) ?>
                </div>
            <?php endif; ?>

            <?php if ($referensiLaporanId !== null): ?>
                <div class="alert-message">
                    Laporan penemuan ini terhubung dengan laporan kehilangan:
                    <strong><?= e($laporanReferensi['nama_barang']) ?></strong>
                </div>
            <?php endif; ?>


            <form action="proses-laporan.php"
                  method="POST"
                  enctype="multipart/form-data"
                  id="reportForm">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION['csrf_laporan']) ?>">

                <input type="hidden"
                       name="jenis_laporan"
                       value="<?= e($jenisLaporan) ?>">

                <?php if ($referensiLaporanId !== null): ?>
                    <input type="hidden"
                           name="referensi_laporan_id"
                           value="<?= (int)$referensiLaporanId ?>">
                <?php endif; ?>


                <!-- NAMA BARANG -->
                <div class="form-group">

                    <label for="nama_barang">Nama barang</label>

                    <input
                        type="text"
                        id="nama_barang"
                        name="nama_barang"
                        placeholder="Contoh: Dompet kulit hitam merek Fossil"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- KATEGORI -->
                <div class="form-group">

                    <label for="category_id">Kategori</label>

                    <select id="category_id"
                            name="category_id"
                            required>

                        <option value="">Pilih kategori barang</option>

                        <?php foreach ($kategori as $item): ?>
                            <option value="<?= (int)$item['id'] ?>">
                                <?= e($item['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CIRI-CIRI BARANG -->
                <div class="form-group">

                    <label for="deskripsi">Ciri-ciri khusus</label>

                    <textarea
                        id="deskripsi"
                        name="deskripsi"
                        rows="4"
                        maxlength="10000"
                        placeholder="Jelaskan ciri-ciri barang yang dapat membantu proses pencocokan..."
                        required
                    ></textarea>

                    <small>
                        Jangan cantumkan informasi pribadi atau detail rahasia yang hanya boleh diketahui pemilik.
                    </small>

                </div>


                <!-- UPLOAD FOTO -->
                <div class="form-group">

                    <label for="foto">Foto barang (jika ada)</label>

                    <label for="foto" class="upload-box">

                        <i class="fa-solid fa-arrow-up-from-bracket"></i>

                        <strong>Unggah foto barang</strong>

                        <span>JPG atau PNG, maksimal 5 MB, maksimal 3 file</span>

                    </label>

                    <input
                        type="file"
                        id="foto"
                        name="foto[]"
                        accept=".jpg,.jpeg,.png,.webp"
                        multiple
                        hidden
                    >

                    <div id="file-list" class="file-list"></div>

                </div>


                <!-- LOKASI -->
                <div class="form-group">

                    <label for="location_id">
                        Perkiraan lokasi <?= $jenisLaporan === 'kehilangan' ? 'kehilangan' : 'penemuan' ?>
                    </label>

                    <select id="location_id"
                            name="location_id"
                            required>

                        <option value="">Pilih lokasi kampus</option>

                        <?php foreach ($lokasi as $item): ?>
                            <option value="<?= (int)$item['id'] ?>">
                                <?= e($item['nama_lokasi']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- DETAIL LOKASI -->
                <div class="form-group">

                    <label for="detail_lokasi">Detail lokasi</label>

                    <input
                        type="text"
                        id="detail_lokasi"
                        name="detail_lokasi"
                        maxlength="150"
                        placeholder="Contoh: Lantai 2, dekat ruang baca"
                    >

                </div>


                <!-- TANGGAL -->
                <div class="form-group">

                    <label for="tanggal_kejadian">
                        Perkiraan tanggal <?= $jenisLaporan === 'kehilangan' ? 'kehilangan' : 'penemuan' ?>
                    </label>

                    <input
                        type="date"
                        id="tanggal_kejadian"
                        name="tanggal_kejadian"
                        max="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- KONTAK -->
                <div class="form-group">

                    <label for="kontak">Kontak yang aman</label>

                    <input
                        type="text"
                        id="kontak"
                        name="kontak"
                        maxlength="30"
                        placeholder="Email atau nomor WhatsApp"
                    >

                </div>


                <!-- BUTTON -->
                <div class="form-actions">

                    <a href="dashboard.php" class="btn-cancel">
                        Batal
                    </a>

                    <button type="submit" class="btn-submit">
                        Kirim laporan
                    </button>

                </div>

            </form>

        </section>


        <!-- TIPS SIDEBAR -->
        <aside class="tips-card">

            <h3>Tips melapor</h3>

            <?php if ($jenisLaporan === 'kehilangan'): ?>

                <p>
                    Tulis ciri khusus yang membantu pemilik mengenali barangnya.
                </p>

                <p>
                    Sebut lokasi terakhir dan perkiraan jam.
                </p>

                <p>
                    Gunakan kontak yang masih aktif.
                </p>

            <?php else: ?>

                <p>
                    Jelaskan ciri barang yang ditemukan.
                </p>

                <p>
                    Sebutkan lokasi dan waktu penemuan.
                </p>

                <p>
                    Simpan barang di tempat yang aman dan ikuti prosedur kampus.
                </p>

            <?php endif; ?>

        </aside>

    </div>

</main>


<script>
const fotoInput = document.getElementById('foto');
const fileList = document.getElementById('file-list');

fotoInput.addEventListener('change', function () {

    fileList.innerHTML = '';

    const files = Array.from(this.files);

    if (files.length > 3) {
        alert('Maksimal upload 3 foto.');
        this.value = '';
        return;
    }

    for (const file of files) {

        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran setiap foto maksimal 5 MB.');
            this.value = '';
            fileList.innerHTML = '';
            return;
        }

        const row = document.createElement('div');
        row.className = 'file-item';

        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-check';

        const name = document.createElement('span');
        name.textContent = file.name;

        const size = document.createElement('span');
        size.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';

        row.append(icon, name, size);
        fileList.appendChild(row);
    }
});
</script>

</body>
</html>
