<?php
require '../includes/db.php';

header('Content-Type: application/json');

// Validate required parameters
if (!isset($_POST['bookingId']) || !isset($_POST['date']) || !isset($_POST['time'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

$bookingId = intval($_POST['bookingId']);
$date = $_POST['date'];
$timeInput = $_POST['time'];
$therapistId = isset($_POST['therapistId']) ? intval($_POST['therapistId']) : 0;

// Validate and convert time
$time = date("H:i:s", strtotime($timeInput));
if ($time === '00:00:00' || $time === false) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid time format']);
    exit;
}

// Get complete booking details with all necessary information
$stmt = $conn->prepare("
    SELECT 
        b.user_id, b.branch_id, b.service_id, b.booking_date, b.booking_time, b.time_end,
        b.bed_used, b.number_of_clients, b.total_duration, b.status,
        s.category AS service_category, s.duration AS service_duration,
        (SELECT COUNT(*) FROM booking_therapists bt WHERE bt.booking_id = b.id) AS therapist_count
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.id = ?
");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $bookingId);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
    exit;
}

$booking = $result->fetch_assoc();

// Check if booking can be rescheduled
if (!in_array($booking['status'], ['Pending', 'Active'])) {
    echo json_encode(['status' => 'error', 'message' => 'Only pending or active bookings can be rescheduled']);
    exit;
}

// Use total_duration if available, otherwise fall back to service_duration
$duration = !empty($booking['total_duration']) ? $booking['total_duration'] : $booking['service_duration'];

// Calculate end time (subtract 1 minute as per business logic)
$endTime = date("H:i:s", strtotime($time) + ($duration * 60) - 60);

// Start transaction
$conn->begin_transaction();

try {
    $assignedTherapistIds = [];

    // Check bed availability first (excluding current booking)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as booked_beds
        FROM bookings
        WHERE branch_id = ?
        AND booking_date = ?
        AND status IN ('Pending', 'Active')
        AND (
            (? BETWEEN booking_time AND time_end) OR
            (? BETWEEN booking_time AND time_end) OR
            (booking_time BETWEEN ? AND ?) OR
            (time_end BETWEEN ? AND ?)
        )
        AND id != ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param(
        "isssssssi",
        $booking['branch_id'],
        $date,
        $time,
        $endTime,
        $time,
        $endTime,
        $time,
        $endTime,
        $bookingId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $bedUsage = $result->fetch_assoc();
    $stmt->close();
    
    // Get total beds available at this branch
    $stmt = $conn->prepare("SELECT bed_count FROM branches WHERE id = ?");
    $stmt->bind_param("i", $booking['branch_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $branch = $result->fetch_assoc();
    $stmt->close();
    
    $availableBeds = $branch['bed_count'] - $bedUsage['booked_beds'];
    if ($availableBeds < $booking['bed_used']) {
        throw new Exception("Not enough beds available. Only $availableBeds bed(s) left.");
    }

    // For Body Healing packages - assign multiple therapists (excluding current booking)
    if ($booking['service_category'] === 'Body Healing') {
        $therapistsNeeded = $booking['number_of_clients'];
        
        $stmt = $conn->prepare("
            SELECT t.id 
            FROM therapists t
            WHERE t.is_active = 1 
            AND t.branch_id = ?
            AND NOT EXISTS (
                SELECT 1 FROM therapist_availability ta 
                WHERE ta.therapist_id = t.id 
                AND ? BETWEEN ta.start_date AND ta.end_date
            )
            AND NOT EXISTS (
                SELECT 1 FROM booking_therapists bt
                JOIN bookings b ON bt.booking_id = b.id
                WHERE bt.therapist_id = t.id
                AND b.id != ?
                AND b.booking_date = ?
                AND b.status IN ('Pending', 'Active')
                AND (
                    (? BETWEEN b.booking_time AND b.time_end) OR
                    (? BETWEEN b.booking_time AND b.time_end) OR
                    (b.booking_time BETWEEN ? AND ?) OR
                    (b.time_end BETWEEN ? AND ?)
                )
            )
            ORDER BY t.id DESC
            LIMIT ?
        ");
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param(
            "isisssssssi", 
            $booking['branch_id'],
            $date,
            $bookingId,  // Exclude current booking
            $date,
            $time,
            $endTime,
            $time,
            $endTime,
            $time,
            $endTime,
            $therapistsNeeded
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $availableTherapists = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($availableTherapists)) {
            throw new Exception("No therapists available for Body Healing package at this time");
        }
        
        if (count($availableTherapists) < $therapistsNeeded) {
            throw new Exception("Only " . count($availableTherapists) . " therapists available (needed: $therapistsNeeded)");
        }
        
        $assignedTherapistIds = array_column($availableTherapists, 'id');
    }
    // For Regular services
    else {
        // "Any Available Therapist" selected
        if ($therapistId == 0) {
            $stmt = $conn->prepare("
                SELECT t.id 
                FROM therapists t
                WHERE t.is_active = 1 
                AND t.branch_id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM therapist_availability ta 
                    WHERE ta.therapist_id = t.id 
                    AND ? BETWEEN ta.start_date AND ta.end_date
                )
                AND NOT EXISTS (
                    SELECT 1 FROM booking_therapists bt
                    JOIN bookings b ON bt.booking_id = b.id
                    WHERE bt.therapist_id = t.id
                    AND b.id != ?
                    AND b.booking_date = ?
                    AND b.status IN ('Pending', 'Active')
                    AND (
                        (? BETWEEN b.booking_time AND b.time_end) OR
                        (? BETWEEN b.booking_time AND b.time_end) OR
                        (b.booking_time BETWEEN ? AND ?) OR
                        (b.time_end BETWEEN ? AND ?)
                    )
                )
                ORDER BY t.id DESC
                LIMIT 1
            ");
            
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param(
                "isissssss", 
                $booking['branch_id'],
                $date,
                $bookingId,  // Exclude current booking
                $date,
                $time,
                $endTime,
                $time,
                $endTime,
                $time,
                $endTime
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $availableTherapist = $result->fetch_assoc();
            $stmt->close();
            
            if (!$availableTherapist) {
                throw new Exception("No available therapists at this time");
            }
            
            $assignedTherapistIds = [$availableTherapist['id']];
            $therapistId = $availableTherapist['id'];
        }
        // Specific therapist selected
        else {
            // Verify therapist exists and is available (excluding current booking)
            $stmt = $conn->prepare("
                SELECT 1
                FROM therapists t
                WHERE t.id = ?
                AND t.is_active = 1
                AND t.branch_id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM therapist_availability ta 
                    WHERE ta.therapist_id = t.id 
                    AND ? BETWEEN ta.start_date AND ta.end_date
                )
                AND NOT EXISTS (
                    SELECT 1 FROM booking_therapists bt
                    JOIN bookings b ON bt.booking_id = b.id
                    WHERE bt.therapist_id = t.id
                    AND b.id != ?
                    AND b.booking_date = ?
                    AND b.status IN ('Pending', 'Active')
                    AND (
                        (? BETWEEN b.booking_time AND b.time_end) OR
                        (? BETWEEN b.booking_time AND b.time_end) OR
                        (b.booking_time BETWEEN ? AND ?) OR
                        (b.time_end BETWEEN ? AND ?)
                    )
                )
            ");
            
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param(
                "iisisssssss", 
                $therapistId,
                $booking['branch_id'],
                $date,
                $bookingId,  // Exclude current booking
                $date,
                $time,
                $endTime,
                $time,
                $endTime,
                $time,
                $endTime
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $isAvailable = $result->num_rows > 0;
            $stmt->close();
            
            if (!$isAvailable) {
                throw new Exception("The selected therapist is not available at this time");
            }
            
            $assignedTherapistIds = [$therapistId];
        }
    }

    // Update the booking
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET booking_date = ?, 
            booking_time = ?, 
            time_end = ?,
            status = ?
        WHERE id = ? AND user_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    // Keep the same status when rescheduling
    $status = $booking['status'];
    
    $stmt->bind_param("ssssii", $date, $time, $endTime, $status, $bookingId, $booking['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update booking: " . $stmt->error);
    }
    $stmt->close();

    // Update therapist assignments
    $stmt = $conn->prepare("DELETE FROM booking_therapists WHERE booking_id = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    $stmt->bind_param("i", $bookingId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to clear therapist assignments: " . $stmt->error);
    }
    $stmt->close();

    // Add new therapist assignments if any
    if (!empty($assignedTherapistIds)) {
        $stmt = $conn->prepare("INSERT INTO booking_therapists (booking_id, therapist_id) VALUES (?, ?)");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        foreach ($assignedTherapistIds as $tid) {
            $stmt->bind_param("ii", $bookingId, $tid);
            if (!$stmt->execute()) {
                throw new Exception("Failed to assign therapist: " . $stmt->error);
            }
        }
        $stmt->close();
    }

    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Booking rescheduled successfully',
        'therapist_ids' => $assignedTherapistIds,
        'number_of_clients' => $booking['number_of_clients'],
        'bed_used' => $booking['bed_used'],
        'booking_time' => $time,
        'time_end' => $endTime,
        'booking_date' => $date
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Reschedule Booking Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'debug' => [
            'bookingId' => $bookingId,
            'date' => $date,
            'time' => $time,
            'endTime' => $endTime,
            'duration' => $duration,
            'branch_id' => $booking['branch_id'] ?? null,
            'service_category' => $booking['service_category'] ?? null,
            'therapistId' => $therapistId
        ]
    ]);
}

$conn->close();