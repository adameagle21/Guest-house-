<?php
session_start();

// ========== SQLite Database (matches your index.php) ==========
$db_file = 'database.sqlite';
$pdo = new PDO("sqlite:$db_file");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Check if admin_users table exists, if not create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )");
    
    // Check if admin user exists, if not create default one
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO admin_users (username, password) VALUES ('admin', '$hashed')");
    }
    
    // Verify login
    $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $login_error = "Invalid username or password.";
        }
    } else {
        $login_error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Sea View Batalaale</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #2F5D5A 0%, #1a3a38 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-container {
      max-width: 420px;
      width: 90%;
      margin: 20px;
    }
    .login-card {
      background: white;
      border-radius: 32px;
      padding: 45px 40px;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      text-align: center;
    }
    .logo-icon {
      font-size: 3.5rem;
      color: #C5A059;
      margin-bottom: 15px;
    }
    h1 {
      font-size: 1.8rem;
      color: #2F5D5A;
      margin-bottom: 10px;
    }
    .subtitle {
      color: #666;
      margin-bottom: 30px;
      font-size: 0.9rem;
    }
    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #2C2A29;
    }
    .form-group input {
      width: 100%;
      padding: 14px 18px;
      border: 1px solid #e0d6c8;
      border-radius: 40px;
      font-size: 1rem;
      transition: all 0.3s;
    }
    .form-group input:focus {
      outline: none;
      border-color: #C5A059;
      box-shadow: 0 0 0 3px rgba(197,160,89,0.1);
    }
    .btn-login {
      width: 100%;
      padding: 14px;
      background: #C5A059;
      color: #2C2A29;
      border: none;
      border-radius: 40px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }
    .btn-login:hover {
      background: #D9B46B;
      transform: translateY(-2px);
    }
    .error-message {
      background: #ffe0e0;
      color: #a13e3e;
      padding: 12px;
      border-radius: 40px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }
    .back-link {
      margin-top: 20px;
      display: inline-block;
      color: #C5A059;
      text-decoration: none;
      font-size: 0.9rem;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    .default-info {
      margin-top: 25px;
      padding-top: 20px;
      border-top: 1px solid #e8e0d4;
      font-size: 0.75rem;
      color: #999;
    }
    .default-info strong {
      color: #2F5D5A;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="logo-icon">
        <i class="fas fa-user-shield"></i>
      </div>
      <h1>Admin Login</h1>
      <p class="subtitle">Enter your credentials to access dashboard</p>
      
      <?php if (!empty($login_error)): ?>
        <div class="error-message">
          <i class="fas fa-exclamation-triangle"></i> <?php echo $login_error; ?>
        </div>
      <?php endif; ?>
      
      <form method="POST">
        <div class="form-group">
          <label><i class="fas fa-user"></i> Username</label>
          <input type="text" name="username" placeholder="Enter username" required autofocus>
        </div>
        <div class="form-group">
          <label><i class="fas fa-lock"></i> Password</label>
          <input type="password" name="password" placeholder="Enter password" required>
        </div>
        <button type="submit" name="admin_login" class="btn-login">
          <i class="fas fa-sign-in-alt"></i> Login
        </button>
      </form>
      
      <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Website
      </a>
      
      <
    </div>
  </div>
</body>
</html>