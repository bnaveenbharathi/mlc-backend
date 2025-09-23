<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once("../config/conn.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing order_id"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    $order_id = intval($data['order_id']);

    // Handle update if payment_status or delivery_status is provided
    $updateFields = [];
    $params = [];
    $types = '';
    if (isset($data['payment_status'])) {
        $updateFields[] = 'payment = ?';
        $params[] = $data['payment_status'];
        $types .= 's';
    }
    if (isset($data['delivery_status'])) {
        $updateFields[] = 'delivery_status = ?';
        $params[] = $data['delivery_status'];
        $types .= 's';
    }
    if (!empty($updateFields)) {
        $params[] = $order_id;
        $types .= 'i';
        $sql = "UPDATE orders SET ".implode(', ', $updateFields).", updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    $orderRes = $conn->query("SELECT * FROM orders WHERE id = $order_id");
    $order = $orderRes->fetch_assoc();

    $products = [];
    $prodRes = $conn->query("
        SELECT oi.product_id, oi.quantity, oi.price, oi.subtotal, p.name as product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = $order_id
    ");
    while ($row = $prodRes->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "order" => $order,
        "products" => $products
    ]);
    exit();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
    exit();
}
// No closing PHP tag
