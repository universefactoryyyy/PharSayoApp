<?php
// api/admin/users_list.php
include_once __DIR__ . '/../config/cors.php';
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

try {
    $stmt = $db->query(
        "SELECT id, name, username, phone, role, account_status, age, language_preference, created_at, verification_file FROM users ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $db->query("SELECT id, name, username, phone, role, account_status, created_at FROM users ORDER BY id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['username'] = $r['username'] ?? '';
            $r['age'] = null;
            $r['language_preference'] = 'fil';
            $r['verification_file'] = null;
        }
        unset($r);
    } catch (PDOException $e2) {
        $stmt = $db->query("SELECT id, name, phone, role, account_status, created_at FROM users ORDER BY id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['username'] = '';
            $r['age'] = null;
            $r['language_preference'] = 'fil';
            $r['verification_file'] = null;
        }
        unset($r);
    }
}

http_response_code(200);
echo json_encode($rows);
