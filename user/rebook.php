<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) {
        die("Unauthorized access");
    }
    header("Location: ../includes/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$is_ajax = isset($_GET['ajax']);

// Fetch the original booking details
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, s.regular_rate, s.vip_elite_rate, 
           s.duration, s.category, br.name AS branch_name, br.id AS branch_id,
           GROUP_CONCAT(bt.therapist_id) AS therapist_ids
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN branches br ON b.branch_id = br.id
    LEFT JOIN booking_therapists bt ON b.id = bt.booking_id
    WHERE b.id = ? AND b.user_id = ?
    GROUP BY b.id
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$original_booking = $result->fetch_assoc();
$stmt->close();

if (!$original_booking) {
    if ($is_ajax) {
        die("Booking not found");
    }
    header("Location: bookings.php");
    exit();
}

// Fetch therapists for the branch
$therapists = [];
$stmt = $conn->prepare("SELECT id, name FROM therapists WHERE is_active = 1 AND branch_id = ?");
$stmt->bind_param("i", $original_booking['branch_id']);
$stmt->execute();
$result = $stmt->get_result();
$therapists = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all active addons
$all_addons = [];
$addons_query = $conn->query("SELECT id, name, regular_rate, vip_elite_rate, duration FROM addons WHERE is_active = 1");
if ($addons_query) {
    while ($row = $addons_query->fetch_assoc()) {
        $all_addons[$row['id']] = $row;
    }
}

// Fetch addons for this booking
$booking_addons = [];
$stmt = $conn->prepare("
    SELECT a.id 
    FROM booking_addons ba
    JOIN addons a ON ba.addon_id = a.id
    WHERE ba.booking_id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $booking_addons[$row['id']] = true;
}
$stmt->close();

$conn->close();

if ($is_ajax):
?>
<div class="rebooking-container">
    <input type="hidden" id="originalBookingId" value="<?= $original_booking['id'] ?>">
    <input type="hidden" id="serviceId" value="<?= $original_booking['service_id'] ?>">
    <input type="hidden" id="serviceDuration" value="<?= $original_booking['duration'] ?>">
    <input type="hidden" id="serviceRegularRate" value="<?= $original_booking['regular_rate'] ?>">
    <input type="hidden" id="serviceVipRate" value="<?= $original_booking['vip_elite_rate'] ?>">
    <input type="hidden" id="branchId" value="<?= $original_booking['branch_id'] ?>">
    
    <div class="current-booking-info mb-4">
        <h5>Rebooking: <?= htmlspecialchars($original_booking['service_name']) ?></h5>
        <p><strong>Branch:</strong> <?= htmlspecialchars($original_booking['branch_name']) ?></p>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Therapist</label>
                <select id="therapist" class="form-select <?= $original_booking['category'] === 'Body Healing' ? 'disabled' : '' ?>" <?= $original_booking['category'] === 'Body Healing' ? 'disabled' : '' ?>>
                    <option value="0">Any Available Therapist</option>
                    <?php foreach ($therapists as $therapist): 
                        $selected = strpos($original_booking['therapist_ids'], (string)$therapist['id']) !== false ? 'selected' : '';
                    ?>
                        <option value="<?= $therapist['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($therapist['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>New Date</label>
                <input type="date" id="bookingDate" class="form-control" 
                       min="<?= date('Y-m-d') ?>" 
                       value="<?= date('Y-m-d') ?>">
            </div>
        </div>
    </div>

    <?php if ($original_booking['category'] === 'Body Healing'): ?>
    <div class="form-group">
        <label>Number of Clients</label>
        <select id="numberOfClients" class="form-select">
            <option value="1" <?= $original_booking['number_of_clients'] == 1 ? 'selected' : '' ?>>1</option>
            <option value="2" <?= $original_booking['number_of_clients'] == 2 ? 'selected' : '' ?>>2</option>
            <option value="3" <?= $original_booking['number_of_clients'] == 3 ? 'selected' : '' ?>>3</option>
            <option value="4" <?= $original_booking['number_of_clients'] == 4 ? 'selected' : '' ?>>4</option>
        </select>
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label class="d-block text-center mb-3">Select New Time</label>
        <div class="time-period-tabs text-center mb-3">
            <div class="time-period-tab active" data-period="morning">Morning</div>
            <div class="time-period-tab" data-period="afternoon">Afternoon</div>
            <div class="time-period-tab" data-period="evening">Evening</div>
        </div>
        <div class="time-slots-wrapper">
            <div class="time-slots-container active" data-period="morning"></div>
            <div class="time-slots-container" data-period="afternoon"></div>
            <div class="time-slots-container" data-period="evening"></div>
        </div>
    </div>

    <?php if ($original_booking['category'] === 'Regular'): ?>
    <div class="addons-section">
        <button type="button" id="addonsBtn" class="btn-oblong">
            <i class="fas fa-plus-circle me-2"></i> Select Addons (<?= count($booking_addons) ?> selected)
        </button>
    </div>
    <?php endif; ?>

    <div class="form-group mt-4">
        <button id="confirmRebookBtn" class="btn-oblong">
            <i class="fas fa-calendar-check me-2"></i> Confirm Rebooking
        </button>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize time slots
    const morningSlots = ['11:00 AM', '11:30 AM'];
    const afternoonSlots = ['12:00 PM', '12:30 PM', '1:00 PM', '1:30 PM', '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM', '4:00 PM', '4:30 PM'];
    const eveningSlots = ['5:00 PM', '5:30 PM', '6:00 PM', '6:30 PM', '7:00 PM', '7:30 PM', '8:00 PM', '8:30 PM'];
    
    const morningContainer = $('.time-slots-container[data-period="morning"]');
    const afternoonContainer = $('.time-slots-container[data-period="afternoon"]');
    const eveningContainer = $('.time-slots-container[data-period="evening"]');
    
    morningSlots.forEach(slot => {
        morningContainer.append(`<div class="time-slot">${slot}</div>`);
    });
    
    afternoonSlots.forEach(slot => {
        afternoonContainer.append(`<div class="time-slot">${slot}</div>`);
    });
    
    eveningSlots.forEach(slot => {
        eveningContainer.append(`<div class="time-slot">${slot}</div>`);
    });
    
    // Time period tab click handler
    $(document).on('click', '.time-period-tab', function() {
        const period = $(this).data('period');
        
        $('.time-period-tab').removeClass('active');
        $(this).addClass('active');
        
        $('.time-slots-container').removeClass('active');
        $(`.time-slots-container[data-period="${period}"]`).addClass('active');
    });

    // Time slot selection
    $(document).on('click', '.time-slot', function() {
        $('.time-slot').removeClass('selected');
        $(this).addClass('selected');
    });
    
    // Addons button click handler
    $('#addonsBtn').click(function() {
        const modalHTML = `
        <div class="modal fade" id="addonsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Select Addons</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="addons-list">
                            <?php foreach ($all_addons as $addon): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input addon-checkbox" type="checkbox" 
                                       id="addon-<?= $addon['id'] ?>" 
                                       value="<?= $addon['id'] ?>"
                                       data-regular-rate="<?= $addon['regular_rate'] ?>"
                                       data-vip-rate="<?= $addon['vip_elite_rate'] ?>"
                                       data-duration="<?= $addon['duration'] ?>"
                                       <?= isset($booking_addons[$addon['id']]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="addon-<?= $addon['id'] ?>">
                                    <?= htmlspecialchars($addon['name']) ?> 
                                    (₱<?= number_format($addon['regular_rate'], 2) ?>)
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        $('body').append(modalHTML);
        const addonsModal = new bootstrap.Modal('#addonsModal');
        addonsModal.show();
        
        $('#addonsModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    });
});
</script>
<?php
else:

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rebook Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your original CSS styles here */
        :root {
            --primary-white: #ffffff;
            --secondary-green: #2e8b57;
            --accent-green: #4caf93;
            --light-green: #e8f5e9;
            --dark-text: #2a6049;
            --medium-gray: #e0e0e0;
            --light-gray: #f5f5f5;
            --oblong-green: #2e8b57;
            --oblong-hover: #247a4a;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', sans-serif;
            color: var(--dark-text);
            line-height: 1.6;
        }

        .main-content {
            padding: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .container {
            max-width: 1200px;
            padding: 20px;
        }

        h1, h2, h3, h4, h5 {
            color: var(--dark-text);
            font-weight: 600;
        }

        h1 {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 15px;
        }

        h1:after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background: var(--accent-green);
            margin: 15px auto 0;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            background-color: white;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            padding: 10px 15px;
        }

        .form-select.disabled {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }

        .time-period-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--medium-gray);
        }

        .time-period-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .time-period-tab.active {
            color: var(--secondary-green);
            border-bottom-color: var(--secondary-green);
        }

        .time-slots-wrapper {
            overflow-x: auto;
            padding-bottom: 10px;
            justify-content: center;
        }

        .time-slots-container {
            display: none;
            flex-wrap: nowrap;
            gap: 10px;
            min-width: max-content;
            justify-content: center;
        }

        .time-slots-container.active {
            display: flex;
        }

        .time-slot {
            padding: 8px 15px;
            border: 1px solid var(--medium-gray);
            border-radius: 20px;
            cursor: pointer;
            white-space: nowrap;
        }

        .time-slot.selected {
            background-color: var(--secondary-green);
            color: white;
        }

        .btn-oblong {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: var(--oblong-green);
            color: white;
            border: none;
        }

        .btn-oblong:hover {
            background-color: var(--oblong-hover);
            color: white;
            transform: translateY(-2px);
        }

        .current-booking-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary-green);
        }
        
        .current-booking-info h5 {
            color: var(--secondary-green);
            margin-bottom: 10px;
        }

        .addons-section {
            text-align: center;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <h1>Rebook Service</h1>
            
            <div class="card mt-4">
                <div class="card-body">
                    <div id="rebookContent">
                        <!-- AJAX content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Load rebook content via AJAX
        $.get('rebook.php', {
            booking_id: <?= $booking_id ?>,
            branch_id: <?= $branch_id ?>,
            ajax: true
        }, function(data) {
            $('#rebookContent').html(data);
            initializeTimeSlots();
        });

        function initializeTimeSlots() {
            const morningSlots = ['11:00 AM', '11:30 AM'];
            const afternoonSlots = ['12:00 PM', '12:30 PM', '1:00 PM', '1:30 PM', '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM', '4:00 PM', '4:30 PM'];
            const eveningSlots = ['5:00 PM', '5:30 PM', '6:00 PM', '6:30 PM', '7:00 PM', '7:30 PM', '8:00 PM', '8:30 PM'];
            
            const morningContainer = $('.time-slots-container[data-period="morning"]');
            const afternoonContainer = $('.time-slots-container[data-period="afternoon"]');
            const eveningContainer = $('.time-slots-container[data-period="evening"]');
            
            morningSlots.forEach(slot => {
                morningContainer.append(`<div class="time-slot">${slot}</div>`);
            });
            
            afternoonSlots.forEach(slot => {
                afternoonContainer.append(`<div class="time-slot">${slot}</div>`);
            });
            
            eveningSlots.forEach(slot => {
                eveningContainer.append(`<div class="time-slot">${slot}</div>`);
            });
        }

        // Handle confirm rebooking
        $(document).on('click', '#confirmRebookBtn', function() {
            const bookingId = $('#originalBookingId').val();
            const bookingDate = $('#bookingDate').val();
            const selectedTime = $('.time-slot.selected').text();
            const therapistId = $('#therapist').val();
            const numberOfClients = $('#numberOfClients').val() || 1;
            
            if (!bookingDate) {
                Swal.fire('Error', 'Please select a booking date', 'error');
                return;
            }
            
            if (!selectedTime) {
                Swal.fire('Error', 'Please select a time slot', 'error');
                return;
            }
            
            const addons = [];
            $('.addon-checkbox:checked').each(function() {
                addons.push({
                    id: $(this).val(),
                    regular_rate: $(this).data('regular-rate'),
                    vip_rate: $(this).data('vip-rate'),
                    duration: $(this).data('duration')
                });
            });
            
            Swal.fire({
                title: 'Processing',
                html: 'Confirming your rebooking...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    $.post('confirm_rebooking.php', { 
                        originalBookingId: bookingId,
                        serviceId: $('#serviceId').val(),
                        therapistId: therapistId,
                        bookingDate: bookingDate,
                        time: selectedTime,
                        branchId: $('#branchId').val(),
                        regularRate: $('#serviceRegularRate').val(),
                        vipRate: $('#serviceVipRate').val(),
                        duration: $('#serviceDuration').val(),
                        addons: addons,
                        numberOfClients: numberOfClients
                    }, 'json')
                    .done(function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                title: 'Success!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = 'bookings.php';
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    })
                    .fail(function() {
                        Swal.fire('Error', 'Failed to process rebooking', 'error');
                    });
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
endif;
?>