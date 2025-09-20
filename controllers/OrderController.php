<?php
class OrderController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createOrder($data) {
        $email = mysqli_real_escape_string($this->conn, $data['email']);
        $phone = mysqli_real_escape_string($this->conn, $data['phone']);
        $address = mysqli_real_escape_string($this->conn, $data['address']);
        $items = $data['items'];
        $total_amount = floatval($data['total_amount']);

        // generate unique user_id and password
        $user_id = uniqid('U');
        $password = bin2hex(random_bytes(4)); // simple 8-char password

        mysqli_begin_transaction($this->conn);

        try {
            // insert order
            $order_sql = "INSERT INTO orders (user_id, email, phone, address, total_amount, status, payment_method, password, created_at, updated_at)
                          VALUES ('$user_id', '$email', '$phone', '$address', $total_amount, 'pending', 'whatsapp', '$password', NOW(), NOW())";

            if (!mysqli_query($this->conn, $order_sql)) {
                throw new Exception("Order insert failed: " . mysqli_error($this->conn));
            }

            $order_id = mysqli_insert_id($this->conn);

            // insert items
            foreach ($items as $item) {
                $product_id = intval($item['product_id']);
                $quantity = intval($item['quantity']);
                $price = floatval($item['price']);
                $subtotal = $price * $quantity;

                $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, created_at, updated_at)
                             VALUES ($order_id, $product_id, $quantity, $price, $subtotal, NOW(), NOW())";

                if (!mysqli_query($this->conn, $item_sql)) {
                    throw new Exception("Item insert failed: " . mysqli_error($this->conn));
                }
            }

            mysqli_commit($this->conn);

            return [
                "success" => true,
                "message" => "Order placed successfully",
                "order_id" => $order_id,
                "user_id" => $user_id,
                "password" => $password
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function getOrder($user_id, $password) {
        $user_id = mysqli_real_escape_string($this->conn, $user_id);
        $password = mysqli_real_escape_string($this->conn, $password);

        $sql = "SELECT * FROM orders WHERE user_id='$user_id' AND password='$password'";
        $res = mysqli_query($this->conn, $sql);

        if (!$res || mysqli_num_rows($res) === 0) {
            return ["success" => false, "error" => "Order not found"];
        }

        $order = mysqli_fetch_assoc($res);

        $items_sql = "SELECT * FROM order_items WHERE order_id=" . intval($order['id']);
        $items_res = mysqli_query($this->conn, $items_sql);

        $items = [];
        while ($row = mysqli_fetch_assoc($items_res)) {
            $items[] = $row;
        }

        $order['items'] = $items;
        return ["success" => true, "order" => $order];
    }
}
