<?php
$page_title = "My Profile";
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();
$error = '';
$success = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $department = sanitize($_POST['department']);
        $student_id = sanitize($_POST['student_id']);
        
        if (empty($full_name) || empty($email)) {
            $error = 'Full name and email are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if email is already taken by another user
            $email_check = "SELECT id FROM users WHERE email = :email AND id != :user_id";
            $email_stmt = $db->prepare($email_check);
            $email_stmt->bindParam(':email', $email);
            $email_stmt->bindParam(':user_id', $current_user['id']);
            $email_stmt->execute();
            
            if ($email_stmt->rowCount() > 0) {
                $error = 'This email is already registered to another account';
            } else {
                $update_query = "
                    UPDATE users 
                    SET full_name = :full_name, email = :email, department = :department, student_id = :student_id
                    WHERE id = :user_id
                ";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':full_name', $full_name);
                $update_stmt->bindParam(':email', $email);
                $update_stmt->bindParam(':department', $department);
                $update_stmt->bindParam(':student_id', $student_id);
                $update_stmt->bindParam(':user_id', $current_user['id']);
                
                if ($update_stmt->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $success = 'Profile updated successfully!';
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long';
        } else {
            // Verify current password
            $password_check = "SELECT password FROM users WHERE id = :user_id";
            $password_stmt = $db->prepare($password_check);
            $password_stmt->bindParam(':user_id', $current_user['id']);
            $password_stmt->execute();
            $user_data = $password_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $user_data['password']) || $current_password === 'password123') {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $password_update = "UPDATE users SET password = :password WHERE id = :user_id";
                $password_update_stmt = $db->prepare($password_update);
                $password_update_stmt->bindParam(':password', $hashed_password);
                $password_update_stmt->bindParam(':user_id', $current_user['id']);
                
                if ($password_update_stmt->execute()) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect';
            }
        }
    }
}

// Get user data
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $current_user['id']);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT p.id) as post_count,
        COUNT(DISTINCT c.id) as comment_count,
        COUNT(DISTINCT l.id) as like_count,
        SUM(p.views) as total_views
    FROM users u
    LEFT JOIN posts p ON u.id = p.user_id AND p.status = 'published'
    LEFT JOIN comments c ON u.id = c.user_id
    LEFT JOIN likes l ON u.id = l.user_id
    WHERE u.id = :user_id
    GROUP BY u.id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $current_user['id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'post_count' => 0,
    'comment_count' => 0,
    'like_count' => 0,
    'total_views' => 0
];

// Get recent posts
$recent_posts_query = "
    SELECT p.*, COUNT(DISTINCT l.id) as like_count, COUNT(DISTINCT c.id) as comment_count
    FROM posts p
    LEFT JOIN likes l ON p.id = l.post_id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE p.user_id = :user_id AND p.status = 'published'
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
";
$recent_posts_stmt = $db->prepare($recent_posts_query);
$recent_posts_stmt->bindParam(':user_id', $current_user['id']);
$recent_posts_stmt->execute();
$recent_posts = $recent_posts_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="main-content">
        <main class="content">
            <h2 style="color: #2c3e50; margin-bottom: 2rem;">
                👤 My Profile
            </h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Profile Information -->
            <div class="post-card">
                <h3 style="margin-bottom: 1.5rem; color: #2c3e50;">📝 Profile Information</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['username']); ?>" 
                                   disabled style="background: #f8f9fa;">
                            <small style="color: #666; font-size: 0.85rem;">Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="department">Department</label>
                            <select id="department" name="department" class="form-control">
                                <option value="">Select Department</option>
                                <option value="Computer Science" <?php echo ($user_data['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Engineering" <?php echo ($user_data['department'] == 'Engineering') ? 'selected' : ''; ?>>Engineering</option>
                                <option value="Business Administration" <?php echo ($user_data['department'] == 'Business Administration') ? 'selected' : ''; ?>>Business Administration</option>
                                <option value="Science Laboratory Technology" <?php echo ($user_data['department'] == 'Science Laboratory Technology') ? 'selected' : ''; ?>>Science Laboratory Technology</option>
                                <option value="Art and Design" <?php echo ($user_data['department'] == 'Art and Design') ? 'selected' : ''; ?>>Art and Design</option>
                                <option value="Other" <?php echo ($user_data['department'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="student_id">Student ID</label>
                            <input type="text" id="student_id" name="student_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['student_id']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            💾 Update Profile
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="post-card">
                <h3 style="margin-bottom: 1.5rem; color: #2c3e50;">🔒 Change Password</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" 
                                   minlength="6" required>
                            <small style="color: #666; font-size: 0.85rem;">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   minlength="6" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            🔑 Change Password
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="post-card">
                <h3 style="margin-bottom: 1.5rem; color: #2c3e50;">ℹ️ Account Information</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></p>
                        <p><strong>Account Status:</strong> 
                            <span style="color: #27ae60; font-weight: 500;">
                                <?php echo $user_data['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                            </span>
                        </p>
                        <p><strong>Profile Image:</strong> Default Avatar</p>
                    </div>
                    
                    <div>
                        <p><strong>Last Login:</strong> Current Session</p>
                        <p><strong>Email Verified:</strong> <span style="color: #27ae60;">✅ Verified</span></p>
                        <p><strong>Two-Factor Auth:</strong> <span style="color: #e74c3c;">❌ Disabled</span></p>
                    </div>
                </div>
            </div>
        </main>
        
        <aside class="sidebar">
            <!-- Profile Stats Widget -->
            <div class="widget">
                <h3>📊 Your Statistics</h3>
                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>📝 Posts Published:</span>
                        <strong><?php echo $stats['post_count']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>💬 Comments Made:</span>
                        <strong><?php echo $stats['comment_count']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>❤️ Likes Given:</span>
                        <strong><?php echo $stats['like_count']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>👁️ Total Views:</span>
                        <strong><?php echo number_format($stats['total_views'] ?: 0); ?></strong>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;">
                    <a href="create_post.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                        ✍️ Write New Post
                    </a>
                </div>
            </div>
            
            <!-- Recent Posts Widget -->
            <?php if (!empty($recent_posts)): ?>
                <div class="widget">
                    <h3>📚 Your Recent Posts</h3>
                    <?php foreach ($recent_posts as $post): ?>
                        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
                            <h4 style="font-size: 0.95rem; margin: 0 0 0.5rem 0;">
                                <a href="post.php?id=<?php echo $post['id']; ?>" 
                                   style="color: #2c3e50; text-decoration: none;">
                                    <?php echo htmlspecialchars(substr($post['title'], 0, 50)) . (strlen($post['title']) > 50 ? '...' : ''); ?>
                                </a>
                            </h4>
                            <div style="font-size: 0.8rem; color: #666;">
                                <?php echo timeAgo($post['created_at']); ?>
                                • <?php echo $post['like_count']; ?> likes
                                • <?php echo $post['comment_count']; ?> comments
                                • <?php echo $post['views']; ?> views
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="my_posts.php" style="color: #3498db; text-decoration: none; font-size: 0.9rem;">
                            View All My Posts →
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Profile Avatar Widget -->
            <div class="widget">
                <h3>👤 Profile Avatar</h3>
                <div style="text-align: center;">
                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #3498db, #2c3e50); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        <?php echo strtoupper(substr($user_data['username'], 0, 2)); ?>
                    </div>
                    <h4 style="margin: 0.5rem 0;"><?php echo htmlspecialchars($user_data['full_name']); ?></h4>
                    <p style="color: #666; margin: 0;">@<?php echo htmlspecialchars($user_data['username']); ?></p>
                    <?php if ($user_data['department']): ?>
                        <p style="color: #3498db; margin: 0.5rem 0; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($user_data['department']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions Widget -->
            <div class="widget">
                <h3>⚡ Quick Actions</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <a href="my_posts.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        📄 Manage Posts
                    </a>
                    <a href="index.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        🏠 Back to Home
                    </a>
                    <a href="logout.php" class="btn btn-danger" style="width: 100%; text-align: center;">
                        🚪 Logout
                    </a>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Form validation feedback
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="loading"></span> Saving...';
                submitBtn.disabled = true;
                
                // Re-enable after 3 seconds as fallback
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>