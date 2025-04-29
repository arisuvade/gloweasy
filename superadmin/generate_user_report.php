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
$branch_id = $_GET['branch'] ?? '';
$membership_filter = $_GET['membership_filter'] ?? 'with_card';

// Build the query to include card_code
$query = "SELECT u.name, u.email, 
                 MAX(b.has_membership_card) as has_membership_card,
                 MAX(b.membership_code) as card_code
          FROM users u
          LEFT JOIN bookings b ON u.id = b.user_id
          WHERE 1=1";

$params = [];
$types = '';

// Add date range filter
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND (b.booking_date BETWEEN ? AND ? OR u.created_at BETWEEN ? AND ?)";
    $params[] = $start_date;
    $params[] = $end_date;
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ssss';
}

// Add branch filter if provided
if (!empty($branch_id)) {
    $query .= " AND b.branch_id = ?";
    $params[] = $branch_id;
    $types .= 'i';
}

// Add membership filter
if ($membership_filter === 'with_card') {
    $query .= " AND EXISTS (SELECT 1 FROM bookings WHERE user_id = u.id AND has_membership_card = 1)";
} elseif ($membership_filter === 'no_card') {
    $query .= " AND NOT EXISTS (SELECT 1 FROM bookings WHERE user_id = u.id AND has_membership_card = 1)";
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Create Excel file
require_once '../vendor/autoload.php';

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Add simple column headers
$sheet->fromArray(['Name', 'Email', 'Membership', 'Card Code'], null, 'A1');

// Style the headers
$headerStyle = [
    'font' => ['bold' => true]
];
$sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

// Add data starting from row 2
$row = 2;
foreach ($users as $user) {
    $sheet->fromArray([
        $user['name'],
        $user['email'],
        $user['has_membership_card'] ? 'Yes' : 'No',
        $user['card_code'] ?? ''
    ], null, "A$row");
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
?>