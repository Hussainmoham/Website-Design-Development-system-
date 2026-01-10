<?php
// Database migration script to add missing columns and tables
// Run this once to update your database structure

$host = "127.0.0.1";
$dbUser = "root";
$dbPass = "hussain";
$dbName = "premium_tool";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Updating Database Structure...</h2>";

// Add stock column to products table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN stock INT DEFAULT 0");
    echo "<p>✓ Added 'stock' column to products table</p>";
} else {
    echo "<p>✓ 'stock' column already exists</p>";
}

// Add category column to products table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'category'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN category VARCHAR(100) DEFAULT 'general'");
    echo "<p>✓ Added 'category' column to products table</p>";
} else {
    echo "<p>✓ 'category' column already exists</p>";
}

// Add supplier_id column to products table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'supplier_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN supplier_id INT NULL");
    echo "<p>✓ Added 'supplier_id' column to products table</p>";
} else {
    echo "<p>✓ 'supplier_id' column already exists</p>";
}

// Create suppliers table if it doesn't exist
$result = $conn->query("SHOW TABLES LIKE 'suppliers'");
if ($result->num_rows == 0) {
    $conn->query("
        CREATE TABLE suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            contact_person VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p>✓ Created 'suppliers' table</p>";
} else {
    echo "<p>✓ 'suppliers' table already exists</p>";
}

// Create product_suppliers junction table if it doesn't exist
$result = $conn->query("SHOW TABLES LIKE 'product_suppliers'");
if ($result->num_rows == 0) {
    $conn->query("
        CREATE TABLE product_suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            supplier_id INT,
            supply_price DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✓ Created 'product_suppliers' table</p>";
} else {
    echo "<p>✓ 'product_suppliers' table already exists</p>";
}

// Add phone and address columns to customers table if they don't exist
$result = $conn->query("SHOW COLUMNS FROM customers LIKE 'phone'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE customers ADD COLUMN phone VARCHAR(20) NULL");
    echo "<p>✓ Added 'phone' column to customers table</p>";
} else {
    echo "<p>✓ 'phone' column already exists</p>";
}

$result = $conn->query("SHOW COLUMNS FROM customers LIKE 'address'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE customers ADD COLUMN address TEXT NULL");
    echo "<p>✓ Added 'address' column to customers table</p>";
} else {
    echo "<p>✓ 'address' column already exists</p>";
}

// Add status column to orders table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'pending'");
    echo "<p>✓ Added 'status' column to orders table</p>";
} else {
    echo "<p>✓ 'status' column already exists</p>";
}

echo "<h2 style='color: green;'>Database update completed successfully!</h2>";
echo "<p><a href='admin-dashboard.php'>Go to Admin Dashboard</a></p>";

$conn->close();
?>

