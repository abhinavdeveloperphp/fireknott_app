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
            $pan = trim($sheet->getCell("E{$i}")->getValue());
            $address = trim($sheet->getCell("F{$i}")->getValue());
            $remarks = trim($sheet->getCell("G{$i}")->getValue());

            if ($vendor_name === "")
                continue;

            $stmt = $pdo->prepare("INSERT INTO vendor_master (vendor_name, pan, contact_person_name, email, phone, address, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$vendor_name, $pan, $contact_person_name, $email, $phone, $address, $remarks]);
        }
        $message = "✅ Vendor sheet imported successfully!";
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_name'])) {
    $id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
    $vendor_name = $_POST['vendor_name'];
    $pan = $_POST['pan'];
    $contact_person_name = $_POST['contact_person_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $remarks = $_POST['remarks'];

    if ($id > 0) {
        // 🔹 UPDATE existing vendor
        $stmt = $pdo->prepare("UPDATE vendor_master 
                               SET vendor_name=?, pan=?, contact_person_name=?, email=?, phone=?, address=?, remarks=? 
                               WHERE id=?");
        $stmt->execute([$vendor_name, $pan, $contact_person_name, $email, $phone, $address, $remarks, $id]);
        $message = "✅ Vendor updated successfully!";
    } else {
        // 🔹 CREATE new vendor
        $stmt = $pdo->prepare("INSERT INTO vendor_master 
                               (vendor_name, pan, contact_person_name, email, phone, address, remarks) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$vendor_name, $pan, $contact_person_name, $email, $phone, $address, $remarks]);
        $message = "✅ Vendor created successfully!";
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
            font-size: 16px;
        }

        .action-btn {
            padding: 6px 12px;
            background: #e53935;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: 0;
            font-size: 16px;
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
            max-width: 1500px;
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
            padding: 12px;
            letter-spacing: 0.5px;
        }

        td {
            border: 1px solid #e6e6e6;
            padding: 12px;
            font-size: 16px;
            text-transform: capitalize;
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
            margin: 0px;
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
            font-size: 16px;
            padding: 10px 40px;
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
            margin: 0px;
            font-size: 16px;
            padding: 5px 10px;
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
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 5px;
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
            padding: 10px 18px;
            text-decoration: none;
            display: block;
            font-weight: normal;
            margin: 0;
            text-align: left;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        td .action-btns {
            display: flex;
            align-items: center;
            justify-content: start;
        }

        .form-button {
            background: #fafafa;
            padding: 20px;
            margin-top: :20px;
            border-radius: 8px;
            border: 1px solid #eeeeeeff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .responsive-table table {
            min-width: 1360px;
        }

        .overflow-auto {
            overflow: auto;
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
        <div class="form-button">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                    <button type="submit" class="btn" id="uploadBtn">
                        <span id="btnText">Upload & Generate</span>
                        <span id="btnSpinner" class="spinner hidden"></span>
                    </button>
                </div>
            </form>
            <button class="btn" onclick="openModal('create')">+ Add New Vendor</button>
        </div>
        <p><?= $message ?></p>
        <div class="responsive-table overflow-auto">
            <table border="1">
                <tr>
                    <th>ID</th>
                    <th>Vendor Name</th>
                    <th>Contact Person Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>PAN</th>
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
                            <td><?= htmlspecialchars($row['pan']) ?></td>
                            <td><?= htmlspecialchars($row['address']) ?></td>
                            <td><?= htmlspecialchars($row['remarks']) ?></td>
                            <td>
                                <div class="action-btns">

                                    <a href="?delete_id=<?= $row['id'] ?>" class="action-btn"
                                        onclick="return confirm('Delete this vendor?')">Delete</a>
                                    <button type="button" class="action-btn" style="background:#1e88e5;margin-left:5px;"
                                        onclick="openModal('edit', <?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No Data found. </td>
                    </tr>
                <?php endif; ?>

            </table>
        </div>

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

    <?php include 'vendor_modal.php'; ?>

    <script>
        // Open modal (Create or Edit)
        function openModal(mode, vendorData = null) {
            const modal = document.getElementById("editModal");
            const modalTitle = document.getElementById("modalTitle");
            const form = document.getElementById("editForm");

            if (mode === "create") {
                modalTitle.textContent = "Add New Vendor";
                form.reset(); // clear old data
                document.getElementById("vendor_id").value = ""; // no ID for new
            } else if (mode === "edit") {
                modalTitle.textContent = "Edit Vendor";
                // Fill form with vendor data
                document.getElementById("vendor_id").value = vendorData.id;
                document.getElementById("vendor_name").value = vendorData.vendor_name;
                document.getElementById("pan").value = vendorData.pan;
                document.getElementById("contact_person_name").value = vendorData.contact_person_name;
                document.getElementById("email").value = vendorData.email;
                document.getElementById("phone").value = vendorData.phone;
                document.getElementById("address").value = vendorData.address;
                document.getElementById("remarks").value = vendorData.remarks;
            }

            modal.classList.remove("hidden"); // show modal
        }

        // Close modal
        function closeEditModal() {
            document.getElementById("editModal").classList.add("hidden");
        }

        function validateForm() {
            let isValid = true;

            // Vendor Name
            const vendorName = document.getElementById("vendor_name");
            const vendorNameError = document.getElementById("vendor_name_error");
            if (vendorName.value.trim() === "") {
                vendorName.classList.add("error-input");
                vendorNameError.style.display = "block";
                isValid = false;
            } else {
                vendorName.classList.remove("error-input");
                vendorNameError.style.display = "none";
            }

            // Email
            const email = document.getElementById("email");
            const emailError = document.getElementById("email_error");
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value.trim())) {
                email.classList.add("error-input");
                emailError.style.display = "block";
                isValid = false;
            } else {
                email.classList.remove("error-input");
                emailError.style.display = "none";
            }

            // Phone
            const phone = document.getElementById("phone");
            const phoneError = document.getElementById("phone_error");
            const phoneRegex = /^[0-9]{7,15}$/;
            if (!phoneRegex.test(phone.value.trim())) {
                phone.classList.add("error-input");
                phoneError.style.display = "block";
                isValid = false;
            } else {
                phone.classList.remove("error-input");
                phoneError.style.display = "none";
            }

            return isValid;
        }

        // Attach validation to form submit
        document.getElementById("editForm").addEventListener("submit", function (e) {
            if (!validateForm()) {
                e.preventDefault(); // stop form submission if invalid
            }
        });
    </script>
</body>

</html>