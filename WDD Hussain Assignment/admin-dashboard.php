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

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['products'] = $result->fetch_assoc()['count'];

// Total customers
$result = $conn->query("SELECT COUNT(*) as count FROM customers");
$stats['customers'] = $result->fetch_assoc()['count'];

// Total orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders");
$stats['orders'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query("SELECT SUM(total) as revenue FROM orders");
$stats['revenue'] = $result->fetch_assoc()['revenue'] ?? 0;

// Recent orders
$recent_orders = $conn->query("
    SELECT o.*, c.name as customer_name 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");

// Low stock products (assuming we add stock field later)
$low_stock = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Premium Tool</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Premium Styles -->
    <link rel="stylesheet" href="premium-styles.css">
    
    <style>
        /* Admin Dashboard Styles */
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
        
        .admin-user {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }
        
        .admin-user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: var(--font-bold);
            font-size: var(--text-lg);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-xl);
            margin-bottom: var(--space-2xl);
        }
        
        .stat-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
            position: relative;
            overflow: hidden;
            transition: all var(--transition-normal);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-2xl);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
        }
        
        .stat-title {
            color: var(--text-secondary);
            font-size: var(--text-sm);
            font-weight: var(--font-semibold);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-xl);
            color: white;
        }
        
        .stat-icon.products { background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); }
        .stat-icon.customers { background: linear-gradient(135deg, var(--secondary-color), var(--secondary-light)); }
        .stat-icon.orders { background: linear-gradient(135deg, var(--accent-color), #059669); }
        .stat-icon.revenue { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
        
        .stat-value {
            font-size: var(--text-4xl);
            font-weight: var(--font-extrabold);
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }
        
        .stat-change {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
        }
        
        .stat-change.positive { color: var(--accent-color); }
        .stat-change.negative { color: var(--danger-color); }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-2xl);
        }
        
        .dashboard-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
        }
        
        .dashboard-card h3 {
            margin: 0 0 var(--space-lg) 0;
            color: var(--text-primary);
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
        }
        
        .recent-orders-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .recent-order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-md) 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .recent-order-item:last-child {
            border-bottom: none;
        }
        
        .order-info h4 {
            margin: 0 0 var(--space-xs) 0;
            color: var(--text-primary);
            font-size: var(--text-base);
            font-weight: var(--font-semibold);
        }
        
        .order-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: var(--text-sm);
        }
        
        .order-amount {
            font-size: var(--text-lg);
            font-weight: var(--font-bold);
            color: var(--primary-color);
        }
        
        .quick-actions {
            display: grid;
            gap: var(--space-md);
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-lg);
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--text-primary);
            font-weight: var(--font-semibold);
            transition: all var(--transition-fast);
        }
        
        .quick-action-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Login Message Notification */
        .login-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--accent-color), #059669);
            color: white;
            padding: var(--space-lg) var(--space-xl);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-2xl);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: var(--space-md);
            min-width: 300px;
            max-width: 500px;
            animation: slideInRight 0.3s ease-out;
            border-left: 4px solid white;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .login-message i {
            font-size: var(--text-2xl);
        }
        
        .login-message-content {
            flex: 1;
        }
        
        .login-message-title {
            font-weight: var(--font-bold);
            font-size: var(--text-lg);
            margin-bottom: var(--space-xs);
        }
        
        .login-message-text {
            font-size: var(--text-sm);
            opacity: 0.95;
        }
        
        .login-message-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-fast);
        }
        
        .login-message-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            .login-message {
                right: 10px;
                left: 10px;
                min-width: auto;
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
                        <a href="admin-dashboard.php" class="admin-nav-link active">
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
            <?php if (isset($_SESSION["show_admin_login_message"]) && $_SESSION["show_admin_login_message"]): ?>
                <div class="login-message" id="adminLoginMessage">
                    <i class="fas fa-check-circle"></i>
                    <div class="login-message-content">
                        <div class="login-message-title">Welcome Back, <?php echo htmlspecialchars($_SESSION["admin_username"]); ?>!</div>
                        <div class="login-message-text">You have successfully logged in to the admin dashboard.</div>
                    </div>
                    <button class="login-message-close" onclick="document.getElementById('adminLoginMessage').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php 
                unset($_SESSION["show_admin_login_message"]); // Clear the flag
                ?>
            <?php endif; ?>
            
            <header class="admin-header">
                <h1>Dashboard</h1>
                <div class="admin-user">
                    <div class="admin-user-avatar">
                        <?php echo strtoupper(substr($_SESSION["admin_username"], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: var(--font-semibold); color: var(--text-primary);">
                            <?php echo htmlspecialchars($_SESSION["admin_username"]); ?>
                        </div>
                        <div style="font-size: var(--text-sm); color: var(--text-secondary);">
                            Administrator
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Products</div>
                        <div class="stat-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['products']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Active products
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Customers</div>
                        <div class="stat-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['customers']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Registered users
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Orders</div>
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['orders']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> All time orders
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Revenue</div>
                        <div class="stat-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">$<?php echo number_format($stats['revenue'], 2); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Total earnings
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <h3>Recent Orders</h3>
                    <ul class="recent-orders-list">
                        <?php if ($recent_orders->num_rows > 0): ?>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <li class="recent-order-item">
                                    <div class="order-info">
                                        <h4>Order #<?php echo $order['id']; ?></h4>
                                        <p><?php echo htmlspecialchars($order['customer_name']); ?> • <?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                                    </div>
                                    <div class="order-amount">$<?php echo number_format($order['total'], 2); ?></div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="recent-order-item">
                                <div class="order-info">
                                    <p style="color: var(--text-secondary); text-align: center;">No orders yet</p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="admin-products.php?action=add" class="quick-action-btn">
                            <i class="fas fa-plus"></i>
                            Add New Product
                        </a>
                        <a href="admin-customers.php?action=add" class="quick-action-btn">
                            <i class="fas fa-user-plus"></i>
                            Add Customer
                        </a>
                        <a href="admin-orders.php" class="quick-action-btn">
                            <i class="fas fa-eye"></i>
                            View All Orders
                        </a>
                        <a href="admin-suppliers.php?action=add" class="quick-action-btn">
                            <i class="fas fa-truck"></i>
                            Add Supplier
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Auto-dismiss login message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const loginMessage = document.getElementById('adminLoginMessage');
            if (loginMessage) {
                setTimeout(function() {
                    loginMessage.style.animation = 'slideInRight 0.3s ease-out reverse';
                    setTimeout(function() {
                        loginMessage.remove();
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>