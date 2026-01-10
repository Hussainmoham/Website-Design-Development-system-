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

// Create suppliers table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        contact_person VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Create product_suppliers junction table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS product_suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        supplier_id INT,
        supply_price DECIMAL(10,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )
");

$message = '';
$message_type = '';

// Handle supplier operations
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["add_supplier"])) {
        $name = $conn->real_escape_string($_POST["name"]);
        $email = $conn->real_escape_string($_POST["email"]);
        $phone = $conn->real_escape_string($_POST["phone"]);
        $address = $conn->real_escape_string($_POST["address"]);
        $contact_person = $conn->real_escape_string($_POST["contact_person"]);
        
        $sql = "INSERT INTO suppliers (name, email, phone, address, contact_person) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $name, $email, $phone, $address, $contact_person);
        
        if ($stmt->execute()) {
            $message = "Supplier added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding supplier: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    
    if (isset($_POST["update_supplier"])) {
        $id = intval($_POST["supplier_id"]);
        $name = $conn->real_escape_string($_POST["name"]);
        $email = $conn->real_escape_string($_POST["email"]);
        $phone = $conn->real_escape_string($_POST["phone"]);
        $address = $conn->real_escape_string($_POST["address"]);
        $contact_person = $conn->real_escape_string($_POST["contact_person"]);
        
        $sql = "UPDATE suppliers SET name=?, email=?, phone=?, address=?, contact_person=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $email, $phone, $address, $contact_person, $id);
        
        if ($stmt->execute()) {
            $message = "Supplier updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating supplier: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    
    if (isset($_POST["assign_product"])) {
        $product_id = intval($_POST["product_id"]);
        $supplier_id = intval($_POST["supplier_id"]);
        $supply_price = floatval($_POST["supply_price"]);
        
        // Check if assignment already exists
        $check = $conn->prepare("SELECT id FROM product_suppliers WHERE product_id = ? AND supplier_id = ?");
        $check->bind_param("ii", $product_id, $supplier_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $message = "Product is already assigned to this supplier!";
            $message_type = "error";
        } else {
            $sql = "INSERT INTO product_suppliers (product_id, supplier_id, supply_price) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iid", $product_id, $supplier_id, $supply_price);
            
            if ($stmt->execute()) {
                $message = "Product assigned to supplier successfully!";
                $message_type = "success";
            } else {
                $message = "Error assigning product: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle supplier deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Supplier deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting supplier.";
        $message_type = "error";
    }
    $stmt->close();
}

// Handle product assignment removal
if (isset($_GET['remove_assignment']) && is_numeric($_GET['remove_assignment'])) {
    $assignment_id = intval($_GET['remove_assignment']);
    $stmt = $conn->prepare("DELETE FROM product_suppliers WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    if ($stmt->execute()) {
        $message = "Product assignment removed successfully!";
        $message_type = "success";
    } else {
        $message = "Error removing assignment.";
        $message_type = "error";
    }
    $stmt->close();
}

// Get supplier for editing
$edit_supplier = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_supplier = $result->fetch_assoc();
    $stmt->close();
}

// Get all suppliers with product counts
$suppliers = $conn->query("
    SELECT s.*, COUNT(ps.id) as product_count
    FROM suppliers s 
    LEFT JOIN product_suppliers ps ON s.id = ps.supplier_id 
    GROUP BY s.id 
    ORDER BY s.created_at DESC
");

// Get all products for assignment dropdown
$products = $conn->query("SELECT id, product_name FROM products ORDER BY product_name");

// Get product assignments
$assignments = $conn->query("
    SELECT ps.*, p.product_name, s.name as supplier_name
    FROM product_suppliers ps
    JOIN products p ON ps.product_id = p.id
    JOIN suppliers s ON ps.supplier_id = s.id
    ORDER BY ps.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Premium Tool Admin</title>
    
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
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-fast);
            font-weight: var(--font-medium);
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
        
        /* Supplier Management Specific Styles */
        .supplier-form-card {
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
            min-height: 100px;
        }
        
        .suppliers-table-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
            overflow: hidden;
            margin-bottom: var(--space-2xl);
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
        
        .suppliers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-lg);
        }
        
        .suppliers-table th,
        .suppliers-table td {
            padding: var(--space-lg);
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .suppliers-table th {
            background: var(--gray-50);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .suppliers-table td {
            color: var(--text-secondary);
        }
        
        .supplier-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-xs);
        }
        
        .supplier-contact {
            font-size: var(--text-sm);
            color: var(--text-light);
        }
        
        .product-count {
            font-weight: var(--font-bold);
            color: var(--primary-color);
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
        
        .assignments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-lg);
        }
        
        .assignments-table th,
        .assignments-table td {
            padding: var(--space-md);
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .assignments-table th {
            background: var(--gray-50);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            font-size: var(--text-sm);
        }
        
        .supply-price {
            font-weight: var(--font-bold);
            color: var(--accent-color);
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
        
        .tabs {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-2xl);
        }
        
        .tab-btn {
            padding: var(--space-md) var(--space-lg);
            background: var(--gray-100);
            border: none;
            border-radius: var(--radius-lg);
            font-weight: var(--font-semibold);
            cursor: pointer;
            transition: all var(--transition-fast);
            color: var(--text-secondary);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
                        <a href="admin-products.php" class="admin-nav-link">
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
                        <a href="admin-suppliers.php" class="admin-nav-link active">
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
                <h1><?php echo $edit_supplier ? 'Edit Supplier' : 'Supplier Management'; ?></h1>
                <div style="display: flex; gap: var(--space-md);">
                    <?php if ($edit_supplier): ?>
                        <a href="admin-suppliers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Suppliers
                        </a>
                    <?php endif; ?>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$edit_supplier): ?>
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('suppliers')">Suppliers</button>
                <button class="tab-btn" onclick="showTab('assignments')">Product Assignments</button>
            </div>
            <?php endif; ?>
            
            <!-- Supplier Form -->
            <div class="supplier-form-card">
                <h3 style="margin-bottom: var(--space-xl); color: var(--text-primary);">
                    <?php echo $edit_supplier ? 'Edit Supplier' : 'Add New Supplier'; ?>
                </h3>
                
                <form method="POST">
                    <?php if ($edit_supplier): ?>
                        <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label for="name" class="form-label">Company Name *</label>
                                <input type="text" id="name" name="name" class="form-input" 
                                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-input" 
                                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['email']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['phone']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person" class="form-input" 
                                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['contact_person']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address" class="form-label">Address</label>
                                <textarea id="address" name="address" class="form-textarea" 
                                          placeholder="Enter supplier address..."><?php echo $edit_supplier ? htmlspecialchars($edit_supplier['address']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: var(--space-2xl); display: flex; gap: var(--space-md);">
                        <button type="submit" name="<?php echo $edit_supplier ? 'update_supplier' : 'add_supplier'; ?>" class="btn btn-primary">
                            <i class="fas fa-<?php echo $edit_supplier ? 'save' : 'plus'; ?>"></i>
                            <?php echo $edit_supplier ? 'Update Supplier' : 'Add Supplier'; ?>
                        </button>
                        <?php if ($edit_supplier): ?>
                            <a href="admin-suppliers.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (!$edit_supplier): ?>
            <!-- Suppliers Tab -->
            <div id="suppliers-tab" class="tab-content active">
                <div class="suppliers-table-card">
                    <div class="table-header">
                        <h3>All Suppliers</h3>
                        <div class="search-box">
                            <input type="text" class="search-input" placeholder="Search suppliers..." id="searchSuppliers">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="suppliers-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Products</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($suppliers->num_rows > 0): ?>
                                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                                <?php if (!empty($supplier['contact_person'])): ?>
                                                    <div class="supplier-contact">Contact: <?php echo htmlspecialchars($supplier['contact_person']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: var(--space-xs);">
                                                    <div>
                                                        <i class="fas fa-envelope" style="margin-right: var(--space-xs);"></i>
                                                        <?php echo htmlspecialchars($supplier['email']); ?>
                                                    </div>
                                                    <?php if (!empty($supplier['phone'])): ?>
                                                        <div>
                                                            <i class="fas fa-phone" style="margin-right: var(--space-xs);"></i>
                                                            <?php echo htmlspecialchars($supplier['phone']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="product-count"><?php echo $supplier['product_count']; ?></span> products
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($supplier['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="admin-suppliers.php?edit=<?php echo $supplier['id']; ?>" class="action-btn edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="admin-suppliers.php?delete=<?php echo $supplier['id']; ?>" 
                                                       class="action-btn delete" 
                                                       onclick="return confirm('Are you sure you want to delete this supplier?');">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: var(--space-2xl); color: var(--text-secondary);">
                                            No suppliers found. Add your first supplier above.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Assignments Tab -->
            <div id="assignments-tab" class="tab-content">
                <!-- Product Assignment Form -->
                <div class="supplier-form-card">
                    <h3 style="margin-bottom: var(--space-xl); color: var(--text-primary);">Assign Product to Supplier</h3>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="product_id" class="form-label">Product *</label>
                                <select id="product_id" name="product_id" class="form-select" required>
                                    <option value="">Select Product</option>
                                    <?php 
                                    $products->data_seek(0); // Reset pointer
                                    while ($product = $products->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="supplier_id" class="form-label">Supplier *</label>
                                <select id="supplier_id" name="supplier_id" class="form-select" required>
                                    <option value="">Select Supplier</option>
                                    <?php 
                                    $suppliers->data_seek(0); // Reset pointer
                                    while ($supplier = $suppliers->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="supply_price" class="form-label">Supply Price ($) *</label>
                                <input type="number" step="0.01" id="supply_price" name="supply_price" class="form-input" required>
                            </div>
                        </div>
                        
                        <div style="margin-top: var(--space-xl);">
                            <button type="submit" name="assign_product" class="btn btn-primary">
                                <i class="fas fa-link"></i> Assign Product
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Assignments Table -->
                <div class="suppliers-table-card">
                    <div class="table-header">
                        <h3>Product Assignments</h3>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="assignments-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Supplier</th>
                                    <th>Supply Price</th>
                                    <th>Assigned</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($assignments->num_rows > 0): ?>
                                    <?php while ($assignment = $assignments->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['supplier_name']); ?></td>
                                            <td>
                                                <span class="supply-price">$<?php echo number_format($assignment['supply_price'], 2); ?></span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($assignment['created_at'])); ?></td>
                                            <td>
                                                <a href="admin-suppliers.php?remove_assignment=<?php echo $assignment['id']; ?>" 
                                                   class="action-btn delete" 
                                                   onclick="return confirm('Are you sure you want to remove this assignment?');">
                                                    <i class="fas fa-unlink"></i> Remove
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: var(--space-2xl); color: var(--text-secondary);">
                                            No product assignments found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // Search functionality
        document.getElementById('searchSuppliers')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.suppliers-table tbody tr');
            
            rows.forEach(row => {
                const supplierName = row.querySelector('.supplier-name')?.textContent.toLowerCase() || '';
                const contactPerson = row.querySelector('.supplier-contact')?.textContent.toLowerCase() || '';
                
                if (supplierName.includes(searchTerm) || contactPerson.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>