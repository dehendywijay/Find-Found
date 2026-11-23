
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Proteksi halaman mahasiswa
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'mahasiswa'
) {
    header('Location: ../auth/login.php');
    exit;
}

// Validasi ID laporan kehilangan
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId < 1) {
    header('Location: cari-barang.php');
    exit;
}

// Ambil laporan kehilangan yang masih aktif
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.kode_laporan,
        r.nama_barang,
        r.tanggal_kejadian,
        r.detail_lokasi,
        r.deskripsi,
        c.nama_kategori,
        l.nama_lokasi,
        u.nama AS nama_pelapor
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN locations l ON l.id = r.location_id
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.id = ?
      AND r.jenis_laporan = 'kehilangan'
      AND r.status = 'aktif'
    LIMIT 1
");

$stmt->execute([$reportId]);
$barang = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    header('Location: cari-barang.php');
    exit;
}

// Ambil foto laporan kehilangan
$stmtFoto = $pdo->prepare("
    SELECT nama_file, urutan
    FROM report_images
    WHERE report_id = ?
    ORDER BY urutan ASC
");

$stmtFoto->execute([$reportId]);
$fotos = $stmtFoto->fetchAll(PDO::FETCH_ASSOC);

// Token keamanan formulir
if (empty($_SESSION['csrf_klaim'])) {
    $_SESSION['csrf_klaim'] = bin2hex(random_bytes(32));
}

// Pesan kesalahan
$pesan = $_SESSION['flash_klaim'] ?? '';
unset($_SESSION['flash_klaim']);

// Nama mahasiswa
$namaMahasiswa = $_SESSION['nama']
    ?? $_SESSION['nama_lengkap']
    ?? 'Mahasiswa';

// Helper
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function tanggalIndonesia($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $waktu = strtotime($tanggal);

    return date('d', $waktu) . ' ' .
        $bulan[(int)date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Klaim Penemuan - FIND & FOUND</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f8fafc;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
        }

        .navbar {
            height: 76px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5%;
        }

        .brand {
            color: #6246d9;
            text-decoration: none;
            font-size: 19px;
            font-weight: bold;
        }

        .brand i {
            margin-right: 7px;
        }

        .nav-menu {
            display: flex;
            gap: 30px;
        }

        .nav-menu a {
            text-decoration: none;
            color: #334155;
            font-size: 14px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: bold;
        }

        .main-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 24px 60px;
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            font-size: 27px;
            margin: 0 0 12px;
        }

        .page-heading p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
            margin: 0;
        }

        .content-layout {
            display: grid;
            grid-template-columns: 1.5fr 0.8fr;
            gap: 28px;
            align-items: start;
        }

        .form-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 28px;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 8px;
            background: #fff1f0;
            color: #c0392b;
            border: 1px solid #ffd5d0;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .linked-report {
            background: #f0edff;
            border: 1px solid #ddd6fe;
            padding: 16px;
            border-radius: 9px;
            margin-bottom: 25px;
        }

        .linked-report .label {
            font-size: 11px;
            color: #7c6bc4;
            font-weight: bold;
            margin-bottom: 7px;
            display: block;
        }

        .linked-report h3 {
            margin: 0 0 7px;
            color: #4f35c4;
            font-size: 17px;
        }

        .linked-report p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }

        .form-group {
            margin-bottom: 23px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 9px;
        }

        .required {
            color: #dc2626;
        }

        .form-group textarea {
            width: 100%;
            min-height: 145px;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            outline: none;
        }

        .form-group textarea:focus {
            border-color: #6246d9;
            box-shadow: 0 0 0 3px rgba(98, 70, 217, 0.1);
        }

        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 28px 18px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .upload-area:hover {
            border-color: #6246d9;
            background: #faf9ff;
        }

        .upload-area i {
            font-size: 32px;
            color: #6246d9;
            margin-bottom: 12px;
        }

        .upload-area h3 {
            font-size: 14px;
            margin: 0 0 7px;
        }

        .upload-area p {
            font-size: 12px;
            color: #94a3b8;
            margin: 0;
        }

        .upload-area input {
            display: none;
        }

        .preview-container {
            display: none;
            margin-top: 15px;
            position: relative;
        }

        .preview-container img {
            width: 100%;
            max-height: 250px;
            object-fit: contain;
            border-radius: 8px;
            background: #f1f5f9;
        }

        .file-name {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
            overflow-wrap: anywhere;
        }

        .form-note {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
            margin-top: 8px;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-back {
            background: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-submit {
            background: #6246d9;
            color: #fff;
        }

        .btn-submit:hover {
            background: #5035c0;
        }

        .side-card {
            background: #eef2ff;
            border-radius: 12px;
            padding: 22px;
        }

        .side-card h3 {
            color: #6246d9;
            font-size: 17px;
            margin: 0 0 16px;
        }

        .side-card p {
            color: #4f46a5;
            font-size: 13px;
            line-height: 1.7;
            margin: 0 0 13px;
        }

        .side-card p:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 750px) {
            .navbar {
                padding: 0 18px;
            }

            .nav-menu {
                display: none;
            }

            .main-container {
                padding: 28px 16px 40px;
            }

            .content-layout {
                grid-template-columns: 1fr;
            }

            .form-card {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar">

        <a href="dashboard.php" class="brand">
            <i class="fa-solid fa-location-dot"></i>
            FIND & FOUND
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


    <!-- KONTEN UTAMA -->
    <main class="main-container">

        <div class="page-heading">

            <h1>Klaim Menemukan Barang</h1>

            <p>
                Isi detail dan unggah foto barang yang kamu temukan
                sebagai bukti untuk membantu proses verifikasi.
            </p>

        </div>


        <div class="content-layout">

            <!-- FORM KLAIM -->
            <section class="form-card">

                <?php if ($pesan !== ''): ?>
                    <div class="alert">
                        <?= e($pesan) ?>
                    </div>
                <?php endif; ?>


                <!-- INFORMASI LAPORAN -->
                <div class="linked-report">

                    <span class="label">
                        KLAIM UNTUK LAPORAN KEHILANGAN
                    </span>

                    <h3><?= e($barang['nama_barang']) ?></h3>

                    <p>
                        Kode laporan:
                        <?= e($barang['kode_laporan']) ?>
                    </p>

                </div>


                <form
                    action="proses-klaim.php"
                    method="POST"
                    enctype="multipart/form-data"
                    id="formKlaim"
                >

                    <input
                        type="hidden"
                        name="csrf_klaim"
                        value="<?= e($_SESSION['csrf_klaim']) ?>"
                    >

                    <input
                        type="hidden"
                        name="report_id"
                        value="<?= (int)$barang['id'] ?>"
                    >


                    <!-- DETAIL BARANG -->
                    <div class="form-group">

                        <label for="detail_klaim">
                            Detail Barang yang Ditemukan
                            <span class="required">*</span>
                        </label>

                        <textarea
                            name="detail_klaim"
                            id="detail_klaim"
                            placeholder="Jelaskan kondisi barang, ciri-ciri khusus, atau informasi lain mengenai barang yang kamu temukan..."
                            minlength="10"
                            maxlength="1000"
                            required
                        ></textarea>

                        <p class="form-note">
                            Berikan informasi yang jelas mengenai
                            barang yang kamu temukan.
                        </p>

                    </div>


                    <!-- FOTO BARANG -->
                    <div class="form-group">

                        <label>
                            Foto Barang yang Ditemukan
                            <span class="required">*</span>
                        </label>

                        <label class="upload-area" for="foto_klaim">

                            <i class="fa-solid fa-cloud-arrow-up"></i>

                            <h3>Pilih foto barang</h3>

                            <p>
                                JPG, PNG, atau WEBP (maksimal 5 MB)
                            </p>

                            <input
                                type="file"
                                name="foto_klaim"
                                id="foto_klaim"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                required
                            >

                        </label>

                        <div class="preview-container" id="previewContainer">

                            <img id="previewFoto" src="" alt="Pratinjau foto barang">

                            <p class="file-name" id="fileName"></p>

                        </div>

                    </div>


                    <!-- TOMBOL -->
                    <div class="form-actions">

                        <a
                            href="detail-barang.php?id=<?= (int)$barang['id'] ?>"
                            class="btn btn-back"
                        >
                            <i class="fa-solid fa-arrow-left"></i>
                            Kembali
                        </a>

                        <button type="submit" class="btn btn-submit">
                            <i class="fa-solid fa-paper-plane"></i>
                            Kirim Klaim
                        </button>

                    </div>

                </form>

            </section>


            <!-- TIPS -->
            <aside class="side-card">

                <h3>Tips mengirim klaim</h3>

                <p>
                    <i class="fa-solid fa-circle-check"></i>
                    Pastikan foto menunjukkan barang yang kamu temukan.
                </p>

                <p>
                    <i class="fa-solid fa-circle-check"></i>
                    Jelaskan kondisi dan ciri-ciri barang secara terperinci.
                </p>

                <p>
                    <i class="fa-solid fa-circle-check"></i>
                    Gunakan foto asli agar admin dapat melakukan verifikasi.
                </p>

                <p>
                    <i class="fa-solid fa-circle-check"></i>
                    Klaim akan diperiksa oleh admin sebelum proses serah terima.
                </p>

            </aside>

        </div>

    </main>


    <script>
        const inputFoto = document.getElementById('foto_klaim');
        const previewContainer = document.getElementById('previewContainer');
        const previewFoto = document.getElementById('previewFoto');
        const fileName = document.getElementById('fileName');

        inputFoto.addEventListener('change', function () {

            const file = this.files[0];

            if (!file) {
                previewContainer.style.display = 'none';
                return;
            }

            const tipeDiizinkan = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!tipeDiizinkan.includes(file.type)) {
                alert('Format foto harus JPG, PNG, atau WEBP.');
                this.value = '';
                previewContainer.style.display = 'none';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran foto maksimal 5 MB.');
                this.value = '';
                previewContainer.style.display = 'none';
                return;
            }

            previewFoto.src = URL.createObjectURL(file);
            fileName.textContent = file.name;

            previewContainer.style.display = 'block';
        });
    </script>

</body>
</html>
