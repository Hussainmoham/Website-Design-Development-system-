<?php
session_start();
header('Content-Type: application/json');

// Database configuration
$host = "127.0.0.1";
$dbUser = "root";
$dbPass = "hussain";
$dbName = "premium_tool";

// Create database connection
$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$action = $input['action'];
$response = ['success' => false];

switch ($action) {
    case 'add':
        if (isset($input['product_id']) && isset($input['quantity'])) {
            $product_id = intval($input['product_id']);
            $quantity = intval($input['quantity']);
            
            // For now, just store in session
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = $quantity;
            }
            
            $response['success'] = true;
            $response['message'] = 'Product added to cart';
        }
        break;
        
    case 'remove':
        if (isset($input['product_id'])) {
            $product_id = intval($input['product_id']);
            
            if (isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
                $response['success'] = true;
                $response['message'] = 'Product removed from cart';
            }
        }
        break;
        
    case 'update':
        if (isset($input['product_id']) && isset($input['quantity'])) {
            $product_id = intval($input['product_id']);
            $quantity = intval($input['quantity']);
            
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                $_SESSION['cart'][$product_id] = $quantity;
            }
            
            $response['success'] = true;
            $response['message'] = 'Cart updated';
        }
        break;
        
    case 'clear':
        $_SESSION['cart'] = [];
        $response['success'] = true;
        $response['message'] = 'Cart cleared';
        break;
        
    case 'get':
        $response['success'] = true;
        $response['cart'] = $_SESSION['cart'] ?? [];
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
}

echo json_encode($response);
$conn->close();
?>