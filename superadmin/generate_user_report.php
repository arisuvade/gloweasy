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
$card_type_filter = $_GET['card_type_filter'] ?? 'all';

// Base queries for different membership filters
$members_query = "SELECT 
                    m.id,
                    COALESCE(u.name, m.customer_name) as name, 
                    COALESCE(u.email, m.customer_email) as email, 
                    m.card_type as membership_type,
                    m.membership_code as card_code,
                    m.created_at
                  FROM membership_members m
                  LEFT JOIN users u ON m.user_id = u.id
                  WHERE 1=1";

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
                )";

// Initialize parameters
$params = [];
$types = '';

// Add branch filter if provided
if (!empty($branch_id)) {
    $members_query .= " AND m.branch_id = ?";
    $params[] = $branch_id;
    $types .= 'i';
}

// Add date range filter
if (!empty($start_date) && !empty($end_date)) {
    $members_query .= " AND (m.created_at BETWEEN ? AND ?)";
    $users_query .= " AND (u.created_at BETWEEN ? AND ?)";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}

// Determine which query to use based on filter
if ($membership_filter === 'with_card') {
    $query = $members_query;
    
    // Card type filter when showing members with cards
    if ($card_type_filter !== 'all') {
        $query .= " AND m.card_type = ?";
        $params[] = $card_type_filter;
        $types .= 's';
    }
} elseif ($membership_filter === 'no_card') {
    $query = $users_query;
} elseif ($membership_filter === 'all') {
    // Combine both queries with UNION
    $query = "($members_query) UNION ALL ($users_query)";
}

$query .= " ORDER BY created_at DESC";

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

// Add headers with styling
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
?>