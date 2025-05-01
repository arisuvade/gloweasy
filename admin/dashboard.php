<?php
session_start();
require '../includes/db.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Fetch admin details
$stmt = $conn->prepare("SELECT name, branch FROM admins WHERE id = ?");
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

// Fetch total users (branch-specific if not superadmin)
if ($is_superadmin) {
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_users FROM users");
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT u.id) AS total_users 
        FROM users u
        JOIN bookings b ON u.id = b.user_id
        JOIN branches br ON b.branch_id = br.id
        WHERE br.name = ?
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$total_users = $result->fetch_assoc()['total_users'] ?? 0;
$stmt->close();

// Fetch total bookings (branch-specific if not superadmin)
if ($is_superadmin) {
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_bookings FROM bookings");
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(b.id) AS total_bookings 
        FROM bookings b
        JOIN branches br ON b.branch_id = br.id
        WHERE br.name = ?
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$total_bookings = $result->fetch_assoc()['total_bookings'] ?? 0;
$stmt->close();

// Fetch total income (branch-specific if not superadmin)
if ($is_superadmin) {
    $stmt = $conn->prepare("
        SELECT SUM(
            CASE 
                WHEN b.has_membership_card = 1 THEN b.vip_elite_amount 
                ELSE b.total_amount 
            END
        ) AS total_income
        FROM bookings b
        WHERE b.status = 'completed'
    ");
} else {
    $stmt = $conn->prepare("
        SELECT SUM(
            CASE 
                WHEN b.has_membership_card = 1 THEN b.vip_elite_amount 
                ELSE b.total_amount 
            END
        ) AS total_income
        FROM bookings b
        JOIN branches br ON b.branch_id = br.id
        WHERE b.status = 'completed' AND br.name = ?
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$total_income = $result->fetch_assoc()['total_income'] ?? 0;
$stmt->close();

// Fetch booking status counts (branch-specific if not superadmin)
$status_counts = [];
$statuses = ['Pending', 'Active', 'Completed', 'Cancelled'];

foreach ($statuses as $status) {
    if ($is_superadmin) {
        $stmt = $conn->prepare("SELECT COUNT(id) AS count FROM bookings WHERE status = ?");
        $stmt->bind_param("s", $status);
    } else {
        $stmt = $conn->prepare("
            SELECT COUNT(b.id) AS count 
            FROM bookings b
            JOIN branches br ON b.branch_id = br.id
            WHERE b.status = ? AND br.name = ?
        ");
        $stmt->bind_param("ss", $status, $branch);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $status_counts[$status] = $result->fetch_assoc()['count'];
    $stmt->close();
}

// Fetch today's income (branch-specific if not superadmin)
if ($is_superadmin) {
    $stmt = $conn->prepare("
        SELECT SUM(
            CASE 
                WHEN b.has_membership_card = 1 THEN b.vip_elite_amount 
                ELSE b.total_amount 
            END
        ) AS today_income
        FROM bookings b
        WHERE b.status = 'completed' AND DATE(b.created_at) = CURDATE()
    ");
} else {
    $stmt = $conn->prepare("
        SELECT SUM(
            CASE 
                WHEN b.has_membership_card = 1 THEN b.vip_elite_amount 
                ELSE b.total_amount 
            END
        ) AS today_income
        FROM bookings b
        JOIN branches br ON b.branch_id = br.id
        WHERE b.status = 'completed' AND br.name = ? AND DATE(b.created_at) = CURDATE()
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$today_income = $result->fetch_assoc()['today_income'] ?? 0;
$stmt->close();

// Fetch weekly income (branch-specific if not superadmin)
if ($is_superadmin) {
    $stmt = $conn->prepare("
        SELECT SUM(
            CASE 
                WHEN b.has_membership_card = 1 THEN b.vip_elite_amount 
                ELSE b.total_amount 
            END
        ) AS weekly_income
        FROM bookings b
        WHERE b.status = 'completed' 
        AND YEARWEEK(b.created_at, 1) = YEARWEEK(CURDATE(), 1)
    ");
} else {
    $stmt = $conn->prepare("
        SELECT SUM(
            CASE 
                WHEN b.has_membership_card = 1 THEN b.vip_elite_amount 
                ELSE b.total_amount 
            END
        ) AS weekly_income
        FROM bookings b
        JOIN branches br ON b.branch_id = br.id
        WHERE b.status = 'completed' AND br.name = ?
        AND YEARWEEK(b.created_at, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$weekly_income = $result->fetch_assoc()['weekly_income'] ?? 0;
$stmt->close();

// Fetch upcoming appointment (single)
if ($is_superadmin) {
    $stmt = $conn->prepare("
        SELECT b.id, s.name AS service_name, br.name AS branch_name, 
               b.booking_date, b.booking_time, b.status, b.receipt_number,
               u.name AS user_name, GROUP_CONCAT(t.name SEPARATOR ', ') AS therapist_names
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN branches br ON b.branch_id = br.id
        JOIN users u ON b.user_id = u.id
        JOIN booking_therapists bt ON b.id = bt.booking_id
        JOIN therapists t ON bt.therapist_id = t.id
        WHERE b.booking_date >= CURDATE() AND b.status IN ('Pending', 'Active')
        GROUP BY b.id
        ORDER BY b.booking_date ASC, b.booking_time ASC
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT b.id, s.name AS service_name, br.name AS branch_name, 
               b.booking_date, b.booking_time, b.status, b.receipt_number,
               u.name AS user_name, GROUP_CONCAT(t.name SEPARATOR ', ') AS therapist_names
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN branches br ON b.branch_id = br.id
        JOIN users u ON b.user_id = u.id
        JOIN booking_therapists bt ON b.id = bt.booking_id
        JOIN therapists t ON bt.therapist_id = t.id
        WHERE b.booking_date >= CURDATE() AND b.status IN ('Pending', 'Active') AND br.name = ?
        GROUP BY b.id
        ORDER BY b.booking_date ASC, b.booking_time ASC
        LIMIT 1
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$upcoming = $result->fetch_assoc();
$stmt->close();

// Fetch recent bookings (3 max)
if ($is_superadmin) {
    $stmt = $conn->prepare("
        SELECT b.id, s.name AS service_name, br.name AS branch_name,
               b.booking_date, b.booking_time, b.status, b.receipt_number,
               u.name AS user_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN branches br ON b.branch_id = br.id
        JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC
        LIMIT 3
    ");
} else {
    $stmt = $conn->prepare("
        SELECT b.id, s.name AS service_name, br.name AS branch_name,
               b.booking_date, b.booking_time, b.status, b.receipt_number,
               u.name AS user_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN branches br ON b.branch_id = br.id
        JOIN users u ON b.user_id = u.id
        WHERE br.name = ?
        ORDER BY b.created_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$recent_bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch top service
if ($is_superadmin) {
    $stmt = $conn->prepare("
        SELECT s.name, COUNT(b.service_id) AS service_count
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        WHERE b.status != 'Cancelled'
        GROUP BY b.service_id
        ORDER BY service_count DESC
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT s.name, COUNT(b.service_id) AS service_count
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN branches br ON b.branch_id = br.id
        WHERE b.status != 'Cancelled' AND br.name = ?
        GROUP BY b.service_id
        ORDER BY service_count DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$top_service = $result->fetch_assoc();
$stmt->close();

// Fetch top therapist
if ($is_superadmin) {
    $stmt = $conn->prepare("
        SELECT t.name, COUNT(bt.therapist_id) AS therapist_count
        FROM booking_therapists bt
        JOIN therapists t ON bt.therapist_id = t.id
        JOIN bookings b ON bt.booking_id = b.id
        WHERE b.status != 'Cancelled'
        GROUP BY bt.therapist_id
        ORDER BY therapist_count DESC
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT t.name, COUNT(bt.therapist_id) AS therapist_count
        FROM booking_therapists bt
        JOIN therapists t ON bt.therapist_id = t.id
        JOIN bookings b ON bt.booking_id = b.id
        JOIN branches br ON b.branch_id = br.id
        WHERE b.status != 'Cancelled' AND br.name = ?
        GROUP BY bt.therapist_id
        ORDER BY therapist_count DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $branch);
}
$stmt->execute();
$result = $stmt->get_result();
$top_therapist = $result->fetch_assoc();
$stmt->close();

// Formatting functions
function formatTime($time) {
    return date("h:i A", strtotime($time));
}

function formatDate($date) {
    return date("M j, Y", strtotime($date));
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
    <title>Admin Dashboard</title>
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
            margin-left: 250px;
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
                margin-right: 0;
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($admin['name']) ?>!</h1>
            <p class="welcome-subtitle"><?= htmlspecialchars($admin['branch']) ?> Branch Dashboard Overview</p>
        </div>

        <!-- Stats Grid -->
        <div class="row stats-grid mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stats-title">Total Clients</div>
                    <div class="stats-value"><?= $total_users ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-cash"></i>
                    </div>
                    <div class="stats-title">Today Income</div>
                    <div class="stats-value">₱<?= number_format($today_income, 2) ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-cash"></i>
                    </div>
                    <div class="stats-title">Weekly Income</div>
                    <div class="stats-value">₱<?= number_format($weekly_income, 2) ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stats-title">Total Income</div>
                    <div class="stats-value">₱<?= number_format($total_income, 2) ?></div>
                </div>
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
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($upcoming['user_name']) ?><br>
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($upcoming['branch_name']) ?><br>
                                    <i class="bi bi-calendar"></i> <?= formatDate($upcoming['booking_date']) ?><br>
                                    <i class="bi bi-clock"></i> <?= formatTime($upcoming['booking_time']) ?><br>
                                    <i class="bi bi-person-hearts"></i> <?= htmlspecialchars($upcoming['therapist_names']) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <?= getStatusBadge($upcoming['status']) ?>
                                    <a href="manage_bookings.php" class="btn btn-sm btn-oblong">Manage</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x" style="font-size: 2rem; color: var(--medium-gray);"></i>
                                <p class="mt-2">No upcoming appointments</p>
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
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($booking['user_name']) ?><br>
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
                <!-- Top Service -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-star"></i> Top Service</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($top_service): ?>
                            <div class="text-center py-3">
                                <i class="bi bi-heart" style="font-size: 2rem; color: var(--accent-green);"></i>
                                <h5 class="mt-2"><?= htmlspecialchars($top_service['name']) ?></h5>
                                <p class="text-muted"><?= $top_service['service_count'] ?> bookings</p>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-heart" style="font-size: 2rem; color: var(--medium-gray);"></i>
                                <p class="mt-2">No service data yet</p>
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