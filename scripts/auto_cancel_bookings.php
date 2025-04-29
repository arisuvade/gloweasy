<?php
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');
$now = new DateTime();

// Set up logging
$logFile = __DIR__ . '/../logs/auto_cancel_bookings.log';
$output = "[".$now->format('Y-m-d H:i:s')."] Starting auto-cancel check\n";

try {
    // Get count of active bookings
    $countQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'Active'";
    $countResult = $conn->query($countQuery);
    $activeCount = $countResult->fetch_assoc()['count'];
    $countResult->close();
    
    $output .= "Found $activeCount active bookings to cancel\n";
    
    if ($activeCount > 0) {
        // Get details of bookings being cancelled for logging
        $detailsQuery = "SELECT id, booking_date, booking_time FROM bookings WHERE status = 'Active'";
        $detailsResult = $conn->query($detailsQuery);
        $bookings = $detailsResult->fetch_all(MYSQLI_ASSOC);
        $detailsResult->close();
        
        // Cancel all active bookings
        $updateQuery = "UPDATE bookings SET status = 'Cancelled', 
                          notes = CONCAT(IFNULL(notes, ''), ' [Auto-cancelled at ', NOW(), ']')
                        WHERE status = 'Active'";
        $conn->query($updateQuery);
        
        $output .= "Successfully cancelled $activeCount bookings:\n";
        foreach ($bookings as $booking) {
            $output .= " - Cancelled Booking ID: ".$booking['id'].", Original Time: ".$booking['booking_date']." ".$booking['booking_time']."\n";
        }
    } else {
        $output .= "No active bookings to cancel\n";
    }
    
} catch (Exception $e) {
    $output .= "Error cancelling bookings: ".$e->getMessage()."\n";
}

// Write to log file
file_put_contents($logFile, $output, FILE_APPEND);

// Output to browser when run manually
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    echo $output;
}