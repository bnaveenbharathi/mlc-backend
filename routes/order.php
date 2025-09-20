<?php


// CORS & headers
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once("../config/conn.php");
$db = new Database();
$conn = $db->connect();

// Get user_id from either POST body or URL query string

$data = json_decode(file_get_contents("php://input"), true);
$user_id = null;
if (isset($data['user_id'])) {
    $user_id = $data['user_id'];
} elseif (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
}

// If address is provided, update it in users table

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit();
}



try {
    // 1. Find the latest pending order for the user, including orderID

    $stmt = $conn->prepare("SELECT id, orderID, total_amount FROM orders WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "No pending order found."]);
        exit();
    }

    $order = $res->fetch_assoc();
    $order_id = $order['id'];
    $orderID = $order['orderID'];
    $total_amount = $order['total_amount'];


    // 2. Recalculate total_amount from order_items
    $stmt_sum = $conn->prepare("SELECT SUM(subtotal) as total FROM order_items WHERE order_id = ?");
    $stmt_sum->bind_param("i", $order_id);
    $stmt_sum->execute();
    $res_sum = $stmt_sum->get_result();
    $row_sum = $res_sum->fetch_assoc();
    $new_total = $row_sum['total'] ?? 0;

    // 3. Update order status to completed and total_amount
    $stmt_update = $conn->prepare("UPDATE orders SET status = 'completed', total_amount = ?, updated_at = NOW() WHERE id = ?");
    $stmt_update->bind_param("di", $new_total, $order_id);
    $stmt_update->execute();

    // 3. Update all order_items status to completed
    $stmt_items = $conn->prepare("UPDATE order_items SET status = 'completed', updated_at = NOW() WHERE order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();

    // 4. Optionally, update order_items with orderID if schema supports it (not typical, but for your request)
    // $stmt_update_items = $conn->prepare("UPDATE order_items SET orderID = ? WHERE order_id = ?");
    // $stmt_update_items->bind_param("si", $orderID, $order_id);
    // $stmt_update_items->execute();

if (isset($data['address']) && $user_id) {
    $address = $data['address'];
    $stmt_addr = $conn->prepare("UPDATE users SET address = ? WHERE id = ?");
    $stmt_addr->bind_param("si", $address, $user_id);
    $stmt_addr->execute();
}

    echo json_encode([
        "status" => "success",
        "message" => "Order completed successfully.",
        "order_id" => $order_id,
        "orderID" => $orderID,
        "total_amount" => $total_amount
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
// NOTE: No closing PHP tag to avoid accidental whitespace output
