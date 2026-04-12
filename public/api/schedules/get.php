<?php
// api/schedules/get.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if($user_id) {
    $query = "SELECT s.*, m.name as medication_name 
              FROM schedules s 
              JOIN medications m ON s.medication_id = m.id 
              WHERE s.user_id = :user_id 
              ORDER BY s.reminder_time ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    http_response_code(200);
    echo json_encode($schedules);
} else {
    http_response_code(404);
    echo json_encode(array('message' => 'No schedules found.'));
}
