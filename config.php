<?php
// Database configuration
$host = 'localhost';
$dbname = 'absen_bengkel';
$username = 'root'; // Default XAMPP username
$password = 'root'; // Default XAMPP password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
