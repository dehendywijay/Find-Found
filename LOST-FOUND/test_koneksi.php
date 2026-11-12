
<?php

require_once 'config/database.php';

try {
    $query = $pdo->query("SELECT DATABASE() AS nama_database");
    $hasil = $query->fetch();

    echo "<h2>Koneksi Database Berhasil!</h2>";
    echo "<p>Database: " . htmlspecialchars($hasil['nama_database']) . "</p>";

} catch (PDOException $e) {
    echo "Terjadi kesalahan saat pengujian.";
}
