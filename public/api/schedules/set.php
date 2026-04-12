<?php
// api/schedules/set.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->medication_id) && !empty($data->user_id) && !empty($data->reminder_time)) {
    $query = "INSERT INTO schedules SET 
                medication_id=:medication_id, 
                user_id=:user_id, 
                reminder_time=:reminder_time, 
                days_of_week=:days_of_week";

    $stmt = $db->prepare($query);

    $stmt->bindValue(":medication_id", (int)$data->medication_id, PDO::PARAM_INT);
    $stmt->bindValue(":user_id", (int)$data->user_id, PDO::PARAM_INT);
    $stmt->bindValue(":reminder_time", $data->reminder_time);
    $days = isset($data->days_of_week) ? $data->days_of_week : 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
    $stmt->bindValue(":days_of_week", $days);

    if($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array("message" => "Schedule was set."));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to set schedule."));
    }
} else {
        http_response_code(400);
        echo json_encode(array("message" => "Unable to set schedule. Data is incomplete."));
    }
