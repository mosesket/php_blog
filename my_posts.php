<?php
$page_title = "My Posts";
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Handle post actions (delete, publish, etc.)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if ($post_id > 0) {
        // Verify post belongs to current user
        $verify_query = "SELECT id, status, title FROM posts WHERE id = :post_id AND user_id = :user_id";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->bindParam(':post_id', $post_id);
        $verify_stmt->bindParam(':user_id', $current_user['id']);
        $verify_stmt->execute();
        
        if ($verify_stmt->rowCount() > 0) {
            $post_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            switch ($action) {
                case 'delete':
                    $delete_query = "DELETE FROM posts WHERE id = :post_id AND user_id = :user_id";
                    $delete_stmt = $db->prepare($delete_query);
                    $delete_stmt->bindParam(':post_id', $post_id);
                    $delete_stmt->bindParam(':user_id', $current_user['id']);
                    
                    if ($delete_stmt->execute()) {
                        $message = 'Post deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete post.';
                        $message_type = 'error';
                    }
                    break;
                    
                case 'publish':
                    if ($post_data['status'] === 'draft') {
                        $publish_query = "UPDATE posts SET status = 'published', updated_at = NOW() WHERE id = :post_id AND user_id = :user_id";
                        $publish_stmt = $db->prepare($publish_query);
                        $publish_stmt->bindParam(':post_id', $post_id);
                        $publish_stmt->bindParam(':user_id', $current_user['id']);
                        
                        if ($publish_stmt->execute()) {
                            $message = 'Post published successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to publish post.';
                            $message_type = 'error';
                        }
                    }
                    break;
                    
                case 'unpublish':
                    if ($post_data['status'] === 'published') {
                        $unpublish_query = "UPDATE posts SET status = 'draft', updated_at = NOW() WHERE id = :post_id AND user_id = :user_id";
                        $unpublish_stmt = $db->prepare($unpublish_query);
                        $unpublish_stmt->bindParam(':post_id', $post_id);
                        $unpublish_stmt->bindParam(':user_id', $current_user['id']);
                        
                        if ($unpublish_stmt->execute()) {
                            $message = 'Post moved to drafts successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to move post to drafts.';
                            $message_type = 'error';
                        }
                    }
                    break;
            }
        } else {
            $message = 'Post not found or you don\'t have permission to modify it.';
            $message_type = 'error';
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';

// Pagination
$posts_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $posts_per_page;

// Build query conditions
$where_conditions = ["p.user_id = :user_id"];
$params = [':user_id' => $current_user['id']];

if ($status_filter !== 'all') {
    $where_conditions[] = "p.status = :status";
    $params[':status'] = $status_filter;
}

if ($category_filter !== 'all') {
    $where_conditions[] = "p.category = :category";
    $params[':category'] = $category_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total posts count for pagination
$count_query = "SELECT COUNT(*) FROM posts p WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_posts = $count_stmt->fetchColumn();
$total_pages = ceil($total_posts / $posts_per_page);

// Get posts with statistics
$posts_query = "
    SELECT p.*,
           COUNT(DISTINCT l.id) as like_count,
           COUNT(DISTINCT c.id) as comment_count
    FROM posts p
    LEFT JOIN likes l ON p.id = l.post_id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.updated_at DESC
    LIMIT :limit OFFSET :offset
";

$posts_stmt = $db->prepare($posts_query);
foreach ($params as $key => $value) {
    $posts_stmt->bindValue($key, $value);
}
$posts_stmt->bindValue(':limit', $posts_per_page, PDO::PARAM_INT);
$posts_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$posts_stmt->execute();
$posts = $posts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's categories for filter
$categories_query = "
    SELECT DISTINCT category 
    FROM posts 
    WHERE user_id = :user_id AND category IS NOT NULL AND category != ''
    ORDER BY category
";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->bindParam(':user_id', $current_user['id']);
$categories_stmt->execute();
$user_categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get user statistics
$stats_query = "
    SELECT 
        COUNT(CASE WHEN status = 'published' THEN 1 END) as published_count,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
        SUM(views) as total_views,
        COUNT(DISTINCT category) as categories_used
    FROM posts 
    WHERE user_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $current_user['id']);
$stats_stmt->execute();
$user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="main-content">
        <main class="content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 style="color: #2c3e50; margin: 0;">📄 My Posts</h2>
                <a href="create_post.php" class="btn btn-primary">
                    ✍️ Write New Post
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="post-card" style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1rem; color: #2c3e50;">🔍 Filter Posts</h3>
                
                <form method="GET" action="" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin: 0;">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Posts</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Drafts</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach ($user_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" 
                                        <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Apply Filters</button>
                </form>
            </div>
            
            <!-- Posts List -->
            <?php if (empty($posts)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📝</div>
                    <h3>No posts found</h3>
                    <p style="color: #666; margin-bottom: 2rem;">
                        <?php if ($status_filter !== 'all' || $category_filter !== 'all'): ?>
                            Try adjusting your filters or create your first post.
                        <?php else: ?>
                            You haven't created any posts yet. Start sharing your thoughts with the community!
                        <?php endif; ?>
                    </p>
                    <a href="create_post.php" class="btn btn-primary">Create Your First Post</a>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-card">
                        <div class="post-header">
                            <div class="post-meta">
                                <span class="category-badge" style="background: <?php echo $post['status'] === 'published' ? '#27ae60' : '#f39c12'; ?>;">
                                    <?php echo $post['status'] === 'published' ? '✅ Published' : '📝 Draft'; ?>
                                </span>
                                <?php if ($post['category']): ?>
                                    <span class="category-badge"><?php echo htmlspecialchars($post['category']); ?></span>
                                <?php endif; ?>
                                <span style="margin-left: 1rem; color: #666;">
                                    Created: <?php echo timeAgo($post['created_at']); ?>
                                    <?php if ($post['updated_at'] !== $post['created_at']): ?>
                                        • Updated: <?php echo timeAgo($post['updated_at']); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <h3 class="post-title">
                            <?php if ($post['status'] === 'published'): ?>
                                <a href="post.php?id=<?php echo $post['id']; ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            <?php else: ?>
                                <a href="edit_post.php?id=<?php echo $post['id']; ?>" style="color: #f39c12;">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            <?php endif; ?>
                        </h3>
                        
                        <div class="post-content">
                            <?php 
                            $content = strip_tags($post['content']);
                            echo htmlspecialchars(substr($content, 0, 150)) . (strlen($content) > 150 ? '...' : '');
                            ?>
                        </div>
                        
                        <?php if ($post['tags']): ?>
                            <div class="tags">
                                <?php 
                                $tags = explode(',', $post['tags']);
                                foreach ($tags as $tag): 
                                    $tag = trim($tag);
                                    if ($tag):
                                ?>
                                    <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-actions" style="justify-content: space-between;">
                            <div style="display: flex; gap: 1rem;">
                                <span class="action-btn">👁️ <?php echo $post['views']; ?> views</span>
                                <span class="action-btn">❤️ <?php echo $post['like_count']; ?> likes</span>
                                <span class="action-btn">💬 <?php echo $post['comment_count']; ?> comments</span>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <?php if ($post['status'] === 'published'): ?>
                                    <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">
                                        👁️ View
                                    </a>
                                <?php endif; ?>
                                
                                <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">
                                    ✏️ Edit
                                </a>
                                
                                <?php if ($post['status'] === 'draft'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Publish this post?');">
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">
                                            🚀 Publish
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Move this post to drafts?');">
                                        <input type="hidden" name="action" value="unpublish">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">
                                            📝 Draft
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="text-align: center; margin-top: 2rem;">
                        <?php
                        $current_params = $_GET;
                        unset($current_params['page']);
                        $base_url = '?' . http_build_query($current_params);
                        $base_url = $base_url === '?' ? '?' : $base_url . '&';
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="btn btn-secondary">Previous</a>
                        <?php endif; ?>
                        
                        <span style="margin: 0 1rem;">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        
        <aside class="sidebar">
            <!-- User Stats Widget -->
            <div class="widget">
                <h3>📊 Your Statistics</h3>
                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>✅ Published:</span>
                        <strong style="color: #27ae60;"><?php echo $user_stats['published_count'] ?: 0; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>📝 Drafts:</span>
                        <strong style="color: #f39c12;"><?php echo $user_stats['draft_count'] ?: 0; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>👁️ Total Views:</span>
                        <strong style="color: #3498db;"><?php echo number_format($user_stats['total_views'] ?: 0); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>🏷️ Categories:</span>
                        <strong style="color: #9b59b6;"><?php echo $user_stats['categories_used'] ?: 0; ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Widget -->
            <div class="widget">
                <h3>⚡ Quick Actions</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <a href="create_post.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                        ✍️ Write New Post
                    </a>
                    <a href="?status=draft" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        📝 View Drafts
                    </a>
                    <a href="?status=published" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        ✅ View Published
                    </a>
                    <a href="profile.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        👤 Edit Profile
                    </a>
                </div>
            </div>
            
            <!-- Performance Tips Widget -->
            <div class="widget">
                <h3>💡 Writing Tips</h3>
                <div style="font-size: 0.9rem; line-height: 1.6;">
                    <p><strong>📈 Increase Engagement:</strong></p>
                    <ul style="margin: 0.5rem 0; padding-left: 1rem;">
                        <li>Use descriptive titles</li>
                        <li>Add relevant tags</li>
                        <li>Include examples and code</li>
                        <li>Respond to comments</li>
                    </ul>
                    
                    <p><strong>🎯 Best Practices:</strong></p>
                    <ul style="margin: 0.5rem 0; padding-left: 1rem;">
                        <li>Proofread before publishing</li>
                        <li>Use appropriate categories</li>
                        <li>Share your experiences</li>
                        <li>Be helpful to others</li>
                    </ul>
                </div>
            </div>
            
            <!-- Categories Widget -->
            <?php if (!empty($user_categories)): ?>
                <div class="widget">
                    <h3>📂 Your Categories</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($user_categories as $category): ?>
                            <a href="?category=<?php echo urlencode($category); ?>" 
                               class="tag" style="text-decoration: none; color: white;">
                                <?php echo htmlspecialchars($category); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>