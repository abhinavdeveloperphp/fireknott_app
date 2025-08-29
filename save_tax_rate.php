<?php
include 'db.php';
header('Content-Type: application/json');

$collection_id = $_POST['collection_id'] ?? null;
$tax_rate = $_POST['tax_rate'] ?? null;
$priority = $_POST['priority'] ?? null;
$effective_from = $_POST['effective_from'] ?? null;

if (!$collection_id || !is_numeric($tax_rate) || !is_numeric($priority) || !$effective_from) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Check for existing row with same collection_id and date
    $stmt = $pdo->prepare("SELECT id FROM tax_rates WHERE collection_id = :collection_id AND effective_from = :effective_from");
    $stmt->execute([
        ':collection_id' => $collection_id,
        ':effective_from' => $effective_from
    ]);

    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing row instead of inserting
        $stmt = $pdo->prepare("UPDATE tax_rates 
                               SET tax_rate = :tax_rate, priority = :priority 
                               WHERE collection_id = :collection_id AND effective_from = :effective_from");

        $stmt->execute([
            ':tax_rate' => $tax_rate,
            ':priority' => $priority,
            ':collection_id' => $collection_id,
            ':effective_from' => $effective_from
        ]);

        echo json_encode(['success' => true, 'message' => 'Tax rate updated']);
        exit;
    }

    // Check if priority is already used (optional check)
    $stmt = $pdo->prepare("SELECT id FROM tax_rates WHERE priority = :priority AND collection_id != :collection_id");
    $stmt->execute([
        ':priority' => $priority,
        ':collection_id' => $collection_id
    ]);

    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Priority already in use']);
        exit;
    }

    // Insert new rate
    $stmt = $pdo->prepare("INSERT INTO tax_rates (collection_id, tax_rate, priority, effective_from)
                           VALUES (:collection_id, :tax_rate, :priority, :effective_from)");

    $stmt->execute([
        ':collection_id' => $collection_id,
        ':tax_rate' => $tax_rate,
        ':priority' => $priority,
        ':effective_from' => $effective_from
    ]);

    echo json_encode(['success' => true, 'message' => 'Tax rate saved']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
