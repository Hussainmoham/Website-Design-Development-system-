<?php
// Set content type to JSON
header('Content-Type: application/json');

// Start a session
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
    $username = isset($_POST["username"]) ? trim($_POST["username"]) : '';
    $password = isset($_POST["password"]) ? $_POST["password"] : '';

    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter both username and password.'
        ]);
        exit();
    }

    // Prepare and execute SQL query - check username only
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Verify the password (check both plain text and hashed passwords)
        $passwordMatch = false;
        
        // Check if password is hashed (starts with $2y$ for bcrypt or length 60)
        if (strlen($row["password"]) === 60 && substr($row["password"], 0, 4) === '$2y$') {
            // Password is hashed, use password_verify
            $passwordMatch = password_verify($password, $row["password"]);
        } else {
            // Password is stored as plain text (for backward compatibility)
            $passwordMatch = ($password === $row["password"]);
        }

        if ($passwordMatch) {
            // Set session variables
            $_SESSION["username"] = $row["username"];
            $_SESSION["user_id"] = $row["id"] ?? null;
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Login successful!',
                'redirect' => 'prp.html'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Incorrect password. Please try again.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No user found with that username.'
        ]);
    }

    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

// Close the connection
$conn->close();
?>