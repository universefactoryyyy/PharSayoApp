<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));
$id = isset($data->id) ? (int)$data->id : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$user_id = isset($data->user_id) ? (int)$data->user_id : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0);

if (!$id || !$user_id) {
    http_response_code(400);
    echo json_encode(["message" => "id and user_id are required"]);
    exit;
}

$stmt = $db->prepare("DELETE FROM medications WHERE id = :id AND user_id = :user_id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

if ($stmt->execute() && $stmt->rowCount() > 0) {
    http_response_code(200);
    echo json_encode(["message" => "Medication deleted"]);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Medication not found"]);
}
