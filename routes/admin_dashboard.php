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
if (!isset($data['admin_token']) || $data['admin_token'] !== 'demo_token') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Total orders
    $totalOrders = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'];

    // Pending orders
    $pendingOrders = $conn->query("SELECT COUNT(*) as pending FROM orders WHERE delivery_status = 'pending'")->fetch_assoc()['pending'];

    // Total payment (sum only completed orders)
    $totalPayment = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0;

    // Monthly stats (orders and payments for each month)
    $monthlyStats = [];
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as orders,
            SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as payment
        FROM orders
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12
    ");
    while ($row = $result->fetch_assoc()) {
        $monthlyStats[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "total_orders" => $totalOrders,
            "pending_orders" => $pendingOrders,
            "total_payment" => $totalPayment,
            "monthly_stats" => $monthlyStats
        ]
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