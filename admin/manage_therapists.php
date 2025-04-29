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

// Handle all CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    try {
        switch ($action) {
            case 'add_therapist':
                $name = $_POST['name'] ?? '';
                $role = $_POST['role'] ?? '';
                $branch_id = $_POST['branch_id'] ?? null;
                
                // For non-superadmins, force their branch
                if (!$is_superadmin) {
                    $branch_id = $conn->query("SELECT id FROM branches WHERE name = '$branch'")->fetch_assoc()['id'];
                }
                
                $stmt = $conn->prepare("INSERT INTO therapists (name, role, branch_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $name, $role, $branch_id);
                break;
                
            case 'update_therapist':
                $name = $_POST['name'] ?? '';
                $role = $_POST['role'] ?? '';
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $branch_id = $_POST['branch_id'] ?? null;
                
                // For non-superadmins, verify therapist belongs to their branch
                if (!$is_superadmin) {
                    $verify_stmt = $conn->prepare("SELECT id FROM therapists WHERE id = ? AND branch_id = (SELECT id FROM branches WHERE name = ?)");
                    $verify_stmt->bind_param("is", $id, $branch);
                    $verify_stmt->execute();
                    
                    if ($verify_stmt->get_result()->num_rows === 0) {
                        throw new Exception("Unauthorized to update this therapist");
                    }
                    $verify_stmt->close();
                    
                    // Force their branch
                    $branch_id = $conn->query("SELECT id FROM branches WHERE name = '$branch'")->fetch_assoc()['id'];
                }
                
                $stmt = $conn->prepare("UPDATE therapists SET name = ?, role = ?, is_active = ?, branch_id = ? WHERE id = ?");
                $stmt->bind_param("ssiii", $name, $role, $is_active, $branch_id, $id);
                break;
                
            case 'delete_therapist':
                // For non-superadmins, verify therapist belongs to their branch
                if (!$is_superadmin) {
                    $verify_stmt = $conn->prepare("SELECT id FROM therapists WHERE id = ? AND branch_id = (SELECT id FROM branches WHERE name = ?)");
                    $verify_stmt->bind_param("is", $id, $branch);
                    $verify_stmt->execute();
                    
                    if ($verify_stmt->get_result()->num_rows === 0) {
                        throw new Exception("Unauthorized to delete this therapist");
                    }
                    $verify_stmt->close();
                }
                
                $stmt = $conn->prepare("DELETE FROM therapists WHERE id = ?");
                $stmt->bind_param("i", $id);
                break;
                
            case 'set_unavailable':
                $therapist_id = $_POST['therapist_id'] ?? 0;
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $reason = $_POST['reason'] ?? 'other';
                
                // For non-superadmins, verify therapist belongs to their branch
                if (!$is_superadmin) {
                    $verify_stmt = $conn->prepare("SELECT id FROM therapists WHERE id = ? AND branch_id = (SELECT id FROM branches WHERE name = ?)");
                    $verify_stmt->bind_param("is", $therapist_id, $branch);
                    $verify_stmt->execute();
                    
                    if ($verify_stmt->get_result()->num_rows === 0) {
                        throw new Exception("Unauthorized to set unavailability for this therapist");
                    }
                    $verify_stmt->close();
                }
                
                // Validate date range
                if ($start_date > $end_date) {
                    $temp = $start_date;
                    $start_date = $end_date;
                    $end_date = $temp;
                }
                
                $stmt = $conn->prepare("INSERT INTO therapist_availability (therapist_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $therapist_id, $start_date, $end_date, $reason);
                break;
                
            case 'cancel_unavailability':
                // For non-superadmins, verify the unavailability record belongs to their branch
                if (!$is_superadmin) {
                    $verify_stmt = $conn->prepare("
                        SELECT ta.id 
                        FROM therapist_availability ta
                        JOIN therapists t ON ta.therapist_id = t.id
                        JOIN branches b ON t.branch_id = b.id
                        WHERE ta.id = ? AND b.name = ?
                    ");
                    $verify_stmt->bind_param("is", $id, $branch);
                    $verify_stmt->execute();
                    
                    if ($verify_stmt->get_result()->num_rows === 0) {
                        throw new Exception("Unauthorized to cancel this unavailability");
                    }
                    $verify_stmt->close();
                }
                
                $stmt = $conn->prepare("DELETE FROM therapist_availability WHERE id = ?");
                $stmt->bind_param("i", $id);
                break;
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
    header("Location: manage_therapists.php");
    exit();
}

// Base query for therapists
$query = "
    SELECT t.*, b.name AS branch_name 
    FROM therapists t
    LEFT JOIN branches b ON t.branch_id = b.id
";

// Add branch filter if not superadmin
if (!$is_superadmin) {
    $query .= " WHERE b.name = ?";
    $params[] = $branch;
    $types = "s";
}

$query .= " ORDER BY t.name ASC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$is_superadmin) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$therapists = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all availability records with branch filtering
$availability_query = "
    SELECT ta.* 
    FROM therapist_availability ta
    JOIN therapists t ON ta.therapist_id = t.id
    LEFT JOIN branches b ON t.branch_id = b.id
";

if (!$is_superadmin) {
    $availability_query .= " WHERE b.name = ?";
    $availability_params[] = $branch;
    $availability_types = "s";
}

$availability_stmt = $conn->prepare($availability_query);
if (!$is_superadmin) {
    $availability_stmt->bind_param($availability_types, ...$availability_params);
}
$availability_stmt->execute();
$availability_result = $availability_stmt->get_result();
$availability_records = $availability_result->fetch_all(MYSQLI_ASSOC);
$availability_stmt->close();

// Fetch all branches for superadmin
$branches = [];
if ($is_superadmin) {
    $branches = $conn->query("SELECT id, name FROM branches")->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

// Formatting function
function formatDateRange($start, $end) {
    return date("M j", strtotime($start)) . ' - ' . date("M j, Y", strtotime($end));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Therapists</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        }

        @media (min-width: 1400px) {
            .main-content {
                max-width: 1400px;
            }
        }

        .table-responsive {
            margin: 20px 0;
            background-color: var(--primary-white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            width: 100%;
        }

        .filter-section {
            background-color: var(--light-green);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            width: 100%;
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        th {
            background-color: var(--light-green);
            border-bottom: 2px solid var(--accent-green);
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }

        .status-active { 
            background-color: #D4EDDA; 
            color: #155724; 
            border: 1px solid #C3E6CB;
        }
        
        .status-inactive { 
            background-color: #F8D7DA; 
            color: #721C24; 
            border: 1px solid #F5C6CB;
        }

        .unavailability-badge {
            background-color: #FFF3CD;
            color: #856404;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
            border: 1px solid #FFEEBA;
        }

        .action-btn {
            margin-right: 5px;
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background-color: var(--accent-green);
            color: white;
            border: none;
        }

        .edit-btn:hover {
            background-color: #3d9e80;
            color: white;
            transform: translateY(-2px);
        }

        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
        }

        .delete-btn:hover {
            background-color: #c82333;
            color: white;
            transform: translateY(-2px);
        }

        .unavailable-btn {
            background-color: var(--secondary-green);
            color: white;
            border: none;
        }

        .unavailable-btn:hover {
            background-color: #247a4a;
            color: white;
            transform: translateY(-2px);
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

        .btn-oblong-outline {
            padding: 8px 20px;
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

        .modal-header {
            background-color: var(--light-green);
            border-bottom: 1px solid var(--medium-gray);
        }

        .modal-title {
            color: var(--dark-text);
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <h1>Manage Therapists</h1>
        
        <!-- Add Therapist Form -->
        <div class="card">
            <div class="card-body">
                <h2 class="mb-4">Add New Therapist</h2>
                <form id="addTherapistForm">
                    <input type="hidden" name="action" value="add_therapist">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="Head Therapist">Head Therapist</option>
                                <option value="Licensed Massage Therapist">Licensed Massage Therapist</option>
                                <option value="Senior Therapist">Senior Therapist</option>
                                <option value="Therapist">Therapist</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select" <?= $is_superadmin ? '' : 'disabled' ?> required>
                                <?php if ($is_superadmin): ?>
                                    <?php foreach ($branches as $branch_option): ?>
                                        <option value="<?= $branch_option['id'] ?>"><?= htmlspecialchars($branch_option['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="<?= $branches[0]['id'] ?? '' ?>"><?= htmlspecialchars($branch) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-oblong">Add Therapist</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Therapists Table -->
        <div class="card">
            <div class="card-body">
                <h2 class="mb-4">All Therapists</h2>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th>Unavailability</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($therapists as $therapist): ?>
                            <tr>
                                <td><?= htmlspecialchars($therapist['name']) ?></td>
                                <td><?= htmlspecialchars($therapist['role']) ?></td>
                                <td><?= htmlspecialchars($therapist['branch_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge status-<?= $therapist['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $therapist['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $therapist_availability = array_filter($availability_records, function($a) use ($therapist) {
                                        return $a['therapist_id'] == $therapist['id'];
                                    });
                                    
                                    foreach ($therapist_availability as $availability): ?>
                                        <span class="unavailability-badge" 
                                              data-id="<?= $availability['id'] ?>"
                                              data-start="<?= $availability['start_date'] ?>"
                                              data-end="<?= $availability['end_date'] ?>"
                                              data-reason="<?= $availability['reason'] ?>">
                                            <?= formatDateRange($availability['start_date'], $availability['end_date']) ?>
                                            <i class="bi bi-x-circle cancel-unavailability" style="cursor:pointer; margin-left:5px;"></i>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <button class="action-btn edit-btn edit-therapist"
                                        data-id="<?= $therapist['id'] ?>"
                                        data-name="<?= htmlspecialchars($therapist['name']) ?>"
                                        data-role="<?= htmlspecialchars($therapist['role']) ?>"
                                        data-is_active="<?= $therapist['is_active'] ?>"
                                        data-branch_id="<?= $therapist['branch_id'] ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="action-btn delete-btn delete-therapist" 
                                        data-id="<?= $therapist['id'] ?>">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                    <button class="action-btn unavailable-btn set-unavailable"
                                        data-therapist_id="<?= $therapist['id'] ?>">
                                        <i class="bi bi-calendar-x"></i> Set Unavailable
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Therapist Modal -->
    <div class="modal fade" id="editTherapistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Therapist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editTherapistForm">
                        <input type="hidden" name="action" value="update_therapist">
                        <input type="hidden" name="id" id="editTherapistId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" id="editTherapistName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" id="editTherapistRole" required>
                                <option value="Head Therapist">Head Therapist</option>
                                <option value="Licensed Massage Therapist">Licensed Massage Therapist</option>
                                <option value="Senior Therapist">Senior Therapist</option>
                                <option value="Therapist">Therapist</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select" id="editTherapistBranch" <?= $is_superadmin ? '' : 'disabled' ?> required>
                                <?php if ($is_superadmin): ?>
                                    <?php foreach ($branches as $branch_option): ?>
                                        <option value="<?= $branch_option['id'] ?>"><?= htmlspecialchars($branch_option['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="<?= $branches[0]['id'] ?? '' ?>"><?= htmlspecialchars($branch) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="editTherapistActive">
                            <label class="form-check-label" for="editTherapistActive">Active</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-oblong-outline" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-oblong" id="saveTherapistChanges">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Set Unavailable Modal -->
    <div class="modal fade" id="setUnavailableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title">Set Unavailable Dates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="setUnavailableForm">
                        <input type="hidden" name="action" value="set_unavailable">
                        <input type="hidden" name="therapist_id" id="unavailableTherapistId">
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <select name="reason" class="form-select" required>
                                <option value="vacation">Vacation</option>
                                <option value="sickness">Sickness</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-oblong-outline" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-oblong" id="saveUnavailable">Set Unavailable</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    const editModal = new bootstrap.Modal(document.getElementById('editTherapistModal'));
    const unavailableModal = new bootstrap.Modal(document.getElementById('setUnavailableModal'));
    
    // Handle edit therapist button clicks
    $('.edit-therapist').click(function() {
        const data = $(this).data();
        $('#editTherapistId').val(data.id);
        $('#editTherapistName').val(data.name);
        $('#editTherapistRole').val(data.role);
        $('#editTherapistBranch').val(data.branch_id);
        $('#editTherapistActive').prop('checked', data.is_active == 1);
        editModal.show();
    });
    
    // Handle set unavailable button clicks
    $('.set-unavailable').click(function() {
        const therapistId = $(this).data('therapist_id');
        $('#unavailableTherapistId').val(therapistId);
        
        // Set default dates (today to tomorrow)
        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split('T')[0];
        
        $('#setUnavailableForm [name="start_date"]').val(today);
        $('#setUnavailableForm [name="end_date"]').val(tomorrowStr);
        
        unavailableModal.show();
    });
    
    // Handle cancel unavailability clicks
    $(document).on('click', '.cancel-unavailability', function() {
        const badge = $(this).closest('.unavailability-badge');
        const availabilityId = badge.data('id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to remove this unavailability period?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'manage_therapists.php',
                    type: 'POST',
                    data: {
                        action: 'cancel_unavailability',
                        id: availabilityId,
                        ajax: 1
                    },
                    dataType: 'json', // Expect JSON response
                    success: function(result) {
                        if (result.success) {
                            Swal.fire(
                                'Removed!',
                                'The unavailability period has been removed.',
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
                            'Failed to remove unavailability: ' + error,
                            'error'
                        );
                    }
                });
            }
        });
    });
    
    // Handle delete button clicks
    $('.delete-therapist').click(function() {
        const id = $(this).data('id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "This will permanently delete the therapist!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'manage_therapists.php',
                    type: 'POST',
                    data: {
                        action: 'delete_therapist',
                        id: id,
                        ajax: 1
                    },
                    dataType: 'json', // Expect JSON response
                    success: function(result) {
                        if (result.success) {
                            Swal.fire(
                                'Deleted!',
                                'Therapist has been deleted.',
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
                            'Failed to delete therapist: ' + error,
                            'error'
                        );
                    }
                });
            }
        });
    });
    
    // Save therapist changes
    $('#saveTherapistChanges').click(function() {
        const formData = $('#editTherapistForm').serialize() + '&ajax=1';
        
        $.ajax({
            url: 'manage_therapists.php',
            type: 'POST',
            data: formData,
            dataType: 'json', // Expect JSON response
            success: function(result) {
                if (result.success) {
                    editModal.hide();
                    Swal.fire(
                        'Updated!',
                        'Therapist has been updated.',
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
                    'Failed to update therapist: ' + error,
                    'error'
                );
            }
        });
    });
    
    // Save unavailable dates
    $('#saveUnavailable').click(function() {
        const formData = $('#setUnavailableForm').serialize() + '&ajax=1';
        
        $.ajax({
            url: 'manage_therapists.php',
            type: 'POST',
            data: formData,
            dataType: 'json', // Expect JSON response
            success: function(result) {
                if (result.success) {
                    unavailableModal.hide();
                    Swal.fire(
                        'Success!',
                        'Unavailability period set successfully.',
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
                    'Failed to set unavailability: ' + error,
                    'error'
                );
            }
        });
    });
    
    // Handle form submissions for adding therapist
    $('#addTherapistForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&ajax=1';
        
        $.ajax({
            url: 'manage_therapists.php',
            type: 'POST',
            data: formData,
            dataType: 'json', // Expect JSON response
            success: function(result) {
                if (result.success) {
                    $('#addTherapistForm')[0].reset();
                    Swal.fire(
                        'Success!',
                        'Therapist added successfully.',
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
                    'Failed to add therapist: ' + error,
                    'error'
                );
            }
        });
    });
});
</script>
</body>
</html>

<?php include '../includes/footer.php'; ?>