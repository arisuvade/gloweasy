<?php
require '../includes/db.php';

header('Content-Type: application/json');

// Validate required parameters
if (!isset($_POST['date']) || !isset($_POST['branch_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Date and Branch ID are required']);
    exit;
}

$date = $_POST['date'];
$branch_id = intval($_POST['branch_id']);
$therapist_id = isset($_POST['therapist_id']) ? intval($_POST['therapist_id']) : 0;
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

// Get all booked time slots for the selected date, branch, and optionally therapist
$query = "SELECT TIME_FORMAT(b.booking_time, '%h:%i%p') as time_slot, 
                 b.therapist_id,
                 t.name as therapist_name
          FROM bookings b
          JOIN therapists t ON b.therapist_id = t.id
          WHERE b.booking_date = ? 
          AND b.branch_id = ?
          AND b.id != ?";
          
$params = [$date, $branch_id, $booking_id];
$types = "sii";

if ($therapist_id > 0) {
    $query .= " AND b.therapist_id = ?";
    $params[] = $therapist_id;
    $types .= "i";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$booked_slots = [];
$therapist_bookings = [];

while ($row = $result->fetch_assoc()) {
    $time_slot = strtoupper($row['time_slot']);
    $booked_slots[] = $time_slot;
    
    // Track which therapist is booked when
    if (!isset($therapist_bookings[$row['therapist_id']])) {
        $therapist_bookings[$row['therapist_id']] = [
            'name' => $row['therapist_name'],
            'slots' => []
        ];
    }
    $therapist_bookings[$row['therapist_id']]['slots'][] = $time_slot;
}

$stmt->close();

// Get all therapists for this branch
$stmt = $conn->prepare("SELECT id, name FROM therapists WHERE is_active = 1 AND branch_id = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$therapists_result = $stmt->get_result();
$therapists = $therapists_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

echo json_encode([
    'booked_slots' => $booked_slots,
    'therapist_bookings' => $therapist_bookings,
    'therapists' => $therapists
]);
?>