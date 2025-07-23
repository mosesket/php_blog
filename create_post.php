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
    $media_ids = isset($_POST['media_ids']) ? explode(',', $_POST['media_ids']) : [];
    
    if (empty($title) || empty($content)) {
        $error = 'Please fill in title and content';
    } else {
        $database = new Database();
        $db = $database->connect();
        $user = getCurrentUser();
        
        try {
            $db->beginTransaction();
            
            $has_media = !empty($media_ids) && !empty(array_filter($media_ids));
            $insert_query = "
                INSERT INTO posts (user_id, title, content, category, tags, status, has_media) 
                VALUES (:user_id, :title, :content, :category, :tags, :status, :has_media)
            ";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user['id']);
            $insert_stmt->bindParam(':title', $title);
            $insert_stmt->bindParam(':content', $content);
            $insert_stmt->bindParam(':category', $category);
            $insert_stmt->bindParam(':tags', $tags);
            $insert_stmt->bindParam(':status', $status);
            $insert_stmt->bindParam(':has_media', $has_media, PDO::PARAM_BOOL);
            
            if ($insert_stmt->execute()) {
                $post_id = $db->lastInsertId();
                
                // Link media files to post
                if ($has_media) {
                    $media_query = "INSERT INTO post_media (post_id, media_id, display_order) VALUES (:post_id, :media_id, :order)";
                    $media_stmt = $db->prepare($media_query);
                    
                    foreach ($media_ids as $index => $media_id) {
                        $media_id = trim($media_id);
                        if (!empty($media_id) && is_numeric($media_id)) {
                            $media_stmt->bindParam(':post_id', $post_id);
                            $media_stmt->bindParam(':media_id', $media_id);
                            $media_stmt->bindParam(':order', $index);
                            $media_stmt->execute();
                        }
                    }
                }
                
                $db->commit();
                
                if ($status === 'published') {
                    $success = 'Post published successfully!';
                    echo "<script>setTimeout(() => window.location.href = 'post.php?id=$post_id', 2000);</script>";
                } else {
                    $success = 'Post saved as draft!';
                    echo "<script>setTimeout(() => window.location.href = 'my_posts.php', 2000);</script>";
                }
            } else {
                throw new Exception('Failed to create post');
            }
        } catch (Exception $e) {
            $db->rollBack();
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
                
                <!-- Media Upload Section -->
                <div class="form-group">
                    <label>📎 Attach Images or Videos</label>
                    <div class="media-upload-container">
                        <div class="upload-area" id="upload-area">
                            <div class="upload-placeholder">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📁</div>
                                <p><strong>Drag & drop files here</strong></p>
                                <p>or <button type="button" class="btn-link" onclick="document.getElementById('file-input').click()">browse files</button></p>
                                <small>Supports: Images (JPG, PNG, GIF, WebP) and Videos (MP4, WebM, AVI, MOV) - Max 50MB per file</small>
                            </div>
                            <input type="file" id="file-input" multiple accept="image/*,video/*" style="display: none;">
                        </div>
                        <div id="media-preview" class="media-preview"></div>
                        <input type="hidden" id="media-ids" name="media_ids" value="">
                    </div>
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
                    <li style="margin-bottom: 0.5rem;">📸 <strong>Media Files:</strong> Add images/videos to make posts more engaging</li>
                    <li>✅ <strong>Proofread:</strong> Check for spelling and grammar</li>
                </ul>
            </div>
            
            <!-- Media Guidelines Widget -->
            <div class="widget">
                <h3>📎 Media Guidelines</h3>
                <div style="font-size: 0.9rem; line-height: 1.6;">
                    <p><strong>✅ Supported Formats:</strong></p>
                    <ul style="margin: 0.5rem 0; font-size: 0.85rem;">
                        <li><strong>Images:</strong> JPG, PNG, GIF, WebP</li>
                        <li><strong>Videos:</strong> MP4, WebM, AVI, MOV</li>
                    </ul>
                    
                    <p><strong>📏 File Limits:</strong></p>
                    <ul style="margin: 0.5rem 0; font-size: 0.85rem;">
                        <li>Maximum 50MB per file</li>
                        <li>No limit on number of files</li>
                        <li>Files are automatically optimized</li>
                    </ul>
                    
                    <p><strong>💡 Tips:</strong></p>
                    <ul style="margin: 0.5rem 0; font-size: 0.85rem;">
                        <li>Use descriptive file names</li>
                        <li>Compress large files before upload</li>
                        <li>Test videos work properly</li>
                    </ul>
                </div>
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
                    <p><strong>Media:</strong> Drag & drop or click to upload files</p>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
// Media upload functionality
let uploadedMedia = [];

document.addEventListener('DOMContentLoaded', function() {
    setupMediaUpload();
});

function setupMediaUpload() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const mediaPreview = document.getElementById('media-preview');
    
    if (!uploadArea || !fileInput || !mediaPreview) return;
    
    // Initialize global array
    window.uploadedMedia = uploadedMedia;
    
    // Drag and drop functionality
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });
    
    // File input change
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });
}

function handleFiles(files) {
    Array.from(files).forEach(file => {
        if (file.size > 50 * 1024 * 1024) {
            showAlert(`File "${file.name}" is too large (max 50MB)`, 'error');
            return;
        }
        
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 
                             'video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov'];
        
        if (!allowedTypes.includes(file.type)) {
            showAlert(`File "${file.name}" is not a supported format`, 'error');
            return;
        }
        
        uploadFile(file);
    });
}

function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    // Create preview element
    const previewId = 'preview-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    const previewElement = createPreviewElement(file, previewId);
    document.getElementById('media-preview').appendChild(previewElement);
    
    // Upload file
    fetch('actions/upload_media.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            uploadedMedia.push(data.media_id);
            updateMediaIds();
            updatePreviewElement(previewId, data);
            showAlert('File uploaded successfully!', 'success');
        } else {
            showAlert(data.message || 'Upload failed', 'error');
            const element = document.getElementById(previewId);
            if (element) element.remove();
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showAlert('Network error occurred', 'error');
        const element = document.getElementById(previewId);
        if (element) element.remove();
    });
}

function createPreviewElement(file, previewId) {
    const div = document.createElement('div');
    div.id = previewId;
    div.className = 'media-preview-item uploading';
    
    const isVideo = file.type.startsWith('video/');
    const fileUrl = URL.createObjectURL(file);
    
    div.innerHTML = `
        <div class="media-thumbnail">
            ${isVideo ? 
                `<video src="${fileUrl}" preload="metadata" style="width: 100%; height: 100%; object-fit: cover;"></video>` :
                `<img src="${fileUrl}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`
            }
            <div class="upload-progress">
                <div class="loading"></div>
                <small>Uploading...</small>
            </div>
        </div>
        <div class="media-info">
            <strong title="${file.name}">${file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name}</strong>
            <small>${formatFileSize(file.size)}</small>
        </div>
        <button type="button" class="remove-media" onclick="removeMedia('${previewId}', null)" title="Remove file">×</button>
    `;
    
    return div;
}

function updatePreviewElement(previewId, data) {
    const element = document.getElementById(previewId);
    if (element) {
        element.classList.remove('uploading');
        element.classList.add('uploaded');
        
        const progressDiv = element.querySelector('.upload-progress');
        if (progressDiv) {
            progressDiv.remove();
        }
        
        const removeBtn = element.querySelector('.remove-media');
        if (removeBtn) {
            removeBtn.setAttribute('onclick', `removeMedia('${previewId}', ${data.media_id})`);
        }
    }
}

function removeMedia(previewId, mediaId) {
    const element = document.getElementById(previewId);
    if (element) {
        element.remove();
    }
    
    if (mediaId) {
        const index = uploadedMedia.indexOf(mediaId);
        if (index > -1) {
            uploadedMedia.splice(index, 1);
            updateMediaIds();
        }
    }
    
    showAlert('File removed', 'success');
}

function updateMediaIds() {
    const mediaIdsInput = document.getElementById('media-ids');
    if (mediaIdsInput) {
        mediaIdsInput.value = uploadedMedia.join(',');
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

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
        formData.append('media_ids', document.getElementById('media-ids').value);
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
    // Remove existing indicator
    const existing = document.getElementById('auto-save-indicator');
    if (existing) existing.remove();
    
    const indicator = document.createElement('div');
    indicator.id = 'auto-save-indicator';
    indicator.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #2ecc71; color: white; padding: 8px 12px; border-radius: 4px; font-size: 0.85rem; z-index: 1000; transition: opacity 0.3s;';
    indicator.textContent = '✓ Draft saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.style.opacity = '0';
        setTimeout(() => indicator.remove(), 300);
    }, 2000);
}

// Start auto-save timer when user starts typing
['title', 'content', 'category', 'tags'].forEach(id => {
    const element = document.getElementById(id);
    if (element) {
        element.addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSaveDraft, autoSaveInterval);
        });
    }
});

// Warn user about unsaved changes
window.addEventListener('beforeunload', function(e) {
    const title = document.getElementById('title').value.trim();
    const content = document.getElementById('content').value.trim();
    
    if (title || content || uploadedMedia.length > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Remove warning when form is submitted
document.getElementById('post-form').addEventListener('submit', function() {
    window.removeEventListener('beforeunload', arguments.callee);
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loading"></span> Creating...';
        submitBtn.disabled = true;
        
        // Re-enable after 10 seconds as fallback
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 10000);
    }
});

// Character counter for content textarea
const contentTextarea = document.getElementById('content');
if (contentTextarea) {
    const maxLength = 5000;
    
    function updateCharacterCount() {
        const currentLength = contentTextarea.value.length;
        const remaining = maxLength - currentLength;
        
        let counter = document.getElementById('char-counter');
        if (!counter) {
            counter = document.createElement('div');
            counter.id = 'char-counter';
            counter.className = 'char-counter';
            contentTextarea.parentNode.appendChild(counter);
        }
        
        counter.textContent = `${currentLength}/${maxLength} characters`;
        counter.style.color = remaining < 100 ? '#e74c3c' : '#666';
        
        if (remaining < 0) {
            contentTextarea.style.borderColor = '#e74c3c';
        } else {
            contentTextarea.style.borderColor = '#ddd';
        }
    }
    
    contentTextarea.addEventListener('input', updateCharacterCount);
    updateCharacterCount(); // Initial count
}

// Make uploadedMedia and other functions available globally
window.uploadedMedia = uploadedMedia;
window.removeMedia = removeMedia;
</script>

<?php require_once 'includes/footer.php'; ?>