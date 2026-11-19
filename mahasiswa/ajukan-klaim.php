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
// 2. VALIDASI ID LAPORAN
// ==========================================
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId < 1) {
    header('Location: cari-barang.php');
    exit;
}

// ==========================================
// 3. AMBIL DATA BARANG DITEMUKAN
// ==========================================
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.user_id,
        r.kode_laporan,
        r.nama_barang,
        r.jenis_laporan,
        r.tanggal_kejadian,
        r.deskripsi,
        c.nama_kategori,
        l.nama_lokasi
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN locations l ON l.id = r.location_id
    WHERE r.id = ?
      AND r.jenis_laporan = 'penemuan'
      AND r.status = 'aktif'
    LIMIT 1
");

$stmt->execute([$reportId]);
$barang = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika laporan tidak ditemukan atau bukan laporan penemuan
if (!$barang) {
    header('Location: cari-barang.php');
    exit;
}

// Pemilik laporan tidak boleh mengklaim barangnya sendiri
if ((int) $barang['user_id'] === $userId) {
    header('Location: detail-barang.php?id=' . $reportId);
    exit;
}

// ==========================================
// 4. CEK KLAIM YANG MASIH BERJALAN
// ==========================================
$stmtCek = $pdo->prepare("
    SELECT id, status
    FROM claims
    WHERE report_id = ?
      AND user_id = ?
      AND status IN ('menunggu', 'disetujui')
    LIMIT 1
");

$stmtCek->execute([$reportId, $userId]);
$klaimAktif = $stmtCek->fetch(PDO::FETCH_ASSOC);

if ($klaimAktif) {
    $_SESSION['flash_klaim_barang'] =
        'Kamu sudah memiliki pengajuan klaim yang sedang diproses untuk barang ini.';

    header('Location: detail-barang.php?id=' . $reportId);
    exit;
}

// ==========================================
// 5. TOKEN KEAMANAN FORM
// ==========================================
if (empty($_SESSION['csrf_klaim_barang'])) {
    $_SESSION['csrf_klaim_barang'] = bin2hex(random_bytes(32));
}

// ==========================================
// 6. DATA MAHASISWA
// ==========================================
$namaMahasiswa = $_SESSION['nama'] ?? '';

function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajukan Klaim Barang - FIND & FOUND</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

        .page-wrapper {
            max-width: 850px;
            margin: 0 auto;
            padding: 35px 24px 60px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .back-link:hover {
            color: #6246d9;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0 0 10px;
            font-size: 27px;
            color: #172033;
        }

        .page-heading p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        .item-card,
        .form-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .item-card h2 {
            margin: 0 0 17px;
            font-size: 17px;
        }

        .item-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .item-info label {
            display: block;
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .item-info p {
            margin: 0;
            color: #334155;
            font-size: 14px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .item-name {
            color: #6246d9 !important;
            font-weight: 600;
        }

        .form-card h2 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .form-description {
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
            margin: 0 0 25px;
        }

        .form-group {
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        .required {
            color: #dc2626;
        }

        .optional {
            color: #94a3b8;
            font-size: 12px;
            font-weight: normal;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            color: #334155;
            outline: none;
            transition: .2s;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color: #6246d9;
            box-shadow: 0 0 0 3px rgba(98, 70, 217, .1);
        }

        .form-group textarea {
            min-height: 130px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-hint {
            margin-top: 8px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
        }

        .upload-box {
            border: 1.5px dashed #cbd5e1;
            border-radius: 9px;
            padding: 25px 18px;
            text-align: center;
            background: #f8fafc;
            transition: .2s;
        }

        .upload-box:hover {
            border-color: #6246d9;
        }

        .upload-box i {
            font-size: 30px;
            color: #6246d9;
            margin-bottom: 12px;
        }

        .upload-box p {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 13px;
        }

        .upload-box input {
            max-width: 100%;
            font-size: 13px;
        }

        .file-name {
            margin-top: 12px;
            color: #6246d9;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 14px;
            border-radius: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .notice i {
            margin-top: 2px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: .2s;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-submit {
            background: #6246d9;
            color: #fff;
        }

        .btn-submit:hover {
            background: #5035c0;
        }

        @media (max-width: 600px) {
            .page-wrapper {
                padding: 22px 16px 40px;
            }

            .page-heading h1 {
                font-size: 23px;
            }

            .item-card,
            .form-card {
                padding: 19px;
            }

            .item-info {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <a href="detail-barang.php?id=<?= (int) $barang['id'] ?>" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Kembali ke Detail Barang
    </a>

    <div class="page-heading">
        <h1>Ajukan Klaim Barang</h1>
        <p>
            Isi informasi berikut untuk membuktikan bahwa barang yang
            ditemukan merupakan milikmu.
        </p>
    </div>

    <!-- INFORMASI BARANG -->
    <section class="item-card">

        <h2>
            <i class="fa-solid fa-box-open"></i>
            Informasi Barang
        </h2>

        <div class="item-info">

            <div>
                <label>Nama Barang</label>
                <p class="item-name"><?= e($barang['nama_barang']) ?></p>
            </div>

            <div>
                <label>Kode Laporan</label>
                <p><?= e($barang['kode_laporan']) ?></p>
            </div>

            <div>
                <label>Kategori</label>
                <p><?= e($barang['nama_kategori'] ?? '-') ?></p>
            </div>

            <div>
                <label>Lokasi Ditemukan</label>
                <p><?= e($barang['nama_lokasi'] ?? '-') ?></p>
            </div>

        </div>

    </section>

    <!-- FORM PENGAJUAN KLAIM -->
    <section class="form-card">

        <h2>Formulir Klaim Kepemilikan</h2>

        <p class="form-description">
            Lengkapi data di bawah ini dengan benar. Admin akan memeriksa
            informasi dan bukti yang kamu kirimkan.
        </p>

        <form
            action="proses-klaim-barang.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="report_id"
                value="<?= (int) $barang['id'] ?>"
            >

            <input
                type="hidden"
                name="csrf_klaim_barang"
                value="<?= e($_SESSION['csrf_klaim_barang']) ?>"
            >

            <div class="form-group">

                <label for="nama_pemilik">
                    Nama Pemilik Barang <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="nama_pemilik"
                    name="nama_pemilik"
                    value="<?= e($namaMahasiswa) ?>"
                    maxlength="100"
                    placeholder="Masukkan nama pemilik barang"
                    required
                >

                <div class="form-hint">
                    Masukkan nama sesuai identitas pemilik barang.
                </div>

            </div>

            <div class="form-group">

                <label for="ciri_barang">
                    Ciri-ciri dan Bukti Kepemilikan
                    <span class="required">*</span>
                </label>

                <textarea
                    id="ciri_barang"
                    name="ciri_barang"
                    minlength="10"
                    maxlength="1000"
                    placeholder="Jelaskan ciri khusus barang yang hanya diketahui pemiliknya, misalnya warna, goresan, isi, tanda khusus, atau detail lainnya..."
                    required
                ></textarea>

                <div class="form-hint">
                    Jelaskan ciri khusus barang untuk membantu admin
                    memverifikasi kepemilikanmu. Minimal 10 karakter.
                </div>

            </div>

            <div class="form-group">

                <label for="foto_klaim">
                    Foto Bukti Kepemilikan
                    <span class="optional">(Opsional)</span>
                </label>

                <div class="upload-box">

                    <i class="fa-solid fa-cloud-arrow-up"></i>

                    <p>
                        Unggah foto jika tersedia untuk membantu
                        membuktikan bahwa barang tersebut milikmu.
                    </p>

                    <input
                        type="file"
                        id="foto_klaim"
                        name="foto_klaim"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <div class="file-name" id="fileName">
                        Belum ada file dipilih
                    </div>

                </div>

                <div class="form-hint">
                    Foto tidak wajib diunggah. Jika ingin mengunggah,
                    gunakan format JPG, JPEG, PNG, atau WEBP dengan ukuran maksimal 5 MB.
                </div>

            </div>

            <div class="notice">
                <i class="fa-solid fa-circle-info"></i>

                <span>
                    Pengajuan klaim akan diperiksa oleh admin.
                    Pastikan informasi dan bukti yang diberikan benar.
                    Pengajuan yang tidak dapat dibuktikan bisa ditolak.
                </span>
            </div>

            <div class="form-actions">

                <a
                    href="detail-barang.php?id=<?= (int) $barang['id'] ?>"
                    class="btn btn-cancel"
                >
                    Batal
                </a>

                <button type="submit" class="btn btn-submit">
                    <i class="fa-solid fa-paper-plane"></i>
                    Kirim Pengajuan
                </button>

            </div>

        </form>

    </section>

</div>

<script>
    const inputFoto = document.getElementById('foto_klaim');
    const namaFile = document.getElementById('fileName');

    inputFoto.addEventListener('change', function () {
        if (this.files.length > 0) {
            namaFile.textContent = this.files[0].name;
        } else {
            namaFile.textContent = 'Belum ada file dipilih';
        }
    });
</script>

</body>
</html>