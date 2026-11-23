
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Pastikan pengguna sudah login sebagai admin
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

$pesan = '';
$jenis_pesan = '';

// Proses aktivasi atau penonaktifan akun
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $aksi = $_POST['aksi'] ?? '';

    if ($user_id && in_array($aksi, ['aktif', 'nonaktif'], true)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users
                SET status_akun = ?
                WHERE id = ? AND role = 'mahasiswa'
            ");

            $stmt->execute([$aksi, $user_id]);

            if ($stmt->rowCount() > 0) {
                $pesan = $aksi === 'aktif'
                    ? 'Akun mahasiswa berhasil diaktifkan.'
                    : 'Akun mahasiswa berhasil dinonaktifkan.';
                $jenis_pesan = 'success';
            } else {
                $pesan = 'Tidak ada perubahan pada akun tersebut.';
                $jenis_pesan = 'error';
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $pesan = 'Terjadi kesalahan saat memperbarui akun.';
            $jenis_pesan = 'error';
        }
    } else {
        $pesan = 'Permintaan tidak valid.';
        $jenis_pesan = 'error';
    }
}

// Ambil seluruh akun mahasiswa
$stmt = $pdo->query("
    SELECT id, nama, nim, email, status_akun, created_at
    FROM users
    WHERE role = 'mahasiswa'
    ORDER BY
        CASE
            WHEN status_akun = 'pending' THEN 1
            WHEN status_akun = 'aktif' THEN 2
            ELSE 3
        END,
        created_at DESC
");

$mahasiswa = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jumlah_pending = 0;
foreach ($mahasiswa as $akun) {
    if ($akun['status_akun'] === 'pending') {
        $jumlah_pending++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivasi Akun Mahasiswa</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px;
            font-family: Arial, sans-serif;
            background: #f5f5f7;
            color: #25233a;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 26px;
        }

        .subtitle {
            color: #777;
            margin: 0;
        }

        .summary {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e8e7ed;
        }

        .summary strong {
            display: block;
            font-size: 28px;
            color: #6c45d9;
            margin-top: 8px;
        }

        .table-wrapper {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            border: 1px solid #e8e7ed;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #faf9fc;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #fff2d8;
            color: #a36b00;
        }

        .aktif {
            background: #dff6e8;
            color: #168044;
        }

        .nonaktif {
            background: #ffe3e3;
            color: #c03939;
        }

        button {
            border: none;
            border-radius: 7px;
            padding: 9px 13px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-aktif {
            background: #6c45d9;
            color: white;
        }

        .btn-nonaktif {
            background: #ffe3e3;
            color: #c03939;
        }

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .success {
            background: #dff6e8;
            color: #168044;
        }

        .error {
            background: #ffe3e3;
            color: #c03939;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #777;
        }

        @media (max-width: 600px) {
            body {
                padding: 18px;
            }

            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <div>
            <h1>Aktivasi Akun Mahasiswa</h1>
            <p class="subtitle">
                Kelola pendaftaran akun mahasiswa FIND & FOUND.
            </p>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="message <?= htmlspecialchars($jenis_pesan) ?>">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="summary">
        Akun menunggu aktivasi
        <strong><?= $jumlah_pending ?></strong>
    </div>

    <div class="table-wrapper">
        <?php if (empty($mahasiswa)): ?>

            <div class="empty">
                Belum ada akun mahasiswa terdaftar.
            </div>

        <?php else: ?>

            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NIM</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($mahasiswa as $akun): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($akun['nama']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($akun['nim'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($akun['email']) ?>
                            </td>

                            <td>
                                <span class="status <?= htmlspecialchars($akun['status_akun']) ?>">
                                    <?= htmlspecialchars(ucfirst($akun['status_akun'])) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($akun['status_akun'] !== 'aktif'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="user_id"
                                            value="<?= (int) $akun['id'] ?>">
                                        <input type="hidden" name="aksi" value="aktif">
                                        <button class="btn-aktif" type="submit">
                                            Aktifkan
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline"
                                        onsubmit="return confirm('Nonaktifkan akun mahasiswa ini?')">
                                        <input type="hidden" name="user_id"
                                            value="<?= (int) $akun['id'] ?>">
                                        <input type="hidden" name="aksi" value="nonaktif">
                                        <button class="btn-nonaktif" type="submit">
                                            Nonaktifkan
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </div>

</div>

</body>
</html>
