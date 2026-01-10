<?php
// Database connection configuration
$host = "127.0.0.1";
$user = "root";
$password = "hussain";
$database = "premium_tool";

// Create a connection to the database
$conn = new mysqli($host, $user, $password, $database);

// Check the connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['name'];
    $product_category = $_POST['category'];
    $product_price = $_POST['price'];
    $product_color = $_POST['color'];

    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_data = file_get_contents($_FILES['image']['tmp_name']); // Read the image file content as binary data

        // Insert the product into the database
        $sql = $conn->prepare("INSERT INTO products12 (name, category, price, color, image) VALUES (?, ?, ?, ?, ?)");
        $sql->bind_param("ssdss", $product_name, $product_category, $product_price, $product_color, $image_data);

        if ($sql->execute()) {
            echo json_encode(["message" => "Product added successfully!"]);
        } else {
            echo json_encode(["error" => "Failed to add product: " . $conn->error]);
        }

        $sql->close();
    } else {
        echo json_encode(["error" => "Image file not provided or upload failed"]);
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
</head>
<body>
    <h1>Admin Panel: Add Product</h1>
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="name">Product Name:</label>
        <input type="text" id="name" name="name" required><br><br>

        <label for="category">Category:</label>
        <input type="text" id="category" name="category" required><br><br>

        <label for="price">Price:</label>
        <input type="number" id="price" name="price" required><br><br>

        <label for="color">Color:</label>
        <input type="text" id="color" name="color" required><br><br>

        <label for="image">Product Image:</label>
        <input type="file" id="image" name="image" accept="image/*" required><br><br>

        <button type="submit">Add Product</button>
    </form>
</body>
</html>
