<?php
require_once 'config.php';

// Function to format hours
function formatHours($hours) {
    $hours_int = floor($hours);
    $minutes = round(($hours - $hours_int) * 60);
    return $hours_int . ' jam ' . $minutes . ' menit';
}

// Get all users
$stmt = $pdo->prepare("SELECT id, username FROM users");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reset_summary = [];
$exported_files = [];

// Process each user
foreach ($users as $user) {
    $user_id = $user['id'];
    $username = $user['username'];

    // Get all attendance data for this user
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY date DESC");
    $stmt->execute([$user_id]);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($attendance_data)) {
        // Calculate statistics for the entire period
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_days, SUM(total_hours) as total_hours FROM attendance WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_days = $stats['total_days'] ?? 0;
        $total_hours = $stats['total_hours'] ?? 0;
        $avg_hours = $total_days > 0 ? $total_hours / $total_days : 0;

        // Create Excel export for this user
        $excel_content = generateMonthlyExcel($user, $attendance_data, $total_days, $total_hours, $avg_hours);

        // Save Excel file
        $filename = 'arsip_absensi_' . $username . '_' . date('Y-m') . '.xls';
        $filepath = __DIR__ . '/arsip/' . $filename;

        // Create arsip directory if it doesn't exist
        if (!is_dir(__DIR__ . '/arsip')) {
            mkdir(__DIR__ . '/arsip', 0755, true);
        }

        file_put_contents($filepath, $excel_content);
        $exported_files[] = $filename;

        // Delete all attendance records for this user
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $reset_summary[] = [
            'user' => $username,
            'records_deleted' => count($attendance_data),
            'total_days' => $total_days,
            'total_hours' => $total_hours,
            'excel_file' => $filename
        ];
    }
}

// Function to generate Excel content for monthly archive
function generateMonthlyExcel($user, $attendance_data, $total_days, $total_hours, $avg_hours) {
    $excel_content = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    $excel_content .= '<head>';
    $excel_content .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    $excel_content .= '<style>';
    $excel_content .= 'body { font-family: Arial, sans-serif; }';
    $excel_content .= 'table { border-collapse: collapse; width: 100%; }';
    $excel_content .= 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }';
    $excel_content .= 'th { background-color: #4CAF50; color: white; font-weight: bold; }';
    $excel_content .= '.header { background-color: #f2f2f2; font-size: 18px; font-weight: bold; text-align: center; padding: 15px; }';
    $excel_content .= '.subheader { background-color: #e8f5e8; font-size: 14px; text-align: center; padding: 10px; }';
    $excel_content .= '.stats { background-color: #fff3cd; font-weight: bold; }';
    $excel_content .= '</style>';
    $excel_content .= '</head>';
    $excel_content .= '<body>';

    // Company Header
    $excel_content .= '<table>';
    $excel_content .= '<tr><td colspan="7" class="header">ARSIP LAPORAN ABSENSI</td></tr>';
    $excel_content .= '<tr><td colspan="7" class="header">Akhmad Firdaus</td></tr>';
    $excel_content .= '<tr><td colspan="7" class="subheader">Periode: ' . date('F Y') . '</td></tr>';
    $excel_content .= '<tr><td colspan="7" class="subheader">Nama Karyawan: ' . htmlspecialchars($user['username']) . '</td></tr>';
    $excel_content .= '<tr><td colspan="7" class="subheader">Tanggal Arsip: ' . date('d/m/Y H:i:s') . '</td></tr>';
    $excel_content .= '<tr><td colspan="7"></td></tr>';
    $excel_content .= '</table>';

    // Attendance Data Table
    $excel_content .= '<table>';
    $excel_content .= '<tr>';
    $excel_content .= '<th style="width: 5%;">No</th>';
    $excel_content .= '<th style="width: 10%;">Hari</th>';
    $excel_content .= '<th style="width: 15%;">Tanggal</th>';
    $excel_content .= '<th style="width: 12%;">Jam Masuk</th>';
    $excel_content .= '<th style="width: 12%;">Jam Keluar</th>';
    $excel_content .= '<th style="width: 20%;">Total Jam Kerja</th>';
    $excel_content .= '<th style="width: 26%;">Tanda Tangan Digital</th>';
    $excel_content .= '</tr>';

    $no = 1;
    foreach ($attendance_data as $row) {
        $excel_content .= '<tr>';
        $excel_content .= '<td style="text-align: center;">' . $no++ . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($row['day']) . '</td>';
        $excel_content .= '<td>' . date('d/m/Y', strtotime($row['date'])) . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($row['start_time']) . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($row['end_time']) . '</td>';
        $excel_content .= '<td>' . formatHours($row['total_hours']) . '</td>';

        // Display signature status
        if (!empty($row['signature'])) {
            $excel_content .= '<td style="text-align: center; color: #00ff88; font-weight: bold;">✓ Sudah ditandatangani secara digital</td>';
        } else {
            $excel_content .= '<td style="text-align: center; color: #999;">Tidak ada tanda tangan</td>';
        }

        $excel_content .= '</tr>';
    }

    // Statistics Section
    $excel_content .= '<tr class="stats">';
    $excel_content .= '<td colspan="5" style="text-align: right; font-weight: bold;">TOTAL HARI KERJA:</td>';
    $excel_content .= '<td colspan="2" style="font-weight: bold;">' . $total_days . ' hari</td>';
    $excel_content .= '</tr>';

    $excel_content .= '<tr class="stats">';
    $excel_content .= '<td colspan="5" style="text-align: right; font-weight: bold;">TOTAL JAM KERJA:</td>';
    $excel_content .= '<td colspan="2" style="font-weight: bold;">' . formatHours($total_hours) . '</td>';
    $excel_content .= '</tr>';

    $excel_content .= '<tr class="stats">';
    $excel_content .= '<td colspan="5" style="text-align: right; font-weight: bold;">RATA-RATA JAM/HARI:</td>';
    $excel_content .= '<td colspan="2" style="font-weight: bold;">' . formatHours($avg_hours) . '</td>';
    $excel_content .= '</tr>';

    $excel_content .= '</table>';

    // Footer
    $excel_content .= '<table style="margin-top: 30px;">';
    $excel_content .= '<tr><td colspan="7" style="text-align: center; font-style: italic; border: none;">';
    $excel_content .= 'Dokumen arsip dibuat secara otomatis oleh Sistem Absensi Digital<br>';
    $excel_content .= 'Akhmad Firdaus - ' . date('d F Y');
    $excel_content .= '</td></tr>';
    $excel_content .= '</table>';

    $excel_content .= '</body>';
    $excel_content .= '</html>';

    return $excel_content;
}

// Create log file
$log_content = "=== RESET BULANAN ABSENSI ===\n";
$log_content .= "Tanggal: " . date('d/m/Y H:i:s') . "\n";
$log_content .= "Periode: " . date('F Y') . "\n\n";

$log_content .= "RINGKASAN RESET:\n";
foreach ($reset_summary as $summary) {
    $log_content .= "- User: {$summary['user']}\n";
    $log_content .= "  Records dihapus: {$summary['records_deleted']}\n";
    $log_content .= "  Total hari: {$summary['total_days']}\n";
    $log_content .= "  Total jam: " . formatHours($summary['total_hours']) . "\n";
    $log_content .= "  File arsip: {$summary['excel_file']}\n\n";
}

$log_content .= "File Excel yang dibuat:\n";
foreach ($exported_files as $file) {
    $log_content .= "- $file\n";
}

$log_content .= "\n=== SELESAI ===\n";

// Save log file
$log_filename = 'reset_log_' . date('Y-m') . '.txt';
file_put_contents(__DIR__ . '/arsip/' . $log_filename, $log_content);

// Output summary
echo "<h2>Reset Bulanan Absensi Berhasil!</h2>";
echo "<p><strong>Tanggal:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "<p><strong>Periode:</strong> " . date('F Y') . "</p>";

echo "<h3>Ringkasan Reset:</h3>";
echo "<ul>";
foreach ($reset_summary as $summary) {
    echo "<li><strong>{$summary['user']}</strong>: {$summary['records_deleted']} records dihapus, file arsip: {$summary['excel_file']}</li>";
}
echo "</ul>";

echo "<h3>File Arsip yang Dibuat:</h3>";
echo "<ul>";
foreach ($exported_files as $file) {
    echo "<li>$file</li>";
}
echo "</ul>";

echo "<p><strong>Log lengkap tersimpan di:</strong> arsip/$log_filename</p>";
echo "<p><a href='index.php'>Kembali ke Dashboard</a></p>";
?>
