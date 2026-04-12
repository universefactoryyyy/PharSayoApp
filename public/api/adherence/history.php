<?php
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
$date = isset($_GET['date']) ? $_GET['date'] : null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["message" => "user_id is required"]);
    exit;
}

$sql = "SELECT l.*, m.name AS medication_name 
        FROM adherence_logs l 
        JOIN medications m ON l.medication_id = m.id 
        WHERE l.user_id = :user_id";
$params = [':user_id' => $user_id];

if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    // Match the calendar day of the scheduled dose (and legacy rows by response day).
    $sql .= " AND (DATE(l.scheduled_time) = :d OR DATE(l.responded_at) = :d)";
    $params[':d'] = $date;
}

$sql .= " ORDER BY l.responded_at DESC LIMIT 200";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode($logs);
