<?php
session_start();
header('Content-Type: application/json');

$isLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);

echo json_encode([
    'loggedIn' => $isLoggedIn,
    'username' => $isLoggedIn ? $_SESSION['username'] : null
]);
?>

