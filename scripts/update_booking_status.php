<?php
require_once __DIR__ . '/../includes/db.php';

// Timezone configuration
date_default_timezone_set('Asia/Manila');

// Log directory setup
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/booking_status_updater.log';

// Create logs directory if it doesn't exist
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Get current datetime - can be overridden by URL parameter
if (isset($_GET['test'])) {
    // Format: YYYY-MM-DD%20HH:MM:SS (URL encoded space)
    $testDateTime = str_replace('%20', ' ', $_GET['test']);
    $currentDateTime = new DateTime($testDateTime);
} else {
    $currentDateTime = new DateTime(); // Normal operation
    
    // Alternative testing method (uncomment when needed):
    // $currentDateTime = new DateTime('2025-04-20 11:00:00');
}

$currentDate = $currentDateTime->format('Y-m-d');
$currentTime = $currentDateTime->format('H:i:00'); // Round to whole minute

// Initialize output message
$output = "[" . $currentDateTime->format('Y-m-d H:i:s') . "] Starting booking status update check\n";

try {
    // Find bookings that match current date and time and are Pending
    $query = "
        SELECT id, booking_date, booking_time 
        FROM bookings 
        WHERE booking_date = ? 
        AND booking_time = ? 
        AND status = 'Pending'
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $currentDate, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookingsToUpdate = $result->fetch_all(MYSQLI_ASSOC);
    $updateCount = count($bookingsToUpdate);
    $stmt->close();
    
    $output .= "Found $updateCount bookings to update\n";
    
    if ($updateCount > 0) {
        // Update status to Active for matched bookings
        $updateQuery = "
            UPDATE bookings 
            SET status = 'Active'
            WHERE booking_date = ? 
            AND booking_time = ? 
            AND status = 'Pending'
        ";
        
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ss", $currentDate, $currentTime);
        $updateStmt->execute();
        $affectedRows = $updateStmt->affected_rows;
        $updateStmt->close();
        
        $output .= "Successfully updated $affectedRows bookings to Active status\n";
        
        // Log details of updated bookings
        foreach ($bookingsToUpdate as $booking) {
            $output .= sprintf(
                " - Booking ID: %d, Date: %s, Time: %s\n",
                $booking['id'],
                $booking['booking_date'],
                $booking['booking_time']
            );
        }
    }
    
} catch (Exception $e) {
    $output .= "Error updating bookings: " . $e->getMessage() . "\n";
}

// Write to log file
file_put_contents($logFile, $output, FILE_APPEND);

// Output the results
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    echo $output;
}
?>