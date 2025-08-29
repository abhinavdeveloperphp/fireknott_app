<?php
// File: tax_invoice.php
include 'functions.php';
include 'db.php';

$token = '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';
$shop = 'cgh6bd-x8.myshopify.com';

function isProductInCollection($productId, $collectionId, $token, $shop) {
    $query = ["query" => "
        {
            product(id: \"gid://shopify/Product/$productId\") {
                collections(first: 5) {
                    edges { 
                        node { id }
                    }
                }
            }
        }
    "];
    $resp = shopify_gql_call($token, $shop, $query);
    $data = json_decode($resp['response'], true);
    foreach ($data['data']['product']['collections']['edges'] as $e) {
        if (str_replace("gid://shopify/Collection/", "", $e['node']['id']) === $collectionId) return true;
    }
    return false;
}

function getOrders($token, $shop, $from, $to, $after = null) {
    $cursorClause = $after ? "after: \"$after\"," : "";
    $qs = "created_at:>=$from created_at:<=$to";
    $query = ["query" => "
    {
        orders(first: 50, $cursorClause query: \"$qs\") {
            pageInfo { hasNextPage endCursor }
            edges {
                node {
                    id
                    name
                    createdAt
                    cancelledAt
                    displayFinancialStatus
                    displayFulfillmentStatus
                    paymentGatewayNames
                    totalPriceSet { presentmentMoney { amount currencyCode } }
                    totalRefundedSet { presentmentMoney { amount } }
                    transactions { id amount kind status }
                    refunds {
                        transactions(first: 10) {
                            edges {
                                node {
                                    amount kind status
                                }
                            }
                        }
                    }
                    shippingAddress { province provinceCode }
                    customer { firstName lastName email }
                    lineItems(first: 10) {
                        edges {
                            node {
                                quantity
                                title
                                originalUnitPrice
                                product { id title }
                            }
                        }
                    }
                }
            }
        }
    }
    "];
    $resp = shopify_gql_call($token, $shop, $query);

    // Check if 'data' exists
// if (!isset($resp['data']) || !isset($resp['data']['orders'])) {
//     echo "<pre>";
//     echo "Shopify GraphQL API Error:\n";
//     print_r($resp);
//     exit; // stop to inspect
// }
    return json_decode($resp['response'], true);
}

function determineTaxType($provinceCode) {
    $stateMap = [
        'AP' => '37', 'AR' => '12', 'AS' => '18', 'BR' => '10', 'CH' => '04',
        'CT' => '22', 'DL' => '07', 'GA' => '30', 'GJ' => '24', 'HR' => '06',
        'HP' => '02', 'JH' => '20', 'JK' => '01', 'KA' => '29', 'KL' => '32',
        'LA' => '38', 'LD' => '31', 'MH' => '27', 'ML' => '17', 'MN' => '14',
        'MP' => '23', 'MZ' => '15', 'NL' => '13', 'OD' => '21', 'PB' => '03',
        'PY' => '34', 'RJ' => '08', 'SK' => '11', 'TN' => '33', 'TS' => '36',
        'TR' => '16', 'UP' => '09', 'UK' => '05', 'WB' => '19'
    ];
    $sellerStateCode = '03'; // Punjab
    $customerStateCode = $stateMap[$provinceCode] ?? 'XX';
    return ($customerStateCode === $sellerStateCode) ? 'CGST+SGST' : 'IGST';
}

$dateOpt = $_GET['date_filter_option'] ?? 'today';
$collectionId = $_GET['collection_id'] ?? '';
$selectedState = $_GET['state'] ?? '';




$selectedPaymentStatus = $_GET['payment_status'] ?? '';
$selectedFulfillmentStatus = $_GET['fulfillment_status'] ?? '';

if ($dateOpt === 'today') {
    $from = gmdate('Y-m-d') . "T00:00:00Z";
    $to = gmdate('Y-m-d') . "T23:59:59Z";
} elseif ($dateOpt === 'custom') {
    $fromR = $_GET['from_date'] ?? '';
    $toR = $_GET['to_date'] ?? '';
    if (!$fromR || !$toR) die("Please specify dates.");
    $from = date('Y-m-d', strtotime($fromR)) . "T00:00:00Z";
    $to = date('Y-m-d', strtotime($toR)) . "T23:59:59Z";
} elseif ($dateOpt === 'all') {
    $from = "2000-01-01T00:00:00Z";
    $to = gmdate('Y-m-d') . "T23:59:59Z";
} else {
    $from = gmdate('Y-m-01') . "T00:00:00Z";
    $to = gmdate('Y-m-t') . "T23:59:59Z";
}

$ordersForTable = [];
$cursor = null;

do {
    $result = getOrders($token, $shop, $from, $to, $cursor);
    $orders = $result['data']['orders']['edges'] ?? [];

    foreach ($orders as $edge) {
        $o = $edge['node'];

 

        $state = $o['shippingAddress']['province'] ?? 'Unknown';
        if ($selectedState && $selectedState !== $state) continue;




        if (
            $selectedPaymentStatus &&
            strtolower($o['displayFinancialStatus'] ?? '') !== strtolower($selectedPaymentStatus)
        ) continue;
        
        if (
            $selectedFulfillmentStatus &&
            strtolower($o['displayFulfillmentStatus'] ?? '') !== strtolower($selectedFulfillmentStatus)
        ) continue;
        




        if ($collectionId) {
            $found = false;
            foreach ($o['lineItems']['edges'] as $li) {
                $pid = str_replace("gid://shopify/Product/", "", $li['node']['product']['id']);
                if (isProductInCollection($pid, $collectionId, $token, $shop)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) continue; 
        }

        $price = (float)($o['totalPriceSet']['presentmentMoney']['amount'] ?? 0);
        $currency = $o['totalPriceSet']['presentmentMoney']['currencyCode'] ?? 'INR';

        $paid = 0;
        foreach ($o['transactions'] ?? [] as $txn) {
            if ($txn['kind'] === 'SALE' && $txn['status'] === 'SUCCESS') {
                $paid += (float)$txn['amount'];
            }
        }

        $refunded = 0;
        foreach ($o['refunds'] ?? [] as $refund) {
            foreach ($refund['transactions']['edges'] ?? [] as $txnEdge) {
                $refunded += (float)$txnEdge['node']['amount'];
            }
        }

        $net = $paid - $refunded;

        $customer = $o['customer'] ?? ['firstName' => 'Guest', 'lastName' => '', 'email' => ''];
        $customerName = trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? ''));
        $customerEmail = $customer['email'] ?? '';

        $taxType = determineTaxType($o['shippingAddress']['provinceCode'] ?? '');


//         echo "<pre>";
// print_r($order['line_items']);
// exit;


        $ordersForTable[] = [
            'name' => $o['name'],
            'date' => $o['createdAt'],
            'state' => $state,
            'status' => ucfirst($o['displayFinancialStatus']),
            'fulfillment' => $o['displayFulfillmentStatus'],
            'price' => number_format($price, 2),
            'paid' => number_format($paid, 2),
            'refunded' => number_format($refunded, 2),
            'net' => number_format($net, 2),
            'currency' => $currency,
            'cancelled' => !empty($o['cancelledAt']),
            'orderId' => str_replace("gid://shopify/Order/", "", $o['id']),
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'product_title' => $o['lineItems']['edges'][0]['node']['title'] ?? '',
            'tax_type' => $taxType,
        
'line_items' => array_map(fn($li) => [
    'title' => $li['node']['title'] ?? '',
    'quantity' => $li['node']['quantity'] ?? 1,
    'price' => $li['node']['originalUnitPrice'] ?? 0,
    'product_id' => str_replace("gid://shopify/Product/", "", $li['node']['product']['id']),
], $o['lineItems']['edges'] ?? []),




        ];
        
    }

    $cursor = $result['data']['orders']['pageInfo']['hasNextPage']
        ? $result['data']['orders']['pageInfo']['endCursor']
        : null;
} while ($cursor);
