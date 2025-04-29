<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

// Get filter parameters from URL
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// 1. Total Income Calculation
$total_income_query = "SELECT 
    SUM(CASE WHEN has_membership_card = 1 THEN vip_elite_amount ELSE total_amount END) as total_income
    FROM bookings 
    WHERE status = 'Completed' 
    AND booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($total_income_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_income_result = $stmt->get_result();
$total_income_row = $total_income_result->fetch_assoc();
$total_income = $total_income_row['total_income'] ?? 0;

// 2. Income By Branch
$branch_income_query = "SELECT 
    b.name as branch_name,
    SUM(CASE WHEN bk.has_membership_card = 1 THEN bk.vip_elite_amount ELSE bk.total_amount END) as branch_income
    FROM bookings bk
    JOIN branches b ON bk.branch_id = b.id
    WHERE bk.status = 'Completed' 
    AND bk.booking_date BETWEEN ? AND ?
    GROUP BY b.name";
$stmt = $conn->prepare($branch_income_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$branch_income_result = $stmt->get_result();
$branch_incomes = [];
while ($row = $branch_income_result->fetch_assoc()) {
    $branch_incomes[$row['branch_name']] = $row['branch_income'];
}

// 3. Income By Service
$service_income_query = "SELECT 
    s.name as service_name,
    SUM(CASE WHEN bk.has_membership_card = 1 THEN bk.vip_elite_amount ELSE bk.total_amount END) as service_income
    FROM bookings bk
    JOIN services s ON bk.service_id = s.id
    WHERE bk.status = 'Completed' 
    AND bk.booking_date BETWEEN ? AND ?
    GROUP BY s.name";
$stmt = $conn->prepare($service_income_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$service_income_result = $stmt->get_result();
$service_incomes = [];
while ($row = $service_income_result->fetch_assoc()) {
    $service_incomes[$row['service_name']] = $row['service_income'];
}

// 4. Members vs Non-Members
$membership_income_query = "SELECT 
    SUM(CASE WHEN has_membership_card = 1 THEN vip_elite_amount ELSE 0 END) as members_income,
    SUM(CASE WHEN has_membership_card = 0 THEN total_amount ELSE 0 END) as non_members_income
    FROM bookings 
    WHERE status = 'Completed' 
    AND booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($membership_income_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$membership_income_result = $stmt->get_result();
$membership_income_row = $membership_income_result->fetch_assoc();
$members_income = $membership_income_row['members_income'] ?? 0;
$non_members_income = $membership_income_row['non_members_income'] ?? 0;

// 5. Bookings Overview
$bookings_overview_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
    FROM bookings 
    WHERE booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($bookings_overview_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$bookings_overview_result = $stmt->get_result();
$bookings_overview_row = $bookings_overview_result->fetch_assoc();
$total_bookings = $bookings_overview_row['total_bookings'] ?? 0;
$completed_bookings = $bookings_overview_row['completed_bookings'] ?? 0;
$cancelled_bookings = $bookings_overview_row['cancelled_bookings'] ?? 0;

// 6. Most Booked Day
$most_booked_day_query = "SELECT 
    DAYNAME(booking_date) as day_name,
    COUNT(*) as booking_count
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
    GROUP BY DAYNAME(booking_date)
    ORDER BY booking_count DESC
    LIMIT 1";
$stmt = $conn->prepare($most_booked_day_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$most_booked_day_result = $stmt->get_result();
$most_booked_day_row = $most_booked_day_result->fetch_assoc();
$most_booked_day = $most_booked_day_row['day_name'] ?? 'N/A';

// 7. Most Booked Services
$most_booked_services_query = "SELECT 
    s.name as service_name,
    COUNT(*) as booking_count
    FROM bookings bk
    JOIN services s ON bk.service_id = s.id
    WHERE bk.booking_date BETWEEN ? AND ?
    GROUP BY s.name
    ORDER BY booking_count DESC";
$stmt = $conn->prepare($most_booked_services_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$most_booked_services_result = $stmt->get_result();
$most_booked_services = [];
while ($row = $most_booked_services_result->fetch_assoc()) {
    $most_booked_services[$row['service_name']] = $row['booking_count'];
}

// Create PDF
require_once '../vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_header' => 10,
    'margin_footer' => 10
]);

// Start building HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Bali Ayurveda Spa - Summary Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #2e8b57; text-align: center; margin-bottom: 5px; }
        .report-title { text-align: center; font-size: 18px; margin-bottom: 20px; }
        .section-title { 
            color: #2e8b57; 
            font-size: 16px; 
            margin-top: 20px; 
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #2e8b57;
        }
        .summary-item { 
            margin-bottom: 8px;
            font-size: 14px;
        }
        .branch-item, .service-item {
            margin-left: 0;
            margin-bottom: 5px;
        }
        .amount { 
            font-weight: bold;
            color: #2a6049;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Bali Ayurveda Spa</h1>
    <div class="report-title">Summary Report</div>
    
    <div class="summary-item"><strong>Total Income:</strong> <span class="amount">₱' . number_format($total_income, 2) . '</span></div>
    <div class="summary-item"><strong>Date Range:</strong> ' . $start_date . ' to ' . $end_date . '</div>';
    
// By Branch section
$html .= '<div class="section-title">By Branch:</div>';
foreach ($branch_incomes as $branch_name => $income) {
    $html .= '<div class="branch-item">' . htmlspecialchars($branch_name) . ': <span class="amount">₱' . number_format($income, 2) . '</span></div>';
}

// By Service section
$html .= '<div class="section-title">By Service:</div>';
foreach ($service_incomes as $service_name => $income) {
    $html .= '<div class="service-item">' . htmlspecialchars($service_name) . ': <span class="amount">₱' . number_format($income, 2) . '</span></div>';
}

// Members vs Non-Members section
$html .= '<div class="section-title">Members vs Non-Members:</div>
    <div class="summary-item">Members: <span class="amount">₱' . number_format($members_income, 2) . '</span></div>
    <div class="summary-item">Non-Members: <span class="amount">₱' . number_format($non_members_income, 2) . '</span></div>';
    
// Bookings Overview section
$html .= '<div class="section-title">Bookings Overview</div>
    <div class="summary-item"><strong>Total Bookings:</strong> ' . number_format($total_bookings) . '</div>
    <div class="summary-item">Completed: ' . number_format($completed_bookings) . '</div>
    <div class="summary-item">Cancelled: ' . number_format($cancelled_bookings) . '</div>
    <div class="summary-item">Most Booked Day: ' . htmlspecialchars($most_booked_day) . '</div>';
    
// Most Booked Services section
$html .= '<div class="section-title">Most Booked Services:</div>';
foreach ($most_booked_services as $service_name => $count) {
    $html .= '<div class="service-item">' . htmlspecialchars($service_name) . ' – ' . number_format($count) . '</div>';
}

// Footer
$html .= '<div class="footer">
        Report generated on ' . date('F j, Y') . ' at ' . date('g:i A') . '
    </div>
</body>
</html>';

$mpdf->WriteHTML($html);

// Output PDF
$filename = 'summary_report_' . date('Ymd_His') . '.pdf';
$mpdf->Output($filename, 'D');
exit();
?>