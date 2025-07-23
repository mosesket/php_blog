<?php
require_once 'includes/header.php';

$category = isset($_GET['cat']) ? sanitize($_GET['cat']) : '';

if (empty($category)) {
    redirect('index.php');
}

$database = new Database();
$db = $database->connect();

// Pagination
$posts_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $posts_per_page;

// Get total posts count for this category
$count_query = "SELECT COUNT(*) FROM posts WHERE category = :category AND status = 'published'";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':category', $category);
$count_stmt->execute();
$total_posts = $count_stmt->fetchColumn();
$total_pages = ceil($total_posts / $posts_per_page);

// Get posts for this category with user information, like counts, and media
$query = "
    SELECT p.*, u.username, u.full_name,
           COUNT(DISTINCT l.id) as like_count,
           COUNT(DISTINCT c.id) as comment_count
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN likes l ON p.id = l.post_id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE p.category = :category AND p.status = 'published' 
    GROUP BY p.id
    ORDER BY p.created_at DESC 
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($query);
$stmt->bindParam(':category', $category);
$stmt->bindParam(':limit', $posts_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get media for posts if they have media
$posts_with_media = [];
foreach ($posts as $post) {
    if ($post['has_media']) {
        $media_query = "
            SELECT m.*, pm.display_order
            FROM media_uploads m
            JOIN post_media pm ON m.id = pm.media_id
            WHERE pm.post_id = :post_id
            ORDER BY pm.display_order ASC
            LIMIT 3
        ";
        $media_stmt = $db->prepare($media_query);
        $media_stmt->bindParam(':post_id', $post['id']);
        $media_stmt->execute();
        $posts_with_media[$post['id']] = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get all categories for sidebar
$categories_query = "
    SELECT category, COUNT(*) as count 
    FROM posts 
    WHERE status = 'published' AND category IS NOT NULL AND category != ''
    GROUP BY category 
    ORDER BY category ASC
";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$all_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category statistics
$stats_query = "
    SELECT 
        COUNT(*) as post_count,
        COUNT(DISTINCT user_id) as contributor_count,
        SUM(views) as total_views
    FROM posts 
    WHERE category = :category AND status = 'published'
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':category', $category);
$stats_stmt->execute();
$category_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get top contributors in this category
$contributors_query = "
    SELECT u.username, u.full_name, COUNT(p.id) as post_count
    FROM users u
    JOIN posts p ON u.id = p.user_id
    WHERE p.category = :category AND p.status = 'published'
    GROUP BY u.id
    ORDER BY post_count DESC
    LIMIT 5
";
$contributors_stmt = $db->prepare($contributors_query);
$contributors_stmt->bindParam(':category', $category);
$contributors_stmt->execute();
$top_contributors = $contributors_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $category . " - Category";
?>

<div class="container">
    <div class="main-content">
        <main class="content">
            <!-- Category Header -->
            <div class="post-card" style="text-align: center; background: linear-gradient(135deg, #f8f9fa, #e9ecef); margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; color: #2c3e50; margin-bottom: 0.5rem;">
                    📁 <?php echo htmlspecialchars($category); ?>
                </h1>
                <p style="font-size: 1.1rem; color: #666; margin-bottom: 1rem;">
                    Explore posts in the <?php echo htmlspecialchars($category); ?> category
                </p>
                <div style="display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap;">
                    <div style="text-align: center;">
                        <strong style="font-size: 1.5rem; color: #3498db;"><?php echo $category_stats['post_count']; ?></strong>
                        <br><small>Posts</small>
                    </div>
                    <div style="text-align: center;">
                        <strong style="font-size: 1.5rem; color: #27ae60;"><?php echo $category_stats['contributor_count']; ?></strong>
                        <br><small>Contributors</small>
                    </div>
                    <div style="text-align: center;">
                        <strong style="font-size: 1.5rem; color: #f39c12;"><?php echo number_format($category_stats['total_views']); ?></strong>
                        <br><small>Total Views</small>
                    </div>
                </div>
            </div>

            <!-- Posts List -->
            <?php if (empty($posts)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📝</div>
                    <h3>No posts found in this category</h3>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Be the first to contribute to the <?php echo htmlspecialchars($category); ?> category!
                    </p>
                    <?php if (isLoggedIn()): ?>
                        <a href="create_post.php" class="btn btn-primary">Create First Post</a>
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
                                • <?php echo $post['views']; ?> views
                            </div>
                        </div>
                        
                        <h2 class="post-title">
                            <a href="post.php?id=<?php echo $post['id']; ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h2>
                        
                        <!-- Display media preview if post has media -->
                        <?php if ($post['has_media'] && isset($posts_with_media[$post['id']])): ?>
                            <div class="post-media-preview">
                                <div class="media-preview-grid">
                                    <?php 
                                    $media_items = $posts_with_media[$post['id']];
                                    $media_count = count($media_items);
                                    $display_count = min(3, $media_count);
                                    ?>
                                    
                                    <?php for ($i = 0; $i < $display_count; $i++): ?>
                                        <?php $media = $media_items[$i]; ?>
                                        <div class="media-preview-item <?php echo $display_count === 1 ? 'single' : ($display_count === 2 ? 'double' : 'triple'); ?>">
                                            <?php if ($media['file_type'] === 'image'): ?>
                                                <a href="post.php?id=<?php echo $post['id']; ?>">
                                                    <img src="<?php echo htmlspecialchars($media['file_path']); ?>" 
                                                         alt="<?php echo htmlspecialchars($media['original_name']); ?>">
                                                </a>
                                            <?php else: ?>
                                                <a href="post.php?id=<?php echo $post['id']; ?>">
                                                    <video preload="metadata" style="pointer-events: none;">
                                                        <source src="<?php echo htmlspecialchars($media['file_path']); ?>" 
                                                                type="<?php echo htmlspecialchars($media['mime_type']); ?>">
                                                    </video>
                                                    <div class="video-overlay">
                                                        <div class="play-button">▶</div>
                                                    </div>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($i === 2 && $media_count > 3): ?>
                                                <div class="media-overlay">
                                                    <span>+<?php echo $media_count - 3; ?> more</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
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
                            <?php if ($post['has_media']): ?>
                                <span class="action-btn">
                                    📎 <?php echo isset($posts_with_media[$post['id']]) ? count($posts_with_media[$post['id']]) : 0; ?> files
                                </span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="text-align: center; margin-top: 2rem;">
                        <?php
                        $base_url = "?cat=" . urlencode($category);
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>" class="btn btn-secondary">Previous</a>
                        <?php endif; ?>
                        
                        <span style="margin: 0 1rem;">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        
        <aside class="sidebar">
            <!-- Category Navigation Widget -->
            <div class="widget">
                <h3>📂 All Categories</h3>
                <ul>
                    <?php foreach ($all_categories as $cat): ?>
                        <li style="margin-bottom: 0.5rem;">
                            <a href="category.php?cat=<?php echo urlencode($cat['category']); ?>" 
                               style="display: flex; justify-content: space-between; align-items: center; padding: 0.3rem 0; <?php echo $cat['category'] === $category ? 'font-weight: bold; color: #2c3e50 !important;' : ''; ?>">
                                <span><?php echo htmlspecialchars($cat['category']); ?></span>
                                <small style="background: #3498db; color: white; padding: 0.1rem 0.5rem; border-radius: 10px;">
                                    <?php echo $cat['count']; ?>
                                </small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                    <a href="index.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        🏠 Back to All Posts
                    </a>
                </div>
            </div>
            
            <!-- Top Contributors Widget -->
            <?php if (!empty($top_contributors)): ?>
                <div class="widget">
                    <h3>👥 Top Contributors</h3>
                    <?php foreach ($top_contributors as $contributor): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; padding: 0.5rem; background: #f8f9fa; border-radius: 5px;">
                            <div>
                                <strong><?php echo htmlspecialchars($contributor['username']); ?></strong>
                                <br><small style="color: #666;"><?php echo htmlspecialchars($contributor['full_name']); ?></small>
                            </div>
                            <div style="text-align: center;">
                                <strong style="color: #3498db;"><?php echo $contributor['post_count']; ?></strong>
                                <br><small>posts</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Category Stats Widget -->
            <div class="widget">
                <h3>📊 Category Statistics</h3>
                <div style="display: grid; gap: 0.8rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>📝 Total Posts:</span>
                        <strong style="color: #3498db;"><?php echo $category_stats['post_count']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>👥 Contributors:</span>
                        <strong style="color: #27ae60;"><?php echo $category_stats['contributor_count']; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>👁️ Total Views:</span>
                        <strong style="color: #f39c12;"><?php echo number_format($category_stats['total_views']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>📈 Avg Views/Post:</span>
                        <strong style="color: #9b59b6;">
                            <?php echo $category_stats['post_count'] > 0 ? number_format($category_stats['total_views'] / $category_stats['post_count']) : 0; ?>
                        </strong>
                    </div>
                </div>
                
                <?php if (isLoggedIn()): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                        <a href="create_post.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                            ✍️ Write in <?php echo htmlspecialchars($category); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions Widget -->
            <div class="widget">
                <h3>⚡ Quick Actions</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <a href="index.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        🏠 All Posts
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="create_post.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            ✍️ Create Post
                        </a>
                        <a href="my_posts.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            📄 My Posts
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            🔐 Login
                        </a>
                        <a href="register.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                            📝 Join Community
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>