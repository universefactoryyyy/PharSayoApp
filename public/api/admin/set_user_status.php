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
$adminId = isset($data->admin_id) ? (int)$data->admin_id : 0;
$targetId = isset($data->user_id) ? (int)$data->user_id : 0;
$status = isset($data->status) ? strtolower(trim((string)$data->status)) : '';

if (!pharsayo_require_active_admin($db, $adminId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Admin access required.']);
    exit;
}

if ($targetId < 1 || !in_array($status, ['active', 'rejected', 'pending', 'inactive'], true)) {
    http_response_code(400);
    echo json_encode(['message' => 'user_id and status (active|rejected|pending|inactive) are required.']);
    exit;
}

if ($targetId === $adminId) {
    http_response_code(400);
    echo json_encode(['message' => 'You cannot change your own account status here.']);
    exit;
}

$chk = $db->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
$chk->bindValue(':id', $targetId, PDO::PARAM_INT);
$chk->execute();
$row = $chk->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['message' => 'User not found.']);
    exit;
}

$upd = $db->prepare('UPDATE users SET account_status = :s WHERE id = :id');
$upd->bindValue(':s', $status);
$upd->bindValue(':id', $targetId, PDO::PARAM_INT);
if ($upd->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'Status updated.', 'user_id' => $targetId, 'status' => $status]);
} else {
    http_response_code(503);
    echo json_encode(['message' => 'Update failed.']);
}
