<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $user_id = $_SESSION['user_id'];
    $attendance_id = $_POST['id'];

    // Check if the attendance belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE id = ? AND user_id = ?");
    $stmt->execute([$attendance_id, $user_id]);
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Attendance not found or not authorized']);
        exit;
    }

    // Delete attendance
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$attendance_id, $user_id])) {
        echo json_encode(['success' => true, 'message' => 'Attendance deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete attendance']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
