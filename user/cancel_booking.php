<?php
session_start();
require '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$bookingId = $_POST['bookingId'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$bookingId || !is_numeric($bookingId)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Verify booking belongs to user
    $stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bookingId, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        throw new Exception("Booking not found or unauthorized");
    }
    $stmt->close();

    // Update status
    $stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?");
    $stmt->bind_param("i", $bookingId);
    
    if (!$stmt->execute()) {
        throw new Exception("Database update failed");
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Booking cancelled successfully']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
?>