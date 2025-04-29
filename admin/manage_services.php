<?php
session_start();
require '../includes/db.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

// Handle all CRUD operations in one place
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table = $_POST['table'] ?? '';
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    // Common data for all tables
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $duration = $_POST['duration'] ?? 0;
    $regular_rate = $_POST['regular_rate'] ?? 0;
    $vip_elite_rate = $_POST['vip_elite_rate'] ?? 0;
    $category = $_POST['category'] ?? 'Regular';
    
    try {
        switch ($action) {
            case 'add':
                if ($table === 'services') {
                    $stmt = $conn->prepare("INSERT INTO services (name, description, duration, regular_rate, vip_elite_rate, category) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssidds", $name, $description, $duration, $regular_rate, $vip_elite_rate, $category);
                } elseif ($table === 'addons') {
                    $stmt = $conn->prepare("INSERT INTO addons (name, duration, regular_rate, vip_elite_rate) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sidd", $name, $duration, $regular_rate, $vip_elite_rate);
                }
                break;
                
            case 'edit':
                if ($table === 'services') {
                    $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, duration = ?, regular_rate = ?, vip_elite_rate = ?, category = ? WHERE id = ?");
                    $stmt->bind_param("ssiddsi", $name, $description, $duration, $regular_rate, $vip_elite_rate, $category, $id);
                } elseif ($table === 'addons') {
                    $stmt = $conn->prepare("UPDATE addons SET name = ?, duration = ?, regular_rate = ?, vip_elite_rate = ? WHERE id = ?");
                    $stmt->bind_param("siddi", $name, $duration, $regular_rate, $vip_elite_rate, $id);
                }
                break;
                
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->bind_param("i", $id);
                break;
                
            case 'toggle_status':
                $is_active = $_POST['is_active'] ?? 0;
                $stmt = $conn->prepare("UPDATE $table SET is_active = ? WHERE id = ?");
                $stmt->bind_param("ii", $is_active, $id);
                break;
        }
        
        if (isset($stmt)) {
            $stmt->execute();
            $stmt->close();
        }
        
        // Return JSON response for AJAX requests
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
    header("Location: manage_services.php");
    exit();
}

// Fetch all data
$services = $conn->query("SELECT * FROM services")->fetch_all(MYSQLI_ASSOC);
$addons = $conn->query("SELECT * FROM addons")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .table-responsive {
            margin-top: 20px;
            overflow-x: auto;
        }
        .card {
            margin-bottom: 20px;
        }
        .status-toggle {
            cursor: pointer;
        }
        .form-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h1 class="mb-4">Manage Services</h1>
            
            <!-- Services Section -->
            <div class="card form-section">
                <div class="card-body">
                    <h2 class="mb-4">Services</h2>
                    
                    <!-- Add Service Form -->
                    <form id="addServiceForm" class="mb-4">
                        <input type="hidden" name="table" value="services">
                        <input type="hidden" name="action" value="add">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="Regular">Regular</option>
                                    <option value="Body Healing">Body Healing</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Duration (mins)</label>
                                <input type="number" name="duration" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Regular Rate</label>
                                <input type="number" step="0.01" name="regular_rate" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">VIP/Elite Rate</label>
                                <input type="number" step="0.01" name="vip_elite_rate" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Add Service</button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Services Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Duration</th>
                                    <th>Regular Rate</th>
                                    <th>VIP/Elite Rate</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><?= htmlspecialchars($service['name']) ?></td>
                                    <td><?= htmlspecialchars($service['category']) ?></td>
                                    <td><?= htmlspecialchars($service['duration']) ?> mins</td>
                                    <td>₱<?= number_format($service['regular_rate'], 2) ?></td>
                                    <td>₱<?= number_format($service['vip_elite_rate'], 2) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning edit-service" 
                                            data-id="<?= $service['id'] ?>"
                                            data-name="<?= htmlspecialchars($service['name']) ?>"
                                            data-category="<?= htmlspecialchars($service['category']) ?>"
                                            data-duration="<?= htmlspecialchars($service['duration']) ?>"
                                            data-regular_rate="<?= htmlspecialchars($service['regular_rate']) ?>"
                                            data-vip_elite_rate="<?= htmlspecialchars($service['vip_elite_rate']) ?>"
                                            data-description="<?= htmlspecialchars($service['description']) ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-item" 
                                            data-table="services" 
                                            data-id="<?= $service['id'] ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Addons Section -->
            <div class="card form-section">
                <div class="card-body">
                    <h2 class="mb-4">Add-ons</h2>
                    
                    <!-- Add Addon Form -->
                    <form id="addAddonForm" class="mb-4">
                        <input type="hidden" name="table" value="addons">
                        <input type="hidden" name="action" value="add">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Duration (mins)</label>
                                <input type="number" name="duration" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Regular Rate</label>
                                <input type="number" step="0.01" name="regular_rate" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">VIP/Elite Rate</label>
                                <input type="number" step="0.01" name="vip_elite_rate" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Add Add-on</button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Addons Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Duration</th>
                                    <th>Regular Rate</th>
                                    <th>VIP/Elite Rate</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($addons as $addon): ?>
                                <tr>
                                    <td><?= htmlspecialchars($addon['name']) ?></td>
                                    <td><?= htmlspecialchars($addon['duration']) ?> mins</td>
                                    <td>₱<?= number_format($addon['regular_rate'], 2) ?></td>
                                    <td>₱<?= number_format($addon['vip_elite_rate'], 2) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning edit-addon" 
                                            data-id="<?= $addon['id'] ?>"
                                            data-name="<?= htmlspecialchars($addon['name']) ?>"
                                            data-duration="<?= htmlspecialchars($addon['duration']) ?>"
                                            data-regular_rate="<?= htmlspecialchars($addon['regular_rate']) ?>"
                                            data-vip_elite_rate="<?= htmlspecialchars($addon['vip_elite_rate']) ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-item" 
                                            data-table="addons" 
                                            data-id="<?= $addon['id'] ?>">
                                            <i class="bi bi-trash"></i> Delete
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
    </div>

    <!-- Edit Modals -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" name="table" id="editTable">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="editId">
                        
                        <div id="serviceFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="Regular">Regular</option>
                                    <option value="Body Healing">Body Healing</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Duration (mins)</label>
                                <input type="number" name="duration" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Regular Rate</label>
                                <input type="number" step="0.01" name="regular_rate" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">VIP/Elite Rate</label>
                                <input type="number" step="0.01" name="vip_elite_rate" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div id="addonFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Duration (mins)</label>
                                <input type="number" name="duration" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Regular Rate</label>
                                <input type="number" step="0.01" name="regular_rate" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">VIP/Elite Rate</label>
                                <input type="number" step="0.01" name="vip_elite_rate" class="form-control" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveChanges">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item?</p>
                    <form id="deleteForm">
                        <input type="hidden" name="table" id="deleteTable">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        // Handle service edit button clicks
        $('.edit-service').click(function() {
            const data = $(this).data();
            $('#editTable').val('services');
            $('#editId').val(data.id);
            
            // Show service fields and hide others
            $('#serviceFields').removeClass('d-none');
            $('#addonFields').addClass('d-none');
            
            // Populate form
            $('#serviceFields [name="name"]').val(data.name);
            $('#serviceFields [name="category"]').val(data.category);
            $('#serviceFields [name="duration"]').val(data.duration);
            $('#serviceFields [name="regular_rate"]').val(data.regular_rate);
            $('#serviceFields [name="vip_elite_rate"]').val(data.vip_elite_rate);
            $('#serviceFields [name="description"]').val(data.description);
            
            editModal.show();
        });
        
        // Handle addon edit button clicks
        $('.edit-addon').click(function() {
            const data = $(this).data();
            $('#editTable').val('addons');
            $('#editId').val(data.id);
            
            // Show addon fields and hide others
            $('#addonFields').removeClass('d-none');
            $('#serviceFields').addClass('d-none');
            
            // Populate form
            $('#addonFields [name="name"]').val(data.name);
            $('#addonFields [name="duration"]').val(data.duration);
            $('#addonFields [name="regular_rate"]').val(data.regular_rate);
            $('#addonFields [name="vip_elite_rate"]').val(data.vip_elite_rate);
            
            editModal.show();
        });
        
        // Handle delete button clicks
        $('.delete-item').click(function() {
            const table = $(this).data('table');
            const id = $(this).data('id');
            
            $('#deleteTable').val(table);
            $('#deleteId').val(id);
            deleteModal.show();
        });
        
        // Save changes in edit modal
        $('#saveChanges').click(function() {
            const formData = $('#editForm').serialize() + '&ajax=1';
            
            $.ajax({
                url: 'manage_services.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (result.error || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error saving changes');
                }
            });
        });
        
        // Confirm delete
        $('#confirmDelete').click(function() {
            const formData = $('#deleteForm').serialize() + '&ajax=1';
            
            $.ajax({
                url: 'manage_services.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (result.error || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error deleting item');
                }
            });
        });
        
        // Handle form submissions
        $('#addServiceForm, #addAddonForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize() + '&ajax=1';
            
            $.ajax({
                url: 'manage_services.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (result.error || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error adding item');
                }
            });
        });
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>
