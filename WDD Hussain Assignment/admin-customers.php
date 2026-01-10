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

// Handle customer operations
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["add_customer"])) {
        $name = $conn->real_escape_string($_POST["name"]);
        $email = $conn->real_escape_string($_POST["email"]);
        $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $phone = isset($_POST["phone"]) ? $conn->real_escape_string($_POST["phone"]) : '';
        $address = isset($_POST["address"]) ? $conn->real_escape_string($_POST["address"]) : '';
        
        // Check if columns exist, if not add them
        $result = $conn->query("SHOW COLUMNS FROM customers LIKE 'phone'");
        if ($result->num_rows == 0) {
            $conn->query("ALTER TABLE customers ADD COLUMN phone VARCHAR(20) NULL");
        }
        $result = $conn->query("SHOW COLUMNS FROM customers LIKE 'address'");
        if ($result->num_rows == 0) {
            $conn->query("ALTER TABLE customers ADD COLUMN address TEXT NULL");
        }
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            $message = "Email already exists!";
            $message_type = "error";
        } else {
            $hasPhone = $conn->query("SHOW COLUMNS FROM customers LIKE 'phone'")->num_rows > 0;
            $hasAddress = $conn->query("SHOW COLUMNS FROM customers LIKE 'address'")->num_rows > 0;
            
            if ($hasPhone && $hasAddress) {
                $sql = "INSERT INTO customers (name, email, password, phone, address) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $name, $email, $password, $phone, $address);
            } else {
                $sql = "INSERT INTO customers (name, email, password) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $name, $email, $password);
            }
            
            if ($stmt->execute()) {
                $message = "Customer added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding customer: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check_email->close();
    }
    
    if (isset($_POST["update_customer"])) {
        $id = intval($_POST["customer_id"]);
        $name = $conn->real_escape_string($_POST["name"]);
        $email = $conn->real_escape_string($_POST["email"]);
        $phone = isset($_POST["phone"]) ? $conn->real_escape_string($_POST["phone"]) : '';
        $address = isset($_POST["address"]) ? $conn->real_escape_string($_POST["address"]) : '';
        
        $hasPhone = $conn->query("SHOW COLUMNS FROM customers LIKE 'phone'")->num_rows > 0;
        $hasAddress = $conn->query("SHOW COLUMNS FROM customers LIKE 'address'")->num_rows > 0;
        
        if ($hasPhone && $hasAddress) {
            $sql = "UPDATE customers SET name=?, email=?, phone=?, address=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $name, $email, $phone, $address, $id);
        } else {
            $sql = "UPDATE customers SET name=?, email=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $email, $id);
        }
        
        if ($stmt->execute()) {
            $message = "Customer updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating customer: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Handle customer deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Customer deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting customer.";
        $message_type = "error";
    }
    $stmt->close();
}

// Get customer for editing
$edit_customer = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_customer = $result->fetch_assoc();
    $stmt->close();
}

// Add missing columns if they don't exist
$conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT ''");
$conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS address TEXT DEFAULT ''");

// Get all customers with order statistics
$customers = $conn->query("
    SELECT c.*, 
           COUNT(o.id) as total_orders,
           COALESCE(SUM(o.total), 0) as total_spent
    FROM customers c 
    LEFT JOIN orders o ON c.id = o.customer_id 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Premium Tool Admin</title>
    
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
        
        /* Customer Management Specific Styles */
        .customer-form-card {
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
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .customers-table-card {
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
        
        .customers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-lg);
        }
        
        .customers-table th,
        .customers-table td {
            padding: var(--space-lg);
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .customers-table th {
            background: var(--gray-50);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .customers-table td {
            color: var(--text-secondary);
        }
        
        .customer-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: var(--font-bold);
            font-size: var(--text-lg);
        }
        
        .customer-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-xs);
        }
        
        .customer-email {
            font-size: var(--text-sm);
            color: var(--text-light);
        }
        
        .stats-display {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .stat-item {
            font-size: var(--text-sm);
        }
        
        .stat-value {
            font-weight: var(--font-semibold);
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
        
        .action-btn.view {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }
        
        .action-btn.view:hover {
            background: var(--accent-color);
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
                        <a href="admin-products.php" class="admin-nav-link">
                            <i class="fas fa-box admin-nav-icon"></i>
                            Products
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="admin-customers.php" class="admin-nav-link active">
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
                <h1><?php echo $edit_customer ? 'Edit Customer' : 'Customer Management'; ?></h1>
                <div style="display: flex; gap: var(--space-md);">
                    <?php if ($edit_customer): ?>
                        <a href="admin-customers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Customers
                        </a>
                    <?php endif; ?>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Customer Form -->
            <div class="customer-form-card">
                <h3 style="margin-bottom: var(--space-xl); color: var(--text-primary);">
                    <?php echo $edit_customer ? 'Edit Customer' : 'Add New Customer'; ?>
                </h3>
                
                <form method="POST">
                    <?php if ($edit_customer): ?>
                        <input type="hidden" name="customer_id" value="<?php echo $edit_customer['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" id="name" name="name" class="form-input" 
                                       value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-input" 
                                       value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['email']) : ''; ?>" required>
                            </div>
                            
                            <?php if (!$edit_customer): ?>
                            <div class="form-group">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" id="password" name="password" class="form-input" required>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['phone'] ?? '') : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address" class="form-label">Address</label>
                                <textarea id="address" name="address" class="form-textarea" 
                                          placeholder="Enter customer address..."><?php echo $edit_customer ? htmlspecialchars($edit_customer['address'] ?? '') : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: var(--space-2xl); display: flex; gap: var(--space-md);">
                        <button type="submit" name="<?php echo $edit_customer ? 'update_customer' : 'add_customer'; ?>" class="btn btn-primary">
                            <i class="fas fa-<?php echo $edit_customer ? 'save' : 'plus'; ?>"></i>
                            <?php echo $edit_customer ? 'Update Customer' : 'Add Customer'; ?>
                        </button>
                        <?php if ($edit_customer): ?>
                            <a href="admin-customers.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (!$edit_customer): ?>
            <!-- Customers Table -->
            <div class="customers-table-card">
                <div class="table-header">
                    <h3>All Customers</h3>
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search customers..." id="searchCustomers">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($customers->num_rows > 0): ?>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: var(--space-md);">
                                                <div class="customer-avatar">
                                                    <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                                    <div class="customer-email"><?php echo htmlspecialchars($customer['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="stats-display">
                                                <?php if (!empty($customer['phone'])): ?>
                                                    <div class="stat-item">
                                                        <i class="fas fa-phone" style="margin-right: var(--space-xs);"></i>
                                                        <?php echo htmlspecialchars($customer['phone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($customer['address'])): ?>
                                                    <div class="stat-item">
                                                        <i class="fas fa-map-marker-alt" style="margin-right: var(--space-xs);"></i>
                                                        <?php echo htmlspecialchars(substr($customer['address'], 0, 30)) . (strlen($customer['address']) > 30 ? '...' : ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="stat-value"><?php echo $customer['total_orders']; ?></span> orders
                                        </td>
                                        <td>
                                            <span class="stat-value">$<?php echo number_format($customer['total_spent'], 2); ?></span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="admin-customer-orders.php?customer_id=<?php echo $customer['id']; ?>" class="action-btn view">
                                                    <i class="fas fa-eye"></i> Orders
                                                </a>
                                                <a href="admin-customers.php?edit=<?php echo $customer['id']; ?>" class="action-btn edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="admin-customers.php?delete=<?php echo $customer['id']; ?>" 
                                                   class="action-btn delete" 
                                                   onclick="return confirm('Are you sure you want to delete this customer?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: var(--space-2xl); color: var(--text-secondary);">
                                        No customers found. Add your first customer above.
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
        document.getElementById('searchCustomers')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.customers-table tbody tr');
            
            rows.forEach(row => {
                const customerName = row.querySelector('.customer-name')?.textContent.toLowerCase() || '';
                const customerEmail = row.querySelector('.customer-email')?.textContent.toLowerCase() || '';
                
                if (customerName.includes(searchTerm) || customerEmail.includes(searchTerm)) {
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