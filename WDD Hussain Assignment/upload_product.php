<?php
// Database configuration – adjust these settings for your environment.
$host   = "127.0.0.1";
$dbUser = "root";
$dbPass = "hussain"; // Update if you have a password.
$dbName = "premium_tool";

// Create database connection.
$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    // If it's a GET request, output a JSON error.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json');
        die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}

// If the request is a POST, process the product upload.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize form inputs.
    $product_name = isset($_POST['product_name']) ? $conn->real_escape_string($_POST['product_name']) : '';
    $price        = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $description  = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';

    // Handle file upload.
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['product_image']['tmp_name'];
        $fileName      = $_FILES['product_image']['name'];
        $fileNameCmps  = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Sanitize file name and create a unique name.
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        
        // Define upload directory.
        $uploadFileDir = 'uploads/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0777, true);
        }
        $dest_path = $uploadFileDir . $newFileName;
        
        if (!move_uploaded_file($fileTmpPath, $dest_path)) {
            die("Error moving the uploaded file.");
        }
    } else {
        die("There was an error uploading the file.");
    }

    // Insert product into the database.
    $sql = "INSERT INTO products (product_name, price, description, image) 
            VALUES ('$product_name', '$price', '$description', '$dest_path')";
    if ($conn->query($sql) === TRUE) {
        echo "Product added successfully! <br><br>
              <a href='admin.php'>Add another product</a> | <a href='index.php'>View Store</a>";
    } else {
        echo "Error: " . $conn->error;
    }
    $conn->close();
    exit; // Stop further execution so that the JSON part below isn’t run.
}

// If the request is GET, output JSON data.
header('Content-Type: application/json');
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
