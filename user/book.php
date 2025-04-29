<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize variables with default values
$branches = [];
$selected_branch_id = null;
$therapists = [];
$services_by_category = [];
$all_addons = [];

// Fetch user details
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch all branches
$branches_query = $conn->query("SELECT id, name FROM branches");
if ($branches_query) {
    $branches = $branches_query->fetch_all(MYSQLI_ASSOC);
}

// Only fetch other data if branch is selected
if (isset($_GET['branch_id'])) {
    $selected_branch_id = intval($_GET['branch_id']);
    
    // Fetch therapists for selected branch
    $stmt = $conn->prepare("SELECT id, name, role FROM therapists WHERE is_active = 1 AND branch_id = ?");
    $stmt->bind_param("i", $selected_branch_id);
    if ($stmt->execute()) {
        $therapists = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();

    // Fetch all active addons with their full details
    $addons_query = $conn->query("SELECT id, name, regular_rate, vip_elite_rate, duration FROM addons WHERE is_active = 1");
    if ($addons_query) {
        while ($row = $addons_query->fetch_assoc()) {
            $all_addons[$row['id']] = $row;
        }
    }

    // Fetch all services grouped by category
    $services_query = $conn->query("SELECT id, name, description, regular_rate, vip_elite_rate, duration, category FROM services WHERE is_active = 1");
    if ($services_query) {
        while ($service = $services_query->fetch_assoc()) {
            $services_by_category[$service['category']][] = $service;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Service</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .container {
            max-width: 1200px;
            padding: 20px;
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

        /* Rest of your existing book.php styles */
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2.5rem;
        }

        .step {
            padding: 10px 25px;
            margin: 0 5px;
            border-radius: 30px;
            background-color: var(--light-green);
            color: var(--accent-green);
            font-weight: 500;
            position: relative;
        }

        .step.active {
            background-color: var(--secondary-green);
            color: white;
        }

        .step:not(:last-child):after {
            content: '→';
            position: absolute;
            right: -18px;
            color: #bdbdbd;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            background-color: white;
            margin-bottom: 2rem;
        }

        /* Branch Selection Styles */
        #step1 {
            display: block;
        }

        #step2, #step3 {
            display: none;
        }

        .branch-card {
            cursor: pointer;
            padding: 2rem;
            border: 1px solid var(--medium-gray);
            border-radius: 10px;
            transition: all 0.3s ease;
            text-align: center;
            margin-bottom: 1.5rem;
            height: 100%;
        }

        .branch-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(46,139,87,0.1);
            border-color: var(--accent-green);
        }

        .branch-card.selected {
            border: 2px solid var(--secondary-green);
            background-color: rgba(233,245,233,0.3);
        }

        .branch-icon {
            font-size: 2.5rem;
            color: var(--accent-green);
            margin-bottom: 1rem;
        }

        /* Service Selection Styles */
        .category-title {
            color: var(--secondary-green);
            font-weight: 600;
            margin: 2rem 0 1rem;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--light-green);
        }

        .service-card {
            cursor: pointer;
            border: 1px solid var(--medium-gray);
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
            height: 220px;
            display: flex;
            flex-direction: column;
        }

        .service-card .card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .service-card:hover {
            border-color: var(--accent-green);
            box-shadow: 0 5px 15px rgba(76,175,147,0.1);
            transform: translateY(-3px);
        }

        .service-card.selected {
            border: 2px solid var(--secondary-green);
            background-color: rgba(233,245,233,0.3);
        }

        .service-card .card-text {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .service-card .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            margin-top: auto;
            border-top: 1px solid #eee;
        }

        /* Booking Details Styles */
        .booking-details-form {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            padding: 10px 15px;
        }

        /* Time Slot Styles */
        .time-period-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--medium-gray);
        }

        .time-period-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .time-period-tab.active {
            color: var(--secondary-green);
            border-bottom-color: var(--secondary-green);
        }

        .time-slots-wrapper {
            overflow-x: auto;
            padding-bottom: 10px;
            justify-content: center;
        }

        .time-slots-container {
            display: none;
            flex-wrap: nowrap;
            gap: 10px;
            min-width: max-content;
            justify-content: center;
        }

        .time-slots-container.active {
            display: flex;
        }

        .time-slot {
            padding: 8px 15px;
            border: 1px solid var(--medium-gray);
            border-radius: 20px;
            cursor: pointer;
            white-space: nowrap;
        }

        .time-slot.selected {
            background-color: var(--secondary-green);
            color: white;
        }

        /* Addons & Total Styles */
        .addons-section {
            text-align: center;
            margin: 2rem 0;
        }

        .total-display {
            background-color: var(--light-green);
            padding: 1.5rem;
            border-radius: 8px;
        }

        /* Button Styles */
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

        .btn-oblong-secondary {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: white;
            color: var(--dark-text);
            border: 1px solid var(--medium-gray);
        }

        .btn-oblong-secondary:hover {
            background-color: var(--light-green);
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            .action-buttons .btn {
                width: 100%;
            }
        }

        .modal.show .modal-dialog {
            margin: auto;
            transform: translate(0, 0);
        }

        .addon-card.selected {
            border: 2px solid var(--secondary-green);
            background-color: rgba(233,245,233,0.3);
        }

        /* Addons card styling */
.addon-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid var(--medium-gray);
    border-radius: 8px;
    margin-bottom: 10px;
}

.addon-card:hover {
    border-color: var(--accent-green);
    box-shadow: 0 5px 15px rgba(76,175,147,0.1);
}

.addon-card.selected {
    border: 2px solid var(--secondary-green);
    background-color: rgba(233,245,233,0.3);
}

.addon-card .card-body {
    padding: 1rem;
}

.addon-card h6 {
    color: var(--dark-text);
    margin-bottom: 0.25rem;
}

.addon-card .small {
    color: #666;
}

.addon-card .text-primary {
    color: var(--secondary-green) !important;
    font-weight: 500;
}

        /* Number of Clients styling */
            .number-of-clients-container {
                text-align: center;
                margin: 1.5rem 0;
            }

            .btn-number-of-clients {
                padding: 10px 25px;
                border-radius: 30px;
                font-weight: 500;
                transition: all 0.3s ease;
                background-color: var(--oblong-green);
                color: white;
                border: none;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }

            .btn-number-of-clients:hover {
                background-color: var(--oblong-hover);
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }

        .btn-number-of-clients {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: var(--oblong-green);
            color: white;
            border: none;
        }

        .btn-number-of-clients:hover {
            background-color: var(--oblong-hover);
            transform: translateY(-2px);
        }

        /* Disabled therapist selection */
        .therapist-select.disabled {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .therapist-select.disabled option:not(:first-child) {
            display: none;
        }

        /* Client count buttons in modal - Updated to match addons style */
.client-count-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin: 1.5rem 0;
}

.client-count-btn {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.2rem;
    border: 2px solid var(--secondary-green);
    background-color: white;
    color: var(--secondary-green);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.client-count-btn:hover {
    background-color: var(--light-green);
    transform: translateY(-2px);
}

.client-count-btn.active {
    background-color: var(--secondary-green);
    color: white;
    border-color: var(--dark-text);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.modal-footer {
    border-top: 1px solid var(--medium-gray);
    padding: 1rem;
    display: flex;
    justify-content: space-between;
}

        /* Modal buttons */
#resetAddons, #cancelAddons, #cancelNumberOfClients {
    background-color: white;
    color: var(--dark-text);
    border: 1px solid var(--medium-gray);
}

#resetAddons:hover, #cancelAddons:hover, #cancelNumberOfClients:hover {
    background-color: var(--light-green);
}

#saveAddons, #saveNumberOfClients {
    background-color: var(--oblong-green);
    color: white;
}

#saveAddons:hover, #saveNumberOfClients:hover {
    background-color: var(--oblong-hover);
}
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <h1>Book a Service</h1>
            
            <div class="step-indicator">
                <div class="step active" id="step1-indicator">1. Select Branch</div>
                <div class="step" id="step2-indicator">2. Select Service</div>
                <div class="step" id="step3-indicator">3. Booking Details</div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <input type="hidden" id="selectedServiceId">
                    <input type="hidden" id="selectedServiceDuration">
                    <input type="hidden" id="serviceRegularRate">
                    <input type="hidden" id="serviceVipRate">
                    <input type="hidden" id="selectedAddons" value="[]">
                    <input type="hidden" id="selectedBranchId" value="<?= $selected_branch_id ?>">
                    
                    <!-- Step 1: Branch Selection -->
                    <div id="step1">
                        <h3 class="text-center mb-4">Select Your Preferred Branch</h3>
                        <div class="row">
                            <?php foreach ($branches as $branch): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="branch-card" data-branch-id="<?= $branch['id'] ?>">
                                        <div class="branch-icon">
                                            <i class="fas fa-spa"></i>
                                        </div>
                                        <h4><?= htmlspecialchars($branch['name']) ?></h4>
                                        <p><?= $branch['id'] == 1 ? 'Malolos, Bulacan' : 'Calumpit, Bulacan' ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="action-buttons">
                            <button class="btn-oblong-secondary" id="cancelBtn">
                                <i class="fas fa-times me-2"></i> Cancel
                            </button>
                            <button id="nextBtnStep1" class="btn-oblong" disabled>
                                Next <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Service Selection -->
                    <div id="step2">
                        <h3 class="text-center mb-4">Select Your Service</h3>
                        <?php foreach ($services_by_category as $category => $services): ?>
                            <h4 class="category-title">
                                <?= $category === 'Regular' ? 'Regular Services' : 'Body Healing Packages' ?>
                            </h4>
                            <div class="row">
                                <?php foreach ($services as $service): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="service-card" 
                                             data-service-id="<?= $service['id'] ?>"
                                             data-regular-rate="<?= $service['regular_rate'] ?>"
                                             data-vip-rate="<?= $service['vip_elite_rate'] ?>"
                                             data-duration="<?= $service['duration'] ?>"
                                             data-category="<?= $service['category'] ?>">
                                            <div class="card-body">
                                                <h5><?= htmlspecialchars($service['name']) ?></h5>
                                                <p class="card-text"><?= htmlspecialchars($service['description']) ?></p>
                                                <div class="card-footer">
                                                    <span><?= $service['duration'] ?> mins</span>
                                                    <span>₱<?= number_format($service['regular_rate'], 2) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="action-buttons">
                            <button id="backBtnStep2" class="btn-oblong-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </button>
                            <button id="nextBtnStep2" class="btn-oblong" disabled>
                                Next <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Booking Details -->
                    <div id="step3">
                        <h3 class="text-center mb-4">Complete Your Booking</h3>
                        <div class="booking-details-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Therapist</label>
                                        <select id="therapist" class="form-select therapist-select">
                                            <option value="0">Any Available Therapist</option>
                                            <?php foreach ($therapists as $therapist): ?>
                                                <option value="<?= $therapist['id'] ?>">
                                                    <?= htmlspecialchars($therapist['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Date</label>
                                        <input type="date" id="bookingDate" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="d-block text-center mb-3">Select Time</label>
                                <div class="time-period-tabs text-center mb-3">
                                    <div class="time-period-tab active" data-period="morning">Morning</div>
                                    <div class="time-period-tab" data-period="afternoon">Afternoon</div>
                                    <div class="time-period-tab" data-period="evening">Evening</div>
                                </div>
                                <div class="time-slots-wrapper">
                                    <div class="time-slots-container active" data-period="morning"></div>
                                    <div class="time-slots-container" data-period="afternoon"></div>
                                    <div class="time-slots-container" data-period="evening"></div>
                                </div>
                            </div>

                            <div class="number-of-clients-container" id="numberOfClientsContainer" style="display: none;">
                                <button type="button" id="numberOfClientsBtn" class="btn-number-of-clients">
                                    <i class="fas fa-users me-2"></i> Select Number of Clients
                                </button>
                            </div>

                            <div class="addons-section">
                                <button type="button" id="addonsBtn" class="btn-oblong">
                                    <i class="fas fa-plus-circle me-2"></i> Select Addons
                                </button>
                            </div>

                            <div class="total-display mt-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Total Amount:</strong>
                                    <span id="totalAmount">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>VIP/Elite Amount:</strong>
                                    <span id="vipTotalAmount">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <strong>Total Duration:</strong>
                                    <span id="totalDuration">0 mins</span>
                                </div>
                            </div>

                            <div class="action-buttons mt-4">
                                <button id="backBtnStep3" class="btn-oblong-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back
                                </button>
                                <button id="saveBtn" class="btn-oblong">
                                    <i class="fas fa-calendar-check me-2"></i> Book Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Initialize variables
        let selectedTime = '';
        let selectedAddons = [];
        let serviceRegularRate = 0;
        let serviceVipRate = 0;
        let serviceDuration = 0;
        let serviceCategory = '';
        let totalRegular = 0;
        let totalVip = 0;
        let totalDuration = 0;
        let selectedBranchId = null;
        let numberOfClients = 1;
        let bedUsed = 1;
        
        // Store addon details
        let addonDetails = {
            <?php if (isset($all_addons)): ?>
                <?php foreach ($all_addons as $addon): ?>
                    <?= $addon['id'] ?>: {
                        id: <?= $addon['id'] ?>,
                        name: "<?= $addon['name'] ?>",
                        regular_rate: <?= $addon['regular_rate'] ?>,
                        vip_rate: <?= $addon['vip_elite_rate'] ?>,
                        duration: <?= $addon['duration'] ?>,
                        selected: false
                    },
                <?php endforeach; ?>
            <?php endif; ?>
        };

        // Branch card click handler
        $(document).on('click', '.branch-card', function() {
            $('.branch-card').removeClass('selected');
            $(this).addClass('selected');
            selectedBranchId = $(this).data('branch-id');
            $('#selectedBranchId').val(selectedBranchId);
            $('#nextBtnStep1').prop('disabled', false);
        });

        // Next button on step 1 (branch selection)
        $('#nextBtnStep1').click(function() {
            if (!selectedBranchId) return;
            
            // Update URL with branch_id parameter
            const newUrl = window.location.pathname + '?branch_id=' + selectedBranchId;
            window.history.pushState({}, '', newUrl);
            location.reload();
            
            $('#step1').hide();
            $('#step1-indicator').removeClass('active');
            $('#step2').show();
            $('#step2-indicator').addClass('active');
        });

        // Back button on step 2
        $('#backBtnStep2').click(function() {
            $('#step2').hide();
            $('#step2-indicator').removeClass('active');
            $('#step1').show();
            $('#step1-indicator').addClass('active');
        });

        // Next button on step 2 (service selection)
        $('#nextBtnStep2').click(function() {
            if (!$('#selectedServiceId').val()) return;
            
            $('#step2').hide();
            $('#step2-indicator').removeClass('active');
            $('#step3').show();
            $('#step3-indicator').addClass('active');
        });

        // Back button on step 3
        $('#backBtnStep3').click(function() {
            $('#step3').hide();
            $('#step3-indicator').removeClass('active');
            $('#step2').show();
            $('#step2-indicator').addClass('active');
        });

        // Service card click handler
        $(document).on('click', '.service-card', function() {
    serviceCategory = $(this).data('category');
    
    if (serviceCategory === 'Body Healing') {
        // For Body Healing - disable therapist selection
        $('#therapist').addClass('disabled').prop('disabled', true);
        $('#numberOfClientsContainer').show();
        $('#addonsBtn').hide().prop('disabled', true);
        numberOfClients = 1;
        bedUsed = 1;
    } else {
        // For Regular services - enable therapist selection
        $('#therapist').removeClass('disabled').prop('disabled', false);
        $('#numberOfClientsContainer').hide();
        $('#addonsBtn').show().prop('disabled', false);
    }
    
    // Rest of your existing code...
    $('.service-card').removeClass('selected');
    $(this).addClass('selected');
    
    serviceRegularRate = parseFloat($(this).data('regular-rate'));
    serviceVipRate = parseFloat($(this).data('vip-rate'));
    serviceDuration = parseInt($(this).data('duration'));
    
    $('#selectedServiceId').val($(this).data('service-id'));
    $('#serviceRegularRate').val(serviceRegularRate);
    $('#serviceVipRate').val(serviceVipRate);
    $('#selectedServiceDuration').val(serviceDuration);
    
    totalRegular = serviceRegularRate;
    totalVip = serviceVipRate;
    totalDuration = serviceDuration;
    
    updateTotals();
    updateAddonsButtonText();
    
    $('#nextBtnStep2').prop('disabled', false);
});

        // Time period tab click handler
        $(document).on('click', '.time-period-tab', function() {
            const period = $(this).data('period');
            
            $('.time-period-tab').removeClass('active');
            $(this).addClass('active');
            
            $('.time-slots-container').removeClass('active');
            $(`.time-slots-container[data-period="${period}"]`).addClass('active');
        });

        // Time slot selection with duration checking
        $(document).on('click', '.time-slot', function() {
            const slotTime = $(this).text();
            const slotDateTime = new Date(`${$('#bookingDate').val()} ${slotTime}`);
            const endDateTime = new Date(slotDateTime.getTime() + (totalDuration * 60000));
            
            const lastSlotTime = new Date(`${$('#bookingDate').val()} 10:00 PM`);
            if (endDateTime > lastSlotTime) {
                Swal.fire({
                    title: 'Not Enough Time',
                    text: 'The selected time plus service duration goes beyond business hours. Please choose an earlier time.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            $('.time-slot').removeClass('selected');
            $(this).addClass('selected');
            selectedTime = slotTime;
        });

        // Addons button click handler
        $('#addonsBtn').click(function() {
            showAddonsModal();
        });

        // Number of Clients button click handler
        $('#numberOfClientsBtn').click(function() {
            showNumberOfClientsModal();
        });

        function showAddonsModal() {
            // Create modal structure
            let modalHTML = `
            <div class="modal fade" id="addonsModal" tabindex="-1" aria-labelledby="addonsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addonsModalLabel">Select Addons</h5>
                        </div>
                        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                            <div id="addonsListContainer">
            `;
            
            // Add addons cards
            Object.values(addonDetails).forEach(addon => {
                modalHTML += `
                    <div class="card mb-2 addon-card ${addon.selected ? 'selected' : ''}" 
                         data-addon-id="${addon.id}">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6>${addon.name}</h6>
                                <p class="mb-0 small">${addon.duration} mins</p>
                            </div>
                            <div>
                                <span class="text-primary">₱${addon.regular_rate.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Close modal structure
            modalHTML += `
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button id="resetAddons" class="btn-oblong-secondary">Reset All</button>
                            <div>
                                <button id="cancelAddons" class="btn-oblong-secondary me-2">Cancel</button>
                                <button id="saveAddons" class="btn-oblong">Save Addons</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
            
            // Remove any existing modal first
            if ($('#addonsModal').length) {
                $('#addonsModal').remove();
            }
            
            // Add modal to body
            $('body').append(modalHTML);
            
            // Initialize modal
            const addonsModal = new bootstrap.Modal(document.getElementById('addonsModal'));
            addonsModal.show();
            
            // Store initial state for cancel
            const initialState = {
                selectedAddons: [...selectedAddons],
                totalRegular: totalRegular,
                totalVip: totalVip,
                totalDuration: totalDuration
            };

            // Addon card click handler
            $('#addonsModal').on('click', '.addon-card', function() {
                const addonId = $(this).data('addon-id');
                const addon = addonDetails[addonId];
                
                addon.selected = !addon.selected;
                $(this).toggleClass('selected', addon.selected);
                
                // Recalculate totals
                totalRegular = serviceRegularRate;
                totalVip = serviceVipRate;
                totalDuration = serviceDuration;
                selectedAddons = [];
                
                Object.values(addonDetails).forEach(addon => {
                    if (addon.selected) {
                        totalRegular += addon.regular_rate;
                        totalVip += addon.vip_rate;
                        totalDuration += addon.duration;
                        selectedAddons.push(addon.id);
                    }
                });
                
                updateTotals();
                $('#selectedAddons').val(JSON.stringify(selectedAddons));
                updateAddonsButtonText();
            });

            // Reset addons button
            $('#addonsModal').on('click', '#resetAddons', function() {
                Object.values(addonDetails).forEach(addon => {
                    addon.selected = false;
                });
                
                $('#addonsListContainer .addon-card').removeClass('selected');
                
                totalRegular = serviceRegularRate;
                totalVip = serviceVipRate;
                totalDuration = serviceDuration;
                selectedAddons = [];
                
                updateTotals();
                $('#selectedAddons').val(JSON.stringify(selectedAddons));
                updateAddonsButtonText();
            });

            // Save addons button
            $('#addonsModal').on('click', '#saveAddons', function() {
                addonsModal.hide();
                $('#addonsModal').remove();
            });

            // Cancel addons button
            $('#addonsModal').on('click', '#cancelAddons', function() {
                // Revert to initial state
                Object.values(addonDetails).forEach(addon => {
                    addon.selected = initialState.selectedAddons.includes(addon.id);
                });
                
                totalRegular = initialState.totalRegular;
                totalVip = initialState.totalVip;
                totalDuration = initialState.totalDuration;
                selectedAddons = [...initialState.selectedAddons];
                
                updateTotals();
                $('#selectedAddons').val(JSON.stringify(selectedAddons));
                updateAddonsButtonText();
                
                addonsModal.hide();
                $('#addonsModal').remove();
            });

            // Clean up when modal is hidden
            $('#addonsModal').on('hidden.bs.modal', function() {
                $('#addonsModal').remove();
            });
        }

        function showNumberOfClientsModal() {
    let modalHTML = `
    <div class="modal fade" id="numberOfClientsModal" tabindex="-1" aria-labelledby="numberOfClientsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="numberOfClientsModalLabel">Select Number of Clients</h5>
                </div>
                <div class="modal-body">
                    <div class="client-count-container">
                        <button type="button" class="client-count-btn ${numberOfClients === 1 ? 'active' : ''}" data-count="1">1</button>
                        <button type="button" class="client-count-btn ${numberOfClients === 2 ? 'active' : ''}" data-count="2">2</button>
                        <button type="button" class="client-count-btn ${numberOfClients === 3 ? 'active' : ''}" data-count="3">3</button>
                        <button type="button" class="client-count-btn ${numberOfClients === 4 ? 'active' : ''}" data-count="4">4</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="cancelNumberOfClients" class="btn-oblong-secondary">Cancel</button>
                    <button id="saveNumberOfClients" class="btn-oblong">Save</button>
                </div>
            </div>
        </div>
    </div>
    `;
    
    // Remove any existing modal first
    if ($('#numberOfClientsModal').length) {
        $('#numberOfClientsModal').remove();
    }
    
    // Add modal to body
    $('body').append(modalHTML);
    
    // Initialize modal
    const numberOfClientsModal = new bootstrap.Modal(document.getElementById('numberOfClientsModal'));
    numberOfClientsModal.show();
    
    // Client count button click handler
    $('#numberOfClientsModal').on('click', '.client-count-btn', function() {
        $('.client-count-btn').removeClass('active');
        $(this).addClass('active');
        numberOfClients = parseInt($(this).data('count'));
    });
    
    // Save button
    $('#numberOfClientsModal').on('click', '#saveNumberOfClients', function() {
        bedUsed = numberOfClients; // Set bed used equal to number of clients
        updateNumberOfClientsButton();
        numberOfClientsModal.hide();
        $('#numberOfClientsModal').remove();
    });
    
    // Cancel button
    $('#numberOfClientsModal').on('click', '#cancelNumberOfClients', function() {
        numberOfClientsModal.hide();
        $('#numberOfClientsModal').remove();
    });
    
    // Clean up when modal is hidden
    $('#numberOfClientsModal').on('hidden.bs.modal', function() {
        $('#numberOfClientsModal').remove();
    });
}

function updateNumberOfClientsButton() {
    $('#numberOfClientsBtn').html(`
        <i class="fas fa-users me-2"></i> 
        ${numberOfClients} Client${numberOfClients > 1 ? 's' : ''}
    `);
}

        function updateTotals() {
            $('#totalAmount').text('₱' + totalRegular.toFixed(2));
            $('#vipTotalAmount').text('₱' + totalVip.toFixed(2));
            $('#totalDuration').text(totalDuration + ' mins');
        }

        function updateAddonsButtonText() {
            const count = selectedAddons.length;
            $('#addonsBtn').html(`<i class="fas fa-plus-circle me-2"></i> Select Addons (${count} selected)`);
        }

        // Cancel button
        $('#cancelBtn').click(function() {
            window.location.href = 'bookings.php';
        });

        // Save/Book button handler
        $('#saveBtn').click(function() {
            const bookingDate = $('#bookingDate').val();
            const serviceId = $('#selectedServiceId').val();
            const branchId = $('#selectedBranchId').val();
            
            if (!serviceId) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select a service',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if (!bookingDate) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select a booking date',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            if (!selectedTime) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select a time slot',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            const addonsData = [];
            Object.values(addonDetails).forEach(addon => {
                if (addon.selected) {
                    addonsData.push({
                        id: addon.id,
                        name: addon.name,
                        regular_rate: addon.regular_rate,
                        vip_rate: addon.vip_rate,
                        duration: addon.duration
                    });
                }
            });

            $('#saveBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Booking...');
            
            $.ajax({
                url: 'confirm_booking.php',
                type: 'POST',
                data: {
                    serviceId: serviceId,
                    therapistId: $('#therapist').val(),
                    bookingDate: bookingDate,
                    time: selectedTime,
                    addons: addonsData,
                    totalRegularRate: totalRegular,
                    totalVipEliteRate: totalVip,
                    totalDuration: totalDuration,
                    branchId: branchId,
                    numberOfClients: numberOfClients,
                    bedUsed: bedUsed
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'bookings.php';
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-calendar-check me-2"></i> Book Now');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error:", status, error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-calendar-check me-2"></i> Book Now');
                }
            });
        });

        // Initialize time slots
        function initializeTimeSlots() {
            const morningSlots = ['11:00 AM', '11:30 AM'];
            const afternoonSlots = ['12:00 PM', '12:30 PM', '1:00 PM', '1:30 PM', '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM', '4:00 PM', '4:30 PM'];
            const eveningSlots = ['5:00 PM', '5:30 PM', '6:00 PM', '6:30 PM', '7:00 PM', '7:30 PM', '8:00 PM', '8:30 PM'];
            
            const morningContainer = $('.time-slots-container[data-period="morning"]');
            const afternoonContainer = $('.time-slots-container[data-period="afternoon"]');
            const eveningContainer = $('.time-slots-container[data-period="evening"]');
            
            morningSlots.forEach(slot => {
                morningContainer.append(`<div class="time-slot">${slot}</div>`);
            });
            
            afternoonSlots.forEach(slot => {
                afternoonContainer.append(`<div class="time-slot">${slot}</div>`);
            });
            
            eveningSlots.forEach(slot => {
                eveningContainer.append(`<div class="time-slot">${slot}</div>`);
            });
        }
        
        initializeTimeSlots();
        
        <?php if ($selected_branch_id): ?>
            $('#step1').hide();
            $('#step1-indicator').removeClass('active');
            $('#step2').show();
            $('#step2-indicator').addClass('active');
        <?php endif; ?>
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>
