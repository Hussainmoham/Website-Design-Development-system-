<?php
// categories.php

// Database configuration – adjust these settings for your environment.
$host   = "127.0.0.1";
$dbUser = "root";
$dbPass = "hussain";
$dbName = "premium_tool";

// Create the database connection.
$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch categories from the database.
$sql = "SELECT * FROM categories ORDER BY name ASC";
$result = $conn->query($sql);
$categories = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Categories - Premium Tool Store</title>
  <style>
    /* Global Styles */
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      margin: 0;
      padding: 0;
    }
    
    /* Header */
    .header {
      background: #333;
      padding: 20px;
      color: #fff;
      text-align: center;
    }
    
    /* Categories Grid */
    .category-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      padding: 20px;
    }
    
    /* Category Card */
    .category-card {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }
    
    .category-card:hover {
      transform: translateY(-4px);
    }
    
    /* Category Image */
    .category-image img {
      width: 100%;
      height: 150px;
      object-fit: cover;
      display: block;
    }
    
    /* Category Details */
    .category-details {
      padding: 15px;
    }
    
    .category-details h2 {
      margin: 0 0 10px;
      font-size: 1.2rem;
    }
    
    .category-details p {
      font-size: 0.9rem;
      color: #555;
    }
    
    .category-details a {
      display: inline-block;
      margin-top: 10px;
      text-decoration: none;
      color: #337ab7;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>Product Categories</h1>
  </div>
  <div class="category-grid">
    <?php if (!empty($categories)): ?>
      <?php foreach ($categories as $category): ?>
        <div class="category-card">
          <?php if (isset($category["image"]) && !empty($category["image"])): ?>
            <div class="category-image">
              <img src="<?php echo $category["image"]; ?>" alt="<?php echo htmlspecialchars($category["name"]); ?>">
            </div>
          <?php endif; ?>
          <div class="category-details">
            <h2><?php echo htmlspecialchars($category["name"]); ?></h2>
            <p><?php echo htmlspecialchars($category["description"]); ?></p>
            <!-- Link to products page, filtering by category_id -->
            <a href="products.php?category_id=<?php echo $category["id"]; ?>">View Products</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align: center; font-size: 1.1rem;">No categories found.</p>
    <?php endif; ?>
  </div>
</body>
</html>
