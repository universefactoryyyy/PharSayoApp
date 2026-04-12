<?php
// api/auth/logout.php
session_start();
session_unset();
session_destroy();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
echo json_encode(array("message" => "Logged out successfully."));
