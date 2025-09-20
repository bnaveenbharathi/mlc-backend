<?php
class Database {
    private $host = "localhost";
    private $user = "gnthealt_mlc";
    private $pass = "gnthealt_mlc";
    private $db   = "gnthealt_mlc";

    
    // private $host = "localhost";
    // private $user = "root";
    // private $pass = "";
    // private $db   = "mlc";
    public $conn;

    public function connect() {
        $this->conn = mysqli_connect($this->host, $this->user, $this->pass, $this->db);

        if (!$this->conn) {
            die(json_encode(["error" => "DB Connection failed: " . mysqli_connect_error()]));
        }

        return $this->conn;
    }
}
