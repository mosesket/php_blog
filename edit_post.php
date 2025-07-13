<?php
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$post_id) {
    redirect('my_posts.php');
}

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Get post data and verify ownership
$post_query = "SELECT * FROM posts WHERE id = :post_id AND user_id = :user_id";
$post_stmt = $db->prepare($post_query);
$post_stmt->bindParam(':post_id', $post_id);
$post_stmt->bindParam(':user_id', $current_user['id']);
$post_stmt->execute();

if ($post_stmt->rowCount() == 0) {
    redirect('my_posts.php');
}

$post = $post_stmt->fetch(PDO::FETCH_ASSOC);
$page_title = "Edit: " . $post['title'];

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
        $update_query = "
            UPDATE posts 
            SET title = :title, content = :content, category = :category, tags = :tags, status = :status, updated_at = NOW()
            WHERE id = :post_id AND user_id = :user_id
        ";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':title', $title);
        $update_stmt->bindParam(':content', $content);
        $update_stmt->bindParam(':category', $category);
        $update_stmt->bindParam(':tags', $tags);
        $update_stmt->bindParam(':status', $status);
        $update_stmt->bindParam(':post_id', $post_id);
        $update_stmt->bindParam(':user_id', $current_user['id']);
        
        if ($update_stmt->execute()) {
            $success = 'Post updated successfully!';
            // Update local post data
            $post['title'] = $title;
            $post['content'] = $content;
            $post['category'] = $category;
            $post['tags'] = $tags;
            $post['status'] = $status;
            
            if ($status === 'published') {
                echo "<script>setTimeout(() => window.location.href = 'post.php?id=$post_id', 2000);</script>";
            }
        } else {
            $error = 'Failed to update post. Please try again.';
        }
    }
}
?>

<div class="container">
    <div class="main-content" style="grid-template-columns: 1fr 300px;">
        <main class="content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 style="color: #2c3e50; margin: 0;">
                    ✏️ Edit Post
                </h2>
                <div>
                    <a href="my_posts.php" class="btn btn-secondary">← Back to My Posts</a>
                    <?php if ($post['status'] === 'published'): ?>
                        <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-secondary">👁️ View Live</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="title">Post Title *</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($post['title']); ?>"
                           placeholder="Enter an engaging title for your post" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">Select Category</option>
                            <option value="Academic" <?php echo ($post['category'] == 'Academic') ? 'selected' : ''; ?>>Academic</option>
                            <option value="Technology" <?php echo ($post['category'] == 'Technology') ? 'selected' : ''; ?>>Technology</option>
                            <option value="Programming" <?php echo ($post['category'] == 'Programming') ? 'selected' : ''; ?>>Programming</option>
                            <option value="Student Life" <?php echo ($post['category'] == 'Student Life') ? 'selected' : ''; ?>>Student Life</option>
                            <option value="Projects" <?php echo ($post['category'] == 'Projects') ? 'selected' : ''; ?>>Projects</option>
                            <option value="Events" <?php echo ($post['category'] == 'Events') ? 'selected' : ''; ?>>Events</option>
                            <option value="Tips & Tricks" <?php echo ($post['category'] == 'Tips & Tricks') ? 'selected' : ''; ?>>Tips & Tricks</option>
                            <option value="General" <?php echo ($post['category'] == 'General') ? 'selected' : ''; ?>>General</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" class="form-control" 
                               value="<?php echo htmlspecialchars($post['tags']); ?>"
                               placeholder="web development, php, mysql">
                        <small style="color: #666; font-size: 0.85rem;">Separate tags with commas</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="content">Content *</label>
                    <textarea id="content" name="content" class="form-control auto-expand" 
                              rows="15" data-max-length="5000" required 
                              placeholder="Share your thoughts, experiences, or knowledge with the TPI community..."><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: space-between; align-items: center;">
                    <div class="form-group" style="margin: 0;">
                        <label for="status">Post Status</label>
                        <select id="status" name="status" class="form-control" style="width: auto;">
                            <option value="published" <?php echo ($post['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo ($post['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">
                            💾 Update Post
                        </button>
                        <a href="my_posts.php" class="btn btn-secondary" style="margin-left: 0.5rem;">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </main>
        
        <aside class="sidebar">
            <!-- Post Information Widget -->
            <div class="widget">
                <h3>📊 Post Information</h3>
                <div style="display: grid; gap: 0.8rem;">
                    <div>
                        <strong>Status:</strong>
                        <span class="category-badge" style="background: <?php echo $post['status'] === 'published' ? '#27ae60' : '#f39c12'; ?>; margin-left: 0.5rem;">
                            <?php echo $post['status'] === 'published' ? '✅ Published' : '📝 Draft'; ?>
                        </span>
                    </div>
                    <div>
                        <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?>
                    </div>
                    <div>
                        <strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($post['updated_at'])); ?>
                    </div>
                    <div>
                        <strong>Views:</strong> <?php echo number_format($post['views']); ?>
                    </div>
                </div>
                
                <?php if ($post['status'] === 'published'): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                        <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            👁️ View Live Post
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
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
            
            <!-- Revision History Widget -->
            <div class="widget">
                <h3>📝 Revision History</h3>
                <div style="font-size: 0.9rem;">
                    <p><strong>Original Version:</strong><br>
                    <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></p>
                    
                    <?php if ($post['updated_at'] !== $post['created_at']): ?>
                        <p><strong>Last Edited:</strong><br>
                        <?php echo date('M j, Y g:i A', strtotime($post['updated_at'])); ?></p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; font-size: 0.85rem; color: #666;">
                        💡 <strong>Tip:</strong> Changes are automatically saved. You can switch between draft and published status anytime.
                    </div>
                </div>
            </div>
            
            <!-- Content Guidelines Widget -->
            <div class="widget">
                <h3>📋 Content Guidelines</h3>
                <div style="font-size: 0.9rem; line-height: 1.6;">
                    <p><strong>✅ Encouraged:</strong></p>
                    <ul style="margin: 0.5rem 0; font-size: 0.85rem;">
                        <li>Academic discussions</li>
                        <li>Project showcases</li>
                        <li>Learning experiences</li>
                        <li>Helpful resources</li>
                    </ul>
                    
                    <p><strong>❌ Not Allowed:</strong></p>
                    <ul style="margin: 0.5rem 0; font-size: 0.85rem;">
                        <li>Spam or promotional content</li>
                        <li>Offensive language</li>
                        <li>Plagiarized content</li>
                        <li>Off-topic discussions</li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
// Auto-save functionality
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
        formData.append('post_id', '<?php echo $post_id; ?>');
        formData.append('auto_save', '1');
        
        fetch('actions/auto_save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Draft auto-saved');
                showAutoSaveIndicator();
            }
        })
        .catch(error => console.error('Auto-save error:', error));
    }
}

function showAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #2ecc71; color: white; padding: 8px 12px; border-radius: 4px; font-size: 0.85rem; z-index: 1000;';
    indicator.textContent = '✓ Changes saved';
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
let formChanged = false;
const form = document.querySelector('form');
const formElements = form.querySelectorAll('input, textarea, select');

formElements.forEach(element => {
    element.addEventListener('change', () => {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Remove warning when form is submitted
form.addEventListener('submit', function() {
    formChanged = false;
});

// Show character count for content
const contentTextarea = document.getElementById('content');
const maxLength = 5000;

function updateCharacterCount() {
    const currentLength = contentTextarea.value.length;
    const remaining = maxLength - currentLength;
    
    let counter = document.getElementById('char-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'char-counter';
        counter.style.cssText = 'font-size: 0.85rem; margin-top: 0.3rem; text-align: right;';
        contentTextarea.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/${maxLength} characters`;
    counter.style.color = remaining < 100 ? '#e74c3c' : '#666';
}

contentTextarea.addEventListener('input', updateCharacterCount);
updateCharacterCount(); // Initial count

// Form validation feedback
form.addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loading"></span> Updating...';
        submitBtn.disabled = true;
        
        // Re-enable after 3 seconds as fallback
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>