<?php
include 'functions.php';
require 'vendor/autoload.php';
require 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = "";

// Delete Vendor if action=delete
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM vendor_master WHERE id = ?");
    $stmt->execute([$delete_id]);
    $message = "✅ Vendor deleted successfully!";
}

// Handle Excel Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getSheetByName('Vendor');
        $highestRow = $sheet->getHighestDataRow();

        for ($i = 2; $i <= $highestRow; $i++) {
            $vendor_name = trim($sheet->getCell("A{$i}")->getValue());
            $contact_person_name = trim($sheet->getCell("B{$i}")->getValue());
            $email = trim($sheet->getCell("C{$i}")->getValue());
            $phone = trim($sheet->getCell("D{$i}")->getValue());
            $address = trim($sheet->getCell("E{$i}")->getValue());
            $remarks = trim($sheet->getCell("F{$i}")->getValue());

            if ($vendor_name === "")
                continue;

            $stmt = $pdo->prepare("INSERT INTO vendor_master (vendor_name, contact_person_name, email, phone, address, remarks) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$vendor_name, $contact_person_name, $email, $phone, $address, $remarks]);
        }
        $message = "✅ Vendor sheet imported successfully!";
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Pagination
$limit = 10; // records per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get total records
$totalStmt = $pdo->query("SELECT COUNT(*) FROM vendor_master");
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get vendors with limit
$stmt = $pdo->prepare("SELECT * FROM vendor_master ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Vendor Master</title>
    <style>
        /* keep your existing CSS */
        td {
            font-size: 12px;
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

        .instructions {
            text-align: center;
            color: #555;
            margin: -15px auto 25px;
            max-width: 700px;
            font-size: 16px;
            line-height: 1.6;
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

        .hidden {
            display: none;
        }


        input[type=file] {
            display: block;
            margin: 10px auto;
            padding: 10px;
            border: 2px solid #007bff;
            border-radius: 8px;
            cursor: pointer;
        }

        .barcode {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
        }

        .barcode img {
            max-width: 250px;
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
        <h2>Upload Vendor Sheet</h2>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                <button type="submit" class="btn" id="uploadBtn">
                    <span id="btnText">Upload & Generate</span>
                    <span id="btnSpinner" class="spinner hidden"></span>
                </button>
            </div>
        </form>
        <p><?= $message ?></p>

        <table border="1">
            <tr>
                <th>ID</th>
                <th>Vendor Name</th>
                <th>Contact Person Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Remarks</th>
                <th>Action</th>
            </tr>
            <?php if (count($vendors) > 0): ?>
                <?php foreach ($vendors as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                        <td><?= htmlspecialchars($row['contact_person_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td>
                            <a href="?delete_id=<?= $row['id'] ?>" class="action-btn"
                                onclick="return confirm('Delete this vendor?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No Data found. </td>
                </tr>
            <?php endif; ?>

        </table>

        <!-- Pagination -->
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

</body>

</html>