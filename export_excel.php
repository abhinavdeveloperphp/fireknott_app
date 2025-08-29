<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Dummy data: Replace this with actual $ordersForTable data from your main file
$orders = json_decode($_POST['data'], true); // Receive JSON from POST

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headings
$sheet->fromArray(['Order Name', 'Status', 'Date', 'State', 'Amount', 'Currency'], null, 'A1');

// Add order data
$row = 2;
foreach ($orders as $order) {
    $sheet->fromArray([
        $order['name'],
        $order['status'],
        $order['date'],
        $order['state'],
        $order['amount'],
        $order['currency']
    ], null, "A$row");
    $row++;
}

// Output Excel file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="shopify_orders.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
