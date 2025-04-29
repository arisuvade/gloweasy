<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized access']));
}

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['id'] ?? null;

if (!$booking_id || !is_numeric($booking_id)) {
    die('<div class="alert alert-danger">Invalid booking ID</div>');
}

// Fetch booking details with therapist info
$stmt = $conn->prepare("
    SELECT b.*, s.category AS service_category, 
           TIME_FORMAT(b.booking_time, '%h:%i %p') as formatted_time,
           t.id AS therapist_id, t.name AS therapist_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN booking_therapists bt ON b.id = bt.booking_id
    LEFT JOIN therapists t ON bt.therapist_id = t.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('<div class="alert alert-danger">Booking not found</div>');
}

$booking = $result->fetch_assoc();
$stmt->close();

// Check if booking can be rescheduled
if (!in_array($booking['status'], ['Pending', 'Active'])) {
    die('<div class="alert alert-danger">Only pending or active bookings can be rescheduled</div>');
}

// Fetch all therapists for this branch
$therapists = [];
$stmt = $conn->prepare("
    SELECT id, name FROM therapists 
    WHERE is_active = 1 AND branch_id = ?
    ORDER BY name ASC
");
$stmt->bind_param("i", $booking['branch_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $therapists[] = $row;
}
$stmt->close();
?>

<div class="container p-0">
    <input type="hidden" id="bookingId" value="<?= $booking_id ?>">
    <input type="hidden" id="branchId" value="<?= $booking['branch_id'] ?>">
    
    <?php if ($booking['service_category'] !== 'Body Healing'): ?>
    <div class="mb-3" style="margin-bottom: 0.5rem !important;">
        <label class="form-label">Therapist</label>
        <select id="therapist" class="form-control">
            <option value="0">Any Available Therapist</option>
            <?php foreach ($therapists as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($t['id'] == $booking['therapist_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">New Date</label>
        <input type="date" id="bookingDate" class="form-control" value="<?= htmlspecialchars($booking['booking_date']) ?>" min="<?= date('Y-m-d') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Select Time</label>
        <div class="time-period-tabs mb-2">
            <div class="time-period-tab active" data-period="morning">Morning</div>
            <div class="time-period-tab" data-period="afternoon">Afternoon</div>
            <div class="time-period-tab" data-period="evening">Evening</div>
        </div>
        <div class="time-slots-wrapper">
            <div class="time-slots-container active" data-period="morning">
                <div class="time-slot" data-time="11:00 AM">11:00 AM</div>
                <div class="time-slot" data-time="11:30 AM">11:30 AM</div>
            </div>
            <div class="time-slots-container" data-period="afternoon">
                <div class="time-slot" data-time="12:00 PM">12:00 PM</div>
                <div class="time-slot" data-time="12:30 PM">12:30 PM</div>
                <div class="time-slot" data-time="1:00 PM">1:00 PM</div>
                <div class="time-slot" data-time="1:30 PM">1:30 PM</div>
                <div class="time-slot" data-time="2:00 PM">2:00 PM</div>
                <div class="time-slot" data-time="2:30 PM">2:30 PM</div>
                <div class="time-slot" data-time="3:00 PM">3:00 PM</div>
                <div class="time-slot" data-time="3:30 PM">3:30 PM</div>
                <div class="time-slot" data-time="4:00 PM">4:00 PM</div>
                <div class="time-slot" data-time="4:30 PM">4:30 PM</div>
            </div>
            <div class="time-slots-container" data-period="evening">
                <div class="time-slot" data-time="5:00 PM">5:00 PM</div>
                <div class="time-slot" data-time="5:30 PM">5:30 PM</div>
                <div class="time-slot" data-time="6:00 PM">6:00 PM</div>
                <div class="time-slot" data-time="6:30 PM">6:30 PM</div>
                <div class="time-slot" data-time="7:00 PM">7:00 PM</div>
                <div class="time-slot" data-time="7:30 PM">7:30 PM</div>
                <div class="time-slot" data-time="8:00 PM">8:00 PM</div>
                <div class="time-slot" data-time="8:30 PM">8:30 PM</div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <button id="saveBtn" class="btn btn-primary">Reschedule Booking</button>
    </div>
</div>

<style>
.time-period-tabs {
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
    border-bottom: 1px solid #dee2e6;
}

.time-period-tab {
    padding: 10px 20px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.time-period-tab.active {
    color: #2e8b57;
    border-bottom-color: #2e8b57;
}

.time-slots-wrapper {
    overflow-x: auto;
    padding-bottom: 10px;
    justify-content: center;
}

.time-slots-container {
    display: none;
    flex-wrap: wrap;
    gap: 10px;
    min-width: max-content;
    justify-content: center;
}

.time-slots-container.active {
    display: flex;
}

.time-slot {
    padding: 8px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
}

.time-slot.selected {
    background-color: #2e8b57;
    color: white;
    border-color: #2e8b57;
}

.time-slot.booked {
    background-color: #F8D7DA;
    color: #721C24;
    border-color: #F5C6CB;
    cursor: not-allowed;
}

.time-slot:hover:not(.booked) {
    background-color: #e9ecef;
}

.container.p-0 {
    margin-top: 0 !important;
    padding-top: 0 !important;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script>
$(document).ready(function() {
    const serviceCategory = '<?= $booking['service_category'] ?>';
    const branchId = $('#branchId').val();
    const bookingId = $('#bookingId').val();
    const totalDuration = <?= $booking['total_duration'] ?? $booking['service_duration'] ?>;
    let selectedTime = '<?= $booking['formatted_time'] ?>';

    // Set initially selected time slot
    if (selectedTime) {
        $(`.time-slot:contains('${selectedTime}')`).addClass('selected');
    }

    // Time period tab click handler
    $(document).on('click', '.time-period-tab', function() {
        const period = $(this).data('period');
        $('.time-period-tab').removeClass('active');
        $(this).addClass('active');
        $('.time-slots-container').removeClass('active');
        $(`.time-slots-container[data-period="${period}"]`).addClass('active');
    });

    // Time slot selection with duration checking
    $(document).on('click', '.time-slot:not(.booked)', function() {
        const slotTime = $(this).text();
        const bookingDate = $('#bookingDate').val();

        if (!bookingDate) {
            Swal.fire('Error', 'Please select a date first', 'error');
            return;
        }

        const time24 = moment(slotTime, 'h:mm A').format('HH:mm:ss');
        const slotDateTime = new Date(`${bookingDate} ${time24}`);
        const endDateTime = new Date(slotDateTime.getTime() + (totalDuration * 60000) - 60000);
        const lastSlotTime = new Date(`${bookingDate} 22:00:00`);

        if (endDateTime > lastSlotTime) {
            Swal.fire({
                title: 'Not Enough Time',
                text: `The selected time plus service duration (${totalDuration} mins) goes beyond business hours (10:00 PM). Please choose an earlier time.`,
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        $('.time-slot').removeClass('selected');
        $(this).addClass('selected');
        selectedTime = slotTime;
    });

    // Save changes handler
    $('#saveBtn').click(function() {
        const date = $('#bookingDate').val();
        const therapistId = serviceCategory === 'Body Healing' ? 0 : $('#therapist').val();

        if (!date) {
            Swal.fire('Error', 'Please select a date', 'error');
            return;
        }

        if (!selectedTime) {
            Swal.fire('Error', 'Please select a time slot', 'error');
            return;
        }

        const time24 = moment(selectedTime, 'h:mm A').format('HH:mm:ss');
        const slotDateTime = new Date(`${date} ${time24}`);
        const endDateTime = new Date(slotDateTime.getTime() + (totalDuration * 60000) - 60000);
        const lastSlotTime = new Date(`${date} 22:00:00`);

        if (endDateTime > lastSlotTime) {
            Swal.fire({
                title: 'Not Enough Time',
                text: `The selected time plus service duration (${totalDuration} mins) goes beyond business hours. Please choose an earlier time.`,
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        $('#saveBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Processing...');

        $.post('reschedule_booking.php', {
            bookingId: bookingId,
            date: date,
            time: selectedTime,
            therapistId: therapistId
        })
        .done(function(response) {
            if (response.status === 'success') {
                Swal.fire('Success', response.message, 'success').then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', response.message, 'error');
                $('#saveBtn').prop('disabled', false).html('Reschedule Booking');
            }
        })
        .fail(function() {
            Swal.fire('Error', 'Failed to reschedule booking', 'error');
            $('#saveBtn').prop('disabled', false).html('Reschedule Booking');
        });
    });
});
</script>
