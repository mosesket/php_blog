<?php
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Student's Community Engagement Blog</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico"> -->
</head>
<body data-logged-in="<?php echo isLoggedIn() ? 'true' : 'false'; ?>">
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><a href="index.php" style="color: white; text-decoration: none;">Student's Community </a></h1>
                    <div class="subtitle">Student Community Platform</div>
                </div>
                
                <nav class="nav">
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="create_post.php">Write Post</a></li>
                            <li><a href="my_posts.php">My Posts</a></li>
                        <?php endif; ?>
                        <li><a href="about.php">About</a></li>
                    </ul>
                </nav>
                
                <div class="user-menu">
                    <?php if (isLoggedIn()): ?>
                        <?php $user = getCurrentUser(); ?>
                        <div class="user-info">
                            Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        </div>
                        <a href="profile.php" class="btn btn-secondary">Profile</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-secondary">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>