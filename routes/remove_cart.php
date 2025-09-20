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
$user_id = isset($data['user_id']) ? $data['user_id'] : null;
$product_id = isset($data['product_id']) ? $data['product_id'] : null;

if (!$user_id || !$product_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing user_id or product_id"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Get the latest pending order for the user
    $stmt_order = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt_order->bind_param("i", $user_id);
    $stmt_order->execute();
    $res_order = $stmt_order->get_result();
    if ($res_order->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "No pending order found for user"]);
        exit();
    }
    $order = $res_order->fetch_assoc();
    $order_id = $order['id'];

    // Remove the item from the order_items table for this order
    $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $order_id, $product_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Item removed from cart"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Item not found in cart"]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
    exit();
}
// No closing PHP tag
