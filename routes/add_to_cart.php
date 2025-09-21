<?php
// -------------------------
// add_to_cart.php
// -------------------------

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

// Parse JSON input
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

if (!$data) {
    error_log("add_to_cart.php: Invalid or missing JSON input: " . $raw_input);
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or missing JSON input"
    ]);
    exit();
}

$user_id    = isset($data['user_id']) ? (int)$data['user_id'] : null;
$product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
$quantity   = isset($data['quantity']) ? (int)$data['quantity'] : 1;
$price      = isset($data['price']) ? (float)$data['price'] : null;

// Validate required fields (null check instead of falsey check)
if ($user_id === null || $product_id === null || $price === null) {
    error_log("add_to_cart.php: Missing required fields: " . json_encode($data));
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields"
    ]);
    exit();
}

// Connect DB
$db = new Database();
$conn = $db->connect();

try {
    // 1. Find existing pending order for user
    $stmt_order = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt_order->bind_param("i", $user_id);
    $stmt_order->execute();
    $res_order = $stmt_order->get_result();

    if ($res_order->num_rows > 0) {
        $order = $res_order->fetch_assoc();
        $order_id = $order['id'];
    } else {
        // 2. Create new pending order
        $stmt_create = $conn->prepare("INSERT INTO orders (user_id, status, created_at, updated_at) VALUES (?, 'pending', NOW(), NOW())");
        $stmt_create->bind_param("i", $user_id);
        $stmt_create->execute();
        $order_id = $stmt_create->insert_id;
        $stmt_create->close();
    }
    $stmt_order->close();

    // 3. Check if product already exists in this order
    $stmt_check = $conn->prepare("SELECT id, quantity FROM order_items WHERE order_id = ? AND product_id = ?");
    $stmt_check->bind_param("ii", $order_id, $product_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        // Already in cart → update quantity & subtotal
        $item = $res_check->fetch_assoc();
        $new_quantity = $item['quantity'] + $quantity;
        $subtotal = $price * $new_quantity;

        $stmt_update = $conn->prepare("UPDATE order_items SET quantity = ?, subtotal = ?, updated_at = NOW() WHERE id = ?");
        $stmt_update->bind_param("idi", $new_quantity, $subtotal, $item['id']);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // Not in cart → insert new row
        $subtotal = $price * $quantity;
        $stmt_insert = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
        $stmt_insert->bind_param("iiidd", $order_id, $product_id, $quantity, $price, $subtotal);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $stmt_check->close();

    // Success response
    echo json_encode([
        "status" => "success",
        "message" => "Product added to cart",
        "order_id" => $order_id
    ]);
    exit();

} catch (Exception $e) {
    error_log("add_to_cart.php: Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error"
    ]);
    exit();
}

?>
