<?php
session_start();
require '../includes/db.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Fetch admin details to get their branch
$stmt = $conn->prepare("SELECT branch FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: ../includes/auth/login.php");
    exit();
}

$branch = $admin['branch'];
$is_superadmin = ($branch === 'Owner');

// Initialize variables
$params = [];
$types = "";

// Fetch filter values from the request
$filterDate = $_GET['filterDate'] ?? null;
$filterStatus = $_GET['filterStatus'] ?? 'Active,Pending'; // Default to Active and Pending
$searchTerm = $_GET['search'] ?? '';

// Base query
$query = "
    SELECT b.id, b.receipt_number, u.name AS user_name, u.email AS user_email, 
           b.booking_date, b.booking_time, b.status, br.name AS branch_name,
           b.has_membership_card
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN branches br ON b.branch_id = br.id
    WHERE 1=1
";

// Add branch filter if not superadmin
if (!$is_superadmin) {
    $query .= " AND br.name = ?";
    $params[] = $branch;
    $types .= "s";
}

// Add other filters
if ($filterDate) {
    $query .= " AND b.booking_date = ?";
    $params[] = $filterDate;
    $types .= "s";
}
if ($filterStatus !== 'all') {
    if ($filterStatus === 'Active,Pending') {
        $query .= " AND (b.status = 'Active' OR b.status = 'Pending')";
    } else {
        $query .= " AND b.status = ?";
        $params[] = $filterStatus;
        $types .= "s";
    }
}
if (!empty($searchTerm)) {
    $query .= " AND (b.receipt_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

// Add sorting - upcoming first, completed/cancelled last
$query .= " ORDER BY 
    CASE 
        WHEN b.status = 'Active' THEN 1 
        WHEN b.status = 'Pending' THEN 2
        WHEN b.status = 'Completed' THEN 3
        ELSE 4
    END,
    CASE
        WHEN b.status IN ('Active', 'Pending') THEN b.booking_date
        ELSE NULL
    END ASC,
    CASE
        WHEN b.status IN ('Active', 'Pending') THEN b.booking_time
        ELSE NULL
    END ASC,
    CASE
        WHEN b.status IN ('Completed', 'Cancelled') THEN b.booking_date
        ELSE NULL
    END DESC,
    CASE
        WHEN b.status IN ('Completed', 'Cancelled') THEN b.booking_time
        ELSE NULL
    END DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle status update via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $booking_id = $_POST['booking_id'];
        $new_status = $_POST['status'];
        $has_membership_card = isset($_POST['has_membership_card']) ? 1 : 0;
        $membership_code = isset($_POST['membership_code']) ? trim($_POST['membership_code']) : null;
        
        // Verify the booking belongs to admin's branch before updating
        $verify_stmt = $conn->prepare("
            SELECT b.id 
            FROM bookings b
            JOIN branches br ON b.branch_id = br.id
            WHERE b.id = ? AND (br.name = ? OR ? = 'Owner')
        ");
        $verify_stmt->bind_param("iss", $booking_id, $branch, $branch);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE bookings SET status = ?, has_membership_card = ?, membership_code = ? WHERE id = ?");
            $stmt->bind_param("sisi", $new_status, $has_membership_card, $membership_code, $booking_id);
            $stmt->execute();
            $stmt->close();
            
            // Set success message in session
            $_SESSION['success_message'] = "Booking status updated successfully!";
        }
        $verify_stmt->close();
        
        // Redirect to refresh the page
        header("Location: manage_bookings.php");
        exit();
    }
    
    if (isset($_POST['decline_booking'])) {
        $booking_id = $_POST['booking_id'];
        $decline_reason = $_POST['decline_reason'] ?? null;
        
        // Verify the booking belongs to admin's branch before updating
        $verify_stmt = $conn->prepare("
            SELECT b.id 
            FROM bookings b
            JOIN branches br ON b.branch_id = br.id
            WHERE b.id = ? AND (br.name = ? OR ? = 'Owner')
        ");
        $verify_stmt->bind_param("iss", $booking_id, $branch, $branch);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled', notes = ? WHERE id = ?");
            $stmt->bind_param("si", $decline_reason, $booking_id);
            $stmt->execute();
            $stmt->close();
            
            // Set success message in session
            $_SESSION['success_message'] = "Booking declined successfully!";
        }
        $verify_stmt->close();
        
        // Redirect to refresh the page
        header("Location: manage_bookings.php");
        exit();
    }
}

$conn->close();

// Formatting functions
function formatTime($time) {
    return date("h:i A", strtotime($time));
}

function formatDate($date) {
    return date("M j, Y", strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
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
            --sidebar-width: 250px;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', sans-serif;
            color: var(--dark-text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .main-content {
            margin-top: 10px;
            padding: 30px;
        }

        .content-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            flex: 1;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            .content-container {
                max-width: 100%;
            }
        }

        h1, h2, h3, h4, h5 {
            color: var(--dark-text);
            font-weight: 600;
        }

        h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 15px;
            margin-top: 0;
        }

        h1:after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background: var(--accent-green);
            margin: 15px auto 0;
        }

        .table-responsive { 
            margin-top: 20px;
            background-color: var(--primary-white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
        }

        /* Status badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        
        .status-Pending { 
            background-color: #FFF3CD; 
            color: #856404; 
            border: 1px solid #FFEEBA;
        }
        
        .status-Active { 
            background-color: #D4EDDA; 
            color: #155724; 
            border: 1px solid #C3E6CB;
        }
        
        .status-Completed { 
            background-color: #D1ECF1; 
            color: #0C5460; 
            border: 1px solid #BEE5EB;
        }
        
        .status-Cancelled { 
            background-color: #F8D7DA; 
            color: #721C24; 
            border: 1px solid #F5C6CB;
        }

        /* Button styles */
        .action-btn {
            margin-right: 5px;
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 20px;
            transition: all 0.3s ease;
            border: none;
        }

        .view-btn {
            background-color: var(--accent-green);
            color: white;
        }

        .view-btn:hover {
            background-color: #3d9e80;
            color: white;
            transform: translateY(-2px);
        }

        .accept-btn {
            background-color: var(--secondary-green);
            color: white;
        }

        .accept-btn:hover {
            background-color: #247a4a;
            color: white;
            transform: translateY(-2px);
        }

        .decline-btn {
            background-color: #DC3545;
            color: white;
        }

        .decline-btn:hover {
            background-color: #C82333;
            color: white;
            transform: translateY(-2px);
        }

        /* Filter section */
        .filter-section {
            background-color: var(--light-green);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-control, .form-select {
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            padding: 8px 12px;
        }

        .btn-oblong {
            padding: 8px 20px;
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

        .btn-secondary {
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: #6c757d;
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
        }

        .modal-header {
            background-color: var(--light-green);
            border-bottom: 1px solid var(--medium-gray);
        }

        .modal-title {
            color: var(--dark-text);
        }

        .membership-options {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }

        .membership-option {
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid var(--medium-gray);
            transition: all 0.3s ease;
        }

        .membership-option.selected {
            border-color: var(--secondary-green);
            background-color: var(--light-green);
        }

        .membership-option:hover {
            border-color: var(--accent-green);
        }
        
        /* Wider table styling */
        .table {
            width: 100%;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                margin: 2px 0;
            }
            
            .table-responsive {
                padding: 10px;
            }
            
            .table th, .table td {
                padding: 8px;
                font-size: 14px;
            }
        }

        /* Search box */
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box .bi {
            position: absolute;
            left: 15px;
            color: #6c757d;
        }

        .search-box input {
            padding-left: 40px;
            padding-right: 15px;
            height: 40px;
            border-radius: 30px;
            border: 1px solid #ced4da;
            width: 100%;
        }

        .search-box input:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 0 0.25rem rgba(76, 175, 80, 0.25);
        }

        /* Membership code container */
        #membershipCodeContainer {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--medium-gray);
        }

        #membershipCodeContainer label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }

        /* Validation styles */
        .is-invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
            display: none;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>  

    <!-- Main Content -->
    <div class="main-content">
        <h1>Manage Bookings</h1>

        <!-- Filter Section -->
        <div class="filter-section">
            <form id="filterForm" method="GET" class="row g-3">
                <!-- Combined Search -->
                <div class="col-md-5">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="Search Receipt, Name, Email" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="date" id="filterDate" name="filterDate" class="form-control" 
                           value="<?= htmlspecialchars($filterDate ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <select id="filterStatus" name="filterStatus" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="Active,Pending" <?= ($filterStatus === 'Active,Pending') ? 'selected' : '' ?>>Upcoming</option>
                        <option value="Pending" <?= ($filterStatus === 'Pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="Active" <?= ($filterStatus === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Completed" <?= ($filterStatus === 'Completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= ($filterStatus === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" id="resetFilters" class="btn btn-secondary w-100">Reset</button>
                </div>
            </form>
        </div>

        <!-- Bookings Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Customer Name</th>
                        <th>Customer Email</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?= htmlspecialchars($booking['receipt_number']) ?></td>
                        <td><?= htmlspecialchars($booking['user_name']) ?></td>
                        <td><?= htmlspecialchars($booking['user_email']) ?></td>
                        <td><?= formatDate($booking['booking_date']) ?></td>
                        <td><?= formatTime($booking['booking_time']) ?></td>
                        <td>
                            <span class="status-badge status-<?= $booking['status'] ?>">
                                <?= $booking['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($booking['status'] === 'Active'): ?>
                                    <!-- Active status actions -->
                                    <button class="action-btn accept-btn complete-btn" 
                                            data-booking-id="<?= $booking['id'] ?>"
                                            data-has-card="<?= $booking['has_membership_card'] ?>">
                                        <i class="bi bi-check-circle"></i> Complete
                                    </button>
                                    <button class="action-btn view-btn view-btn" 
                                            data-booking-id="<?= $booking['id'] ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    
                                <?php elseif ($booking['status'] === 'Pending'): ?>
                                    <!-- Pending status actions -->
                                    <button class="action-btn decline-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#declineModal"
                                            data-booking-id="<?= $booking['id'] ?>">
                                        <i class="bi bi-x-circle"></i> Decline
                                    </button>
                                    <button class="action-btn view-btn view-btn" 
                                            data-booking-id="<?= $booking['id'] ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    
                                <?php else: ?>
                                    <!-- Completed/Cancelled status actions -->
                                    <button class="action-btn view-btn view-btn" 
                                            data-booking-id="<?= $booking['id'] ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Booking Modal -->
    <div class="modal fade" id="viewBookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bookingDetails">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Decline Booking Modal -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Decline Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_bookings.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="booking_id" id="declineBookingId">
                        <div class="mb-3">
                            <label for="declineReason" class="form-label">Reason for declining (optional):</label>
                            <textarea class="form-control" id="declineReason" name="decline_reason" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="decline_booking" class="btn btn-oblong">Confirm Decline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Complete Booking Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="completeBookingForm" action="manage_bookings.php" method="POST">
                    <input type="hidden" name="booking_id" id="completeBookingId">
                    <input type="hidden" name="status" value="Completed">
                    <div class="modal-body">
                        <div class="mb-3">
                            <p>Did the customer use a VIP/Elite membership card?</p>
                            <div class="membership-options">
                                <div class="membership-option selected" data-value="0">
                                    <i class="bi bi-cash"></i> Regular
                                </div>
                                <div class="membership-option" data-value="1">
                                    <i class="bi bi-credit-card"></i> With Card
                                </div>
                            </div>
                            <input type="hidden" name="has_membership_card" id="membershipCardValue" value="0">
                        </div>
                        <div class="mb-3" id="membershipCodeContainer">
                            <label for="membershipCode" class="form-label">Enter the customer membership code:</label>
                            <input type="text" class="form-control" id="membershipCode" name="membership_code" placeholder="Membership code">
                            <div class="invalid-feedback" id="membershipCodeError">
                                Please enter a membership code when using a card.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-oblong">Complete Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Initialize Bootstrap modals
        const viewModal = new bootstrap.Modal(document.getElementById('viewBookingModal'));
        const declineModal = new bootstrap.Modal(document.getElementById('declineModal'));
        const completeModal = new bootstrap.Modal(document.getElementById('completeModal'));

        // Show success message if exists
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?= $_SESSION['success_message'] ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        // View booking details
        $('.view-btn').click(function() {
            const bookingId = $(this).data('booking-id');
            
            $.ajax({
                url: '../includes/view_booking.php',
                type: 'GET',
                data: { id: bookingId },
                success: function(response) {
                    $('#bookingDetails').html(response);
                    viewModal.show();
                },
                error: function() {
                    alert('Error loading booking details');
                }
            });
        });

        // Set booking ID in decline modal
        $('#declineModal').on('show.bs.modal', function(event) {
            const button = $(event.relatedTarget);
            const bookingId = button.data('booking-id');
            $('#declineBookingId').val(bookingId);
        });

        // Set booking ID in complete modal and membership card status
        $('.complete-btn').click(function() {
            const bookingId = $(this).data('booking-id');
            const hasCard = $(this).data('has-card');
            $('#completeBookingId').val(bookingId);
            
            // Set initial selection based on existing value
            $('.membership-option').removeClass('selected');
            if (hasCard == 1) {
                $('.membership-option[data-value="1"]').addClass('selected');
                $('#membershipCardValue').val(1);
                $('#membershipCodeContainer').show();
            } else {
                $('.membership-option[data-value="0"]').addClass('selected');
                $('#membershipCardValue').val(0);
                $('#membershipCodeContainer').hide();
            }
            
            completeModal.show();
        });

        // Handle membership option selection
        $('.membership-option').click(function() {
            $('.membership-option').removeClass('selected');
            $(this).addClass('selected');
            const selectedValue = $(this).data('value');
            $('#membershipCardValue').val(selectedValue);
            
            // Show/hide membership code field based on selection
            if (selectedValue == 1) {
                $('#membershipCodeContainer').show();
            } else {
                $('#membershipCodeContainer').hide();
                $('#membershipCode').removeClass('is-invalid');
                $('#membershipCodeError').hide();
            }
        });

        // Handle form submission with validation
        $('#completeBookingForm').on('submit', function(e) {
            const hasCard = $('#membershipCardValue').val() == 1;
            const membershipCode = $('#membershipCode').val().trim();
            
            if (hasCard && !membershipCode) {
                e.preventDefault();
                $('#membershipCode').addClass('is-invalid');
                $('#membershipCodeError').show();
                
                // Scroll to the error if needed
                $('html, body').animate({
                    scrollTop: $('#membershipCodeContainer').offset().top - 100
                }, 500);
                
                return false;
            }
            
            return true;
        });

        // Clear validation when typing in the membership code
        $('#membershipCode').on('input', function() {
            if ($(this).val().trim()) {
                $(this).removeClass('is-invalid');
                $('#membershipCodeError').hide();
            }
        });

        $('#completeModal').on('show.bs.modal', function () {
        $('.membership-option').removeClass('selected');
        $('.membership-option[data-value="0"]').addClass('selected');
        $('#membershipCardValue').val(0);
        $('#membershipCodeContainer').hide();
        $('#membershipCode').removeClass('is-invalid').val('');
        $('#membershipCodeError').hide();
    });

        // Reset filters
        $('#resetFilters').click(function() {
            window.location.href = 'manage_bookings.php';
        });

        // Auto-submit form when filters change
        $('#filterForm select, #filterForm input[type="date"]').on('change', function() {
            $('#filterForm').submit();
        });

        // Submit form when Enter is pressed in search field
        $('#search').keypress(function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $('#filterForm').submit();
            }
        });
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>