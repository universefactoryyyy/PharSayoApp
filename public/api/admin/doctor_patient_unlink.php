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
$doctorU = isset($data->doctor_username) ? trim((string)$data->doctor_username) : '';
$patientU = isset($data->patient_username) ? trim((string)$data->patient_username) : '';

if (!pharsayo_require_active_admin($db, $adminId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Admin access required.']);
    exit;
}

$doctorId = pharsayo_find_user_id_by_username($db, $doctorU, 'doctor');
$patientId = pharsayo_find_user_id_by_username($db, $patientU, 'patient');
if ($doctorId === null || $patientId === null) {
    http_response_code(404);
    echo json_encode(['message' => 'Doctor or patient username not found.']);
    exit;
}

$del = $db->prepare('DELETE FROM doctor_patient WHERE doctor_id = :d AND patient_id = :p');
$del->bindValue(':d', $doctorId, PDO::PARAM_INT);
$del->bindValue(':p', $patientId, PDO::PARAM_INT);
$del->execute();

http_response_code(200);
echo json_encode(['message' => 'Doctor unlinked from patient.', 'doctor_id' => $doctorId, 'patient_id' => $patientId]);
