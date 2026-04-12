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

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents('php://input'));

$userId = isset($data->user_id) ? (int)$data->user_id : 0;
$current = isset($data->current_password) ? (string)$data->current_password : '';
$new = isset($data->new_password) ? (string)$data->new_password : '';

if ($userId < 1 || $current === '' || strlen($new) < 6) {
    http_response_code(400);
    echo json_encode(['message' => 'user_id, current_password, and new_password (min 6 chars) are required.']);
    exit;
}

$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || !password_verify($current, $row['password_hash'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Current password is incorrect.']);
    exit;
}

$hash = password_hash($new, PASSWORD_BCRYPT);
$upd = $db->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
$upd->bindValue(':h', $hash);
$upd->bindValue(':id', $userId, PDO::PARAM_INT);
if ($upd->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'Password changed.']);
} else {
    http_response_code(503);
    echo json_encode(['message' => 'Could not update password.']);
}
