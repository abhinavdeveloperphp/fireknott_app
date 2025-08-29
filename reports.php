<?php
include 'functions.php';

$token = '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';
$shop = 'cgh6bd-x8.myshopify.com';

$selectedMonth = $_GET['month'] ?? date('Y-m');
$collectionId = $_GET['collection_id'] ?? '';

$from = $selectedMonth . "-01";
$to = date("Y-m-t", strtotime($from));

function getOrders($token, $shop, $from, $to, $after = null) {
    $afterCursor = $after ? "after: \"$after\", " : "";
    $query = [
        "query" => "
        {
            orders(first: 50, $afterCursor query: \"created_at:>=$from created_at:<=$to\") {
                edges {
                    cursor
                    node {
                        id
                        name
                        createdAt
                        totalPriceSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                        }
                        lineItems(first: 10) {
                            edges {
                                node {
                                    title
                                    product {
                                        id
                                    }
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }"
    ];

    $response = shopify_gql_call($token, $shop, $query);
    return json_decode($response['response'], true);
}

function isProductInCollection($productId, $collectionId, $token, $shop) {
    $query = [
        "query" => "
        {
            collection(id: \"gid://shopify/Collection/$collectionId\") {
                products(first: 50) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }"
    ];
    $response = shopify_gql_call($token, $shop, $query);
    $data = json_decode($response['response'], true);

    if (!isset($data['data']['collection']['products']['edges'])) return false;

    foreach ($data['data']['collection']['products']['edges'] as $edge) {
        $id = str_replace("gid://shopify/Product/", "", $edge['node']['id']);
        if ($id == $productId) return true;
    }
    return false;
}

// Prepare CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_report.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Order ID', 'Order Date', 'Product Name', 'Total Price']);

$cursor = null;
do {
    $ordersData = getOrders($token, $shop, $from, $to, $cursor);
    $orders = $ordersData['data']['orders']['edges'];

    foreach ($orders as $order) {
        $orderNode = $order['node'];
        $orderId = $orderNode['name'];
        $orderDate = date('Y-m-d', strtotime($orderNode['createdAt']));
        $totalPrice = $orderNode['totalPriceSet']['presentmentMoney']['amount'];

        foreach ($orderNode['lineItems']['edges'] as $item) {
            $productId = str_replace("gid://shopify/Product/", "", $item['node']['product']['id']);
            $productName = $item['node']['title'];

            if ($collectionId && !isProductInCollection($productId, $collectionId, $token, $shop)) {
                continue;
            }

            fputcsv($output, [$orderId, $orderDate, $productName, $totalPrice]);
        }
    }

    $cursor = $ordersData['data']['orders']['pageInfo']['hasNextPage'] 
              ? $ordersData['data']['orders']['pageInfo']['endCursor'] 
              : null;
} while ($cursor);

fclose($output);
exit;
