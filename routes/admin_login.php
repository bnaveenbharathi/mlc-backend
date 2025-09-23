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
$password = $data['password'] ?? null;

if (!$name || !$password) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing name or password"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    $stmt = $conn->prepare("SELECT id, password FROM admin WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
        exit();
    }
    $stmt->bind_result($admin_id, $hashed_password);
    $stmt->fetch();
    $stmt->close();

    if (password_verify($password, $hashed_password)) {
        echo json_encode(["status" => "success", "admin_id" => $admin_id, "message" => "Login successful"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    }
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
    exit();
}
// No closing PHP tag
