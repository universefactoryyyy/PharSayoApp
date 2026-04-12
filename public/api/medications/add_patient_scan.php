<?php
/**
 * Patient adds a medicine from scanner / self-service (prescribed_by = NULL).
 * Same medication + schedule shape as doctor/prescribe.php.
 */
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

$patientId = isset($data->user_id) ? (int)$data->user_id : 0;
if ($patientId < 1 || !pharsayo_require_active_patient($db, $patientId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Active patient account required.']);
    exit;
}

$name = isset($data->name) ? trim((string)$data->name) : '';
if ($name === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Medication name is required.']);
    exit;
}

$times = $data->times ?? null;
if (!is_array($times) || count($times) < 1) {
    http_response_code(400);
    echo json_encode(['message' => 'Provide times as an array of HH:MM strings.']);
    exit;
}

function pharsayo_patient_scan_normalize_time($t) {
    $t = trim((string)$t);
    if (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
        $parts = explode(':', $t);
        return str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . ':00';
    }
    if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $t)) {
        return $t;
    }
    return '';
}

$normTimes = [];
foreach ($times as $t) {
    $n = pharsayo_patient_scan_normalize_time($t);
    if ($n !== '') {
        $normTimes[] = $n;
    }
}
$normTimes = array_values(array_unique($normTimes));
if (count($normTimes) < 1) {
    http_response_code(400);
    echo json_encode(['message' => 'No valid reminder times. Use HH:MM format.']);
    exit;
}

$dosage = isset($data->dosage) ? (string)$data->dosage : '';
$frequency = isset($data->frequency) ? (string)$data->frequency : '';
$frequency_fil = isset($data->frequency_fil) ? (string)$data->frequency_fil : '';
$purpose_fil = isset($data->purpose_fil) ? (string)$data->purpose_fil : '';
$purpose_en = isset($data->purpose_en) ? (string)$data->purpose_en : '';
$precautions_fil = isset($data->precautions_fil) ? (string)$data->precautions_fil : '';
$precautions_en = isset($data->precautions_en) ? (string)$data->precautions_en : '';
$medication_notes = isset($data->notes) ? (string)$data->notes : '';
$schedule_notes_list = isset($data->schedule_notes) && is_array($data->schedule_notes) ? $data->schedule_notes : [];

$hasFrequencyFilCol = true;
try {
    $db->query('SELECT frequency_fil FROM medications LIMIT 1');
} catch (PDOException $e) {
    $hasFrequencyFilCol = false;
}

$db->beginTransaction();
try {
    if ($hasFrequencyFilCol) {
        $q = 'INSERT INTO medications SET 
        user_id=:user_id, 
        name=:name, 
        dosage=:dosage, 
        frequency=:frequency, 
        frequency_fil=:frequency_fil, 
        purpose_fil=:purpose_fil, 
        purpose_en=:purpose_en, 
        precautions_fil=:precautions_fil, 
        precautions_en=:precautions_en,
        notes=:mednotes,
        prescribed_by=NULL';
    } else {
        $q = 'INSERT INTO medications SET 
        user_id=:user_id, 
        name=:name, 
        dosage=:dosage, 
        frequency=:frequency, 
        purpose_fil=:purpose_fil, 
        purpose_en=:purpose_en, 
        precautions_fil=:precautions_fil, 
        precautions_en=:precautions_en,
        notes=:mednotes,
        prescribed_by=NULL';
    }
    $stmt = $db->prepare($q);
    $stmt->bindValue(':user_id', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':dosage', $dosage);
    $stmt->bindValue(':frequency', $frequency);
    if ($hasFrequencyFilCol) {
        $stmt->bindValue(':frequency_fil', $frequency_fil);
    }
    $stmt->bindValue(':purpose_fil', $purpose_fil);
    $stmt->bindValue(':purpose_en', $purpose_en);
    $stmt->bindValue(':precautions_fil', $precautions_fil);
    $stmt->bindValue(':precautions_en', $precautions_en);
    $stmt->bindValue(':mednotes', $medication_notes);
    $stmt->execute();
    $medId = (int)$db->lastInsertId();

    $db->commit();
    http_response_code(201);
    echo json_encode(['message' => 'Medication saved.', 'medication_id' => $medId]);
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(503);
    echo json_encode(['message' => 'Unable to save medication.']);
}
