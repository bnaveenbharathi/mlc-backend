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

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Fetch pending order for this user
    $stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => "success", "items" => []]);
        exit();
    }

    $order = $res->fetch_assoc();
    $order_id = $order['id'];

    // Fetch order items
    $stmt_items = $conn->prepare("
        SELECT oi.product_id, oi.quantity, oi.price, p.name 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $res_items = $stmt_items->get_result();

    $items = [];
    while ($row = $res_items->fetch_assoc()) {
        $items[] = [
            "product_id" => (int)$row['product_id'],
            "name" => $row['name'],
            "quantity" => (int)$row['quantity'],
            "price" => (float)$row['price']
        ];
    }

    echo json_encode(["status" => "success", "items" => $items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
