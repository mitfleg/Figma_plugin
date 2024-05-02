<?php
require_once 'Database.php';
require_once 'User.php';
require_once 'RateLimiter.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, PUT, DELETE, GET');
header('Access-Control-Allow-Headers: Content-Type, content-type');
header('Content-Type: application/json');

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['requestType'])) {
    throw new ApiException('Missing requestType', 400, 3);
}

$rateLimiter = new RateLimiter($db);
$ip = $_SERVER['REMOTE_ADDR'];
if (!$rateLimiter->check($ip)) {
    throw new ApiException('You have reached your request limit', 429, 3);
}

$user = new User($db, $data);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($data['requestType']) {
        case 'checkingAuthorize':
            $response = $user->authorize();
            break;

        case 'signup':
            $response = $user->signUp();
            break;

        case 'activatePromocode':
            $response = $user->activatePromocode();
            break;

        case 'resendCode':
            $response = $user->resendCode();
            break;

        case 'subscribe':
            $response = $user->paySubscribe($data['plan_id']);
            break;

        case 'confirmEmail':
            $response = $user->confirmEmail();
            break;

        default:
            throw new ApiException('Invalid requestType', 400, 2);
    }
} else {
    throw new ApiException('Method not allowed', 405, 2);
}

echo json_encode($response);
