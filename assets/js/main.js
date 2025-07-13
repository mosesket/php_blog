// TPI Blog Community JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeBlog();
});

function initializeBlog() {
    setupLikeButtons();
    setupCommentForms();
    setupPostForm();
    setupSearch();
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
        resultsContainer.innerHTML = '<p>No results found</p>';
        return;
    }

    const resultsHtml = results.map(post => `
        <div class="search-result">
            <h4><a href="post.php?id=${post.id}">${post.title}</a></h4>
            <p>${post.excerpt}</p>
            <small>by ${post.author} • ${post.created_at}</small>
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

// Initialize character counters
document.addEventListener('DOMContentLoaded', setupCharacterCounters);

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

document.addEventListener('DOMContentLoaded', setupAutoExpandTextareas);