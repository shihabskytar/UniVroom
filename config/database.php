<?php
/**
 * Database Configuration
 * UniVroom - By Students, For Students
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'univroom';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Database connection helper function
function getDB() {
    $database = new Database();
    return $database->getConnection();
}
?>
