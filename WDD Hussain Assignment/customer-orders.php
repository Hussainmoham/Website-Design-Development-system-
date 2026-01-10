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

// Check if customer is logged in
if (!isset($_SESSION["customer_logged_in"])) {
    header("Location: login.html");
    exit;
}

$customer_id = $_SESSION["customer_id"];

// Get customer orders with items
$orders = $conn->query("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.customer_id = $customer_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

// Get order details if viewing specific order
$order_details = null;
$order_items = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $order_id = intval($_GET['view']);
    
    // Verify order belongs to customer
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $order_id, $customer_id);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Premium Tool</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Premium Styles -->
    <link rel="stylesheet" href="premium-styles.css">
    
    <style>
        .customer-layout {
            min-height: 100vh;
            background: var(--bg-secondary);
            padding-top: 100px;
        }
        
        .customer-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 50%, #1e40af 100%);
            color: white;
            padding: var(--space-3xl) 0 var(--space-2xl);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .customer-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            animation: float 8s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(1deg); }
        }
        
        .customer-header h1 {
            color: white;
            margin-bottom: var(--space-md);
            font-size: var(--text-4xl);
            font-weight: var(--font-extrabold);
            position: relative;
            z-index: 1;
        }
        
        .customer-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: var(--text-lg);
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .orders-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-2xl);
        }
        
        .orders-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
            margin-bottom: var(--space-2xl);
        }
        
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-2xl);
            padding-bottom: var(--space-lg);
            border-bottom: 2px solid var(--gray-100);
        }
        
        .orders-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: var(--text-2xl);
        }
        
        .order-item {
            background: var(--gray-50);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            margin-bottom: var(--space-lg);
            border: 1px solid var(--gray-200);
            transition: all var(--transition-fast);
        }
        
        .order-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-md);
        }
        
        .order-id {
            font-weight: var(--font-bold);
            color: var(--text-primary);
            font-size: var(--text-lg);
            font-family: var(--font-mono);
        }
        
        .order-status {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-md);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            text-transform: uppercase;
        }
        
        .order-status.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--secondary-color);
        }
        
        .order-status.processing {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }
        
        .order-status.shipped {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }
        
        .order-status.delivered {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        
        .order-status.cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .info-label {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            font-weight: var(--font-medium);
        }
        
        .info-value {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
        }
        
        .order-total {
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
            color: var(--primary-color);
        }
        
        .order-actions {
            display: flex;
            gap: var(--space-md);
            justify-content: flex-end;
        }
        
        .order-details-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
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
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            object-fit: cover;
        }
        
        .item-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
        }
        
        .item-price {
            font-weight: var(--font-bold);
            color: var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-3xl);
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: var(--space-lg);
            color: var(--gray-300);
        }
        
        @media (max-width: 768px) {
            .customer-header h1 {
                font-size: var(--text-3xl);
            }
            
            .orders-container {
                padding: var(--space-lg);
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-md);
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.html" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-tools"></i>
                </div>
                Premium Tool
            </a>
            
            <ul class="nav-links" id="navLinks">
                <li><a href="index.html">Home</a></li>
                <li><a href="prp.html">Products</a></li>
                <li><a href="Features.html">Features</a></li>
                <li><a href="Contact.html">Contact</a></li>
                <li><a href="chatbot.html">Support</a></li>
                <li><a href="customer-orders.php" class="active">My Orders</a></li>
                <li><a href="logout.php" class="btn btn-secondary">Logout</a></li>
            </ul>
            
            <button class="mobile-menu" id="mobileMenu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="customer-layout">
        <!-- Header -->
        <section class="customer-header">
            <div class="container">
                <h1><?php echo $order_details ? 'Order Details' : 'My Orders'; ?></h1>
                <p><?php echo $order_details ? 'View your order details and track status' : 'Track your orders and view purchase history'; ?></p>
            </div>
        </section>

        <div class="orders-container">
            <?php if ($order_details): ?>
                <!-- Order Details View -->
                <div class="order-details-card">
                    <div class="orders-header">
                        <h2>Order #<?php echo $order_details['id']; ?></h2>
                        <a href="customer-orders.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                    </div>
                    
                    <div class="order-info">
                        <div class="info-item">
                            <span class="info-label">Order Date</span>
                            <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($order_details['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="order-status <?php echo $order_details['status'] ?? 'pending'; ?>">
                                <?php echo ucfirst($order_details['status'] ?? 'pending'); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Amount</span>
                            <span class="info-value order-total">$<?php echo number_format($order_details['total'], 2); ?></span>
                        </div>
                    </div>
                    
                    <h3 style="margin: var(--space-2xl) 0 var(--space-lg) 0; color: var(--text-primary);">Order Items</h3>
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
                                    <?php while ($item = $order_items->fetch_assoc()): ?>
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
                                            <td class="item-price">$<?php echo number_format($item['current_price'], 2); ?></td>
                                            <td class="item-price">$<?php echo number_format($item['current_price'] * $item['quantity'], 2); ?></td>
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
                <!-- Orders List -->
                <div class="orders-card">
                    <div class="orders-header">
                        <h2>Your Orders</h2>
                    </div>
                    
                    <?php if ($orders->num_rows > 0): ?>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <div class="order-item">
                                <div class="order-header">
                                    <span class="order-id">Order #<?php echo $order['id']; ?></span>
                                    <span class="order-status <?php echo $order['status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                                    </span>
                                </div>
                                
                                <div class="order-info">
                                    <div class="info-item">
                                        <span class="info-label">Order Date</span>
                                        <span class="info-value"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Items</span>
                                        <span class="info-value"><?php echo $order['item_count']; ?> items</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Total</span>
                                        <span class="info-value order-total">$<?php echo number_format($order['total'], 2); ?></span>
                                    </div>
                                </div>
                                
                                <div class="order-actions">
                                    <a href="customer-orders.php?view=<?php echo $order['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No Orders Yet</h3>
                            <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                            <a href="prp.html" class="btn btn-primary" style="margin-top: var(--space-lg);">
                                <i class="fas fa-shopping-cart"></i> Start Shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenu').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('active');
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>