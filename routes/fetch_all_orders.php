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


$db = new Database();
$conn = $db->connect();

try {
    $orders = [];
    $sql = "SELECT o.*, u.name as user_name, u.phone as user_phone,u.address as user_address FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
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
