<?php
// Always send CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json");
    http_response_code(200);
    exit();
}
require_once("../config/conn.php");

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);

$name = $data['name'];
$email = $data['email'];
$phone = $data['phone'];
$address = $data['address'];
$info = $data['information'];
$orderItems = $data['orderItems']; // array: [{product_id, quantity, price, subtotal}]

try {
    // 1. Save user (insert or update)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Prepare failed (user select)", "mysql_error" => $conn->error]);
        exit();
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "User select execute failed", "mysql_error" => $stmt->error]);
        exit();
    }
    $stmt->store_result();
    $user_id = null;
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id);
        $stmt->fetch();
        // Optionally update user info here
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, address, info) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(["status" => "error", "message" => "Prepare failed (user insert)", "mysql_error" => $conn->error]);
            exit();
        }
        $stmt->bind_param("sssss", $name, $email, $phone, $address, $info);
        if (!$stmt->execute()) {
            echo json_encode(["status" => "error", "message" => "User insert execute failed", "mysql_error" => $stmt->error]);
            exit();
        }
        $user_id = $stmt->insert_id;
    }
    $stmt->close();

    // 2. Create order
    $total_amount = array_sum(array_column($orderItems, 'subtotal'));
    $order_status = 'completed';
    $delivery_status = 'processing';
    $payment_status = 'processing';

    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, delivery_status, payment) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Prepare failed (order insert)", "mysql_error" => $conn->error]);
        exit();
    }
    $stmt->bind_param("idsss", $user_id, $total_amount, $order_status, $delivery_status, $payment_status);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Order insert execute failed", "mysql_error" => $stmt->error]);
        exit();
    }
    $order_id = $stmt->insert_id;
    $stmt->close();

    // 3. Save order items with error checking
    if (is_array($orderItems) && count($orderItems) > 0) {
        $item_status = 'completed';
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(["status" => "error", "message" => "Prepare failed (order_items insert)", "mysql_error" => $conn->error]);
            exit();
        }
        foreach ($orderItems as $item) {
            $product_id = (int)$item['product_id'];
            $quantity = (int)$item['quantity'];
            $price = (float)$item['price'];
            $subtotal = (float)$item['subtotal'];
            $stmt->bind_param("iiidds", $order_id, $product_id, $quantity, $price, $subtotal, $item_status);
            if (!$stmt->execute()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Order item insert failed",
                    "mysql_error" => $stmt->error,
                    "payload" => $item
                ]);
                $stmt->close();
                exit();
            }
        }
        $stmt->close();
    }

    echo json_encode(["status" => "success", "order_id" => $order_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}