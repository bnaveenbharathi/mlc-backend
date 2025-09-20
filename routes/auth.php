<?php

error_reporting(0);
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once('../config/conn.php'); 


$input = json_decode(file_get_contents("php://input"), true);
$action = $_GET['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing action"]);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    if ($action === 'signup') {
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$name || !$email || !$password) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "All fields are required"]);
            exit();
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "Email already registered"]);
            exit();
        }

    
        $hash = password_hash($password, PASSWORD_BCRYPT);

      
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hash);
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "user" => [
                "id" => $stmt->insert_id,
                "name" => $name,
                "email" => $email
            ]
        ]);
        exit();

    } elseif ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Email and password required"]);
            exit();
        }

        $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "User not found"]);
            exit();
        }

        $user = $result->fetch_assoc();
        if (!password_verify($password, $user['password'])) {
            echo json_encode(["status" => "error", "message" => "Invalid password"]);
            exit();
        }

        echo json_encode([
            "status" => "success",
            "user" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "email" => $user['email']
            ]
        ]);
        exit();
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
