<?php
session_start();
require '../includes/db.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// Validate required POST data
$required_fields = ['serviceId', 'bookingDate', 'time', 'totalRegularRate', 'totalVipEliteRate', 'branchId', 'totalDuration'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$serviceId = $_POST['serviceId'];
$bookingDate = $_POST['bookingDate'];
$time = $_POST['time'];
$totalRegularRate = $_POST['totalRegularRate'];
$totalVipEliteRate = $_POST['totalVipEliteRate'];
$branchId = $_POST['branchId'];
$totalDuration = (int)$_POST['totalDuration'];
$numberOfClients = isset($_POST['numberOfClients']) ? (int)$_POST['numberOfClients'] : 1;
$bedUsed = isset($_POST['bedUsed']) ? (int)$_POST['bedUsed'] : 1;

// Validate date and time
try {
    // Create DateTime objects for comparison
    $currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $bookingDateTime = DateTime::createFromFormat('Y-m-d h:i A', $bookingDate . ' ' . $time, new DateTimeZone('Asia/Manila'));
    
    if (!$bookingDateTime) {
        throw new Exception("Invalid date or time format");
    }
    
    // Check if booking is in the past
    if ($bookingDateTime < $currentDateTime) {
        throw new Exception("You cannot book a time in the past. Please select a future time.");
    }
    
    // Check if booking is at least 1 hour in advance
    $minimumBookingTime = (clone $currentDateTime)->add(new DateInterval('PT1H'));
    if ($bookingDateTime < $minimumBookingTime) {
        throw new Exception("Please book at least 1 hour in advance.");
    }
    
    // Convert to proper formats for database
    $formattedDate = $bookingDateTime->format('Y-m-d');
    $formattedTime = $bookingDateTime->format('H:i:s');
    
    // Calculate end time
    $endTime = (clone $bookingDateTime)->add(new DateInterval('PT' . $totalDuration . 'M'))->format('H:i:s');
    
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit();
}

// Get service category
$stmt = $conn->prepare("SELECT category FROM services WHERE id = ?");
$stmt->bind_param("i", $serviceId);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

$serviceCategory = $service['category'] ?? 'Regular';

// Validate number of clients and beds
if ($numberOfClients < 1 || $numberOfClients > 4) {
    echo json_encode(["status" => "error", "message" => "Invalid number of clients (1-4 allowed)"]);
    exit();
}

if ($bedUsed < 1 || $bedUsed > 4) {
    echo json_encode(["status" => "error", "message" => "Invalid bed count (1-4 allowed)"]);
    exit();
}

// Handle addons data
$addons = [];
if (isset($_POST['addons'])) {
    if (is_string($_POST['addons'])) {
        $addons = json_decode($_POST['addons'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(["status" => "error", "message" => "Invalid addons data format"]);
            exit();
        }
    } elseif (is_array($_POST['addons'])) {
        $addons = $_POST['addons'];
    }
}

// Generate receipt number with branch prefix
$branchPrefix = strtoupper(substr($branchId == 1 ? 'MLS' : 'CLP', 0, 3));
$stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(receipt_number, 5) AS UNSIGNED)) AS last_receipt FROM bookings WHERE receipt_number LIKE ?");
$prefixPattern = $branchPrefix . '-%';
$stmt->bind_param("s", $prefixPattern);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$lastReceipt = $row['last_receipt'] ?? 0;
$stmt->close();

$receiptNumber = $branchPrefix . '-' . str_pad($lastReceipt + 1, 6, '0', STR_PAD_LEFT);

// Start transaction
$conn->begin_transaction();

try {
    // Create booking with all time and duration information
    $stmt = $conn->prepare("
        INSERT INTO bookings 
        (user_id, service_id, branch_id, booking_date, booking_time, time_end, 
         total_amount, vip_elite_amount, total_duration, receipt_number, status,
         bed_used, number_of_clients) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
    ");
    
    $stmt->bind_param(
        "iiisssddisii", 
        $user_id, 
        $serviceId, 
        $branchId, 
        $formattedDate,
        $formattedTime,
        $endTime,
        $totalRegularRate, 
        $totalVipEliteRate,
        $totalDuration,
        $receiptNumber,
        $bedUsed,
        $numberOfClients
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Booking failed: " . $stmt->error);
    }
    
    $bookingId = $conn->insert_id;
    $stmt->close();

    $assignedTherapistIds = [];

    if ($serviceCategory === 'Body Healing') {
        // For Body Healing packages - assign multiple available therapists
        $therapistsNeeded = $numberOfClients;
        
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
        
        $stmt->bind_param(
            "issssssssi", 
            $branchId,
            $formattedDate,
            $formattedDate,
            $formattedTime,
            $endTime,
            $formattedTime,
            $endTime,
            $formattedTime,
            $endTime,
            $therapistsNeeded
        );
        
        $stmt->execute();
        $result = $stmt->get_result();
        $availableTherapists = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($availableTherapists)) {
            throw new Exception("No therapists available for this booking.");
        }
        
        if (count($availableTherapists) < $therapistsNeeded) {
            throw new Exception("Not enough therapists available. Only " . count($availableTherapists) . " available.");
        }
        
        $assignedTherapistIds = array_column($availableTherapists, 'id');
    } else {
        // For Regular services
        $therapistId = $_POST['therapistId'] ?? 0;
        
        if ($therapistId == 0) {
            // "Any Available Therapist" selected - assign highest ID available
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
            
            $stmt->bind_param(
                "issssssss", 
                $branchId,
                $formattedDate,
                $formattedDate,
                $formattedTime,
                $endTime,
                $formattedTime,
                $endTime,
                $formattedTime,
                $endTime
            );
            
            $stmt->execute();
            $result = $stmt->get_result();
            $availableTherapist = $result->fetch_assoc();
            $stmt->close();
            
            if (!$availableTherapist) {
                throw new Exception("No therapists available at this time.");
            }
            
            $assignedTherapistIds = [$availableTherapist['id']];
        } else {
            // Specific therapist selected - verify availability
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
            
            $stmt->bind_param(
                "iissssssss", 
                $therapistId,
                $branchId,
                $formattedDate,
                $formattedDate,
                $formattedTime,
                $endTime,
                $formattedTime,
                $endTime,
                $formattedTime,
                $endTime
            );
            
            $stmt->execute();
            $result = $stmt->get_result();
            $isAvailable = $result->num_rows > 0;
            $stmt->close();
            
            if (!$isAvailable) {
                throw new Exception("Selected therapist is not available at this time.");
            }
            
            $assignedTherapistIds = [$therapistId];
        }
    }

    // Now assign therapists to booking
    if (!empty($assignedTherapistIds)) {
        $stmt = $conn->prepare("INSERT INTO booking_therapists (booking_id, therapist_id) VALUES (?, ?)");
        foreach ($assignedTherapistIds as $therapistId) {
            $stmt->bind_param("ii", $bookingId, $therapistId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to assign therapist: " . $stmt->error);
            }
        }
        $stmt->close();
    } else {
        throw new Exception("No therapists were assigned to this booking.");
    }

    // Insert addons if any
    if (!empty($addons)) {
        $stmt = $conn->prepare("
            INSERT INTO booking_addons 
            (booking_id, addon_id, regular_rate, vip_elite_rate, duration) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($addons as $addon) {
            if (!isset($addon['id'], $addon['regular_rate'], $addon['vip_rate'], $addon['duration'])) {
                continue;
            }
            
            $stmt->bind_param(
                "iiddi", 
                $bookingId, 
                $addon['id'], 
                $addon['regular_rate'], 
                $addon['vip_rate'], 
                $addon['duration']
            );
            if (!$stmt->execute()) {
                throw new Exception("Failed to save addons: " . $stmt->error);
            }
        }
        $stmt->close();
    }

    $conn->commit();
    echo json_encode([
        "status" => "success", 
        "message" => "Booking confirmed! Receipt #$receiptNumber", 
        "receipt_number" => $receiptNumber,
        "therapist_ids" => $assignedTherapistIds,
        "number_of_clients" => $numberOfClients,
        "bed_used" => $bedUsed
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>