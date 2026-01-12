<?php
session_start();
header('Content-Type: application/json');

$response = [
    'showMessage' => false,
    'username' => null,
    'message' => ''
];

if (isset($_SESSION["show_login_message"]) && $_SESSION["show_login_message"]) {
    $response['showMessage'] = true;
    $response['username'] = isset($_SESSION["login_username"]) ? $_SESSION["login_username"] : 'User';
    $response['message'] = 'Welcome back! You have successfully logged in.';
    
    // Clear the flag after reading
    unset($_SESSION["show_login_message"]);
}

echo json_encode($response);
?>
