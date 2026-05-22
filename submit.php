<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $day = $_POST['day'];
    $date = $_POST['date'];
    $start_time = $_POST['startTime'];
    $end_time = $_POST['endTime'];
    $total_hours = $_POST['totalHours'];
    $signature = $_POST['signature'];

    // Validate input
    if (empty($day) || empty($date) || empty($start_time) || empty($end_time) || empty($signature)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    // Check if attendance already exists for this date
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $date]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Attendance already exists for this date']);
        exit;
    }

    // Save signature as image file
    $signature_filename = null;
    if (!empty($signature)) {
        // Create signatures directory if it doesn't exist
        $signature_dir = 'signatures';
        if (!is_dir($signature_dir)) {
            mkdir($signature_dir, 0755, true);
        }

        // Extract base64 data from data URL
        $signature_data = explode(',', $signature)[1];
        $signature_decoded = base64_decode($signature_data);

        // Generate unique filename
        $signature_filename = 'signature_' . $user_id . '_' . time() . '.png';
        $signature_path = $signature_dir . '/' . $signature_filename;

        // Save image file
        if (file_put_contents($signature_path, $signature_decoded)) {
            // File saved successfully
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save signature image']);
            exit;
        }
    }

    // Insert attendance
    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, day, date, start_time, end_time, total_hours, signature) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $day, $date, $start_time, $end_time, $total_hours, $signature_filename])) {
        echo json_encode(['success' => true, 'message' => 'Attendance saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save attendance']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
