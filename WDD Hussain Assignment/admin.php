<?php
session_start();

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

// Process logout request.
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

/* 
Step 1: Check if an admin account has been registered.
We assume you already have an "admin" table.
*/
$result_admin = $conn->query("SELECT COUNT(*) as adminCount FROM admin");
$row_admin = $result_admin->fetch_assoc();
$adminCount = intval($row_admin['adminCount']);

/* 
Step 2: If no admin account exists, require registration.
If the POST variable "register_admin" is set, process registration:
*/
if ($adminCount == 0) {
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["register_admin"])) {
        // Get registration info
        $username         = $conn->real_escape_string($_POST["username"]);
        $password         = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];

        if ($password !== $confirm_password) {
            $registration_error = "Passwords do not match.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO admin (username, password) VALUES ('$username', '$hashed_password')";
            if ($conn->query($sql)) {
                // Registration successful: log in and redirect.
                $_SESSION["admin_logged_in"] = true;
                $_SESSION["admin_username"] = $username;
                // Set flag to show welcome message
                $_SESSION["show_admin_login_message"] = true;
                header("Location: admin-dashboard.php");
                exit;
            } else {
                $registration_error = "Error: " . $conn->error;
            }
        }
    }
    // Display the registration form.
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Admin Registration - Premium Tool</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link rel="stylesheet" href="premium-styles.css">
      <style>
        body {
          background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 50%, #1e40af 100%);
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: var(--space-xl);
          font-family: var(--font-sans);
        }
        .admin-auth-container {
          background: var(--bg-primary);
          border-radius: var(--radius-2xl);
          padding: var(--space-3xl);
          box-shadow: var(--shadow-2xl);
          width: 100%;
          max-width: 450px;
          border: 1px solid var(--gray-200);
        }
        .admin-auth-header {
          text-align: center;
          margin-bottom: var(--space-2xl);
        }
        .admin-auth-header .logo-icon {
          width: 80px;
          height: 80px;
          background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
          border-radius: var(--radius-xl);
          display: flex;
          align-items: center;
          justify-content: center;
          margin: 0 auto var(--space-lg);
          font-size: 2rem;
          color: white;
        }
        .admin-auth-header h1 {
          color: var(--text-primary);
          font-size: var(--text-3xl);
          font-weight: var(--font-bold);
          margin-bottom: var(--space-sm);
        }
        .admin-auth-header p {
          color: var(--text-secondary);
          font-size: var(--text-base);
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
        .form-input {
          width: 100%;
          padding: var(--space-md);
          border: 2px solid var(--gray-200);
          border-radius: var(--radius-lg);
          font-size: var(--text-base);
          transition: all var(--transition-fast);
          background-color: var(--bg-primary);
          font-family: var(--font-sans);
        }
        .form-input:focus {
          outline: none;
          border-color: var(--primary-color);
          box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .error-message {
          background: rgba(239, 68, 68, 0.1);
          color: var(--danger-color);
          padding: var(--space-md);
          border-radius: var(--radius-lg);
          margin-bottom: var(--space-lg);
          border: 1px solid rgba(239, 68, 68, 0.2);
          font-size: var(--text-sm);
        }
        .btn-submit {
          width: 100%;
          padding: var(--space-md);
          background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
          color: white;
          border: none;
          border-radius: var(--radius-lg);
          font-size: var(--text-base);
          font-weight: var(--font-semibold);
          cursor: pointer;
          transition: all var(--transition-fast);
          font-family: var(--font-sans);
        }
        .btn-submit:hover {
          transform: translateY(-2px);
          box-shadow: var(--shadow-lg);
        }
      </style>
    </head>
    <body>
      <div class="admin-auth-container">
        <div class="admin-auth-header">
          <div class="logo-icon">
            <i class="fas fa-tools"></i>
          </div>
          <h1>Admin Registration</h1>
          <p>Create your admin account</p>
        </div>
        <form action="admin.php" method="POST">
          <?php if (isset($registration_error)) { echo "<div class='error-message'><i class='fas fa-exclamation-circle'></i> {$registration_error}</div>"; } ?>
          <div class="form-group">
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username" class="form-input" required autocomplete="username">
          </div>
          <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-input" required autocomplete="new-password">
          </div>
          <div class="form-group">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required autocomplete="new-password">
          </div>
          <button type="submit" name="register_admin" value="1" class="btn-submit">
            <i class="fas fa-user-plus"></i> Register
          </button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}


if (!isset($_SESSION["admin_logged_in"])) {
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login_admin"])) {
        $username = $conn->real_escape_string($_POST["username"]);
        $password = $_POST["password"];
        $result = $conn->query("SELECT * FROM admin WHERE username='$username' LIMIT 1");
        if ($result->num_rows > 0) {
            $adminRow = $result->fetch_assoc();
            if (password_verify($password, $adminRow['password'])) {
                $_SESSION["admin_logged_in"] = true;
                $_SESSION["admin_username"] = $username;
                // Set flag to show welcome message
                $_SESSION["show_admin_login_message"] = true;
                header("Location: admin-dashboard.php");
                exit;
            } else {
                $login_error = "Invalid credentials.";
            }
        } else {
            $login_error = "Invalid credentials.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Admin Login - Premium Tool</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link rel="stylesheet" href="premium-styles.css">
      <style>
        body {
          background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 50%, #1e40af 100%);
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: var(--space-xl);
          font-family: var(--font-sans);
        }
        .admin-auth-container {
          background: var(--bg-primary);
          border-radius: var(--radius-2xl);
          padding: var(--space-3xl);
          box-shadow: var(--shadow-2xl);
          width: 100%;
          max-width: 450px;
          border: 1px solid var(--gray-200);
        }
        .admin-auth-header {
          text-align: center;
          margin-bottom: var(--space-2xl);
        }
        .admin-auth-header .logo-icon {
          width: 80px;
          height: 80px;
          background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
          border-radius: var(--radius-xl);
          display: flex;
          align-items: center;
          justify-content: center;
          margin: 0 auto var(--space-lg);
          font-size: 2rem;
          color: white;
        }
        .admin-auth-header h1 {
          color: var(--text-primary);
          font-size: var(--text-3xl);
          font-weight: var(--font-bold);
          margin-bottom: var(--space-sm);
        }
        .admin-auth-header p {
          color: var(--text-secondary);
          font-size: var(--text-base);
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
        .form-input {
          width: 100%;
          padding: var(--space-md);
          border: 2px solid var(--gray-200);
          border-radius: var(--radius-lg);
          font-size: var(--text-base);
          transition: all var(--transition-fast);
          background-color: var(--bg-primary);
          font-family: var(--font-sans);
        }
        .form-input:focus {
          outline: none;
          border-color: var(--primary-color);
          box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .error-message {
          background: rgba(239, 68, 68, 0.1);
          color: var(--danger-color);
          padding: var(--space-md);
          border-radius: var(--radius-lg);
          margin-bottom: var(--space-lg);
          border: 1px solid rgba(239, 68, 68, 0.2);
          font-size: var(--text-sm);
        }
        .btn-submit {
          width: 100%;
          padding: var(--space-md);
          background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
          color: white;
          border: none;
          border-radius: var(--radius-lg);
          font-size: var(--text-base);
          font-weight: var(--font-semibold);
          cursor: pointer;
          transition: all var(--transition-fast);
          font-family: var(--font-sans);
        }
        .btn-submit:hover {
          transform: translateY(-2px);
          box-shadow: var(--shadow-lg);
        }
      </style>
    </head>
    <body>
      <div class="admin-auth-container">
        <div class="admin-auth-header">
          <div class="logo-icon">
            <i class="fas fa-tools"></i>
          </div>
          <h1>Admin Login</h1>
          <p>Sign in to your admin account</p>
        </div>
        <form action="admin.php" method="POST">
          <?php if (isset($login_error)) { echo "<div class='error-message'><i class='fas fa-exclamation-circle'></i> {$login_error}</div>"; } ?>
          <div class="form-group">
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username" class="form-input" required autocomplete="username">
          </div>
          <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password">
          </div>
          <button type="submit" name="login_admin" value="1" class="btn-submit">
            <i class="fas fa-sign-in-alt"></i> Login
          </button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}



// If admin is logged in, redirect to dashboard
if (isset($_SESSION["admin_logged_in"])) {
    header("Location: admin-dashboard.php");
    exit;
}

$conn->close();
?>
