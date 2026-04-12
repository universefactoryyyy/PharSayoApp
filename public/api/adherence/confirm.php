<?php
// api/adherence/confirm.php
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

if (!empty($data->user_id) && !empty($data->medication_id) && isset($data->taken) && isset($data->scheduled_time) && $data->scheduled_time !== '') {
    $query = "INSERT INTO adherence_logs SET 
                user_id=:user_id, 
                medication_id=:medication_id, 
                scheduled_time=:scheduled_time, 
                taken=:taken,
                responded_at=NOW()";

    $stmt = $db->prepare($query);

    $stmt->bindParam(":user_id", $data->user_id);
    $stmt->bindParam(":medication_id", $data->medication_id);
    $stmt->bindParam(":scheduled_time", $data->scheduled_time);
    $stmt->bindParam(":taken", $data->taken, PDO::PARAM_BOOL);

    if($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array('message' => 'Medication intake confirmed.'));
    } else {
        http_response_code(503);
        echo json_encode(array('message' => 'Unable to confirm intake.'));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Unable to log adherence. Data is incomplete."));
}
