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

// Handle order status update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    $order_id = intval($_POST["order_id"]);
    $status = $conn->real_escape_string($_POST["status"]);
    
    $sql = "UPDATE orders SET status=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $order_id);
    
    if ($stmt->execute()) {
        $message = "Order status updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating order status: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Add status column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'pending'");
}

// Get order details if viewing specific order
$order_details = null;
$order_items = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $order_id = intval($_GET['view']);
    
    // Check if customers table has phone/address columns
    $hasPhone = $conn->query("SHOW COLUMNS FROM customers LIKE 'phone'")->num_rows > 0;
    $hasAddress = $conn->query("SHOW COLUMNS FROM customers LIKE 'address'")->num_rows > 0;
    
    if ($hasPhone && $hasAddress) {
        $stmt = $conn->prepare("
            SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone, c.address 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT o.*, c.name as customer_name, c.email as customer_email
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ?
        ");
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_details = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    if ($order_details) {
        $stmt = $conn->prepare("
            SELECT oi.*, p.product_name, p.image, p.price as current_price
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order_items = $stmt->get_result();
        $stmt->close();
    }
}

// Get all orders with customer info
$orders = $conn->query("
    SELECT o.*, c.name as customer_name, c.email as customer_email,
           COUNT(oi.id) as item_count
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Premium Tool Admin</title>
    
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
        
        .admin-sidebar {
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
        
        /* Order Management Specific Styles */
        .orders-table-card {
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
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-lg);
        }
        
        .orders-table th,
        .orders-table td {
            padding: var(--space-lg);
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .orders-table th {
            background: var(--gray-50);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .orders-table td {
            color: var(--text-secondary);
        }
        
        .order-id {
            font-weight: var(--font-bold);
            color: var(--text-primary);
            font-family: var(--font-mono);
        }
        
        .customer-info {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .customer-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
        }
        
        .customer-email {
            font-size: var(--text-sm);
            color: var(--text-light);
        }
        
        .order-total {
            font-weight: var(--font-bold);
            color: var(--primary-color);
            font-size: var(--text-lg);
        }
        
        .status-badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-md);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            text-transform: uppercase;
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--secondary-color);
        }
        
        .status-badge.processing {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }
        
        .status-badge.shipped {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }
        
        .status-badge.delivered {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        
        .status-badge.cancelled {
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
        
        .action-btn.view {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }
        
        .action-btn.view:hover {
            background: var(--accent-color);
            color: white;
        }
        
        .status-select {
            padding: var(--space-xs) var(--space-sm);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: var(--text-xs);
            background: var(--bg-primary);
        }
        
        .order-details-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            margin-bottom: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-2xl);
            margin-bottom: var(--space-2xl);
        }
        
        .order-info-section {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-sm) 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: var(--font-semibold);
            color: var(--text-secondary);
        }
        
        .info-value {
            color: var(--text-primary);
            font-weight: var(--font-medium);
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-lg);
        }
        
        .order-items-table th,
        .order-items-table td {
            padding: var(--space-md);
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .order-items-table th {
            background: var(--gray-50);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            font-size: var(--text-sm);
        }
        
        .item-image {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            object-fit: cover;
        }
        
        .item-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
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
            
            .search-box {
                width: 100%;
                margin-top: var(--space-md);
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .order-details-grid {
                grid-template-columns: 1fr;
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
                        <a href="admin-orders.php" class="admin-nav-link active">
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
                <h1><?php echo $order_details ? 'Order Details' : 'Order Management'; ?></h1>
                <div style="display: flex; gap: var(--space-md);">
                    <?php if ($order_details): ?>
                        <a href="admin-orders.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                    <?php endif; ?>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($order_details): ?>
                <!-- Order Details View -->
                <div class="order-details-card">
                    <div class="order-details-grid">
                        <div>
                            <h3 style="margin-bottom: var(--space-lg); color: var(--text-primary);">
                                Order #<?php echo $order_details['id']; ?>
                            </h3>
                            
                            <div class="order-info-section">
                                <div class="info-row">
                                    <span class="info-label">Customer:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($order_details['customer_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($order_details['customer_email']); ?></span>
                                </div>
                                <?php if (!empty($order_details['phone'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Phone:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($order_details['phone']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($order_details['address'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Address:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($order_details['address']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label">Order Date:</span>
                                    <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($order_details['created_at'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Total:</span>
                                    <span class="info-value order-total">$<?php echo number_format($order_details['total'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="margin-bottom: var(--space-lg); color: var(--text-primary);">Order Status</h4>
                            <form method="POST" style="margin-bottom: var(--space-lg);">
                                <input type="hidden" name="order_id" value="<?php echo $order_details['id']; ?>">
                                <div style="margin-bottom: var(--space-md);">
                                    <select name="status" class="form-input" style="margin-bottom: var(--space-md);">
                                        <option value="pending" <?php echo ($order_details['status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo ($order_details['status'] ?? 'pending') == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo ($order_details['status'] ?? 'pending') == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo ($order_details['status'] ?? 'pending') == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo ($order_details['status'] ?? 'pending') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                            </form>
                            
                            <div style="text-align: center;">
                                <span class="status-badge <?php echo $order_details['status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($order_details['status'] ?? 'pending'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <h4 style="margin-bottom: var(--space-lg); color: var(--text-primary);">Order Items</h4>
                    <div style="overflow-x: auto;">
                        <table class="order-items-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($order_items && $order_items->num_rows > 0): ?>
                                    <?php 
                                    $subtotal = 0;
                                    while ($item = $order_items->fetch_assoc()): 
                                        $item_total = $item['current_price'] * $item['quantity'];
                                        $subtotal += $item_total;
                                    ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: var(--space-md);">
                                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                         class="item-image">
                                                    <span class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>$<?php echo number_format($item['current_price'], 2); ?></td>
                                            <td class="order-total">$<?php echo number_format($item_total, 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: var(--space-xl); color: var(--text-secondary);">
                                            No items found for this order.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <!-- Orders Table -->
                <div class="orders-table-card">
                    <div class="table-header">
                        <h3>All Orders</h3>
                        <div class="search-box">
                            <input type="text" class="search-input" placeholder="Search orders..." id="searchOrders">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($orders->num_rows > 0): ?>
                                    <?php while ($order = $orders->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="order-id">#<?php echo $order['id']; ?></span>
                                            </td>
                                            <td>
                                                <div class="customer-info">
                                                    <span class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                    <span class="customer-email"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo $order['item_count']; ?> items</td>
                                            <td>
                                                <span class="order-total">$<?php echo number_format($order['total'], 2); ?></span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $order['status'] ?? 'pending'; ?>">
                                                    <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="admin-orders.php?view=<?php echo $order['id']; ?>" class="action-btn view">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: var(--space-2xl); color: var(--text-secondary);">
                                            No orders found.
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
        document.getElementById('searchOrders')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.orders-table tbody tr');
            
            rows.forEach(row => {
                const orderId = row.querySelector('.order-id')?.textContent.toLowerCase() || '';
                const customerName = row.querySelector('.customer-name')?.textContent.toLowerCase() || '';
                const customerEmail = row.querySelector('.customer-email')?.textContent.toLowerCase() || '';
                
                if (orderId.includes(searchTerm) || customerName.includes(searchTerm) || customerEmail.includes(searchTerm)) {
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