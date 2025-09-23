
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

$db = new Database();
$conn = $db->connect();

try {
	// Fetch all products with category name
	$stmt = $conn->prepare("SELECT p.id, p.name, p.price, p.category_id,p.per, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id");
	$stmt->execute();
	$stmt->store_result();
	$stmt->bind_result($id, $name, $price,  $category_id,$per, $category_name);
	$products = [];
	while ($stmt->fetch()) {
		$products[] = [
			"id" => $id,
			"name" => $name,
			"price" => $price,
			"per" => $per,
			
			"category" => $category_name ?? $category_id
		];
	}
	$stmt->close();

	echo json_encode([
		"status" => "success",
		"products" => $products
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
