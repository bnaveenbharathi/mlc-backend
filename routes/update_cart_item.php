<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once("../config/conn.php");
$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$product_id = $data['product_id'] ?? null;
$quantity = $data['quantity'] ?? null;

if (!$user_id || !$product_id || $quantity === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Find pending order
    $stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    $order_id = null;
    if ($stmt->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "No pending order"]);
        exit();
    }
    $stmt->bind_result($order_id);
    $stmt->fetch();
    $stmt->close();

    if ($quantity > 0) {
        // Update or insert item
        $stmt_item = $conn->prepare("UPDATE order_items SET quantity = ? WHERE order_id = ? AND product_id = ?");
        $stmt_item->bind_param("iii", $quantity, $order_id, $product_id);
        $stmt_item->execute();
    } else {
        // Remove item
        $stmt_item = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND product_id = ?");
        $stmt_item->bind_param("ii", $order_id, $product_id);
        $stmt_item->execute();
    }

    echo json_encode(["status" => "success"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}