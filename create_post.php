<?php
$page_title = "Create New Post";
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize($_POST['title']);
    $content = trim($_POST['content']);
    $category = sanitize($_POST['category']);
    $tags = sanitize($_POST['tags']);
    $status = sanitize($_POST['status']);
    
    if (empty($title) || empty($content)) {
        $error = 'Please fill in title and content';
    } else {
        $database = new Database();
        $db = $database->connect();
        $user = getCurrentUser();
        
        $insert_query = "
            INSERT INTO posts (user_id, title, content, category, tags, status) 
            VALUES (:user_id, :title, :content, :category, :tags, :status)
        ";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':user_id', $user['id']);
        $insert_stmt->bindParam(':title', $title);
        $insert_stmt->bindParam(':content', $content);
        $insert_stmt->bindParam(':category', $category);
        $insert_stmt->bindParam(':tags', $tags);
        $insert_stmt->bindParam(':status', $status);
        
        if ($insert_stmt->execute()) {
            $post_id = $db->lastInsertId();
            if ($status === 'published') {
                $success = 'Post published successfully!';
                echo "<script>setTimeout(() => window.location.href = 'post.php?id=$post_id', 2000);</script>";
            } else {
                $success = 'Post saved as draft!';
                echo "<script>setTimeout(() => window.location.href = 'my_posts.php', 2000);</script>";
            }
        } else {
            $error = 'Failed to create post. Please try again.';
        }
    }
}
?>

<div class="container">
    <div class="main-content" style="grid-template-columns: 1fr 300px;">
        <main class="content">
            <h2 style="color: #2c3e50; margin-bottom: 2rem;">
                ✍️ Create New Post
            </h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form id="post-form" method="POST" action="">
                <div class="form-group">
                    <label for="title">Post Title *</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                           placeholder="Enter an engaging title for your post" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">Select Category</option>
                            <option value="Academic" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Academic') ? 'selected' : ''; ?>>Academic</option>
                            <option value="Technology" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Technology') ? 'selected' : ''; ?>>Technology</option>
                            <option value="Programming" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Programming') ? 'selected' : ''; ?>>Programming</option>
                            <option value="Student Life" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Student Life') ? 'selected' : ''; ?>>Student Life</option>
                            <option value="Projects" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Projects') ? 'selected' : ''; ?>>Projects</option>
                            <option value="Events" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Events') ? 'selected' : ''; ?>>Events</option>
                            <option value="Tips & Tricks" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Tips & Tricks') ? 'selected' : ''; ?>>Tips & Tricks</option>
                            <option value="General" <?php echo (isset($_POST['category']) && $_POST['category'] == 'General') ? 'selected' : ''; ?>>General</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" class="form-control" 
                               value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>"
                               placeholder="web development, php, mysql">
                        <small style="color: #666; font-size: 0.85rem;">Separate tags with commas</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="content">Content *</label>
                    <textarea id="content" name="content" class="form-control auto-expand" 
                              rows="15" data-max-length="5000" required 
                              placeholder="Share your thoughts, experiences, or knowledge with the TPI community..."><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: space-between; align-items: center;">
                    <div class="form-group" style="margin: 0;">
                        <label for="status">Publish Status</label>
                        <select id="status" name="status" class="form-control" style="width: auto;">
                            <option value="published">Publish Now</option>
                            <option value="draft">Save as Draft</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">
                            📝 Create Post
                        </button>
                        <a href="index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </main>
        
        <aside class="sidebar">
            <!-- Writing Tips Widget -->
            <div class="widget">
                <h3>✨ Writing Tips</h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 0.5rem;">💡 <strong>Catchy Title:</strong> Make it clear and engaging</li>
                    <li style="margin-bottom: 0.5rem;">📝 <strong>Clear Content:</strong> Write in simple, understandable language</li>
                    <li style="margin-bottom: 0.5rem;">🏷️ <strong>Relevant Tags:</strong> Help others find your content</li>
                    <li style="margin-bottom: 0.5rem;">📸 <strong>Categories:</strong> Choose the most appropriate category</li>
                    <li>✅ <strong>Proofread:</strong> Check for spelling and grammar</li>
                </ul>
            </div>
            
            <!-- Post Guidelines Widget -->
            <div class="widget">
                <h3>📋 Community Guidelines</h3>
                <div style="font-size: 0.9rem; line-height: 1.6;">
                    <p><strong>✅ Encouraged:</strong></p>
                    <ul style="margin: 0.5rem 0;">
                        <li>Academic discussions</li>
                        <li>Project showcases</li>
                        <li>Learning experiences</li>
                        <li>Helpful resources</li>
                        <li>Constructive feedback</li>
                    </ul>
                    
                    <p><strong>❌ Not Allowed:</strong></p>
                    <ul style="margin: 0.5rem 0;">
                        <li>Spam or promotional content</li>
                        <li>Offensive language</li>
                        <li>Plagiarized content</li>
                        <li>Off-topic discussions</li>
                    </ul>
                </div>
            </div>
            
            <!-- Formatting Help Widget -->
            <div class="widget">
                <h3>🎨 Formatting Help</h3>
                <div style="font-size: 0.85rem;">
                    <p><strong>Line Breaks:</strong> Press Enter twice for new paragraph</p>
                    <p><strong>Lists:</strong> Use - or * for bullet points</p>
                    <p><strong>Code:</strong> Wrap code in backticks `code`</p>
                    <p><strong>Links:</strong> Include full URLs (https://...)</p>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
// Auto-save draft functionality
let autoSaveTimer;
const autoSaveInterval = 30000; // 30 seconds

function autoSaveDraft() {
    const title = document.getElementById('title').value.trim();
    const content = document.getElementById('content').value.trim();
    
    if (title || content) {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        formData.append('category', document.getElementById('category').value);
        formData.append('tags', document.getElementById('tags').value);
        formData.append('status', 'draft');
        formData.append('auto_save', '1');
        
        fetch('actions/auto_save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Draft auto-saved');
                // Show subtle indicator
                showAutoSaveIndicator();
            }
        })
        .catch(error => console.error('Auto-save error:', error));
    }
}

function showAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #2ecc71; color: white; padding: 8px 12px; border-radius: 4px; font-size: 0.85rem; z-index: 1000;';
    indicator.textContent = '✓ Draft saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}

// Start auto-save timer when user starts typing
document.getElementById('title').addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(autoSaveDraft, autoSaveInterval);
});

document.getElementById('content').addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(autoSaveDraft, autoSaveInterval);
});

// Warn user about unsaved changes
window.addEventListener('beforeunload', function(e) {
    const title = document.getElementById('title').value.trim();
    const content = document.getElementById('content').value.trim();
    
    if (title || content) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Remove warning when form is submitted
document.getElementById('post-form').addEventListener('submit', function() {
    window.removeEventListener('beforeunload', arguments.callee);
});
</script>

<?php require_once 'includes/footer.php'; ?>