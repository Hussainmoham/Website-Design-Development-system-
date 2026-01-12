<?php
// getProducts.php

header('Content-Type: application/json');

// Database connection details
$host   = "127.0.0.1";
$dbUser = "root";
$dbPass = "hussain";  // Update if necessary
$dbName = "premium_tool"; // Make sure this matches your database name

// Create a connection to MySQL
$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

// Query all products (adjust the query as needed)
$sql    = "SELECT * FROM products ORDER BY created_at DESC";
$result = $conn->query($sql);

$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();
echo json_encode($products);
?>
