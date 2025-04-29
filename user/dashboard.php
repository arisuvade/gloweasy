<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../includes/auth/login.php");
    exit();
}

// Fetch upcoming appointment
$stmt = $conn->prepare("
    SELECT b.id, s.name AS service_name, br.name AS branch_name, 
           b.booking_date, b.booking_time, b.status, b.receipt_number
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN branches br ON b.branch_id = br.id
    WHERE b.user_id = ? AND b.booking_date >= CURDATE() AND b.status = 'Pending'
    ORDER BY b.booking_date ASC, b.booking_time ASC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$upcoming = $result->fetch_assoc();
$stmt->close();

// Fetch booking stats
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings 
    WHERE user_id = ?
");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Fetch most booked service
$service_stmt = $conn->prepare("
    SELECT s.name, COUNT(*) as count
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.user_id = ?
    GROUP BY b.service_id
    ORDER BY count DESC
    LIMIT 1
");
$service_stmt->bind_param("i", $user_id);
$service_stmt->execute();
$service_result = $service_stmt->get_result();
$top_service = $service_result->fetch_assoc();
$service_stmt->close();

// Fetch favorite branch
$branch_stmt = $conn->prepare("
    SELECT br.name, COUNT(*) as count
    FROM bookings b
    JOIN branches br ON b.branch_id = br.id
    WHERE b.user_id = ?
    GROUP BY b.branch_id
    ORDER BY count DESC
    LIMIT 1
");
$branch_stmt->bind_param("i", $user_id);
$branch_stmt->execute();
$branch_result = $branch_stmt->get_result();
$top_branch = $branch_result->fetch_assoc();
$branch_stmt->close();

// Fetch recent bookings
$recent_stmt = $conn->prepare("
    SELECT b.id, s.name AS service_name, br.name AS branch_name,
           b.booking_date, b.booking_time, b.status, b.receipt_number
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN branches br ON b.branch_id = br.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC, b.booking_time DESC
    LIMIT 3
");
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
$recent_bookings = $recent_result->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

// Fetch available membership cards (just the 2 cards as advertisements)
$membership_stmt = $conn->prepare("SELECT card_type, price, description FROM membership_cards LIMIT 2");
$membership_stmt->execute();
$membership_result = $membership_stmt->get_result();
$membership_cards = $membership_result->fetch_all(MYSQLI_ASSOC);
$membership_stmt->close();

// Formatting functions
function formatTime($time) {
    return date("h:i A", strtotime($time));
}

function formatDate($date) {
    return date("M j, Y", strtotime($date));
}

function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

function getStatusBadge($status) {
    $classes = [
        'Pending' => 'status-Pending',
        'Upcoming' => 'status-Upcoming',
        'Active' => 'status-Active',
        'Completed' => 'status-Completed',
        'Cancelled' => 'status-Cancelled'
    ];
    return '<span class="status-badge ' . ($classes[$status] ?? 'status-Pending') . '">' . $status . '</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bali Ayurveda Spa</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
            padding: 20px;
            margin-left: 250px; /* Adjust based on sidebar width */
        }

        .welcome-card {
            background-color: var(--primary-white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
        }

        .welcome-title {
            color: var(--dark-text);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            color: var(--accent-green);
            margin-bottom: 20px;
        }

        .stats-card {
            background-color: var(--primary-white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            height: 100%;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            font-size: 2rem;
            color: var(--accent-green);
            margin-bottom: 15px;
        }

        .stats-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .stats-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-text);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            background-color: white;
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: var(--light-green);
            border-bottom: 1px solid var(--medium-gray);
            padding: 15px 20px;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-title {
            color: var(--dark-text);
            font-weight: 600;
            margin: 0;
        }

        .appointment-card {
            border-left: 4px solid var(--accent-green);
            padding: 15px;
            margin-bottom: 15px;
            background-color: var(--light-gray);
            border-radius: 8px;
        }

        .appointment-service {
            font-weight: 600;
            color: var(--dark-text);
        }

        .appointment-meta {
            color: #666;
            font-size: 14px;
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

        .btn-oblong-outline {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: white;
            color: var(--dark-text);
            border: 1px solid var(--medium-gray);
        }

        .btn-oblong-outline:hover {
            background-color: var(--light-green);
            transform: translateY(-2px);
        }

        .membership-card {
            background: linear-gradient(135deg, var(--secondary-green), var(--accent-green));
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .membership-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .membership-price {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .membership-desc {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .table th {
            background-color: var(--light-green);
            border-bottom: 2px solid var(--accent-green);
        }

        /* Updated status badges to match bookings.php */
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .stats-grid {
                margin-bottom: 15px;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        .spa-hours {
            background-color: var(--light-green);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .spa-hours-title {
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 10px;
        }
        
        .spa-hours-time {
            font-weight: 500;
            color: var(--secondary-green);
        }
        
        .card-notice {
            background-color: #FFF3CD;
            border-left: 4px solid #FFC107;
            padding: 15px;
            margin-top: 15px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
         <!-- Welcome Section -->
         <div class="welcome-card">
            <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
            <p class="welcome-subtitle">Here's what's happening with your spa journey</p>
            
            <div class="row">
                <div class="col-md-12">
                    <p><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
            
            <!-- Add Spa Hours Information -->
            <div class="spa-hours">
                <h5 class="spa-hours-title"><i class="bi bi-clock"></i> Bali Ayurveda Spa Operating Hours</h5>
                <p class="spa-hours-time">Open daily from 11:00 AM to 10:00 PM</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="row stats-grid mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stats-title">Total Bookings</div>
                    <div class="stats-value"><?= $stats['total_bookings'] ?? 0 ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-title">Completed</div>
                    <div class="stats-value"><?= $stats['completed'] ?? 0 ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stats-title">Cancelled</div>
                    <div class="stats-value"><?= $stats['cancelled'] ?? 0 ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div class="stats-title">Favorite Branch</div>
                    <div class="stats-value"><?= $top_branch ? htmlspecialchars($top_branch['name']) : 'N/A' ?></div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <a href="book.php" class="btn btn-oblong w-100">
                    <i class="bi bi-plus-circle"></i> Book New Appointment
                </a>
            </div>
            <div class="col-md-6 mb-3">
                <a href="bookings.php" class="btn btn-oblong-outline w-100">
                    <i class="bi bi-list-ul"></i> View All Bookings
                </a>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Upcoming Appointment -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-alarm"></i> Upcoming Appointment</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming): ?>
                            <div class="appointment-card">
                                <h5 class="appointment-service"><?= htmlspecialchars($upcoming['service_name']) ?></h5>
                                <p class="appointment-meta">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($upcoming['branch_name']) ?><br>
                                    <i class="bi bi-calendar"></i> <?= formatDate($upcoming['booking_date']) ?><br>
                                    <i class="bi bi-clock"></i> <?= formatTime($upcoming['booking_time']) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <?= getStatusBadge($upcoming['status']) ?>
                                    <a href="bookings.php" class="btn btn-sm btn-oblong">View Details</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x" style="font-size: 2rem; color: var(--medium-gray);"></i>
                                <p class="mt-2">No upcoming appointments</p>
                                <a href="book.php" class="btn btn-oblong">Book Now</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-clock-history"></i> Recent Bookings</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_bookings) > 0): ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <div class="appointment-card mb-3">
                                    <h6 class="appointment-service"><?= htmlspecialchars($booking['service_name']) ?></h6>
                                    <p class="appointment-meta small">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($booking['branch_name']) ?><br>
                                        <?= formatDate($booking['booking_date']) ?> at <?= formatTime($booking['booking_time']) ?>
                                    </p>
                                    <?= getStatusBadge($booking['status']) ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-journal-x" style="font-size: 2rem; color: var(--medium-gray);"></i>
                                <p class="mt-2">No recent bookings</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Membership Cards Advertisement -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-credit-card"></i> Membership Cards</h3>
                </div>
                <div class="card-body">
                    <?php if (count($membership_cards) > 0): ?>
                        <?php foreach ($membership_cards as $card): ?>
                            <div class="membership-card">
                                <h5 class="membership-title"><?= htmlspecialchars($card['card_type']) ?></h5>
                                <div class="membership-price"><?= formatPrice($card['price']) ?></div>
                                <p class="membership-desc"><?= htmlspecialchars($card['description']) ?></p>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Add membership card reminder -->
                        <div class="card-notice mt-3">
                            <p><strong>Important Reminder:</strong></p>
                            <p>If you're a member, please bring your membership card to the spa to avail your discounts. No card means no discount will be applied to your service.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-credit-card-2-back" style="font-size: 2rem; color: var(--medium-gray);"></i>
                            <p class="mt-2">No membership cards available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                
                <!-- Favorite Service -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-star"></i> Favorite Service</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($top_service): ?>
                            <div class="text-center py-3">
                                <i class="bi bi-heart" style="font-size: 2rem; color: var(--accent-green);"></i>
                                <h5 class="mt-2"><?= htmlspecialchars($top_service['name']) ?></h5>
                                <p class="text-muted">Your most booked service</p>
                                <a href="book.php?service=<?= urlencode($top_service['name']) ?>" class="btn btn-oblong">Book Again</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-heart" style="font-size: 2rem; color: var(--medium-gray);"></i>
                                <p class="mt-2">No favorite service yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../includes/footer.php'; ?>