<?php
// api/medications/add.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../lib/role_helpers.php';
include_once __DIR__ . '/../lib/medication_lookup.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

$patientId = !empty($data->user_id) ? (int)$data->user_id : 0;
$prescribedBy = isset($data->prescribed_by) ? (int)$data->prescribed_by : 0;

if ($patientId < 1 || empty($data->name)) {
    http_response_code(400);
    echo json_encode(array("message" => "Unable to add medication. Data is incomplete."));
    exit;
}

if ($prescribedBy < 1 || !pharsayo_require_active_doctor($db, $prescribedBy) || !pharsayo_doctor_has_patient($db, $prescribedBy, $patientId)) {
    http_response_code(403);
    echo json_encode(array("message" => "Only an approved doctor linked to this patient may add medications."));
    exit;
}

$medNotes = isset($data->notes) ? (string)$data->notes : '';

$query = "INSERT INTO medications SET 
            user_id=:user_id, 
            name=:name, 
            dosage=:dosage, 
            frequency=:frequency, 
            purpose_fil=:purpose_fil, 
            purpose_en=:purpose_en, 
            precautions_fil=:precautions_fil, 
            precautions_en=:precautions_en,
            notes=:notes,
            prescribed_by=:prescribed_by";

$stmt = $db->prepare($query);

$stmt->bindValue(":user_id", $patientId, PDO::PARAM_INT);
$stmt->bindValue(":name", $data->name);
$stmt->bindValue(":dosage", $data->dosage ?? '');
$stmt->bindValue(":frequency", $data->frequency ?? '');
$stmt->bindValue(":purpose_fil", $data->purpose_fil ?? '');
$stmt->bindValue(":purpose_en", $data->purpose_en ?? '');
$stmt->bindValue(":precautions_fil", $data->precautions_fil ?? '');
$stmt->bindValue(":precautions_en", $data->precautions_en ?? '');
$stmt->bindValue(":notes", $medNotes);
$stmt->bindValue(":prescribed_by", $prescribedBy, PDO::PARAM_INT);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(array("message" => "Medication was added.", "id" => $db->lastInsertId()));
} else {
    http_response_code(503);
    echo json_encode(array("message" => "Unable to add medication."));
}
