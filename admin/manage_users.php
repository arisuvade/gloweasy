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

// Handle all CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_member':
                $customer_name = $_POST['customer_name'] ?? '';
                $customer_email = $_POST['customer_email'] ?? '';
                $card_type = $_POST['card_type'] ?? '';
                $membership_code = $_POST['membership_code'] ?? '';
                
                // Get branch ID
                $branch_id = $conn->query("SELECT id FROM branches WHERE name = '$branch'")->fetch_assoc()['id'];
                
                $stmt = $conn->prepare("INSERT INTO membership_members (customer_name, customer_email, branch_id, card_type, membership_code) 
                                        VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiss", $customer_name, $customer_email, $branch_id, $card_type, $membership_code);
                break;
                
            case 'delete_member':
                $member_id = $_POST['member_id'] ?? 0;
                
                // Verify member belongs to admin's branch
                $verify_stmt = $conn->prepare("SELECT id FROM membership_members WHERE id = ? AND branch_id = (SELECT id FROM branches WHERE name = ?)");
                $verify_stmt->bind_param("is", $member_id, $branch);
                $verify_stmt->execute();
                
                if ($verify_stmt->get_result()->num_rows === 0) {
                    throw new Exception("Unauthorized to delete this member");
                }
                $verify_stmt->close();
                
                $stmt = $conn->prepare("DELETE FROM membership_members WHERE id = ?");
                $stmt->bind_param("i", $member_id);
                break;
                
            case 'delete_user':
                $user_id = $_POST['user_id'];
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Delete from bookings first (due to foreign key constraints)
                    $stmt = $conn->prepare("DELETE FROM bookings WHERE user_id = ? AND branch_id = (SELECT id FROM branches WHERE name = ?)");
                    $stmt->bind_param("is", $user_id, $branch);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Delete from membership_members
                    $stmt = $conn->prepare("DELETE FROM membership_members WHERE user_id = ? AND branch_id = (SELECT id FROM branches WHERE name = ?)");
                    $stmt->bind_param("is", $user_id, $branch);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Then delete the user if they have no bookings in other branches
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND NOT EXISTS (SELECT 1 FROM bookings WHERE user_id = ? AND branch_id != (SELECT id FROM branches WHERE name = ?))");
                    $stmt->bind_param("iis", $user_id, $user_id, $branch);
                    $stmt->execute();
                    $stmt->close();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = "User deleted successfully";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
                }
                
                header("Location: manage_users.php");
                exit();
        }
        
        if (isset($stmt)) {
            $stmt->execute();
            $stmt->close();
        }
        
        // Return JSON for AJAX requests
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit();
        }
        
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    // Redirect for non-AJAX requests
    header("Location: manage_users.php");
    exit();
}

// Handle Excel export
if (isset($_GET['export_excel'])) {
    require_once '../vendor/autoload.php';
    
    // Get filter values
    $search_term = $_GET['search'] ?? '';
    $membership_filter = $_GET['membership_filter'] ?? 'with_card';
    $card_type_filter = $_GET['card_type_filter'] ?? 'all';
    
    // Build base queries
    $members_query = "SELECT 
                        m.id,
                        COALESCE(u.name, m.customer_name) as name, 
                        COALESCE(u.email, m.customer_email) as email, 
                        m.card_type as membership_type,
                        m.membership_code as card_code,
                        m.created_at
                      FROM membership_members m
                      LEFT JOIN users u ON m.user_id = u.id
                      WHERE m.branch_id = (SELECT id FROM branches WHERE name = ?)";
    
    $users_query = "SELECT 
                      u.id,
                      u.name, 
                      u.email, 
                      'No Card' as membership_type,
                      NULL as card_code,
                      u.created_at
                    FROM users u
                    WHERE NOT EXISTS (
                        SELECT 1 FROM membership_members m 
                        WHERE m.user_id = u.id
                        AND m.branch_id = (SELECT id FROM branches WHERE name = ?)
                    )";
    
    // Initialize parameters
    $params = [];
    $types = '';
    
    // Determine which query to use based on filter
    if ($membership_filter === 'with_card') {
        $query = $members_query;
        $params = [$branch];
        $types = 's';
        
        // Card type filter when showing members with cards
        if ($card_type_filter !== 'all') {
            $query .= " AND m.card_type = ?";
            $params[] = $card_type_filter;
            $types .= 's';
        }
    } elseif ($membership_filter === 'no_card') {
        $query = $users_query;
        $params = [$branch];
        $types = 's';
    } elseif ($membership_filter === 'all') {
        // Combine both queries with UNION
        $query = "($members_query) UNION ALL ($users_query)";
        $params = [$branch, $branch];
        $types = 'ss';
    }
    
    // Apply search term if provided
    if (!empty($search_term)) {
        $search_param = "%$search_term%";
        if ($membership_filter === 'all') {
            // For combined query, we need to wrap it and apply WHERE to the outer query
            $query = "SELECT * FROM ($query) AS combined WHERE name LIKE ? OR email LIKE ?";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ss';
        } else {
            // For single queries, just add WHERE clause
            $query .= " AND (COALESCE(u.name, m.customer_name) LIKE ? OR COALESCE(u.email, m.customer_email) LIKE ?)";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ss';
        }
    }
    
    // Order results
    $query .= " ORDER BY created_at DESC";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die('Error preparing statement: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    // Create Excel file with styling
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers with styling
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '6C757D']],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    
    // Only include the requested columns
    $sheet->fromArray(['Name', 'Email', 'Membership', 'Card Code'], null, 'A1');
    $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
    
    // Add data with borders
    $row = 2;
    foreach ($users as $user) {
        $sheet->fromArray([
            $user['name'],
            $user['email'],
            $user['membership_type'],
            $user['card_code'] ?? ''
        ], null, "A$row");
        
        // Apply borders to all cells
        $sheet->getStyle("A$row:D$row")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
        
        $row++;
    }
    
    // Auto size columns
    foreach (range('A', 'D') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Set file name and headers
    $filename = 'user_report_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Save to output
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit();
}

// Get filter values from GET - default to 'with_card'
$search_term = $_GET['search'] ?? '';
$membership_filter = $_GET['membership_filter'] ?? 'with_card';
$card_type_filter = $_GET['card_type_filter'] ?? 'all';

// Main query for members with cards (from membership_members table)
$members_query = "SELECT 
                    id,
                    customer_name as name, 
                    customer_email as email, 
                    created_at,
                    card_type as membership_type,
                    membership_code as card_code
                  FROM membership_members 
                  WHERE branch_id = (SELECT id FROM branches WHERE name = ?)";
$members_params = [$branch];
$members_types = 's';

// Query for users without cards (from users table)
$users_query = "SELECT 
                    u.id,
                    u.name, 
                    u.email, 
                    u.created_at,
                    'No Card' as membership_type,
                    NULL as card_code
                FROM users u
                WHERE NOT EXISTS (
                    SELECT 1 FROM membership_members m 
                    WHERE m.user_id = u.id
                    AND m.branch_id = (SELECT id FROM branches WHERE name = ?)
                )";
$users_params = [$branch];
$users_types = 's';

// Apply filters to members query
if ($membership_filter === 'with_card') {
    if ($card_type_filter !== 'all') {
        $members_query .= " AND card_type = ?";
        $members_params[] = $card_type_filter;
        $members_types .= 's';
    }
} elseif ($membership_filter === 'no_card') {
    $members_query .= " AND 1=0"; // Return no results
}

// Apply filters to users query
if ($membership_filter === 'with_card') {
    $users_query .= " AND 1=0"; // Return no results
} elseif ($membership_filter === 'no_card') {
    // No additional filters needed
}

// Apply search term to both queries
if (!empty($search_term)) {
    $search_param = "%$search_term%";
    
    $members_query .= " AND (customer_name LIKE ? OR customer_email LIKE ?)";
    $members_params[] = $search_param;
    $members_params[] = $search_param;
    $members_types .= 'ss';
    
    $users_query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $users_params[] = $search_param;
    $users_params[] = $search_param;
    $users_types .= 'ss';
}

// Execute both queries
$members = [];
$users = [];

// Get members with cards
$stmt = $conn->prepare($members_query);
$stmt->bind_param($members_types, ...$members_params);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get users without cards
$stmt = $conn->prepare($users_query);
$stmt->bind_param($users_types, ...$users_params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Combine results
$all_users = array_merge($members, $users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-white: #ffffff;
            --secondary-green: #2e8b57;
            --accent-green: #4caf93;
            --light-green: #e8f5e9;
            --dark-text: #2a6049;
            --medium-gray: #6c757d;
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

        .action-btn {
            margin-right: 5px;
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 20px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-filter {
            background-color: var(--secondary-green);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .btn-filter:hover {
            background-color: var(--oblong-hover);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-export {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .btn-export:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

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

        .btn-delete {
            background-color: #DC3545;
            color: white;
        }

        .btn-delete:hover {
            background-color: #C82333;
            color: white;
            transform: translateY(-2px);
        }

        .filter-section {
            background-color: var(--light-green);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .table {
            width: 100%;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .membership-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        
        .membership-vip { 
            background-color: #D4EDDA; 
            color: #155724; 
            border: 1px solid #C3E6CB;
        }
        
        .membership-elite { 
            background-color: #D1ECF1; 
            color: #0C5460; 
            border: 1px solid #BEE5EB;
        }
        
        .membership-none { 
            background-color: #F8D7DA; 
            color: #721C24; 
            border: 1px solid #F5C6CB;
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-excel {
            background-color: #1e8449;
            color: white;
        }

        .btn-excel:hover {
            background-color: #186138;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            background-color: white;
            margin-bottom: 2rem;
        }

        .card-body {
            padding: 20px;
        }

        .add-member-btn {
            background-color: var(--secondary-green);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .add-member-btn:hover {
            background-color: var(--oblong-hover);
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <h1 class="mb-4">Manage Customers</h1>
        
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Add Member Card -->
        <div class="card">
            <div class="card-body">
                <h2 class="mb-4">Add Manual Member</h2>
                <form id="addMemberForm">
                    <input type="hidden" name="action" value="add_member">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Email (Optional)</label>
                            <input type="email" name="customer_email" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Card Type</label>
                            <select name="card_type" class="form-select" required>
                                <option value="VIP">VIP</option>
                                <option value="Elite">Elite</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Membership Code</label>
                            <input type="text" name="membership_code" class="form-control" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn" style="background-color: var(--secondary-green); color: white;">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter/Search Form -->
        <div class="filter-section">
            <form method="get" class="row g-3 align-items-center">
                <!-- Search Box -->
                <div class="col-md-4">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" name="search" placeholder="Search Name, Email" 
                            value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="search-btn" style="display:none;"></button>
                    </div>
                </div>
                
                <!-- Membership Filter -->
                <div class="col-md-3">
                    <select class="form-select" id="membership_filter" name="membership_filter">
                        <option value="with_card" <?= $membership_filter === 'with_card' ? 'selected' : '' ?>>With Membership Card</option>
                        <option value="no_card" <?= $membership_filter === 'no_card' ? 'selected' : '' ?>>No Membership Card</option>
                        <option value="all" <?= $membership_filter === 'all' ? 'selected' : '' ?>>All Customers</option>
                    </select>
                </div>
                
                <!-- Card Type Filter (only shown when viewing members with cards) -->
                <div class="col-md-3">
                    <select class="form-select" id="card_type_filter" name="card_type_filter" <?= $membership_filter !== 'with_card' ? 'disabled' : '' ?> style="<?= $membership_filter !== 'with_card' ? 'opacity: 0.5;' : '' ?>">
                        <option value="all" <?= $card_type_filter === 'all' ? 'selected' : '' ?>>All Card Types</option>
                        <option value="VIP" <?= $card_type_filter === 'VIP' ? 'selected' : '' ?>>VIP Only</option>
                        <option value="Elite" <?= $card_type_filter === 'Elite' ? 'selected' : '' ?>>Elite Only</option>
                    </select>
                </div>
                
                <!-- Export Button -->
                <div class="col-md-2">
                    <button type="submit" name="export_excel" class="btn-oblong btn-excel w-100">
                        <i class="fas fa-file-excel me-2"></i> Export to Excel
                    </button>
                </div>
            </form>
        </div>

        <!-- Customers Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Membership</th>
                        <th>Card Code</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_users)): ?>
                        <tr><td colspan="5" class="text-center">No customers found</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="membership-badge membership-<?= strtolower(str_replace(' ', '-', $user['membership_type'])) ?>">
                                        <?= $user['membership_type'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['card_code'] ?? '') ?></td>
                                <td>
                                    <form method="post" class="delete-form">
                                        <?php if (isset($user['id']) && $user['membership_type'] !== 'No Card'): ?>
                                            <input type="hidden" name="action" value="delete_member">
                                            <input type="hidden" name="member_id" value="<?= $user['id'] ?>">
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="action-btn btn-delete">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        
        // SweetAlert for delete confirmation
        $('.delete-form').on('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading indicator
                    Swal.fire({
                        title: 'Deleting',
                        html: 'Please wait while we delete the user...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form
                    $.ajax({
                        type: 'POST',
                        url: '',
                        data: $(this).serialize(),
                        success: function(response) {
                            Swal.fire(
                                'Deleted!',
                                'The user has been deleted.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        },
                        error: function() {
                            Swal.fire(
                                'Error!',
                                'There was a problem deleting the user.',
                                'error'
                            );
                        }
                    });
                }
            });
        });

        // Handle form submissions for adding member
        $('#addMemberForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize() + '&ajax=1';
            
            $.ajax({
                url: 'manage_users.php',
                type: 'POST',
                data: formData,
                dataType: 'json', // Expect JSON response
                success: function(result) {
                    if (result.success) {
                        $('#addMemberForm')[0].reset();
                        Swal.fire(
                            'Success!',
                            'Member added successfully.',
                            'success'
                        ).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error!',
                            result.error || 'Unknown error occurred',
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire(
                        'Error!',
                        'Failed to add member: ' + error,
                        'error'
                    );
                }
            });
        });

        // Disable card type filter when not viewing "With Membership Card"
        function updateCardTypeFilter() {
            const membershipFilter = document.getElementById('membership_filter');
            const cardTypeFilter = document.getElementById('card_type_filter');
            
            if (membershipFilter.value === 'with_card') {
                cardTypeFilter.disabled = false;
                cardTypeFilter.style.opacity = '1';
            } else {
                cardTypeFilter.disabled = true;
                cardTypeFilter.style.opacity = '0.5';
                // Reset to 'all' when disabled
                cardTypeFilter.value = 'all';
            }
        }

        // Initialize and add event listener
        updateCardTypeFilter();
        
        document.getElementById('membership_filter').addEventListener('change', function() {
            updateCardTypeFilter();
            // Submit the form when filter changes
            this.form.submit();
        });
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>