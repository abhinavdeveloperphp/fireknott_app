<?php
include 'functions.php';

$token = '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';
$shop = 'cgh6bd-x8.myshopify.com';

$orderId = $_GET['order_id'] ?? null;
if (!$orderId) {
    die("Order ID not provided.");
}

$gid = "gid://shopify/Order/{$orderId}";

$query = ["query" => "
{
    order(id: \"$gid\") {
        id
        name
        createdAt
        displayFinancialStatus 
        currentTotalPriceSet {
            presentmentMoney {
                amount
                currencyCode
            }
        }
        customer {
            firstName
            lastName
            email
        }
        lineItems(first: 10) {
            edges {
                node {
                    title
                    quantity
                    originalUnitPriceSet {
                        presentmentMoney {
                            amount
                            currencyCode
                        }
                    }
                }
            }
        }
        transactions {
            id
            kind
            status
            amountSet {
                presentmentMoney {
                    amount
                    currencyCode
                }
            }
            gateway
            createdAt
        }
    }
}"];

$response = shopify_gql_call($token, $shop, $query);
$data = json_decode($response['response'], true);

if (isset($data['errors'])) {
    die("Error: " . json_encode($data['errors']));
}

$order = $data['data']['order'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order #<?= $order['name'] ?> Details</title>
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
    }

    .nav-links a:hover {
      color: #ffe082;
    }

    .container {
      max-width: 1000px;
      margin: 40px auto;
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    h1 {
      color: #2c3e50;
      text-align: center;
      margin-bottom: 30px;
    }

    h2 {
      border-bottom: 2px solid #1e88e5;
      color: #1e88e5;
      padding-bottom: 5px;
      margin-top: 30px;
      font-size: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      background: #fff;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      border-radius: 8px;
      overflow: hidden;
    }

    th, td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }

    th {
      background-color: #3498db;
      color: white;
      text-transform: uppercase;
    }

    tr:hover {
      background-color: #f1f1f1;
    }

    .section {
      margin-bottom: 40px;
    }

    .info-label {
      font-weight: bold;
      color: #333;
    }

    .back {
      display: inline-block;
      margin-bottom: 20px;
      text-decoration: none;
      background: #6c757d;
      color: white;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: bold;
      transition: background 0.3s ease;
    }

    .back:hover {
      background: #5a6268;
    }

    @media (max-width: 768px) {
      table, th, td {
        font-size: 13px;
      }

      .container {
        padding: 20px;
      }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
  <div>
    <img src="https://fireknott.com/cdn/shop/files/Fireknott-Logo-Black-ori_360x.webp?v=1729241431" alt="Logo" class="logo">
  </div>
  <div class="nav-links">
    <a href="orders.php">Home</a>
    <a href="monthly_sales.php">Monthly Sales</a>
    <a href="tax_rate.php">Tax Rates</a>
  </div>
</div>

<!-- Page Content -->
<div class="container">
  <a href="orders.php" class="back">&larr; Back to Orders</a>

  <h1>Order #<?= htmlspecialchars($order['name']) ?> Details</h1>

  <div class="section">
    <h2>Customer Info</h2>
    <p><span class="info-label">Name:</span> <?= htmlspecialchars($order['customer']['firstName'] . ' ' . $order['customer']['lastName']) ?></p>
    <p><span class="info-label">Email:</span> <?= htmlspecialchars($order['customer']['email']) ?></p>
    <p><span class="info-label">Date:</span> <?= date('Y-m-d H:i:s', strtotime($order['createdAt'])) ?></p>
    <p><span class="info-label">Status:</span> <?= htmlspecialchars($order['displayFinancialStatus']) ?></p>
    <p><span class="info-label">Total:</span> <?= $order['currentTotalPriceSet']['presentmentMoney']['amount'] . ' ' . $order['currentTotalPriceSet']['presentmentMoney']['currencyCode'] ?></p>
  </div>

  <div class="section">
    <h2>Line Items</h2>
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>Qty</th>
          <th>Unit Price</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($order['lineItems']['edges'] as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['node']['title']) ?></td>
            <td><?= htmlspecialchars($item['node']['quantity']) ?></td>
            <td><?= $item['node']['originalUnitPriceSet']['presentmentMoney']['amount'] . ' ' . $item['node']['originalUnitPriceSet']['presentmentMoney']['currencyCode'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="section">
    <h2>Transactions</h2>
    <?php if (empty($order['transactions'])): ?>
      <p>No transactions found for this order.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Kind</th>
            <th>Status</th>
            <th>Amount</th>
            <th>Gateway</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($order['transactions'] as $tx): ?>
            <tr>
              <td><?= htmlspecialchars($tx['kind']) ?></td>
              <td><?= htmlspecialchars($tx['status']) ?></td>
              <td><?= $tx['amountSet']['presentmentMoney']['amount'] . ' ' . $tx['amountSet']['presentmentMoney']['currencyCode'] ?></td>
              <td><?= htmlspecialchars($tx['gateway']) ?></td>
              <td><?= date('Y-m-d H:i:s', strtotime($tx['createdAt'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
