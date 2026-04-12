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
$patientId = isset($data->patient_user_id) ? (int)$data->patient_user_id : 0;

if (!pharsayo_require_active_doctor($db, $doctorId) || !pharsayo_doctor_has_patient($db, $doctorId, $patientId)) {
    http_response_code(403);
    echo json_encode(['message' => 'You must be an active doctor linked to this patient.']);
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
    echo json_encode(['message' => 'Provide times as an array of HH:MM strings (intake schedule from the doctor).']);
    exit;
}

function pharsayo_normalize_time($t) {
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
    $n = pharsayo_normalize_time($t);
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
$medication_notes = isset($data->medication_notes) ? (string)$data->medication_notes : '';
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
        prescribed_by=:prescribed_by';
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
        prescribed_by=:prescribed_by';
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
    $stmt->bindValue(':prescribed_by', $doctorId, PDO::PARAM_INT);
    $stmt->execute();
    $medId = (int)$db->lastInsertId();

    $days = isset($data->days_of_week) ? (string)$data->days_of_week : 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
    foreach ($normTimes as $idx => $rt) {
        $sn = isset($schedule_notes_list[$idx]) ? (string)$schedule_notes_list[$idx] : '';
        $sq = $db->prepare(
            'INSERT INTO schedules SET medication_id=:m, user_id=:u, reminder_time=:rt, days_of_week=:d, notes=:sn'
        );
        $sq->bindValue(':m', $medId, PDO::PARAM_INT);
        $sq->bindValue(':u', $patientId, PDO::PARAM_INT);
        $sq->bindValue(':rt', $rt);
        $sq->bindValue(':d', $days);
        $sq->bindValue(':sn', $sn);
        $sq->execute();
    }

    $db->commit();
    http_response_code(201);
    echo json_encode(['message' => 'Prescription saved.', 'medication_id' => $medId]);
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(503);
    echo json_encode(['message' => 'Unable to save prescription.']);
}
