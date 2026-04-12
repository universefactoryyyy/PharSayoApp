<?php
// api/config/db.php

class Database {
    // IMPORTANT: UPDATE THESE FOR YOUR AWARDSPACE DATABASE
    // Host is usually: pdbX.awardspace.net
    private $host = "fdb1033.awardspace.net";
    private $db_name = "4750319_pharsayo";
    private $username = "4750319_pharsayo";
    private $password = "pharsay0";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            // AWARDSPACE NOTE: If running on AwardSpace, "localhost" might not work.
            // Use your "Database Host" from the Control Panel (e.g., pdbX.awardspace.net)
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *'); // Ensure CORS headers are even on errors
            http_response_code(500);
            
            $msg = $exception->getMessage();
            $help = "";
            if (strpos($msg, "Access denied") !== false) {
                $help = " Check your username and password in db.php.";
            } else if (strpos($msg, "getaddrinfo failed") !== false || strpos($msg, "Unknown MySQL server host") !== false) {
                $help = " Your database host ($this->host) is incorrect. Use your AwardSpace DB Host.";
            } else if (strpos($msg, "Connection refused") !== false || strpos($msg, "timed out") !== false || strpos($msg, "No such file or directory") !== false) {
                $help = " Database host is unreachable. On AwardSpace, do NOT use 'localhost'. Use your 'Database Host' (e.g., pdbX.awardspace.net) from the Control Panel.";
            }

            echo json_encode(array(
                'status' => 'error',
                'message' => 'Database connection error: ' . $msg . $help,
                'debug_info' => [
                    'host' => $this->host,
                    'db' => $this->db_name,
                    'user' => $this->username
                ]
            ));
            exit;
        }

        return $this->conn;
    }
}
