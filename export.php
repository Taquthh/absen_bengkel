<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get attendance data
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY created_at ASC");
$stmt->execute([$user_id]);
$attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format hours
function formatHours($hours) {
    $hours_int = floor($hours);
    $minutes = round(($hours - $hours_int) * 60);
    return $hours_int . ' jam ' . $minutes . ' menit';
}

// Calculate statistics first
$current_month = date('m');
$current_year = date('Y');
$stmt = $pdo->prepare("SELECT COUNT(*) as total_days, SUM(total_hours) as total_hours FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
$stmt->execute([$user_id, $current_month, $current_year]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_days = $stats['total_days'] ?? 0;
$total_hours = $stats['total_hours'] ?? 0;
$avg_hours = $total_days > 0 ? $total_hours / $total_days : 0;

// Create Excel file using PhpSpreadsheet
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set column widths
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(12);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(20);
$sheet->getColumnDimension('G')->setWidth(30);

// Company Header
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'Laporan Absensi');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
$sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

$sheet->mergeCells('A2:G2');
$sheet->setCellValue('A2', 'Akhmad Firdaus');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

$sheet->mergeCells('A3:G3');
$sheet->setCellValue('A3', 'Periode: ' . date('F Y'));
$sheet->getStyle('A3')->getAlignment()->setHorizontal('center');

$sheet->mergeCells('A4:G4');
$sheet->setCellValue('A4', 'Nama Karyawan: ' . htmlspecialchars($user['username']));
$sheet->getStyle('A4')->getAlignment()->setHorizontal('center');

$sheet->mergeCells('A5:G5');
$sheet->setCellValue('A5', 'Tanggal Export: ' . date('d/m/Y H:i:s'));
$sheet->getStyle('A5')->getAlignment()->setHorizontal('center');

// Empty row
$sheet->setCellValue('A6', '');

// Table headers
$row = 7;
$sheet->setCellValue('A' . $row, 'No');
$sheet->setCellValue('B' . $row, 'Hari');
$sheet->setCellValue('C' . $row, 'Tanggal');
$sheet->setCellValue('D' . $row, 'Jam Masuk');
$sheet->setCellValue('E' . $row, 'Jam Keluar');
$sheet->setCellValue('F' . $row, 'Total Jam Kerja');
$sheet->setCellValue('G' . $row, 'Tanda Tangan Digital');

// Style headers
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4CAF50']],
    'alignment' => ['horizontal' => 'center'],
    'borders' => ['allBorders' => ['borderStyle' => 'thin']]
];
$sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($headerStyle);

// Data rows
$no = 1;
$row = 8;
foreach ($attendance_data as $item) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, htmlspecialchars($item['day']));
    $sheet->setCellValue('C' . $row, date('d/m/Y', strtotime($item['date'])));
    $sheet->setCellValue('D' . $row, htmlspecialchars($item['start_time']));
    $sheet->setCellValue('E' . $row, htmlspecialchars($item['end_time']));
    $sheet->setCellValue('F' . $row, formatHours($item['total_hours']));

    // Add signature image
    if (!empty($item['signature'])) {
        $signature_path = __DIR__ . '/signatures/' . $item['signature'];
        if (file_exists($signature_path)) {
            $drawing = new Drawing();
            $drawing->setName('Signature');
            $drawing->setDescription('Digital Signature');
            $drawing->setPath($signature_path);
            $drawing->setHeight(40);
            $drawing->setWidth(80);
            $drawing->setCoordinates('G' . $row);
            $drawing->setWorksheet($sheet);
        } else {
            $sheet->setCellValue('G' . $row, 'File tanda tangan tidak ditemukan');
        }
    } else {
        $sheet->setCellValue('G' . $row, 'Tidak ada tanda tangan');
    }

    // Style data rows
    $dataStyle = [
        'borders' => ['allBorders' => ['borderStyle' => 'thin']],
        'alignment' => ['horizontal' => 'center']
    ];
    $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($dataStyle);
    $sheet->getStyle('G' . $row)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => 'thin']]]);

    $row++;
}

// Statistics
$statsRow = $row + 1;
$sheet->mergeCells('A' . $statsRow . ':E' . $statsRow);
$sheet->setCellValue('A' . $statsRow, 'TOTAL HARI KERJA:');
$sheet->mergeCells('F' . $statsRow . ':G' . $statsRow);
$sheet->setCellValue('F' . $statsRow, $total_days . ' hari');
$sheet->getStyle('A' . $statsRow . ':G' . $statsRow)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF3CD']],
    'borders' => ['allBorders' => ['borderStyle' => 'thin']]
]);

$statsRow++;
$sheet->mergeCells('A' . $statsRow . ':E' . $statsRow);
$sheet->setCellValue('A' . $statsRow, 'TOTAL JAM KERJA:');
$sheet->mergeCells('F' . $statsRow . ':G' . $statsRow);
$sheet->setCellValue('F' . $statsRow, formatHours($total_hours));
$sheet->getStyle('A' . $statsRow . ':G' . $statsRow)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF3CD']],
    'borders' => ['allBorders' => ['borderStyle' => 'thin']]
]);

$statsRow++;
$sheet->mergeCells('A' . $statsRow . ':E' . $statsRow);
$sheet->setCellValue('A' . $statsRow, 'RATA-RATA JAM/HARI:');
$sheet->mergeCells('F' . $statsRow . ':G' . $statsRow);
$sheet->setCellValue('F' . $statsRow, formatHours($avg_hours));
$sheet->getStyle('A' . $statsRow . ':G' . $statsRow)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF3CD']],
    'borders' => ['allBorders' => ['borderStyle' => 'thin']]
]);

// Footer
$footerRow = $statsRow + 2;
$sheet->mergeCells('A' . $footerRow . ':G' . $footerRow);
$sheet->setCellValue('A' . $footerRow, 'Dokumen ini dibuat secara otomatis oleh Sistem Absensi Digital');
$sheet->getStyle('A' . $footerRow)->getAlignment()->setHorizontal('center')->setVertical('center');
$sheet->getStyle('A' . $footerRow)->getFont()->setItalic(true);

$footerRow++;
$sheet->mergeCells('A' . $footerRow . ':G' . $footerRow);
$sheet->setCellValue('A' . $footerRow, 'Akhmad Firdaus - ' . date('d F Y'));
$sheet->getStyle('A' . $footerRow)->getAlignment()->setHorizontal('center')->setVertical('center');
$sheet->getStyle('A' . $footerRow)->getFont()->setItalic(true);

// Output Excel file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="laporan_absensi_' . $user['username'] . '_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;
?>
