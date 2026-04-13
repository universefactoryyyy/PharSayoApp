<?php
// Emergency mobile-data login endpoint.
// Uses query params to avoid POST JSON redirect/preflight issues on some free-hosting edges.
session_start();
include_once __DIR__ . '/config/cors.php';
include_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/lib/role_helpers.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(array('message' => 'Database connection failed.'));
    exit;
}

$ident = isset($_GET['username']) ? trim((string)$_GET['username']) : '';
if ($ident === '' && isset($_GET['u'])) {
    $ident = trim((string)$_GET['u']);
}
$password = isset($_GET['password']) ? (string)$_GET['password'] : '';
if ($password === '' && isset($_GET['p'])) {
    $password = (string)$_GET['p'];
}

if ($ident === '' || $password === '') {
    http_response_code(400);
    echo json_encode(array('message' => 'Username and password are required.'));
    exit;
}

$row = pharsayo_login_fetch_user($db, $ident);
if (!is_array($row)) {
    http_response_code(401);
    echo json_encode(array('message' => 'Username not found in database.'));
    exit;
}
if (empty($row['password_hash'])) {
    http_response_code(401);
    echo json_encode(array('message' => 'User has no password set in database.'));
    exit;
}
if (!password_verify($password, $row['password_hash'])) {
    http_response_code(401);
    echo json_encode(array('message' => 'Wrong password.'));
    exit;
}

$acct = isset($row['account_status']) ? (string)$row['account_status'] : 'active';
if ($acct === 'pending') {
    http_response_code(403);
    echo json_encode(array('message' => 'Account is pending administrator approval.'));
    exit;
}
if ($acct === 'rejected') {
    http_response_code(403);
    echo json_encode(array('message' => 'This account was not approved.'));
    exit;
}
if ($acct === 'inactive' || $acct === 'suspended') {
    http_response_code(403);
    echo json_encode(array('message' => 'This account has been deactivated.'));
    exit;
}
if ($acct !== 'active') {
    http_response_code(403);
    echo json_encode(array('message' => 'Account is not active.'));
    exit;
}

$_SESSION['user_id'] = $row['id'];
$_SESSION['role'] = $row['role'];
$_SESSION['lang'] = $row['language_preference'];

http_response_code(200);
echo json_encode(array(
    'message' => 'Login successful.',
    'user' => array(
        'id' => $row['id'],
        'name' => $row['name'],
        'role' => $row['role'],
        'phone' => $row['phone'],
        'username' => isset($row['username']) ? (string)$row['username'] : '',
        'language_preference' => $row['language_preference'],
        'account_status' => $acct,
    ),
));
exit;

