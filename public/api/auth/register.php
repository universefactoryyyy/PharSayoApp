<?php
// api/auth/register.php
include_once __DIR__ . '/../config/cors.php';
include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$verification_file_path = null;

// Handle multipart/form-data for file uploads
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    $data = (object)$_POST;
} else {
    $data = json_decode(file_get_contents('php://input'));
}

$allowed_roles = ['patient', 'doctor'];
$role_in = isset($data->role) ? strtolower(trim((string)$data->role)) : '';
if (!in_array($role_in, $allowed_roles, true)) {
    http_response_code(400);
    echo json_encode(array('message' => 'Invalid account type. Choose patient or doctor.'));
    exit;
}

// Doctor verification file check
if ($role_in === 'doctor') {
    if (!isset($_FILES['verification_file']) || $_FILES['verification_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(array('message' => 'Doctor account requires a verification document (ID or evidence).'));
        exit;
    }
    
    // Simple file upload logic
    $upload_dir = '../uploads/verification/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($_FILES['verification_file']['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('doc_') . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['verification_file']['tmp_name'], $target_file)) {
        $verification_file_path = 'uploads/verification/' . $file_name;
    } else {
        http_response_code(500);
        echo json_encode(array('message' => 'Failed to save verification file.'));
        exit;
    }
}

$username = isset($data->username) ? trim((string)$data->username) : '';
if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
    http_response_code(400);
    echo json_encode(array('message' => 'Username must be 3–32 characters: letters, numbers, dot, underscore, or hyphen.'));
    exit;
}

if (
    empty($data->name) ||
    empty($data->phone) ||
    empty($data->password) ||
    empty($data->role)
) {
    http_response_code(400);
    echo json_encode(array('message' => 'Unable to create user. Data is incomplete.'));
    exit;
}

$check_u = $db->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
$check_u->bindValue(':u', $username);
$check_u->execute();
if ($check_u->rowCount() > 0) {
    http_response_code(400);
    echo json_encode(array('message' => 'Username is already taken.'));
    exit;
}

$check_query = 'SELECT id FROM users WHERE phone = :phone';
$stmt = $db->prepare($check_query);
$stmt->bindParam(':phone', $data->phone);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    http_response_code(400);
    echo json_encode(array('message' => 'Phone number already registered.'));
    exit;
}

// Optional: DB without username column — try INSERT without username (legacy)
$hasUsername = true;
try {
    $db->query('SELECT username FROM users LIMIT 1');
} catch (PDOException $e) {
    $hasUsername = false;
}

if ($hasUsername) {
    $query = 'INSERT INTO users SET 
                name=:name, 
                age=:age, 
                role=:role, 
                username=:username,
                phone=:phone, 
                password_hash=:password, 
                language_preference=:lang,
                verification_file=:v_file,
                account_status=:acct';
} else {
    $query = 'INSERT INTO users SET 
                name=:name, 
                age=:age, 
                role=:role, 
                phone=:phone, 
                password_hash=:password, 
                language_preference=:lang,
                verification_file=:v_file,
                account_status=:acct';
}

$stmt = $db->prepare($query);

$name = htmlspecialchars(strip_tags($data->name));
$age = isset($data->age) ? (int)$data->age : null;
$role = $role_in;
$phone = htmlspecialchars(strip_tags($data->phone));
$password = password_hash($data->password, PASSWORD_BCRYPT);
$lang = isset($data->language_preference) ? $data->language_preference : 'fil';

$stmt->bindValue(':name', $name);
if ($age === null) {
    $stmt->bindValue(':age', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':age', $age, PDO::PARAM_INT);
}
$stmt->bindValue(':role', $role);
if ($hasUsername) {
    $stmt->bindValue(':username', $username);
}
$stmt->bindValue(':phone', $phone);
$stmt->bindValue(':password', $password);
$stmt->bindValue(':lang', in_array($lang, ['fil', 'en'], true) ? $lang : 'fil');
$stmt->bindValue(':v_file', $verification_file_path);
    $status = ($role === 'patient') ? 'active' : 'pending';
    $stmt->bindValue(':acct', $status);

    if ($stmt->execute()) {
        http_response_code(201);
        $msg = ($status === 'active') 
            ? 'User was created successfully. You can now sign in.' 
            : 'User was created. An administrator must approve your account before you can sign in.';
        echo json_encode(array(
            'message' => $msg,
        ));
    } else {
    http_response_code(503);
    echo json_encode(array('message' => 'Unable to create user.'));
}
