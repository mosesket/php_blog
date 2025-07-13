<?php
$page_title = "Home";
require_once 'includes/header.php';

// Database connection
$database = new Database();
$db = $database->connect();

// Pagination
$posts_per_page = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $posts_per_page;

// Get total posts count
$count_query = "SELECT COUNT(*) FROM posts WHERE status = 'published'";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_posts = $count_stmt->fetchColumn();
$total_pages = ceil($total_posts / $posts_per_page);

// Get posts with user information and like counts
$query = "
    SELECT p.*, u.username, u.full_name,
           COUNT(DISTINCT l.id) as like_count,
           COUNT(DISTINCT c.id) as comment_count
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN likes l ON p.id = l.post_id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE p.status = 'published' 
    GROUP BY p.id
    ORDER BY p.created_at DESC 
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($query);
$stmt->bindParam(':limit', $posts_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get popular categories
$category_query = "
    SELECT category, COUNT(*) as count 
    FROM posts 
    WHERE status = 'published' AND category IS NOT NULL
    GROUP BY category 
    ORDER BY count DESC 
    LIMIT 5
";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent comments
$recent_comments_query = "
    SELECT c.content, c.created_at, u.username, p.title, p.id as post_id
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN posts p ON c.post_id = p.id
    WHERE p.status = 'published'
    ORDER BY c.created_at DESC
    LIMIT 5
";
$recent_comments_stmt = $db->prepare($recent_comments_query);
$recent_comments_stmt->execute();
$recent_comments = $recent_comments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="main-content">
        <main class="content">
            <?php if (empty($posts)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <h2>Welcome to Student's Community Engagement Blog!</h2>
                    <p>No posts yet. Be the first to share your thoughts!</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="create_post.php" class="btn btn-primary">Write Your First Post</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-primary">Join Our Community</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-card">
                        <div class="post-header">
                            <div class="post-meta">
                                By <strong><?php echo htmlspecialchars($post['username']); ?></strong>
                                • <?php echo timeAgo($post['created_at']); ?>
                                <?php if ($post['category']): ?>
                                    <span class="category-badge"><?php echo htmlspecialchars($post['category']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h2 class="post-title">
                            <a href="post.php?id=<?php echo $post['id']; ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h2>
                        
                        <div class="post-content">
                            <?php 
                            $content = strip_tags($post['content']);
                            echo htmlspecialchars(substr($content, 0, 200)) . (strlen($content) > 200 ? '...' : '');
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
                        
                        <div class="post-actions">
                            <button class="action-btn like-btn" data-post-id="<?php echo $post['id']; ?>">
                                ❤️ Like (<?php echo $post['like_count']; ?>)
                            </button>
                            <a href="post.php?id=<?php echo $post['id']; ?>#comments" class="action-btn">
                                💬 Comments (<?php echo $post['comment_count']; ?>)
                            </a>
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="action-btn">
                                📖 Read More
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="text-align: center; margin-top: 2rem;">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary">Previous</a>
                        <?php endif; ?>
                        
                        <span style="margin: 0 1rem;">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        
        <aside class="sidebar">
            <!-- Search Widget -->
            <div class="widget">
                <h3>Search Posts</h3>
                <div class="form-group">
                    <input type="text" id="search-input" class="form-control" placeholder="Search for posts...">
                </div>
                <div id="search-results"></div>
            </div>
            
            <!-- Categories Widget -->
            <?php if (!empty($categories)): ?>
                <div class="widget">
                    <h3>Popular Categories</h3>
                    <ul style="list-style: none;">
                        <?php foreach ($categories as $category): ?>
                            <li style="margin-bottom: 0.5rem;">
                                <a href="category.php?cat=<?php echo urlencode($category['category']); ?>" 
                                   style="text-decoration: none; color: #3498db;">
                                    <?php echo htmlspecialchars($category['category']); ?> 
                                    (<?php echo $category['count']; ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Recent Comments Widget -->
            <?php if (!empty($recent_comments)): ?>
                <div class="widget">
                    <h3>Recent Comments</h3>
                    <?php foreach ($recent_comments as $comment): ?>
                        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.3rem;">
                                <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                                on <a href="post.php?id=<?php echo $comment['post_id']; ?>" 
                                      style="color: #3498db; text-decoration: none;">
                                    <?php echo htmlspecialchars(substr($comment['title'], 0, 30)) . '...'; ?>
                                </a>
                            </div>
                            <div style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars(substr($comment['content'], 0, 60)) . '...'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Widget -->
            <div class="widget">
                <h3>Community Stats</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <div>📝 Total Posts: <strong><?php echo $total_posts; ?></strong></div>
                    <div>👥 Active Categories: <strong><?php echo count($categories); ?></strong></div>
                    <div>💬 Recent Comments: <strong><?php echo count($recent_comments); ?></strong></div>
                </div>
                
                <?php if (!isLoggedIn()): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                        <a href="register.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                            Join Our Community
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>