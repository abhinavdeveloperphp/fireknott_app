<?php
include 'functions.php';
include 'db.php'; // Database connection

$token = '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';
$shop = 'cgh6bd-x8.myshopify.com';

function getCollections($token, $shop, $after = null) {
    $afterCursor = $after ? "after: \"$after\", " : "";
    $query = ["query" => "
        {
            collections(first: 100, $afterCursor) {
                edges {
                    node {
                        id
                        title
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }"];
    $response = shopify_gql_call($token, $shop, $query);
    $data = json_decode($response['response'], true);
    return $data['data']['collections']['edges'] ?? [];
}

$collections = getCollections($token, $shop);

$stmt = $pdo->query("
  SELECT * FROM tax_rates 
  WHERE (collection_id, effective_from) IN (
    SELECT collection_id, MAX(effective_from) 
    FROM tax_rates 
    GROUP BY collection_id
  )
");
$savedRates = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $savedRates[$row['collection_id']] = $row;
}






$allRates = [];
$stmt = $pdo->query("
  SELECT * FROM tax_rates ORDER BY collection_id, effective_from
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cid = $row['collection_id'];
    if (!isset($allRates[$cid])) $allRates[$cid] = [];
    $allRates[$cid][] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Tax Rate Management</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f4f6f9;
      margin: 0;
      padding: 0;
    }

    .navbar {
      background-color: #1e88e5;
      padding: 15px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: white;
    }

    .navbar .logo {
      max-height: 40px;
    }

    .nav-links a {
      color: white;
      text-decoration: none;
      margin-left: 20px;
      font-weight: 500;
    }

    .nav-links a:hover {
      color: #ffe082;
    }

    .container {
      max-width: 1000px;
      margin: 40px auto;
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    h2 {
      text-align: center;
      margin-bottom: 25px;
      color: #333;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    th, td {
      padding: 14px;
      text-align: center;
    }

    th {
      background: linear-gradient(to right, #4facfe, #00f2fe);
      color: white;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    td {
      background-color: #f9fbff;
    }

    tr:nth-child(even) td {
      background-color: #f0f6ff;
    }

    input[type="number"] {
      width: 80px;
      padding: 6px;
      font-size: 14px;
      border-radius: 4px;
      border: 1px solid #ccc;
    }

    .input-wrapper {
      position: relative;
      display: inline-block;
    }

    .row-toast {
      position: absolute;
      top: 50%;
      left: 110%;
      transform: translateY(-50%);
      background-color: #43a047;
      color: white;
      padding: 5px 10px;
      border-radius: 6px;
      font-size: 13px;
      white-space: nowrap;
      animation: fadeInOut 3s ease forwards;
      z-index: 5;
      display: none;
    }

    .row-toast.error {
      background-color: #d32f2f;
    }

    @keyframes fadeInOut {
      0% { opacity: 0; transform: translateY(-50%) translateX(-10px); }
      10% { opacity: 1; transform: translateY(-50%) translateX(0); }
      90% { opacity: 1; }
      100% { opacity: 0; transform: translateY(-50%) translateX(-10px); }
    }

    button {
      padding: 8px 14px;
      background-color: #43a047;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
      transition: background 0.3s ease;
    }

    button:hover {
      background-color: #388e3c;
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
  </div>
</div>

<div class="container">
  <h2>Manage Collection Tax Rates</h2>
  <table>
    <thead>
      <tr>
        <th>Collection</th>
        <th>Tax Rate (%)</th> 
        <th>Priority</th>
        <th>Effective From</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="taxTableBody">
      <?php foreach ($collections as $collection): ?>
        <?php
          $id = str_replace('gid://shopify/Collection/', '', $collection['node']['id']);
          $title = htmlspecialchars($collection['node']['title']);
          $rate = $savedRates[$id]['tax_rate'] ?? '';
          $priority = $savedRates[$id]['priority'] ?? '';
          $effective_from = $savedRates[$id]['effective_from'] ?? '2025-01-01';

        ?>
        <tr data-id="<?= $id ?>">
          <td><?= $title ?></td>
          <td>
            <div class="input-wrapper">
              <input type="number" step="0.01" min="0" max="100" class="tax-rate" value="<?= $rate ?>" />
              <span class="row-toast"></span>
            </div>
          </td>
          <td>
            <input type="number" min="1" class="priority" value="<?= $priority ?>" />
          </td>

        

<td>
<input type="date" class="effective-date" value="<?= $effective_from ?>" />

</td>

          <td>
            <button onclick="saveTaxRate('<?= $id ?>', this)">Save</button>

            <button onclick="openHistoryModal('<?= $id ?>')">View History</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function saveTaxRate(id, btn) {
  const row = document.querySelector(`tr[data-id='${id}']`);
  const rate = parseFloat(row.querySelector(".tax-rate").value);
  const priority = parseInt(row.querySelector(".priority").value);
  const effectiveDate = row.querySelector(".effective-date").value;
  const toast = row.querySelector(".row-toast");

  fetch('save_tax_rate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      collection_id: id,
      tax_rate: rate,
      priority: priority,
      effective_from: effectiveDate
    })
  })
  .then(res => res.json())
  .then(data => {
    toast.classList.remove("error");
    if (data.success) {
      toast.textContent = "Saved";
    } else {
      toast.textContent = data.message || "Error";
      toast.classList.add("error");
    }
    toast.style.display = 'inline-block';
    toast.style.animation = "none";
    void toast.offsetWidth;
    toast.style.animation = "fadeInOut 3s ease forwards";
    setTimeout(() => toast.style.display = "none", 3000);
  });
}
</script>









<div id="historyModal" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%);
background:white; padding:20px; border-radius:10px; box-shadow:0 0 20px rgba(0,0,0,0.2); max-height:80vh; overflow-y:auto; z-index:1000;">
  <h3>Tax Rate History</h3>
  <table border="1" cellpadding="10" style="width:100%; border-collapse:collapse; margin-top:10px;">
    <thead>
      <tr>
        <th>Tax Rate (%)</th>
        <th>Priority</th>
        <th>Effective From</th>
        <th>Effective To</th>
      </tr>
    </thead>
    <tbody id="historyTableBody"></tbody>
  </table>
  <br>
  <button onclick="closeHistoryModal()">Close</button>
</div>
<div id="modalOverlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:999;" onclick="closeHistoryModal()"></div>
<script>
  const taxRateHistory = <?= json_encode($allRates) ?>;
</script>
<script>
function openHistoryModal(collectionId) {
  const modal = document.getElementById('historyModal');
  const overlay = document.getElementById('modalOverlay');
  const tbody = document.getElementById('historyTableBody');

  const rows = taxRateHistory[collectionId];
  if (!rows) return;

  // Clear previous
  tbody.innerHTML = '';

  // Build rows with "effective_to" as next row's effective_from - 1 day
  for (let i = 0; i < rows.length; i++) {
    const current = rows[i];
    const next = rows[i + 1];
    
    const effective_from = current.effective_from;
    let effective_to = next ? new Date(new Date(next.effective_from).getTime() - 86400000) : 'Ongoing';
    
    if (effective_to instanceof Date) {
      const yyyy = effective_to.getFullYear();
      const mm = String(effective_to.getMonth() + 1).padStart(2, '0');
      const dd = String(effective_to.getDate()).padStart(2, '0');
      effective_to = `${yyyy}-${mm}-${dd}`;
    }

    tbody.innerHTML += `
      <tr>
        <td>${current.tax_rate}</td>
        <td>${current.priority}</td>
        <td>${effective_from}</td>
        <td>${effective_to}</td>
      </tr>
    `;
  }

  modal.style.display = 'block';
  overlay.style.display = 'block';
}

function closeHistoryModal() {
  document.getElementById('historyModal').style.display = 'none';
  document.getElementById('modalOverlay').style.display = 'none';
}
</script>

</body>
</html>