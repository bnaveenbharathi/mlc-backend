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

// Simple admin token check (replace with real auth in production)
$data = json_decode(file_get_contents("php://input"), true);

$db = new Database();
$conn = $db->connect();

try {
    $users = [];
    $userRes = $conn->query("SELECT id, name, email, phone, address FROM users");
    while ($user = $userRes->fetch_assoc()) {
        $user_id = $user['id'];

        // Total orders
        $orderCount = $conn->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id")->fetch_assoc()['total'];

        // Pending orders
        $pendingCount = $conn->query("SELECT COUNT(*) as pending FROM orders WHERE user_id = $user_id AND delivery_status = 'pending'")->fetch_assoc()['pending'];

        // Total payment (sum of completed orders)
        $totalPayment = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE user_id = $user_id AND status = 'completed'")->fetch_assoc()['total'] ?? 0;

        // List of orders with payment and status
        $orders = [];
        $orderRes = $conn->query("SELECT id, total_amount, status, delivery_status, created_at FROM orders WHERE user_id = $user_id ORDER BY created_at DESC");
        while ($order = $orderRes->fetch_assoc()) {
            $orders[] = [
                "order_id" => $order['id'],
                "total_amount" => $order['total_amount'],
                "status" => $order['status'],
                "delivery_status" => $order['delivery_status'],
                "created_at" => $order['created_at']
            ];
        }

        $users[] = [
            "id" => $user_id,
            "name" => $user['name'],
            "email" => $user['email'],
            "phone" => $user['phone'],
            "address" => $user['address'],
            "total_orders" => $orderCount,
            "pending_orders" => $pendingCount,
            "total_payment" => $totalPayment,
            "orders" => $orders
        ];
    }

    echo json_encode([
        "status" => "success",
        "users" => $users
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