<?php
// api/config/cors.php

// 1. Simple, wide-open CORS for maximum compatibility with free hosting
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept, X-Requested-With');

// 2. Handle pre-flight (OPTIONS) requests immediately
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 3. Set JSON content type for all responses
header('Content-Type: application/json; charset=UTF-8');



