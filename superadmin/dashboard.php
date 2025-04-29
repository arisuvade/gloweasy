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

// Fetch total users (all branches)
$stmt = $conn->prepare("SELECT COUNT(id) AS total_users FROM users");
$stmt->execute();
$result = $stmt->get_result();
$total_users = $result->fetch_assoc()['total_users'] ?? 0;
$stmt->close();

// Fetch total bookings (all branches)
$stmt = $conn->prepare("SELECT COUNT(id) AS total_bookings FROM bookings");
$stmt->execute();
$result = $stmt->get_result();
$total_bookings = $result->fetch_assoc()['total_bookings'] ?? 0;
$stmt->close();

// Fetch total income (all branches)
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
$stmt->execute();
$result = $stmt->get_result();
$total_income = $result->fetch_assoc()['total_income'] ?? 0;
$stmt->close();

// Fetch booking status counts (all branches)
$status_counts = [];
$statuses = ['Pending', 'Active', 'Completed', 'Cancelled'];

foreach ($statuses as $status) {
    $stmt = $conn->prepare("SELECT COUNT(id) AS count FROM bookings WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $status_counts[$status] = $result->fetch_assoc()['count'];
    $stmt->close();
}

// Fetch membership card statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT user_id) AS total_membership_users,
        SUM(CASE WHEN has_membership_card = 1 THEN 1 ELSE 0 END) AS total_membership_bookings
    FROM bookings
    WHERE has_membership_card = 1
");
$stmt->execute();
$result = $stmt->get_result();
$membership_stats = $result->fetch_assoc();
$stmt->close();

// Fetch branch-wise statistics
$stmt = $conn->prepare("
    SELECT 
        br.name AS branch_name,
        COUNT(DISTINCT b.user_id) AS users,
        COUNT(b.id) AS bookings,
        SUM(CASE WHEN b.status = 'Completed' THEN 
            CASE WHEN b.has_membership_card = 1 THEN b.vip_elite_amount ELSE b.total_amount END
        ELSE 0 END) AS income,
        SUM(CASE WHEN b.has_membership_card = 1 THEN 1 ELSE 0 END) AS membership_bookings
    FROM branches br
    LEFT JOIN bookings b ON br.id = b.branch_id
    GROUP BY br.id
    ORDER BY income DESC
");
$stmt->execute();
$result = $stmt->get_result();
$branch_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch top service (all branches)
$stmt = $conn->prepare("
    SELECT s.name, COUNT(b.service_id) AS service_count
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.status != 'Cancelled'
    GROUP BY b.service_id
    ORDER BY service_count DESC
    LIMIT 1
");
$stmt->execute();
$result = $stmt->get_result();
$top_service = $result->fetch_assoc();
$stmt->close();

// Fetch top therapist (all branches)
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

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function getStatusBadge($status) {
    $classes = [
        'Pending' => 'bg-secondary',
        'Active' => 'bg-primary',
        'Completed' => 'bg-success',
        'Cancelled' => 'bg-danger'
    ];
    return '<span class="badge ' . ($classes[$status] ?? 'bg-secondary') . '">' . $status . '</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
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

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .bg-primary {
            background-color: var(--accent-green) !important;
        }

        .bg-success {
            background-color: var(--secondary-green) !important;
        }

        .branch-card {
            border-left: 4px solid var(--accent-green);
            margin-bottom: 15px;
        }

        .branch-name {
            font-weight: 600;
            color: var(--dark-text);
        }

        .branch-stats {
            font-size: 14px;
        }

        .branch-income {
            font-weight: 600;
            color: var(--secondary-green);
        }

        .table th {
            background-color: var(--light-green);
            border-bottom: 2px solid var(--accent-green);
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
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h1 class="welcome-title">Welcome, Owner!</h1>
            <p class="welcome-subtitle">Business Overview Dashboard</p>
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
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stats-title">Total Bookings</div>
                    <div class="stats-value"><?= $total_bookings ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stats-title">Total Income</div>
                    <div class="stats-value"><?= formatCurrency($total_income) ?></div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div class="stats-title">Membership Users</div>
                    <div class="stats-value"><?= $membership_stats['total_membership_users'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Branch Performance -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-shop"></i> Branch Performance</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($branch_stats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Branch</th>
                                            <th>Clients</th>
                                            <th>Bookings</th>
                                            <th>Membership Bookings</th>
                                            <th>Income</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($branch_stats as $branch): ?>
                                            <tr>
                                                <td class="branch-name"><?= htmlspecialchars($branch['branch_name']) ?></td>
                                                <td><?= $branch['users'] ?></td>
                                                <td><?= $branch['bookings'] ?></td>
                                                <td><?= $branch['membership_bookings'] ?></td>
                                                <td class="branch-income"><?= formatCurrency($branch['income'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-shop" style="font-size: 2rem; color: var(--medium-gray);"></i>
                                <p class="mt-2">No branch data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Booking Status Overview -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-bar-chart"></i> Booking Status Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($status_counts as $status => $count): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="stats-card">
                                        <div class="stats-title"><?= $status ?></div>
                                        <div class="stats-value"><?= $count ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Membership Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-credit-card"></i> Membership Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stats-title">Total Membership Users</div>
                            <div class="stats-value"><?= $membership_stats['total_membership_users'] ?? 0 ?></div>
                        </div>
                        <div class="stats-card mt-3">
                            <div class="stats-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="stats-title">Total Membership Bookings</div>
                            <div class="stats-value"><?= $membership_stats['total_membership_bookings'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Performers
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-trophy"></i> Top Performers</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($top_service): ?>
                            <div class="stats-card mb-3">
                                <div class="stats-icon">
                                    <i class="bi bi-heart"></i>
                                </div>
                                <div class="stats-title">Top Service</div>
                                <div class="stats-value"><?= htmlspecialchars($top_service['name']) ?></div>
                                <div class="stats-title"><?= $top_service['service_count'] ?> bookings</div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($top_therapist): ?>
                            <div class="stats-card">
                                <div class="stats-icon">
                                    <i class="bi bi-person-hearts"></i>
                                </div>
                                <div class="stats-title">Top Therapist</div>
                                <div class="stats-value"><?= htmlspecialchars($top_therapist['name']) ?></div>
                                <div class="stats-title"><?= $top_therapist['therapist_count'] ?> bookings</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div> -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../includes/footer.php'; ?>
