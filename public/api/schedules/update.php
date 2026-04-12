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
    echo json_encode(['message' => 'Not allowed to edit this schedule.']);
    exit;
}

$reminder_time = isset($data->reminder_time) ? trim((string)$data->reminder_time) : null;
$days = isset($data->days_of_week) ? trim((string)$data->days_of_week) : null;
$notes = (is_object($data) && property_exists($data, 'notes')) ? (string)$data->notes : null;

if ($reminder_time !== null && $reminder_time !== '') {
    if (preg_match('/^\d{1,2}:\d{2}$/', $reminder_time)) {
        $p = explode(':', $reminder_time);
        $reminder_time = str_pad($p[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($p[1], 2, '0', STR_PAD_LEFT) . ':00';
    }
}

$sets = [];
$params = [':id' => $scheduleId];
if ($reminder_time !== null && $reminder_time !== '') {
    $sets[] = 'reminder_time = :rt';
    $params[':rt'] = $reminder_time;
}
if ($days !== null && $days !== '') {
    $sets[] = 'days_of_week = :dow';
    $params[':dow'] = $days;
}
if ($notes !== null) {
    $sets[] = 'notes = :notes';
    $params[':notes'] = $notes;
}

if (count($sets) === 0) {
    http_response_code(400);
    echo json_encode(['message' => 'Provide at least one of reminder_time, days_of_week, or notes.']);
    exit;
}

$sql = 'UPDATE schedules SET ' . implode(', ', $sets) . ' WHERE id = :id';
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'Schedule updated.', 'schedule_id' => $scheduleId]);
} else {
    http_response_code(503);
    echo json_encode(['message' => 'Update failed.']);
}
