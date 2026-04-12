<?php
// api/test.php
include_once __DIR__ . '/config/cors.php';
include_once __DIR__ . '/config/db.php';

$database = new Database();
$db = $database->getConnection();

$response = [
    "status" => "success",
    "message" => "PharSayo API is connected!",
    "database" => ($db !== null ? "Connected" : "Failed"),
    "server_time" => date("Y-m-d H:i:s"),
    "cors_headers" => "Active"
];

echo json_encode($response);
