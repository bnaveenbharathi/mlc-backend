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
$user_id = null;
if (isset($data['user_id'])) {
    $user_id = $data['user_id'];
} elseif (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
}

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Fetch all orders for the user with order details
    $stmt = $conn->prepare("
        SELECT o.id as order_id, o.total_amount, o.created_at, o.updated_at, o.delivery_status, o.payment ,
               oi.product_id, oi.quantity, oi.price, oi.subtotal, oi.created_at as item_created_at, oi.updated_at as item_updated_at,
               p.name as product_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ? AND o.status = 'completed'
        ORDER BY o.created_at DESC, oi.created_at ASC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $orders = [];
    while ($row = $res->fetch_assoc()) {
        $order_id = $row['order_id'];
        if (!isset($orders[$order_id])) {
            $orders[$order_id] = [
                "order_id" => $order_id,
                "total_amount" => $row['total_amount'],
                "order_placed_at" => $row['created_at'],
                "delivery_status" => $row['delivery_status'],
                "delivery_date" => $row['updated_at'],
                "payment_status" => $row['payment'],
                "products" => []
            ];
        }
        $orders[$order_id]['products'][] = [
            "product_id" => $row['product_id'],
            "product_name" => $row['product_name'],
            "price" => $row['price'],
            "quantity" => $row['quantity'],
            "subtotal" => $row['subtotal'],
            "item_created_at" => $row['item_created_at'],
            "item_updated_at" => $row['item_updated_at']
        ];
    }
    // Re-index orders numerically
    $orders = array_values($orders);

    echo json_encode([
        "status" => "success",
        "orders" => $orders
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
