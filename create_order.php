<?php
include 'functions.php';
require 'db.php';
require 'vendor/autoload.php';

// Fetch vendor & design data
$vendors = $pdo->query("SELECT * FROM vendor_master")->fetchAll();
$designs = $pdo->query("SELECT * FROM design_master")->fetchAll();
$message = "";

// Handle Delete
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$deleteId]);
    header("Location: create_order.php?deleted=1");
    exit;
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rows'])) {

    $barcodeDir = __DIR__ . "/storage/barcodes/";
    if (!is_dir($barcodeDir)) {
        mkdir($barcodeDir, 0777, true);
    }

    foreach ($_POST['rows'] as $row) {
        $vendor_id = $row['vendor'];
        $design_name = $row['design_name'];
        $design_code = $row['design_code'];
        $garment_type = $row['garment_type'];
        $garment_code = $row['garment_code'];
        $size = $row['size'];
        $size_code = $row['size_code'];
        $colour = $row['colour'];
        $colour_code = $row['code'];
        $price = $row['price'];
        $quantity = $row['quantity'];
        $hsn_no = $row['hsn_no'];

        $code = $design_code . $garment_code . $colour_code . $size_code . $price;

        // Generate barcode image
        $randomFile = uniqid('barcode_', true) . "_" . bin2hex(random_bytes(5)) . ".png";
        $barcodeFile = $barcodeDir . $randomFile;

        generateLabelImage(
            "Fireknott",
            $price,
            $garment_type,
            $code,
            $size,
            $colour,
            "www.fireknott.com",
            $barcodeFile
        );

        $barcodeUrl = "storage/barcodes/" . $randomFile;

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders 
            (vendor_id, design_name, design_code, garment_type, garment_code, colour, colour_code, size, size_code, price, code, quantity, hsn_no, barcode_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $vendor_id,
            $design_name,
            $design_code,
            $garment_type,
            $garment_code,
            $colour,
            $colour_code,
            $size,
            $size_code,
            $price,
            $code,
            $quantity,
            $hsn_no,
            $barcodeUrl
        ]);
    }

    $message = "✅ Orders Saved Successfully!";
}

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$start = ($page - 1) * $limit;

$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Fetch paginated orders
$stmt = $pdo->prepare("SELECT o.*, v.vendor_name FROM orders o 
                       JOIN vendor_master v ON o.vendor_id = v.id 
                       ORDER BY o.id DESC LIMIT :start, :limit");
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Order</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        /* ---------- Form Styles ---------- */
        form {
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            overflow: hidden;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        th {
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            text-transform: uppercase;
            font-size: 14px;
            padding: 12px;
        }

        td {
            padding: 10px;
            border: 1px solid #e0e0e0;
            text-align: center;
            font-size: 14px;
        }

        tr:nth-child(even) {
            background-color: #f0f6ff;
        }

        select,
        input[type="number"],
        input[type="text"] {
            width: 95%;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn {
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            background: #1e88e5;
            color: white;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background: #1565c0;
        }

        .btn-secondary {
            background: #eeeeee;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d6d6d6;
        }

        .btn-danger {
            background: #e53935;
        }

        .btn-danger:hover {
            background: #c62828;
        }

        .saved-orders table {
            margin-top: 10px;
        }

        .saved-orders td img {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 4px;
            background: #fafafa;
        }

        @media(max-width: 768px) {

            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
            }

            th {
                display: none;
            }

            td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: left;
            }

            td:before {
                position: absolute;
                left: 15px;
                top: 10px;
                font-weight: bold;
                color: #555;
            }
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

        .action-btn {
            padding: 6px 12px;
            background: #e53935;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
        }

        .action-btn:hover {
            background: #c62828;
        }

        .pagination-btn {
            padding: 8px 15px;
            background-color: #1e88e5;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            margin: 0 4px;
            display: inline-block;
        }

        .pagination-btn:hover {
            background-color: #1565c0;
        }

        .pagination {
            text-align: center;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 14px;
            margin: 0 5px;
            background: #1e88e5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .pagination a.active {
            background: #1565c0;
        }

        .pagination a:hover {
            background: #0d47a1;
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
                <a>Barcodes Management ▾</a>
                <div class="dropdown-content">
                    <a href="design_master.php">Design Master</a>
                    <a href="vendor_master.php">Vendor Master</a>
                    <a href="create_order.php">Create Order</a>
                </div>
            </span>
        </div>
    </div>
    <div class="container">
        <h2>Create Order</h2>

        <!-- Create Order Form -->
        <form method="post">
            <table id="orderTable">
                <tr>
                    <th>Vendor</th>
                    <th>Design Name</th>
                    <th>Design Code</th>
                    <th>Garment Type</th>
                    <th>Garment Code</th>
                    <th>Size</th>
                    <th>Size Code</th>
                    <th>Colour</th>
                    <th>Colour Code</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>HSN No</th>
                    <th>Action</th>
                </tr>

                <?php if (count($vendors) === 0 || count($designs) === 0): ?>
                    <tr>
                        <td colspan="13" style="text-align:center; color:red; font-weight:bold;">
                            ⚠ No Vendors or Designs Found. Please add them first.
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <?php if (count($vendors) > 0 && count($designs) > 0): ?>
                <button type="button" class="btn" onclick="addRow()">+ Add More</button>
                <button type="submit" class="btn">Save Orders</button>
            <?php endif; ?>
        </form>
        <p><?= $message ?></p>

        <hr>
        <h2>Saved Orders</h2>
        <div class="saved-orders">
            <form method="post" action="print_pdf.php">
                <input type="hidden" name="select_all" id="selectAllInput" value="0">
                <table>
                    <tr>
                        <th><input type="checkbox"
                                onclick="document.querySelectorAll('.selectOrder').forEach(cb=>cb.checked=this.checked)"
                                id="selectAll">
                            All</th>
                        <th>ID</th>
                        <th>Vendor</th>
                        <th>Design</th>
                        <th>Size</th>
                        <th>Colour</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Barcode</th>
                        <th>Action</th>
                    </tr>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><input type="checkbox" name="selected[]" value="<?= $o['id'] ?>" class="selectOrder"></td>
                                <td><?= $o['id'] ?></td>
                                <td><?= htmlspecialchars($o['vendor_name']) ?></td>
                                <td><?= htmlspecialchars($o['design_name']) ?></td>
                                <td><?= htmlspecialchars($o['size']) ?></td>
                                <td><?= htmlspecialchars($o['colour']) ?></td>
                                <td><?= $o['price'] ?></td>
                                <td><?= $o['quantity'] ?></td>
                                <td><a href="<?= $o['barcode_url'] ?>" target="_blank"><img src="<?= $o['barcode_url'] ?>"
                                            width="120"></a></td>
                                <td><a href="print_pdf.php?id=<?= $o['id'] ?>" target="_blank">🖨 Print</a> |
                                    <a href="create_order.php?delete_id=<?= $o['id'] ?>"
                                        onclick="return confirm('Are you sure?')" style="color:red;">🗑 Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                </table>
                <?php if (count($orders) > 0): ?>
                    <button type="submit" class="btn" id="printSelectedBtn" style="display:none;">🖨 Print Selected</button>
                <?php endif; ?>
            </form>

            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden Row Template -->
    <?php if (count($vendors) > 0 && count($designs) > 0): ?>
        <table style="display:none;">
            <tr id="rowTemplate">
                <td>
                    <select name="rows[__INDEX__][vendor]">
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][design_name]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['design_name'] ?>"><?= htmlspecialchars($d['design_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][design_code]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['design_code'] ?>"><?= htmlspecialchars($d['design_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][garment_type]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['garment_type'] ?>"><?= htmlspecialchars($d['garment_type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][garment_code]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['garment_code'] ?>"><?= htmlspecialchars($d['garment_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][size]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['size_name'] ?>"><?= htmlspecialchars($d['size_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][size_code]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['size_code'] ?>"><?= htmlspecialchars($d['size_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][colour]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['colour'] ?>"><?= htmlspecialchars($d['colour']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][code]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['code'] ?>"><?= htmlspecialchars($d['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][price]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['price'] ?>"><?= $d['price'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][quantity]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['quantity'] ?>"><?= $d['quantity'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="rows[__INDEX__][hsn_no]">
                        <?php foreach ($designs as $d): ?>
                            <option value="<?= $d['hsn_no'] ?>"><?= htmlspecialchars($d['hsn_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button class="action-btn" type="button" onclick="removeRow(this)">Remove</button></td>
            </tr>
        </table>
    <?php endif; ?>
    <script>
        let rowIndex = 0;
        function addRow() {
            let template = document.getElementById("rowTemplate").outerHTML;
            let newRow = template.replace(/__INDEX__/g, rowIndex);
            let table = document.getElementById("orderTable");
            let row = table.insertRow();
            row.innerHTML = newRow;
            row.removeAttribute("id");
            row.style.display = "";
            rowIndex++;
        }

        function removeRow(btn) {
            let table = document.getElementById("orderTable");
            let rowCount = table.rows.length - 1;
            if (rowCount <= 1) {
                alert("⚠ You must have at least one row.");
                return;
            }
            btn.closest("tr").remove();
        }

        document.addEventListener("DOMContentLoaded", function () {
            addRow();
        });

        document.addEventListener("DOMContentLoaded", function () {
            const checkboxes = document.querySelectorAll(".selectOrder");
            const selectAll = document.getElementById("selectAll");
            const printBtn = document.getElementById("printSelectedBtn");
            const selectAllInput = document.getElementById("selectAllInput");

            function toggleButton() {
                const anyChecked = document.querySelectorAll(".selectOrder:checked").length > 0;
                printBtn.style.display = anyChecked || selectAll.checked ? "inline-block" : "none";
            }

            if (selectAll) {
                selectAll.addEventListener("change", function () {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    selectAllInput.value = this.checked ? "1" : "0";
                    toggleButton();
                });
            }

            checkboxes.forEach(cb => cb.addEventListener("change", toggleButton));
        });
    </script>
</body>

</html>