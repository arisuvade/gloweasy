<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get status values from database
$status_query = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'bookings' AND COLUMN_NAME = 'status'";
$status_result = $conn->query($status_query);
$status_row = $status_result->fetch_assoc();
preg_match("/^enum\(\'(.*)\'\)$/", $status_row['COLUMN_TYPE'], $matches);
$status_values = explode("','", $matches[1]);

// Define fixed status display order with new 'Upcoming' status
$fixed_status_order = ['Pending', 'Upcoming', 'Active', 'Completed', 'Cancelled'];

// Get filter values - default to showing both Active and Upcoming
$filterDate = $_GET['filterDate'] ?? null;
$filterTime = $_GET['filterTime'] ?? 'all';
$filterStatus = $_GET['filterStatus'] ?? 'Active,Pending'; // Default to showing both
$searchTerm = $_GET['search'] ?? '';
$sortColumn = $_GET['sort'] ?? 'booking_date';
$sortOrder = $_GET['order'] ?? 'asc';

// Calculate which bookings should hide reschedule button (4 hours before)
$hideRescheduleIds = [];
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$cutoff = (clone $now)->modify('+4 hours');

$stmt = $conn->prepare("
    SELECT id FROM bookings 
    WHERE user_id = ?
    AND status = 'Pending'
    AND booking_date = ?
    AND booking_time <= ?
");
$booking_date = $cutoff->format('Y-m-d');
$booking_time = $cutoff->format('H:i:00');
$stmt->bind_param("iss", $user_id, $booking_date, $booking_time);

$stmt->execute();
$result = $stmt->get_result();
$hideRescheduleIds = array_column($result->fetch_all(MYSQLI_ASSOC), 'id');
$stmt->close();

// Main bookings query
$query = "SELECT b.id, b.receipt_number, b.booking_date, b.booking_time, b.status, 
                 s.name AS service_name, br.name AS branch_name, b.branch_id,
                 b.allow_reschedule,
                 TIME_FORMAT(b.booking_time, '%h:%i %p') as formatted_time
          FROM bookings b 
          JOIN services s ON b.service_id = s.id
          JOIN branches br ON b.branch_id = br.id
          WHERE b.user_id = ?";

// Add filters
if ($filterDate) $query .= " AND b.booking_date = ?";
if ($filterTime !== 'all') $query .= " AND b.booking_time = ?";
if ($filterStatus !== 'all') {
    $statuses = explode(',', $filterStatus);
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $query .= " AND b.status IN ($placeholders)";
}
if (!empty($searchTerm)) $query .= " AND (b.receipt_number LIKE ? OR br.name LIKE ? OR s.name LIKE ?)";

// Add sorting
$query .= ($sortColumn === 'receipt_number') 
    ? " ORDER BY b.receipt_number $sortOrder" 
    : " ORDER BY b.booking_date $sortOrder, b.booking_time $sortOrder";

// Execute query
$stmt = $conn->prepare($query);
$params = [$user_id];
$types = "i";

if ($filterDate) {
    $params[] = $filterDate;
    $types .= "s";
}
if ($filterTime !== 'all') {
    $params[] = date("H:i:s", strtotime($filterTime));
    $types .= "s";
}
if ($filterStatus !== 'all') {
    $statuses = explode(',', $filterStatus);
    foreach ($statuses as $status) {
        $params[] = $status;
        $types .= "s";
    }
}
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Format functions
function formatDate($date) { return date("M j, Y", strtotime($date)); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', sans-serif;
            color: var(--dark-text);
            line-height: 1.6;
        }

        .main-content {
            margin-top: 10px;
            padding: 30px;
            margin-left: -250px;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
        }

        h1, h2, h3, h4, h5 {
            color: var(--dark-text);
            font-weight: 600;
        }

        h1 {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
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

        /* Updated status badges with new 'Upcoming' status */
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
        
        .status-Upcoming {
            background-color: #CCE5FF;
            color: #004085;
            border: 1px solid #B8DAFF;
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

        .btn-view {
            background-color: var(--accent-green);
            color: white;
        }

        .btn-view:hover {
            background-color: #3d9e80;
            color: white;
            transform: translateY(-2px);
        }

        .btn-reschedule {
            background-color: #FFC107;
            color: #212529;
        }

        .btn-reschedule:hover {
            background-color: #E0A800;
            color: #212529;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background-color: #DC3545;
            color: white;
        }

        .btn-cancel:hover {
            background-color: #C82333;
            color: white;
            transform: translateY(-2px);
        }

        .btn-rebook {
            background-color: var(--secondary-green);
            color: white;
        }

        .btn-rebook:hover {
            background-color: var(--oblong-hover);
            color: white;
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
        }

        /* Filter section */
        .filter-section {
            background-color: var(--light-green);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Wider table styling */
        .table {
            width: 100%;
        }
        
        .table th, .table td {
            padding: 12px 15px;
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <h1 class="mb-4">My Bookings</h1>
        
        <!-- Filter/Search Form -->
        <form id="filterForm" method="GET" class="mb-4 filter-section">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search Receipt, Branch, Service" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="date" name="filterDate" class="form-control" value="<?= htmlspecialchars($filterDate ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <select name="filterStatus" class="form-control">
                        <option value="all">All Statuses</option>
                        <option value="Active,Pending" <?= $filterStatus === 'Active,Pending' ? 'selected' : '' ?>>Upcoming</option>
                        <?php foreach ($fixed_status_order as $status): ?>
                            <?php if (in_array($status, $status_values)): ?>
                                <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>>
                                    <?= ucfirst($status) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" id="resetFilters" class="btn btn-secondary w-100">Reset</button>
                </div>
            </div>
        </form>

        <!-- Bookings Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Branch</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['receipt_number']) ?></td>
                            <td><?= htmlspecialchars($booking['branch_name']) ?></td>
                            <td><?= htmlspecialchars($booking['service_name']) ?></td>
                            <td><?= formatDate($booking['booking_date']) ?></td>
                            <td><?= htmlspecialchars($booking['formatted_time']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $booking['status'] ?>">
                                    <?= ucfirst($booking['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn btn-view view-btn" data-booking-id="<?= $booking['id'] ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php if ($booking['status'] === 'Pending' || $booking['status'] === 'Upcoming'): ?>
                                        <?php if ($booking['allow_reschedule'] && !in_array($booking['id'], $hideRescheduleIds)): ?>
                                            <button class="action-btn btn-reschedule reschedule-btn" 
                                                    data-booking-id="<?= $booking['id'] ?>"
                                                    data-branch-id="<?= $booking['branch_id'] ?>">
                                                <i class="bi bi-calendar-event"></i> Reschedule
                                            </button>
                                        <?php endif; ?>
                                        <button class="action-btn btn-cancel cancel-btn" data-booking-id="<?= $booking['id'] ?>">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    <?php elseif (in_array($booking['status'], ['Completed', 'Cancelled'])): ?>
                                        <!-- <button class="action-btn btn-rebook rebook-btn" 
                                            data-booking-id="<?= $booking['id'] ?>"
                                            data-branch-id="<?= $booking['branch_id'] ?>">
                                            <i class="bi bi-arrow-repeat"></i> Rebook
                                        </button> -->
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bookingDetails">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading booking details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Booking Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reschedule Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- This will be loaded from reschedule.php -->
                    <div id="rescheduleContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading rescheduling options...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <!-- Rebook Booking Modal -->
    <div class="modal fade" id="rebookModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rebook Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <iframe id="rebookIframe" style="width:100%; height:500px; border:none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        const bookingModal = new bootstrap.Modal('#bookingModal');
        const rescheduleModal = new bootstrap.Modal('#rescheduleModal');
        const rebookModal = new bootstrap.Modal('#rebookModal');
        
        // View booking details
        $(document).on('click', '.view-btn', function() {
            const bookingId = $(this).data('booking-id');
            $('#bookingDetails').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading booking details...</p>
                </div>
            `);
            
            $.get('../includes/view_booking.php', { id: bookingId })
                .done(function(data) {
                    $('#bookingDetails').html(data);
                })
                .fail(function() {
                    $('#bookingDetails').html('<div class="alert alert-danger">Failed to load details</div>');
                });
            
            bookingModal.show();
        });
        
        // Reschedule booking - show modal
        $(document).on('click', '.reschedule-btn', function() {
            const bookingId = $(this).data('booking-id');
            $('#rescheduleContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading rescheduling options...</p>
                </div>
            `);
            
            // Load reschedule.php content into modal
            $.get('reschedule.php', { id: bookingId })
                .done(function(data) {
                    $('#rescheduleContent').html(data);
                })
                .fail(function() {
                    $('#rescheduleContent').html('<div class="alert alert-danger">Failed to load rescheduling options</div>');
                });
            
            rescheduleModal.show();
        });

        // Cancel booking
        $(document).on('click', '.cancel-btn', function() {
            const bookingId = $(this).data('booking-id');
            Swal.fire({
                title: 'Confirm Cancellation',
                text: "Are you sure you want to cancel this booking?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing',
                        html: 'Cancelling your booking...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    
                    $.post('cancel_booking.php', { bookingId: bookingId }, 'json')
                        .done(function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Cancelled!',
                                    text: response.message
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        })
                        .fail(function() {
                            Swal.fire('Error', 'Failed to cancel booking', 'error');
                        });
                }
            });
        });

        // Rebook booking - show modal
        $(document).on('click', '.rebook-btn', function() {
            const bookingId = $(this).data('booking-id');
            const branchId = $(this).data('branch-id');
            
            // Load rebook.php in the iframe
            $('#rebookIframe').attr('src', 'rebook.php?booking_id=' + bookingId + '&branch_id=' + branchId);
            
            rebookModal.show();
            
            // Handle when rebooking is successful
            window.rebookSuccess = function() {
                rebookModal.hide();
                location.reload();
            };
        });
        
        // Filter handling
        $('#filterForm select, #filterForm input').on('change', function() {
            $('#filterForm').submit();
        });
        
        $('#resetFilters').click(function() {
            window.location.href = 'bookings.php';
        });
        
        // Submit form when Enter is pressed in search field
        $('input[name="search"]').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $('#filterForm').submit();
            }
        });

        // Sidebar toggle functionality
        $('#sidebarToggle').click(function() {
            $('#sidebar').toggleClass('active');
        });
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>