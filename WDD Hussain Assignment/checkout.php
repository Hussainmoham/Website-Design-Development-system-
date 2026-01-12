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

$message = '';
$message_type = '';

// Handle order submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["place_order"])) {
    $customer_name = $conn->real_escape_string($_POST["customer_name"]);
    $customer_email = $conn->real_escape_string($_POST["customer_email"]);
    $customer_phone = $conn->real_escape_string($_POST["customer_phone"]);
    $customer_address = $conn->real_escape_string($_POST["customer_address"]);
    $cart_data = json_decode($_POST["cart_data"], true);
    
    if (!empty($cart_data)) {
        // Calculate total
        $total = 0;
        foreach ($cart_data as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        // Check if customer exists, if not create one
        $customer_id = null;
        
        // If user is logged in, try to find their customer record first
        if (isset($_SESSION["username"]) || isset($_SESSION["user_id"])) {
            $logged_in_username = isset($_SESSION["username"]) ? $_SESSION["username"] : '';
            $logged_in_user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : null;
            
            // Try to find customer by email (which might match username) or by name matching username
            $check_customer = $conn->prepare("SELECT id FROM customers WHERE email = ? OR name = ?");
            $check_customer->bind_param("ss", $customer_email, $logged_in_username);
            $check_customer->execute();
            $result = $check_customer->get_result();
            
            if ($result->num_rows > 0) {
                $customer = $result->fetch_assoc();
                $customer_id = $customer['id'];
            }
            $check_customer->close();
        }
        
        // If no customer found and user is logged in, or if user is not logged in
        if (!$customer_id) {
            // Check if customer exists by email
            $check_customer = $conn->prepare("SELECT id FROM customers WHERE email = ?");
            $check_customer->bind_param("s", $customer_email);
            $check_customer->execute();
            $result = $check_customer->get_result();
            
            if ($result->num_rows > 0) {
                $customer = $result->fetch_assoc();
                $customer_id = $customer['id'];
            } else {
                // Create new customer (only name, email, password columns exist in customers table)
                $password = password_hash('default123', PASSWORD_DEFAULT);
                $insert_customer = $conn->prepare("INSERT INTO customers (name, email, password) VALUES (?, ?, ?)");
                $insert_customer->bind_param("sss", $customer_name, $customer_email, $password);
                
                if ($insert_customer->execute()) {
                    $customer_id = $conn->insert_id;
                }
                $insert_customer->close();
            }
            $check_customer->close();
        }
        
        // Store customer_id in session for future use
        if ($customer_id) {
            $_SESSION["customer_id"] = $customer_id;
        }
        
        if ($customer_id) {
            // Create order
            $insert_order = $conn->prepare("INSERT INTO orders (customer_id, total) VALUES (?, ?)");
            $insert_order->bind_param("id", $customer_id, $total);
            
            if ($insert_order->execute()) {
                $order_id = $conn->insert_id;
                
                // Insert order items
                $insert_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
                
                foreach ($cart_data as $item) {
                    $insert_item->bind_param("iii", $order_id, $item['id'], $item['quantity']);
                    $insert_item->execute();
                }
                
                $insert_item->close();
                $message = "Order placed successfully! Order ID: #$order_id";
                $message_type = "success";
                
                // Clear cart
                unset($_SESSION['cart']);
                
            } else {
                $message = "Error placing order: " . $conn->error;
                $message_type = "error";
            }
            $insert_order->close();
        } else {
            $message = "Error creating customer account.";
            $message_type = "error";
        }
    } else {
        $message = "Your cart is empty!";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Premium Tool</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Premium Styles -->
    <link rel="stylesheet" href="premium-styles.css">
    
    <style>
        .checkout-layout {
            min-height: 100vh;
            background: var(--bg-secondary);
            padding-top: 100px;
        }
        
        .checkout-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 50%, #1e40af 100%);
            color: white;
            padding: var(--space-3xl) 0 var(--space-2xl);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .checkout-header::before {
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
        
        .checkout-header h1 {
            color: white;
            margin-bottom: var(--space-md);
            font-size: var(--text-4xl);
            font-weight: var(--font-extrabold);
            position: relative;
            z-index: 1;
        }
        
        .checkout-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: var(--text-lg);
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-2xl);
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-2xl);
        }
        
        .checkout-form-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
        }
        
        .order-summary-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
            height: fit-content;
            position: sticky;
            top: 120px;
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
        
        .cart-items {
            margin-bottom: var(--space-xl);
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md) 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            object-fit: cover;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-xs);
        }
        
        .item-details {
            font-size: var(--text-sm);
            color: var(--text-secondary);
        }
        
        .item-price {
            font-weight: var(--font-bold);
            color: var(--primary-color);
        }
        
        .order-total {
            border-top: 2px solid var(--gray-200);
            padding-top: var(--space-lg);
            margin-top: var(--space-lg);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-sm);
        }
        
        .total-row.final {
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
            color: var(--text-primary);
            border-top: 1px solid var(--gray-200);
            padding-top: var(--space-md);
            margin-top: var(--space-md);
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
        
        .empty-cart {
            text-align: center;
            padding: var(--space-3xl);
            color: var(--text-secondary);
        }
        
        .empty-cart i {
            font-size: 4rem;
            margin-bottom: var(--space-lg);
            color: var(--gray-300);
        }
        
        @media (max-width: 1024px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            
            .order-summary-card {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .checkout-header h1 {
                font-size: var(--text-3xl);
            }
            
            .checkout-container {
                padding: var(--space-lg);
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
                <li><a href="login.html" class="btn btn-primary">Login</a></li>
            </ul>
            
            <button class="mobile-menu" id="mobileMenu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="checkout-layout">
        <!-- Header -->
        <section class="checkout-header">
            <div class="container">
                <h1>Checkout</h1>
                <p>Complete your order and get your premium tools delivered</p>
            </div>
        </section>

        <div class="checkout-container">
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                    <?php if ($message_type === 'success'): ?>
                        <div style="margin-top: var(--space-md);">
                            <a href="prp.html" class="btn btn-primary">Continue Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="checkout-grid">
                <!-- Checkout Form -->
                <div class="checkout-form-card">
                    <h2 style="margin-bottom: var(--space-xl); color: var(--text-primary);">Billing Information</h2>
                    
                    <form method="POST" id="checkoutForm">
                        <input type="hidden" name="cart_data" id="cartData">
                        
                        <div class="form-group">
                            <label for="customer_name" class="form-label">Full Name *</label>
                            <input type="text" id="customer_name" name="customer_name" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_email" class="form-label">Email Address *</label>
                            <input type="email" id="customer_email" name="customer_email" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_phone" class="form-label">Phone Number *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_address" class="form-label">Shipping Address *</label>
                            <textarea id="customer_address" name="customer_address" class="form-textarea" 
                                      placeholder="Enter your complete shipping address..." required></textarea>
                        </div>
                        
                        <button type="submit" name="place_order" class="btn btn-primary" style="width: 100%; font-size: var(--text-lg); padding: var(--space-lg);" id="placeOrderBtn">
                            <i class="fas fa-credit-card"></i>
                            Place Order
                        </button>
                    </form>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary-card">
                    <h3 style="margin-bottom: var(--space-lg); color: var(--text-primary);">Order Summary</h3>
                    
                    <div class="cart-items" id="cartItems">
                        <!-- Cart items will be loaded here -->
                    </div>
                    
                    <div class="order-total">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="total-row">
                            <span>Shipping:</span>
                            <span>Free</span>
                        </div>
                        <div class="total-row">
                            <span>Tax:</span>
                            <span id="tax">$0.00</span>
                        </div>
                        <div class="total-row final">
                            <span>Total:</span>
                            <span id="total">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load cart from localStorage
        function loadCart() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const cartItems = document.getElementById('cartItems');
            const subtotalEl = document.getElementById('subtotal');
            const taxEl = document.getElementById('tax');
            const totalEl = document.getElementById('total');
            const cartDataInput = document.getElementById('cartData');
            const placeOrderBtn = document.getElementById('placeOrderBtn');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Add some products to continue with checkout</p>
                        <a href="prp.html" class="btn btn-primary" style="margin-top: var(--space-lg);">
                            <i class="fas fa-shopping-bag"></i> Continue Shopping
                        </a>
                    </div>
                `;
                placeOrderBtn.disabled = true;
                placeOrderBtn.style.opacity = '0.5';
                return;
            }
            
            let subtotal = 0;
            
            cartItems.innerHTML = cart.map(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                
                return `
                    <div class="cart-item">
                        <img src="${item.image}" alt="${item.name}" class="item-image">
                        <div class="item-info">
                            <div class="item-name">${item.name}</div>
                            <div class="item-details">Qty: ${item.quantity} × $${item.price.toFixed(2)}</div>
                        </div>
                        <div class="item-price">$${itemTotal.toFixed(2)}</div>
                    </div>
                `;
            }).join('');
            
            const tax = subtotal * 0.08; // 8% tax
            const total = subtotal + tax;
            
            subtotalEl.textContent = `$${subtotal.toFixed(2)}`;
            taxEl.textContent = `$${tax.toFixed(2)}`;
            totalEl.textContent = `$${total.toFixed(2)}`;
            
            // Set cart data for form submission
            cartDataInput.value = JSON.stringify(cart);
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
        });
        
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
        
        // Clear cart after successful order
        <?php if ($message_type === 'success'): ?>
            localStorage.removeItem('cart');
        <?php endif; ?>
    </script>
</body>
</html>

<?php $conn->close(); ?>