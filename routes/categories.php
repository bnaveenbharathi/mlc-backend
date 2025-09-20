<?php
// categories.php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: https://www.magiclightcrackers.com/");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once("../config/conn.php");
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? 'fetch';
$db = new Database();
$conn = $db->connect();

if ($action === 'fetch') {
    $result = $conn->query("SELECT id, name, description FROM categories ORDER BY id DESC");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    echo json_encode(['status' => 'success', 'categories' => $categories]);
    exit();
}

if ($action === 'add') {
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    if ($name === '' || $description === '') {
        echo json_encode(['status' => 'error', 'message' => 'Name and description required.']);
        exit();
    }
    $stmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
    $stmt->bind_param('ss', $name, $description);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add category.']);
    }
    exit();
}

if ($action === 'update') {
    $id = intval($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    if ($id <= 0 || $name === '' || $description === '') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
        exit();
    }
    $stmt = $conn->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
    $stmt->bind_param('ssi', $name, $description, $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update category.']);
    }
    exit();
}

if ($action === 'delete') {
    $id = intval($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid category ID.']);
        exit();
    }
    $stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete category.']);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
exit();
// No closing PHP tag
