<?php
include 'functions.php';

$token = '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';
$shop = 'cgh6bd-x8.myshopify.com';

// Get filter/search/cursor
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$pageCursor = $_GET['cursor'] ?? null;

// Build GraphQL query
$queryParts = [];

if ($filter === 'cancelled') {
    $queryParts[] = 'cancel_reason:*';
} elseif ($filter === 'refunded') {
    $queryParts[] = '(financial_status:refunded OR financial_status:partially_refunded)';
}

if (!empty($search)) {
    $queryParts[] = "name:*{$search}*";
}

$combinedQuery = implode(' AND ', $queryParts);
$afterCursor = $pageCursor ? "after: \"$pageCursor\", " : "";

function getAllOrders($token, $shop, $afterCursor, $combinedQuery) {
    $queryString = $combinedQuery ? "query: \"$combinedQuery\"" : "";

    $query = [
        "query" => "
        {
          orders(first: 50, reverse: true, $afterCursor $queryString) {
            edges {
              cursor
              node {
                id
                name
                createdAt
                paymentGatewayNames
                displayFulfillmentStatus
                displayFinancialStatus
                totalPriceSet {
                  presentmentMoney {
                    amount
                    currencyCode
                  }
                }
                refunds {
                  id
                  createdAt
                  transactions(first: 10) {
                    edges {
                      node {
                        id
                        amount
                      }
                    }
                  }
                }
                transactions {
                  id
                  amount
                  kind
                  status
                }
                customer {
                  firstName
                  lastName
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
    $data = json_decode($response['response'], true);

    if (isset($data['errors'])) {
        return ['error' => $data['errors']];
    }

    return $data;
}

$ordersData = getAllOrders($token, $shop, $afterCursor, $combinedQuery);

if (isset($ordersData['error'])) {
    die("<div class='error'>Error: " . json_encode($ordersData['error']) . "</div>");
}

$orders = $ordersData['data']['orders']['edges'];
$hasNextPage = $ordersData['data']['orders']['pageInfo']['hasNextPage'];
$nextCursor = $ordersData['data']['orders']['pageInfo']['endCursor'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f6f9;
        }

        .navbar {
            background-color: #1e88e5;
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
        }

        .navbar .logo {
            max-height: 40px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 25px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #ffe082;
        }

        .container {
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        th {
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            font-size: 16px;
            padding: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            border: 1px solid #e6e6e6;
            padding: 14px;
            text-align: center;
            font-size: 10px;
        }

        tr:nth-child(even) {
            background-color: #f0f6ff;
        }

        .btn {
            background-color: #43a047;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background-color: #388e3c;
        }

        .pagination {
            margin-top: 30px;
            text-align: center;
        }

        .pagination a {
            background-color: #1e88e5;
            color: white;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .pagination a:hover {
            background-color: #1565c0;
        }

        .filter-form {
            display: inline-flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-form select,
        .filter-form input {
            padding: 8px;
            font-size: 15px;
        }

        .filter-form button {
            background-color: #1e88e5;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .filter-form button:hover {
            background-color: #1565c0;
        }

        @media (max-width: 768px) {
            .nav-links a {
                margin-left: 10px;
            }

            th, td {
                font-size: 14px;
                padding: 10px;
            }

            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div>
        <img src="https://fireknott.com/cdn/shop/files/Fireknott-Logo-Black-ori_360x.webp?v=1729241431" alt="Logo" class="logo">
    </div>
    <div class="nav-links">
        <a href="orders.php">Home</a>
        <a href="monthly_sales.php">Monthly Sales</a>
        <a href="tax_rate.php">Tax Rates</a>
        <a href="barcode.php">Barcode</a>
    </div>
</div>

<div class="container">
    <h1>Orders</h1>

    <form class="filter-form" method="GET">
        <div>
            <label for="filter">Filter: </label>
            <select name="filter" id="filter">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="refunded" <?= $filter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                <!-- <option value="cancelled" <?= $filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option> -->
            </select>
        </div>
        <div>
            <label for="search">Search: </label>
            <input type="text" name="search" placeholder="Order ID" value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit">Apply</button>
    </form>

    <table>
        <thead>
        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Refunded</th>
            <th>Net Payment</th>
            <th>Payment Status</th>
            <th>Status</th>
            <th>Invoice</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): 
            $node = $order['node'];
            $totalAmount = floatval($node['totalPriceSet']['presentmentMoney']['amount']);
            $currency = $node['totalPriceSet']['presentmentMoney']['currencyCode'];

            $paidAmount = 0;
            foreach ($node['transactions'] as $txn) {
                if ($txn['kind'] === 'SALE' && $txn['status'] === 'SUCCESS') {
                    $paidAmount += floatval($txn['amount']);
                }
            }

            $refunded = 0;
            foreach ($node['refunds'] as $refund) {
                foreach ($refund['transactions']['edges'] as $txnEdge) {
                    $refunded += floatval($txnEdge['node']['amount']);
                }
            }

            $remaining = $paidAmount - $refunded;
        ?>
        <tr>
            <td><?= htmlspecialchars($node['name']) ?></td>
            <td><?= htmlspecialchars($node['customer']['firstName'] . ' ' . $node['customer']['lastName']) ?></td>
            <td><?= date("Y-m-d", strtotime($node['createdAt'])) ?></td>
            <td><?= number_format($totalAmount, 2) . ' ' . $currency ?></td>
            <td><?= number_format($paidAmount, 2) . ' ' . $currency ?></td>
            <td><?= number_format($refunded, 2) . ' ' . $currency ?></td>
            <td><?= number_format($remaining, 2) . ' ' . $currency ?></td>
            <td><?= htmlspecialchars($node['displayFinancialStatus']) ?></td>
            <td><?= htmlspecialchars($node['displayFulfillmentStatus']) ?></td>
            <td>
                <?php if ($node['displayFinancialStatus'] === 'PAID' || $node['displayFinancialStatus'] === 'PARTIALLY_REFUNDED'): ?>
                    <a class="btn" href="invoice.php?order_id=<?= urlencode(str_replace('gid://shopify/Order/', '', $node['id'])) ?>">View Invoice</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php if ($hasNextPage): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['cursor' => $nextCursor])) ?>">Next Page</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
