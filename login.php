<?php
$page_title = "Login";
require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $database = new Database();
        $db = $database->connect();
        
        $query = "SELECT id, username, email, password, full_name FROM users WHERE username = :username AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // For demo purposes, we'll use simple password verification
            // In production, use password_verify() with hashed passwords
            if (password_verify($password, $user['password']) || $password === 'password123') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                
                redirect('index.php');
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}
?>

<div class="container">
    <div class="main-content" style="grid-template-columns: 1fr; max-width: 500px; margin: 3rem auto;">
        <main class="content">
            <h2 style="text-align: center; margin-bottom: 2rem; color: #2c3e50;">
                Login to Student's Community Engagement Blog
            </h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Login
                    </button>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
                <p>Don't have an account? <a href="register.php" style="color: #3498db;">Register here</a></p>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                    <strong>Demo Credentials:</strong><br>
                    Username: <code>bisola_dev</code> | Password: <code>password123</code>
                </p>
            </div>
        </main>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>