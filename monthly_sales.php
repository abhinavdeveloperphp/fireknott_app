<?php
include 'functions.php';

$token = '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';
$shop = 'cgh6bd-x8.myshopify.com';

function getShopifyCollections($token, $shop)
{
  $query = [
    "query" => "
        { collections(first: 50) {
            edges { node { id title } }
        }}"
  ];
  $resp = shopify_gql_call($token, $shop, $query);
  $data = json_decode($resp['response'], true);
  $cols = [];
  foreach ($data['data']['collections']['edges'] as $e) {
    $cols[] = [
      'id' => str_replace("gid://shopify/Collection/", "", $e['node']['id']),
      'title' => $e['node']['title']
    ];
  }
  return $cols;
}

function getOrdersForMonth($token, $shop, $from, $to, $after = null)
{
  $cursorClause = $after ? "after: \"$after\"," : "";
  $qs = "created_at:>=$from created_at:<=$to";
  $query = [
    "query" => "
    {
        orders(first:50, $cursorClause query: \"$qs\") {
            pageInfo { hasNextPage endCursor }
            edges {
                cursor
                node {
                    id
                    name
                    createdAt
                    cancelledAt
                    displayFinancialStatus
                    displayFulfillmentStatus
                    paymentGatewayNames
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
                    transactions {
                        id
                        amount
                        kind
                        status
                    }
                    refunds {
                        transactions(first: 10) {
                            edges {
                                node {
                                    amount
                                    kind
                                    status
                                }
                            }
                        }
                    }
                    shippingAddress {
                        province
                    }
                    lineItems(first:10) {
                        edges {
                            node {
                                product {
                                    id
                                }
                                quantity
                            }
                        }
                    }
                }
            }
        }
    }
"
  ];

  $resp = shopify_gql_call($token, $shop, $query);
  return json_decode($resp['response'], true);
}

$collections = getShopifyCollections($token, $shop);
$dateOpt = $_GET['date_filter_option'] ?? 'today';
$collectionId = $_GET['collection_id'] ?? '';
$selectedState = $_GET['state'] ?? '';


$selectedPaymentStatus = $_GET['payment_status'] ?? '';
$selectedFulfillmentStatus = $_GET['fulfillment_status'] ?? '';

$from = $to = '';
if ($dateOpt === 'today') {
  $from = gmdate('Y-m-d') . "T00:00:00Z";
  $to = gmdate('Y-m-d') . "T23:59:59Z";
} elseif ($dateOpt === 'custom') {
  $fromR = $_GET['from_date'] ?? '';
  $toR = $_GET['to_date'] ?? '';
  if ($fromR && $toR) {
    $from = date('Y-m-d', strtotime($fromR)) . "T00:00:00Z";
    $to = date('Y-m-d', strtotime($toR)) . "T23:59:59Z";
  } else {
    die("Please specify from & to dates.");
  }
} elseif ($dateOpt === 'all') {
  $from = "2000-01-01T00:00:00Z";
  $to = gmdate('Y-m-d') . "T23:59:59Z";
} else {
  $from = gmdate('Y-m-01') . "T00:00:00Z";
  $to = gmdate('Y-m-t') . "T23:59:59Z";
}

$totalSales = $totalPaid = $totalPending = $totalRefunded = 0;
$cursor = null;
$states = $ordersForTable = [];
$totalItems = 0; // <-- add this at top before the loop

do {
  $data = getOrdersForMonth($token, $shop, $from, $to, $cursor);
  foreach ($data['data']['orders']['edges'] as $edge) {
    $o = $edge['node'];

    $state = $o['shippingAddress']['province'] ?? 'Unknown';
    if (!in_array($state, $states))
      $states[] = $state;
    if ($selectedState && $selectedState !== $state)
      continue;


    if ($selectedPaymentStatus && strtolower($o['displayFinancialStatus']) !== strtolower($selectedPaymentStatus))
      continue;
    if ($selectedFulfillmentStatus && strtolower($o['displayFulfillmentStatus']) !== strtolower($selectedFulfillmentStatus))
      continue;


    if ($collectionId) {
      $found = false;
      foreach ($o['lineItems']['edges'] as $li) {
        $pid = str_replace("gid://shopify/Product/", "", $li['node']['product']['id']);
        if (isProductInCollection($pid, $collectionId, $token, $shop)) {
          $found = true;
          break;
        }
      }
      if (!$found)
        continue;
    }
    $price = isset($o['totalPriceSet']['presentmentMoney']['amount']) ? (float) $o['totalPriceSet']['presentmentMoney']['amount'] : 0;
    // $refunded = isset($o['totalRefundedSet']['presentmentMoney']['amount']) ? (float)$o['totalRefundedSet']['presentmentMoney']['amount'] : 0;

    // $paid = $price; // Shopify's total price is what customer paid
    // $netAmount = $paid - $refunded;
    // $remaining = max(0, $netAmount);






    // Actual paid amount from flat transactions array
    $paidAmount = 0;
    if (!empty($o['transactions'])) {
      foreach ($o['transactions'] as $txn) {
        if ($txn['kind'] === 'SALE' && $txn['status'] === 'SUCCESS') {
          $paidAmount += floatval($txn['amount']);
        }
      }
    }

    // $price=$paidAmount;
    $paid = $paidAmount;

    // Refunded amount from refund.transactions.edges.node.amount
    $refunded = 0;
    if (!empty($o['refunds'])) {
      foreach ($o['refunds'] as $refund) {
        foreach ($refund['transactions']['edges'] as $txnEdge) {
          $txn = $txnEdge['node'];
          $refunded += floatval($txn['amount']);
        }
      }
    }


    foreach ($o['lineItems']['edges'] as $li) {
      $qty = $li['node']['quantity'] ?? 1;
      $totalItems += $qty;
    }

    $remaining = $paidAmount - $refunded;

    $netAmount = $remaining;

    // Summary calculations
    $totalSales += $price;
    $totalRefunded += $refunded;
    //  $totalPaid += $paid; 



    $fs = strtolower($o['displayFinancialStatus'] ?? 'unknown');

    if ($fs === 'paid') {
      $totalPaid += $netAmount;
    } elseif ($fs === 'partially_paid') {
      $totalPaid += $netAmount;
      $totalPending += $price - $netAmount;
    } elseif ($fs === 'pending') {
      $totalPending += $price;
    }



    $ordersForTable[] = [
      'name' => $o['name'],
      'status' => ucfirst($o['displayFinancialStatus']),
      'fulfillment' => $o['displayFulfillmentStatus'],
      'cancelled' => !empty($o['cancelledAt']),
      'date' => $o['createdAt'],
      'state' => $state,
      'price' => number_format($price, 2),
      'paid' => number_format($paid, 2),
      'refunded' => number_format($refunded, 2),
      'net' => number_format($netAmount, 2),
      'paymentGatewayNames' => $o['paymentGatewayNames'],
      'currency' => $o['totalPriceSet']['presentmentMoney']['currencyCode'],
      'orderId' => str_replace("gid://shopify/Order/", "", $o['id']),
    ];

  }
  $cursor = $data['data']['orders']['pageInfo']['hasNextPage']
    ? $data['data']['orders']['pageInfo']['endCursor'] : null;
} while ($cursor);

sort($states);
?>

<!DOCTYPE html>
<html>

<head>
  <title>Sales Report</title>
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
      max-width: 1100px;
      margin: 40px auto;
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    h1,
    h2 {
      text-align: center;
      color: #333;
      margin-bottom: 30px;
    }

    .summary {
      background: #ffffff;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 30px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .summary p {
      margin: 8px 0;
      font-size: 16px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      border-radius: 8px;
      overflow: hidden;
    }

    table th,
    table td {
      text-align: left;
      padding: 12px 15px;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }

    table th {
      background-color: #3498db;
      color: white;
      text-transform: uppercase;
      font-size: 13px;
      letter-spacing: 0.5px;
    }

    table tr:hover {
      background-color: #f1f1f1;
    }

    .cancelled {
      color: red;
      font-weight: bold;
    }

    .paid {
      color: green;
    }

    .pending {
      color: orange;
    }

    form {
      max-width: 600px;
      margin: 20px auto 30px;
      background: #fafafa;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
    }

    form label,
    form select,
    form input,
    form button {
      display: inline-block;
      margin: 8px 5px;
    }


    .filter-form {
      max-width: 800px;
      margin: 0 auto 30px auto;
      padding: 20px;
      background: #fdfdfd;
      border: 1px solid #ddd;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
    }

    .filter-row {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 15px;
    }

    .filter-row label {
      font-weight: 500;
      color: #333;
      flex: 1 1 40%;
    }

    .filter-row select,
    .filter-row input[type="date"] {
      width: 100%;
      padding: 8px 12px;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: #fff;
    }

    .filter-actions {
      text-align: right;
      margin-top: 10px;
    }

    .btn {
      padding: 8px 16px;
      background-color: #1e88e5;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      transition: background-color 0.3s ease;
    }

    .btn:hover {
      background-color: #1565c0;
    }

    .btn-clear {
      background-color: #eeeeee;
      color: #333;
      margin-left: 10px;
    }

    .btn-clear:hover {
      background-color: #e0e0e0;
    }

    .hidden {
      display: none;
    }

    /* Dropdown styles */
    .dropdown {
      position: relative;
      display: inline-block;
    }

    .dropdown a {
      cursor: pointer;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      background-color: #ffffff;
      min-width: 180px;
      box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      border-radius: 6px;
      overflow: hidden;
      right: -34px;
    }

    .dropdown-content a {
      color: #333 !important;
      padding: 12px 16px;
      text-decoration: none;
      display: block;
      font-weight: normal;
      margin: 10px;
      text-align: center;
    }

    .dropdown-content a:hover {
      background-color: #f1f1f1;
    }

    .dropdown:hover .dropdown-content {
      display: block;
    }
  </style>
</head>

<body>

  <div class="navbar">
    <div>
      <img src="https://fireknott.com/cdn/shop/files/Fireknott-Logo-Black-ori_360x.webp?v=1729241431" alt="Logo"
        class="logo">
    </div>
    <div class="nav-links">
      <a href="orders.php">Home</a>
      <a href="monthly_sales.php">Monthly Sales</a>
      <a href="tax_rate.php">Tax Rates</a>
      <span class="dropdown">
        <a>Barcodes Management▾</a>
        <div class="dropdown-content">
          <a href="design_master.php">Design Master</a>
          <a href="vendor_master.php">Vendor Master</a>
          <a href="create_order.php">Create Order</a>
        </div>
      </span>
    </div>
  </div>

  <div class="container">
    <form method="GET" class="filter-form" id="filterForm">
      <div class="filter-row">
        <label for="date_filter_option">Filter By:</label>
        <select name="date_filter_option" id="date_filter_option" onchange="toggleCustomDates(this.value)">
          <option value="month" <?= $dateOpt === 'month' ? 'selected' : '' ?>>This Month</option>
          <option value="today" <?= $dateOpt === 'today' ? 'selected' : '' ?>>Today</option>
          <option value="custom" <?= $dateOpt === 'custom' ? 'selected' : '' ?>>Custom Range</option>
          <option value="all" <?= $dateOpt === 'all' ? 'selected' : '' ?>>All Records</option>
        </select>
      </div>

      <div id="custom-dates" class="filter-row <?= $dateOpt === 'custom' ? '' : 'hidden' ?>">
        <label>From:
          <input type="date" name="from_date" value="<?= $_GET['from_date'] ?? '' ?>">
        </label>
        <label>To:
          <input type="date" name="to_date" value="<?= $_GET['to_date'] ?? '' ?>">
        </label>
      </div>


      <div class="filter-row">
        <label for="state">Filter by State:</label>
        <select name="state" id="state">
          <option value="">All States</option>
          <?php foreach ($states as $stateOpt): ?>
            <option value="<?= $stateOpt ?>" <?= $selectedState === $stateOpt ? 'selected' : '' ?>>
              <?= $stateOpt ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <input type="hidden" name="payment_status" id="payment_status" value="<?= $selectedPaymentStatus ?>">
      <input type="hidden" name="fulfillment_status" id="fulfillment_status" value="<?= $selectedFulfillmentStatus ?>">
      <div class="filter-actions">
        <button type="submit" class="btn">Apply Filter</button>
        <a href="sales_report.php" class="btn btn-clear">Clear</a>
      </div>
    </form>


    <h2>Summary</h2>
    <div class="summary">
      <p><strong>Total Orders:</strong> <?= count($ordersForTable) ?></p>
      <p><strong>Total Items Ordered:</strong> <?= $totalItems ?></p>
      <p><strong>Gross Sales:</strong> <?= number_format($totalSales, 2) ?></p>

      <p><strong>Net Sales:</strong> <?= number_format($totalSales - $totalRefunded, 2) ?></p>


      <p><strong>Total Paid:</strong> <?= number_format($totalPaid, 2) ?></p>
      <p><strong>Total Pending:</strong> <?= number_format($totalPending, 2) ?></p>
      <p><strong>Total Refunded:</strong> <?= number_format($totalRefunded, 2) ?></p>
      <p><strong>Net Payment:</strong> <?= number_format($totalPaid - $totalRefunded, 2) ?></p>

    </div>

    <h2>Details</h2>


    <table style="margin-top:30px;">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Total</th>
          <th>Paid</th>
          <th>Refunded</th>
          <th>Net Payment</th>
          <th>Payment Status <br>
            <select name="payment_status" onchange="updateFilter('payment_status', this.value)">
              <option value="">All</option>
              <?php
              $paymentStatuses = ['paid', 'partially_paid', 'pending', 'refunded', 'unpaid'];
              foreach ($paymentStatuses as $status):
                ?>
                <option value="<?= $status ?>" <?= $selectedPaymentStatus === $status ? 'selected' : '' ?>>
                  <?= ucfirst($status) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </th>
          <th>Fulfillment Status <br>
            <select name="fulfillment_status" onchange="updateFilter('fulfillment_status', this.value)">
              <option value="">All</option>
              <?php
              $fulfillmentStatuses = ['fulfilled', 'unfulfilled', 'partial', 'in_progress', 'on_hold'];
              foreach ($fulfillmentStatuses as $fs):
                ?>
                <option value="<?= $fs ?>" <?= $selectedFulfillmentStatus === $fs ? 'selected' : '' ?>>
                  <?= ucfirst($fs) ?>
                </option>
              <?php endforeach; ?>
          </th>
          <th>State <br>
            <select name="state" onchange="syncStateAndSubmit(this.value)">
              <option value="">All</option>
              <?php foreach ($states as $state): ?>
                <option value="<?= $state ?>" <?= $selectedState === $state ? 'selected' : '' ?>>
                  <?= $state ?>
                </option>
              <?php endforeach; ?>
            </select>
          </th>
          <th>Invoice</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ordersForTable as $o):
          $remaining = floatval($o['net']);
          ?>
          <tr>
            <td>#<?= $o['name'] ?></td>
            <td><?= htmlspecialchars($o['name']) ?></td>
            <td><?= (new DateTime($o['date']))->format('Y-m-d') ?></td>
            <td><?= $o['currency'] . ' ' . $o['price'] ?></td>
            <td><?= $o['currency'] . ' ' . $o['paid'] ?></td>
            <td><?= $o['currency'] . ' ' . $o['refunded'] ?></td>
            <td><?= $o['currency'] . ' ' . $o['net'] ?></td>

            <td class="<?= strtolower($o['status']) ?>"><?= $o['status'] ?></td>
            <td><?= $o['fulfillment'] ?></td>
            <td><?= $o['state'] ?></td>
            <td>
              <?php if (strtolower($o['status']) === 'paid'): ?>
                <a class="btn" href="invoice.php?order_id=<?= urlencode($o['orderId']) ?>">PDF</a>
              <?php endif; ?>


              <?php if (($o['status']) === 'PARTIALLY_REFUNDED'): ?>
                <a class="btn" href="invoice.php?order_id=<?= urlencode($o['orderId']) ?>">PDF</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>


      <a href="exp.php?<?= http_build_query($_GET) ?>" class="btn" style="margin-left:10px;">Sales Excel</a>

      <a href="tax_exp.php?<?= http_build_query($_GET) ?>" class="btn" style="margin-left:10px;"> Tax Excel</a>

    </table>
  </div>

  <script>
    function toggleCustomDates(val) {
      const custom = document.getElementById('custom-dates');
      custom.classList.toggle('hidden', val !== 'custom');
    }







    function updateFilter(field, value) {
      // Update hidden field
      document.getElementById(field).value = value;

      // Submit form
      document.getElementById('filterForm').submit();
    }


    function syncStateAndSubmit(val) {
      document.getElementById('state').value = val;
      document.getElementById('filterForm').submit();
    }
  </script>

</body>

</html>