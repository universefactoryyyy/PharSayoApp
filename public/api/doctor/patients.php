<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../lib/role_helpers.php';

$database = new Database();
$db = $database->getConnection();

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if (!pharsayo_require_active_doctor($db, $doctorId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Active doctor account required.']);
    exit;
}

try {
    $stmt = $db->prepare(
        'SELECT u.id, u.name, u.username, u.age, u.phone, u.language_preference, u.account_status
         FROM users u
         INNER JOIN doctor_patient dp ON u.id = dp.patient_id
         WHERE dp.doctor_id = :d
         ORDER BY u.name ASC'
    );
    $stmt->bindValue(':d', $doctorId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $db->prepare(
        'SELECT u.id, u.name, u.age, u.phone, u.language_preference, u.account_status
         FROM users u
         INNER JOIN doctor_patient dp ON u.id = dp.patient_id
         WHERE dp.doctor_id = :d
         ORDER BY u.name ASC'
    );
    $stmt->bindValue(':d', $doctorId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['username'] = '';
    }
    unset($r);
}

http_response_code(200);
echo json_encode($rows);
