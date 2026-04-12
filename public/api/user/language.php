<?php
// api/user/language.php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->user_id) && !empty($data->language_preference)) {
    $query = "UPDATE users SET language_preference = :lang WHERE id = :user_id";
    $stmt = $db->prepare($query);

    $raw = htmlspecialchars(strip_tags($data->language_preference));
    $lang = in_array($raw, ['fil', 'en'], true) ? $raw : 'fil';
    $user_id = (int)$data->user_id;

    $stmt->bindParam(":lang", $lang);
    $stmt->bindParam(":user_id", $user_id);

    if($stmt->execute()) {
        $_SESSION['lang'] = $lang;
        http_response_code(200);
        echo json_encode(array("message" => "Language preference updated."));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to update language preference."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Unable to update language. Data is incomplete."));
}
