<?php
session_start();
require 'db.php';

// Check if either user or admin is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    die('<div class="alert alert-danger">Unauthorized access</div>');
}

$booking_id = $_GET['id'] ?? null;

if (!$booking_id || !is_numeric($booking_id)) {
    die('<div class="alert alert-danger">Invalid booking ID</div>');
}

// Prepare the base query - now including branch name
$query = "
    SELECT b.*, s.name AS service_name, s.description AS service_description, 
           s.regular_rate AS service_regular_rate, s.vip_elite_rate AS service_vip_rate,
           s.duration AS service_duration, u.name AS user_name, u.email AS user_email,
           br.name AS branch_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users u ON b.user_id = u.id
    JOIN branches br ON b.branch_id = br.id
    WHERE b.id = ?
";

// Add user/admin restriction
if (isset($_SESSION['user_id'])) {
    $query .= " AND b.user_id = ?";
    $params = [$booking_id, $_SESSION['user_id']];
    $types = "ii";
} else {
    $params = [$booking_id];
    $types = "i";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('<div class="alert alert-danger">Booking not found</div>');
}

$booking = $result->fetch_assoc();
$stmt->close();

// Fetch therapists assigned to this booking
$therapists = [];
$stmt = $conn->prepare("
    SELECT t.id, t.name, t.role 
    FROM booking_therapists bt
    JOIN therapists t ON bt.therapist_id = t.id
    WHERE bt.booking_id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$therapists_result = $stmt->get_result();
$therapists = $therapists_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch addons from booking_addons table
$addons = [];
$stmt = $conn->prepare("
    SELECT ba.*, a.name 
    FROM booking_addons ba
    JOIN addons a ON ba.addon_id = a.id
    WHERE ba.booking_id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$addons_result = $stmt->get_result();
$addons = $addons_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total duration
$total_duration = $booking['service_duration'];
foreach ($addons as $addon) {
    $total_duration += $addon['duration'];
}

$conn->close();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Receipt #<?= htmlspecialchars($booking['receipt_number']) ?></h4>
        <span class="status-badge status-<?= strtolower($booking['status']) ?>" id="bookingStatus" data-status="<?= $booking['status'] ?>">
            <?= ucfirst($booking['status']) ?>
        </span>
    </div>

    <?php if (isset($_SESSION['admin_id'])): ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="fw-bold mb-1">Customer Name</div>
            <div><?= htmlspecialchars($booking['user_name']) ?></div>
        </div>
        <div class="col-md-6">
            <div class="fw-bold mb-1">Customer Email</div>
            <div><?= htmlspecialchars($booking['user_email']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="fw-bold mb-1">Branch</div>
            <div><?= htmlspecialchars($booking['branch_name']) ?></div>
        </div>
        <div class="col-md-6">
            <div class="fw-bold mb-1">Therapist(s)</div>
            <div>
                <?php if (!empty($therapists)): ?>
                    <?php foreach ($therapists as $therapist): ?>
                        <div>
                            <?= htmlspecialchars($therapist['name']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    Any Therapist
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="fw-bold mb-1">Service</div>
            <div>
                <?= htmlspecialchars($booking['service_name']) ?>
                <div class="text-muted small"><?= htmlspecialchars($booking['service_description']) ?></div>
                <div class="text-muted small"><?= $booking['service_duration'] ?> minutes</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="fw-bold mb-1">Date & Time</div>
            <div><?= date("F j, Y", strtotime($booking['booking_date'])) ?></div>
            <div>
                <?= date("h:i A", strtotime($booking['booking_time'])) ?>
                <?php if ($booking['time_end']): ?> 
                    - <?= date("h:i A", strtotime($booking['time_end'] . ' +1 minute')) ?>
                <?php endif; ?>
            </div>

            <div class="fw-bold mt-2">Clients</div>
            <div><?= $booking['number_of_clients'] ?> client(s) using <?= $booking['bed_used'] ?> bed(s)</div>
        </div>
    </div>

    <?php if (!empty($booking['notes'])): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="fw-bold mb-1">Notes:</div>
            <div class="alert alert-warning"><?= htmlspecialchars($booking['notes']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($addons)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="fw-bold mb-2">Add-ons</div>
            <div class="list-group">
                <?php foreach ($addons as $addon): ?>
                    <div class="list-group-item py-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <?= htmlspecialchars($addon['name']) ?>
                                <span class="text-muted small">(<?= $addon['duration'] ?> mins)</span>
                            </div>
                            <div>
                                ₱<?= number_format($addon['regular_rate'], 2) ?>
                                <span class="text-success small">(Discounted: ₱<?= number_format($addon['vip_elite_rate'], 2) ?>)</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mt-4 border-top pt-3">
        <div class="col-md-6">
            <div class="fw-bold mb-1">Service Price</div>
            <div class="d-flex justify-content-between">
                <span>Regular:</span>
                <span>₱<?= number_format($booking['service_regular_rate'], 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span>VIP/Elite:</span>
                <span>₱<?= number_format($booking['service_vip_rate'], 2) ?></span>
            </div>
        </div>
        <div class="col-md-6">
            <div class="fw-bold mb-1">Total Amount</div>
            <div class="d-flex justify-content-between">
                <span>Regular Total:</span>
                <span class="h5">₱<?= number_format($booking['total_amount'], 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span>VIP/Elite Total:</span>
                <span class="h5">₱<?= number_format($booking['vip_elite_amount'], 2) ?></span>
            </div>
            <div class="fw-bold mt-3 mb-1">Total Duration</div>
            <div><?= $total_duration ?> minutes</div>
        </div>
    </div>
</div>