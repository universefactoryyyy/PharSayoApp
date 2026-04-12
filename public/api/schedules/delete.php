<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../lib/role_helpers.php';

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents('php://input'));

$actorId = isset($data->actor_id) ? (int)$data->actor_id : 0;
$actorRole = isset($data->actor_role) ? (string)$data->actor_role : '';
$scheduleId = isset($data->schedule_id) ? (int)$data->schedule_id : 0;

if ($actorId < 1 || $scheduleId < 1) {
    http_response_code(400);
    echo json_encode(['message' => 'actor_id, actor_role, and schedule_id are required.']);
    exit;
}

$row = pharsayo_schedule_row_if_manageable($db, $scheduleId, $actorId, $actorRole);
if ($row === null) {
    http_response_code(403);
    echo json_encode(['message' => 'Not allowed to delete this schedule.']);
    exit;
}

$stmt = $db->prepare('DELETE FROM schedules WHERE id = :id');
$stmt->bindValue(':id', $scheduleId, PDO::PARAM_INT);
if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'Schedule deleted.', 'schedule_id' => $scheduleId]);
} else {
    http_response_code(503);
    echo json_encode(['message' => 'Delete failed.']);
}
