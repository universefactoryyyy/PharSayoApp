<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id) || empty($data->user_id) || empty($data->name)) {
    http_response_code(400);
    echo json_encode(["message" => "id, user_id, and name are required"]);
    exit;
}

$check = $db->prepare("SELECT id FROM medications WHERE id = :id AND user_id = :uid LIMIT 1");
$check->execute([':id' => (int)$data->id, ':uid' => (int)$data->user_id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(["message" => "Medication not found for this user"]);
    exit;
}

$query = "UPDATE medications SET 
    name = :name,
    dosage = :dosage,
    frequency = :frequency,
    purpose_fil = :purpose_fil,
    purpose_en = :purpose_en,
    precautions_fil = :precautions_fil,
    precautions_en = :precautions_en
    WHERE id = :id AND user_id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindValue(':id', (int)$data->id, PDO::PARAM_INT);
$stmt->bindValue(':user_id', (int)$data->user_id, PDO::PARAM_INT);
$stmt->bindValue(':name', $data->name);
$stmt->bindValue(':dosage', $data->dosage ?? '');
$stmt->bindValue(':frequency', $data->frequency ?? '');
$stmt->bindValue(':purpose_fil', $data->purpose_fil ?? '');
$stmt->bindValue(':purpose_en', $data->purpose_en ?? '');
$stmt->bindValue(':precautions_fil', $data->precautions_fil ?? '');
$stmt->bindValue(':precautions_en', $data->precautions_en ?? '');

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["message" => "Medication updated"]);
} else {
    http_response_code(503);
    echo json_encode(["message" => "Unable to update medication"]);
}
