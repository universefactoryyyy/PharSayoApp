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
include_once __DIR__ . '/../lib/schedule_adherence_helpers.php';

$database = new Database();
$db = $database->getConnection();

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if (!pharsayo_require_active_doctor($db, $doctorId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Active doctor account required.']);
    exit;
}

$adherenceJoin = '
    LEFT JOIN adherence_logs al ON al.user_id = s.user_id
        AND al.medication_id = s.medication_id
        AND DATE(al.scheduled_time) = CURDATE()
        AND TIME(al.scheduled_time) = s.reminder_time
        AND al.id = (
            SELECT MAX(l2.id) FROM adherence_logs l2
            WHERE l2.user_id = s.user_id
              AND l2.medication_id = s.medication_id
              AND DATE(l2.scheduled_time) = CURDATE()
              AND TIME(l2.scheduled_time) = s.reminder_time
        )';

$sql = 'SELECT s.id AS schedule_id, s.reminder_time, s.days_of_week, s.notes AS schedule_notes,
        m.id AS medication_id, m.name AS medicine_name,
        u.name AS patient_name, u.username AS patient_username,
        al.taken AS log_taken, al.responded_at AS log_responded_at, al.scheduled_time AS log_scheduled_time
        FROM schedules s
        INNER JOIN medications m ON s.medication_id = m.id
        INNER JOIN users u ON s.user_id = u.id
        INNER JOIN doctor_patient dp ON dp.patient_id = u.id AND dp.doctor_id = :d
        ' . $adherenceJoin . '
        WHERE m.prescribed_by = :d2
        ORDER BY s.reminder_time ASC';

$stmt = $db->prepare($sql);
$stmt->bindValue(':d', $doctorId, PDO::PARAM_INT);
$stmt->bindValue(':d2', $doctorId, PDO::PARAM_INT);

try {
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sql2 = 'SELECT s.id AS schedule_id, s.reminder_time, s.days_of_week,
        m.id AS medication_id, m.name AS medicine_name,
        u.name AS patient_name, u.username AS patient_username,
        al.taken AS log_taken, al.responded_at AS log_responded_at, al.scheduled_time AS log_scheduled_time
        FROM schedules s
        INNER JOIN medications m ON s.medication_id = m.id
        INNER JOIN users u ON s.user_id = u.id
        INNER JOIN doctor_patient dp ON dp.patient_id = u.id AND dp.doctor_id = :d
        ' . $adherenceJoin . '
        WHERE m.prescribed_by = :d2
        ORDER BY s.reminder_time ASC';
    $stmt2 = $db->prepare($sql2);
    $stmt2->bindValue(':d', $doctorId, PDO::PARAM_INT);
    $stmt2->bindValue(':d2', $doctorId, PDO::PARAM_INT);
    $stmt2->execute();
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['schedule_notes'] = '';
    }
    unset($r);
}

foreach ($rows as &$r) {
    $status = pharsayo_adherence_today_status($r);
    $confirmedTime = null;
    $lt = $r['log_taken'] ?? null;
    $isTaken = ($lt === true || $lt === 1 || $lt === '1');
    if ($isTaken && !empty($r['log_responded_at'])) {
        $confirmedTime = pharsayo_format_adherence_confirmed_philippine((string)$r['log_responded_at']);
    }
    unset($r['log_taken'], $r['log_responded_at'], $r['log_scheduled_time']);
    $r['adherence_today'] = $status;
    $r['adherence_confirmed_time'] = $confirmedTime;
}
unset($r);

http_response_code(200);
echo json_encode($rows);
