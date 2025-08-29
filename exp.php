<?php
require 'vendor/autoload.php';
include 'sales_data.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sales Report');

// Report Title
if ($dateOpt === 'today') {
    $dateLabel = 'Today (' . date('Y-m-d') . ')';
} elseif ($dateOpt === 'custom') {
    $dateLabel = 'From ' . ($_GET['from_date'] ?? '') . ' to ' . ($_GET['to_date'] ?? '');
} elseif ($dateOpt === 'all') {
    $dateLabel = 'All Records';
} else {
    $dateLabel = 'This Month (' . date('F Y') . ')';
}
$selectedStateLabel = $selectedState ?: 'All States';
$summaryTitle = "Sales Report Summary - $dateLabel | State: $selectedStateLabel";

$sheet->setCellValue('A1', $summaryTitle);
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

// Summary
$summary = [
    ['Total Orders', count($ordersForTable)],
    ['Total Items', $totalItems],
    ['Total Sales', number_format($totalSales, 2)],
    ['Total Paid', number_format($totalPaid, 2)],
    ['Total Refunded', number_format($totalRefunded, 2)],
    ['Total Pending', number_format($totalPending, 2)],
    ['Net Payment', number_format($totalPaid - $totalRefunded, 2)],
];

$row = 3;
foreach ($summary as $item) {
    $sheet->setCellValue("A{$row}", $item[0]);
    $sheet->setCellValue("B{$row}", $item[1]);
    $row++;
}

// Table Header
$startRow = $row + 2;
$headers = ['Order No', 'Date', 'State', 'Total', 'Paid', 'Refunded', 'Net', 'Payment Status', 'Fulfillment'];
$col = 'A';
foreach ($headers as $head) {
    $sheet->setCellValue($col.$startRow, $head);
    $sheet->getStyle($col.$startRow)->getFont()->setBold(true);
    $col++;
}
 
// Order Data
$dataRow = $startRow + 1;
foreach ($ordersForTable as $order) {
    $sheet->setCellValue("A{$dataRow}", $order['name']);
    // $sheet->setCellValue("B{$dataRow}", (new DateTime($order['date']))->format('Y-m-d'));

    $dt = new DateTime($order['date'], new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Kolkata')); // Convert to IST
    $sheet->setCellValue("B{$dataRow}", $dt->format('Y-m-d'));
    $sheet->setCellValue("C{$dataRow}", $order['state']);
    $sheet->setCellValue("D{$dataRow}", $order['currency'] . ' ' . $order['price']);
    $sheet->setCellValue("E{$dataRow}", $order['currency'] . ' ' . $order['paid']);
    $sheet->setCellValue("F{$dataRow}", $order['currency'] . ' ' . $order['refunded']);
    $sheet->setCellValue("G{$dataRow}", $order['currency'] . ' ' . $order['net']);
    $sheet->setCellValue("H{$dataRow}", ucfirst($order['status']));
    $sheet->setCellValue("I{$dataRow}", $order['fulfillment']);
    $dataRow++;
}

// Format Columns
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output file
$filename = "sales_report_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
