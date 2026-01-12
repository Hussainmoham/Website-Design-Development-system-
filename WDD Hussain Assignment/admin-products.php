<?php
session_start();

// Database configuration
$host = "127.0.0.1";
$dbUser = "root";
$dbPass = "hussain";
$dbName = "premium_tool";

// Create database connection
$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if admin is logged in
if (!isset($_SESSION["admin_logged_in"])) {
    header("Location: admin.php");
    exit;
}

$message = '';
$message_type = '';

// Handle product operations
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["add_product"])) {
        $product_name = $conn->real_escape_string($_POST["product_name"]);
        $price = floatval($_POST["price"]);
        $description = $conn->real_escape_string($_POST["description"]);
        $category = isset($_POST["category"]) ? $conn->real_escape_string($_POST["category"]) : 'general';
        $stock = isset($_POST["stock"]) ? intval($_POST["stock"]) : 0;

        // Check if columns exist, if not add them
        $result = $conn->query("SHOW COLUMNS FROM products LIKE 'category'");
        if ($result->num_rows == 0) {
            $conn->query("ALTER TABLE products ADD COLUMN category VARCHAR(100) DEFAULT 'general'");
        }
        $result = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
        if ($result->num_rows == 0) {
            $conn->query("ALTER TABLE products ADD COLUMN stock INT DEFAULT 0");
        }

        // Handle file upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['product_image']['tmp_name'];
            $fileName = $_FILES['product_image']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            
            $uploadFileDir = 'uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Check which columns exist
                $hasCategory = $conn->query("SHOW COLUMNS FROM products LIKE 'category'")->num_rows > 0;
                $hasStock = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'")->num_rows > 0;
                
                if ($hasCategory && $hasStock) {
                    $sql = "INSERT INTO products (product_name, price, description, image, category, stock) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sdsssi", $product_name, $price, $description, $dest_path, $category, $stock);
                } else if ($hasCategory) {
                    $sql = "INSERT INTO products (product_name, price, description, image, category) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sdsss", $product_name, $price, $description, $dest_path, $category);
                } else {
                    $sql = "INSERT INTO products (product_name, price, description, image) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sdss", $product_name, $price, $description, $dest_path);
                }
                
                if ($stmt->execute()) {
                    $message = "Product added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding product: " . $conn->error;
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "Error uploading file.";
                $message_type = "error";
            }
        } else {
            $message = "Please select a product image.";
            $message_type = "error";
        }
    }
    
    if (isset($_POST["update_product"])) {
        $id = intval($_POST["product_id"]);
        $product_name = $conn->real_escape_string($_POST["product_name"]);
        $price = floatval($_POST["price"]);
        $description = $conn->real_escape_string($_POST["description"]);
        $category = isset($_POST["category"]) ? $conn->real_escape_string($_POST["category"]) : 'general';
        $stock = isset($_POST["stock"]) ? intval($_POST["stock"]) : 0;
        
        // Check if columns exist
        $hasCategory = $conn->query("SHOW COLUMNS FROM products LIKE 'category'")->num_rows > 0;
        $hasStock = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'")->num_rows > 0;
        
        if ($hasCategory && $hasStock) {
            $sql = "UPDATE products SET product_name=?, price=?, description=?, category=?, stock=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdssii", $product_name, $price, $description, $category, $stock, $id);
        } else if ($hasCategory) {
            $sql = "UPDATE products SET product_name=?, price=?, description=?, category=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdssi", $product_name, $price, $description, $category, $id);
        } else {
            $sql = "UPDATE products SET product_name=?, price=?, description=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdssi", $product_name, $price, $description, $id);
        }
        
        if ($stmt->execute()) {
            $message = "Product updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating product: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Product deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting product.";
        $message_type = "error";
    }
    $stmt->close();
}

// Get product for editing
$edit_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_product = $result->fetch_assoc();
    $stmt->close();
}

// Get all products
$products = $conn->query("SELECT * FROM products ORDER BY created_at DESC");

// Add stock column if it doesn't exist
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS stock INT DEFAULT 0");
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'General'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Premium Tool Admin</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Premium Styles -->
    <link rel="stylesheet" href="premium-styles.css">
    
    <style>
        /* Include the same admin styles from dashboard */
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: var(--bg-secondary);
        }
        
        .admin-sidebar {
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary-color) 50%, #1e40af 100%);
            color: white;
            padding: var(--space-xl);
            box-shadow: var(--shadow-xl);
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .admin-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }
            width: 280px;
            height: 100vh;
            overflow-y: auto;
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-2xl);
            padding-bottom: var(--space-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }
        
        .admin-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-lg);
        }
        
        .admin-logo h2 {
            color: white;
            font-size: var(--text-xl);
            margin: 0;
        }
        
        .admin-nav {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .admin-nav-item {
            margin-bottom: var(--space-sm);
        }
        
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md) var(--space-lg);
            color: var(--gray-300);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-fast);
            font-weight: var(--font-medium);
        }
        
        .admin-nav-link {
            color: rgba(255, 255, 255, 0.9);
            position: relative;
            z-index: 1;
        }
        
        .admin-nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(4px);
        }
        
        .admin-nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(4px);
        }
        
        .admin-nav-icon {
            width: 20px;
            text-align: center;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: var(--space-2xl);
            width: calc(100% - 280px);
        }
        
        .admin-header {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            margin-bottom: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--gray-100);
        }
        
        .admin-header h1 {
            margin: 0;
            color: var(--text-primary);
            font-size: var(--text-3xl);
        }
        
        .logout-btn {
            margin-top: var(--space-2xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--gray-700);
        }
        
        .logout-btn a {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md) var(--space-lg);
            color: var(--gray-300);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-fast);
            font-weight: var(--font-medium);
        }
        
        .logout-btn a:hover {
            background: var(--danger-color);
            color: white;
        }
        
        /* Product Management Specific Styles */
        .product-form-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            margin-bottom: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-xl);
        }
        
        .form-group {
            margin-bottom: var(--space-lg);
        }
        
        .form-label {
            display: block;
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
            font-size: var(--text-sm);
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: var(--space-md);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: var(--text-base);
            transition: all var(--transition-fast);
            background-color: var(--bg-primary);
            font-family: var(--font-sans);
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-md);
            padding: var(--space-xl);
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-lg);
            background: var(--gray-50);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .file-input-label:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
            color: var(--primary-color);
        }
        
        .products-table-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
        }
        
        .table-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: var(--text-xl);
        }
        
        .search-box {
            position: relative;
            width: 300px;
        }
        
        .search-input {
            width: 100%;
            padding: var(--space-md) var(--space-md) var(--space-md) 3rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: var(--text-sm);
        }
        
        .search-icon {
            position: absolute;
            left: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-lg);
        }
        
        .products-table th,
        .products-table td {
            padding: var(--space-lg);
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .products-table th {
            background: var(--gray-50);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .products-table td {
            color: var(--text-secondary);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            box-shadow: var(--shadow-sm);
        }
        
        .product-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-xs);
        }
        
        .product-category {
            font-size: var(--text-xs);
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .price-display {
            font-weight: var(--font-bold);
            color: var(--primary-color);
            font-size: var(--text-lg);
        }
        
        .stock-badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-md);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            text-transform: uppercase;
        }
        
        .stock-badge.in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }
        
        .stock-badge.low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: var(--secondary-color);
        }
        
        .stock-badge.out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .action-buttons {
            display: flex;
            gap: var(--space-sm);
        }
        
        .action-btn {
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }
        
        .action-btn.edit {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }
        
        .action-btn.edit:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .action-btn.delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .action-btn.delete:hover {
            background: var(--danger-color);
            color: white;
        }
        
        .message {
            padding: var(--space-lg);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-xl);
            font-weight: var(--font-medium);
        }
        
        .message.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        @media (max-width: 1024px) {
            .admin-layout {
                flex-direction: column;
            }
            
            .admin-sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            
            .admin-main {
                margin-left: 0;
                width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .search-box {
                width: 100%;
                margin-top: var(--space-md);
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <div class="admin-logo-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h2>Premium Tool</h2>
            </div>
            
            <nav>
                <ul class="admin-nav">
                    <li class="admin-nav-item">
                        <a href="admin-dashboard.php" class="admin-nav-link">
                            <i class="fas fa-tachometer-alt admin-nav-icon"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="admin-products.php" class="admin-nav-link active">
                            <i class="fas fa-box admin-nav-icon"></i>
                            Products
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="admin-customers.php" class="admin-nav-link">
                            <i class="fas fa-users admin-nav-icon"></i>
                            Customers
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="admin-orders.php" class="admin-nav-link">
                            <i class="fas fa-shopping-cart admin-nav-icon"></i>
                            Orders
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="admin-suppliers.php" class="admin-nav-link">
                            <i class="fas fa-truck admin-nav-icon"></i>
                            Suppliers
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="logout-btn">
                <a href="admin.php?logout=true">
                    <i class="fas fa-sign-out-alt admin-nav-icon"></i>
                    Logout
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1><?php echo $edit_product ? 'Edit Product' : 'Product Management'; ?></h1>
                <div style="display: flex; gap: var(--space-md);">
                    <?php if ($edit_product): ?>
                        <a href="admin-products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    <?php endif; ?>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Product Form -->
            <div class="product-form-card">
                <h3 style="margin-bottom: var(--space-xl); color: var(--text-primary);">
                    <?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?>
                </h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_product): ?>
                        <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label for="product_name" class="form-label">Product Name *</label>
                                <input type="text" id="product_name" name="product_name" class="form-input" 
                                       value="<?php echo $edit_product ? htmlspecialchars($edit_product['product_name']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="price" class="form-label">Price ($) *</label>
                                <input type="number" step="0.01" id="price" name="price" class="form-input" 
                                       value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category" class="form-label">Category *</label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="hand-tools" <?php echo ($edit_product && $edit_product['category'] == 'hand-tools') ? 'selected' : ''; ?>>Hand Tools</option>
                                    <option value="power-tools" <?php echo ($edit_product && $edit_product['category'] == 'power-tools') ? 'selected' : ''; ?>>Power Tools</option>
                                    <option value="safety" <?php echo ($edit_product && $edit_product['category'] == 'safety') ? 'selected' : ''; ?>>Safety Equipment</option>
                                    <option value="electrical" <?php echo ($edit_product && $edit_product['category'] == 'electrical') ? 'selected' : ''; ?>>Electrical</option>
                                    <option value="hardware" <?php echo ($edit_product && $edit_product['category'] == 'hardware') ? 'selected' : ''; ?>>Hardware</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock" class="form-label">Stock Quantity *</label>
                                <input type="number" id="stock" name="stock" class="form-input" 
                                       value="<?php echo $edit_product ? ($edit_product['stock'] ?? 0) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-textarea" 
                                          placeholder="Enter product description..."><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                            </div>
                            
                            <?php if (!$edit_product): ?>
                            <div class="form-group">
                                <label class="form-label">Product Image *</label>
                                <div class="file-input-wrapper">
                                    <input type="file" id="product_image" name="product_image" class="file-input" accept="image/*" required>
                                    <label for="product_image" class="file-input-label">
                                        <i class="fas fa-cloud-upload-alt" style="font-size: var(--text-xl);"></i>
                                        <span>Click to upload image or drag and drop</span>
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: var(--space-2xl); display: flex; gap: var(--space-md);">
                        <button type="submit" name="<?php echo $edit_product ? 'update_product' : 'add_product'; ?>" class="btn btn-primary">
                            <i class="fas fa-<?php echo $edit_product ? 'save' : 'plus'; ?>"></i>
                            <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                        </button>
                        <?php if ($edit_product): ?>
                            <a href="admin-products.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (!$edit_product): ?>
            <!-- Products Table -->
            <div class="products-table-card">
                <div class="table-header">
                    <h3>All Products</h3>
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search products..." id="searchProducts">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products->num_rows > 0): ?>
                                <?php while ($product = $products->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                 class="product-image">
                                        </td>
                                        <td>
                                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                            <div class="product-category"><?php echo htmlspecialchars($product['category'] ?? 'General'); ?></div>
                                        </td>
                                        <td>
                                            <div class="price-display">$<?php echo number_format($product['price'], 2); ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $stock = $product['stock'] ?? 0;
                                            $stock_class = $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
                                            $stock_text = $stock > 10 ? 'In Stock' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
                                            ?>
                                            <span class="stock-badge <?php echo $stock_class; ?>">
                                                <?php echo $stock_text; ?> (<?php echo $stock; ?>)
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="admin-products.php?edit=<?php echo $product['id']; ?>" class="action-btn edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="admin-products.php?delete=<?php echo $product['id']; ?>" 
                                                   class="action-btn delete" 
                                                   onclick="return confirm('Are you sure you want to delete this product?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: var(--space-2xl); color: var(--text-secondary);">
                                        No products found. Add your first product above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchProducts')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.products-table tbody tr');
            
            rows.forEach(row => {
                const productName = row.querySelector('.product-name')?.textContent.toLowerCase() || '';
                const category = row.querySelector('.product-category')?.textContent.toLowerCase() || '';
                
                if (productName.includes(searchTerm) || category.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // File input preview
        document.getElementById('product_image')?.addEventListener('change', function() {
            const label = document.querySelector('.file-input-label span');
            if (this.files.length > 0) {
                label.textContent = this.files[0].name;
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>