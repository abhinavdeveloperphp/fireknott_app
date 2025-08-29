<?php
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
                    transactions {
                        id amount kind status
                    }
                    refunds {
                        transactions(first: 10) {
                            edges {
                                node {
                                    amount kind status
                                }
                            }
                        }
                    }
                    shippingAddress { province }
                    lineItems(first: 10) {
                        edges {
                            node {
                                quantity
                                product { id }
                            }
                        }
                    }
                }
            }
        }
    }
    "];
    $resp = shopify_gql_call($token, $shop, $query);
    return json_decode($resp['response'], true);
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

$totalSales = $totalPaid = $totalPending = $totalRefunded = $totalItems = 0;
$ordersForTable = [];
$states = [];
$cursor = null;

do {
    $result = getOrders($token, $shop, $from, $to, $cursor);
    $orders = $result['data']['orders']['edges'] ?? [];

    foreach ($orders as $edge) {
        $o = $edge['node'];
        $state = $o['shippingAddress']['province'] ?? 'Unknown';
        if (!in_array($state, $states)) $states[] = $state;
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

        // Total paid from successful SALE transactions
        $paid = 0;
        foreach ($o['transactions'] ?? [] as $txn) {
            if ($txn['kind'] === 'SALE' && $txn['status'] === 'SUCCESS') {
                $paid += (float)$txn['amount'];
            }
        }

        // Refunded amount from nested refunds.transactions
        $refunded = 0;
        foreach ($o['refunds'] ?? [] as $refund) {
            foreach ($refund['transactions']['edges'] ?? [] as $txnEdge) {
                $refunded += (float)$txnEdge['node']['amount'];
            }
        }

        // Count items
        foreach ($o['lineItems']['edges'] as $li) {
            $qty = $li['node']['quantity'] ?? 1;
            $totalItems += $qty;
        }

        $net = $paid - $refunded;

        $totalSales += $price;
        $totalRefunded += $refunded;

        $fs = strtolower($o['displayFinancialStatus'] ?? 'unknown');
        if ($fs === 'paid') {
            $totalPaid += $net;
        } elseif ($fs === 'partially_paid') {
            $totalPaid += $net;
            $totalPending += $price - $net;
        } elseif ($fs === 'pending') {
            $totalPending += $price;
        }

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
        ];
    }

    $cursor = $result['data']['orders']['pageInfo']['hasNextPage']
        ? $result['data']['orders']['pageInfo']['endCursor']
        : null;
} while ($cursor);

sort($states);
