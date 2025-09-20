<?php

class ProductController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllProducts() {
        $sql = "SELECT 
                    p.id, 
                    p.name, 
                    p.description, 
                    p.price, 
                    p.per, 
                    p.is_featured, 
                    p.stock, 
                    p.created_at,
                    c.name AS category_name,
                    c.id AS category_id
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id";

        $result = mysqli_query($this->conn, $sql);
        $products = [];

        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $products[] = [
                    "id"            => (int)$row["id"],
                    "name"          => $row["name"],
                    "description"   => $row["description"],
                    "price"         => (float)$row["price"],
                    "originalPrice" => (float)$row["price"] + 30,
                    "category"      => $row["category_name"] ?? "Others",
                    "category_id"   => (int)$row["category_id"],
                  
                    "badge"         => $row["is_featured"] ? "Featured" : null,
         
                    "isPopular"     => (bool)$row["is_featured"],
                    "stock"         => (int)$row["stock"],
                    "per"           => $row["per"],
                    "created_at"    => $row["created_at"]
                ];
            }
        }

        return $products;
    }
}
