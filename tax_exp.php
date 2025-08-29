<?php
require 'vendor/autoload.php';
include 'tax_invoice.php'; // loads $ordersForTable and $pdo

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
$sheet->mergeCells('A1:Q1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

// Table Header
$startRow = 3;
$headers = [
    'Order No', 'Date', 'Customer Name', 'Item Title', 'Qty',
    'Price (₹)', 'Paid (₹)', 'Refunded (₹)', 'Net (₹)',
    'Tax Type', 'Tax Rate (%)', 'Base (₹)', 'CGST (₹)', 'SGST (₹)', 'IGST (₹)', 'Total Tax (₹)'
];
$col = 'A';
foreach ($headers as $head) {
    $sheet->setCellValue($col . $startRow, $head);
    $sheet->getStyle($col . $startRow)->getFont()->setBold(true);
    $col++;
}

$dataRow = $startRow + 1;
foreach ($ordersForTable as $order) {
    $orderDate = $order['date'];
    $taxType = $order['tax_type'];

    $lineItems = $order['line_items'] ?? [['title' => $order['product_title'] ?? $order['name'], 'price' => $order['price'], 'quantity' => 1]];

    foreach ($lineItems as $index => $item) {
        $title = $item['title'];
        $qty = $item['quantity'] ?? 1;
        $itemPrice = isset($item['price']) ? (float) $item['price'] : 0.00;
        $itemPaid = $itemPrice * $qty;
        $itemRefunded = 0; // assumed
        $itemNet = $itemPaid;

        $taxRate = getCollectionTaxRate($pdo, $token, $shop, $title, $orderDate);

        if (($itemPaid + $itemRefunded) == 0 || $itemNet <= 0) {
            $taxAmount = $baseAmount = $cgst = $sgst = $igst = 0;
        } else {
            $baseAmount = round(($itemNet * 100) / ($taxRate + 100), 2);
            $taxAmount = round($itemNet - $baseAmount, 2);
            if ($taxType === 'CGST+SGST') {
                $cgst = round($taxAmount / 2, 2);
                $sgst = round($taxAmount / 2, 2);
                $igst = 0;
            } else {
                $cgst = $sgst = 0;
                $igst = round($taxAmount, 2);
            }
        }

        $sheet->setCellValue("A{$dataRow}", $index === 0 ? $order['name'] : '');
        $dt = new DateTime($order['date'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $sheet->setCellValue("B{$dataRow}", $index === 0 ? $dt->format('Y-m-d') : '');
        $sheet->setCellValue("C{$dataRow}", $index === 0 ? $order['customer_name'] ?? '' : '');
        $sheet->setCellValue("D{$dataRow}", $title);
        $sheet->setCellValue("E{$dataRow}", $qty);
        $sheet->setCellValue("F{$dataRow}", 'INR ' . number_format($itemPrice, 2));
        $sheet->setCellValue("G{$dataRow}", 'INR ' . number_format($itemPaid, 2));
        $sheet->setCellValue("H{$dataRow}", 'INR ' . number_format($itemRefunded, 2));
        $sheet->setCellValue("I{$dataRow}", 'INR ' . number_format($itemNet, 2));
        $sheet->setCellValue("J{$dataRow}", $taxType);
        $sheet->setCellValue("K{$dataRow}", $taxRate . ' %');
        $sheet->setCellValue("L{$dataRow}", number_format($baseAmount, 2));
        $sheet->setCellValue("M{$dataRow}", number_format($cgst, 2));
        $sheet->setCellValue("N{$dataRow}", number_format($sgst, 2));
        $sheet->setCellValue("O{$dataRow}", number_format($igst, 2));
        $sheet->setCellValue("P{$dataRow}", number_format($taxAmount, 2));

        $dataRow++;
    }
}

foreach (range('A', 'P') as $col) { 
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = "sales_report_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

function getCollectionTaxRate($pdo, $token, $shop, $productTitle, $orderDate) {
    $query = ["query" => "
    {
        collections(first: 100) {
            edges {
                node {
                    id
                    title 
                    products(first: 100) {
                        edges {
                            node {
                                title
                            }
                        }
                    }
                }
            }
        }
    }"];

    $response = shopify_gql_call($token, $shop, $query);
    $data = json_decode($response['response'], true);

    $matchingRates = [];

    foreach ($data['data']['collections']['edges'] as $collection) {
        foreach ($collection['node']['products']['edges'] as $product) {
            if (trim(strtolower($product['node']['title'])) === trim(strtolower($productTitle))) {
                $collectionId = str_replace("gid://shopify/Collection/", "", $collection['node']['id']);

                $stmt = $pdo->prepare("
                    SELECT tax_rate 
                    FROM tax_rates 
                    WHERE collection_id = :cid 
                      AND effective_from <= :order_date 
                    ORDER BY effective_from DESC, priority ASC 
                    LIMIT 1
                ");
                $stmt->execute([
                    ':cid' => $collectionId,
                    ':order_date' => $orderDate
                ]);

                if ($row = $stmt->fetch()) {
                    $matchingRates[] = $row['tax_rate'];
                }
            }
        }
    }

    return !empty($matchingRates) ? $matchingRates[0] : 5;
}
