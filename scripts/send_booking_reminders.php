<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/send_email.php';

// Timezone configuration
date_default_timezone_set('Asia/Manila');

// Log directory setup
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/booking_reminders.log';

// Create logs directory if it doesn't exist
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Get current datetime - can be overridden by URL parameter
if (isset($_GET['test'])) {
    // Format: YYYY-MM-DD%20HH:MM:SS (URL encoded space)
    $currentDateTime = new DateTime(str_replace('%20', ' ', $_GET['test']));
} else {
    $currentDateTime = new DateTime(); // Normal operation
    
    // Alternative testing method (uncomment when needed):
    // $currentDateTime = new DateTime('2025-04-19 13:00:00');
}

$twentyFourHoursLater = clone $currentDateTime;
$twentyFourHoursLater->modify('+24 hours');

// Format for database comparison
$reminderDate = $twentyFourHoursLater->format('Y-m-d');
$reminderTime = $twentyFourHoursLater->format('H:i:00');

// Initialize output message
$output = "[" . $currentDateTime->format('Y-m-d H:i:s') . "] Starting booking reminders check (24hrs in advance)\n";

try {
    // Find upcoming bookings in 24 hours that are Pending or Active
    $query = "
        SELECT b.id, b.booking_date, b.booking_time, b.status, b.receipt_number,
               u.name as customer_name, u.email as customer_email,
               s.name as service_name, br.name as branch_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN services s ON b.service_id = s.id
        JOIN branches br ON b.branch_id = br.id
        WHERE b.status IN ('Pending', 'Active')
        AND b.booking_date = ?
        AND b.booking_time = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $reminderDate, $reminderTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookingsToRemind = $result->fetch_all(MYSQLI_ASSOC);
    $reminderCount = count($bookingsToRemind);
    $stmt->close();
    
    $output .= "Found $reminderCount bookings to remind (scheduled for $reminderDate $reminderTime)\n";
    
    if ($reminderCount > 0) {
        $successCount = 0;
        
        foreach ($bookingsToRemind as $booking) {
            // Prepare email content
            $subject = "Reminder: Your Spa Booking Tomorrow";
            
            $message = "
                <p>Dear {$booking['customer_name']},</p>
                
                <p>This is a friendly reminder about your upcoming spa appointment:</p>
                
                <p><strong>Service:</strong> {$booking['service_name']}<br>
                <strong>Branch:</strong> {$booking['branch_name']}<br>
                <strong>Date:</strong> " . date('F j, Y', strtotime($booking['booking_date'])) . "<br>
                <strong>Time:</strong> " . date('g:i A', strtotime($booking['booking_time'])) . "<br>
                <strong>Receipt #:</strong> {$booking['receipt_number']}</p>
                
                <p>Please arrive 10-15 minutes before your scheduled time.</p>
    
                <p>If you need to reschedule or cancel, you can do so directly on our website at least 4 hours in advance.</p>

                <p><strong>Please note:</strong> If you arrive more than 15 minutes late, your appointment may be automatically canceled to accommodate other guests.</p>
                
                <p>We look forward to serving you!</p>
            ";
            
            // ACTUALLY SEND THE EMAIL (no test mode)
            $emailSent = sendEmail($booking['customer_email'], $subject, $message);
            
            if ($emailSent) {
                $successCount++;
                $output .= " - Sent reminder for Booking ID: {$booking['id']} to {$booking['customer_email']}\n";
            } else {
                $output .= " - Failed to send reminder for Booking ID: {$booking['id']}\n";
            }
        }
        
        $output .= "Successfully sent $successCount out of $reminderCount reminders\n";
    }
    
} catch (Exception $e) {
    $output .= "Error sending reminders: " . $e->getMessage() . "\n";
}

// Write to log file
file_put_contents($logFile, $output, FILE_APPEND);

// Output the results
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    echo $output;
}
?>