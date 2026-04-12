<?php
// api/medications/get.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$lang = isset($_GET['lang']) ? strtolower((string)$_GET['lang']) : 'fil';
if (!in_array($lang, ['fil', 'en'], true)) {
    $lang = 'fil';
}

if($user_id) {
    $query = "SELECT * FROM medications WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    $meds = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pEn = trim((string)($row['purpose_en'] ?? ''));
        $pFil = trim((string)($row['purpose_fil'] ?? ''));
        $cEn = trim((string)($row['precautions_en'] ?? ''));
        $cFil = trim((string)($row['precautions_fil'] ?? ''));

        if ($lang === 'en') {
            $row['purpose'] = $pEn !== '' ? $pEn : $pFil;
            $row['precautions'] = $cEn !== '' ? $cEn : $cFil;
        } else {
            $row['purpose'] = $pFil !== '' ? $pFil : $pEn;
            $row['precautions'] = $cFil !== '' ? $cFil : $cEn;
        }

        $freqEn = trim((string)($row['frequency'] ?? ''));
        $freqFil = isset($row['frequency_fil']) ? trim((string)$row['frequency_fil']) : '';
        if ($lang === 'en') {
            $row['frequency'] = $freqEn !== '' ? $freqEn : $freqFil;
        } else {
            $row['frequency'] = $freqFil !== '' ? $freqFil : $freqEn;
        }

        $meds[] = $row;
    }

    http_response_code(200);
    echo json_encode($meds);
} else {
    http_response_code(400);
    echo json_encode(array("message" => "User ID is required."));
}
