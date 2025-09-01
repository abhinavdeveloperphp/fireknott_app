<?php
include 'functions.php';
require 'vendor/autoload.php';

require 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use Picqer\Barcode\BarcodeGeneratorPNG;

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];

    if ($fileTmpPath) {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $generator = new BarcodeGeneratorPNG();

            $barcodeDir = __DIR__ . "/storage/barcodes/";
            if (!is_dir($barcodeDir)) {
                mkdir($barcodeDir, 0777, true);
            }

            // Skip header row
            for ($i = 1; $i < count($rows); $i++) {
                $design_name = trim($rows[$i][0] ?? '');
                $design_code = trim($rows[$i][1] ?? '');
                $garment_type = trim($rows[$i][2] ?? '');
                $garment_code = trim($rows[$i][3] ?? '');
                $colour = trim($rows[$i][4] ?? '');
                $colour_code = trim($rows[$i][5] ?? '');
                $size = trim($rows[$i][6] ?? '');
                $size_code = trim($rows[$i][7] ?? '');
                $price = trim($rows[$i][8] ?? 0);
                $mrp = trim($rows[$i][9] ?? 0);
                $code = trim($rows[$i][10] ?? '');
                $quantity = trim($rows[$i][11] ?? 0);
                $hsn_no = trim($rows[$i][12] ?? '');

                // If code is missing, generate one automatically
                if ($code === '') {
                    $code = "AUTO" . strtoupper(bin2hex(random_bytes(3)));
                }

                // Generate random PNG file name
                $randomFile = uniqid('barcode_', true) . "_" . bin2hex(random_bytes(5)) . ".png";
                $barcodeFile = $barcodeDir . $randomFile;

                // Generate barcode image
                generateLabelImage(
                    "Fireknøtt",  // Brand
                    $price,
                    $garment_type,
                    $code,
                    $size,
                    $colour,
                    "www.fireknott.com",
                    $barcodeFile
                );

                // Insert into DB
                $stmt = $pdo->prepare("
                    INSERT INTO products 
                    (design_name, design_code, garment_type, garment_code, colour, colour_code, size, size_code, price, mrp, code, quantity, hsn_no, barcode_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $design_name,
                    $design_code,
                    $garment_type,
                    $garment_code,
                    $colour,
                    $colour_code,
                    $size,
                    $size_code,
                    $price,
                    $mrp,
                    $code,
                    $quantity,
                    $hsn_no,
                    "storage/barcodes/" . $randomFile
                ]);
            }

            $message = "✅ All products imported & barcodes generated successfully!";
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
        }
    } else {
        $message = "❌ File not uploaded.";
    }
}

// Fetch with pagination
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

$totalPages = ceil($total / $limit);
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
            <a href="barcode.php">Barcode</a>
        </div>
    </div>

    <div class="container">

        <h2>Import Products & Generate Barcodes</h2>
        <p class="instructions">
            Select your product data file (.xlsx or .xls) and click the button to import all products and create
            barcodes automatically. The generated list will appear below.
        </p>
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                <button type="submit" class="btn" id="uploadBtn">
                    <span id="btnText">Upload & Generate</span>
                    <span id="btnSpinner" class="spinner hidden"></span>
                </button>
            </div>
        </form>

        <?php if (count($products) > 0): ?>
            <h3>Generated Barcodes</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Design Name</th>
                    <th>Design Code</th>
                    <th>Garment</th>
                    <th>Colour</th>
                    <th>Size</th>
                    <th>Price</th>
                    <th>MRP</th>
                    <th>Code</th>
                    <th>Quantity</th>
                    <th>HSN</th>
                    <th>Barcode</th>
                </tr>
                <?php foreach ($products as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['design_name']) ?></td>
                        <td><?= htmlspecialchars($row['design_code']) ?></td>
                        <td><?= htmlspecialchars($row['garment_type']) ?></td>
                        <td><?= htmlspecialchars($row['colour']) ?></td>
                        <td><?= htmlspecialchars($row['size']) ?></td>
                        <td><?= htmlspecialchars($row['price']) ?></td>
                        <td><?= htmlspecialchars($row['mrp']) ?></td>
                        <td><?= htmlspecialchars($row['code']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= htmlspecialchars($row['hsn_no']) ?></td>
                        <td><a href="<?= $row['barcode_path'] ?>" target="_blank"><img src="<?= $row['barcode_path'] ?>"
                                    width="150"></a></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="pagination" style="text-align:center; margin-top:20px;">
                <?php if ($page > 1): ?>
                    <a class="pagination-btn" href="?page=<?= $page - 1 ?>">Previous</a>
                <?php endif; ?>

                <?php
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($totalPages, $page + $range);

                if ($start > 1) {
                    echo '<a class="pagination-btn" href="?page=1">1</a>';
                    if ($start > 2)
                        echo ' ... ';
                }

                for ($p = $start; $p <= $end; $p++): ?>
                    <a class="pagination-btn <?= $p == $page ? 'btn-clear' : '' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
                <?php endfor;

                if ($end < $totalPages) {
                    if ($end < $totalPages - 1)
                        echo ' ... ';
                    echo '<a class="pagination-btn" href="?page=' . $totalPages . '">' . $totalPages . '</a>';
                }
                ?>

                <?php if ($page < $totalPages): ?>
                    <a class="pagination-btn" href="?page=<?= $page + 1 ?>">Next</a>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>

    <script>
        
        const form = document.querySelector("form");
        const uploadBtn = document.getElementById("uploadBtn");
        const btnText = document.getElementById("btnText");
        const btnSpinner = document.getElementById("btnSpinner");

        form.addEventListener("submit", function () {
            uploadBtn.disabled = true;
            btnText.textContent = "Uploading...";
            btnSpinner.classList.remove("hidden");
        });

    </script>


</body>

</html>