<?php
require_once __DIR__ . '/config/database.php';

$nama = 'Administrator';
$nim = null;
$email = 'admin@bsi.ac.id';
$password = password_hash('Admin12345', PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("
        INSERT INTO users
        (nama, nim, email, password, role, status_akun)
        VALUES (?, ?, ?, ?, 'admin', 'aktif')
    ");

    $stmt->execute([
        $nama,
        $nim,
        $email,
        $password
    ]);

    echo "Akun admin berhasil dibuat.";

} catch (PDOException $e) {
    echo "Gagal membuat akun admin: " . $e->getMessage();
}