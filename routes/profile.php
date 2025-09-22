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
$user_id =  null;
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
    // Fetch user profile (assuming users table exists)

    $stmt_user = $conn->prepare("SELECT id, name, email,phone,address, created_at FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $stmt_user->store_result();
    $stmt_user->bind_result($id, $name, $email, $phone, $address, $created_at);
    $user = null;
    if ($stmt_user->num_rows > 0) {
        $stmt_user->fetch();
        $user = [
            "id" => $id,
            "name" => $name,
            "email" => $email,
            "phone" => $phone,
            "address" => $address,
            "created_at" => $created_at
        ];
    }
    $stmt_user->close();

    if (!$user) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit();
    }

    // Fetch all orders for the user
    $stmt_orders = $conn->prepare("SELECT id as order_id, total_amount, status, delivery_status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt_orders->bind_param("i", $user_id);
    $stmt_orders->execute();
    $stmt_orders->store_result();
    $stmt_orders->bind_result($order_id, $total_amount, $status, $delivery_status, $order_created_at);
    $orders = [];
    $pending_orders = [];
    $total_spends = 0;
    while ($stmt_orders->fetch()) {
        // Fetch total_products for this order
        $stmt_order_products = $conn->prepare("SELECT SUM(quantity) as total_products FROM order_items WHERE order_id = ?");
        $stmt_order_products->bind_param("i", $order_id);
        $stmt_order_products->execute();
        $stmt_order_products->store_result();
        $stmt_order_products->bind_result($total_products_order);
        $stmt_order_products->fetch();
        $stmt_order_products->close();
        $row = [
            "order_id" => $order_id,
            "total_amount" => $total_amount,
            "status" => $status,
            "delivery_status" => $delivery_status,
            "created_at" => $order_created_at,
            "total_products" => $total_products_order ?? 0
        ];
        $orders[] = $row;
        if ($status === 'pending') {
            $pending_orders[] = $row;
        }
        $total_spends += floatval($total_amount);
    }
    $stmt_orders->close();

    $total_orders = count($orders);

    // Calculate total number of products ordered by the user (sum of all order_items quantities)
    $stmt_products = $conn->prepare("SELECT SUM(quantity) as total_products FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)");
    $stmt_products->bind_param("i", $user_id);
    $stmt_products->execute();
    $stmt_products->store_result();
    $stmt_products->bind_result($total_products);
    $stmt_products->fetch();
    $stmt_products->close();

    // Collect all order_ids
    $order_ids = array_map(function($o) { return $o['order_id']; }, $orders);

    echo json_encode([
        "status" => "success",
        "profile" => $user,
        "orders" => $orders,
        "pending_orders" => $pending_orders,
        "total_orders" => $total_orders,
        "total_spends" => $total_spends,
        "total_products" => $total_products,
        "orderID" => $order_ids
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
