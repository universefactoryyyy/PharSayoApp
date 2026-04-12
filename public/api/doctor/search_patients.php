<?php
/**
 * Active patients searchable by username, phone, or name (for doctor link-patient UI).
 */
include_once __DIR__ . '/../config/cors.php';
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../lib/role_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if (!pharsayo_require_active_doctor($db, $doctorId)) {
    http_response_code(403);
    echo json_encode(['message' => 'Active doctor account required.']);
    exit;
}

if (strlen($q) < 2) {
    http_response_code(200);
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';

try {
    $stmt = $db->prepare(
        "SELECT id, name, username, phone, account_status
         FROM users
         WHERE role = 'patient'
           AND account_status = 'active'
           AND (
                username LIKE :q
             OR phone LIKE :q
             OR name LIKE :q
           )
         ORDER BY name ASC
         LIMIT 40"
    );
    $stmt->bindValue(':q', $like);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $db->prepare(
        "SELECT id, name, phone, account_status
         FROM users
         WHERE role = 'patient'
           AND account_status = 'active'
           AND (phone LIKE :q OR name LIKE :q)
         ORDER BY name ASC
         LIMIT 40"
    );
    $stmt->bindValue(':q', $like);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['username'] = '';
    }
    unset($r);
}

http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array_values($rows));
