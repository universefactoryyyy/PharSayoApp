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
$doctorId = isset($data->doctor_id) ? (int)$data->doctor_id : 0;
$patientUsername = isset($data->patient_username) ? trim((string)$data->patient_username) : '';
$patientPhone = isset($data->patient_phone) ? trim((string)$data->patient_phone) : '';

if (!pharsayo_require_active_doctor($db, $doctorId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Active doctor account required.']);
    exit;
}

$patientId = null;
if ($patientUsername !== '') {
    $patientId = pharsayo_find_user_id_by_username($db, $patientUsername, 'patient');
}
if ($patientId === null && $patientPhone !== '') {
    $stmt = $db->prepare(
        "SELECT id FROM users WHERE phone = :p AND role = 'patient' AND account_status = 'active' LIMIT 1"
    );
    $stmt->bindValue(':p', $patientPhone);
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $patientId = $r ? (int)$r['id'] : null;
}

if ($patientId === null) {
    http_response_code(404);
    echo json_encode(['message' => 'No active patient found with that username or phone number.']);
    exit;
}

try {
    $stmt = $db->prepare(
        'SELECT id, name, phone, username FROM users WHERE id = :id AND role = :role AND account_status = :st LIMIT 1'
    );
    $stmt->bindValue(':id', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':role', 'patient');
    $stmt->bindValue(':st', 'active');
    $stmt->execute();
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $db->prepare(
        "SELECT id, name, phone FROM users WHERE id = :id AND role = 'patient' AND account_status = 'active' LIMIT 1"
    );
    $stmt->bindValue(':id', $patientId, PDO::PARAM_INT);
    $stmt->execute();
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($p)) {
        $p['username'] = '';
    }
}

if (!is_array($p)) {
    http_response_code(404);
    echo json_encode(['message' => 'Patient not found.']);
    exit;
}

$ins = $db->prepare(
    'INSERT IGNORE INTO doctor_patient (doctor_id, patient_id) VALUES (:d, :p)'
);
$ins->bindValue(':d', $doctorId, PDO::PARAM_INT);
$ins->bindValue(':p', $patientId, PDO::PARAM_INT);
$ins->execute();

http_response_code(201);
echo json_encode([
    'message' => 'Patient linked to your practice.',
    'patient' => [
        'id' => $patientId,
        'name' => $p['name'],
        'phone' => $p['phone'],
        'username' => isset($p['username']) ? (string)$p['username'] : '',
    ],
]);
