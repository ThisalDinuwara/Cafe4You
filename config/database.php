<?php
// config/database.php
class Database {
    private $host = "localhost";
    private $database_name = "restaurant_db";
    private $username = "root";
    private $password = "Dinuwara@2005";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->database_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>