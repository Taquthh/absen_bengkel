<?php
ob_start(); // Tambahkan ini di baris pertama
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// COMMENT SEMENTARA LINE DI BAWAH INI:
// require_once 'config.php'; 

// Tambahkan teks dummy ini untuk tes apakah Vercel mau merender halaman
echo "<h1>Halo, Runtime PHP Vercel Berhasil Berjalan!</h1>"; 
exit; // Paksa berhenti di sini untuk tes