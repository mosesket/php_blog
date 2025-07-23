// TPI Blog Community JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeBlog();
});

function initializeBlog() {
    setupLikeButtons();
    setupCommentForms();
    setupPostForm();
    setupSearch();
    setupAutoExpandTextareas();
    setupCharacterCounters();
}

// Like functionality
function setupLikeButtons() {
    const likeButtons = document.querySelectorAll('.like-btn');
    
    likeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;
            toggleLike(postId, this);
        });
    });
}

function toggleLike(postId, button) {
    // Check if user is logged in
    if (!isUserLoggedIn()) {
        showAlert('Please login to like posts', 'error');
        return;
    }

    // Show loading state
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="loading"></span>';
    button.disabled = true;

    fetch('actions/toggle_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ post_id: postId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button state
            button.classList.toggle('liked');
            const likeCount = button.querySelector('.like-count');
            if (likeCount) {
                likeCount.textContent = data.like_count;
            }
            
            // Update button text
            const isLiked = button.classList.contains('liked');
            button.innerHTML = `❤️ ${isLiked ? 'Liked' : 'Like'} (${data.like_count})`;
        } else {
            showAlert(data.message || 'Error toggling like', 'error');
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error occurred', 'error');
        button.innerHTML = originalText;
    })
    .finally(() => {
        button.disabled = false;
    });
}

// Comment functionality
function setupCommentForms() {
    const commentForms = document.querySelectorAll('.comment-form');
    
    commentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitComment(this);
        });
    });
}

function submitComment(form) {
    const postId = form.querySelector('input[name="post_id"]').value;
    const content = form.querySelector('textarea[name="content"]').value.trim();
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (!content) {
        showAlert('Please enter a comment', 'error');
        return;
    }

    if (!isUserLoggedIn()) {
        showAlert('Please login to comment', 'error');
        return;
    }

    // Show loading state
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="loading"></span> Posting...';
    submitBtn.disabled = true;

    const formData = new FormData(form);

    fetch('actions/add_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear form
            form.querySelector('textarea[name="content"]').value = '';
            
            // Add new comment to the page
            addCommentToPage(data.comment, postId);
            showAlert('Comment added successfully!', 'success');
        } else {
            showAlert(data.message || 'Error adding comment', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error occurred', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function addCommentToPage(comment, postId) {
    const commentsContainer = document.querySelector(`#comments-${postId}`);
    if (commentsContainer) {
        const commentHtml = `
            <div class="comment">
                <div class="comment-meta">
                    <strong>${comment.username}</strong> • just now
                </div>
                <div class="comment-content">${comment.content}</div>
            </div>
        `;
        commentsContainer.insertAdjacentHTML('beforeend', commentHtml);
    }
}

// Post form functionality
function setupPostForm() {
    const postForm = document.querySelector('#post-form');
    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitPost(this);
        });
    }
}

function submitPost(form) {
    const title = form.querySelector('input[name="title"]').value.trim();
    const content = form.querySelector('textarea[name="content"]').value.trim();
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (!title || !content) {
        showAlert('Please fill in all required fields', 'error');
        return;
    }

    // Show loading state
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="loading"></span> Publishing...';
    submitBtn.disabled = true;

    const formData = new FormData(form);

    fetch('actions/create_post.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Post published successfully!', 'success');
            // Redirect to the new post or home page
            setTimeout(() => {
                window.location.href = data.redirect || 'index.php';
            }, 1500);
        } else {
            showAlert(data.message || 'Error creating post', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error occurred', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Search functionality
function setupSearch() {
    const searchInput = document.querySelector('#search-input');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 500);
        });
    }
}

function performSearch(query) {
    if (query.length < 2) {
        clearSearchResults();
        return;
    }

    fetch(`actions/search.php?q=${encodeURIComponent(query)}`)
    .then(response => response.json())
    .then(data => {
        displaySearchResults(data.results);
    })
    .catch(error => {
        console.error('Search error:', error);
    });
}

function displaySearchResults(results) {
    const resultsContainer = document.querySelector('#search-results');
    if (!resultsContainer) return;

    if (results.length === 0) {
        resultsContainer.innerHTML = '<p style="padding: 1rem; text-align: center; color: #666;">No results found</p>';
        return;
    }

    const resultsHtml = results.map(post => `
        <div class="search-result">
            <h4><a href="post.php?id=${post.id}">${post.title}</a></h4>
            <p>${post.excerpt}</p>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                <small>by ${post.author} • ${post.created_at}</small>
                <div style="display: flex; gap: 1rem; font-size: 0.8rem; color: #666;">
                    <span>❤️ ${post.like_count}</span>
                    <span>💬 ${post.comment_count}</span>
                    ${post.has_media ? `<span>📎 ${post.media_count}</span>` : ''}
                </div>
            </div>
        </div>
    `).join('');

    resultsContainer.innerHTML = resultsHtml;
}

function clearSearchResults() {
    const resultsContainer = document.querySelector('#search-results');
    if (resultsContainer) {
        resultsContainer.innerHTML = '';
    }
}

// Utility functions
function isUserLoggedIn() {
    // This would typically check for a session or auth token
    return document.body.dataset.loggedIn === 'true';
}

function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());

    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;

    // Insert at top of main content
    const mainContent = document.querySelector('.content') || document.body;
    mainContent.insertBefore(alertDiv, mainContent.firstChild);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Character counter for textareas
function setupCharacterCounters() {
    const textareas = document.querySelectorAll('textarea[data-max-length]');
    
    textareas.forEach(textarea => {
        const maxLength = parseInt(textarea.dataset.maxLength);
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        textarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${remaining} characters remaining`;
            counter.style.color = remaining < 50 ? '#e74c3c' : '#666';
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });
}

// Auto-expand textareas
function setupAutoExpandTextareas() {
    const textareas = document.querySelectorAll('textarea.auto-expand');
    
    textareas.forEach(textarea => {
        function resize() {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
        
        textarea.addEventListener('input', resize);
        resize(); // Initial resize
    });
}

// Media upload functionality (for create/edit post pages)
function setupMediaUpload() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const mediaPreview = document.getElementById('media-preview');
    
    if (!uploadArea || !fileInput || !mediaPreview) return;
    
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
    for (let file of files) {
        if (file.size > 50 * 1024 * 1024) {
            showAlert(`File "${file.name}" is too large (max 50MB)`, 'error');
            continue;
        }
        
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 
                             'video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov'];
        
        if (!allowedTypes.includes(file.type)) {
            showAlert(`File "${file.name}" is not a supported format`, 'error');
            continue;
        }
        
        uploadFile(file);
    }
}

function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    // Create preview element
    const previewId = 'preview-' + Date.now();
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
            if (window.uploadedMedia) {
                window.uploadedMedia.push(data.media_id);
                updateMediaIds();
            }
            updatePreviewElement(previewId, data);
        } else {
            showAlert(data.message || 'Upload failed', 'error');
            document.getElementById(previewId).remove();
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showAlert('Network error occurred', 'error');
        document.getElementById(previewId).remove();
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
                `<video src="${fileUrl}" controls style="width: 100%; height: 100%; object-fit: cover;"></video>` :
                `<img src="${fileUrl}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`
            }
            <div class="upload-progress">
                <div class="loading"></div>
                <small>Uploading...</small>
            </div>
        </div>
        <div class="media-info">
            <strong>${file.name}</strong>
            <small>${formatFileSize(file.size)}</small>
        </div>
        <button type="button" class="remove-media" onclick="removeMedia('${previewId}', null)">×</button>
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
    
    if (mediaId && window.uploadedMedia) {
        const index = window.uploadedMedia.indexOf(mediaId);
        if (index > -1) {
            window.uploadedMedia.splice(index, 1);
            updateMediaIds();
        }
    }
}

function updateMediaIds() {
    const mediaIdsInput = document.getElementById('media-ids');
    if (mediaIdsInput && window.uploadedMedia) {
        mediaIdsInput.value = window.uploadedMedia.join(',');
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Image/Video modal functionality
function openMediaModal(src, type, filename) {
    const modal = document.getElementById('media-modal');
    const modalImage = document.getElementById('modal-image');
    const modalInfo = document.getElementById('modal-info');
    
    if (!modal || !modalImage || !modalInfo) return;
    
    if (type === 'image') {
        modalImage.src = src;
        modalImage.alt = filename;
        modalImage.style.display = 'block';
        modalInfo.innerHTML = `<p><strong>${filename}</strong></p>`;
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeMediaModal() {
    const modal = document.getElementById('media-modal');
    const modalImage = document.getElementById('modal-image');
    
    if (modal && modalImage) {
        modal.style.display = 'none';
        modalImage.style.display = 'none';
        modalImage.src = '';
        document.body.style.overflow = 'auto';
    }
}

// Lazy loading for images
function setupLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
}

// Form validation helpers
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Debounce function for search and other inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeBlog();
    setupMediaUpload();
    setupLazyLoading();
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMediaModal();
        }
    });
    
    // Initialize uploaded media array for create/edit post pages
    if (document.getElementById('media-ids')) {
        window.uploadedMedia = [];
    }
});

// Export functions for global use
window.openMediaModal = openMediaModal;
window.closeMediaModal = closeMediaModal;
window.removeMedia = removeMedia;
window.showAlert = showAlert;