<?php
require 'db.php';
include 'functions.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT o.*, v.vendor_name FROM orders o  JOIN vendor_master v ON o.vendor_id = v.id WHERE o.id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if ($order) {
        $html = "
        <h2 style='text-align:center;'>Product Barcode</h2>
        <div style='text-align:center;margin-bottom:20px;'>
            <strong>{$order['design_name']} ({$order['size']} - {$order['colour']})</strong><br>
            <img src='" . getBaseUrl() . $order['barcode_url'] . "' style='margin-top:10px;max-width:200px;'>
        </div>";
    } else {
        $html = "<p>No product found.</p>";
    }
} elseif (isset($_POST['select_all']) && $_POST['select_all'] == "1") {
    // Fetch all records
    $stmt = $pdo->query("SELECT o.*, v.vendor_name FROM orders o JOIN vendor_master v ON o.vendor_id = v.id ORDER BY o.id DESC");
    $orders = $stmt->fetchAll();
    $html = "<h2 style='text-align:center;'>Product Barcodes</h2>";
    foreach ($orders as $order) {
        $html .= "
            <div style='text-align:center;margin-bottom:40px;'>
                <strong>{$order['design_name']} ({$order['size']} - {$order['colour']})</strong><br>
                <img src='" . getBaseUrl() . $order['barcode_url'] . "' style='margin-top:10px;max-width:200px;'>
            </div>
            <hr>";
    }
} elseif (isset($_POST['selected']) && is_array($_POST['selected'])) {
    $ids = array_map('intval', $_POST['selected']);
    if (count($ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT o.*, v.vendor_name FROM orders o JOIN vendor_master v ON o.vendor_id = v.id WHERE o.id IN ($placeholders)");
        $stmt->execute($ids);
        $orders = $stmt->fetchAll();

        $html = "<h2 style='text-align:center;'>Product Barcodes</h2>";
        foreach ($orders as $order) {
            $html .= "
            <div style='text-align:center;margin-bottom:40px;'>
                <strong>{$order['design_name']} ({$order['size']} - {$order['colour']})</strong><br>
                <img src='" . getBaseUrl() . $order['barcode_url'] . "' style='margin-top:10px;max-width:200px;'>
            </div>
            <hr>";
        }
    } else {
        $html = "<p>No products selected.</p>";
    }
} else {
    $html = "<p>Invalid request.</p>";
}


// ✅ Generate PDF
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Stream to browser
$dompdf->stream("barcodes.pdf", ["Attachment" => true]);
