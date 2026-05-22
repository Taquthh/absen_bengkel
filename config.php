<?php
// Database configuration - Clever Cloud Production
$host     = 'b5ywhgopl6jfgicjlubq-mysql.services.clever-cloud.com';
$dbname   = 'b5ywhgopl6jfgicjlubq';
$username = 'u54vhpjsfijen9jo';
$password = 'SWHuw568JeHSLe0pUtNn';
$port     = '3306';

try {
    // Menyusun DSN PDO dengan menyertakan port dari Clever Cloud
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
    
    $pdo = new PDO($dsn, $username, $password);
    
    // Set error mode ke exception untuk penanganan error yang lebih baik
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // Menampilkan pesan error jika koneksi gagal
    die("Connection failed: " . $e->getMessage());
}
?>