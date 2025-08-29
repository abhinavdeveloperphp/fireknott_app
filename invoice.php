<?php
require 'vendor/autoload.php';
include 'functions.php';
include 'db.php';

use Dompdf\Dompdf;
use Dompdf\Options; 

$token = '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';
$shop = 'cgh6bd-x8.myshopify.com';

function getOrderDetails($token, $shop, $orderId) {
    $query = [
        "query" => "
        {
            order(id: \"gid://shopify/Order/$orderId\") {
                id
                name
                createdAt
                totalPriceSet {
                    presentmentMoney {
                        amount
                        currencyCode
                    }
                }
                         totalRefundedSet {
            presentmentMoney {
                amount
            }
        }

refunds {
  refundLineItems(first: 10) {
    edges {
      node {
        quantity
        subtotalSet {
          presentmentMoney {
            amount
          }
        }
        lineItem {
          title
        }
      }
    }
  }
}

                lineItems(first: 10) {
                    edges {
                        node {
                            title
                            quantity
                            variant {
                                price
                                product {
                                    productType
                                }
                            }
                        }
                    }
                }
                shippingAddress {
                    provinceCode
                }
                billingAddress {
                    name
                    address1
                    city
                    province
                    zip
                    country
                }
                customer {
                    firstName
                    lastName
                    email
                }
            }
        }"
    ];

    $response = shopify_gql_call($token, $shop, $query);
    if (!isset($response['response'])) {
        return ['error' => 'Invalid response from Shopify'];
    }
    $data = json_decode($response['response'], true);
    if (isset($data['errors'])) {
        return ['error' => $data['errors']];
    }
    return $data;
}

function getCollectionTaxRate($pdo, $token, $shop, $productTitle, $orderDate) {
    $query = [
        "query" => "
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
        }"
    ];
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



$orderId = $_GET['order_id'] ?? null;
if (!$orderId) die("Order ID is required.");

$orderData = getOrderDetails($token, $shop, $orderId);
if (isset($orderData['error'])) die("Error: " . json_encode($orderData['error']));



$order = $orderData['data']['order'];
$customer = $order['customer'] ?? ['firstName' => 'Guest', 'lastName' => '', 'email' => 'N/A'];
$currency = $order['totalPriceSet']['presentmentMoney']['currencyCode'];
$totalPrice = $order['totalPriceSet']['presentmentMoney']['amount'];

$refundedBlock = "";

$stateMap = [
    'AP' => '37', 'AR' => '12', 'AS' => '18', 'BR' => '10', 'CH' => '04',
    'CT' => '22', 'DL' => '07', 'GA' => '30', 'GJ' => '24', 'HR' => '06',
    'HP' => '02', 'JH' => '20', 'JK' => '01', 'KA' => '29', 'KL' => '32',
    'LA' => '38', 'LD' => '31', 'MH' => '27', 'ML' => '17', 'MN' => '14',
    'MP' => '23', 'MZ' => '15', 'NL' => '13', 'OD' => '21', 'PB' => '03',
    'PY' => '34', 'RJ' => '08', 'SK' => '11', 'TN' => '33', 'TS' => '36',
    'TR' => '16', 'UP' => '09', 'UK' => '05', 'WB' => '19'
];

$sellerStateCode = '03';
$provinceCode = $order['shippingAddress']['provinceCode'] ?? null;
$customerStateCode = $stateMap[$provinceCode] ?? 'XX';
$taxType = ($customerStateCode === $sellerStateCode) ? 'CGST + SGST' : 'IGST';

$itemsHTML = "";
$counter = 1;
$totalTaxAmount = 0;
foreach ($order['lineItems']['edges'] as $item) {
    $title = $item['node']['title'];
    $qty = $item['node']['quantity'];



   


    $price = $item['node']['variant']['price'];
    // $taxRate = getCollectionTaxRate($pdo, $token, $shop, $title);
    $taxRate = getCollectionTaxRate($pdo, $token, $shop, $title, $order['createdAt']);


 
    

    $amt = isset($order['totalPriceSet']['presentmentMoney']['amount']) ? (float)$order['totalPriceSet']['presentmentMoney']['amount'] : 0;
$refunded = isset($order['totalRefundedSet']['presentmentMoney']['amount']) ? (float)$order['totalRefundedSet']['presentmentMoney']['amount'] : 0;

$paid = $amt; // Total paid by customer
$netAmount = $paid - $refunded;

    
    // $net = $price * $qty;
    // $taxAmount = $netAmount - round((($netAmount * 100) / ($taxRate + 100)), 2);
    // $baseAmount = $net - $taxAmount;

    // $baseAmount2 = $netAmount - $taxAmount;
    // $totalTaxAmount += $taxAmount;





    $net = $price * $qty;

// Proportional allocation logic
$proportion = $net / $amt; // This item’s proportion of the total
$itemPaid = $proportion * $netAmount; // Realistic share of paid amount
$itemTaxAmount = $itemPaid - round(($itemPaid * 100) / ($taxRate + 100), 2);
$itemBaseAmount = $itemPaid - $itemTaxAmount;

$totalTaxAmount += $itemTaxAmount;

    
    // // You must define paid/refunded here, or get from API
    // $paid = $net;        // You can override this with real paid amount
    // //$refunded = 0;       // You can override with real refunded
    // $netAmount = $paid - $refunded;
    
    // if ($taxType === 'CGST + SGST') {
    //     $halfTax = number_format($taxAmount / 2, 2);
    //     $itemsHTML .= "<tr><td>{$counter}</td><td>610910</td><td>{$title}</td><td>" . number_format($total, 2) . "</td><td>{$qty}</td><td>" . number_format($total * $qty, 2) . "</td><td>{$taxRate}%</td><td>Na</td><td>Rs. {$halfTax}</td><td>Rs. {$halfTax}</td><td>" . number_format($taxAmount, 2) . "</td><td>" . number_format($price, 2) . "</td></tr>";
    // } else {
    //     $itemsHTML .= "<tr><td>{$counter}</td><td>610910</td><td>{$title}</td><td>" . number_format($total, 2) . "</td><td>{$qty}</td><td>" . number_format($total * $qty, 2) . "</td><td>{$taxRate}%</td><td>" . number_format($taxAmount, 2) . "</td><td>Na</td><td>Na</td><td>" . number_format($totalTaxAmount, 2) . "</td><td>" . number_format($price, 2) . "</td></tr>";
    // }

    // if ($taxType === 'CGST + SGST') {
    //     $halfTax = number_format($taxAmount / 2, 2);
    //     $itemsHTML .= "<tr><td>{$counter}</td><td>610910</td><td>{$title}</td><td>" . number_format($net, 2) . "</td><td>{$qty}</td><td>" . number_format($baseAmount2 * $qty, 2) . "</td><td>{$taxRate}%</td><td>Na</td><td>Rs. {$halfTax}</td><td>Rs. {$halfTax}</td><td>" . number_format($taxAmount, 2) . "</td><td>" . number_format($netAmount, 2) . "</td></tr>";
    // } else {
    //     $itemsHTML .= "<tr><td>{$counter}</td><td>610910</td><td>{$title}</td><td>" . number_format($net, 2) . "</td><td>{$qty}</td><td>" . number_format($baseAmount2 * $qty, 2) . "</td><td>{$taxRate}%</td><td>" . number_format($taxAmount, 2) . "</td><td>Na</td><td>Na</td><td>" . number_format($totalTaxAmount, 2) . "</td><td>" . number_format($netAmount, 2) . "</td></tr>";
    // }


    if ($taxType === 'CGST + SGST') {
        $halfTax = number_format($itemTaxAmount / 2, 2);
        $itemsHTML .= "<tr><td>{$counter}</td><td>610910</td><td>{$title}</td><td>" . number_format($net, 2) . "</td><td>{$qty}</td><td>" . number_format($itemBaseAmount, 2) . "</td><td>{$taxRate}%</td><td>Na</td><td>Rs. {$halfTax}</td><td>Rs. {$halfTax}</td><td>" . number_format($itemTaxAmount, 2) . "</td><td>" . number_format($itemPaid, 2) . "</td></tr>"; 
    } else {
        $itemsHTML .= "<tr><td>{$counter}</td><td>610910</td><td>{$title}</td><td>" . number_format($net, 2) . "</td><td>{$qty}</td><td>" . number_format($itemBaseAmount, 2) . "</td><td>{$taxRate}%</td><td>" . number_format($itemTaxAmount, 2) . "</td><td>Na</td><td>Na</td><td>" . number_format($itemTaxAmount, 2) . "</td><td>" . number_format($itemPaid, 2) . "</td></tr>";
    }
    





    
    $counter++;
}

$refundedBlock = "";

if (!empty($order['refunds'])) {
    foreach ($order['refunds'] as $refund) {
        if (!empty($refund['refundLineItems']['edges'])) {

            // Add refunded header once
            if ($refundedBlock === "") {
                $refundedBlock .= "<tr><td colspan='12' style='font-weight:bold; background:#f0f0f0;text-align:center;'>Refunded Items</td></tr>";
                $refundedBlock .= "<tr>
                    <th colspan='4'>Name</th>
                    <th colspan='4'>Qty</th>
                     <th colspan='4'>Refund</th>
                  
                </tr>";
            }

            $item_refund_count=count($refund['refundLineItems']['edges']);
            $item_refund=$refunded/$item_refund_count;
            foreach ($refund['refundLineItems']['edges'] as $rItem) {
                $title = $rItem['node']['lineItem']['title'];
                $qty = $rItem['node']['quantity'];
                $refundedAmount = isset($rItem['node']['subtotalSet']['presentmentMoney']['amount']) 
                    ? (float)$rItem['node']['subtotalSet']['presentmentMoney']['amount'] 
                    : 0.00;
                $price = $qty > 0 ? $refundedAmount / $qty : 0;

                $refundedBlock .= "<tr>
                <td colspan='4' style='text-align:center;'>{$title}</td>
                <td colspan='4' style='text-align:center;'>{$qty}</td>
                <td colspan='4' style='text-align:center;'>" . number_format($item_refund, 2) . "</td>
            </tr>";
            
            }


            $refundedBlock .= "<tr>
            <td colspan='6' style='text-align:center;'></td>
            <td colspan='6' style='text-align:center;'> Total Refund - {$refunded}</td>
          
        </tr>";
        }
    }
} 


$itemsHTML .= $refundedBlock;

$logoPath = __DIR__ . '/fireknott_logo.png';
$logoSrc = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : 'https://fireknott.com/cdn/shop/files/Fireknott-Logo-Black-ori_360x.webp?v=1729241431';

$invoiceDate = date("d.m.Y", strtotime($order['createdAt']));
$invoiceNumber = "INV-$orderId";

$billing = $order['billingAddress'];
$customBillingAddress = "{$billing['name']}<br>{$billing['address1']}<br>{$billing['city']}, {$billing['province']} - {$billing['zip']}<br>{$billing['country']}";

$html = "
<!DOCTYPE html>
<html> 
<head>
    <style>
        body { font-family: Arial; font-size: 12px; padding: 40px; color: #000; }
        .header-logo { width: 120px; }
        .invoice-title { font-weight: bold; font-size: 16px; text-align: right; }
        .sub-title { font-size: 11px; text-align: right; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 6px; font-size: 11px; vertical-align: top; }
        .no-border td { border: none; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .section-title { font-size: 13px; font-weight: bold; } 
        .footer-table td { border: 1px solid black; padding: 6px; }
    </style>
</head>
<body>
    <div style='display: flex; justify-content: space-between; align-items: center;'>
        <img src='{$logoSrc}' class='header-logo' alt='Logo'>
        <div>
            <div class='invoice-title'>Tax Invoice/Bill of Supply/Cash Memo</div>
            <div class='sub-title'>(Original for Recipient)</div>
        </div>
    </div>

    <table> 
        <tr>
            <td><strong>Sold By:</strong><br>Fireknott Pvt. Ltd.<br>Punjab, India<br>GSTIN: 03AAFCF8802P1ZD<br>PAN: AAFCF8802P</td>
            <td><strong>Billing Address:</strong><br>{$customBillingAddress}</td>
        </tr>
    </table>

    <table class='no-border'>
        <tr><td>Order Number: <strong>{$order['name']}</strong></td><td>Invoice Number: <strong>{$invoiceNumber}</strong></td></tr>
        <tr><td>Order Date: <strong>{$invoiceDate}</strong></td><td>Invoice Date: <strong>{$invoiceDate}</strong></td></tr>
        <tr><td colspan='2'>Customer Email: <strong>{$customer['email']}</strong></td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                  <th>HSN Code</th>
                <th>Item</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Net</th>
                <th>Tax %</th>
                <th>IGST</th>
                  <th>CGST</th>
                    <th>SGST</th>
                <th>Tax Amt</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            {$itemsHTML}
        </tbody>
    </table>

   
</body>
</html>
";



$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("invoice_$orderId.pdf", ["Attachment" => false]);
