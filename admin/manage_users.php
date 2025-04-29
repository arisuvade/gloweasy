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

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete from bookings first (due to foreign key constraints)
        $stmt = $conn->prepare("DELETE FROM bookings WHERE user_id = ? AND branch_id = (SELECT id FROM branches WHERE name = ?)");
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

// Handle Excel export
if (isset($_GET['export_excel'])) {
    require_once '../vendor/autoload.php';
    
    // Get filter values
    $search_term = $_GET['search'] ?? '';
    $membership_filter = $_GET['membership_filter'] ?? 'with_card';
    
    // Build query for current branch only
    $query = "SELECT u.name, u.email, 
                     MAX(b.has_membership_card) as has_membership_card,
                     MAX(b.membership_code) as card_code
              FROM users u
              LEFT JOIN bookings b ON u.id = b.user_id 
              WHERE b.branch_id = (SELECT id FROM branches WHERE name = ?)";
    
    $params = [$branch];
    $types = 's';
    
    // Membership filter - only check for cards issued at this branch
    if ($membership_filter === 'with_card') {
        $query .= " AND u.id IN (SELECT DISTINCT user_id FROM bookings WHERE has_membership_card = 1 AND branch_id = (SELECT id FROM branches WHERE name = ?))";
        $params[] = $branch;
        $types .= 's';
    } elseif ($membership_filter === 'no_card') {
        $query .= " AND u.id NOT IN (SELECT DISTINCT user_id FROM bookings WHERE has_membership_card = 1 AND branch_id = (SELECT id FROM branches WHERE name = ?))";
        $params[] = $branch;
        $types .= 's';
    }
    
    // Search filter
    if (!empty($search_term)) {
        $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search_term%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
    }
    
    $query .= " GROUP BY u.id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
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
            $user['has_membership_card'] ? 'Yes' : 'No',
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

// Build query for table display - current branch only
$query = "SELECT u.id, u.name, u.email, u.created_at, 
                 MAX(b.has_membership_card) as has_membership_card,
                 COUNT(b.id) as total_bookings
          FROM users u
          LEFT JOIN bookings b ON u.id = b.user_id
          WHERE b.branch_id = (SELECT id FROM branches WHERE name = ?)";
          
$params = [$branch];
$types = 's';

// Membership filter - only check for cards issued at this branch
if ($membership_filter === 'with_card') {
    $query .= " AND u.id IN (SELECT DISTINCT user_id FROM bookings WHERE has_membership_card = 1 AND branch_id = (SELECT id FROM branches WHERE name = ?))";
    $params[] = $branch;
    $types .= 's';
} elseif ($membership_filter === 'no_card') {
    $query .= " AND u.id NOT IN (SELECT DISTINCT user_id FROM bookings WHERE has_membership_card = 1 AND branch_id = (SELECT id FROM branches WHERE name = ?))";
    $params[] = $branch;
    $types .= 's';
}

// Search filter
if (!empty($search_term)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$query .= " GROUP BY u.id
           ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
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
        
        .membership-yes { 
            background-color: #D4EDDA; 
            color: #155724; 
            border: 1px solid #C3E6CB;
        }
        
        .membership-no { 
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <h1 class="mb-4">Manage Users</h1>
        
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
        
        <!-- Filter/Search Form -->
        <div class="filter-section">
            <form method="get" class="row g-3 align-items-center">
                <!-- Search Box -->
                <div class="col-md-5">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" name="search" placeholder="Search Name, Email" 
                            value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="search-btn" style="display:none;"></button>
                    </div>
                </div>
                
                <!-- Membership Filter -->
                <div class="col-md-5">
                    <select class="form-select" id="membership_filter" name="membership_filter" onchange="this.form.submit()">
                        <option value="with_card" <?= $membership_filter === 'with_card' ? 'selected' : '' ?>>With Membership Card</option>
                        <option value="no_card" <?= $membership_filter === 'no_card' ? 'selected' : '' ?>>No Membership Card</option>
                        <option value="all" <?= $membership_filter === 'all' ? 'selected' : '' ?>>All Users</option>
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

        <!-- Users Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Membership</th>
                        <th>Total Bookings</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="membership-badge <?= $user['has_membership_card'] ? 'membership-yes' : 'membership-no' ?>">
                                        <?= $user['has_membership_card'] ? 'Has Card' : 'No Card' ?>
                                    </span>
                                </td>
                                <td><?= $user['total_bookings'] ?></td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <form method="post" class="delete-form" style="display: inline-block;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" name="delete_user" class="action-btn btn-delete">
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>