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

if (!is_object($data)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON body.']);
    exit;
}

$adminId = isset($data->admin_id) ? (int)$data->admin_id : 0;
$targetId = isset($data->user_id) ? (int)$data->user_id : 0;

if (!pharsayo_require_active_admin($db, $adminId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Admin access required.']);
    exit;
}

if ($targetId < 1) {
    http_response_code(400);
    echo json_encode(['message' => 'user_id is required.']);
    exit;
}

if ($targetId === $adminId) {
    http_response_code(400);
    echo json_encode(['message' => 'You cannot delete your own account.']);
    exit;
}

$stmt = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
$stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
$stmt->execute();
if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(['message' => 'User not found.']);
    exit;
}

try {
    $del = $db->prepare('DELETE FROM users WHERE id = :id');
    $del->bindValue(':id', $targetId, PDO::PARAM_INT);
    $del->execute();
    if ($del->rowCount() < 1) {
        http_response_code(503);
        echo json_encode(['message' => 'Delete failed.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['message' => 'Delete failed.']);
    exit;
}

http_response_code(200);
echo json_encode(['message' => 'Account deleted.']);
