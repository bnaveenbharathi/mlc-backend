
<?php
// products.php
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
  $result = $conn->query("SELECT id, name, description, price, category_id, created_at FROM products ORDER BY id DESC");
  $products = [];
  while ($row = $result->fetch_assoc()) {
    $products[] = $row;
  }
  echo json_encode(['status' => 'success', 'products' => $products]);
  exit();
}

if ($action === 'add') {
  $name = trim($data['name'] ?? '');
  $description = trim($data['description'] ?? '');
  $price = floatval($data['price'] ?? 0);
  $category_id = intval($data['category_id'] ?? 0);
  if ($name === '' || $description === '' || $price <= 0 || $category_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields required.']);
    exit();
  }
  $stmt = $conn->prepare('INSERT INTO products (name, description, price, category_id, created_at) VALUES (?, ?, ?, ?, NOW())');
  $stmt->bind_param('ssdi', $name, $description, $price, $category_id);
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add product.']);
  }
  exit();
}

if ($action === 'update') {
  $id = intval($data['id'] ?? 0);
  $name = trim($data['name'] ?? '');
  $description = trim($data['description'] ?? '');
  $price = floatval($data['price'] ?? 0);
  $category_id = intval($data['category_id'] ?? 0);
  if ($id <= 0 || $name === '' || $description === '' || $price <= 0 || $category_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit();
  }
  $stmt = $conn->prepare('UPDATE products SET name = ?, description = ?, price = ?, category_id = ? WHERE id = ?');
  $stmt->bind_param('ssdii', $name, $description, $price, $category_id, $id);
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update product.']);
  }
  exit();
}

if ($action === 'delete') {
  $id = intval($data['id'] ?? 0);
  if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID.']);
    exit();
  }
  $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
  $stmt->bind_param('i', $id);
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete product.']);
  }
  exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
exit();
// No closing PHP tag
