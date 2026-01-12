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

// Check if user is logged in
if (!isset($_SESSION["username"]) && !isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}

// Get customer ID from session (set during checkout)
$customer_id = isset($_SESSION["customer_id"]) ? $_SESSION["customer_id"] : null;
$username = isset($_SESSION["username"]) ? $_SESSION["username"] : '';

// If customer_id is not in session, try to find it based on logged-in user
if (!$customer_id && $username) {
    // Try to find customer by username (matching name or email)
    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ? OR name = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        $customer_id = $customer['id'];
        $_SESSION["customer_id"] = $customer_id; // Store for future use
    }
    $stmt->close();
}

// If still no customer_id, redirect to login
if (!$customer_id) {
    header("Location: login.html");
    exit;
}

// Get all orders for this customer
$stmt = $conn->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           SUM(oi.quantity * p.price) as calculated_total
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.customer_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
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
        .my-orders-layout {
            min-height: 100vh;
            background: var(--bg-secondary);
            padding-top: 100px;
        }
        
        .my-orders-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 50%, #1e40af 100%);
            color: white;
            padding: var(--space-3xl) 0 var(--space-2xl);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .my-orders-header::before {
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
        
        .my-orders-header h1 {
            color: white;
            margin-bottom: var(--space-md);
            font-size: var(--text-4xl);
            font-weight: var(--font-extrabold);
            position: relative;
            z-index: 1;
        }
        
        .my-orders-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: var(--text-lg);
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .my-orders-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-2xl);
        }
        
        .order-card {
            background: var(--bg-primary);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            margin-bottom: var(--space-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            transition: all var(--transition-fast);
        }
        
        .order-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 2px solid var(--gray-100);
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .order-number {
            font-size: var(--text-lg);
            font-weight: var(--font-bold);
            color: var(--text-primary);
        }
        
        .order-date {
            font-size: var(--text-sm);
            color: var(--text-secondary);
        }
        
        .order-status {
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-full);
            font-size: var(--text-sm);
            font-weight: var(--font-semibold);
            text-transform: uppercase;
        }
        
        .order-status.pending {
            background: rgba(251, 191, 36, 0.1);
            color: #f59e0b;
        }
        
        .order-status.completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }
        
        .order-status.processing {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }
        
        .order-items {
            margin-bottom: var(--space-lg);
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md) 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            border: 1px solid var(--gray-200);
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-xs);
        }
        
        .item-quantity {
            font-size: var(--text-sm);
            color: var(--text-secondary);
        }
        
        .item-price {
            font-weight: var(--font-bold);
            color: var(--primary-color);
            font-size: var(--text-lg);
        }
        
        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--space-md);
            border-top: 2px solid var(--gray-200);
            margin-top: var(--space-md);
        }
        
        .total-label {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
        }
        
        .total-amount {
            font-size: var(--text-2xl);
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
        
        .empty-state h3 {
            font-size: var(--text-xl);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-md);
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
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
                <li><a href="my-orders.php" class="active">My Orders</a></li>
                <li id="loginStatus">
                    <a href="login.html" class="btn btn-primary">Login</a>
                </li>
            </ul>
            
            <button class="mobile-menu" id="mobileMenu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="my-orders-layout">
        <!-- Header -->
        <section class="my-orders-header">
            <div class="container">
                <h1>My Orders</h1>
                <p>View all your past orders and track their status</p>
            </div>
        </section>

        <div class="my-orders-container">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    // Get order items for this order
                    $stmt = $conn->prepare("
                        SELECT oi.*, p.product_name, p.image, p.price as current_price
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = ?
                    ");
                    $stmt->bind_param("i", $order['id']);
                    $stmt->execute();
                    $items_result = $stmt->get_result();
                    $items = [];
                    while ($item = $items_result->fetch_assoc()) {
                        $items[] = $item;
                    }
                    $stmt->close();
                    
                    $status = isset($order['status']) ? $order['status'] : 'pending';
                    $status_class = $status;
                    ?>
                    
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-number">Order #<?php echo $order['id']; ?></div>
                                <div class="order-date">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?>
                                </div>
                            </div>
                            <div class="order-status <?php echo $status_class; ?>">
                                <?php echo ucfirst($status); ?>
                            </div>
                        </div>
                        
                        <div class="order-items">
                            <?php foreach ($items as $item): ?>
                                <div class="order-item">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                         class="item-image"
                                         onerror="this.src='https://via.placeholder.com/80x80?text=No+Image'">
                                    <div class="item-details">
                                        <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                    </div>
                                    <div class="item-price">
                                        $<?php echo number_format($item['current_price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-total">
                            <div class="total-label">Total Amount:</div>
                            <div class="total-amount">$<?php echo number_format($order['total'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders yet. Start shopping to see your order history here!</p>
                    <a href="prp.html" class="btn btn-primary" style="margin-top: var(--space-lg);">
                        <i class="fas fa-shopping-cart"></i> Browse Products
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Check login status and update navbar
        function checkLoginStatus() {
            fetch('check_login.php')
                .then(response => response.json())
                .then(data => {
                    const loginStatus = document.getElementById('loginStatus');
                    if (data.loggedIn && data.username) {
                        loginStatus.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="color: var(--text-primary); font-weight: 500;">
                                    <i class="fas fa-user"></i> ${data.username}
                                </span>
                                <a href="logout.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.875rem;">
                                    Logout
                                </a>
                            </div>
                        `;
                    } else {
                        loginStatus.innerHTML = '<a href="login.html" class="btn btn-primary">Login</a>';
                    }
                })
                .catch(error => {
                    console.log('Could not check login status:', error);
                });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus();
            
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
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>

