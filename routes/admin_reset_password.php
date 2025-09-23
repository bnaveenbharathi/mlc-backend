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
$name = $data['name'] ?? null;
$new_password = $data['password'] ?? null;

if (!$name || !$new_password) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing name or new password"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    $stmt = $conn->prepare("SELECT id FROM admin WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Admin not found"]);
        exit();
    }
    $stmt->bind_result($admin_id);
    $stmt->fetch();
    $stmt->close();

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $admin_id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Password reset successful"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Password reset failed", "mysql_error" => $stmt->error]);
    }
    $stmt->close();
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
    exit();
}
// No closing PHP tag
