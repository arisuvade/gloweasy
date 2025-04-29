<!-- every 30 mins -->

<?php
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');
$now = new DateTime();
$cutoff = (clone $now)->modify('+4 hours');

// Store formatted times in variables
$today = $now->format('Y-m-d');
$cutoff_time = $cutoff->format('H:i:00');

// Disable reschedule within next 4 hours
$stmt = $conn->prepare("
    UPDATE bookings 
    SET allow_reschedule = 0 
    WHERE status = 'Pending'
    AND allow_reschedule = 1
    AND (
        booking_date < ? 
        OR (booking_date = ? AND booking_time <= ?)
    )
");
$stmt->bind_param("sss", $today, $today, $cutoff_time);
$stmt->execute();
$stmt->close();

// Enable reschedule beyond 4 hours
$stmt = $conn->prepare("
    UPDATE bookings 
    SET allow_reschedule = 1 
    WHERE status = 'Pending'
    AND allow_reschedule = 0
    AND (
        booking_date > ? 
        OR (booking_date = ? AND booking_time > ?)
    )
");
$stmt->bind_param("sss", $today, $today, $cutoff_time);
$stmt->execute();
$stmt->close();
