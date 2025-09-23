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
$name = $data['name'] ?? '';
$password = $data['password'] ?? '';

if (!$name || !$password) {
    echo json_encode(["status" => "error", "message" => "Name and password required"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT id, password FROM admin WHERE name = ?");
$stmt->bind_param("s", $name);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Admin not found"]);
    exit();
}

$stmt->bind_result($admin_id, $hash);
$stmt->fetch();

if (!password_verify($password, $hash)) {
    echo json_encode(["status" => "error", "message" => "Invalid password"]);
    exit();
}

// Generate a session token (simple random string, you can use JWT or more secure methods)
$token = bin2hex(random_bytes(32));
$stmt->close();

echo json_encode([
    "status" => "success",
    "admin_id" => $admin_id,
    "token" => $token
]);
exit();
?>