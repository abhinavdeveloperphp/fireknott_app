<?php
include 'functions.php';
require 'vendor/autoload.php';
require 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = "";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getSheetByName('Design Master'); // ✅ Sheet: Design Master
        $highestRow = $sheet->getHighestDataRow();

        for ($i = 2; $i <= $highestRow; $i++) {
            $design_name = trim($sheet->getCell("A{$i}")->getValue());
            $design_code = trim($sheet->getCell("B{$i}")->getValue());
            $garment_type = trim($sheet->getCell("C{$i}")->getValue());
            $garment_code = trim($sheet->getCell("D{$i}")->getValue());
            $size_name = trim($sheet->getCell("E{$i}")->getValue());
            $size_code = trim($sheet->getCell("F{$i}")->getValue());
            $colour = trim($sheet->getCell("G{$i}")->getValue());
            $code = trim($sheet->getCell("H{$i}")->getValue());
            $price = trim($sheet->getCell("I{$i}")->getValue());
            $quantity = trim($sheet->getCell("J{$i}")->getValue());
            $hsn_no = trim($sheet->getCell("K{$i}")->getValue());

            if ($design_name === "" && $design_code === "")
                continue;

            $stmt = $pdo->prepare("INSERT INTO design_master (design_name, design_code, garment_type, garment_code, size_name, size_code, colour, code, price, quantity, hsn_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$design_name, $design_code, $garment_type, $garment_code, $size_name, $size_code, $colour, $code, $price, $quantity, $hsn_no]);
        }
        $message = "✅ Design Master sheet imported successfully!";
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $pdo->prepare("DELETE FROM design_master WHERE id = ?")->execute([$deleteId]);
    header("Location: design_master.php"); // Refresh page
    exit;
}

// Pagination setup
$limit = 10; // Rows per page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalRows = $pdo->query("SELECT COUNT(*) FROM design_master")->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM design_master ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$designs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <title>Design Master</title>
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
            font-size: 14px;
            padding: 12px;
            text-transform: uppercase;
        }

        td {
            border: 1px solid #e6e6e6;
            padding: 12px;
            text-align: center;
            font-size: 12px;
        }

        tr:nth-child(even) {
            background-color: #f0f6ff;
        }

        .btn-delete {
            padding: 5px 10px;
            background-color: #e53935;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-delete:hover {
            background-color: #c62828;
        }

        .pagination {
            text-align: center;
            margin-top: 20px;
        }

        .pagination-btn {
            padding: 6px 12px;
            background-color: #1e88e5;
            color: white;
            border: none;
            border-radius: 4px;
            margin: 0 3px;
            text-decoration: none;
            font-size: 12px;
        }

        .pagination-btn:hover {
            background-color: #1565c0;
        }

        .pagination-btn.active {
            background-color: #0d47a1;
            font-weight: bold;
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
            text-align: center;
            margin: 10px;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        .dropdown:hover .dropdown-content {
            display: block;
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

        input[type=file] {
            display: block;
            margin: 10px auto;
            padding: 10px;
            border: 2px solid #007bff;
            border-radius: 8px;
            cursor: pointer;
        }


        .form-group {
            display: flex;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 8px;
            vertical-align: middle;
            position: absolute;
            right: 0;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .hidden {
            display: none !important;
        }

        .btn {
            padding: 8px 40px;
            background-color: #1e88e5;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
        }

        .btn:hover {
            background-color: #1565c0;
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

        .btn-clear {
            background-color: #eeeeee;
            color: #333;
            margin-left: 10px;
        }

        .btn-clear:hover {
            background-color: #e0e0e0;
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
        <h2>Upload Design Sheet</h2>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                <button type="submit" class="btn">Upload & Generate</button>
            </div>
        </form>
        <p><?= $message ?></p>

        <table>
            <tr>
                <th>ID</th>
                <th>Design Name</th>
                <th>Design Code</th>
                <th>Garment Type</th>
                <th>Garment Code</th>
                <th>Size Name</th>
                <th>Size Code</th>
                <th>Colour</th>
                <th>Code</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>HSN No</th>
                <th>Action</th>
            </tr>
            <?php if (count($designs) > 0): ?>
                <?php foreach ($designs as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['design_name']) ?></td>
                        <td><?= htmlspecialchars($row['design_code']) ?></td>
                        <td><?= htmlspecialchars($row['garment_type']) ?></td>
                        <td><?= htmlspecialchars($row['garment_code']) ?></td>
                        <td><?= htmlspecialchars($row['size_name']) ?></td>
                        <td><?= htmlspecialchars($row['size_code']) ?></td>
                        <td><?= htmlspecialchars($row['colour']) ?></td>
                        <td><?= htmlspecialchars($row['code']) ?></td>
                        <td><?= htmlspecialchars($row['price']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= htmlspecialchars($row['hsn_no']) ?></td>
                        <td>
                            <a href="?delete_id=<?= $row['id'] ?>" class="btn-delete"
                                onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php else: ?>
                <tr>
                    <td colspan="13">No Data found.</td>
                </tr>
            <?php endif; ?>
        </table>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="pagination-btn">Prev</a>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>" class="pagination-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="pagination-btn">Next</a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>