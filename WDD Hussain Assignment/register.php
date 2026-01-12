<?php
// Set content type to JSON
header('Content-Type: application/json');

// Start session
session_start();

// Database connection settings
$servername = "127.0.0.1";
$dbusername = "root";
$dbpassword = "hussain";
$dbname = "premium_tool";

// Create a connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check the connection
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit();
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = isset($_POST["firstName"]) ? trim($_POST["firstName"]) : '';
    $lastName = isset($_POST["lastName"]) ? trim($_POST["lastName"]) : '';
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
    $username = isset($_POST["username"]) ? trim($_POST["username"]) : '';
    $password = isset($_POST["password"]) ? $_POST["password"] : '';
    $confirmPassword = isset($_POST["confirmPassword"]) ? $_POST["confirmPassword"] : '';

    // Validate all required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ]);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address.'
        ]);
        exit();
    }

    // Check if passwords match
    if ($password !== $confirmPassword) {
        echo json_encode([
            'success' => false,
            'message' => 'Passwords do not match.'
        ]);
        exit();
    }

    // Check if username already exists
    $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkUser->bind_param("s", $username);
    $checkUser->execute();
    $result = $checkUser->get_result();

    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Username already exists. Please choose a different one.'
        ]);
        $checkUser->close();
        exit();
    }
    $checkUser->close();

    // Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into the `users` table (only username and password columns exist)
    $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ss", $username, $hashedPassword);
        
        if ($stmt->execute()) {
            // Set session variables
            $_SESSION["username"] = $username;
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful! Redirecting to login...',
                'redirect' => 'login.html'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Registration failed: ' . $stmt->error
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

// Close the connection
$conn->close();
?>
