<?php
require_once("../pdf/fpdf.php");
require_once("../config/conn.php");

// Get orderID from GET or POST
$orderID = isset($_GET['orderID']) ? $_GET['orderID'] : (isset($_POST['orderID']) ? $_POST['orderID'] : null);
if (!$orderID) {
    die("Order ID is required.");
}

$db = new Database();
$conn = $db->connect();

// Fetch order, user, and products info
$stmt = $conn->prepare("SELECT o.id, o.orderID, o.total_amount, o.created_at, o.delivery_status, o.payment_status, o.user_id, u.name, u.phone, u.address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.orderID = ? LIMIT 1");
$stmt->bind_param("s", $orderID);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
if (!$order) die("Order not found.");

// Fetch products for this order
$stmt2 = $conn->prepare("SELECT oi.quantity, oi.price, oi.subtotal, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt2->bind_param("i", $order['id']);
$stmt2->execute();
$res2 = $stmt2->get_result();
$products = [];
while ($row = $res2->fetch_assoc()) {
    $products[] = $row;
}

// Delivery amount (default, not from backend)
$delivery_amount = 50;

$pdf = new FPDF();
$pdf->AddPage();

// Logo (place your logo in the pdf/ folder as logo.png)
$logoPath = __DIR__ . '/logo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath,10,6,30);
} else {
    $pdf->SetFont('Arial','I',10);
    $pdf->SetTextColor(150,150,150);
    $pdf->Cell(0,8,'[Logo missing]',0,1,'L');
    $pdf->SetTextColor(0,0,0);
}

// Title
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Magic Light Crackers',0,1,'C');
$pdf->Ln(2);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,'Order Invoice',0,1,'C');
$pdf->Ln(5);

// Order and user info
$pdf->SetFont('Arial','',11);
$pdf->Cell(100,8,'Order ID: ' . $order['orderID'],0,0);
$pdf->Cell(0,8,'Order Date: ' . date('d-m-Y', strtotime($order['created_at'])),0,1);
$pdf->Cell(100,8,'Name: ' . $order['name'],0,0);
$pdf->Cell(0,8,'Phone: ' . $order['phone'],0,1);
$pdf->Cell(0,8,'Address: ' . $order['address'],0,1);
$pdf->Ln(5);

// Table header
$pdf->SetFont('Arial','B',11);
$pdf->Cell(10,8,'',1); // For box icon
$pdf->Cell(70,8,'Product',1);
$pdf->Cell(25,8,'Quantity',1);
$pdf->Cell(35,8,'Price',1);
$pdf->Cell(40,8,'Subtotal',1);
$pdf->Ln();

// Table rows
$pdf->SetFont('Arial','',11);
foreach ($products as $prod) {
    $pdf->Cell(10,8,chr(0xA0).chr(0xA0).'☐',1,0,'C'); // Unicode box icon
    $pdf->Cell(70,8,$prod['product_name'],1);
    $pdf->Cell(25,8,$prod['quantity'],1,0,'C');
    $pdf->Cell(35,8,number_format($prod['price'],2),1,0,'R');
    $pdf->Cell(40,8,number_format($prod['subtotal'],2),1,0,'R');
    $pdf->Ln();
}


// Totals
$pdf->SetFont('Arial','B',11);
$pdf->Cell(140,8,'Delivery Amount',1);
$pdf->Cell(40,8,'',1,1,'R');
$pdf->Cell(140,8,'Total Amount',1);
$pdf->Cell(40,8,number_format($order['total_amount'],2),1,1,'R');

$pdf->Ln(10);
$pdf->SetFont('Arial','I',10);
$pdf->Cell(0,8,'Thank you for shopping with Magic Light Crackers!',0,1,'C');

$pdf->Output('I', 'Invoice_'.$order['orderID'].'.pdf');
