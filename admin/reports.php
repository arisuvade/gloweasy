<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$branch = $_SESSION['branch'] ?? '';

// Get branch ID for the current admin
$branch_stmt = $conn->prepare("SELECT id FROM branches WHERE name = ?");
$branch_stmt->bind_param("s", $branch);
$branch_stmt->execute();
$branch_result = $branch_stmt->get_result();
$branch_data = $branch_result->fetch_assoc();
$branch_id = $branch_data['id'] ?? null;
$branch_stmt->close();

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Generate summary report (PDF or Excel)
    $format = $_POST['format'];
    if ($format === 'pdf') {
        header("Location: generate_summary_pdf.php?" . http_build_query([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'branch_id' => $branch_id
        ]));
    } else {
        header("Location: generate_summary_excel.php?" . http_build_query([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'branch_id' => $branch_id
        ]));
    }
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
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

        /* Report Card Styling */
        .report-card {
            background-color: var(--primary-white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 20px;
        }

        /* Format Selection Styling */
        .format-option {
            cursor: pointer;
            border: 1px solid var(--medium-gray);
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            height: 100%;
        }

        .format-option:hover {
            border-color: var(--accent-green);
            background-color: rgba(233,245,233,0.3);
        }

        .format-option.active {
            border: 2px solid var(--secondary-green);
            background-color: var(--light-green);
        }

        .format-icon {
            font-size: 2rem;
            margin-right: 15px;
            color: var(--dark-text);
        }

        .format-icon.pdf {
            color: #d32f2f;
        }

        .format-icon.excel {
            color: #1e8449;
        }

        .format-content h6 {
            margin-bottom: 5px;
            font-weight: 600;
        }

        .format-content p {
            margin-bottom: 0;
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Button Styling */
        .btn-pdf {
            background-color: #d32f2f;
            color: white;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-pdf:hover {
            background-color: #b71c1c;
            color: white;
            transform: translateY(-2px);
        }

        .btn-excel {
            background-color: #1e8449;
            color: white;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-excel:hover {
            background-color: #186138;
            color: white;
            transform: translateY(-2px);
        }

        /* Form Elements */
        .form-control, .form-select {
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            padding: 8px 12px;
        }

        .date-range-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .date-input {
            flex-grow: 1;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .date-range-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-pdf, .btn-excel {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="content-container">
            <h1>Reports</h1>
            
            <div class="report-card">
                <form method="POST" id="summaryReportForm">
                    <input type="hidden" name="report_type" value="summary">
                    
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <label class="form-label">Date Range</label>
                            <div class="date-range-container">
                                <input type="date" class="form-control date-input" name="start_date" required>
                                <span class="input-group-text">to</span>
                                <input type="date" class="form-control date-input" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Quick Date Range</label>
                            <select class="form-select quick-date-range">
                                <option value="">Custom Range</option>
                                <option value="today">Today</option>
                                <option value="this_week">This Week</option>
                                <option value="this_month">This Month</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5 class="mb-3">Report Type</h5>
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="format-option active" data-format="pdf">
                                        <div class="format-icon pdf">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                        <div class="format-content">
                                            <h6>PDF Summary</h6>
                                            <p>Generates a summary report in PDF format</p>
                                        </div>
                                        <input type="radio" name="format" value="pdf" checked style="display: none;">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="format-option" data-format="excel">
                                        <div class="format-icon excel">
                                            <i class="fas fa-file-excel"></i>
                                        </div>
                                        <div class="format-content">
                                            <h6>Excel Summary</h6>
                                            <p>Generates complete data in Excel format</p>
                                        </div>
                                        <input type="radio" name="format" value="excel" style="display: none;">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-center justify-content-center">
                                    <div class="text-center">
                                        <button type="submit" class="btn-pdf pdf-submit mb-2">
                                            <i class="fas fa-file-pdf me-2"></i> Export to PDF
                                        </button>
                                        <button type="submit" class="btn-excel excel-submit">
                                            <i class="fas fa-file-excel me-2"></i> Export to Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Set default dates (today)
        const today = new Date().toISOString().split('T')[0];
        $('input[name="start_date"], input[name="end_date"]').val(today);
        
        // Quick date range selector
        $('.quick-date-range').change(function() {
            const range = $(this).val();
            const startDate = new Date();
            const endDate = new Date();
            
            switch(range) {
                case 'today':
                    break;
                case 'this_week':
                    startDate.setDate(startDate.getDate() - startDate.getDay());
                    endDate.setDate(endDate.getDate() + (6 - endDate.getDay()));
                    break;
                case 'this_month':
                    startDate.setDate(1);
                    endDate.setMonth(endDate.getMonth() + 1);
                    endDate.setDate(0);
                    break;
                default:
                    return;
            }
            
            $('input[name="start_date"]').val(startDate.toISOString().split('T')[0]);
            $('input[name="end_date"]').val(endDate.toISOString().split('T')[0]);
        });
        
        // Format option selection
        $('.format-option').click(function() {
            $('.format-option').removeClass('active');
            $(this).addClass('active');
            const format = $(this).data('format');
            $(this).find('input[type="radio"]').prop('checked', true);
            
            // Update button visibility
            if (format === 'pdf') {
                $('.pdf-submit').show();
                $('.excel-submit').hide();
            } else {
                $('.pdf-submit').hide();
                $('.excel-submit').show();
            }
        });
        
        // Initialize button visibility
        $('.excel-submit').hide();
        
        // Form submission handling
        $('form').submit(function(e) {
            const startDate = $('input[name="start_date"]').val();
            const endDate = $('input[name="end_date"]').val();
            
            if (!startDate || !endDate) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date Range',
                    text: 'Please select both start and end dates',
                    confirmButtonColor: '#2e8b57'
                });
                return;
            }
            
            if (new Date(startDate) > new Date(endDate)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date Range',
                    text: 'Start date cannot be after end date',
                    confirmButtonColor: '#2e8b57'
                });
                return;
            }
        });
    });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>