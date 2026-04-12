<?php
/**
 * Stream a doctor's uploaded verification document (admin only).
 * Do not include cors.php — it sets application/json and breaks binary responses.
 */
header('Access-Control-Allow-Origin: *');

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../lib/role_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$adminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$targetId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$database = new Database();
$db = $database->getConnection();

if (!pharsayo_require_active_admin($db, $adminId)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Admin access required.';
    exit;
}

if ($targetId < 1) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'user_id is required.';
    exit;
}

$stmt = $db->prepare('SELECT role, verification_file FROM users WHERE id = :id LIMIT 1');
$stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($row) || ($row['role'] ?? '') !== 'doctor') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'User not found or not a doctor account.';
    exit;
}

$rel = trim((string)($row['verification_file'] ?? ''));
if ($rel === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No verification document on file.';
    exit;
}

$rel = str_replace('\\', '/', $rel);
if (!preg_match('#^uploads/verification/[^/]+$#', $rel)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid stored path.';
    exit;
}

$abs = realpath(__DIR__ . '/../' . $rel);
$base = realpath(__DIR__ . '/../uploads/verification');
if ($abs === false || $base === false || strpos($abs, $base) !== 0 || !is_file($abs)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'File not found.';
    exit;
}

$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $m = @mime_content_type($abs);
    if (is_string($m) && $m !== '') {
        $mime = $m;
    }
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . basename($abs) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($abs);
exit;
