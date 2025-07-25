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

// Get posts with user information, like counts, and media
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
                    <ul>
                        <?php foreach ($categories as $category): ?>
                            <li style="margin-bottom: 0.5rem;">
                                <a href="category.php?cat=<?php echo urlencode($category['category']); ?>">
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
                                on <a href="post.php?id=<?php echo $comment['post_id']; ?>">
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

<style>
/* Media Preview Styles for Index */
.post-media-preview {
    margin: 1rem 0;
}

.media-preview-grid {
    display: grid;
    gap: 0.5rem;
    border-radius: 8px;
    overflow: hidden;
}

.media-preview-item {
    position: relative;
    overflow: hidden;
    background: #f5f5f5;
}

.media-preview-item.single {
    height: 300px;
}

.media-preview-item.double {
    height: 200px;
}

.media-preview-item.triple {
    height: 150px;
}

.media-preview-grid .media-preview-item:first-child.single {
    grid-column: 1 / -1;
}

.media-preview-grid .media-preview-item.double:first-child {
    grid-column: 1 / 2;
}

.media-preview-grid .media-preview-item.double:last-child {
    grid-column: 2 / 3;
}

.media-preview-grid .media-preview-item.triple {
    grid-column: span 1;
}

.media-preview-grid {
    grid-template-columns: repeat(3, 1fr);
}

.media-preview-item img,
.media-preview-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.media-preview-item a {
    display: block;
    position: relative;
    height: 100%;
}

.media-preview-item:hover img,
.media-preview-item:hover video {
    transform: scale(1.05);
}

.video-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.3);
    transition: background 0.3s;
}

.video-overlay:hover {
    background: rgba(0, 0, 0, 0.5);
}

.play-button {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #333;
    padding-left: 3px;
}

.media-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .media-preview-item.single,
    .media-preview-item.double,
    .media-preview-item.triple {
        height: 200px;
    }
    
    .media-preview-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .media-preview-item.triple:last-child {
        grid-column: 1 / -1;
        height: 150px;
    }
}

@media (max-width: 480px) {
    .media-preview-grid {
        grid-template-columns: 1fr;
    }
    
    .media-preview-item {
        height: 200px !important;
        grid-column: 1 / -1 !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>