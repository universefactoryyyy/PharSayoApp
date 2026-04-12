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

$adminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
if (!pharsayo_require_active_admin($db, $adminId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Admin access required.']);
    exit;
}

$sql = 'SELECT s.id AS schedule_id, s.reminder_time, s.days_of_week, s.start_date, s.end_date, s.notes AS schedule_notes,
        m.id AS medication_id, m.name AS medicine_name, m.notes AS medication_notes,
        u.id AS patient_id, u.name AS patient_name, u.username AS patient_username,
        doc.id AS doctor_id, doc.name AS doctor_name, doc.username AS doctor_username
        FROM schedules s
        INNER JOIN medications m ON s.medication_id = m.id
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN users doc ON m.prescribed_by = doc.id
        ORDER BY u.name ASC, s.reminder_time ASC';

try {
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sql2 = str_replace('s.notes AS schedule_notes,', '', $sql);
    $sql2 = str_replace('m.notes AS medication_notes,', '', $sql2);
    try {
        $rows = $db->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        http_response_code(500);
        echo json_encode(['message' => 'Could not load schedules. Run database migration.']);
        exit;
    }
}

http_response_code(200);
echo json_encode($rows);
