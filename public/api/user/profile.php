<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents('php://input'));

if (!is_object($data)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON body.']);
    exit;
}

$userId = isset($data->user_id) ? (int)$data->user_id : 0;
if ($userId < 1) {
    http_response_code(400);
    echo json_encode(['message' => 'user_id is required.']);
    exit;
}

$stmt = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
$stmt->execute();
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    http_response_code(404);
    echo json_encode(['message' => 'User not found.']);
    exit;
}

$name = isset($data->name) ? trim((string)$data->name) : null;
$phone = isset($data->phone) ? trim((string)$data->phone) : null;
// json_decode returns stdClass - do not use array_key_exists() on it (PHP 8+ TypeError).
$age = property_exists($data, 'age') ? $data->age : null;
$lang = isset($data->language_preference) ? (string)$data->language_preference : null;
$newUsername = isset($data->username) ? trim((string)$data->username) : null;

if ($newUsername !== null && $newUsername !== '') {
    if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $newUsername)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid username format.']);
        exit;
    }
    $chk = $db->prepare('SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1');
    $chk->bindValue(':u', $newUsername);
    $chk->bindValue(':id', $userId, PDO::PARAM_INT);
    $chk->execute();
    if ($chk->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['message' => 'Username is already taken.']);
        exit;
    }
}

if ($phone !== null && $phone !== '') {
    $chk = $db->prepare('SELECT id FROM users WHERE phone = :p AND id != :id LIMIT 1');
    $chk->bindValue(':p', $phone);
    $chk->bindValue(':id', $userId, PDO::PARAM_INT);
    $chk->execute();
    if ($chk->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['message' => 'Phone number already in use.']);
        exit;
    }
}

$sets = [];
$params = [':id' => $userId];
if ($name !== null && $name !== '') {
    $sets[] = 'name = :name';
    $params[':name'] = htmlspecialchars(strip_tags($name));
}
if ($phone !== null && $phone !== '') {
    $sets[] = 'phone = :phone';
    $params[':phone'] = htmlspecialchars(strip_tags($phone));
}
if ($age !== null) {
    $sets[] = 'age = :age';
    $params[':age'] = $age === '' || $age === null ? null : (int)$age;
}
if ($lang !== null && in_array($lang, ['fil', 'en'], true)) {
    $sets[] = 'language_preference = :lang';
    $params[':lang'] = $lang;
}
if ($newUsername !== null && $newUsername !== '') {
    $sets[] = 'username = :username';
    $params[':username'] = $newUsername;
}

if (count($sets) === 0) {
    http_response_code(400);
    echo json_encode(['message' => 'No fields to update.']);
    exit;
}

$runUpdate = function (array $useSets, array $useParams) use ($db): bool {
    if (count($useSets) === 0) {
        return false;
    }
    $sql = 'UPDATE users SET ' . implode(', ', $useSets) . ' WHERE id = :id';
    $upd = $db->prepare($sql);
    foreach ($useParams as $k => $v) {
        if ($k === ':age' && $v === null) {
            $upd->bindValue($k, null, PDO::PARAM_NULL);
        } else {
            $upd->bindValue($k, $v);
        }
    }
    return $upd->execute();
};

$ok = false;
try {
    $ok = $runUpdate($sets, $params);
} catch (PDOException $e) {
    $ok = false;
}
if (!$ok && isset($params[':username'])) {
    $setsNo = [];
    foreach ($sets as $s) {
        if (strpos($s, 'username') === false) {
            $setsNo[] = $s;
        }
    }
    $paramsNo = $params;
    unset($paramsNo[':username']);
    if (count($setsNo) > 0) {
        try {
            $ok = $runUpdate($setsNo, $paramsNo);
        } catch (PDOException $e) {
            $ok = false;
        }
    }
}

if ($ok) {
    try {
        $stmt2 = $db->prepare(
            'SELECT id, name, role, phone, username, language_preference, account_status FROM users WHERE id = :id LIMIT 1'
        );
        $stmt2->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt2->execute();
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt2 = $db->prepare(
            'SELECT id, name, role, phone, language_preference, account_status FROM users WHERE id = :id LIMIT 1'
        );
        $stmt2->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt2->execute();
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row['username'] = '';
        }
    }
    if (!is_array($row)) {
        http_response_code(503);
        echo json_encode(['message' => 'Updated but could not reload profile.']);
        exit;
    }
    http_response_code(200);
    echo json_encode([
        'message' => 'Profile updated.',
        'user' => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'role' => $row['role'],
            'phone' => $row['phone'],
            'username' => isset($row['username']) ? $row['username'] : '',
            'language_preference' => $row['language_preference'],
            'account_status' => isset($row['account_status']) ? $row['account_status'] : 'active',
        ],
    ]);
} else {
    http_response_code(503);
    echo json_encode(['message' => 'Update failed.']);
}
