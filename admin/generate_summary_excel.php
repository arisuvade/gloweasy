<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../includes/auth/login.php");
    exit();
}

// Get the admin's branch information
$admin_id = $_SESSION['admin_id'];
$branch_name = $_SESSION['branch'] ?? '';

// Get filter parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Get the branch ID for this admin
$branch_stmt = $conn->prepare("SELECT b.id FROM branches b 
                             JOIN admins a ON b.name = a.branch 
                             WHERE a.id = ?");
$branch_stmt->bind_param("i", $admin_id);
$branch_stmt->execute();
$branch_result = $branch_stmt->get_result();
$branch_data = $branch_result->fetch_assoc();
$branch_id = $branch_data['id'] ?? null;
$branch_stmt->close();

if (!$branch_id) {
    die("Error: Could not determine branch for this admin");
}

// 1. Total Income Calculation
$total_income_query = "SELECT 
    SUM(CASE WHEN has_membership_card = 1 THEN vip_elite_amount ELSE total_amount END) as total_income
    FROM bookings 
    WHERE status = 'Completed' 
    AND branch_id = ?
    AND booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($total_income_query);
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$total_income_result = $stmt->get_result();
$total_income_row = $total_income_result->fetch_assoc();
$total_income = $total_income_row['total_income'] ?? 0;

// 2. Income By Service (for this branch only)
$service_income_query = "SELECT 
    s.name as service_name,
    SUM(CASE WHEN bk.has_membership_card = 1 THEN bk.vip_elite_amount ELSE bk.total_amount END) as service_income
    FROM bookings bk
    JOIN services s ON bk.service_id = s.id
    WHERE bk.status = 'Completed' 
    AND bk.branch_id = ?
    AND bk.booking_date BETWEEN ? AND ?
    GROUP BY s.name";
$stmt = $conn->prepare($service_income_query);
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$service_income_result = $stmt->get_result();
$service_incomes = [];
while ($row = $service_income_result->fetch_assoc()) {
    $service_incomes[$row['service_name']] = $row['service_income'];
}

// 3. Members vs Non-Members
$membership_income_query = "SELECT 
    SUM(CASE WHEN has_membership_card = 1 THEN vip_elite_amount ELSE 0 END) as members_income,
    SUM(CASE WHEN has_membership_card = 0 THEN total_amount ELSE 0 END) as non_members_income
    FROM bookings 
    WHERE status = 'Completed' 
    AND branch_id = ?
    AND booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($membership_income_query);
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$membership_income_result = $stmt->get_result();
$membership_income_row = $membership_income_result->fetch_assoc();
$members_income = $membership_income_row['members_income'] ?? 0;
$non_members_income = $membership_income_row['non_members_income'] ?? 0;

// 4. Bookings Overview
$bookings_overview_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
    FROM bookings 
    WHERE branch_id = ?
    AND booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($bookings_overview_query);
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$bookings_overview_result = $stmt->get_result();
$bookings_overview_row = $bookings_overview_result->fetch_assoc();
$total_bookings = $bookings_overview_row['total_bookings'] ?? 0;
$completed_bookings = $bookings_overview_row['completed_bookings'] ?? 0;
$cancelled_bookings = $bookings_overview_row['cancelled_bookings'] ?? 0;

// 5. Most Booked Day
$most_booked_day_query = "SELECT 
    DAYNAME(booking_date) as day_name,
    COUNT(*) as booking_count
    FROM bookings
    WHERE branch_id = ?
    AND booking_date BETWEEN ? AND ?
    GROUP BY DAYNAME(booking_date)
    ORDER BY booking_count DESC
    LIMIT 1";
$stmt = $conn->prepare($most_booked_day_query);
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$most_booked_day_result = $stmt->get_result();
$most_booked_day_row = $most_booked_day_result->fetch_assoc();
$most_booked_day = $most_booked_day_row['day_name'] ?? 'N/A';

// 6. Most Booked Services
$most_booked_services_query = "SELECT 
    s.name as service_name,
    COUNT(*) as booking_count
    FROM bookings bk
    JOIN services s ON bk.service_id = s.id
    WHERE bk.branch_id = ?
    AND bk.booking_date BETWEEN ? AND ?
    GROUP BY s.name
    ORDER BY booking_count DESC";
$stmt = $conn->prepare($most_booked_services_query);
$stmt->bind_param("iss", $branch_id, $start_date, $end_date);
$stmt->execute();
$most_booked_services_result = $stmt->get_result();
$most_booked_services = [];
while ($row = $most_booked_services_result->fetch_assoc()) {
    $most_booked_services[$row['service_name']] = $row['booking_count'];
}

// Create Excel file
require_once '../vendor/autoload.php';

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("BaliSpa System")
    ->setTitle("Income Summary Report - " . $branch_name)
    ->setDescription("Summary report of income and bookings for " . $branch_name);

// Header
$sheet->setCellValue('A1', 'Bali Ayurveda Spa - Summary Report');
$sheet->mergeCells('A1:B1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$sheet->setCellValue('A3', 'Date Range: ' . $start_date . ' to ' . $end_date);
$sheet->mergeCells('A3:B3');

// Income Summary
$sheet->setCellValue('A5', 'Income Summary');
$sheet->mergeCells('A5:B5');
$sheet->getStyle('A5')->getFont()->setBold(true);

$sheet->setCellValue('A6', 'Total Income:');
$sheet->setCellValue('B6', '₱' . number_format($total_income, 2));
$sheet->getStyle('B6')->getFont()->setBold(true);

// By Service
$sheet->setCellValue('A8', 'By Service:');
$sheet->mergeCells('A8:B8');
$sheet->getStyle('A8')->getFont()->setBold(true);

$row = 9;
foreach ($service_incomes as $service_name => $income) {
    $sheet->setCellValue('A' . $row, $service_name);
    $sheet->setCellValue('B' . $row, '₱' . number_format($income, 2));
    $row++;
}

// Members vs Non-Members
$sheet->setCellValue('A' . ($row + 1), 'Members vs Non-Members:');
$sheet->mergeCells('A' . ($row + 1) . ':B' . ($row + 1));
$sheet->getStyle('A' . ($row + 1))->getFont()->setBold(true);

$sheet->setCellValue('A' . ($row + 2), 'Members');
$sheet->setCellValue('B' . ($row + 2), '₱' . number_format($members_income, 2));

$sheet->setCellValue('A' . ($row + 3), 'Non-Members');
$sheet->setCellValue('B' . ($row + 3), '₱' . number_format($non_members_income, 2));

$row += 4;

// Bookings Overview
$sheet->setCellValue('A' . ($row + 1), 'Bookings Overview');
$sheet->mergeCells('A' . ($row + 1) . ':B' . ($row + 1));
$sheet->getStyle('A' . ($row + 1))->getFont()->setBold(true);

$sheet->setCellValue('A' . ($row + 2), 'Total Bookings');
$sheet->setCellValue('B' . ($row + 2), number_format($total_bookings));

$sheet->setCellValue('A' . ($row + 3), 'Completed');
$sheet->setCellValue('B' . ($row + 3), number_format($completed_bookings));

$sheet->setCellValue('A' . ($row + 4), 'Cancelled');
$sheet->setCellValue('B' . ($row + 4), number_format($cancelled_bookings));

$sheet->setCellValue('A' . ($row + 5), 'Most Booked Day');
$sheet->setCellValue('B' . ($row + 5), $most_booked_day);

$row += 6;

// Most Booked Services
$sheet->setCellValue('A' . ($row + 1), 'Most Booked Services:');
$sheet->mergeCells('A' . ($row + 1) . ':B' . ($row + 1));
$sheet->getStyle('A' . ($row + 1))->getFont()->setBold(true);

$row += 2;
foreach ($most_booked_services as $service_name => $count) {
    $sheet->setCellValue('A' . $row, $service_name);
    $sheet->setCellValue('B' . $row, number_format($count));
    $row++;
}

// Footer
$sheet->setCellValue('A' . ($row + 2), 'Report generated on ' . date('F j, Y') . ' at ' . date('g:i A'));
$sheet->mergeCells('A' . ($row + 2) . ':B' . ($row + 2));
$sheet->getStyle('A' . ($row + 2))->getFont()->setItalic(true);

// Style the amounts
$amountStyle = [
    'font' => ['color' => ['rgb' => '2a6049']]
];
$sheet->getStyle('B6:B6')->applyFromArray($amountStyle);

// Auto size columns
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(20);

// Set file name and headers
$filename = 'summary_report_' . $branch_name . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Save to output
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit();
?>