<?php
require_once 'includes/header.php';

$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$post_id) {
    redirect('index.php');
}

$database = new Database();
$db = $database->connect();

// Get post with author info and counts
$post_query = "
    SELECT p.*, u.username, u.full_name,
           COUNT(DISTINCT l.id) as like_count,
           COUNT(DISTINCT c.id) as comment_count
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN likes l ON p.id = l.post_id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE p.id = :post_id AND p.status = 'published'
    GROUP BY p.id
";

$post_stmt = $db->prepare($post_query);
$post_stmt->bindParam(':post_id', $post_id);
$post_stmt->execute();

if ($post_stmt->rowCount() == 0) {
    redirect('index.php');
}

$post = $post_stmt->fetch(PDO::FETCH_ASSOC);
$page_title = $post['title'];

// Update view count
$view_query = "UPDATE posts SET views = views + 1 WHERE id = :post_id";
$view_stmt = $db->prepare($view_query);
$view_stmt->bindParam(':post_id', $post_id);
$view_stmt->execute();

// Check if current user liked this post
$user_liked = false;
if (isLoggedIn()) {
    $user = getCurrentUser();
    $like_check_query = "SELECT id FROM likes WHERE post_id = :post_id AND user_id = :user_id";
    $like_check_stmt = $db->prepare($like_check_query);
    $like_check_stmt->bindParam(':post_id', $post_id);
    $like_check_stmt->bindParam(':user_id', $user['id']);
    $like_check_stmt->execute();
    $user_liked = $like_check_stmt->rowCount() > 0;
}

// Get comments with user info
$comments_query = "
    SELECT c.*, u.username, u.full_name
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = :post_id
    ORDER BY c.created_at ASC
";
$comments_stmt = $db->prepare($comments_query);
$comments_stmt->bindParam(':post_id', $post_id);
$comments_stmt->execute();
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related posts
$related_query = "
    SELECT p.*, u.username,
           COUNT(DISTINCT l.id) as like_count
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN likes l ON p.id = l.post_id
    WHERE p.id != :post_id 
    AND p.status = 'published'
    AND (p.category = :category OR p.user_id = :user_id)
    GROUP BY p.id
    ORDER BY p.created_at DESC 
    LIMIT 3
";
$related_stmt = $db->prepare($related_query);
$related_stmt->bindParam(':post_id', $post_id);
$related_stmt->bindParam(':category', $post['category']);
$related_stmt->bindParam(':user_id', $post['user_id']);
$related_stmt->execute();
$related_posts = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="main-content">
        <main class="content">
            <!-- Post Content -->
            <article class="post-card">
                <div class="post-header">
                    <div class="post-meta">
                        By <strong><?php echo htmlspecialchars($post['username']); ?></strong>
                        • <?php echo timeAgo($post['created_at']); ?>
                        • <?php echo $post['views'] + 1; ?> views
                        <?php if ($post['category']): ?>
                            <span class="category-badge"><?php echo htmlspecialchars($post['category']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h1 class="post-title" style="font-size: 2rem; margin: 1rem 0;">
                    <?php echo htmlspecialchars($post['title']); ?>
                </h1>
                
                <div class="post-content" style="font-size: 1.1rem; line-height: 1.8; margin: 2rem 0;">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
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
                    <button class="action-btn like-btn <?php echo $user_liked ? 'liked' : ''; ?>" 
                            data-post-id="<?php echo $post['id']; ?>">
                        ❤️ <?php echo $user_liked ? 'Liked' : 'Like'; ?> (<?php echo $post['like_count']; ?>)
                    </button>
                    <a href="#comments" class="action-btn">
                        💬 Comments (<?php echo $post['comment_count']; ?>)
                    </a>
                    <button class="action-btn" onclick="sharePost()">
                        📤 Share
                    </button>
                </div>
            </article>
            
            <!-- Comments Section -->
            <div class="comments-section" id="comments">
                <h3 style="margin-bottom: 1.5rem;">
                    💬 Comments (<?php echo count($comments); ?>)
                </h3>
                
                <?php if (isLoggedIn()): ?>
                    <form class="comment-form" style="margin-bottom: 2rem;">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <div class="form-group">
                            <textarea name="content" class="form-control auto-expand" 
                                      placeholder="Share your thoughts..." rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Post Comment</button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 2rem;">
                        <p>Please <a href="login.php" style="color: #3498db;">login</a> to post a comment.</p>
                    </div>
                <?php endif; ?>
                
                <div id="comments-<?php echo $post['id']; ?>">
                    <?php if (empty($comments)): ?>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <p>No comments yet. Be the first to share your thoughts!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <div class="comment-meta">
                                    <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                                    • <?php echo timeAgo($comment['created_at']); ?>
                                </div>
                                <div class="comment-content">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <aside class="sidebar">
            <!-- Author Info Widget -->
            <div class="widget">
                <h3>👤 About the Author</h3>
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: #3498db; border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold;">
                        <?php echo strtoupper(substr($post['username'], 0, 2)); ?>
                    </div>
                    <h4 style="margin: 0.5rem 0;"><?php echo htmlspecialchars($post['full_name']); ?></h4>
                    <p style="color: #666; margin: 0;">@<?php echo htmlspecialchars($post['username']); ?></p>
                </div>
            </div>
            
            <!-- Related Posts Widget -->
            <?php if (!empty($related_posts)): ?>
                <div class="widget">
                    <h3>📚 Related Posts</h3>
                    <?php foreach ($related_posts as $related): ?>
                        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
                            <h4 style="font-size: 0.95rem; margin: 0 0 0.3rem 0;">
                                <a href="post.php?id=<?php echo $related['id']; ?>" 
                                   style="color: #2c3e50; text-decoration: none;">
                                    <?php echo htmlspecialchars(substr($related['title'], 0, 60)) . (strlen($related['title']) > 60 ? '...' : ''); ?>
                                </a>
                            </h4>
                            <div style="font-size: 0.8rem; color: #666;">
                                by <?php echo htmlspecialchars($related['username']); ?>
                                • <?php echo $related['like_count']; ?> likes
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Share Widget -->
            <div class="widget">
                <h3>📤 Share This Post</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <button onclick="shareToWhatsApp()" class="btn btn-secondary" style="width: 100%;">
                        📱 WhatsApp
                    </button>
                    <button onclick="copyLink()" class="btn btn-secondary" style="width: 100%;">
                        🔗 Copy Link
                    </button>
                    <button onclick="shareToEmail()" class="btn btn-secondary" style="width: 100%;">
                        📧 Email
                    </button>
                </div>
            </div>
            
            <!-- Navigation Widget -->
            <div class="widget">
                <h3>🧭 Navigation</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <a href="index.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        🏠 Back to Home
                    </a>
                    <?php if ($post['category']): ?>
                        <a href="category.php?cat=<?php echo urlencode($post['category']); ?>" 
                           class="btn btn-secondary" style="width: 100%; text-align: center;">
                            📁 More in <?php echo htmlspecialchars($post['category']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
function sharePost() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($post['title']); ?>',
            text: "Check out this post from Student's Community Engagement Blog",
            url: window.location.href
        });
    } else {
        copyLink();
    }
}

function shareToWhatsApp() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('Check out this post: <?php echo addslashes($post['title']); ?>');
    window.open(`https://wa.me/?text=${text} ${url}`, '_blank');
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        showAlert('Link copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = window.location.href;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showAlert('Link copied to clipboard!', 'success');
    });
}

function shareToEmail() {
    const subject = encodeURIComponent("Student's Community Engagement Blog: <?php echo addslashes($post['title']); ?>");
    const body = encodeURIComponent(`Check out this interesting post from Student's Community Engagement Blog:\n\n${window.location.href}`);
    window.location.href = `mailto:?subject=${subject}&body=${body}`;
}
</script>

<?php require_once 'includes/footer.php'; ?>